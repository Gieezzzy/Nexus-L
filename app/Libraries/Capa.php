<?php

namespace App\Libraries;

use App\Libraries\Chat\NexusStore;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Failed Sample CAPA Closure Tracker.
 * - Tracks failed bonding Lab Refs with Excel-compatible fields (Line, Production Date, Prepared by, Problem Statement, Action Plan, Preventive Action, up to 3 Photos).
 * - Supports unique multi-card public links (e.g. 5 failed lines = 1 shareable link); submitting 1 card locks only that card as view-only while other draft cards remain editable.
 * - Tracks photo removal history and retains deleted photos for 72 hours for internal audit review.
 * - Sends real-time Inbox notifications to Admin, Manager, and Supervisor users on submission / photo uploads.
 */
class Capa
{
    public const ROLES = ['Admin', 'Supervisor', 'Manager', 'Bonding PIC'];
    public const MAX_PHOTOS = 3;
    public const DELETED_TTL_HOURS = 72;

    public static function canEdit(): bool
    {
        return (bool) array_intersect(self::ROLES, Roles::parse((string) (service('auth')->user()['role'] ?? '')));
    }

    public static function startDate(): string
    {
        $v = trim((string) SystemSettings::get('capa.start_date', '2026-11-01'));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '2026-11-01';
    }

    public static function uploadDir(int $capaId): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'capa' . DIRECTORY_SEPARATOR . $capaId;
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_capa (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lab_ref_no VARCHAR(191) NOT NULL,
            failed_report_id INT UNSIGNED NOT NULL DEFAULT 0,
            failed_date DATE NULL,
            production_date DATE NULL,
            line VARCHAR(10) NOT NULL DEFAULT "",
            style VARCHAR(191) NOT NULL DEFAULT "",
            cement VARCHAR(191) NOT NULL DEFAULT "",
            mode_of_failure VARCHAR(255) NOT NULL DEFAULT "",
            prepared_by VARCHAR(191) NOT NULL DEFAULT "",
            root_cause TEXT NULL,
            corrective_action TEXT NULL,
            preventive_action TEXT NULL,
            retest_lab_ref VARCHAR(191) NOT NULL DEFAULT "",
            status VARCHAR(10) NOT NULL DEFAULT "Open",
            submitted_by VARCHAR(191) NOT NULL DEFAULT "",
            submitted_at DATETIME NULL,
            closed_by VARCHAR(120) NOT NULL DEFAULT "",
            closed_at DATETIME NULL,
            updated_by VARCHAR(120) NOT NULL DEFAULT "",
            updated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_capa_ref (lab_ref_no),
            KEY k_capa_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Safely add new columns to existing tbl_dtr_capa if missing
        $cols = array_column($db->query('SHOW COLUMNS FROM tbl_dtr_capa')->getResultArray(), 'Field');
        $add  = [
            'production_date'   => 'ALTER TABLE tbl_dtr_capa ADD COLUMN production_date DATE NULL AFTER failed_date',
            'prepared_by'       => 'ALTER TABLE tbl_dtr_capa ADD COLUMN prepared_by VARCHAR(191) NOT NULL DEFAULT "" AFTER mode_of_failure',
            'preventive_action' => 'ALTER TABLE tbl_dtr_capa ADD COLUMN preventive_action TEXT NULL AFTER corrective_action',
            'submitted_by'      => 'ALTER TABLE tbl_dtr_capa ADD COLUMN submitted_by VARCHAR(191) NOT NULL DEFAULT "" AFTER status',
            'submitted_at'      => 'ALTER TABLE tbl_dtr_capa ADD COLUMN submitted_at DATETIME NULL AFTER submitted_by',
        ];
        foreach ($add as $col => $sql) {
            if (! in_array($col, $cols, true)) {
                try {
                    $db->query($sql);
                } catch (\Throwable $e) {
                }
            }
        }

        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_capa_photos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            capa_id INT UNSIGNED NOT NULL,
            filename VARCHAR(255) NOT NULL,
            orig_name VARCHAR(255) NOT NULL DEFAULT "",
            mime VARCHAR(64) NOT NULL DEFAULT "image/jpeg",
            uploaded_by VARCHAR(120) NOT NULL DEFAULT "",
            uploaded_via VARCHAR(20) NOT NULL DEFAULT "web",
            created_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            deleted_by VARCHAR(120) NOT NULL DEFAULT "",
            expires_at DATETIME NULL,
            KEY k_capa_photos (capa_id, deleted_at),
            KEY k_capa_exp (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_capa_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL,
            title VARCHAR(191) NOT NULL DEFAULT "",
            capa_ids TEXT NOT NULL,
            created_by VARCHAR(120) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            UNIQUE KEY uq_capa_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_capa_history (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            capa_id INT UNSIGNED NOT NULL,
            photo_id INT UNSIGNED NULL,
            event_type VARCHAR(32) NOT NULL,
            actor VARCHAR(120) NOT NULL DEFAULT "",
            detail VARCHAR(255) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            KEY k_capa_hist (capa_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $ready = true;
    }

    /** Permanently remove deleted photo files whose 72-hour retention window has expired (throttled to every 5 min) */
    public static function purgeExpiredDeletedPhotos(bool $force = false): void
    {
        if (! $force) {
            try {
                if (cache('capa_purge_ok')) {
                    return;
                }
                cache()->save('capa_purge_ok', 1, 300);
            } catch (\Throwable $e) {
            }
        }
        self::ensureTable();
        $db   = \Config\Database::connect();
        $rows = $db->query('SELECT id, capa_id, filename FROM tbl_dtr_capa_photos WHERE deleted_at IS NOT NULL AND expires_at IS NOT NULL AND expires_at <= NOW() LIMIT 100')->getResultArray();
        foreach ($rows as $r) {
            $path = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'capa' . DIRECTORY_SEPARATOR . (int) $r['capa_id'] . DIRECTORY_SEPARATOR . basename((string) $r['filename']);
            if (is_file($path)) {
                @unlink($path);
            }
            $db->table('tbl_dtr_capa_photos')->where('id', (int) $r['id'])->delete();
        }
    }

    public static function logHistory(int $capaId, string $eventType, string $actor, string $detail = '', ?int $photoId = null): void
    {
        try {
            \Config\Database::connect()->table('tbl_dtr_capa_history')->insert([
                'capa_id'    => $capaId,
                'photo_id'   => $photoId,
                'event_type' => mb_substr($eventType, 0, 32),
                'actor'      => mb_substr(trim($actor) ?: 'External user', 0, 120),
                'detail'     => mb_substr($detail, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
        }
    }

    /** one case per failed Lab Ref since the start date (throttled to every 60s unless forced) */
    public static function sync(bool $force = false): int
    {
        self::ensureTable();
        if (! $force) {
            try {
                if (cache('capa_sync_ok')) {
                    return 0;
                }
                cache()->save('capa_sync_ok', 1, 60);
            } catch (\Throwable $e) {
            }
        }
        $db   = \Config\Database::connect();
        $rows = $db->query("SELECT LabRefNo ref, MIN(BondingTestID) first_id, MIN(LEFT(TestedDate, 10)) fd, MAX(LEFT(ProductionDate, 10)) pd, MAX(ModelStyle) style, MAX(AdhesiveSupplier) cement,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(ModeOfFailure), '') SEPARATOR '; ') modes, GROUP_CONCAT(DISTINCT NULLIF(TRIM(PossibleCause), '') SEPARATOR '; ') cause
            FROM tbl_dtr_bonding_info
            WHERE Result LIKE 'F%' AND TestedDate >= ? AND LabRefNo <> '' AND LabRefNo NOT LIKE '%TRIAL%' AND LabRefNo NOT LIKE '%SHUTDOWN%' AND COALESCE(TestType, '') NOT LIKE '%LACKING%'
            GROUP BY LabRefNo", [self::startDate()])->getResultArray();
        $have = array_flip(array_column($db->query('SELECT lab_ref_no FROM tbl_dtr_capa')->getResultArray(), 'lab_ref_no'));
        $n    = 0;
        foreach ($rows as $r) {
            if (isset($have[$r['ref']])) {
                continue;
            }
            $pd = trim((string) ($r['pd'] ?? ''));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd)) {
                $pd = null;
            }
            $db->table('tbl_dtr_capa')->insert([
                'lab_ref_no'       => $r['ref'],
                'failed_report_id' => (int) $r['first_id'],
                'failed_date'      => $r['fd'] ?: null,
                'production_date'  => $pd,
                'line'             => \App\Controllers\QualityInsightsController::lineOf((string) $r['ref']),
                'style'            => mb_substr((string) $r['style'], 0, 191),
                'cement'           => mb_substr((string) $r['cement'], 0, 191),
                'mode_of_failure'  => mb_substr((string) $r['modes'], 0, 255),
                'root_cause'       => (string) ($r['cause'] ?? ''),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
            $n++;
        }

        return $n;
    }

    /** @return array{active:array<int,list<array<string,mixed>>>,deleted:array<int,list<array<string,mixed>>>,history:array<int,list<array<string,mixed>>>} */
    private static function mediaForCases(array $ids, ?string $publicToken = null): array
    {
        $active  = [];
        $deleted = [];
        $history = [];
        if (! $ids) {
            return ['active' => $active, 'deleted' => $deleted, 'history' => $history];
        }
        $db  = \Config\Database::connect();
        $in  = implode(',', array_map('intval', $ids));
        $now = time();
        foreach ($db->query("SELECT * FROM tbl_dtr_capa_photos WHERE capa_id IN ({$in}) ORDER BY id ASC")->getResultArray() as $p) {
            $cid = (int) $p['capa_id'];
            $url = $publicToken !== null
                ? site_url('capa-form/' . rawurlencode($publicToken) . '/photo/' . (int) $p['id'])
                : site_url('quality-insights/capa/photo/' . (int) $p['id']);
            $item = [
                'id'          => (int) $p['id'],
                'orig_name'   => (string) $p['orig_name'],
                'uploaded_by' => (string) $p['uploaded_by'],
                'uploaded_via'=> (string) $p['uploaded_via'],
                'created_at'  => (string) $p['created_at'],
                'url'         => $url,
            ];
            if (empty($p['deleted_at'])) {
                $active[$cid][] = $item;
            } elseif ($publicToken === null) {
                $expTs = $p['expires_at'] ? strtotime((string) $p['expires_at']) : 0;
                if ($expTs > $now) {
                    $item['deleted_at'] = (string) $p['deleted_at'];
                    $item['deleted_by'] = (string) $p['deleted_by'];
                    $item['expires_at'] = (string) $p['expires_at'];
                    $item['hours_left'] = max(1, (int) ceil(($expTs - $now) / 3600));
                    $deleted[$cid][]    = $item;
                }
            }
        }
        if ($publicToken === null) {
            foreach ($db->query("SELECT * FROM tbl_dtr_capa_history WHERE capa_id IN ({$in}) ORDER BY id DESC LIMIT 400")->getResultArray() as $h) {
                $cid = (int) $h['capa_id'];
                if (count($history[$cid] ?? []) < 12) {
                    $history[$cid][] = [
                        'event'      => (string) $h['event_type'],
                        'actor'      => (string) $h['actor'],
                        'detail'     => (string) $h['detail'],
                        'created_at' => (string) $h['created_at'],
                    ];
                }
            }
        }

        return ['active' => $active, 'deleted' => $deleted, 'history' => $history];
    }

    /** Lightweight count query for the Quality Insights tab badge (avoids loading all cases) */
    public static function badgeCounts(): array
    {
        self::ensureTable();
        $c = \Config\Database::connect()->query("SELECT SUM(status = 'Open') o, SUM(status = 'Closed') c, SUM(status = 'Open' AND failed_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)) late FROM tbl_dtr_capa")->getRowArray();

        return ['open' => (int) ($c['o'] ?? 0), 'closed' => (int) ($c['c'] ?? 0), 'overdue' => (int) ($c['late'] ?? 0)];
    }

    /** @return array{cases:list<array<string,mixed>>,open:int,closed:int,overdue:int,since:string} */
    public static function listing(string $status = 'Open', bool $forceSync = false): array
    {
        self::sync($forceSync);
        self::purgeExpiredDeletedPhotos();
        $db = \Config\Database::connect();
        $c  = $db->query("SELECT SUM(status = 'Open') o, SUM(status = 'Closed') c, SUM(status = 'Open' AND failed_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)) late FROM tbl_dtr_capa")->getRowArray();
        $q  = $db->table('tbl_dtr_capa')->orderBy('status', 'ASC')->orderBy('failed_date', 'DESC')->orderBy('id', 'DESC')->limit(200);
        if (in_array($status, ['Open', 'Closed'], true)) {
            $q->where('status', $status);
        }
        $raw   = $q->get()->getResultArray();
        $ids   = array_map(static fn ($r) => (int) $r['id'], $raw);
        $media = self::mediaForCases($ids, null);
        $openRows = array_values(array_filter($raw, static fn ($r) => ($r['status'] ?? '') === 'Open'));
        $candsMap = self::candidatesBatch($openRows);
        $cases = [];
        foreach ($raw as $r) {
            $id = (int) $r['id'];
            $cases[] = [
                'id'                => $id,
                'ref'               => $r['lab_ref_no'],
                'failed_date'       => (string) ($r['failed_date'] ?? ''),
                'production_date'   => (string) ($r['production_date'] ?? ''),
                'age'               => ! empty($r['failed_date']) ? (int) floor((time() - strtotime((string) $r['failed_date'])) / 86400) : 0,
                'line'              => (string) $r['line'],
                'style'             => (string) $r['style'],
                'cement'            => (string) $r['cement'],
                'mode'              => (string) $r['mode_of_failure'],
                'prepared_by'       => (string) ($r['prepared_by'] ?? ''),
                'root_cause'        => (string) ($r['root_cause'] ?? ''),
                'action'            => (string) ($r['corrective_action'] ?? ''),
                'preventive_action' => (string) ($r['preventive_action'] ?? ''),
                'retest'            => (string) ($r['retest_lab_ref'] ?? ''),
                'status'            => (string) $r['status'],
                'submitted_by'      => (string) ($r['submitted_by'] ?? ''),
                'submitted_at'      => (string) ($r['submitted_at'] ?? ''),
                'closed_by'         => (string) ($r['closed_by'] ?? ''),
                'closed_at'         => (string) ($r['closed_at'] ?? ''),
                'photos'            => $media['active'][$id] ?? [],
                'deleted_photos'    => $media['deleted'][$id] ?? [],
                'history'           => $media['history'][$id] ?? [],
                'candidates'        => $r['status'] === 'Open' ? ($candsMap[$id] ?? []) : [],
            ];
        }

        return ['cases' => $cases, 'open' => (int) ($c['o'] ?? 0), 'closed' => (int) ($c['c'] ?? 0), 'overdue' => (int) ($c['late'] ?? 0), 'since' => self::startDate()];
    }

    /** Batched retest lookup: 2 queries total for all open cases instead of 2 queries per case (eliminates N+1 lag) */
    private static function candidatesBatch(array $openCases): array
    {
        if (! $openCases) {
            return [];
        }
        $db   = \Config\Database::connect();
        $refs = array_values(array_unique(array_filter(array_column($openCases, 'lab_ref_no'))));
        if (! $refs) {
            return [];
        }
        $lastFailByRef = [];
        $placeholders  = implode(',', array_fill(0, count($refs), '?'));
        foreach ($db->query("SELECT LabRefNo ref, MAX(BondingTestID) m FROM tbl_dtr_bonding_info WHERE Result LIKE 'F%' AND LabRefNo IN ({$placeholders}) GROUP BY LabRefNo", $refs)->getResultArray() as $r) {
            $lastFailByRef[(string) $r['ref']] = (int) $r['m'];
        }
        $passRows = $db->query("SELECT LabRefNo ref, MAX(BondingTestID) id, MAX(LEFT(TestedDate, 10)) td FROM tbl_dtr_bonding_info
            WHERE Result LIKE 'P%' AND TestedDate >= ? AND LabRefNo <> '' AND LabRefNo NOT LIKE '%TRIAL%'
            GROUP BY LabRefNo ORDER BY id DESC LIMIT 1500", [self::startDate()])->getResultArray();

        $out = [];
        foreach ($openCases as $case) {
            $cid      = (int) $case['id'];
            $ref      = (string) $case['lab_ref_no'];
            $base     = (string) preg_replace('/-(?:R\d+|\d+)$/i', '', $ref);
            $prefix   = $base . '-';
            $lastFail = $lastFailByRef[$ref] ?? 0;
            $matches  = [];
            foreach ($passRows as $pr) {
                $prRef = (string) $pr['ref'];
                $prId  = (int) $pr['id'];
                if (($prRef === $ref && $prId > $lastFail) || ($prRef !== $ref && stripos($prRef, $prefix) === 0)) {
                    $matches[] = ['ref' => $prRef, 'tested' => (string) $pr['td']];
                    if (count($matches) >= 6) {
                        break;
                    }
                }
            }
            $out[$cid] = $matches;
        }

        return $out;
    }

    /** Internal save / close */
    public static function save(int $id, array $in, bool $close, string $by): array
    {
        self::ensureTable();
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_dtr_capa')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return ['ok' => false, 'message' => 'That case no longer exists.'];
        }
        if ($row['status'] === 'Closed') {
            return ['ok' => false, 'message' => 'A closed case cannot be changed.'];
        }
        $prep   = trim((string) ($in['prepared_by'] ?? $row['prepared_by'] ?? ''));
        $pd     = trim((string) ($in['production_date'] ?? $row['production_date'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd)) {
            $pd = $row['production_date'] ?: null;
        }
        $cause  = trim((string) ($in['root_cause'] ?? ''));
        $action = trim((string) ($in['corrective_action'] ?? ''));
        $prev   = trim((string) ($in['preventive_action'] ?? $row['preventive_action'] ?? ''));
        $retest = trim((string) ($in['retest_lab_ref'] ?? ''));
        $upd    = [
            'prepared_by'       => mb_substr($prep, 0, 191),
            'production_date'   => $pd,
            'root_cause'        => mb_substr($cause, 0, 4000),
            'corrective_action' => mb_substr($action, 0, 4000),
            'preventive_action' => mb_substr($prev, 0, 4000),
            'retest_lab_ref'    => mb_substr($retest, 0, 191),
            'updated_by'        => mb_substr($by, 0, 120),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
        if ($close) {
            $miss = [];
            if ($cause === '') {
                $miss[] = 'the Problem Statement / Root Cause';
            }
            if ($action === '') {
                $miss[] = 'the Action Plan / Corrective Action';
            }
            if ($retest === '') {
                $miss[] = 'the Retest Lab Ref';
            }
            if ($miss) {
                return ['ok' => false, 'message' => 'To close this case write ' . implode(', ', $miss) . '.'];
            }
            $problem = self::retestProblem($row, $retest);
            if ($problem !== '') {
                return ['ok' => false, 'message' => $problem];
            }
            $upd += ['status' => 'Closed', 'closed_by' => mb_substr($by, 0, 120), 'closed_at' => date('Y-m-d H:i:s')];
        }
        $db->table('tbl_dtr_capa')->where('id', $id)->update($upd);
        if ($close) {
            self::logHistory($id, 'closed', $by, 'Closed with passing retest ' . $retest);
        }

        return ['ok' => true, 'message' => $close ? 'Case closed: ' . $row['lab_ref_no'] . ' (retest ' . $retest . ' passed).' : 'Saved.', 'status' => $close ? 'Closed' : 'Open'];
    }

    /** Upload a photo (max 3 active per CAPA case) */
    public static function uploadPhoto(int $capaId, ?UploadedFile $file, string $actor, string $via = 'web', ?string $publicToken = null): array
    {
        self::ensureTable();
        self::purgeExpiredDeletedPhotos();
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_dtr_capa')->where('id', $capaId)->get()->getRowArray();
        if (! $row) {
            return ['ok' => false, 'message' => 'CAPA case not found.'];
        }
        if ($row['status'] === 'Closed') {
            return ['ok' => false, 'message' => 'Closed cases cannot be modified.'];
        }
        if ($via === 'link' && ! empty($row['submitted_at'])) {
            return ['ok' => false, 'message' => 'This CAPA card has already been submitted and is now view-only.'];
        }
        $activeCount = $db->table('tbl_dtr_capa_photos')->where(['capa_id' => $capaId, 'deleted_at' => null])->countAllResults();
        if ($activeCount >= self::MAX_PHOTOS) {
            return ['ok' => false, 'message' => 'Maximum of ' . self::MAX_PHOTOS . ' photos allowed per CAPA case.'];
        }
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return ['ok' => false, 'message' => 'Please select a valid image file.'];
        }
        if ($file->getSize() > 12 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Image is too large (max 12 MB).'];
        }
        $mime = (string) $file->getMimeType();
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (! isset($extMap[$mime])) {
            return ['ok' => false, 'message' => 'Only JPG, PNG, WebP, or GIF images are allowed.'];
        }
        $ext  = $extMap[$mime];
        $name = 'capa_' . $capaId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dir  = self::uploadDir($capaId);
        $file->move($dir, $name);

        $orig = mb_substr((string) $file->getClientName(), 0, 200);
        $now  = date('Y-m-d H:i:s');
        $db->table('tbl_dtr_capa_photos')->insert([
            'capa_id'      => $capaId,
            'filename'     => $name,
            'orig_name'    => $orig ?: $name,
            'mime'         => $mime,
            'uploaded_by'  => mb_substr(trim($actor) ?: 'Line Staff', 0, 120),
            'uploaded_via' => $via === 'link' ? 'link' : 'web',
            'created_at'   => $now,
        ]);
        $pid = (int) $db->insertID();
        self::logHistory($capaId, 'photo_added', $actor, 'Uploaded photo ' . ($orig ?: $name) . ' (' . ($activeCount + 1) . '/' . self::MAX_PHOTOS . ')', $pid);
        $db->table('tbl_dtr_capa')->where('id', $capaId)->update(['updated_at' => $now]);

        if ($via === 'link') {
            self::notifyManagers($row, "Photo uploaded via CAPA link for {$row['line']} ({$row['lab_ref_no']}) by " . (trim($actor) ?: 'Line Staff') . '.');
        }

        $url = $publicToken !== null
            ? site_url('capa-form/' . rawurlencode($publicToken) . '/photo/' . $pid)
            : site_url('quality-insights/capa/photo/' . $pid);

        return [
            'ok'    => true,
            'photo' => ['id' => $pid, 'orig_name' => $orig ?: $name, 'uploaded_by' => trim($actor) ?: 'Line Staff', 'created_at' => $now, 'url' => $url],
        ];
    }

    /** Soft-delete a photo (retained for 72 hours for audit review) */
    public static function removePhoto(int $capaId, int $photoId, string $actor, bool $fromLink = false): array
    {
        self::ensureTable();
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_dtr_capa')->where('id', $capaId)->get()->getRowArray();
        if (! $row) {
            return ['ok' => false, 'message' => 'CAPA case not found.'];
        }
        if ($row['status'] === 'Closed') {
            return ['ok' => false, 'message' => 'Closed cases cannot be modified.'];
        }
        if ($fromLink && ! empty($row['submitted_at'])) {
            return ['ok' => false, 'message' => 'Submitted CAPA cards are locked and photos cannot be removed.'];
        }
        $p = $db->table('tbl_dtr_capa_photos')->where(['id' => $photoId, 'capa_id' => $capaId, 'deleted_at' => null])->get()->getRowArray();
        if (! $p) {
            return ['ok' => false, 'message' => 'Photo not found or already removed.'];
        }
        $now = date('Y-m-d H:i:s');
        $exp = date('Y-m-d H:i:s', time() + self::DELETED_TTL_HOURS * 3600);
        $who = mb_substr(trim($actor) ?: ($fromLink ? 'Line Staff (Link)' : 'Staff'), 0, 120);
        $db->table('tbl_dtr_capa_photos')->where('id', $photoId)->update([
            'deleted_at' => $now,
            'deleted_by' => $who,
            'expires_at' => $exp,
        ]);
        self::logHistory($capaId, 'photo_removed', $who, 'Removed photo "' . ($p['orig_name'] ?: $p['filename']) . '" (retained for 72h until ' . $exp . ')', $photoId);
        $db->table('tbl_dtr_capa')->where('id', $capaId)->update(['updated_at' => $now]);

        return ['ok' => true, 'message' => 'Photo removed (kept in 72-hour audit history).'];
    }

    /** Create 1 unique shareable token link covering N CAPA cases (e.g. 5 failed lines -> 1 link) */
    public static function createLink(array $capaIds, string $title, string $by): array
    {
        self::ensureTable();
        $ids = array_values(array_unique(array_filter(array_map('intval', $capaIds), static fn ($v) => $v > 0)));
        if (! $ids) {
            return ['ok' => false, 'message' => 'Select at least one CAPA case to include in the link.'];
        }
        $db    = \Config\Database::connect();
        $valid = array_map(static fn ($r) => (int) $r['id'], $db->table('tbl_dtr_capa')->select('id')->whereIn('id', $ids)->get()->getResultArray());
        if (! $valid) {
            return ['ok' => false, 'message' => 'No matching CAPA cases found.'];
        }
        $token = bin2hex(random_bytes(16));
        $t     = trim($title) !== '' ? mb_substr(trim($title), 0, 191) : ('Daily Bonding Failure CAPA (' . count($valid) . ' cases · ' . date('Y-m-d') . ')');
        $db->table('tbl_dtr_capa_links')->insert([
            'token'      => $token,
            'title'      => $t,
            'capa_ids'   => json_encode($valid),
            'created_by' => mb_substr($by, 0, 120),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok'    => true,
            'token' => $token,
            'url'   => site_url('capa-form/' . $token),
            'count' => count($valid),
            'title' => $t,
        ];
    }

    /** Load a public CAPA batch link and its cards */
    public static function linkData(string $token): ?array
    {
        self::ensureTable();
        self::purgeExpiredDeletedPhotos();
        if (! preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
            return null;
        }
        $db   = \Config\Database::connect();
        $link = $db->table('tbl_dtr_capa_links')->where('token', $token)->get()->getRowArray();
        if (! $link) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', json_decode((string) $link['capa_ids'], true) ?: [])));
        if (! $ids) {
            return ['link' => $link, 'cases' => []];
        }
        $rows  = $db->table('tbl_dtr_capa')->whereIn('id', $ids)->orderBy('line', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $media = self::mediaForCases($ids, $token);
        $cases = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $locked = ! empty($r['submitted_at']) || $r['status'] === 'Closed';
            $cases[] = [
                'id'                => $id,
                'ref'               => (string) $r['lab_ref_no'],
                'line'              => (string) $r['line'],
                'style'             => (string) $r['style'],
                'cement'            => (string) $r['cement'],
                'mode'              => (string) $r['mode_of_failure'],
                'failed_date'       => (string) ($r['failed_date'] ?? ''),
                'production_date'   => (string) ($r['production_date'] ?? ''),
                'prepared_by'       => (string) ($r['prepared_by'] ?? ''),
                'root_cause'        => (string) ($r['root_cause'] ?? ''),
                'action'            => (string) ($r['corrective_action'] ?? ''),
                'preventive_action' => (string) ($r['preventive_action'] ?? ''),
                'status'            => (string) $r['status'],
                'submitted_by'      => (string) ($r['submitted_by'] ?? ''),
                'submitted_at'      => (string) ($r['submitted_at'] ?? ''),
                'locked'            => $locked,
                'photos'            => $media['active'][$id] ?? [],
            ];
        }

        return ['link' => $link, 'cases' => $cases];
    }

    /** Save draft or submit 1 card on a public token link; submitting locks ONLY that card */
    public static function saveFromLink(string $token, int $capaId, array $in, bool $submit): array
    {
        $data = self::linkData($token);
        if (! $data) {
            return ['ok' => false, 'message' => 'This CAPA link is invalid.'];
        }
        $allowedIds = array_column($data['cases'], 'id');
        if (! in_array($capaId, $allowedIds, true)) {
            return ['ok' => false, 'message' => 'That CAPA card does not belong to this link.'];
        }
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_dtr_capa')->where('id', $capaId)->get()->getRowArray();
        if (! $row) {
            return ['ok' => false, 'message' => 'CAPA case not found.'];
        }
        if (! empty($row['submitted_at']) || $row['status'] === 'Closed') {
            return ['ok' => false, 'message' => 'This CAPA card was already submitted and is now view-only.'];
        }

        $prep   = trim((string) ($in['prepared_by'] ?? ''));
        $pd     = trim((string) ($in['production_date'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd)) {
            $pd = $row['production_date'] ?: null;
        }
        $cause  = trim((string) ($in['root_cause'] ?? ''));
        $action = trim((string) ($in['corrective_action'] ?? ''));
        $prev   = trim((string) ($in['preventive_action'] ?? ''));

        if ($submit) {
            $miss = [];
            if ($prep === '') {
                $miss[] = 'Prepared by';
            }
            if ($cause === '') {
                $miss[] = 'Problem Statement';
            }
            if ($action === '') {
                $miss[] = 'Action Plan';
            }
            if ($miss) {
                return ['ok' => false, 'message' => 'Please fill in: ' . implode(', ', $miss) . '.'];
            }
        }

        $now   = date('Y-m-d H:i:s');
        $actor = $prep !== '' ? $prep : 'Line Staff (Link)';
        $upd   = [
            'prepared_by'       => mb_substr($prep, 0, 191),
            'production_date'   => $pd,
            'root_cause'        => mb_substr($cause, 0, 4000),
            'corrective_action' => mb_substr($action, 0, 4000),
            'preventive_action' => mb_substr($prev, 0, 4000),
            'updated_by'        => mb_substr($actor, 0, 120),
            'updated_at'        => $now,
        ];
        if ($submit) {
            $upd['submitted_by'] = mb_substr($actor, 0, 191);
            $upd['submitted_at'] = $now;
        }
        $db->table('tbl_dtr_capa')->where('id', $capaId)->update($upd);

        if ($submit) {
            $pCount = $db->table('tbl_dtr_capa_photos')->where(['capa_id' => $capaId, 'deleted_at' => null])->countAllResults();
            self::logHistory($capaId, 'submitted', $actor, 'Submitted CAPA via link (' . $pCount . ' photo(s))');
            $summary = "📋 CAPA Submitted — Line {$row['line']} ({$row['lab_ref_no']})\n"
                . "Prepared by: {$actor}\n"
                . "Problem: " . mb_substr($cause, 0, 140) . "\n"
                . "Action Plan: " . mb_substr($action, 0, 180) . "\n"
                . "Photos attached: {$pCount} · Open Quality Insights → Failed Sample CAPA to review.";
            self::notifyManagers($row, $summary);

            return ['ok' => true, 'locked' => true, 'submitted_at' => $now, 'submitted_by' => $actor, 'message' => 'Submitted! This card is now locked (view-only).'];
        }

        self::logHistory($capaId, 'draft_saved', $actor, 'Saved draft via CAPA link');

        return ['ok' => true, 'locked' => false, 'message' => 'Draft saved. You can still edit or update photos before submitting.'];
    }

    /** Send Inbox notification to all active Admin, Manager, and Supervisor accounts */
    public static function notifyManagers(array $case, string $message): void
    {
        try {
            $db       = \Config\Database::connect();
            $users    = $db->query('SELECT UserID, Role, SynologyID FROM tbl_users WHERE IsActive = 1')->getResultArray();
            $store    = new NexusStore();
            $notifier = new InboxNotifier();
            foreach ($users as $u) {
                $roles = Roles::parse((string) ($u['Role'] ?? ''));
                if (! array_intersect(['Admin', 'Manager', 'Supervisor'], $roles)) {
                    continue;
                }
                $uid = (string) $u['UserID'];
                try {
                    $store->send('lab_alerts', $uid, $message);
                    $notifier->newMessage($store, 'lab_alerts', 'Lab Alerts — NEXUS-L', $uid, $message, $u['SynologyID'] ?? null);
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[CAPA notifyManagers] ' . $e->getMessage());
        }
    }

    private static function retestProblem(array $case, string $retest): string
    {
        $db  = \Config\Database::connect();
        $ref = (string) $case['lab_ref_no'];
        if (stripos($retest, 'TRIAL') !== false) {
            return 'A trial Lab Ref cannot be the retest.';
        }
        $rows = $db->query("SELECT BondingTestID id, Result, LEFT(TestedDate, 10) td FROM tbl_dtr_bonding_info WHERE LabRefNo = ? AND Result IS NOT NULL AND Result <> '' ORDER BY BondingTestID", [$retest])->getResultArray();
        if (! $rows) {
            return $retest . ' has no saved result yet: a failed sample is closed only after a passing retest.';
        }
        $lastFail = (int) ($db->query("SELECT MAX(BondingTestID) m FROM tbl_dtr_bonding_info WHERE LabRefNo = ? AND Result LIKE 'F%'", [$ref])->getRow('m') ?? 0);
        foreach (array_reverse($rows) as $r) {
            if (strtoupper($retest) === strtoupper($ref) && (int) $r['id'] <= $lastFail) {
                return 'The retest of the same Lab Ref must be saved after its last failed report.';
            }
            if (stripos((string) $r['Result'], 'P') === 0) {
                return '';
            }

            return $retest . ' did not pass (latest result: ' . strtoupper((string) $r['Result']) . '): a failed sample is closed only after a passing retest.';
        }

        return 'No passing retest found.';
    }
}
