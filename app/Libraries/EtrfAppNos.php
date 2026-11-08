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
        foreach (array_unique(self::KEYS) as $k) {
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
