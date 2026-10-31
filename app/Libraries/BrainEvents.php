<?php

namespace App\Libraries;

/**
 * The live record behind the Brain Universe page: every question (and which brain answered it), every new or changed piece of knowledge,
 * every thumbs vote and every training run, as one small row. The page polls it every second or two and animates the new rows.
 * Questions are stored shortened and redacted; no user name is ever stored here.
 */
class BrainEvents
{
    private static function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        self::db()->query('CREATE TABLE IF NOT EXISTS tbl_bot_brain_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            kind VARCHAR(16) NOT NULL,
            brain VARCHAR(12) NOT NULL DEFAULT "",
            kb_id INT UNSIGNED NULL,
            conf SMALLINT NULL,
            label VARCHAR(160) NOT NULL DEFAULT "",
            KEY idx_kind_time (kind, created_at)
        ) DEFAULT CHARSET=utf8mb4');
    }

    /** kind: ask | learn | feedback | train | correct | import */
    public static function log(string $kind, string $brain = '', ?int $kbId = null, ?int $conf = null, string $label = ''): void
    {
        try {
            self::ensureTable();
            $db = self::db();
            $db->table('tbl_bot_brain_events')->insert(['created_at' => date('Y-m-d H:i:s'), 'kind' => $kind, 'brain' => $brain, 'kb_id' => $kbId, 'conf' => $conf, 'label' => mb_substr(Brain::redact($label), 0, 160)]);
            if (random_int(1, 200) === 1) {                          // keep the table small
                $db->table('tbl_bot_brain_events')->where('created_at <', date('Y-m-d H:i:s', time() - 86400 * 30))->delete();
            }
        } catch (\Throwable $e) {
            log_message('error', 'BrainEvents: ' . $e->getMessage());
        }
    }

    /** @return array{events:list<array<string,mixed>>,last:int,stats:array<string,mixed>} */
    public static function feed(int $since, int $limit = 60): array
    {
        self::ensureTable();
        $db = self::db();
        if ($since <= 0) {                                           // first load: the most recent ones, oldest first
            $rows = array_reverse($db->table('tbl_bot_brain_events')->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray());
        } else {
            $rows = $db->table('tbl_bot_brain_events')->where('id >', $since)->orderBy('id')->limit(200)->get()->getResultArray();
        }
        $last = $rows ? (int) end($rows)['id'] : $since;

        return ['events' => $rows, 'last' => $last, 'stats' => self::stats()];
    }

    /** @return array<string,mixed> */
    public static function stats(): array
    {
        $db    = self::db();
        $today = date('Y-m-d 00:00:00');
        $by    = [];
        foreach ($db->query("SELECT brain, COUNT(*) n, AVG(conf) c FROM tbl_bot_brain_events WHERE kind = 'ask' AND created_at >= ? GROUP BY brain", [$today])->getResultArray() as $r) {
            $by[$r['brain']] = ['n' => (int) $r['n'], 'conf' => $r['c'] === null ? null : (int) round((float) $r['c'])];
        }

        return [
            'knowledge' => (int) $db->table('tbl_bot_knowledge')->where('status', 'approved')->countAllResults(),
            'asked_today' => array_sum(array_column($by, 'n')),
            'by_brain' => $by,
            'learned_today' => (int) $db->table('tbl_bot_brain_events')->where('kind', 'learn')->where('created_at >=', $today)->countAllResults(),
            'votes_today' => (int) $db->table('tbl_bot_brain_events')->where('kind', 'feedback')->where('created_at >=', $today)->countAllResults(),
            'ai_online' => self::aiOnline(),
        ];
    }

    /** Is the primary brain (the AI service) answering? Checked at most every 8 seconds. */
    public static function aiOnline(): bool
    {
        $c = cache('brain_ai_online');
        if ($c === null || $c === false) {
            $c = AiBridge::enabled() && AiBridge::get('/health', 700) !== null ? 'y' : 'n';
            cache()->save('brain_ai_online', $c, 8);
        }

        return $c === 'y';
    }

    /** The topic a piece of knowledge belongs to: decides which "star cluster" it sits in. */
    public static function bucket(string $title, string $keywords, ?string $page): string
    {
        $t = mb_strtolower($title . ' ' . $keywords . ' ' . (string) $page);
        $rules = [
            'Charts & files' => '/\b(chart|graph|excel|csv|export|png|image|picture|download link|spreadsheet)\b/',
            'InterLink service' => '/\b(interlink service|service computer|queue|installer|tray|shared key|sign in to interlink|background service)\b/',
            'InterLink' => '/\b(interlink|upload form|sender|extension|lab ref.*attach|submit)\b/',
            'Bonding' => '/\b(bonding|shoe|u60|shb|machine file|diagram)\b/',
            'Materials & tests' => '/\b(fabric|leather|rubber|material|test|reading|rating|standard|thickness|abrasion|tensile|hardware|label|insole|foam|wst|fthl)\b/',
            'Approvals & reports' => '/\b(approval|approve|signat|sign|report|pdf|preview|print|lab tracker|lab ref)\b/',
            'Settings & admin' => '/\b(settings|admin|database|transfer|synology|defaults|brain|teach|knowledge)\b/',
            'Chat & inbox' => '/\b(inbox|chat|message|emoji|theme|assistant|bot)\b/',
            'Errors & fixes' => '/\b(error|fail|problem|cannot|can\'t|not working|slow|blank|stuck|timeout|lag)\b/',
            'Account & access' => '/\b(login|password|account|role|access|user|permission|logout)\b/',
            'Designer' => '/\b(designer|layout|template|canvas|field)\b/',
        ];
        foreach ($rules as $name => $re) {
            if (preg_match($re, $t)) {
                return $name;
            }
        }

        return 'General';
    }

    /** @return list<array<string,mixed>> one row per approved answer: where it sits and how alive it is */
    public static function nodes(): array
    {
        $rows = self::db()->table('tbl_bot_knowledge')->select('id, title, keywords, page_match, kind, source, hits, helpful, created_at, updated_at')->where('status', 'approved')->orderBy('id')->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int) $r['id'], 't' => mb_substr((string) $r['title'], 0, 90), 'c' => self::bucket((string) $r['title'], (string) $r['keywords'], $r['page_match']),
                'h' => (int) $r['hits'], 'u' => (int) $r['helpful'], 'k' => (string) $r['kind'], 'src' => (string) $r['source'], 'ts' => strtotime((string) ($r['updated_at'] ?: $r['created_at'])) ?: 0];
        }

        return $out;
    }
}
