<?php

namespace App\Libraries;

/**
 * ERP Finish Monitoring: the bonding lab refs of a production day, with their InterLink Application Nos. (bonding and pull test),
 * the result and the ERP status. Extra columns on tbl_dtr_unified_labrefs:
 *   erp_status       OK | Submitted | Ongoing | Pending | '' (not submitted)
 *   interlink_flag   OK when the daily tracker workbook marked it "INTERLINK OK / SARGE OK"
 */
class ErpMonitor
{
    private static function db()
    {
        return \Config\Database::connect();
    }

    public static function ensureColumns(): void
    {
        EtrfAppNos::ensureColumns();
        $db   = self::db();
        $have = array_column($db->query('SHOW COLUMNS FROM tbl_dtr_unified_labrefs')->getResultArray(), 'Field');
        $add  = [];
        if (! in_array('erp_status', $have, true)) {
            $add[] = "ADD COLUMN `erp_status` VARCHAR(20) NOT NULL DEFAULT ''";
        }
        if (! in_array('interlink_flag', $have, true)) {
            $add[] = "ADD COLUMN `interlink_flag` VARCHAR(20) NOT NULL DEFAULT ''";
        }
        if ($add) {
            $db->query('ALTER TABLE tbl_dtr_unified_labrefs ' . implode(', ', $add));
        }
    }

    /**
     * The log of what was sent to ERP (one row per report; a bulk send shares a batch_id). status: queued | sending | submitted | failed.
     * The ERP Finish Monitoring page lists it and tells the submitter when a report is completely sent.
     */
    public static function ensureLogTable(): void
    {
        self::db()->query("CREATE TABLE IF NOT EXISTS tbl_erp_submissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            labref_id INT UNSIGNED NULL,
            lab_ref_no VARCHAR(80) NOT NULL DEFAULT '',
            production_date DATE NULL,
            mode VARCHAR(10) NOT NULL DEFAULT 'single',
            batch_id VARCHAR(40) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            message VARCHAR(500) NOT NULL DEFAULT '',
            requested_by_id INT UNSIGNED NOT NULL DEFAULT 0,
            requested_by_name VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            submitted_at DATETIME NULL,
            payload MEDIUMTEXT NULL,
            KEY ix_lab_ref (lab_ref_no),
            KEY ix_batch (batch_id),
            KEY ix_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // a table made before the payload existed
        $have = array_column(self::db()->query('SHOW COLUMNS FROM tbl_erp_submissions')->getResultArray(), 'Field');
        if (! in_array('payload', $have, true)) {
            self::db()->query('ALTER TABLE tbl_erp_submissions ADD COLUMN payload MEDIUMTEXT NULL');
        }
        if (! in_array('dispatched_at', $have, true)) {
            self::db()->query('ALTER TABLE tbl_erp_submissions ADD COLUMN dispatched_at DATETIME NULL');   // when the ERP agent accepted it
        }
    }

    /** an ERP result word: only PASS, TOLERABLE or FAILED are accepted by ERP; '' when the report has no (known) result */
    public static function erpResult(string $raw): string
    {
        $r = strtoupper(trim($raw));

        return str_contains($r, 'FAIL') ? 'FAILED' : (str_contains($r, 'TOLER') ? 'TOLERABLE' : (str_starts_with($r, 'PASS') ? 'PASS' : ''));
    }

    /** any date text of NEXUS-L (2026-11-03, 03 Nov 2026, 11-03-2026) => Y-m-d, or '' */
    public static function ymd($v): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $v, $m)) {   // the screen's month-day-year
            return "{$m[3]}-{$m[1]}-{$m[2]}";
        }
        $t = strtotime($v);

        return $t ? date('Y-m-d', $t) : '';
    }

    /** a tracker cell => a clean Application / TR number, or '' */
    private static function appNo($v): string
    {
        $v = strtoupper(trim((string) $v));

        return preg_match('/^(A\d{6,}|TR\d{6,})$/', $v) ? $v : '';
    }

    /** puts $val into the current column; a different current one moves to the history (the workbook is older than what NEXUS-L saved) */
    private static function merge(array $row, string $key, string $val): array
    {
        $cur  = trim((string) ($row["etrf_{$key}_app"] ?? ''));
        $hist = array_values(array_filter(array_map('trim', explode(';', (string) ($row["etrf_{$key}_hist"] ?? ''))), 'strlen'));
        if ($cur === '') {
            $cur = $val;
        } elseif ($cur !== $val && ! in_array($val, $hist, true)) {
            $hist[] = $val;
        }

        return ["etrf_{$key}_app" => $cur, "etrf_{$key}_hist" => implode(';', $hist)];
    }

    /**
     * Reads every month sheet of the daily tracker workbook; for each Lab Reference that exists in tbl_dtr_unified_labrefs it fills the
     * bonding / pull test numbers, the ERP status and the InterLink flag. Returns counts.
     */
    public static function importWorkbook(string $path): array
    {
        self::ensureColumns();
        $db   = self::db();
        $book = XlsxSheets::read($path);
        $ref  = [];
        foreach ($db->query('SELECT id, lab_ref_no, etrf_bonding_app, etrf_bonding_hist, etrf_pull_app, etrf_pull_hist, erp_status, interlink_flag FROM tbl_dtr_unified_labrefs')->getResultArray() as $r) {
            $ref[strtoupper(trim($r['lab_ref_no']))][] = $r;
        }
        $out = ['sheets' => 0, 'rows' => 0, 'matched' => 0, 'bonding' => 0, 'pull' => 0, 'erp' => 0, 'flag' => 0];
        foreach ($book['sheets'] as $sh) {
            $cols = null;
            $out['sheets']++;
            foreach ($sh['grid'] as $row) {
                $vals = array_map(static fn ($c) => trim((string) ($c['v'] ?? '')), $row);
                if ($cols === null) {
                    if (($vals[0] ?? '') === 'Tested Date') {
                        $cols = [];
                        foreach ($vals as $i => $h) {
                            $h = strtoupper($h);
                            if ($h !== '' && ! isset($cols[$h])) {
                                $cols[$h] = $i;   // the first column of a name (later sheets repeat Lab Reference / Sarge Status further right)
                            }
                            if ($h === 'ERP STATUS') {
                                $cols['ERP'] = $i;
                            }
                        }
                    }
                    continue;
                }
                $lab = strtoupper($vals[$cols['LAB REFERENCE'] ?? 3] ?? '');
                if (! str_starts_with($lab, 'DTP')) {
                    continue;
                }
                $out['rows']++;
                if (! isset($ref[$lab])) {
                    continue;
                }
                $out['matched']++;
                $bond  = self::appNo($vals[$cols['BONDING TR NO.'] ?? 5] ?? '');
                $pull  = self::appNo($vals[$cols['PULL TEST TR NO.'] ?? 6] ?? '');
                $sarge = strtoupper($vals[$cols['SARGE STATUS'] ?? 7] ?? '');
                $erp   = strtoupper($vals[$cols['ERP'] ?? 10] ?? '');
                foreach ($ref[$lab] as &$r) {
                    $upd = [];
                    if ($bond !== '') {
                        $upd += self::merge($r, 'bonding', $bond);
                        $out['bonding']++;
                    }
                    if ($pull !== '') {
                        $upd += self::merge($r, 'pull', $pull);
                        $out['pull']++;
                    }
                    if ($erp === 'ERP OK' && $r['erp_status'] === '') {
                        $upd['erp_status'] = 'OK';
                        $out['erp']++;
                    }
                    if (preg_match('/(INTERLINK|SARGE)\s*OK/', $sarge) && $r['interlink_flag'] === '') {
                        $upd['interlink_flag'] = 'OK';
                        $out['flag']++;
                    }
                    if ($upd) {
                        $db->table('tbl_dtr_unified_labrefs')->where('id', $r['id'])->update($upd);
                        $r = array_merge($r, $upd);
                    }
                }
                unset($r);
            }
        }

        return $out;
    }
}
