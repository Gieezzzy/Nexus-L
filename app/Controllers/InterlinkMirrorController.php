<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;

/**
 * Local copy ("mirror") of InterLink's Inhouse & 3rd party search grid: every row, plus what is inside its E (eTRF) and U (Upload form)
 * pages. Filled by the separate NexusInterlinkMirror program; NEXUS-L only stores and searches it.
 *
 *  GET  interlink/service/mirror-state  (service key)  known rows {application_no: row_hash} + whether a refresh was asked for
 *  POST interlink/service/mirror        (service key)  a batch of rows {application_no, grid, hash, e, u}
 *  POST interlink/service/mirror-run    (service key)  run log line / finished marker
 *  GET  interlink/mirror/search?q=      (login)        search the stored rows
 *  GET  interlink/mirror/row/<app>      (login)        one row with its E and U data
 */
class InterlinkMirrorController extends BaseController
{
    private function db()
    {
        return \Config\Database::connect();
    }

    private function keyOk(): bool
    {
        $key = (string) SystemSettings::get('interlink.service.key', '');
        $got = (string) $this->request->getHeaderLine('X-Service-Key');

        return strlen($key) >= 16 && $got !== '' && hash_equals($key, $got);
    }

    private function json(array $d, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($d);
    }

    public static function tables(): void
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_interlink_rows (
            application_no VARCHAR(30) NOT NULL PRIMARY KEY,
            report_no VARCHAR(80) NOT NULL DEFAULT "",
            job_status VARCHAR(40) NOT NULL DEFAULT "",
            overall_result VARCHAR(40) NOT NULL DEFAULT "",
            submitted_on VARCHAR(20) NOT NULL DEFAULT "",
            vendor_name VARCHAR(160) NOT NULL DEFAULT "",
            factory_name VARCHAR(160) NOT NULL DEFAULT "",
            brand_name VARCHAR(80) NOT NULL DEFAULT "",
            style_no VARCHAR(60) NOT NULL DEFAULT "",
            test_packages VARCHAR(255) NOT NULL DEFAULT "",
            lab_location VARCHAR(80) NOT NULL DEFAULT "",
            row_hash CHAR(32) NOT NULL DEFAULT "",
            grid_json MEDIUMTEXT NULL,
            e_json MEDIUMTEXT NULL,
            u_json MEDIUMTEXT NULL,
            u_mode VARCHAR(8) NOT NULL DEFAULT "",
            detail_at DATETIME NULL,
            first_seen DATETIME NOT NULL,
            synced_at DATETIME NOT NULL,
            KEY k_report (report_no),
            KEY k_style (style_no),
            KEY k_status (job_status),
            KEY k_synced (synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_interlink_mirror_state (
            k VARCHAR(40) NOT NULL PRIMARY KEY,
            v TEXT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private static function setState(string $k, string $v): void
    {
        self::tables();
        $now = date('Y-m-d H:i:s');
        \Config\Database::connect()->query('INSERT INTO tbl_interlink_mirror_state (k, v, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v), updated_at=VALUES(updated_at)', [$k, $v, $now]);
    }

    /** Asks the Mirror program for a refresh at its next check (a new eTRF was created, or a report was submitted). */
    public static function trigger(): void
    {
        try {
            self::setState('trigger', date('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            // never break the caller
        }
    }

    /**
     * A new eTRF / report was just created in InterLink: put its row in the copy at once (Application No. and what is already known), so
     * the Report Submission list shows it without waiting for the Mirror. The empty hash makes the Mirror replace it with the real row.
     */
    public static function stub(string $app, string $reportNo = ''): void
    {
        $app = strtoupper(trim($app));
        if (! preg_match('/^A\d{6,}$/', $app)) {
            return;
        }
        try {
            self::tables();
            $db = \Config\Database::connect();
            if ($db->table('tbl_interlink_rows')->select('application_no')->where('application_no', $app)->get()->getRowArray()) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $g   = ['Application No.' => $app, 'Report No.' => $reportNo, 'Online TRF Submission Date' => date('Y-m-d')];
            $db->table('tbl_interlink_rows')->insert([
                'application_no' => $app, 'report_no' => mb_substr($reportNo, 0, 80), 'submitted_on' => date('Y-m-d'), 'row_hash' => '',
                'grid_json' => json_encode($g), 'first_seen' => $now, 'synced_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // never break the caller
        }
    }

    public function state()
    {
        if (! $this->keyOk()) {
            return $this->json(['ok' => false, 'error' => 'Wrong or missing service key.'], 401);
        }
        self::tables();
        $db     = $this->db();
        $hashes = [];
        foreach ($db->query('SELECT application_no, row_hash, (detail_at IS NOT NULL) AS has_detail FROM tbl_interlink_rows')->getResultArray() as $r) {
            $hashes[$r['application_no']] = $r['row_hash'] . ($r['has_detail'] ? '' : '!');
        }
        $trig = $db->query('SELECT v FROM tbl_interlink_mirror_state WHERE k="trigger"')->getRowArray();
        if ($this->request->getGet('ack')) {
            self::setState('trigger', '');
        }

        return $this->json(['ok' => true, 'rows' => $hashes, 'trigger' => ! empty($trig['v']) ? $trig['v'] : '', 'count' => count($hashes)]);
    }

    public function ingest()
    {
        if (! $this->keyOk()) {
            return $this->json(['ok' => false, 'error' => 'Wrong or missing service key.'], 401);
        }
        self::tables();
        $p    = $this->request->getJSON(true) ?? [];
        $now  = date('Y-m-d H:i:s');
        $n    = 0;
        $cut  = static fn ($v, int $l) => mb_substr(trim((string) $v), 0, $l);
        foreach ((array) ($p['rows'] ?? []) as $r) {
            $app = strtoupper(trim((string) ($r['application_no'] ?? '')));
            if (! preg_match('/^A\d{6,}$/', $app)) {
                continue;
            }
            $g    = (array) ($r['grid'] ?? []);
            $e    = $r['e'] ?? null;
            $u    = $r['u'] ?? null;
            $data = [
                'application_no' => $app,
                'report_no'      => $cut($g['Report No.'] ?? '', 80),
                'job_status'     => $cut($g['Job Status'] ?? '', 40),
                'overall_result' => $cut($g['Overall Result'] ?? '', 40),
                'submitted_on'   => $cut($g['Online TRF Submission Date'] ?? '', 20),
                'vendor_name'    => $cut($g['Vendor Name'] ?? '', 160),
                'factory_name'   => $cut($g['Factory Name'] ?? '', 160),
                'brand_name'     => $cut($g['Brand Name'] ?? '', 80),
                'style_no'       => $cut($g['Style No.'] ?? '', 60),
                'test_packages'  => $cut($g['Test Package(s)'] ?? '', 255),
                'lab_location'   => $cut($g['Lab Location'] ?? '', 80),
                'row_hash'       => $cut($r['hash'] ?? '', 32),
                'grid_json'      => json_encode($g, JSON_UNESCAPED_UNICODE),
                'u_mode'         => $cut($r['u_mode'] ?? '', 8),
                'synced_at'      => $now,
            ];
            if ($e !== null) {
                $data['e_json']    = json_encode($e, JSON_UNESCAPED_UNICODE);
                $data['detail_at'] = $now;
            }
            if ($u !== null) {
                $data['u_json'] = json_encode($u, JSON_UNESCAPED_UNICODE);
            }
            $exists = $this->db()->table('tbl_interlink_rows')->select('application_no')->where('application_no', $app)->get()->getRowArray();
            if ($exists) {
                $this->db()->table('tbl_interlink_rows')->where('application_no', $app)->update($data);
            } else {
                $data['first_seen'] = $now;
                $this->db()->table('tbl_interlink_rows')->insert($data);
            }
            $n++;
        }

        return $this->json(['ok' => true, 'saved' => $n]);
    }

    public function run()
    {
        if (! $this->keyOk()) {
            return $this->json(['ok' => false, 'error' => 'Wrong or missing service key.'], 401);
        }
        $p = $this->request->getJSON(true) ?? [];
        self::setState('last_run', json_encode(['at' => date('Y-m-d H:i:s'), 'state' => (string) ($p['state'] ?? ''), 'message' => mb_substr((string) ($p['message'] ?? ''), 0, 400), 'rows' => (int) ($p['rows'] ?? 0)]));

        return $this->json(['ok' => true]);
    }

    public function search()
    {
        self::tables();
        $q  = trim((string) $this->request->getGet('q'));
        $b  = $this->db()->table('tbl_interlink_rows')->select('application_no, report_no, job_status, overall_result, submitted_on, vendor_name, factory_name, brand_name, style_no, test_packages, lab_location, u_mode, synced_at');
        if ($q !== '') {
            $b->groupStart()->like('application_no', $q)->orLike('report_no', $q)->orLike('style_no', $q)->orLike('vendor_name', $q)->orLike('factory_name', $q)->orLike('test_packages', $q)->orLike('e_json', $q)->groupEnd();
        }
        $rows = $b->orderBy('application_no', 'DESC')->limit(100)->get()->getResultArray();

        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    public function row(string $app)
    {
        self::tables();
        $r = $this->db()->table('tbl_interlink_rows')->where('application_no', strtoupper($app))->get()->getRowArray();
        if (! $r) {
            return $this->json(['ok' => false, 'error' => 'Not in the mirror.'], 404);
        }
        foreach (['grid_json', 'e_json', 'u_json'] as $k) {
            $r[$k] = $r[$k] ? json_decode($r[$k], true) : null;
        }

        return $this->json(['ok' => true, 'row' => $r]);
    }
}
