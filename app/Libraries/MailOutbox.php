<?php

namespace App\Libraries;

/**
 * The e-mail outbox (tbl_email_outbox): NEXUS-L only INSERTs a row; a sender delivers it. Two senders exist:
 *   - the NEXUS-L Outlook Email Relay (Windows app, through Outlook; talks to MailRelayController), and
 *   - the Server HTML Handler tray program (SMTP / Microsoft account), the fallback.
 * Settings > E-mail (mail.transport) decides who sends. Everything that queues an e-mail goes through enqueue() here.
 */
class MailOutbox
{
    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_email_outbox (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(50) NOT NULL DEFAULT \'NEXUS-L\',
            sender_name VARCHAR(100) NULL,
            sender_email VARCHAR(150) NULL,
            to_address TEXT NOT NULL,
            cc_address TEXT NULL,
            subject VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            attachments LONGTEXT NULL,
            is_html TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(10) NOT NULL DEFAULT \'queued\',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            claimed_at DATETIME NULL,
            claimed_by VARCHAR(20) NULL,
            next_attempt_at DATETIME NULL,
            sent_at DATETIME NULL,
            KEY idx_outbox_status (status, next_attempt_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        // a table made earlier by the Handler or an older version may miss columns
        $have = array_column($db->query('SHOW COLUMNS FROM tbl_email_outbox')->getResultArray(), 'Field');
        $add  = ['sender_name' => 'VARCHAR(100) NULL', 'sender_email' => 'VARCHAR(150) NULL', 'attachments' => 'LONGTEXT NULL',
            'claimed_at' => 'DATETIME NULL', 'claimed_by' => 'VARCHAR(20) NULL'];
        foreach ($add as $col => $def) {
            if (! in_array($col, $have, true)) {
                $db->query("ALTER TABLE tbl_email_outbox ADD COLUMN {$col} {$def}");
            }
        }
        self::$ready = true;
    }

    /** @return list<string> the valid addresses of "a@x.com; b@y.com, c@z.com" */
    public static function addresses(string $list): array
    {
        $out = [];
        foreach (preg_split('/[;,\s]+/', $list) ?: [] as $a) {
            $a = trim($a);
            if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($a)] = $a;
            }
        }

        return array_values($out);
    }

    /**
     * Queues one e-mail. Returns [id, error]: id > 0 when queued.
     *
     * @param array{cc?:string,is_html?:bool,attachments?:mixed,sender_name?:string,sender_email?:string,source?:string} $opt
     *
     * @return array{0:int,1:string}
     */
    public static function enqueue(string $to, string $subject, string $body, array $opt = []): array
    {
        $toList = self::addresses($to);
        if ($toList === []) {
            return [0, 'No valid recipient address.'];
        }
        $subject = trim($subject);
        if ($subject === '' || trim($body) === '') {
            return [0, 'A subject and a message are required.'];
        }
        $att = $opt['attachments'] ?? null;
        $attJson = is_array($att) ? json_encode($att) : (is_string($att) && $att !== '' ? $att : null);
        if ($attJson !== null && strlen($attJson) > 30 * 1024 * 1024) {
            return [0, 'The attachments are over 30 MB.'];
        }
        $cc = self::addresses((string) ($opt['cc'] ?? ''));
        self::ensureTable();
        $db = \Config\Database::connect();
        $db->table('tbl_email_outbox')->insert([
            'source'       => mb_substr((string) ($opt['source'] ?? 'NEXUS-L'), 0, 50),
            'sender_name'  => trim((string) ($opt['sender_name'] ?? '')) ?: null,
            'sender_email' => trim((string) ($opt['sender_email'] ?? '')) ?: null,
            'to_address'   => implode(', ', $toList),
            'cc_address'   => $cc ? implode(', ', $cc) : null,
            'subject'      => mb_substr($subject, 0, 250),
            'body'         => $body,
            'attachments'  => $attJson,
            'is_html'      => array_key_exists('is_html', $opt) && ! $opt['is_html'] ? 0 : 1,
            'status'       => 'queued',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return [(int) $db->insertID(), ''];
    }

    /** the settings the Relay app follows, as the Relay receives them */
    public static function relayConfig(): array
    {
        $g = static fn (string $k, $d) => SystemSettings::get($k, $d);

        return [
            'transport'        => (string) $g('mail.transport', 'auto'),
            'poll_seconds'     => max(1, min(60, (int) $g('mail.relay_poll_seconds', 3))),
            'throttle_seconds' => max(0, min(120, (int) $g('mail.relay_throttle_seconds', 2))),
            'daily_limit'      => max(0, (int) $g('mail.relay_daily_limit', 0)),
            'dry_run'          => in_array(strtolower((string) $g('mail.relay_dry_run', '0')), ['1', 'true', 'yes', 'on'], true),
            'bcc'              => trim((string) $g('mail.relay_bcc', '')),
            'sender_name'      => (string) $g('mail.sender_name', 'Nexus-L'),
            'sender_email'     => (string) $g('mail.sender_email', 'Nexus-Noreply@outlook.com'),
            'max_attempts'     => max(1, (int) $g('mail.relay_max_attempts', 5)),
        ];
    }

    public static function relayOnline(): bool
    {
        $seen = (string) SystemSettings::get('mail.relay_seen_at', '');

        return $seen !== '' && (time() - (int) strtotime($seen)) <= 45;
    }

    /** remember that the Relay app is alive (at most one write every 10 seconds) */
    public static function touchRelay(?array $info = null): void
    {
        $seen = (string) SystemSettings::get('mail.relay_seen_at', '');
        if ($info === null && $seen !== '' && (time() - (int) strtotime($seen)) < 10) {
            return;
        }
        SystemSettings::set('mail.relay_seen_at', date('Y-m-d H:i:s'), 'relay');
        if ($info !== null) {
            SystemSettings::set('mail.relay_info', (string) json_encode($info), 'relay');
        }
    }

    // ------------------------------------------------------------------------------------------------ e-mail designs (made in the Email Designer app)

    /** the five formats: key => [title, the variables it knows] */
    public const TEMPLATES = [
        'report_sent'      => ['Report sent (Send Report)', ['title', 'message', 'lab_ref', 'date', 'sender', 'images_html']],
        'approval_request' => ['Approval needed (signatory link + QR)', ['name', 'about', 'link', 'minutes', 'qr_img']],
        'test_alert'       => ['Test alert (fail / out of tolerance)', ['lab_ref', 'test', 'result', 'details', 'link', 'date']],
        'daily_summary'    => ['Daily summary', ['date', 'total', 'passed', 'failed', 'pending', 'rows_html', 'link']],
        'general_notice'   => ['General notice / test e-mail', ['title', 'message', 'link', 'button_text', 'date']],
    ];

    public static function templateDir(): string
    {
        $d = WRITEPATH . 'email-templates';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    public static function templatePath(string $slot): ?string
    {
        return isset(self::TEMPLATES[$slot]) ? self::templateDir() . DIRECTORY_SEPARATOR . $slot . '.html' : null;
    }

    public static function hasTemplate(string $slot): bool
    {
        $p = self::templatePath($slot);

        return $p !== null && is_file($p);
    }

    /**
     * The HTML of a design with its {{variables}} filled in, or null when no design was uploaded for that slot.
     * A variable ending in _html or _img is HTML (trusted, built by NEXUS-L); every other one is plain text: escaped, line breaks kept.
     *
     * @param array<string,scalar|null> $vars
     */
    public static function render(string $slot, array $vars): ?string
    {
        $p = self::templatePath($slot);
        if ($p === null || ! is_file($p)) {
            return null;
        }
        $html = (string) file_get_contents($p);

        return self::fill($html, $vars);
    }

    /** @param array<string,scalar|null> $vars */
    public static function fill(string $html, array $vars): string
    {
        return (string) preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/i', static function (array $m) use ($vars): string {
            $k = strtolower($m[1]);
            if (! array_key_exists($k, $vars) || $vars[$k] === null) {
                return '';
            }
            $v = (string) $vars[$k];
            if (str_ends_with($k, '_html') || str_ends_with($k, '_img')) {
                return $v;
            }

            return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), false);
        }, $html);
    }

    /** the subject written in the design's <title> ({{variables}} filled in), or the fallback */
    public static function subjectOf(string $slot, array $vars, string $fallback): string
    {
        $p = self::templatePath($slot);
        if ($p === null || ! is_file($p)) {
            return $fallback;
        }
        if (preg_match('/<title>(.*?)<\/title>/is', (string) file_get_contents($p), $m)) {
            $t = trim(html_entity_decode(strip_tags(self::fill($m[1], array_map(static fn ($v) => is_string($v) ? $v : $v, $vars))), ENT_QUOTES, 'UTF-8'));
            if ($t !== '') {
                return $t;
            }
        }

        return $fallback;
    }
}
