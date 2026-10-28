<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark dtr:sync-access [--dry] [--table=Standards]
 *
 * Brings everything in the QC Lab Access databases (D:\#Digital Test Report) into dbnexus: standards with their
 * tolerances, all material and bonding test results, master lists, DSR, audit trail. Access is read through .NET
 * (app/Libraries/export_access_tables.ps1) so Chinese text arrives intact.
 *
 * - mirror tables (reference data): Access is the master; missing rows are added and changed rows updated.
 * - merge tables (test results): missing rows are added, blank or "???" values filled, and a row is only overwritten
 *   when Access has a newer LastModified, so reports edited in the web are kept.
 * - a row whose ID is used by a different Lab Ref in dbnexus is never touched; it is listed as a conflict.
 * - reports that arrived from Access get their row in tbl_dtr_unified_labrefs (DTR tracker, InterLink Lab Ref list).
 */
class DtrSyncAccess extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'dtr:sync-access';
    protected $description = 'Imports all standards, tolerances, test results and master data from the Access databases into dbnexus.';
    protected $usage       = 'dtr:sync-access [--dry] [--show] [--log] [--table=AccessTableName]';
    protected $options     = ['--dry' => 'Only report what would change', '--table' => 'Sync one Access table only', '--show' => 'Print sample field differences', '--log' => 'Append a summary to writable/logs/dtr-sync-access.log (scheduled runs)'];

    private const MATERIAL_DB = 'D:\\#Digital Test Report\\V20261006.accdb';
    private const BONDING_DB  = 'D:\\#Digital Test Report\\BondingReportV2.accdb';

    /** Access table => [front-end, dbnexus table, key column (null = no key), mode] */
    private const MAP = [
        'Standards'                        => ['mat', 'tbl_dtr_standards', 'ID', 'mirror'],
        'tblFieldMap'                      => ['mat', 'tbl_dtr_field_map', 'ID1', 'mirror'],
        'tblFormNo'                        => ['mat', 'tbl_dtr_form_no', 'ID', 'mirror'],
        'tblPermissions'                   => ['mat', 'tbl_dtr_permissions', 'ID', 'mirror'],
        'Settings'                         => ['mat', 'tbl_dtr_settings', 'ID', 'mirror'],
        'Filters'                          => ['mat', 'tbl_dtr_filters', 'ID', 'mirror'],
        'copycopy'                         => ['mat', 'tbl_dtr_copycopy', 'ID', 'mirror'],
        'MaterialList'                     => ['mat', 'tbl_dtr_material_list', 'ID', 'mirror'],
        'ShoeList'                         => ['mat', 'tbl_dtr_shoe_list', 'ID', 'mirror'],
        'tblMaterialTypeStatus'            => ['mat', 'tbl_dtr_material_type_status', 'ID', 'mirror'],
        'tblContainers'                    => ['mat', 'tbl_dtr_containers', 'ContainerID', 'mirror'],
        'tblMaterialsMaster'               => ['mat', 'tbl_dtr_materials_master', 'MaterialID', 'mirror'],
        'tblImportRaw'                     => ['mat', 'tbl_dtr_import_raw', 'ID', 'mirror'],
        'tblDSR'                           => ['mat', 'tbl_dtr_dsr', null, 'mirror'],
        'tblAuditTrail'                    => ['mat', 'tbl_dtr_audit_trail', 'ID', 'merge'],
        'FabricTestResults'                => ['mat', 'tbl_dtr_fabric_results', 'ID', 'merge'],
        'LeatherTestResults'               => ['mat', 'tbl_dtr_leather_results', 'ID', 'merge'],
        'BoxTestResults'                   => ['mat', 'tbl_dtr_box_results', 'ID', 'merge'],
        'FoxingTapeHeelLogoTestResults'    => ['mat', 'tbl_dtr_fthl_results', 'ID', 'merge'],
        'HardwareTestResults'              => ['mat', 'tbl_dtr_hardware_results', 'ID', 'merge'],
        'InsoleTestResults'                => ['mat', 'tbl_dtr_insole_results', 'ID', 'merge'],
        'LabelTestResults'                 => ['mat', 'tbl_dtr_label_results', 'ID', 'merge'],
        'RubberTestResults'                => ['mat', 'tbl_dtr_rubber_results', 'ID', 'merge'],
        'SocklinerFoamTestResults'         => ['mat', 'tbl_dtr_sklfm_results', 'ID', 'merge'],
        'WebbingShoelaceThreadTestResults' => ['mat', 'tbl_dtr_wst_results', 'ID', 'merge'],
        'tblBally'                         => ['mat', 'tbl_dtr_bally_results', 'BallyID', 'merge'],
        'tblLaminar'                       => ['mat', 'tbl_dtr_laminar_results', 'FabricLaminarID', 'merge'],
        'BondingTestInfo'                  => ['bond', 'tbl_dtr_bonding_info', 'BondingTestID', 'merge'],
        'BondingTestPoints'                => ['bond', 'tbl_dtr_bonding_points', 'BondingPointstID', 'merge'],
    ];
    // not copied: tblUsers (Access passwords), MainMenu / tblLoginTracker (empty), Paste Errors (scratch table)

    private $db;
    private bool $dry = false;
    private bool $show = false;

    public function run(array $params)
    {
        $this->dry = CLI::getOption('dry') !== null;
        $this->show = CLI::getOption('show') !== null;
        $log        = CLI::getOption('log') !== null;
        $notes      = [];
        ini_set('memory_limit', '1536M'); // DSR has 150k+ rows
        $t0 = microtime(true);
        $only      = (string) (CLI::getOption('table') ?? '');
        foreach (array_keys(CLI::getOptions()) as $o) { // --table=Name arrives as one option key
            if (str_starts_with((string) $o, 'table=')) {
                $only = substr((string) $o, 6);
            }
        }
        $this->db  = \Config\Database::connect();
        $map       = $only !== '' ? array_intersect_key(self::MAP, [$only => 1]) : self::MAP;
        if (! $map) {
            CLI::error('Unknown table ' . $only . '. Known: ' . implode(', ', array_keys(self::MAP)));

            return EXIT_ERROR;
        }

        $dir = WRITEPATH . 'cache/access_sync/';
        foreach (['mat' => self::MATERIAL_DB, 'bond' => self::BONDING_DB] as $fe => $path) {
            $tables = array_keys(array_filter($map, static fn ($m) => $m[0] === $fe));
            if (! $tables) {
                continue;
            }
            CLI::write('Reading ' . count($tables) . ' table(s) from ' . $path . ' …', 'white');
            $out = (string) shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg(APPPATH . 'Libraries/export_access_tables.ps1')
                . ' -Database ' . escapeshellarg($path) . ' -Tables ' . escapeshellarg(implode(',', $tables)) . ' -OutDir ' . escapeshellarg($dir) . ' 2>&1');
            foreach (preg_split('/\R/', trim($out)) as $l) {
                if (str_starts_with($l, 'ERR')) {
                    CLI::error('  ' . $l);
                    $notes[] = trim($l);
                }
            }
        }

        CLI::write(sprintf('%-34s %8s %8s %8s %8s %8s', 'Access table', 'Access', 'added', 'updated', 'filled', 'conflict'), 'white');
        $tot = ['added' => 0, 'updated' => 0, 'filled' => 0, 'conflict' => 0];
        foreach ($map as $acc => [, $table, $key, $mode]) {
            $file = $dir . preg_replace('/[^A-Za-z0-9_-]/', '_', $acc) . '.ndjson';
            if (! is_file($file)) {
                CLI::write(sprintf('%-34s %s', $acc, 'not read'), 'red');
                $notes[] = $acc . ' not read';

                continue;
            }
            $r = $this->syncTable($file, $table, $key, $mode);
            foreach ($tot as $k => $v) {
                $tot[$k] = $v + $r[$k];
            }
            CLI::write(sprintf('%-34s %8d %8d %8d %8d %8d', $acc, $r['rows'], $r['added'], $r['updated'], $r['filled'], $r['conflict']), $r['conflict'] ? 'yellow' : 'green');
            if ($r['added'] + $r['updated'] + $r['filled'] + $r['conflict'] > 0) {
                $notes[] = sprintf('%s +%d ~%d fill %d conflict %d', $acc, $r['added'], $r['updated'], $r['filled'], $r['conflict']) . ($r['conflicts'] ? ' (' . implode('; ', array_slice($r['conflicts'], 0, 3)) . ')' : '');
            }
            foreach (array_slice($r['conflicts'], 0, 5) as $c) {
                CLI::write('    conflict ' . $c, 'yellow');
            }
            @unlink($file);
        }
        $idx = \App\Libraries\DtrLabrefIndex::addMissing($this->db, $this->dry);
        CLI::write(sprintf('%-34s %8s %8d', 'Lab Ref list (unified)', '', $idx), 'green');
        $sum = sprintf('%s added %d, updated %d, filled %d, conflicts %d', $this->dry ? 'DRY RUN -' : 'Done -', $tot['added'], $tot['updated'], $tot['filled'], $tot['conflict']);
        CLI::write($sum, 'white');
        if ($idx) {
            $notes[] = "Lab Ref list +{$idx}";
        }
        if ($log) {
            $file = WRITEPATH . 'logs/dtr-sync-access.log';
            if (is_file($file) && filesize($file) > 5 * 1024 * 1024) {
                @rename($file, $file . '.1'); // keeps one older log
            }
            file_put_contents($file, date('Y-m-d H:i:s') . ' ' . $sum . ' (' . round(microtime(true) - $t0) . " s)
"
                . implode('', array_map(static fn ($n) => '    ' . $n . "
", $notes)), FILE_APPEND | LOCK_EX);
        }

        return EXIT_SUCCESS;
    }

    private function q(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }

    /** Value as compared between Access and MySQL: numbers without float noise, dates without a midnight time. */
    private static function norm($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        $s = trim((string) $v);
        if ($s !== '' && is_numeric($s)) {
            $f = (float) $s;

            return abs($f) < 1e15 && floor($f) === $f ? (string) (int) $f : rtrim(rtrim(sprintf('%.6F', $f), '0'), '.');
        }

        return preg_replace('/^(\d{4}-\d{2}-\d{2}) 00:00:00$/', '$1', $s);
    }

    /** dbnexus holds "???" where the first migration lost the Chinese characters that Access has. */
    private static function corrupted(string $mine, string $theirs): bool
    {
        return str_contains($mine, '?') && preg_match('/[^\x00-\x7F]/u', $theirs) === 1
            && $mine === preg_replace('/[^\x00-\x7F]/u', '?', $theirs);
    }

    private function sqlType(string $t): string
    {
        return match ($t) {
            'Int32', 'Int16', 'Byte' => 'INT NULL',
            'Double', 'Single', 'Decimal' => 'DOUBLE NULL',
            'DateTime' => 'DATETIME NULL',
            'Boolean' => 'TINYINT(1) NULL',
            default => 'TEXT NULL',
        };
    }

    /** Creates the table or adds the Access columns dbnexus does not have yet. */
    private function ensureColumns(string $table, array $cols): void
    {
        if (! $this->db->tableExists($table, false)) {
            $defs = array_map(fn ($c) => $this->q($c['name']) . ' ' . $this->sqlType($c['type']), $cols);
            $sql  = 'CREATE TABLE ' . $this->q($table) . ' (' . implode(', ', $defs) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $this->dry ? CLI::write('    would create ' . $table, 'yellow') : $this->db->query($sql);

            return;
        }
        $have = array_flip($this->db->getFieldNames($table));
        foreach ($cols as $c) {
            if (! isset($have[$c['name']])) {
                $sql = 'ALTER TABLE ' . $this->q($table) . ' ADD COLUMN ' . $this->q($c['name']) . ' ' . $this->sqlType($c['type']);
                $this->dry ? CLI::write('    would add column ' . $table . '.' . $c['name'], 'yellow') : $this->db->query($sql);
            }
        }
    }

    private function insertRows(string $table, array $names, array $rows): void
    {
        if ($this->dry || ! $rows) {
            return;
        }
        $cols = implode(', ', array_map(fn ($n) => $this->q($n), $names));
        foreach (array_chunk($rows, 300) as $chunk) {
            $vals = [];
            foreach ($chunk as $r) {
                $vals[] = '(' . implode(', ', array_map(fn ($n) => $this->db->escape($r[$n] ?? null), $names)) . ')';
            }
            $this->db->query('INSERT INTO ' . $this->q($table) . ' (' . $cols . ') VALUES ' . implode(', ', $vals));
        }
    }

    private function updateRow(string $table, string $key, $keyVal, array $set): void
    {
        if ($this->dry || ! $set) {
            return;
        }
        $parts = [];
        foreach ($set as $c => $v) {
            $parts[] = $this->q($c) . ' = ' . $this->db->escape($v);
        }
        $this->db->query('UPDATE ' . $this->q($table) . ' SET ' . implode(', ', $parts) . ' WHERE ' . $this->q($key) . ' = ' . $this->db->escape($keyVal));
    }

    private function syncTable(string $file, string $table, ?string $key, string $mode): array
    {
        $res = ['rows' => 0, 'added' => 0, 'updated' => 0, 'filled' => 0, 'conflict' => 0, 'conflicts' => []];
        $fh  = fopen($file, 'rb');
        $hdr = json_decode((string) fgets($fh), true);
        $cols  = $hdr['__cols'] ?? [];
        $names = array_column($cols, 'name');
        $this->ensureColumns($table, $cols);
        // keyless reference table (DSR): streamed, and replaced when its content differs
        if ($key === null) {
            $sig = static fn (array $r) => md5(implode("", array_map([self::class, 'norm'], array_values($r))));
            $a   = [];
            while (($l = fgets($fh)) !== false) {
                if (($r = json_decode($l, true)) !== null) {
                    $a[] = $sig($r);
                }
            }
            $res['rows'] = count($a);
            if (! $this->db->tableExists($table, false)) {
                fclose($fh);
                $res['added'] = count($a);

                return $res;
            }
            $m = [];
            $q = $this->db->query('SELECT ' . implode(', ', array_map(fn ($n) => $this->q($n), $names)) . ' FROM ' . $this->q($table));
            while ($r = $q->getUnbufferedRow('array')) {
                $m[] = $sig($r);
            }
            sort($a);
            sort($m);
            if ($a !== $m) {
                $res['updated'] = count($a);
                if (! $this->dry) {
                    rewind($fh);
                    fgets($fh);
                    $this->db->transStart();
                    $this->db->query('DELETE FROM ' . $this->q($table));
                    $batch = [];
                    while (($l = fgets($fh)) !== false) {
                        if (($r = json_decode($l, true)) !== null) {
                            $batch[] = $r;
                        }
                        if (count($batch) >= 3000) {
                            $this->insertRows($table, $names, $batch);
                            $batch = [];
                        }
                    }
                    $this->insertRows($table, $names, $batch);
                    $this->db->transComplete();
                }
            }
            fclose($fh);

            return $res;
        }

        $access = [];
        while (($l = fgets($fh)) !== false) {
            if (($r = json_decode($l, true)) !== null) {
                $access[] = $r;
            }
        }
        fclose($fh);
        $res['rows'] = count($access);
        if (! $this->db->tableExists($table, false)) {
            $res['added'] = count($access); // dry run of a new table

            return $res;
        }

        $mine  = [];
        $mineN = [];
        foreach ($this->db->query('SELECT ' . implode(', ', array_map(fn ($n) => $this->q($n), $names)) . ' FROM ' . $this->q($table))->getResultArray() as $r) {
            $mk           = self::norm($r[$key]);
            $mine[$mk]    = $r;
            $mineN[$mk][] = $r;
        }
        $labCol = in_array('LabRefNo', $names, true) ? 'LabRefNo' : null;
        $new    = [];
        $this->db->transStart();

        // Access sometimes repeats an ID (e.g. MaterialList 41371 x4): those rows are kept as a group, replaced as a whole
        // when the group differs, instead of overwriting each other on every run
        $groups = [];
        foreach ($access as $i => $a) {
            $groups[self::norm($a[$key] ?? null)][] = $i;
        }
        $sig = static fn (array $rows) => (static function (array $s) { sort($s); return $s; })(array_map(static fn ($r) => implode("\x1f", array_map([self::class, 'norm'], array_values($r))), $rows));
        foreach ($groups as $k => $idx) {
            if ($k === '' || count($idx) < 2 && count($mineN[$k] ?? []) < 2) {
                continue;
            }
            $rows = array_map(static fn ($i) => $access[$i], $idx);
            $have = array_map(static fn ($r) => array_intersect_key($r, array_flip($names)), $mineN[$k] ?? []);
            if ($mode === 'mirror' && $sig($rows) !== $sig($have)) {
                $res['updated'] += count($rows);
                if (! $this->dry) {
                    $this->db->query('DELETE FROM ' . $this->q($table) . ' WHERE ' . $this->q($key) . ' = ' . $this->db->escape($rows[0][$key]));
                    $this->insertRows($table, $names, $rows);
                }
            }
            foreach ($idx as $i) {
                unset($access[$i]);
            }
        }

        foreach ($access as $a) {
            $k = self::norm($a[$key] ?? null);
            if ($k === '') {
                continue;
            }
            if (! isset($mine[$k])) {
                $new[] = $a;

                continue;
            }
            $m = $mine[$k];
            if ($labCol && self::norm($a[$labCol]) !== '' && self::norm($m[$labCol]) !== '' && strcasecmp(self::norm($a[$labCol]), self::norm($m[$labCol])) !== 0) {
                $res['conflict']++;
                $res['conflicts'][] = "{$key} {$k}: Access {$a[$labCol]} / dbnexus {$m[$labCol]}";

                continue;
            }
            $newer = $mode === 'mirror'
                || (isset($a['LastModified'], $m['LastModified']) && strtotime((string) $a['LastModified']) > strtotime((string) $m['LastModified']));
            $set    = [];
            $filled = false;
            foreach ($names as $n) {
                $av = self::norm($a[$n] ?? null);
                $mv = self::norm($m[$n] ?? null);
                if ($av === $mv || $n === $key) {
                    continue;
                }
                if ($newer) {
                    $set[$n] = $a[$n];
                } elseif ($av !== '' && ($mv === '' || self::corrupted($mv, $av))) {
                    $set[$n] = $a[$n];
                    $filled  = true;
                }
            }
            if ($set && $this->show && count($res['conflicts']) < 3 && ($res['updated'] + $res['filled']) < 3) {
                foreach (array_slice($set, 0, 3, true) as $c => $v) {
                    CLI::write("    {$key} {$k} {$c}: dbnexus [" . mb_substr((string) $m[$c], 0, 60) . '] -> Access [' . mb_substr((string) $v, 0, 60) . ']', 'light_gray');
                }
            }
            if ($set) {
                $this->updateRow($table, $key, $m[$key], $set);
                $newer ? $res['updated']++ : ($filled ? $res['filled']++ : null);
            }
        }
        $this->insertRows($table, $names, $new);
        $this->db->transComplete();
        $res['added'] = count($new);

        return $res;
    }
}
