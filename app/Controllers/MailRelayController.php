<?php

namespace App\Controllers;

use App\Libraries\MailOutbox;
use App\Libraries\SystemSettings;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API for the "NEXUS-L Outlook Email Relay" Windows application (C#, D:\desktop\MaterialTest\NexusOutlookRelay).
 * The Windows app connects to Microsoft Outlook desktop and delivers the queued e-mails (tbl_email_outbox) in FIFO order.
 * Authentication: the token in Settings > E-mail (mail.relay_token), in the X-Relay-Token header or as Authorization: Bearer <token>.
 *
 * Who sends is Settings > "Who sends the e-mails" (mail.transport): auto / relay / handler. The queue is claimed row by row, so the
 * Relay and the Server HTML Handler can never send the same e-mail twice.
 */
class MailRelayController extends BaseController
{
    private const STALE_MINUTES = 10;           // a row 'sending' for longer than this was lost by its sender: back to the queue

    private function denied(): ?ResponseInterface
    {
        $want = (string) SystemSettings::get('mail.relay_token', '');
        $sent = (string) $this->request->getHeaderLine('X-Relay-Token');
        if ($sent === '') {
            $auth = (string) $this->request->getHeaderLine('Authorization');
            if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
                $sent = $m[1];
            }
        }

        if ($want === '' || ! hash_equals($want, $sent)) {
            return $this->response->setStatusCode(401)->setJSON([
                'ok'    => false,
                'error' => $want === '' ? 'No Outlook Relay token is configured in NEXUS-L (Settings > E-mail).' : 'Invalid or expired relay token.',
            ]);
        }

        return null;
    }

    private function fail(string $msg, int $code = 422): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON(['ok' => false, 'error' => $msg]);
    }

    /** GET mail-relay/ping: is the token right, and the settings the Relay follows */
    public function ping(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        MailOutbox::ensureTable();
        MailOutbox::touchRelay();
        $db = \Config\Database::connect();

        return $this->response->setJSON([
            'ok'                   => true,
            'service'              => 'NEXUS-L Mail Relay Hub',
            'server_time'          => date('c'),
            'queue_count'          => (int) $db->table('tbl_email_outbox')->where('status', 'queued')->countAllResults(),
            'default_sender_name'  => (string) SystemSettings::get('mail.sender_name', 'Nexus-L'),
            'default_sender_email' => (string) SystemSettings::get('mail.sender_email', 'Nexus-Noreply@outlook.com'),
            'config'               => MailOutbox::relayConfig(),
            'routing'              => \App\Libraries\PayloadRouting::info($this->request),
        ]);
    }

    /** GET mail-relay/config: the settings the Relay follows (the admin changes them under Settings > E-mail) */
    public function config(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        MailOutbox::touchRelay();

        return $this->response->setJSON(['ok' => true, 'config' => MailOutbox::relayConfig(), 'routing' => \App\Libraries\PayloadRouting::info($this->request)]);
    }

    /** POST mail-relay/heartbeat: the Relay says it is alive and what Outlook it uses (shown under Settings > E-mail) */
    public function heartbeat(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        $j = $this->request->getJSON(true) ?: [];
        MailOutbox::touchRelay([
            'version'           => mb_substr((string) ($j['version'] ?? ''), 0, 20),
            'host'              => mb_substr((string) ($j['host'] ?? ''), 0, 60),
            'outlook_connected' => ! empty($j['outlook_connected']),
            'account'           => mb_substr((string) ($j['account'] ?? ''), 0, 120),
            'smtp'              => mb_substr((string) ($j['smtp'] ?? ''), 0, 150),
            'sent_session'      => (int) ($j['sent_session'] ?? 0),
            'failed_session'    => (int) ($j['failed_session'] ?? 0),
            'running'           => ! empty($j['running']),
            'error'             => mb_substr((string) ($j['error'] ?? ''), 0, 200),
        ]);

        return $this->response->setJSON(['ok' => true, 'config' => MailOutbox::relayConfig(), 'routing' => \App\Libraries\PayloadRouting::info($this->request)]);
    }

    /** GET mail-relay/queue: the oldest due e-mails, FIFO. Each one is claimed ('sending') before it is handed out. */
    public function queue(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        MailOutbox::ensureTable();
        MailOutbox::touchRelay();
        $cfg = MailOutbox::relayConfig();
        $routing = \App\Libraries\PayloadRouting::info($this->request);
        if ($cfg['transport'] === 'handler') {
            return $this->response->setJSON(['ok' => true, 'count' => 0, 'items' => [], 'note' => 'Settings say the Server HTML Handler sends the e-mails.', 'config' => $cfg, 'routing' => $routing]);
        }
        if (! $routing['is_target']) {
            return $this->response->setJSON(['ok' => true, 'count' => 0, 'items' => [], 'note' => $routing['note'], 'config' => $cfg, 'routing' => $routing]);
        }

        $db    = \Config\Database::connect();
        $limit = max(1, min(50, (int) ($this->request->getGet('limit') ?: 10)));
        $now   = date('Y-m-d H:i:s');

        // rows lost by a sender that died: back to the queue
        $db->query("UPDATE tbl_email_outbox SET status = 'queued' WHERE status = 'sending' AND (claimed_at IS NULL OR claimed_at < ?)", [date('Y-m-d H:i:s', time() - self::STALE_MINUTES * 60)]);

        // the daily limit
        if ($cfg['daily_limit'] > 0) {
            $today = (int) $db->table('tbl_email_outbox')->where('status', 'sent')->where('sent_at >=', date('Y-m-d 00:00:00'))->countAllResults();
            $limit = min($limit, max(0, $cfg['daily_limit'] - $today));
            if ($limit === 0) {
                return $this->response->setJSON(['ok' => true, 'count' => 0, 'items' => [], 'note' => 'The daily limit of ' . $cfg['daily_limit'] . ' e-mails is reached.', 'config' => $cfg, 'routing' => $routing]);
            }
        }

        $rows = $db->query("SELECT * FROM tbl_email_outbox WHERE status = 'queued' AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY id ASC LIMIT " . ($limit * 3), [$now])->getResultArray();

        $allowed = array_filter(array_map(static fn ($d) => strtolower(trim($d)), preg_split('/[;,\s]+/', (string) SystemSettings::get('mail.relay_allowed_domains', '')) ?: []));
        $items   = [];
        foreach ($rows as $r) {
            if (count($items) >= $limit) {
                break;
            }
            // recipients outside the allowed domains are refused here (the e-mail is not sent)
            if ($allowed) {
                $bad = [];
                foreach (MailOutbox::addresses((string) $r['to_address'] . ',' . (string) $r['cc_address']) as $a) {
                    if (! in_array(strtolower(substr(strrchr($a, '@') ?: '', 1)), $allowed, true)) {
                        $bad[] = $a;
                    }
                }
                if ($bad) {
                    $db->table('tbl_email_outbox')->where('id', $r['id'])->where('status', 'queued')->update(['status' => 'failed', 'attempts' => (int) $r['attempts'] + 1, 'last_error' => 'Recipient not in the allowed domains: ' . implode(', ', $bad)]);
                    continue;
                }
            }
            // claim: only one sender wins this row
            $db->query("UPDATE tbl_email_outbox SET status = 'sending', attempts = attempts + 1, claimed_at = ?, claimed_by = 'relay' WHERE id = ? AND status = 'queued'", [$now, $r['id']]);
            if ($db->affectedRows() !== 1) {
                continue;
            }
            $r['status']      = 'sending';
            $r['attempts']    = (int) $r['attempts'] + 1;
            $r['bcc_address'] = $cfg['bcc'];
            $r['dry_run']     = $cfg['dry_run'] ? 1 : 0;
            $items[]          = $r;
        }

        return $this->response->setJSON(['ok' => true, 'count' => count($items), 'items' => $items, 'config' => $cfg, 'routing' => $routing]);
    }

    /** POST mail-relay/update-status {id, status: sent|failed|queued, error_message}: a failed e-mail goes back to the queue until the attempts are used up */
    public function updateStatus(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $json   = $this->request->getJSON(true) ?: [];
        $id     = (int) ($json['id'] ?? 0);
        $status = strtolower(trim((string) ($json['status'] ?? '')));
        $error  = mb_substr(trim((string) ($json['error_message'] ?? ($json['last_error'] ?? ''))), 0, 2000);

        if ($id <= 0 || ! in_array($status, ['sent', 'failed', 'queued'], true)) {
            return $this->fail('Valid job ID and status (sent, failed, queued) are required.');
        }

        $db  = \Config\Database::connect();
        $row = $db->table('tbl_email_outbox')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return $this->fail('That e-mail does not exist.', 404);
        }
        $update = ['status' => $status, 'last_error' => $error ?: null];
        $final  = $status;

        if ($status === 'sent') {
            $update['sent_at']         = date('Y-m-d H:i:s');
            $update['last_error']      = null;
            $update['next_attempt_at'] = null;
        } elseif ($status === 'failed') {
            $cfg = MailOutbox::relayConfig();
            if ((int) $row['attempts'] < $cfg['max_attempts']) {          // try again later: 5, 10, 15 ... minutes
                $final                     = 'queued';
                $update['status']          = 'queued';
                $wait                      = max(1, (int) SystemSettings::get('mail.relay_retry_minutes', 5)) * max(1, (int) $row['attempts']);
                $update['next_attempt_at'] = date('Y-m-d H:i:s', time() + $wait * 60);
            } else {
                $update['next_attempt_at'] = null;
            }
        } else {
            $update['next_attempt_at'] = null;
        }

        $db->table('tbl_email_outbox')->where('id', $id)->update($update);

        return $this->response->setJSON(['ok' => true, 'id' => $id, 'status' => $final, 'will_retry' => $final === 'queued' && $status === 'failed']);
    }

    /** POST mail-relay/enqueue: adds an e-mail to the queue */
    public function enqueue(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $j = $this->request->getJSON(true) ?: [];
        // a design from the Email Designer: {"template": "general_notice", "vars": {"title": "...", "message": "..."}}
        if (! empty($j['template'])) {
            $vars = is_array($j['vars'] ?? null) ? $j['vars'] : [];
            $html = MailOutbox::render((string) $j['template'], $vars);
            if ($html === null) {
                return $this->fail('No design is uploaded for the template "' . $j['template'] . '" (Settings > E-mail > E-mail designs).');
            }
            $j['body']    = $html;
            $j['subject'] = trim((string) ($j['subject'] ?? '')) !== '' ? $j['subject'] : MailOutbox::subjectOf((string) $j['template'], $vars, 'NEXUS-L');
            $j['is_html'] = 1;
        }
        [$id, $err] = MailOutbox::enqueue(
            (string) ($j['to'] ?? ($j['to_address'] ?? '')),
            (string) ($j['subject'] ?? ''),
            (string) ($j['body'] ?? ($j['message'] ?? '')),
            [
                'cc'           => (string) ($j['cc'] ?? ($j['cc_address'] ?? '')),
                'is_html'      => ! array_key_exists('is_html', $j) || ! in_array($j['is_html'], [0, '0', false, 'false'], true),
                'attachments'  => $j['attachments'] ?? null,
                'sender_name'  => (string) ($j['sender_name'] ?? ''),
                'sender_email' => (string) ($j['sender_email'] ?? ''),
                'source'       => (string) ($j['source'] ?? 'NEXUS-L'),
            ]
        );
        if ($id === 0) {
            return $this->fail($err);
        }

        return $this->response->setJSON(['ok' => true, 'id' => $id, 'message' => 'Email queued successfully (FIFO ID: #' . $id . ').']);
    }

    /** GET mail-relay/stats */
    public function stats(): ResponseInterface
    {
        if ($denied = $this->denied()) {
            return $denied;
        }
        MailOutbox::ensureTable();
        MailOutbox::touchRelay();
        $db  = \Config\Database::connect();
        $out = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($db->query('SELECT status, COUNT(*) c FROM tbl_email_outbox GROUP BY status')->getResultArray() as $r) {
            $out[$r['status']] = (int) $r['c'];
        }

        return $this->response->setJSON(['ok' => true] + $out + ['total' => array_sum($out)]);
    }
}
