<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Fills the EMPTY fields of tbl_dtr_bonding_info from dbappsheet.tests_bonding (matched on the Lab Ref No).
 * Never overwrites a value that is already there, never adds or deletes a row.
 *   php spark dtr:appsheet-fill            dry run: shows what would be filled
 *   php spark dtr:appsheet-fill --apply    writes it (and refreshes the Lab Ref index of the filled rows)
 */
class AppsheetFill extends BaseCommand
{
    protected $group       = 'dtr';
    protected $name        = 'dtr:appsheet-fill';
    protected $description = 'Fill empty bonding fields (style, colorway, technician, result, dates, machine, failure ...) from dbappsheet.tests_bonding';

    public function run(array $params)
    {
        $apply = CLI::getOption('apply') !== null || in_array('--apply', $params, true);
        $db    = \Config\Database::connect();
        $C     = 'COLLATE utf8mb4_general_ci';

        $tech = [];
        foreach ($db->query('SELECT TechnicianID, TechnicianName FROM dbappsheet.technicians')->getResultArray() as $t) {
            $tech[trim((string) $t['TechnicianID'])] = trim((string) $t['TechnicianName']);
        }
        $cols = array_map('strtolower', $db->getFieldNames('tbl_dtr_bonding_info'));
        $has  = static fn (string $c) => in_array(strtolower($c), $cols, true);

        // newest AppSheet row per Lab Reference
        $src = [];
        foreach ($db->query("SELECT t.* FROM dbappsheet.tests_bonding t JOIN (SELECT TRIM(`Lab Reference`) lr, MAX(Row_ID) mx FROM dbappsheet.tests_bonding WHERE TRIM(`Lab Reference`) <> '' GROUP BY 1) m ON m.mx = t.Row_ID")->getResultArray() as $r) {
            $src[strtoupper(trim((string) $r['Lab Reference']))] = $r;
        }
        CLI::write('AppSheet rows (one per Lab Reference): ' . count($src));

        // second-line sources, used only for what tests_bonding does not have: shoes_master (style, colorway, Prod. Code, MTL #, production date) and the three bonding archives (result, technician, failure)
        $date  = static function ($v) { $v = trim((string) $v); if ($v === '') return ''; $ts = strtotime($v); return $ts ? date('Y-m-d', $ts) : ''; };
        $resOf = static function ($ts) { $ts = strtoupper(trim((string) $ts)); return str_starts_with($ts, 'PASS') ? 'PASS' : (str_starts_with($ts, 'FAIL') ? 'FAIL' : (str_starts_with($ts, 'TOL') ? 'TOLERABLE' : '')); };
        $tn    = static fn ($v) => $tech[trim((string) $v)] ?? (preg_match('/^\d+$/', trim((string) $v)) ? '' : trim((string) $v));
        $extra = [];
        $add   = static function (array &$extra, string $ref, array $vals): void {
            $ref = strtoupper(trim($ref));
            if ($ref === '') return;
            foreach ($vals as $k => $v) { if (trim((string) $v) !== '' && trim((string) $v) !== '-' && empty($extra[$ref][$k])) $extra[$ref][$k] = trim((string) $v); }
        };
        foreach ($db->query('SELECT * FROM dbappsheet.shoes_master ORDER BY Row_ID DESC')->getResultArray() as $r) {
            $add($extra, (string) ($r['Lab Reference'] ?? ''), ['ModelStyle' => $r['Style'] ?? '', 'Color' => $r['Colorway'] ?? '', 'POLotNO' => $r['Production Code'] ?? '', 'MTL' => $r['MTL#'] ?? '', 'ProductionDate' => $date($r['Production Date'] ?? ''), 'ShoeID' => $r['ShoeID'] ?? '']);
        }
        foreach (['bonding_tests_archive_april' => ['Lab Reference', 'FINISH_Bonding_Timestamp'], 'bonding_tests_archive_log' => ['Lab Ref No.', 'Finished Time'], 'bonding_tests_archive_manual' => ['Lab Ref No.', 'Finished Time']] as $tbl => [$lc, $fc]) {
            foreach ($db->query("SELECT * FROM dbappsheet.{$tbl} ORDER BY Row_ID DESC")->getResultArray() as $r) {
                $res = $resOf($r['Test Status'] ?? '');
                $fin = $res !== '' ? $date($r[$fc] ?? '') : '';
                $add($extra, (string) ($r[$lc] ?? ''), ['Result' => $res, 'TestedDate' => $fin, 'FinishedDate' => $fin, 'Technician' => $tn($r['Technician'] ?? ''), 'MachineNumber' => $r['Machine Number'] ?? '', 'FailPart' => $r['Fail_Part'] ?? '', 'Cause' => $r['Cause'] ?? '', 'PossibleCause' => $r['Possible Cause'] ?? '']);
            }
        }
        CLI::write('shoes_master + archive Lab References: ' . count($extra));
        $blank = static fn ($v) => $v === null || trim((string) $v) === '' || trim((string) $v) === '-';
        $stat  = [];
        $done  = 0;
        $rows  = $db->query('SELECT * FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> ""')->getResultArray();
        foreach ($rows as $i) {
            $key = strtoupper(trim((string) $i['LabRefNo']));
            $s   = $src[$key] ?? null;
            $x   = $extra[$key] ?? [];
            if (! $s && ! $x) {
                continue;
            }
            $s = $s ?? [];
            $f  = [];
            $ts = strtoupper(trim((string) ($s['Test Status'] ?? '')));
            $fin = substr(trim((string) ($s['FINISH_Bonding_Timestamp'] ?? '')), 0, 10);
            $res = $ts === 'PASSED' ? 'PASS' : ($ts === 'FAILED' ? 'FAIL' : ($ts === 'TOLERABLE' ? 'TOLERABLE' : ''));
            $techName = $tech[trim((string) ($s['Technician'] ?? ''))] ?? '';
            $cand = [
                'ModelStyle'      => trim((string) ($s['Style'] ?? '')),
                'Color'           => trim((string) ($s['Colorway'] ?? '')),
                'Technician'      => $techName,
                'Result'          => $res,
                'TestedDate'      => $res !== '' ? $fin : '',
                'FinishedDate'    => $res !== '' ? $fin : '',
                'MachineNumber'   => trim((string) ($s['Machine Number'] ?? '')),
                'FailPart'        => trim((string) ($s['Fail_Part'] ?? '')),
                'Cause'           => trim((string) ($s['Cause'] ?? '')),
                'PossibleCause'   => trim((string) ($s['Possible Cause'] ?? '')),
                'StartTimestamp'  => trim((string) ($s['START_Bonding_Timestamp'] ?? '')),
                'FinishTimestamp' => trim((string) ($s['FINISH_Bonding_Timestamp'] ?? '')),
                'TestDuration'    => trim((string) ($s['BondingTest_Duration'] ?? '')),
                'SavedDuration'   => trim((string) ($s['Saved_Duration'] ?? '')),
                'TotalTime'       => trim((string) ($s['TotalTime'] ?? '')),
                'RetestOf'        => trim((string) ($s['RetestOf'] ?? '')),
                'RetestStatus'    => trim((string) ($s['RetestStatus'] ?? '')),
                'ShoeID'          => trim((string) ($s['ShoeID'] ?? '')),
                'AppSheetTestID'  => trim((string) ($s['BondingTestID'] ?? '')),
            ];
            foreach ($x as $col => $val) {
                if (trim((string) ($cand[$col] ?? '')) === '') {
                    $cand[$col] = $val;
                }
            }
            foreach ($cand as $col => $val) {
                // a production date that is not a real date (2026-90-40) counts as empty
                if ($col === 'ProductionDate' && $val !== '' && ! $blank($i[$col] ?? null) && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', substr((string) $i[$col], 0, 10))) {
                    $f[$col] = $val; $stat[$col] = ($stat[$col] ?? 0) + 1;
                    continue;
                }
                if ($val === '' || $val === '-' || ! $has($col) || ! $blank($i[$col] ?? null)) {
                    continue;
                }
                $f[$col] = $val;
                $stat[$col] = ($stat[$col] ?? 0) + 1;
            }
            if ($f === []) {
                continue;
            }
            $done++;
            if ($apply) {
                $db->table('tbl_dtr_bonding_info')->where('BondingTestID', $i['BondingTestID'])->update($f);
                \App\Libraries\DtrLabrefIndex::upsert($db, 'bonding', (int) $i['BondingTestID'], array_merge($i, $f));
            }
        }
        CLI::write(($apply ? 'FILLED' : 'WOULD FILL') . ' rows: ' . $done);
        ksort($stat);
        foreach ($stat as $c => $n) {
            CLI::write(sprintf('  %-16s %d', $c, $n));
        }
        if ($apply && $done) {
            \App\Libraries\DataCache::bump();
        }
    }
}
