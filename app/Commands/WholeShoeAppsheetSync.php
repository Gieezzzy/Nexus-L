<?php

namespace App\Commands;

use App\Libraries\DataCache;
use App\Libraries\DtrLabrefIndex;
use App\Libraries\EtrfAppNos;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark wholeshoe:appsheet-sync [file.tsv] [--apply]
 *
 * Brings the whole shoes read from the AppSheet "Whole Shoe Log" into NEXUS-L with the parent and its tests connected:
 *  - the mother Lab Ref of a whole shoe is DTP<date>WH<building><line>-<side of the AppSheet mother>; its intake rows, its Lab Tracker (index) rows and every test (tbl_dtr_wholeshoetracker.WholeshoeLabRefNo) point to it;
 *  - missing tests / shoes are added, a test only moves forward (PENDING > RUNNING > COMPLETED), nothing is deleted;
 *  - the TR (Application No) of the whole shoe is the same for all its tests: it is put in the Flexing / Aging / Hydrolysis / Washing / UV boxes of the mother (ERP Finish Monitoring, Whole Shoe tab) and the ERP status is OK.
 * Without --apply it only prints what it would do. A backup of every row it changes is written to writable/wholeshoe-scan/.
 * File lines (tab separated): AppSheetRowId, production date, AppSheet mother ref, kind (U = uploaded in InterLink / ERP OK, E = ERP OK, N = other), prod code, MTL, TR, tests (Type|ref | status | result ~ ...)
 */
class WholeShoeAppsheetSync extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'wholeshoe:appsheet-sync';
    protected $description = 'Connect AppSheet whole shoes (parent + tests + TR) in NEXUS-L; dry run unless --apply.';
    protected $usage       = 'wholeshoe:appsheet-sync [file.tsv] [--apply]';

    private const TYPES = [
        'flexing' => ['Flexing', 'FX'], 'aging' => ['Heat Aging', 'HA'], 'hydrolysis' => ['Hydrolysis', 'HY'], 'washing' => ['Washing', 'WG'], 'uv' => ['UV', 'UV'],
    ];

    private array $backup = [];

    public function run(array $params)
    {
        date_default_timezone_set('Asia/Manila');
        $file  = (string) ($params[0] ?? WRITEPATH . 'wholeshoe-scan/appsheet-import.tsv');
        $apply = CLI::getOption('apply') !== null;
        if (! is_file($file)) {
            CLI::error("File not found: {$file}");

            return;
        }
        $db = \Config\Database::connect();
        EtrfAppNos::ensureColumns();
        $rows = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $c = explode("\t", $line);
            if (count($c) < 7) {
                continue;
            }
            $tests = [];
            foreach (array_filter(explode('~', (string) ($c[7] ?? ''))) as $t) {
                $p = array_map('trim', explode('|', $t));
                $tests[] = ['key' => $this->keyOf($p[0] ?? ''), 'ref' => $p[1] ?? '', 'status' => $p[2] ?? '', 'result' => $p[3] ?? ''];
            }
            $rows[] = ['id' => $c[0], 'date' => $c[1], 'mother' => $c[2], 'kind' => $c[3], 'prod' => $c[4], 'mtl' => $c[5], 'tr' => trim($c[6]), 'tests' => $tests];
        }
        CLI::write(($apply ? 'APPLY' : 'DRY RUN') . ' - ' . count($rows) . ' AppSheet whole shoes');
        $db->transStart();
        $stat = ['shoes' => 0, 'skipped' => 0, 'tests_moved' => 0, 'tests_added' => 0, 'tests_status' => 0, 'tr_set' => 0];
        $used = [];
        foreach ($rows as $r) {
            $this->shoe($db, $r, $apply, $used, $stat);
        }
        $db->transComplete();
        if ($apply) {
            $bk = WRITEPATH . 'wholeshoe-scan/backup-' . date('Ymd-His') . '.json';
            file_put_contents($bk, json_encode($this->backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            DataCache::bump('wholeshoe');
            CLI::write("Backup of changed rows: {$bk}");
        }
        CLI::write(json_encode($stat));
        if ($apply && ! $db->transStatus()) {
            CLI::error('The database refused part of it; nothing was changed.');
        }
    }

    private function keyOf(string $name): string
    {
        $n = strtoupper($name);

        return str_contains($n, 'FLEX') ? 'flexing' : (str_contains($n, 'AGING') ? 'aging' : (str_contains($n, 'HYDRO') ? 'hydrolysis' : (str_contains($n, 'WASH') ? 'washing' : 'uv')));
    }

    private function rank(string $s): int
    {
        $s = strtoupper($s);

        return str_starts_with($s, 'COMPLETE') ? 2 : ($s === 'RUNNING' || $s === 'PAUSED' ? 1 : 0);
    }

    private function bk(string $table, string $pk, array $row): void
    {
        $this->backup[$table][$row[$pk]] ??= $row;
    }

    private function shoe($db, array $r, bool $apply, array &$used, array &$stat): void
    {
        $id   = $r['id'];
        $trk  = $db->table('tbl_dtr_wholeshoetracker')->where('ShoeID', $id)->get()->getResultArray();
        $ink  = $db->table('tbl_dtr_wholeshoe_intake')->where('ShoeID', $id)->get()->getResultArray();
        $uni  = $db->table('tbl_dtr_unified_labrefs')->where('form_slug', 'wholeshoe')->where('appsheet_id', $id)->get()->getResultArray();
        $adoptT = $adoptI = [];
        if (! $trk && ! $ink && ! $uni) {
            // entered in NEXUS-L directly (no AppSheet id yet): the same production code within two days of the AppSheet date is the same whole shoe
            $win = 'BETWEEN DATE_SUB(?, INTERVAL 2 DAY) AND DATE_ADD(?, INTERVAL 2 DAY)';
            $adoptT = $db->query("SELECT * FROM tbl_dtr_wholeshoetracker WHERE COALESCE(ShoeID, '') = '' AND LOWER(ProductionCode) = LOWER(?) AND COALESCE(ProductionDate, CollectionDate) {$win}", [$r['prod'], $r['date'], $r['date']])->getResultArray();
            $adoptI = $db->query("SELECT * FROM tbl_dtr_wholeshoe_intake WHERE COALESCE(ShoeID, '') = '' AND LOWER(ProdCode) = LOWER(?) AND COALESCE(ProductionDate, CollectionDate) {$win}", [$r['prod'], $r['date'], $r['date']])->getResultArray();
            $trk = $adoptT;
            $ink = $adoptI;
        }
        $here = $trk || $ink || $uni;
        if (! preg_match('/^DTP(\d{8})WS([AB])(\d+)-([RL])$/', $r['mother'], $m)) {
            $sd = $ink[0]['Side'] ?? ($trk[0]['Side'] ?? '');
            if ($sd === '') {   // all the tests of the AppSheet line are of one side: that is the side of the whole shoe
                $sides = array_unique(array_filter(array_map(static fn ($t) => preg_match('/-([RL])(?:-\d+)?$/', (string) $t['ref'], $mm) ? $mm[1] : '', $r['tests'])));
                $sd    = count($sides) === 1 ? reset($sides) : '';
            }
            if ($sd !== '' && preg_match('/^DTP(\d{8})WS([AB])(\d+)-?$/', $r['mother'], $m2)) {
                $m = [$r['mother'], $m2[1], $m2[2], $m2[3], $sd];
            } else {
                CLI::write("SKIP  {$r['mother']} ({$id}): the AppSheet mother has no building/line/side (a trial or an incomplete Lab Ref) - tell me the side to add it");
                $stat['skipped']++;

                return;
            }
        }
        [$ymd, $bld, $line, $side] = [$m[1], $m[2], $m[3], $m[4]];
        $mother = "DTP{$ymd}WH{$bld}{$line}-{$side}";
        for ($n = 2; isset($used[$mother]) && $used[$mother] !== $id || $this->taken($db, $mother, $id, array_column($adoptT, 'TrackerID'), array_column($adoptI, 'IntakeID')); $n++) {
            $mother = "DTP{$ymd}WH{$bld}{$line}-{$side}-{$n}";
        }
        $used[$mother] = $id;
        $stat['shoes']++;
        if ($apply) {
            foreach ($adoptT as $x) {
                $this->bk('tbl_dtr_wholeshoetracker', 'TrackerID', $x);
                $db->table('tbl_dtr_wholeshoetracker')->where('TrackerID', $x['TrackerID'])->update(['ShoeID' => $id]);
            }
            foreach ($adoptI as $x) {
                $this->bk('tbl_dtr_wholeshoe_intake', 'IntakeID', $x);
                $db->table('tbl_dtr_wholeshoe_intake')->where('IntakeID', $x['IntakeID'])->update(['ShoeID' => $id]);
            }
        }
        if ($adoptT || $adoptI) {
            CLI::write('      entered in NEXUS-L directly: ' . count($adoptT) . ' test(s) and ' . count($adoptI) . ' collection line(s) are connected to this whole shoe');
        }
        CLI::write("SHOE  {$r['mother']} ({$id}) -> parent {$mother}" . ($r['tr'] !== '' ? "  TR {$r['tr']}" : '  (no TR)'));

        $shoe = $db->table('tbl_dtr_shoe_list')->where('ProdCode', $r['prod'])->get(1)->getRowArray();
        if (! $shoe) {
            CLI::write("      note: production code {$r['prod']} is not in the shoe list (style stays empty)");
        }
        $now = date('Y-m-d H:i:s');

        // tests
        $present = [];   // the tests this whole shoe really has (recorded or added): the TR goes into their boxes only
        foreach ($r['tests'] as $t) {
            [$tt, $code] = self::TYPES[$t['key']];
            $mine = array_values(array_filter($trk, fn ($x) => $this->keyOf((string) $x['TestType']) === $t['key'] && (string) $x['TestType'] !== ''));
            $st   = str_starts_with(strtolower($t['status']), 'done') ? 'COMPLETED' : (stripos($t['status'], 'progress') !== false ? 'RUNNING' : 'PENDING');
            $fr   = ['PASSED' => 'PASS', 'FAILED' => 'FAIL', 'TOLERABLE' => 'TOLERABLE'][strtoupper($t['result'])] ?? null;
            if ($mine) {
                $present[$t['key']] = true;
                foreach ($mine as $x) {
                    $upd = [];
                    if ((string) $x['WholeshoeLabRefNo'] !== $mother) {
                        $upd['WholeshoeLabRefNo'] = $mother;
                        $stat['tests_moved']++;
                    }
                    if ($this->rank($st) > $this->rank((string) $x['Status'])) {
                        $upd['Status'] = $st;
                        $upd['FinalResult'] = $fr ?? $x['FinalResult'];
                        $stat['tests_status']++;
                    } elseif (($x['FinalResult'] === null || $x['FinalResult'] === '') && $fr !== null && $this->rank((string) $x['Status']) === 2) {
                        $upd['FinalResult'] = $fr;
                    }
                    if ($upd) {
                        CLI::write("      test {$x['LabRefNo']}: " . json_encode($upd));
                        if ($apply) {
                            $this->bk('tbl_dtr_wholeshoetracker', 'TrackerID', $x);
                            $db->table('tbl_dtr_wholeshoetracker')->where('TrackerID', $x['TrackerID'])->update($upd);
                        }
                    }
                }
                continue;
            }
            // not recorded under this shoe: take a row that already carries this lab ref (same production code, no AppSheet id yet), else add it
            $ref  = rtrim($t['ref'], '-');   // AppSheet has a few Lab Refs with a dangling dash (DTP20261107UVB10-)
            $have = $db->table('tbl_dtr_wholeshoetracker')->where('LabRefNo', $ref)->get(1)->getRowArray();
            if ($st === 'PENDING' && $r['kind'] !== 'N' && ! ($have && trim((string) $have['ShoeID']) === '' && strcasecmp((string) $have['ProductionCode'], $r['prod']) === 0)) {
                CLI::write("      test {$ref}: not added (AppSheet: {$t['status']} - never started)");

                continue;
            }
            $present[$t['key']] = true;
            if ($have && trim((string) $have['ShoeID']) === '' && strcasecmp((string) $have['ProductionCode'], $r['prod']) === 0) {
                CLI::write("      test {$ref}: connected to the parent (existing row)");
                $stat['tests_moved']++;
                if ($apply) {
                    $this->bk('tbl_dtr_wholeshoetracker', 'TrackerID', $have);
                    $db->table('tbl_dtr_wholeshoetracker')->where('TrackerID', $have['TrackerID'])->update(['ShoeID' => $id, 'WholeshoeLabRefNo' => $mother]);
                }
                continue;
            }
            for ($n = 2; $have; $n++) {
                $ref  = rtrim($t['ref'], '-') . '-' . $n;
                $have = $db->table('tbl_dtr_wholeshoetracker')->where('LabRefNo', $ref)->get(1)->getRowArray();
            }
            $sd = preg_match('/-([RL])(?:-\d+)?$/', $t['ref'], $sm) ? $sm[1] : $side;
            $row = [
                'LabRefNo' => $ref, 'Building' => $bld, 'LineNo' => $line, 'ProductionCode' => $r['prod'], 'Style' => (string) ($shoe['StyleNo'] ?? ''), 'Colorway' => (string) ($shoe['Colorway'] ?? ''),
                'MTL' => $r['mtl'], 'NoOfShoes' => 1, 'CollectionDate' => $r['date'], 'ProductionDate' => $r['date'], 'EncodedBy' => 'AppSheet import', 'CreatedAt' => $now,
                'WholeshoeLabRefNo' => $mother, 'TestType' => $tt, 'TestCode' => $code, 'Side' => $sd, 'Status' => $st, 'FinalResult' => $fr, 'ShoeID' => $id, 'SourceSystem' => 'appsheet', 'IntakeType' => 'DAILY',
            ];
            CLI::write("      test {$ref}: added ({$tt}, {$st}" . ($fr ? ", {$fr}" : '') . ')');
            $stat['tests_added']++;
            if ($apply) {
                $db->table('tbl_dtr_wholeshoetracker')->insert($row);
            }
        }

        // intake rows (the shoe's collection lines) -> parent Lab Ref
        $iids = [];
        foreach ($ink as $i) {
            $iids[] = (int) $i['IntakeID'];
            if ((string) $i['LabRefNo'] !== $mother) {
                CLI::write("      intake #{$i['IntakeID']}: {$i['LabRefNo']} -> {$mother}");
                if ($apply) {
                    $this->bk('tbl_dtr_wholeshoe_intake', 'IntakeID', $i);
                    $db->table('tbl_dtr_wholeshoe_intake')->where('IntakeID', $i['IntakeID'])->update(['LabRefNo' => $mother]);
                }
            }
        }
        if (! $ink) {
            CLI::write('      intake: added');
            if ($apply) {
                $db->table('tbl_dtr_wholeshoe_intake')->insert([
                    'Building' => $bld, 'LineNo' => $line, 'Side' => $side, 'LabRefNo' => $mother, 'ProdCode' => $r['prod'], 'NoOfShoes' => 1,
                    'TestTypes' => implode(', ', array_map(fn ($t) => self::TYPES[$t['key']][0], $r['tests'])), 'CollectionDate' => $r['date'], 'ProductionDate' => $r['date'],
                    'EncodedBy' => 'AppSheet import', 'CreatedAt' => $now, 'ShoeID' => $id, 'Style' => (string) ($shoe['StyleNo'] ?? ''), 'Colorway' => (string) ($shoe['Colorway'] ?? ''), 'MTL' => $r['mtl'], 'SourceSystem' => 'appsheet',
                ]);
                $iids[] = (int) $db->insertID();
            }
        }

        // Lab Tracker (index) rows: their lab ref is the parent; refresh status/result from the tests
        if ($apply) {
            foreach ($uni as $u) {
                $this->bk('tbl_dtr_unified_labrefs', 'id', $u);
            }
            foreach ($iids as $iid) {
                DtrLabrefIndex::upsertWholeShoe($db, $iid);
            }
            if ($iids) {
                $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_wholeshoe_intake')->whereIn('source_id', $iids)->update(['appsheet_id' => $id, 'source_system' => 'appsheet']);
            }
        } elseif (! $uni && ! $ink) {
            CLI::write('      Lab Tracker row: added');
        }

        // the TR: the same for every test of the whole shoe
        if ($r['tr'] !== '') {
            $keys = array_keys($present);
            CLI::write('      TR ' . $r['tr'] . ' -> ' . implode(', ', $keys) . ' + whole shoe' . ($r['kind'] !== 'N' ? '; ERP status OK' : ''));
            $stat['tr_set']++;
            if ($apply) {
                foreach ($db->table('tbl_dtr_unified_labrefs')->where('form_slug', 'wholeshoe')->where('lab_ref_no', $mother)->get()->getResultArray() as $u) {
                    $this->bk('tbl_dtr_unified_labrefs', 'id', $u);
                    $set = [];
                    foreach (array_merge($keys, ['whole']) as $k) {
                        $cur = trim((string) $u["etrf_{$k}_app"]);
                        if ($cur === $r['tr']) {
                            continue;
                        }
                        $hist = array_values(array_filter(array_map('trim', explode(';', (string) ($u["etrf_{$k}_hist"] ?? ''))), 'strlen'));
                        if ($cur !== '' && ! in_array($cur, $hist, true)) {
                            $hist[] = $cur;
                        }
                        $set["etrf_{$k}_app"]  = $r['tr'];
                        $set["etrf_{$k}_hist"] = implode(';', array_diff($hist, [$r['tr']]));
                    }
                    if ($r['kind'] !== 'N') {
                        $set['erp_status'] = 'OK';
                    }
                    if ($set) {
                        $db->table('tbl_dtr_unified_labrefs')->where('id', $u['id'])->update($set);
                    }
                }
            }
        }
    }

    /** the parent Lab Ref is already used by another whole shoe (another AppSheet id) in the index, the intake or the tests */
    private function taken($db, string $mother, string $id, array $trkIds = [], array $inkIds = []): bool
    {
        $ni = $inkIds ? ' AND IntakeID NOT IN (' . implode(',', array_map('intval', $inkIds)) . ')' : '';
        $nt = $trkIds ? ' AND TrackerID NOT IN (' . implode(',', array_map('intval', $trkIds)) . ')' : '';
        $nu = $inkIds ? " AND NOT (source_table = 'tbl_dtr_wholeshoe_intake' AND source_id IN (" . implode(',', array_map('intval', $inkIds)) . '))' : '';

        return $db->query("SELECT COUNT(*) n FROM tbl_dtr_unified_labrefs WHERE form_slug = 'wholeshoe' AND lab_ref_no = ? AND COALESCE(appsheet_id, '') <> ?{$nu}", [$mother, $id])->getRow('n') > 0
            || $db->query("SELECT COUNT(*) n FROM tbl_dtr_wholeshoe_intake WHERE LabRefNo = ? AND COALESCE(ShoeID, '') <> ?{$ni}", [$mother, $id])->getRow('n') > 0
            || $db->query("SELECT COUNT(*) n FROM tbl_dtr_wholeshoetracker WHERE WholeshoeLabRefNo = ? AND COALESCE(ShoeID, '') <> ?{$nt}", [$mother, $id])->getRow('n') > 0;
    }
}
