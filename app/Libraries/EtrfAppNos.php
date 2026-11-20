<?php

namespace App\Libraries;

/**
 * One Lab Reference can be sent through several eTRF types (Material, Product, PULLTEST, bonding, Whole Shoe, Product Adult), and every
 * submission gets its own InterLink Application No. These columns on tbl_dtr_unified_labrefs keep them apart:
 *   etrf_<key>_app   the current (latest) Application No. of that eTRF type for the Lab Ref
 *   etrf_<key>_hist  the earlier ones, separated by ";"  (for example  A006001;A006045)
 */
class EtrfAppNos
{
    /** eTRF template code (tbl_interlink_etrf_requests.etrf_type) => column key */
    public const KEYS = [
        'eTRF-Mat'                  => 'mat',
        'eTRF-Prod'                 => 'prod',
        'eTRF-PULL'                 => 'pull',
        'VF eTRF-bonding'           => 'bonding',
        'VF eTRF-Whole Shoe'        => 'whole',
        'VF eTRF - Product (Adult)' => 'adult',
    ];

    /** The whole shoe tests, one TR no. each in the ERP (Finished Product Test Report: Flexing, Aging, Hydrolysis, Washing, UV Light Test): column key => the tracker's test types */
    public const TEST_KEYS = [
        'flexing'   => ['FLEXING'],
        'aging'     => ['HEAT AGING', 'AGING'],
        'hydrolysis' => ['HYDROLYSIS'],
        'washing'   => ['WASHING'],
        'uv'        => ['UV', 'UV LIGHT'],
    ];

    private static bool $ready = false;

    private static function db()
    {
        return \Config\Database::connect();
    }

    /** the column key for an eTRF type code or label; null when it is not one of ours */
    public static function keyFor(string $type): ?string
    {
        $type = trim($type);
        if (isset(self::KEYS[$type])) {
            return self::KEYS[$type];
        }
        foreach (self::KEYS as $code => $k) {
            if (strcasecmp($code, $type) === 0) {
                return $k;
            }
        }
        $t = strtolower($type);
        foreach (['whole' => 'whole shoe', 'adult' => 'adult', 'bonding' => 'bonding', 'pull' => 'pull', 'mat' => 'material', 'prod' => 'product'] as $k => $needle) {
            if (str_contains($t, $needle)) {
                return $k;
            }
        }

        return null;
    }

    /** adds the 12 columns when they are missing (cheap after the first call in a request) */
    public static function ensureColumns(): void
    {
        if (self::$ready) {
            return;
        }
        $db = self::db();
        if (! $db->tableExists('tbl_dtr_unified_labrefs')) {
            return;
        }
        $have = array_column($db->query('SHOW COLUMNS FROM tbl_dtr_unified_labrefs')->getResultArray(), 'Field');
        $add  = [];
        foreach (array_merge(array_unique(self::KEYS), array_keys(self::TEST_KEYS)) as $k) {
            if (! in_array("etrf_{$k}_app", $have, true)) {
                $add[] = "ADD COLUMN `etrf_{$k}_app` VARCHAR(60) NOT NULL DEFAULT ''";
            }
            if (! in_array("etrf_{$k}_hist", $have, true)) {
                $add[] = "ADD COLUMN `etrf_{$k}_hist` TEXT NULL";
            }
        }
        if ($add) {
            $db->query('ALTER TABLE tbl_dtr_unified_labrefs ' . implode(', ', $add));
        }
        self::$ready = true;
    }

    /**
     * A submission of eTRF type $type for $labRef got Application No. $appNo: it becomes the current one, and the one it replaces
     * moves into the history. Same number again: nothing changes. Returns false when nothing could be recorded.
     */
    public static function record(string $labRef, string $type, string $appNo): bool
    {
        $k = self::keyFor($type);
        if ($k === null || $labRef === '' || $appNo === '') {
            return false;
        }
        self::ensureColumns();
        $db   = self::db();
        $rows = $db->table('tbl_dtr_unified_labrefs')->select("id, etrf_{$k}_app AS cur, etrf_{$k}_hist AS hist")->where('lab_ref_no', $labRef)->get()->getResultArray();
        foreach ($rows as $r) {
            $cur = trim((string) $r['cur']);
            if ($cur === $appNo) {
                continue;
            }
            $hist = array_values(array_filter(array_map('trim', explode(';', (string) $r['hist'])), 'strlen'));
            if ($cur !== '' && ! in_array($cur, $hist, true)) {
                $hist[] = $cur;
            }
            $hist = array_values(array_diff($hist, [$appNo]));
            $db->table('tbl_dtr_unified_labrefs')->where('id', $r['id'])->update(["etrf_{$k}_app" => $appNo, "etrf_{$k}_hist" => implode(';', $hist)]);
        }

        return $rows !== [];
    }

    /**
     * Links a Lab Reference to the Application No. InterLink gave it: tbl_dtr_unified_labrefs.tr_no, the per-type column (etrf_<key>_app, the
     * earlier ones to _hist) and the TR / ETRFNo column of the DTR form the Lab Reference came from.
     *
     * @return int how many unified Lab Reference rows were linked
     */
    public static function linkToLabRef(string $labRef, string $type, string $appNo): int
    {
        $labRef = trim($labRef);
        $appNo  = strtoupper(trim($appNo));
        if ($labRef === '' || ! preg_match('/^A\d{6,}(-REV\d+)?$/', $appNo)) {
            return 0;
        }
        $db = self::db();
        $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $labRef)->update(['tr_no' => $appNo]);
        self::record($labRef, $type, $appNo);
        self::recordWholeShoeTest($labRef, $appNo);
        $n = 0;
        foreach ($db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $labRef)->get()->getResultArray() as $ur) {
            $n++;
            $slug = (string) ($ur['form_slug'] ?? '');
            $sid  = (int) ($ur['source_id'] ?? 0);
            $cfg  = $slug !== '' ? (\App\Controllers\DtrController::FORM_CONFIG[$slug] ?? null) : null;
            if ($cfg && $sid > 0 && $db->tableExists($cfg['table'])) {
                $by = $db->fieldExists('LabRefNo', $cfg['table']) ? ['LabRefNo' => $labRef] : [$cfg['pk'] => $sid];   // every record of this Lab Ref, not only the one the list points to
                if ($db->fieldExists('TR', $cfg['table'])) {
                    $db->table($cfg['table'])->where($by)->update(['TR' => $appNo]);
                } elseif ($db->fieldExists('ETRFNo', $cfg['table'])) {
                    $db->table($cfg['table'])->where($by)->update(['ETRFNo' => $appNo]);
                }
            }
        }

        return $n;
    }

    /**
     * The Application No. (eTRF No) of a Lab Reference, wherever it is kept, so the report always shows it: the Lab Tracker row (the current number of the eTRF type of this form,
     * then TR), any record of the same Lab Ref that already carries it (a Lab Ref can have two records), the last eTRF job InterLink accepted for it, the eTRF request.
     * '' when none is known.
     */
    public static function forLabRef(string $labRef, string $slug = ''): string
    {
        $labRef = trim($labRef);
        if ($labRef === '') {
            return '';
        }
        $db  = self::db();
        $ok  = static fn ($v) => (bool) preg_match('/^A\d{6,}(-REV\d+)?$/i', trim((string) $v));
        $key = ['bonding' => 'bonding', 'wholeshoe' => 'whole', 'pull' => 'pull', 'pulltest' => 'pull'][$slug] ?? ($slug !== '' ? 'mat' : '');
        foreach ($db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $labRef)->orderBy('id', 'DESC')->get()->getResultArray() as $u) {
            foreach (array_filter([$key !== '' ? ($u["etrf_{$key}_app"] ?? '') : '', $u['tr_no'] ?? '']) as $v) {
                if ($ok($v)) {
                    return strtoupper(trim((string) $v));
                }
            }
        }
        if ($db->tableExists('tbl_dtr_bonding_info')) {
            foreach ($db->table('tbl_dtr_bonding_info')->select('ETRFNo')->where('LabRefNo', $labRef)->where("COALESCE(ETRFNo, '') <>", '')->orderBy('BondingTestID', 'DESC')->get()->getResultArray() as $b) {
                if ($ok($b['ETRFNo'])) {
                    return strtoupper(trim((string) $b['ETRFNo']));
                }
            }
        }
        if ($db->tableExists('tbl_interlink_service_jobs')) {
            foreach ($db->table('tbl_interlink_service_jobs')->select('application_no')->where('lab_ref_no', $labRef)->where('status', 'done')->orderBy('id', 'DESC')->limit(8)->get()->getResultArray() as $j) {
                if ($ok($j['application_no'])) {
                    return strtoupper(trim((string) $j['application_no']));
                }
            }
        }
        if ($db->tableExists('tbl_interlink_etrf_requests')) {
            foreach ($db->table('tbl_interlink_etrf_requests')->select('application_no')->where('lab_ref_no', $labRef)->orderBy('id', 'DESC')->limit(8)->get()->getResultArray() as $j) {
                if ($ok($j['application_no'])) {
                    return strtoupper(trim((string) $j['application_no']));
                }
            }
        }

        return '';
    }

    /** the test key (flexing, aging, hydrolysis, washing, uv) of a whole shoe TEST Lab Ref (DTP20261116FXA3-L-2): from its tracker row, else from the letters of the Lab Ref */
    public static function testKeyOf(string $labRef): ?string
    {
        $db  = self::db();
        $row = $db->table('tbl_dtr_wholeshoetracker')->select('TestType')->where('LabRefNo', $labRef)->orderBy('TrackerID', 'DESC')->get(1)->getRowArray();
        $tt  = strtoupper(trim((string) ($row['TestType'] ?? '')));
        if ($tt === '' && preg_match('/^DTP\d{8}(FX|AG|HY|WS|WG|UV)[A-Z]\d+/i', $labRef, $m)) {
            $tt = ['FX' => 'FLEXING', 'AG' => 'HEAT AGING', 'HY' => 'HYDROLYSIS', 'WS' => 'WASHING', 'WG' => 'WASHING', 'UV' => 'UV'][strtoupper($m[1])];
        }
        foreach (self::TEST_KEYS as $k => $names) {
            if (in_array($tt, $names, true)) {
                return $k;
            }
        }

        return null;
    }

    /**
     * An Application No. (TR) of a whole shoe TEST goes to its whole shoe's Lab Ref (DTP20261116WHA3-L, the WholeshoeLabRefNo of the test) in the column of that test
     * (etrf_flexing_app ...), the earlier one to _hist, so the ERP Finish Monitoring (Whole Shoe tab) and the ERP agent have the five TR numbers on one line.
     */
    public static function recordWholeShoeTest(string $labRef, string $appNo): bool
    {
        $k = self::testKeyOf($labRef);
        if ($k === null || $appNo === '') {
            return false;
        }
        self::ensureColumns();
        $db   = self::db();
        $test = $db->table('tbl_dtr_wholeshoetracker')->select('WholeshoeLabRefNo')->where('LabRefNo', $labRef)->orderBy('TrackerID', 'DESC')->get(1)->getRowArray();
        $wh   = trim((string) ($test['WholeshoeLabRefNo'] ?? ''));
        if ($wh === '') {
            return false;
        }
        $rows = $db->table('tbl_dtr_unified_labrefs')->select("id, etrf_{$k}_app AS cur, etrf_{$k}_hist AS hist")->where('lab_ref_no', $wh)->get()->getResultArray();
        foreach ($rows as $r) {
            $cur = trim((string) $r['cur']);
            if ($cur === $appNo) {
                continue;
            }
            $hist = array_values(array_filter(array_map('trim', explode(';', (string) $r['hist'])), 'strlen'));
            if ($cur !== '' && ! in_array($cur, $hist, true)) {
                $hist[] = $cur;
            }
            $hist = array_values(array_diff($hist, [$appNo]));
            $db->table('tbl_dtr_unified_labrefs')->where('id', $r['id'])->update(["etrf_{$k}_app" => $appNo, "etrf_{$k}_hist" => implode(';', $hist)]);
        }

        return $rows !== [];
    }

    /** fills the columns from the submissions already saved in tbl_interlink_etrf_requests (oldest first, so the latest ends as current) */
    public static function backfill(): int
    {
        self::ensureColumns();
        $db = self::db();
        if (! $db->tableExists('tbl_interlink_etrf_requests')) {
            return 0;
        }
        $n = 0;
        foreach ($db->table('tbl_interlink_etrf_requests')->select('lab_ref_no, application_no, etrf_type')->where('application_no !=', '')->where('lab_ref_no !=', '')->orderBy('id')->get()->getResultArray() as $r) {
            if (preg_match('/^A\d{6,}$/i', (string) $r['application_no']) && self::record((string) $r['lab_ref_no'], (string) $r['etrf_type'], (string) $r['application_no'])) {
                $n++;
            }
        }

        return $n;
    }
}
