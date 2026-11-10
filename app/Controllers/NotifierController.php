<?php

namespace App\Controllers;

use App\Libraries\Chat\NexusStore;
use App\Libraries\SynologyChat;
use App\Libraries\SystemSettings;

/**
 * API of the "NEXUS-L Notifier" Windows app (C#). The app holds the rules (who gets which alert, when, how often, escalation) and decides;
 * this controller gives it the facts (what failed, what is waiting, the day's numbers), the people directory, and delivers its messages:
 * the Inbox thread "Lab Alerts — NEXUS-L" of each recipient, a Synology Chat DM, and a phone / browser push.
 * No sign-in: the token in Settings > AI > Notifier app token (notify.token) is the key, sent as X-Notifier-Token.
 */
class NotifierController extends BaseController
{
    private function denied()
    {
        $want = (string) SystemSettings::get('notify.token', '');
        $sent = (string) $this->request->getHeaderLine('X-Notifier-Token');
        if ($want === '' || ! hash_equals($want, $sent)) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => $want === '' ? 'No Notifier token is set in NEXUS-L (Settings > AI).' : 'Wrong token']);
        }
        $this->ensureTables();

        return null;
    }

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    private function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $db = $this->db();
        $db->query('SET NAMES utf8mb4');   // the alert texts carry emoji
        $db->query('CREATE TABLE IF NOT EXISTS tbl_notif_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            type VARCHAR(40) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            config LONGTEXT NULL,
            updated_by VARCHAR(80) NOT NULL DEFAULT "",
            updated_at DATETIME NULL,
            created_at DATETIME NULL
        ) DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_notif_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rule_id INT UNSIGNED NOT NULL,
            rule_name VARCHAR(120) NOT NULL DEFAULT "",
            k VARCHAR(190) NOT NULL,
            recipient_id VARCHAR(40) NOT NULL,
            recipient_name VARCHAR(120) NOT NULL DEFAULT "",
            level INT NOT NULL DEFAULT 0,
            title VARCHAR(190) NOT NULL DEFAULT "",
            body TEXT NULL,
            channels VARCHAR(60) NOT NULL DEFAULT "",
            result VARCHAR(255) NOT NULL DEFAULT "",
            ack_code CHAR(24) NOT NULL DEFAULT "",
            sent_at DATETIME NOT NULL,
            ack_at DATETIME NULL,
            UNIQUE KEY uq_rule_key_rcpt_level (rule_id, k, recipient_id, level),
            KEY idx_ack (ack_code),
            KEY idx_sent (sent_at)
        ) DEFAULT CHARSET=utf8mb4');
    }

    private function json(array $d, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($d);
    }

    // ------------------------------------------------------------------------------------------------------------------ status / people
    public function ping()
    {
        if ($r = $this->denied()) {
            return $r;
        }

        return $this->json(['ok' => true, 'server_time' => date('c'), 'tz' => date_default_timezone_get(), 'base' => site_url(), 'routing' => \App\Libraries\PayloadRouting::info($this->request)]);
    }

    /** GET notifier/directory: every active user with his roles, the roles, and the lines / test types seen in the data */
    public function directory()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $db    = $this->db();
        $users = [];
        foreach ($db->query('SELECT UserID, FullName, Username, Role, Department, SynologyID FROM tbl_users WHERE IsActive = 1 ORDER BY FullName')->getResultArray() as $u) {
            $users[] = [
                'id'       => (string) $u['UserID'],
                'name'     => (string) $u['FullName'],
                'username' => (string) $u['Username'],
                'roles'    => array_values(array_filter(array_map('trim', explode(';', (string) $u['Role'])))),
                'dept'     => (string) $u['Department'],
                'synology' => (int) $u['SynologyID'] > 0,
            ];
        }
        $lines = [];
        foreach ($db->query('SELECT DISTINCT Building, `LineNo` AS ln FROM tbl_dtr_bondingtracker ORDER BY Building, `LineNo`')->getResultArray() as $l) {
            $lines[] = trim($l['Building'] . ' ' . $l['ln']);
        }
        $types = array_column($db->query("SELECT DISTINCT TestType t FROM tbl_dtr_bonding_info WHERE TestType IS NOT NULL AND TestType <> ''")->getResultArray(), 't');

        return $this->json(['ok' => true, 'users' => $users, 'roles' => \App\Libraries\Roles::ROLES, 'lines' => $lines, 'test_types' => $types,
            'categories' => array_column($db->query('SELECT DISTINCT form_slug s FROM tbl_dtr_unified_labrefs ORDER BY form_slug')->getResultArray(), 's')]);
    }

    // ------------------------------------------------------------------------------------------------------------------ rules
    public function rules()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $out = [];
        foreach ($this->db()->table('tbl_notif_rules')->orderBy('id')->get()->getResultArray() as $r) {
            $c     = json_decode((string) $r['config'], true) ?: [];
            $out[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'type' => $r['type'], 'enabled' => (bool) $r['enabled'], 'config' => $c, 'updated_at' => $r['updated_at']];
        }

        return $this->json(['ok' => true, 'rules' => $out]);
    }

    /** POST notifier/rule {id?, name, type, enabled, config{...}} */
    public function ruleSave()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $p    = $this->request->getJSON(true) ?? [];
        $name = trim((string) ($p['name'] ?? ''));
        $type = preg_replace('/[^a-z_]/', '', (string) ($p['type'] ?? ''));
        if ($name === '' || $type === '') {
            return $this->json(['ok' => false, 'error' => 'A rule needs a name and a type.'], 422);
        }
        $row = ['name' => mb_substr($name, 0, 120), 'type' => $type, 'enabled' => ! empty($p['enabled']) ? 1 : 0,
            'config' => json_encode($p['config'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE), 'updated_by' => 'Notifier app', 'updated_at' => date('Y-m-d H:i:s')];
        $db = $this->db();
        $id = (int) ($p['id'] ?? 0);
        if ($id > 0 && $db->table('tbl_notif_rules')->where('id', $id)->countAllResults()) {
            $db->table('tbl_notif_rules')->where('id', $id)->update($row);
        } else {
            $db->table('tbl_notif_rules')->insert($row + ['created_at' => date('Y-m-d H:i:s')]);
            $id = (int) $db->insertID();
        }

        return $this->json(['ok' => true, 'id' => $id]);
    }

    public function ruleDelete()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $p = $this->request->getJSON(true) ?? [];
        $this->db()->table('tbl_notif_rules')->where('id', (int) ($p['id'] ?? 0))->delete();

        return $this->json(['ok' => true]);
    }

    // ------------------------------------------------------------------------------------------------------------------ facts
    private static function result(string $r): string
    {
        $r = strtoupper(trim($r));

        return in_array($r, ['PASS', 'PASSED', 'P'], true) ? 'PASS' : (in_array($r, ['FAIL', 'FAILED', 'F'], true) ? 'FAIL' : (in_array($r, ['TOLERABLE', 'TOL', 'TOLE'], true) ? 'TOLERABLE' : ''));
    }

    /** "DTP20261103SHA1-L" -> ['building' => 'A', 'line' => 'A1'] (the building and line letters are part of the Lab Ref) */
    private static function lineOf(string $ref): array
    {
        if (preg_match('/^DTP\d{8}[A-Z]{2,3}([A-Z])(\d+)/', strtoupper($ref), $m)) {
            return ['building' => $m[1], 'line' => $m[1] . $m[2]];
        }

        return ['building' => '', 'line' => ''];
    }

    private function sentKeys(int $ruleId, int $days = 4): array
    {
        $out = [];
        foreach ($this->db()->query('SELECT DISTINCT k FROM tbl_notif_log WHERE rule_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', [$ruleId, $days])->getResultArray() as $r) {
            $out[$r['k']] = true;
        }

        return $out;
    }

    /**
     * GET notifier/events?type=&rule_id=&date=
     *  result_failed   reports of the last 2 days rated Fail / Tolerable that this rule has not announced yet
     *  uncollected     shoes collected from the lines on `date` with no finished bonding report yet (not announced yet by this rule)
     *  shift_summary   the numbers of `date` (one event, key = date)
     *  erp_failed      ERP sends that failed in the last day
     */
    public function events()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $type = (string) $this->request->getGet('type');
        $rid  = (int) $this->request->getGet('rule_id');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('date')) ? (string) $this->request->getGet('date') : date('Y-m-d');
        $sent = $this->sentKeys($rid);
        $db   = $this->db();
        $ev   = [];
        $days = max(0, min(7, (int) $this->request->getGet('days')));   // how many days back a held-back alert may still be sent (0 = today's results only)
        $fire = mb_substr((string) $this->request->getGet('fire_key'), 0, 190);   // timed alerts: one combined message per (rule, day, time) - already sent = nothing to do
        if ($fire !== '' && isset($sent[$fire])) {
            return $this->json(['ok' => true, 'type' => $type, 'events' => [], 'fired' => true, 'server_time' => date('c')]);
        }

        if ($type === 'result_failed') {
            $rows = $db->query("
                SELECT u.id, u.lab_ref_no, u.form_slug, u.form_category, u.model_style, u.color, u.material_description, u.supplier, u.technician, u.overall_result, u.status, u.tested_date,
                       b.TestType AS test_type, b.ModeOfFailure AS mode, b.PossibleCause AS cause, b.AdhesiveSupplier AS cement, b.Side AS side
                FROM tbl_dtr_unified_labrefs u
                LEFT JOIN tbl_dtr_bonding_info b ON u.source_table = 'tbl_dtr_bonding_info' AND b.BondingTestID = u.source_id
                WHERE STR_TO_DATE(NULLIF(u.tested_date, ''), '%d %b %Y') >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                  AND u.overall_result IN ('Fail', 'FAIL', 'Failed', 'Tolerable', 'TOLERABLE')
                ORDER BY u.id DESC LIMIT 200")->getResultArray();
            // the day's numbers so far, shown in every alert (Bonding reports of `date`; trial records left out)
            $dt = ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tolerable' => 0];
            foreach ($db->query("SELECT overall_result r FROM tbl_dtr_unified_labrefs WHERE form_slug = 'bonding' AND tested_date = ? AND lab_ref_no NOT LIKE '%TRIAL%'", [date('d M Y', strtotime($date))])->getResultArray() as $z) {
                $q = self::result((string) $z['r']);
                $dt['tested']++;
                if ($q === 'PASS') {
                    $dt['pass']++;
                } elseif ($q === 'FAIL') {
                    $dt['fail']++;
                } elseif ($q === 'TOLERABLE') {
                    $dt['tolerable']++;
                }
            }
            $rated = $dt['pass'] + $dt['fail'] + $dt['tolerable'];
            $day   = ['day_tested' => $dt['tested'], 'day_pass' => $dt['pass'], 'day_tolerable' => $dt['tolerable'], 'day_fail' => $dt['fail'], 'day_rate' => $rated ? round($dt['fail'] * 100 / $rated, 1) : 0];
            foreach ($rows as $x) {
                $res = self::result((string) $x['overall_result']);
                $key = $x['lab_ref_no'] . '|' . $res;
                if ($res === '' || isset($sent[$key]) || stripos($x['lab_ref_no'], 'TRIAL') !== false) {
                    continue;
                }
                $ev[] = ['key' => $key, 'lab_ref' => $x['lab_ref_no'], 'result' => $res, 'domain' => $x['form_slug'] === 'bonding' ? 'bonding' : ($x['form_slug'] === 'wholeshoe' ? 'wholeshoe' : 'material'),
                    'category' => $x['form_category'], 'style' => (string) ($x['model_style'] ?: $x['material_description']), 'color' => $x['color'], 'supplier' => (string) ($x['cement'] ?: $x['supplier']),
                    'technician' => $x['technician'], 'test_type' => (string) $x['test_type'], 'mode' => (string) $x['mode'], 'cause' => (string) $x['cause'], 'tested_date' => $x['tested_date']]
                    + self::lineOf((string) $x['lab_ref_no']) + $day;
            }
        } elseif ($type === 'uncollected') {
            $rows = $db->query("
                SELECT t.LabRefNo, MAX(t.Building) b, MAX(t.`LineNo`) ln, MAX(t.Side) side, MAX(t.Style) style, MAX(t.Colorway) color, MAX(t.TestType) tt, MAX(t.Technician) tech,
                       MIN(t.CreatedAt) created
                FROM tbl_dtr_bondingtracker t
                WHERE t.CollectionDate = ?
                  AND NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info i WHERE i.LabRefNo COLLATE utf8mb4_general_ci = t.LabRefNo COLLATE utf8mb4_general_ci AND i.Result IS NOT NULL AND i.Result <> '')
                GROUP BY t.LabRefNo ORDER BY created", [$date])->getResultArray();
            foreach ($rows as $x) {
                $key = $x['LabRefNo'] . '|' . $date;
                if (($fire === '' && isset($sent[$key])) || stripos($x['LabRefNo'], 'TRIAL') !== false) {
                    continue;
                }
                $ev[] = ['key' => $key, 'lab_ref' => $x['LabRefNo'], 'building' => $x['b'], 'line' => preg_match('/^[A-Za-z]/', (string) $x['ln']) ? (string) $x['ln'] : trim($x['b'] . $x['ln']), 'side' => $x['side'], 'style' => $x['style'], 'color' => $x['color'],
                    'test_type' => $x['tt'], 'technician' => $x['tech'], 'collected_at' => $x['created'], 'hours_waiting' => $x['created'] ? round((time() - strtotime($x['created'])) / 3600, 1) : null];
            }
        } elseif ($type === 'shift_summary') {
            if (true) {
                $d    = date('d M Y', strtotime($date));
                $rows = $db->query("SELECT form_slug, overall_result, status, lab_ref_no FROM tbl_dtr_unified_labrefs WHERE tested_date = ? AND lab_ref_no NOT LIKE '%TRIAL%'", [$d])->getResultArray();
                $c    = ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tolerable' => 0, 'pending' => 0];
                $by   = [];
                foreach ($rows as $x) {
                    $res = self::result((string) $x['overall_result']);
                    $c['tested']++;
                    if ($res === 'PASS') {
                        $c['pass']++;
                    } elseif ($res === 'FAIL') {
                        $c['fail']++;
                    } elseif ($res === 'TOLERABLE') {
                        $c['tolerable']++;
                    } else {
                        $c['pending']++;
                    }
                    if ($res === 'FAIL' && $x['form_slug'] === 'bonding') {
                        $ln = self::lineOf((string) $x['lab_ref_no'])['line'];
                        $by[$ln ?: '?'] = ($by[$ln ?: '?'] ?? 0) + 1;
                    }
                }
                arsort($by);
                $rated = $c['pass'] + $c['fail'] + $c['tolerable'];
                $coll  = (int) $db->query("SELECT COUNT(DISTINCT t.LabRefNo) n FROM tbl_dtr_bondingtracker t WHERE t.CollectionDate = ? AND NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info i WHERE i.LabRefNo COLLATE utf8mb4_general_ci = t.LabRefNo COLLATE utf8mb4_general_ci AND i.Result IS NOT NULL AND i.Result <> '')", [$date])->getRow()->n;
                $ev[]  = ['key' => $fire !== '' ? $fire : $date, 'date' => $date, 'date_label' => date('D, M j', strtotime($date))] + $c + ['rated' => $rated, 'fail_rate' => $rated ? round($c['fail'] * 100 / $rated, 1) : 0,
                    'worst_lines' => implode(', ', array_map(static fn ($l, $n) => "$l ($n)", array_keys(array_slice($by, 0, 3, true)), array_slice($by, 0, 3, true))), 'uncollected' => $coll];
            }
        } elseif ($type === 'erp_failed') {
            if ($db->tableExists('tbl_erp_submissions')) {
                foreach ($db->query("SELECT id, lab_ref_no, message, updated_at FROM tbl_erp_submissions WHERE status = 'failed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY id DESC LIMIT 50")->getResultArray() as $x) {
                    $key = 'erp' . $x['id'];
                    if (! isset($sent[$key])) {
                        $ev[] = ['key' => $key, 'lab_ref' => $x['lab_ref_no'], 'message' => mb_substr((string) $x['message'], 0, 300), 'at' => $x['updated_at']];
                    }
                }
            }
        } else {
            return $this->json(['ok' => false, 'error' => 'Unknown event type.'], 422);
        }

        return $this->json(['ok' => true, 'type' => $type, 'events' => $ev, 'server_time' => date('c')]);
    }

    // ------------------------------------------------------------------------------------------------------------------ delivery
    /**
     * POST notifier/send {rule_id, rule_name, key, level, title, text, recipients[], channels{inbox,synology,push}, ack}
     * One message per recipient; a (rule, key, recipient, level) that was already sent is skipped (so nothing is sent twice).
     */
    public function send()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $p      = $this->request->getJSON(true) ?? [];
        $ruleId = (int) ($p['rule_id'] ?? 0);
        $key    = mb_substr((string) ($p['key'] ?? ''), 0, 190);
        $level  = (int) ($p['level'] ?? 0);
        $text   = trim((string) ($p['text'] ?? ''));
        $ids    = array_values(array_unique(array_map('strval', (array) ($p['recipients'] ?? []))));
        $ch     = (array) ($p['channels'] ?? ['inbox' => true, 'synology' => true, 'push' => true]);
        $isTest = ! empty($p['test']);
        if ($text === '' || ! $ids || ($key === '' && ! $isTest)) {
            return $this->json(['ok' => false, 'error' => 'Nothing to send (text, recipients and key are needed).'], 422);
        }
        if (! $isTest && ! \App\Libraries\PayloadRouting::allowed($this->request)) {
            $routing = \App\Libraries\PayloadRouting::info($this->request);

            return $this->json(['ok' => false, 'error' => $routing['note'], 'routing' => $routing]);
        }
        $db     = $this->db();
        $store  = new NexusStore();
        $syn    = new SynologyChat();
        $out    = [];
        $sentN  = 0;
        foreach ($ids as $uid) {
            $u = $db->query('SELECT UserID, FullName, SynologyID FROM tbl_users WHERE UserID = ? AND IsActive = 1', [(int) $uid])->getRowArray();
            if (! $u) {
                $out[] = ['id' => $uid, 'status' => 'unknown user'];
                continue;
            }
            $code = bin2hex(random_bytes(12));
            if (! $isTest) {
                // reserve the (rule, key, recipient, level) first: the unique key is what stops a double send
                try {
                    $db->table('tbl_notif_log')->insert(['rule_id' => $ruleId, 'rule_name' => mb_substr((string) ($p['rule_name'] ?? ''), 0, 120), 'k' => $key, 'recipient_id' => $uid,
                        'recipient_name' => (string) $u['FullName'], 'level' => $level, 'title' => mb_substr((string) ($p['title'] ?? ''), 0, 190), 'body' => $text,
                        'channels' => implode(',', array_keys(array_filter($ch))), 'ack_code' => ! empty($p['ack']) ? $code : '', 'sent_at' => date('Y-m-d H:i:s')]);
                } catch (\Throwable $e) {
                    $out[] = ['id' => $uid, 'status' => 'already sent'];
                    continue;
                }
                $logId = (int) $db->insertID();
            }
            $msg = $text . (! empty($p['ack']) && ! $isTest ? "\n\n✅ Seen it? Tap to acknowledge: " . site_url('notifier/ack/' . $code) : '');
            $res = [];
            if (! empty($ch['inbox'])) {
                try {
                    $store->send('lab_alerts', $uid, $msg);
                    $res[] = 'inbox';
                } catch (\Throwable $e) {
                    $res[] = 'inbox failed';
                    log_message('error', '[Notifier] inbox: ' . $e->getMessage());
                }
            }
            if (! empty($ch['synology']) && (int) $u['SynologyID'] > 0) {
                try {
                    $res[] = $syn->send((int) $u['SynologyID'], $msg) ? 'synology' : 'synology failed';
                } catch (\Throwable $e) {
                    $res[] = 'synology failed';
                }
            }
            if (! empty($ch['push'])) {
                try {
                    $fcm = new \App\Libraries\FcmService();
                    if ($fcm->isConfigured() && $db->tableExists('tbl_user_push_tokens')) {
                        $n = 0;
                        foreach ($db->query('SELECT token FROM tbl_user_push_tokens WHERE user_id = ?', [$uid])->getResultArray() as $t) {
                            $n += (int) $fcm->sendChat((string) $t['token'], 'Lab Alerts', mb_substr(preg_replace('/\s+/', ' ', $text), 0, 140), ['sender' => 'Lab Alerts', 'message' => mb_substr($text, 0, 140), 'conversation_id' => 'lab_alerts', 'sender_id' => 'lab_alerts', 'TARGET_PEER_ID' => 'lab_alerts']);
                        }
                        if ($n) {
                            $res[] = 'push';
                        }
                    }
                } catch (\Throwable $e) {
                    // push is optional
                }
            }
            if (! $isTest) {
                $db->table('tbl_notif_log')->where('id', $logId)->update(['result' => implode(', ', $res)]);
            }
            $sentN++;
            $out[] = ['id' => $uid, 'name' => $u['FullName'], 'status' => 'sent', 'via' => $res];
        }

        return $this->json(['ok' => true, 'sent' => $sentN, 'results' => $out]);
    }

    /** GET notifier/unacked?rule_id=&min_age=: alerts nobody acknowledged yet (with the level last sent), for the escalation */
    public function unacked()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $rid = (int) $this->request->getGet('rule_id');
        $rows = $this->db()->query("
            SELECT k, MAX(level) lvl, MIN(sent_at) first_at, MAX(sent_at) last_at, MAX(title) title, MAX(body) body,
                   SUM(ack_at IS NOT NULL) acked, SUM(ack_code <> '') needs_ack
            FROM tbl_notif_log WHERE rule_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
            GROUP BY k HAVING acked = 0 AND needs_ack > 0 ORDER BY last_at DESC LIMIT 100", [$rid])->getResultArray();

        return $this->json(['ok' => true, 'items' => $rows, 'server_time' => date('Y-m-d H:i:s')]);
    }

    /** GET notifier/log?limit= */
    public function log()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $n = max(1, min(500, (int) ($this->request->getGet('limit') ?: 100)));
        $rows = $this->db()->query('SELECT id, rule_id, rule_name, k, recipient_name, level, title, channels, result, sent_at, ack_at FROM tbl_notif_log ORDER BY id DESC LIMIT ' . $n)->getResultArray();

        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    /** GET notifier/ack/<code>: the link in the message; no sign-in. One person acknowledging closes the alert for everybody. */
    public function ack(string $code = '')
    {
        $code = preg_replace('/[^a-f0-9]/', '', $code);
        $this->ensureTables();
        $db  = $this->db();
        $row = $code === '' ? null : $db->table('tbl_notif_log')->where('ack_code', $code)->get()->getRowArray();
        if (! $row) {
            return $this->response->setStatusCode(404)->setBody($this->page('Link not found', 'This acknowledgement link is not valid any more.'));
        }
        $db->table('tbl_notif_log')->where('rule_id', $row['rule_id'])->where('k', $row['k'])->where('ack_at IS NULL', null, false)->update(['ack_at' => date('Y-m-d H:i:s')]);

        return $this->response->setBody($this->page('Thank you, ' . esc((string) $row['recipient_name']), 'The alert was acknowledged. Nobody will be reminded about it again.'));
    }

    private function page(string $title, string $msg): string
    {
        return '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc($title) . '</title>'
            . '<body style="font-family:Segoe UI,system-ui,sans-serif;background:#f1f5f9;display:grid;place-items:center;min-height:100vh;margin:0"><div style="background:#fff;border-radius:16px;padding:32px 28px;max-width:360px;text-align:center;box-shadow:0 8px 30px rgba(15,23,42,.1)">'
            . '<div style="font-size:42px">✅</div><h2 style="margin:8px 0">' . esc($title) . '</h2><p style="color:#475569">' . esc($msg) . '</p></div></body>';
    }
}
