<?php

namespace App\Services\Analytics;

/** Shoe Bonding (U60): monthly, daily, failure, retest, result and UTM builders. */
class BondingAnalytics extends AnalyticsBase
{
    /** Monthly Summary (by production date): daily tested / failed trend, status totals and failed count per line and day. */
    public function bondingMonthly($db): array
    {
        $pd   = self::BOND_DATE;
        // the Monthly Summary counts only the DAILY bonding tests (no Tech Trial, First Article / First Case, Retest, Lacking or Sample)
        $base = $this->bondingBaseWhere($pd) . " AND (TestType IS NULL OR TRIM(TestType) = '' OR UPPER(TestType) = 'DAILY')";
        $wDay = $base . ' AND ' . $this->dateWhere($pd);
        // tested = one per line and day: the L / R specimens of a line (and a line retested the same day) count once, like the failed count per line
        $days = $db->query("SELECT {$pd} AS d, COUNT(DISTINCT SUBSTRING_INDEX(LabRefNo, '-', 1)) AS tested, SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AS failed FROM tbl_dtr_bonding_info WHERE {$wDay} GROUP BY d ORDER BY d")->getResultArray();
        $st   = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(UPPER(Result) LIKE 'PASS%'), 0) AS pass, COALESCE(SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED'), 0) AS fail, COALESCE(SUM(UPPER(Result) LIKE 'TOL%'), 0) AS tol FROM tbl_dtr_bonding_info WHERE {$base}")->getRowArray();

        // failed specimens per building line and production date; the line comes from the Lab Ref No (DTP<date>SH<building><line>-<side>)
        $lines = ['A' => range(1, 12), 'B' => range(1, 14)];
        $cells = [];
        $noline = [];
        foreach ($db->query("SELECT LabRefNo, {$pd} AS d FROM tbl_dtr_bonding_info WHERE {$wDay} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED')")->getResultArray() as $r) {
            if (preg_match('/^DTP\d{8}[A-Z]{2}([A-Z])(\d+)/i', (string) $r['LabRefNo'], $m)) {
                $b = strtoupper($m[1]); $n = (int) $m[2];
                $cells[$b][$n][$r['d']] = ($cells[$b][$n][$r['d']] ?? 0) + 1;
                if (! in_array($n, $lines[$b] ?? [], true)) {
                    $lines[$b][] = $n;
                }
            } else {
                $noline[(string) $r['d']] = ($noline[(string) $r['d']] ?? 0) + 1;   // Tech Trial / Sample refs have no building line: still a failed specimen of that day
            }
        }
        foreach ($lines as &$ln) {
            sort($ln);
        }
        unset($ln);
        ksort($lines);

        // lines marked SHUTDOWN in the Specimen Intake (Active toggle off) for a production date
        $shut = [];
        $shutCnt = 0;
        if (($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) && $db->fieldExists('Shutdown', 'tbl_dtr_BondingTracker')) {
            $sw = 'Shutdown = 1' . (($this->from !== '' || $this->to !== '') ? ' AND ' . $this->dateWhere('ProductionDate') : '');
            foreach ($db->query("SELECT Building, `LineNo` AS ln, ProductionDate AS d FROM tbl_dtr_BondingTracker WHERE {$sw}")->getResultArray() as $r) {
                $shut[strtoupper((string) $r['Building'])][(int) preg_replace('/\D/', '', (string) $r['ln'])][substr((string) $r['d'], 0, 10)] = true;
                $shutCnt++;
            }
        }
        $swB = self::SHUTDOWN_ROW . (($this->from !== '' || $this->to !== '') ? ' AND ' . $this->dateWhere('ProductionDate') : '');
        $shutCnt += (int) $db->query("SELECT COUNT(*) AS c FROM tbl_dtr_bonding_info WHERE {$swB}")->getRow()->c;
        $st['shut'] = $shutCnt;

        // production dates BEFORE the Nexus data is complete (up to BACKUP_UNTIL) come from the weekly Excel record, dbexcelbackup.tbl_weekly_bonding_result
        // (one row per line and day: PASS / TOLERABLE / FAILED / SHUTDOWN, with the failure category): it replaces what Nexus holds for those dates
        // the audit record dbexcelbackup.tbl_MonthlyBondingSummary (JANUARY..NOVEMBER tabs) is read first; a date it does not hold falls back to the weekly record
        $bk = [];
        try {
            if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_monthlybondingsummary'")->getNumRows() > 0) {
                $bk = $db->query("SELECT production_date AS d, UPPER(TRIM(assembly_line)) AS ln, IF(is_shutdown = 1, 'SHUTDOWN', UPPER(TRIM(COALESCE(bonding_result, '')))) AS res
                    FROM dbexcelbackup.tbl_monthlybondingsummary
                    WHERE production_date < ? AND " . $this->dateWhere('production_date') . " AND UPPER(TRIM(assembly_line)) REGEXP '^[A-Z][0-9]+$'", [self::BACKUP_UNTIL])->getResultArray();
            }
            $have = array_flip(array_column($bk, 'd'));
            foreach ($db->query('SELECT production_date AS d, line_no AS ln, UPPER(TRIM(result)) AS res FROM dbexcelbackup.tbl_weekly_bonding_result WHERE production_date < ? AND ' . $this->dateWhere('production_date') . " AND line_no REGEXP '^[A-Za-z][0-9]+$'", [self::BACKUP_UNTIL])->getResultArray() as $wr) {
                if (! isset($have[$wr['d']])) {
                    $bk[] = $wr;
                }
            }
        } catch (\Throwable $e) {
            // the backup database is not on this server: the Nexus records stay as they are
        }
        $bkDates = [];
        if ($bk) {
            $bkDates = array_values(array_unique(array_column($bk, 'd')));
            $eDates  = implode(',', array_map([$db, 'escape'], $bkDates));
            // what the Nexus records of those dates contributed to the status totals / shutdown count is taken out first
            $nx = $db->query("SELECT COALESCE(SUM(UPPER(Result) LIKE 'PASS%'), 0) AS pass, COALESCE(SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED'), 0) AS fail, COALESCE(SUM(UPPER(Result) LIKE 'TOL%'), 0) AS tol FROM tbl_dtr_bonding_info WHERE {$base} AND {$pd} IN ({$eDates})")->getRowArray() ?: [];
            $st['total'] = (int) $st['total'] - (int) ($nx['pass'] ?? 0) - (int) ($nx['fail'] ?? 0) - (int) ($nx['tol'] ?? 0);
            foreach (['pass', 'fail', 'tol'] as $k) {
                $st[$k] = (int) $st[$k] - (int) ($nx[$k] ?? 0);
            }
            $days = array_values(array_filter($days, static fn ($r) => ! in_array($r['d'], $bkDates, true)));
            $noline = array_diff_key($noline, array_flip($bkDates));
            foreach ($cells as $b => $byLine) {
                foreach ($byLine as $n => $byDay) {
                    foreach ($bkDates as $d) {
                        unset($cells[$b][$n][$d]);
                    }
                }
            }
            foreach ($shut as $b => $byLine) {
                foreach ($byLine as $n => $byDay) {
                    foreach ($bkDates as $d) {
                        if (isset($shut[$b][$n][$d])) { unset($shut[$b][$n][$d]); $shutCnt--; }
                    }
                }
            }
            $per = [];
            $seenLn = [];
            foreach ($bk as $r) {
                $b = strtoupper($r['ln'][0]); $n = (int) substr($r['ln'], 1); $d = (string) $r['d']; $rs = (string) $r['res'];
                if ($rs === 'SHUTDOWN') {
                    $shut[$b][$n][$d] = true;
                    $shutCnt++;
                    continue;
                }
                if (! preg_match('/^(PASS|TOL|FAIL)/', $rs)) {
                    continue;   // no result written for that line / day
                }
                if (! isset($seenLn[$d][$b . $n])) {   // a line written twice in the Excel for one day is one tested line
                    $seenLn[$d][$b . $n] = true;
                    $per[$d]['tested'] = ($per[$d]['tested'] ?? 0) + 1;
                }
                if (str_starts_with($rs, 'FAIL')) {
                    $per[$d]['failed'] = ($per[$d]['failed'] ?? 0) + 1;
                    $cells[$b][$n][$d] = 1;
                    $st['fail']++;
                    if (! in_array($n, $lines[$b] ?? [], true)) { $lines[$b][] = $n; }
                } else {
                    $st[str_starts_with($rs, 'PASS') ? 'pass' : 'tol']++;
                }
                $st['total']++;
            }
            foreach ($per as $d => $v) {
                $days[] = ['d' => $d, 'tested' => $v['tested'], 'failed' => $v['failed'] ?? 0];
            }
            usort($days, static fn ($a, $b) => strcmp((string) $a['d'], (string) $b['d']));
            foreach ($lines as &$ln) { sort($ln); }
            unset($ln);
            ksort($lines);
            $st['shut'] = $shutCnt;
        }

        // tested lines per building (one per line and day, same rule as the daily tested count): Excel record for the dates before BACKUP_UNTIL, Nexus for the rest
        $bldgTested = ['A' => 0, 'B' => 0];
        try {
            $bkSeen = [];
            foreach ($bk as $r) {
                $rs2 = (string) $r['res'];
                $ln2 = strtoupper((string) $r['ln']);
                if (! preg_match('/^(PASS|TOL|FAIL)/', $rs2) || $ln2 === '' || isset($bkSeen[$r['d'] . '|' . $ln2])) {
                    continue;
                }
                $bkSeen[$r['d'] . '|' . $ln2] = true;
                $bldgTested[$ln2[0]] = ($bldgTested[$ln2[0]] ?? 0) + 1;
            }
            $notBk = $bkDates ? ' AND ' . $pd . ' NOT IN (' . implode(',', array_map([$db, 'escape'], $bkDates)) . ')' : '';
            foreach ($db->query("SELECT UPPER(SUBSTRING(LabRefNo, 14, 1)) AS b, COUNT(DISTINCT CONCAT({$pd}, '|', SUBSTRING_INDEX(LabRefNo, '-', 1))) AS t FROM tbl_dtr_bonding_info WHERE {$wDay}{$notBk} AND LabRefNo REGEXP '^DTP[0-9]{8}SH[AB][0-9]+' GROUP BY b")->getResultArray() as $r) {
                $bldgTested[$r['b']] = ($bldgTested[$r['b']] ?? 0) + (int) $r['t'];
            }
        } catch (\Throwable $e) {
            $bldgTested = ['A' => 0, 'B' => 0];
        }

        // hand marks of the Edit mode: '1' = one failed specimen on that line / date, 'S' = the line was shut down
        $marks = [];
        $this->ensureMarksTable($db);
        foreach ($db->query('SELECT Building, `LineNo` AS ln, ProdDate AS d, Mark FROM tbl_an_bonding_marks')->getResultArray() as $r) {
            $b = strtoupper((string) $r['Building']); $n = (int) $r['ln']; $d = (string) $r['d'];
            if (in_array($d, $bkDates, true)) {
                // the Excel record is the source of these dates; the only hand mark it takes is a failed '1' for a line the Excel has as PASS / TOLERABLE
                // (the failure is on the Failed Bldg sheet): that specimen moves from pass / tolerable to failed and the day's failed count follows
                if ($r['Mark'] === '1' && ! isset($cells[$b][$n][$d])) {
                    foreach ($bk as $e) {
                        if (strtoupper((string) $e['ln']) === $b . $n && (string) $e['d'] === $d && preg_match('/^(PASS|TOL)/', (string) $e['res'])) {
                            $st[str_starts_with((string) $e['res'], 'PASS') ? 'pass' : 'tol']--;
                            $st['fail']++;
                            foreach ($days as &$dy) { if ($dy['d'] === $d) { $dy['failed'] = (int) $dy['failed'] + 1; } }
                            unset($dy);
                            $cells[$b][$n][$d] = 1;
                            $marks[$b][$n][$d] = '1';
                            break;
                        }
                    }
                }
                continue;
            }
            $marks[$b][$n][$d] = $r['Mark'];
            if ($r['Mark'] === '1') {
                $cells[$b][$n][$d] = 1;
            } elseif ($r['Mark'] === '0') {
                unset($cells[$b][$n][$d]);
            } elseif ($r['Mark'] === 'N') {
                unset($shut[$b][$n][$d]);
            } elseif ($r['Mark'] === 'S') {
                $shut[$b][$n][$d] = true;
                unset($cells[$b][$n][$d]);
            }
        }

        // ONE tested line per production day (the worst result of its specimens, a hand-marked failure included): the tested / passed / tolerable / failed totals, the daily counts
        // and the building counts are all taken from it, so every tab that uses the Monthly Summary (Daily Bonding, Retest, ...) shows the same numbers
        try {
            $rankK = ['FAIL' => 3, 'TOL' => 2, 'PASS' => 1];
            $kindOf = static function (string $r): string {
                $r = strtoupper(trim($r));

                return (str_starts_with($r, 'FAIL') || $r === 'REJECTED') ? 'FAIL' : (str_starts_with($r, 'PASS') ? 'PASS' : (str_starts_with($r, 'TOL') ? 'TOL' : ''));
            };
            $canon = [];   // [date][line] => PASS|TOL|FAIL
            $put = static function (array &$canon, string $d, string $ln, string $k) use ($rankK): void {
                if ($k !== '' && ($rankK[$k] ?? 0) > ($rankK[$canon[$d][$ln] ?? ''] ?? 0)) {
                    $canon[$d][$ln] = $k;
                }
            };
            foreach ($bk as $r) {
                $d2 = (string) $r['d'];
                $ln2 = strtoupper((string) $r['ln']);
                if ($ln2 === '' || $ln2 === 'SHUTDOWN' || (string) $r['res'] === 'SHUTDOWN') {
                    continue;
                }
                $k2 = $kindOf((string) $r['res']);
                if ($k2 !== '' && $k2 !== 'FAIL' && ! empty($cells[$ln2[0]][(int) substr($ln2, 1)][$d2])) {
                    $k2 = 'FAIL';   // marked failed by hand (the failure is on the Failed Bldg sheet)
                }
                $put($canon, $d2, $ln2, $k2);
            }
            $notBk2 = $bkDates ? ' AND ' . $pd . ' NOT IN (' . implode(',', array_map([$db, 'escape'], $bkDates)) . ')' : '';
            foreach ($db->query("SELECT {$pd} AS d, UPPER(SUBSTRING_INDEX(LabRefNo, '-', 1)) AS ln, Result AS res FROM tbl_dtr_bonding_info WHERE {$wDay}{$notBk2} AND LabRefNo IS NOT NULL AND LabRefNo <> ''")->getResultArray() as $r) {
                $put($canon, (string) $r['d'], (string) $r['ln'], $kindOf((string) $r['res']));
            }
            if ($canon) {
                ksort($canon);
                $days = [];
                $st['pass'] = $st['tol'] = $st['fail'] = 0;
                $bldgTested = ['A' => 0, 'B' => 0];
                foreach ($canon as $d2 => $byLn) {
                    $cnt = array_count_values($byLn);
                    $days[] = ['d' => $d2, 'tested' => count($byLn), 'failed' => $cnt['FAIL'] ?? 0, 'passed' => $cnt['PASS'] ?? 0, 'tol' => $cnt['TOL'] ?? 0];
                    $st['pass'] += $cnt['PASS'] ?? 0;
                    $st['tol']  += $cnt['TOL'] ?? 0;
                    $st['fail'] += $cnt['FAIL'] ?? 0;
                    foreach ($byLn as $ln2 => $_k) {
                        $bb = preg_match('/^[AB]\d+$/', (string) $ln2) ? $ln2[0] : (preg_match('/^DTP\d{8}[A-Z]{2}([AB])\d+/', (string) $ln2, $bm) ? $bm[1] : '');
                        if ($bb !== '') { $bldgTested[$bb]++; }
                    }
                }
                $st['total'] = $st['pass'] + $st['tol'] + $st['fail'];
            }
        } catch (\Throwable $e) {
            // the previous counts stay
        }

        // typed totals of the bottom rows: B = total failed of a building, F = total failed per production date, T = total tested per production date
        $ovr = ['B' => [], 'F' => [], 'T' => []];
        $this->ensureTotalsTable($db);
        foreach ($db->query('SELECT Kind, Building, ProdDate, Val FROM tbl_an_bonding_totals')->getResultArray() as $r) {
            if (in_array((string) $r['ProdDate'], $bkDates, true)) { continue; }   // typed totals do not replace the weekly Excel record
            if ($r['Kind'] === 'B') { $ovr['B'][strtoupper((string) $r['Building'])][(string) $r['ProdDate']] = (int) $r['Val']; }
            elseif (isset($ovr[$r['Kind']])) { $ovr[$r['Kind']][(string) $r['ProdDate']] = (int) $r['Val']; }
        }

        $yrSrc = $this->from !== '' ? $this->from : ($this->to !== '' ? $this->to : date('Y-m-d'));
        $year  = (int) substr($yrSrc, 0, 4);
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        return [
            'noline' => $noline, 'days' => $days, 'shut' => $shut, 'marks' => $marks, 'ovr' => $ovr,
            'status' => $st, 'lines' => $lines, 'cells' => $cells, 'bldgTested' => $bldgTested,
            'yearlyPies' => $this->bondingYearlySummary($db, $year), 'yearlyYear' => $year,
            'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => [],
        ];
    }

    /**
     * Whole-year monthly PASS / FAILED / TOLERABLE totals (Jan-Dec) for the Yearly Pie Summary view.
     * Uses dbexcelbackup.tbl_monthlybondingsummary for completed historical audit months and live
     * tbl_dtr_bonding_info + tbl_weekly_bonding_result for the active/live cutoff month onward.
     */
    public function bondingYearlySummary($db, int $year): array
    {
        $mNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[$m] = ['month' => $m, 'name' => $mNames[$m], 'pass' => 0, 'fail' => 0, 'tol' => 0, 'total' => 0];
        }

        $yStart     = sprintf('%04d-01-01', $year);
        $yEnd       = sprintf('%04d-12-31', $year);
        $auditUntil = min(sprintf('%04d-12-31', $year), date('Y-m-d', strtotime(self::BACKUP_UNTIL . ' -1 day'))); // up to the day before BACKUP_UNTIL (Nov 13) from dbexcelbackup.tbl_monthlybondingsummary; Nov 14 onward from dbnexus
        if ($year < (int) substr(self::BACKUP_UNTIL, 0, 4)) {
            $auditUntil = $yEnd;
        } elseif ($year > (int) substr(self::BACKUP_UNTIL, 0, 4)) {
            $auditUntil = '0000-00-00';
        }
        $hasAudit   = false;

        // 1) January to October from dbexcelbackup.tbl_monthlybondingsummary
        try {
            if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_monthlybondingsummary'")->getNumRows() > 0) {
                $rows = $db->query(
                    "SELECT month_no AS m,
                            COALESCE(SUM(UPPER(TRIM(bonding_result)) LIKE 'PASS%'), 0) AS pass,
                            COALESCE(SUM(UPPER(TRIM(bonding_result)) LIKE 'FAIL%' OR UPPER(TRIM(bonding_result)) = 'REJECTED'), 0) AS fail,
                            COALESCE(SUM(UPPER(TRIM(bonding_result)) LIKE 'TOL%'), 0) AS tol
                     FROM dbexcelbackup.tbl_monthlybondingsummary
                     WHERE production_date BETWEEN ? AND ? AND is_shutdown = 0
                     GROUP BY month_no",
                    [$yStart, $auditUntil]
                )->getResultArray();
                foreach ($rows as $r) {
                    $m = (int) $r['m'];
                    if ($m < 1 || $m > 12) {
                        continue;
                    }
                    $p = (int) $r['pass'];
                    $f = (int) $r['fail'];
                    $t = (int) $r['tol'];
                    if ($p + $f + $t > 0) {
                        $out[$m]['pass']  += $p;
                        $out[$m]['fail']  += $f;
                        $out[$m]['tol']   += $t;
                        $out[$m]['total'] += $p + $f + $t;
                        $hasAudit          = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // backup database optional
        }

        // 2) Live dbnexus records from November 1 onward (or full year if audit table unavailable)
        $pd   = self::BOND_DATE;
        $base = $this->bondingBaseWhere($pd) . " AND (TestType IS NULL OR TRIM(TestType) = '' OR UPPER(TestType) = 'DAILY')";
        $liveFrom = $hasAudit ? date('Y-m-d', strtotime($auditUntil . ' +1 day')) : $yStart;

        foreach ($db->query(
            "SELECT CAST(SUBSTR({$pd}, 6, 2) AS UNSIGNED) AS m,
                    COALESCE(SUM(UPPER(Result) LIKE 'PASS%'), 0) AS pass,
                    COALESCE(SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED'), 0) AS fail,
                    COALESCE(SUM(UPPER(Result) LIKE 'TOL%'), 0) AS tol
             FROM tbl_dtr_bonding_info
             WHERE {$base} AND {$pd} BETWEEN ? AND ?
             GROUP BY m",
            [$liveFrom, $yEnd]
        )->getResultArray() as $r) {
            $m = (int) $r['m'];
            if ($m < 1 || $m > 12) {
                continue;
            }
            $p = (int) $r['pass'];
            $f = (int) $r['fail'];
            $t = (int) $r['tol'];
            $out[$m]['pass']  += $p;
            $out[$m]['fail']  += $f;
            $out[$m]['tol']   += $t;
            $out[$m]['total'] += $p + $f + $t;
        }

        return array_values($out);
    }

    /**
     * Universal Tensile Machines (DTP-QC-xxx) Daily Productivity: specimens finished, failed shoes,
     * machine start/finish time duration, fleet utilization, cycle time, and downtime.
     */
    public function bondingUtm($db): array
    {
        if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_UCANstats'")->getNumRows() < 1 || $db->query("SHOW TABLES LIKE 'tbl_dtr_BondingTracker'")->getNumRows() < 1) {
            return $this->bondingUtmLegacy($db);
        }

        $shiftSec = 8 * 3600; // 8h scheduled shift per machine per active lab day
        $parseSec = static function (?string $t): ?int {
            $t = trim((string) $t);
            if ($t === '' || $t === '00:00:00') {
                return null;
            }
            if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $t, $m)) {
                $sec = ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + ((int) ($m[3] ?? 0));
                return $sec > 0 ? $sec : null;
            }
            return null;
        };
        $fmtTime = static function (?int $sec): string {
            if ($sec === null || $sec < 0) {
                return '';
            }
            return sprintf('%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60));
        };

        $xlUntil = (string) ($db->query('SELECT MAX(tested_date) AS m FROM dbexcelbackup.tbl_UCANstats WHERE tested_date <= CURDATE()')->getRowArray()['m'] ?? '');
        $mach      = [];
        $cell      = [];
        $dayT      = [];
        $machDay   = [];
        $dayDetail = [];

        $initMach = static function (string $m) use (&$mach): void {
            $mach[$m] ??= [
                'bond' => 0, 'pull' => 0, 'tests' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0,
                'days' => [], 'peak' => 0, 'peakDay' => '',
                'activeSec' => 0, 'spanSec' => 0, 'specSec' => 0, 'specCnt' => 0,
                'downDays' => 0, 'downSec' => 0,
            ];
        };
        $initDay = static function (string $d) use (&$dayDetail): void {
            $dayDetail[$d] ??= [
                'finished' => 0, 'target' => 0, 'pass' => 0, 'tol' => 0, 'failed' => 0,
                'activeMachines' => 0, 'downMachines' => 0, 'activeSec' => 0,
            ];
        };
        $add = static function (string $m, string $d, int $n, string $kind) use (&$mach, &$cell, &$dayT, &$dayDetail, $initMach, $initDay): void {
            if ($n <= 0) {
                return;
            }
            $initMach($m);
            $initDay($d);
            $mach[$m][$kind] += $n;
            if ($kind === 'pull') {
                return; // eyelet / rivet / D-ring pull specimens are shown apart: totals count shoes
            }
            $mach[$m]['tests'] += $n;
            $mach[$m]['days'][$d] = true;
            $cell[$m][$d] = ($cell[$m][$d] ?? 0) + $n;
            $dayT[$d]     = ($dayT[$d] ?? 0) + $n;
            $dayDetail[$d]['finished'] += $n;
            if ($cell[$m][$d] > $mach[$m]['peak']) {
                $mach[$m]['peak']    = $cell[$m][$d];
                $mach[$m]['peakDay'] = $d;
            }
        };

        // 1) Historical Excel monitoring (up through $xlUntil): start_time, finish_time, machine_used, day_total_xl, note
        $xw = $this->dateWhere('tested_date') . " AND tested_date <= '{$xlUntil}'";
        $xlRows = $db->query("SELECT UPPER(TRIM(machine)) AS m, tested_date AS d, start_time, finish_time,
                COALESCE(total_specimens, 0) AS n, COALESCE(machine_used, 0) AS used,
                COALESCE(day_total_xl, 0) AS day_target, COALESCE(note, '') AS note
            FROM dbexcelbackup.tbl_UCANstats WHERE {$xw} ORDER BY tested_date ASC, machine ASC")->getResultArray();

        $xlDayActive = [];
        foreach ($xlRows as $r) {
            if ((int) $r['used'] === 1 || (int) $r['n'] > 0) {
                $xlDayActive[(string) $r['d']] = true;
            }
        }

        $downtimeEvents = 0;
        $downtimeSec    = 0;
        $downtimeNotes  = [];
        foreach ($xlRows as $r) {
            $m = (string) $r['m'];
            $d = (string) $r['d'];
            if ($m === '') {
                continue;
            }
            $initMach($m);
            $initDay($d);
            if ((int) $r['day_target'] > $dayDetail[$d]['target']) {
                $dayDetail[$d]['target'] = (int) $r['day_target'];
            }
            $used = (int) $r['used'] === 1;
            $n    = (int) $r['n'];
            if ($used && $n > 0) {
                $add($m, $d, $n, 'bond');
                $sSec = $parseSec((string) $r['start_time']);
                $fSec = $parseSec((string) $r['finish_time']);
                $span = ($sSec !== null && $fSec !== null && $fSec > $sSec && ($fSec - $sSec) <= 16 * 3600) ? ($fSec - $sSec) : 0;
                $mach[$m]['activeSec'] += $span;
                $mach[$m]['spanSec']   += $span;
                $dayDetail[$d]['activeSec'] += $span;
                $dayDetail[$d]['activeMachines']++;
                $machDay[$m][$d] = [
                    'used' => true, 'start' => $fmtTime($sSec), 'finish' => $fmtTime($fSec),
                    'startSec' => $sSec ?? 0, 'finishSec' => $fSec ?? 0,
                    'spanSec' => $span, 'activeSec' => $span, 'finished' => $n,
                    'pass' => 0, 'tol' => 0, 'fail' => 0, 'note' => trim((string) $r['note']),
                ];
            } elseif (! $used && isset($xlDayActive[$d])) {
                $note = trim((string) $r['note']);
                $mach[$m]['downDays']++;
                $mach[$m]['downSec'] += $shiftSec;
                $dayDetail[$d]['downMachines']++;
                $downtimeEvents++;
                $downtimeSec += $shiftSec;
                if ($note !== '') {
                    $downtimeNotes[$note] = ($downtimeNotes[$note] ?? 0) + 1;
                }
                $machDay[$m][$d] = [
                    'used' => false, 'start' => '', 'finish' => '',
                    'startSec' => 0, 'finishSec' => 0,
                    'spanSec' => 0, 'activeSec' => 0, 'finished' => 0,
                    'pass' => 0, 'tol' => 0, 'fail' => 0, 'note' => $note,
                ];
            }
        }

        // 2) Live Lab Tracker (> $xlUntil): FinishTimestamp, StartTimestamp, PullTest, with bonding_info.MachineNumber fallback via LabRefNo
        $fd   = 'DATE(tr.FinishTimestamp)';
        $lw   = $this->dateWhere($fd) . " AND {$fd} > '{$xlUntil}' AND tr.FinishTimestamp IS NOT NULL";
        $mcTr = "UPPER(TRIM(COALESCE(NULLIF(TRIM(tr.MachineNumber), '') COLLATE utf8mb4_general_ci, bi.bi_m, '')))";
        $biMcJoin = "LEFT JOIN (
                SELECT LabRefNo, MAX(NULLIF(TRIM(MachineNumber), '')) COLLATE utf8mb4_general_ci AS bi_m
                FROM tbl_dtr_bonding_info
                WHERE NULLIF(TRIM(MachineNumber), '') IS NOT NULL
                GROUP BY LabRefNo
            ) bi ON bi.LabRefNo COLLATE utf8mb4_general_ci = tr.LabRefNo COLLATE utf8mb4_general_ci";
        $liveRows = $db->query("SELECT {$mcTr} AS m, {$fd} AS d,
                COUNT(*) AS n,
                SUM(COALESCE(tr.PullTest, 0) = 1) AS pl,
                MIN(TIME(CASE WHEN tr.StartTimestamp IS NOT NULL AND DATE(tr.StartTimestamp) = {$fd} THEN tr.StartTimestamp ELSE tr.FinishTimestamp END)) AS first_start,
                MAX(TIME(tr.FinishTimestamp)) AS last_finish,
                SUM(CASE WHEN tr.StartTimestamp IS NOT NULL AND DATE(tr.StartTimestamp) = {$fd}
                              AND TIMESTAMPDIFF(SECOND, tr.StartTimestamp, tr.FinishTimestamp) BETWEEN 1 AND 21600
                         THEN TIMESTAMPDIFF(SECOND, tr.StartTimestamp, tr.FinishTimestamp) ELSE 0 END) AS spec_sec,
                SUM(CASE WHEN tr.StartTimestamp IS NOT NULL AND DATE(tr.StartTimestamp) = {$fd}
                              AND TIMESTAMPDIFF(SECOND, tr.StartTimestamp, tr.FinishTimestamp) BETWEEN 1 AND 21600
                         THEN 1 ELSE 0 END) AS spec_cnt
            FROM tbl_dtr_BondingTracker tr
            {$biMcJoin}
            WHERE {$lw} AND {$mcTr} LIKE 'DTP-QC-0%'
            GROUP BY {$mcTr}, {$fd}")->getResultArray();

        // Collect real per-specimen StartTimestamp -> FinishTimestamp intervals on live days and merge contiguous/overlapping runs
        $rawIntervals = $db->query("SELECT {$mcTr} AS m, {$fd} AS d,
                TIME(tr.StartTimestamp) AS st, TIME(tr.FinishTimestamp) AS ft
            FROM tbl_dtr_BondingTracker tr
            {$biMcJoin}
            WHERE {$lw} AND {$mcTr} LIKE 'DTP-QC-0%'
              AND tr.StartTimestamp IS NOT NULL
              AND DATE(tr.StartTimestamp) = {$fd}
              AND TIMESTAMPDIFF(SECOND, tr.StartTimestamp, tr.FinishTimestamp) BETWEEN 1 AND 21600
            ORDER BY m ASC, d ASC, st ASC, ft ASC")->getResultArray();

        $segMap = [];
        foreach ($rawIntervals as $iv) {
            $mKey = (string) $iv['m'];
            $dKey = (string) $iv['d'];
            $sVal = $parseSec((string) $iv['st']);
            $eVal = $parseSec((string) $iv['ft']);
            if ($sVal === null || $eVal === null || $eVal <= $sVal) {
                continue;
            }
            $segMap[$mKey][$dKey][] = [$sVal, $eVal];
        }
        $mergedMap = [];
        foreach ($segMap as $mKey => $byDate) {
            foreach ($byDate as $dKey => $pairs) {
                usort($pairs, static fn ($a, $b) => $a[0] <=> $b[0]);
                $merged = [];
                foreach ($pairs as [$sVal, $eVal]) {
                    $lastIdx = count($merged) - 1;
                    if ($lastIdx >= 0 && $sVal <= $merged[$lastIdx]['e'] + 60) {
                        if ($eVal > $merged[$lastIdx]['e']) {
                            $merged[$lastIdx]['e']  = $eVal;
                            $merged[$lastIdx]['ft'] = $fmtTime($eVal);
                            $merged[$lastIdx]['dur'] = $eVal - $merged[$lastIdx]['s'];
                        }
                    } else {
                        $merged[] = [
                            's'   => $sVal,
                            'e'   => $eVal,
                            'st'  => $fmtTime($sVal),
                            'ft'  => $fmtTime($eVal),
                            'dur' => $eVal - $sVal,
                        ];
                    }
                }
                $mergedMap[$mKey][$dKey] = $merged;
            }
        }

        $liveDays = [];
        foreach ($liveRows as $r) {
            $m    = (string) $r['m'];
            $d    = (string) $r['d'];
            $pl   = (int) $r['pl'];
            $bond = (int) $r['n'] - $pl;
            $add($m, $d, $bond, 'bond');
            $add($m, $d, $pl, 'pull');
            if ($bond <= 0) {
                continue;
            }
            $liveDays[$d] = true;
            $sSec = $parseSec((string) $r['first_start']);
            $fSec = $parseSec((string) $r['last_finish']);
            $span = ($sSec !== null && $fSec !== null && $fSec > $sSec && ($fSec - $sSec) <= 16 * 3600) ? ($fSec - $sSec) : 0;
            $specSec  = (int) $r['spec_sec'];
            $specCnt  = (int) $r['spec_cnt'];
            $segments = $mergedMap[$m][$d] ?? [];
            $workSec  = 0;
            foreach ($segments as $sg) {
                $workSec += $sg['dur'];
            }
            $active  = $workSec > 0 ? $workSec : ($span > 0 ? $span : $specSec);
            $idleSec = ($workSec > 0 && $span > $workSec) ? ($span - $workSec) : 0;
            $mach[$m]['activeSec'] += $active;
            $mach[$m]['spanSec']   += $span;
            $mach[$m]['specSec']   += $specSec;
            $mach[$m]['specCnt']   += $specCnt;
            $dayDetail[$d]['activeSec'] += $active;
            $dayDetail[$d]['activeMachines']++;
            $machDay[$m][$d] = [
                'used' => true, 'start' => $fmtTime($sSec), 'finish' => $fmtTime($fSec),
                'startSec' => $sSec ?? 0, 'finishSec' => $fSec ?? 0,
                'spanSec' => $span, 'activeSec' => $active, 'idleSec' => $idleSec,
                'segments' => $segments, 'hasIntervals' => $segments !== [],
                'finished' => $bond, 'specSec' => $specSec, 'specCnt' => $specCnt,
                'pass' => 0, 'tol' => 0, 'fail' => 0, 'note' => '',
            ];
        }

        // Daily live targets (total non-shutdown bonding specimens logged for that tested date)
        if ($liveDays !== []) {
            foreach ($db->query("SELECT {$fd} AS d, COUNT(*) AS tot FROM tbl_dtr_BondingTracker tr WHERE {$lw} AND COALESCE(tr.PullTest, 0) = 0 AND COALESCE(tr.Shutdown, 0) = 0 GROUP BY {$fd}")->getResultArray() as $r) {
                $d = (string) $r['d'];
                $initDay($d);
                if ((int) $r['tot'] > $dayDetail[$d]['target']) {
                    $dayDetail[$d]['target'] = (int) $r['tot'];
                }
            }
        }

        $unB = (int) $db->query("SELECT COUNT(*) AS c FROM tbl_dtr_BondingTracker tr {$biMcJoin} WHERE {$lw} AND {$mcTr} NOT LIKE 'DTP-QC-0%' AND COALESCE(tr.PullTest, 0) = 0")->getRow()->c;
        $unP = (int) $db->query("SELECT COUNT(*) AS c FROM tbl_dtr_BondingTracker tr {$biMcJoin} WHERE {$lw} AND {$mcTr} NOT LIKE 'DTP-QC-0%' AND COALESCE(tr.PullTest, 0) = 1")->getRow()->c;

        // 3) Outcomes (PASS / TOLERABLE / FAILED) joined 1:1 via LabRefNo so tracker-assigned machines get exact failure counts
        $passSql = "UPPER(COALESCE(Result, '')) LIKE 'PASS%'";
        $tolSql  = "UPPER(COALESCE(Result, '')) LIKE 'TOL%'";
        $failSql = "(UPPER(COALESCE(Result, '')) LIKE 'FAIL%' OR UPPER(COALESCE(Result, '')) = 'REJECTED')";
        $bDate   = 'LEFT(TestedDate, 10)';
        $bw      = $this->bondingBaseWhere($bDate) . ' AND ' . $this->dateWhere($bDate);
        $mcExpr  = "UPPER(TRIM(COALESCE(NULLIF(TRIM(bi.MachineNumber), '') COLLATE utf8mb4_general_ci, tr.tr_m, '')))";
        $biOutcomes = $db->query("SELECT {$mcExpr} AS m, bi.d AS d,
                SUM(bi.p) AS p, SUM(bi.t) AS t, SUM(bi.f) AS f
            FROM (
                SELECT LabRefNo, MachineNumber, {$bDate} AS d,
                       ({$passSql}) AS p, ({$tolSql}) AS t, ({$failSql}) AS f
                FROM tbl_dtr_bonding_info
                WHERE {$bw}
            ) bi
            LEFT JOIN (
                SELECT LabRefNo, MAX(NULLIF(TRIM(MachineNumber), '')) COLLATE utf8mb4_general_ci AS tr_m
                FROM tbl_dtr_BondingTracker
                WHERE NULLIF(TRIM(MachineNumber), '') IS NOT NULL
                GROUP BY LabRefNo
            ) tr ON tr.LabRefNo COLLATE utf8mb4_general_ci = bi.LabRefNo COLLATE utf8mb4_general_ci
            GROUP BY {$mcExpr}, bi.d")->getResultArray();

        $unrecordedFail = 0;
        $unrecordedPass = 0;
        $unrecordedTol  = 0;
        foreach ($biOutcomes as $r) {
            $m = (string) $r['m'];
            $d = (string) $r['d'];
            $p = (int) $r['p'];
            $t = (int) $r['t'];
            $f = (int) $r['f'];
            if (isset($dayDetail[$d])) {
                $dayDetail[$d]['pass']   += $p;
                $dayDetail[$d]['tol']    += $t;
                $dayDetail[$d]['failed'] += $f;
            }
            if ($m !== '' && str_starts_with($m, 'DTP-QC-0')) {
                $initMach($m);
                $mach[$m]['pass'] += $p;
                $mach[$m]['tol']  += $t;
                $mach[$m]['fail'] += $f;
                if (isset($machDay[$m][$d])) {
                    $machDay[$m][$d]['pass'] += $p;
                    $machDay[$m][$d]['tol']  += $t;
                    $machDay[$m][$d]['fail'] += $f;
                }
            } else {
                $unrecordedFail += $f;
                $unrecordedPass += $p;
                $unrecordedTol  += $t;
            }
        }

        // Also capture any explicitly logged PauseDuration on bonding_info
        $pauseRow = $db->query("SELECT COUNT(*) AS c, COALESCE(SUM(
                CASE
                    WHEN PauseDuration REGEXP '^[0-9]+:[0-9]{2}' THEN TIME_TO_SEC(TIME(PauseDuration))
                    WHEN PauseDuration REGEXP '^[0-9]+$' THEN CAST(PauseDuration AS UNSIGNED) * 60
                    ELSE 0
                END
            ), 0) AS sec FROM tbl_dtr_bonding_info bi WHERE {$bw} AND NULLIF(TRIM(PauseDuration), '') IS NOT NULL")->getRowArray();
        $pauseSec = (int) ($pauseRow['sec'] ?? 0);
        if ($pauseSec > 0) {
            $downtimeSec    += $pauseSec;
            $downtimeEvents += (int) ($pauseRow['c'] ?? 0);
        }

        // 4) Fleet utilization, cycle time, completion vs target, and downtime totals
        ksort($dayT);
        ksort($dayDetail);
        $totalActiveSec = 0;
        $totalSpecSec   = 0;
        $totalSpecCnt   = 0;
        $totalCompleted = 0;
        $totalTarget    = 0;
        $totalFailed    = 0;
        foreach ($mach as $m => $info) {
            $totalActiveSec += $info['activeSec'];
            $totalSpecSec   += $info['specSec'];
            $totalSpecCnt   += $info['specCnt'];
            $totalCompleted += $info['tests'];
            $totalFailed    += $info['fail'];
        }
        $nFleetMach   = count($mach);
        $nActiveDays  = count($dayT);
        $scheduledSec = $nFleetMach * $nActiveDays * $shiftSec;
        foreach ($dayT as $d => $cnt) {
            $t = (int) ($dayDetail[$d]['target'] ?? 0);
            if ($t <= 0) {
                $t = $cnt;
                $dayDetail[$d]['target'] = $t;
            }
            $totalTarget += $t;
        }

        $fleetUtilPct = $scheduledSec > 0 ? round(($totalActiveSec / $scheduledSec) * 100, 1) : 0.0;
        $targetPct    = $totalTarget > 0 ? round(($totalCompleted / $totalTarget) * 100, 1) : 0.0;
        // Prefer real specimen timestamps when available, otherwise active window / finished count
        $avgCycleSec  = $totalSpecCnt > 0
            ? (int) round($totalSpecSec / $totalSpecCnt)
            : ($totalCompleted > 0 && $totalActiveSec > 0 ? (int) round($totalActiveSec / $totalCompleted) : 0);

        arsort($downtimeNotes);
        uasort($mach, static fn ($a, $b) => $b['tests'] <=> $a['tests']);

        return [
            'utm' => [
                'machines'       => $mach,
                'cell'           => $cell,
                'dayTot'         => $dayT,
                'machDay'        => $machDay,
                'dayDetail'      => $dayDetail,
                'unassigned'     => $unB + $unP,
                'unBond'         => $unB,
                'unPull'         => $unP,
                'unrecordedFail' => $unrecordedFail,
                'unrecordedPass' => $unrecordedPass,
                'unrecordedTol'  => $unrecordedTol,
                'xlUntil'        => $xlUntil,
                'summary'        => [
                    'fleetUtilPct'   => $fleetUtilPct,
                    'activeSec'      => $totalActiveSec,
                    'scheduledSec'   => $scheduledSec,
                    'completed'      => $totalCompleted,
                    'target'         => $totalTarget,
                    'targetPct'      => $targetPct,
                    'avgCycleSec'    => $avgCycleSec,
                    'timedCount'     => $totalSpecCnt,
                    'downtimeSec'    => $downtimeSec,
                    'downtimeEvents' => $downtimeEvents,
                    'downtimeNotes'  => $downtimeNotes,
                    'pauseSec'       => $pauseSec,
                    'totalFailed'    => $totalFailed + $unrecordedFail,
                    'shiftHours'     => 8,
                ],
            ],
            'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => [],
        ];
    }

    /** Fallback when tbl_UCANstats is not there: bonding_info + pull_tests. */
    public function bondingUtmLegacy($db): array
    {
        $isUtm = static fn (string $col): string => "UPPER(TRIM({$col})) LIKE 'DTP-QC-0%'";
        $pass  = "UPPER(COALESCE(Result, '')) LIKE 'PASS%'";
        $tol   = "UPPER(COALESCE(Result, '')) LIKE 'TOL%'";
        $fail  = "(UPPER(COALESCE(Result, '')) LIKE 'FAIL%' OR UPPER(COALESCE(Result, '')) = 'REJECTED')";

        $bDate = 'LEFT(TestedDate, 10)';
        $bw    = $this->bondingBaseWhere($bDate) . ' AND ' . $this->dateWhere($bDate);
        $rows  = $db->query("SELECT UPPER(TRIM(MachineNumber)) AS m, {$bDate} AS d, COUNT(*) AS n, SUM({$pass}) AS p, SUM({$tol}) AS t, SUM({$fail}) AS f
            FROM tbl_dtr_bonding_info WHERE {$bw} AND " . $isUtm('MachineNumber') . ' GROUP BY m, d')->getResultArray();
        $unB   = (int) $db->query("SELECT COUNT(*) AS c FROM tbl_dtr_bonding_info WHERE {$bw} AND NOT (COALESCE(MachineNumber, '') <> '' AND " . $isUtm('MachineNumber') . ')')->getRow()->c;

        $pRows = [];
        $unP   = 0;
        if ($db->tableExists('tbl_dtr_pull_tests')) {
            $mc  = "COALESCE(NULLIF(TRIM(EquipmentCode), ''), NULLIF(TRIM(Machine), ''), NULLIF(TRIM(MachineName), ''))";
            $pw  = $this->dateWhere('TestedDate');
            $pRows = $db->query("SELECT UPPER({$mc}) AS m, LEFT(TestedDate, 10) AS d, COUNT(*) AS n, SUM({$pass}) AS p, SUM({$tol}) AS t, SUM({$fail}) AS f
                FROM tbl_dtr_pull_tests WHERE {$pw} AND {$mc} IS NOT NULL AND " . $isUtm($mc) . ' GROUP BY m, d')->getResultArray();
            $unP = (int) $db->query("SELECT COUNT(*) AS c FROM tbl_dtr_pull_tests WHERE {$pw} AND NOT ({$mc} IS NOT NULL AND " . $isUtm($mc) . ')')->getRow()->c;
        }

        $mach      = [];
        $cell      = [];
        $dayT      = [];
        $dayDetail = [];
        $add  = static function (array $r, string $kind) use (&$mach, &$cell, &$dayT, &$dayDetail): void {
            $m = (string) $r['m'];
            $d = (string) $r['d'];
            $n = (int) $r['n'];
            $mach[$m] ??= [
                'bond' => 0, 'pull' => 0, 'tests' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0,
                'days' => [], 'peak' => 0, 'peakDay' => '',
                'activeSec' => 0, 'spanSec' => 0, 'specSec' => 0, 'specCnt' => 0, 'downDays' => 0, 'downSec' => 0,
            ];
            $dayDetail[$d] ??= ['finished' => 0, 'target' => 0, 'pass' => 0, 'tol' => 0, 'failed' => 0, 'activeMachines' => 0, 'downMachines' => 0, 'activeSec' => 0];
            $mach[$m][$kind] += $n;
            $mach[$m]['tests'] += $n;
            $mach[$m]['pass'] += (int) $r['p'];
            $mach[$m]['tol']  += (int) $r['t'];
            $mach[$m]['fail'] += (int) $r['f'];
            $mach[$m]['days'][$d] = true;
            $cell[$m][$d] = ($cell[$m][$d] ?? 0) + $n;
            $dayT[$d]     = ($dayT[$d] ?? 0) + $n;
            $dayDetail[$d]['finished'] += $n;
            $dayDetail[$d]['target']   += $n;
            $dayDetail[$d]['pass']     += (int) $r['p'];
            $dayDetail[$d]['tol']      += (int) $r['t'];
            $dayDetail[$d]['failed']   += (int) $r['f'];
            if ($cell[$m][$d] > $mach[$m]['peak']) {
                $mach[$m]['peak']    = $cell[$m][$d];
                $mach[$m]['peakDay'] = $d;
            }
        };
        foreach ($rows as $r) { $add($r, 'bond'); }
        foreach ($pRows as $r) { $add($r, 'pull'); }
        uasort($mach, static fn ($a, $b) => $b['tests'] <=> $a['tests']);
        ksort($dayT);
        ksort($dayDetail);
        $totalCompleted = array_sum($dayT);
        $totalFailed    = array_sum(array_column($mach, 'fail'));

        return [
            'utm' => [
                'machines'       => $mach,
                'cell'           => $cell,
                'dayTot'         => $dayT,
                'machDay'        => [],
                'dayDetail'      => $dayDetail,
                'unassigned'     => $unB + $unP,
                'unBond'         => $unB,
                'unPull'         => $unP,
                'unrecordedFail' => 0,
                'xlUntil'        => '',
                'summary'        => [
                    'fleetUtilPct' => 0.0, 'activeSec' => 0, 'scheduledSec' => 0,
                    'completed' => $totalCompleted, 'target' => $totalCompleted, 'targetPct' => $totalCompleted > 0 ? 100.0 : 0.0,
                    'avgCycleSec' => 0, 'timedCount' => 0, 'downtimeSec' => 0, 'downtimeEvents' => 0, 'downtimeNotes' => [], 'pauseSec' => 0,
                    'totalFailed' => $totalFailed, 'shiftHours' => 8,
                ],
            ],
            'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => [],
        ];
    }

    /**
     * Retest tab data: for the day / week / month / year of the end date (a future end date falls back to today) the retest count, its % of all bonding tests,
     * the retests by result (pass / tolerable / fail / pending) and a per-day (per-month for the year) series for the bars. Independent of the selected range.
     */
    /** The latest production day, when it is recent and still being tested (fewer lines tested than lines producing): [date, tested, failed], or [] */
    public function pendingProductionDay(array $days, array $lines, array $shut): array
    {
        $rows = array_values(array_filter($days, static fn ($r) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['d']) === 1));
        usort($rows, static fn ($a, $b) => strcmp((string) $a['d'], (string) $b['d']));
        $last = $rows ? end($rows) : null;
        if (! $last || strtotime((string) $last['d']) < strtotime('-3 days', strtotime(date('Y-m-d')))) {
            return [];
        }
        $lnTot = count($lines['A'] ?? []) + count($lines['B'] ?? []);
        $shCnt = 0;
        foreach ($shut as $byLine) {
            foreach ($byLine as $byDay) {
                if (isset($byDay[$last['d']])) { $shCnt++; }
            }
        }
        if ($lnTot > 0 && (int) $last['tested'] < $lnTot - $shCnt) {
            return ['d' => (string) $last['d'], 'tested' => (int) $last['tested'], 'failed' => (int) ($last['failed'] ?? 0)];
        }
        return [];
    }

    public function bondingRetest($db): array
    {
        $pd   = self::BOND_DATE;
        $asOf = min($this->to !== '' ? $this->to : date('Y-m-d'), date('Y-m-d'));
        $rg   = [
            'daily'   => [date('Y-m-d', strtotime('-6 days', strtotime($asOf))), $asOf],   // the day is shown against the 6 days before it
            'weekly'  => [date('Y-m-d', strtotime('monday this week', strtotime($asOf))), date('Y-m-d', strtotime('sunday this week', strtotime($asOf)))],
            'monthly' => [date('Y-m-01', strtotime($asOf)), date('Y-m-t', strtotime($asOf))],
            'yearly'  => [date('Y-01-01', strtotime($asOf)), date('Y-12-31', strtotime($asOf))],
        ];
        [$sf, $st] = [$this->from, $this->to];
        $this->from = $this->to = '';
        $base0 = $this->bondingBaseWhere($pd);
        [$this->from, $this->to] = [$sf, $st];
        $minD = min(array_column($rg, 0));
        $maxD = max(array_column($rg, 1));
        $eMin = $db->escape($minD);
        $eMax = $db->escape($maxD);
        $cls  = static fn (string $r): string => str_starts_with($r, 'FAIL') || $r === 'REJECTED' ? 'fail' : (str_starts_with($r, 'PASS') ? 'pass' : (str_starts_with($r, 'TOL') ? 'tol' : 'pend'));
        $day  = [];   // [d] => retest (tracker), failed (all failed reports), pass/tol/fail/pend (results of the RETEST reports)
        $new  = static fn (): array => ['retest' => 0, 'failed' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0, 'pend' => 0];
        // retests = every RETEST row of the bonding tracker (a pull test is not a retest), counted on its collection date
        $trk = $db->tableExists('tbl_dtr_BondingTracker') ? 'tbl_dtr_BondingTracker' : 'tbl_dtr_bondingtracker';
        $td  = "COALESCE(NULLIF(LEFT(CollectionDate, 10), ''), NULLIF(LEFT(ProductionDate, 10), ''))";
        // production dates up to $xlUntil come from the Excel retest record (dbexcelbackup.tbl_Retest: one row per failed line, with its retest and result);
        // Nexus supplies only the dates after it, so a shoe is never counted twice
        $xlUntil = '';
        $xlRows  = [];
        try {
            if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_Retest'")->getNumRows() > 0) {
                $xlRows  = $db->query("SELECT production_date AS d, tested_date AS td, line_no, style, colorway, failed_ref, upper_to_foxing, foxing_to_outsole, retest_ref, retest_result, retest_result_raw
                    FROM dbexcelbackup.tbl_Retest WHERE production_date BETWEEN {$eMin} AND {$eMax} ORDER BY production_date DESC, line_no ASC")->getResultArray();
                $xlUntil = (string) ($db->query('SELECT MAX(production_date) AS m FROM dbexcelbackup.tbl_Retest WHERE production_date <= CURDATE()')->getRowArray()['m'] ?? '');
            }
        } catch (\Throwable $e) {
            $xlUntil = '';
            $xlRows  = [];
        }
        // same split as the Monthly Summary / Daily Bonding counts: Excel record BEFORE BACKUP_UNTIL, Nexus from BACKUP_UNTIL on
        if ($xlUntil !== '') {
            $xlUntil = min($xlUntil, date('Y-m-d', strtotime(self::BACKUP_UNTIL . ' -1 day')));
        }
        $eXl = $db->escape($xlUntil !== '' ? $xlUntil : '0000-00-00');
        // Daily-only filter for failed baseline & table (exclude Tech Trial, First Article, Sample, Lacking)
        $dailyOnly = "((TestType IS NULL OR TRIM(TestType) = '' OR UPPER(TRIM(TestType)) = 'DAILY') AND UPPER(COALESCE(LabRefNo,'')) NOT LIKE '%TRIAL%' AND UPPER(COALESCE(LabRefNo,'')) NOT LIKE '%SAMPLE%')";

        // Build retest lookup from both tracker and bonding_info to match each failed specimen to its retest(s)
        $retests = [];
        foreach ($db->query("SELECT LabRefNo, COALESCE(RetestOf,'') AS RetestOf, {$td} AS dt FROM {$trk} WHERE (UPPER(COALESCE(TestType,'')) = 'RETEST' OR UPPER(LabRefNo) REGEXP '-R[0O]?[0-9]+$') AND COALESCE(PullTest,0) = 0 AND COALESCE(Shutdown,0) = 0")->getResultArray() as $r) {
            $rk = strtoupper(trim((string) $r['LabRefNo']));
            if ($rk === '') {
                continue;
            }
            $retests[$rk] = ['ref' => trim((string) $r['LabRefNo']), 'of' => strtoupper(trim((string) $r['RetestOf'])), 'date' => (string) $r['dt'], 'result' => 'PENDING'];
        }
        foreach ($db->query("SELECT LabRefNo, COALESCE(RetestOf,'') AS RetestOf, UPPER(TRIM(COALESCE(Result,''))) AS res, COALESCE(NULLIF(LEFT(TestedDate,10),''), NULLIF(LEFT(ReceivedDate,10),''), LEFT({$pd},10)) AS dt
            FROM tbl_dtr_bonding_info WHERE {$base0} AND (UPPER(COALESCE(TestType,'')) = 'RETEST' OR COALESCE(RetestOf,'') <> '' OR UPPER(LabRefNo) REGEXP '-R[0O]?[0-9]+$')")->getResultArray() as $r) {
            $rk = strtoupper(trim((string) $r['LabRefNo']));
            if ($rk === '') {
                continue;
            }
            $res = (string) $r['res'];
            $rst = (str_starts_with($res, 'FAIL') || $res === 'REJECTED') ? 'FAILED' : (str_starts_with($res, 'PASS') ? 'PASS' : (str_starts_with($res, 'TOL') ? 'TOLERABLE' : 'PENDING'));
            if (! isset($retests[$rk])) {
                $retests[$rk] = ['ref' => trim((string) $r['LabRefNo']), 'of' => strtoupper(trim((string) $r['RetestOf'])), 'date' => (string) $r['dt'], 'result' => $rst];
            } else {
                if ($r['RetestOf'] !== '' && $retests[$rk]['of'] === '') {
                    $retests[$rk]['of'] = strtoupper(trim((string) $r['RetestOf']));
                }
                if ($rst !== 'PENDING') {
                    $retests[$rk]['result'] = $rst;
                }
                if ($retests[$rk]['date'] === '' && $r['dt'] !== '') {
                    $retests[$rk]['date'] = (string) $r['dt'];
                }
            }
        }

        $byOf       = [];
        $byBase     = [];
        $byDateLine = [];
        $lineKeyOf  = static function (string $ref): array {
            if (preg_match('/^(DTP\d{8})[A-Z]*?([AB]\d+)(?:-?([LR]))?(?:-R[0O]?(\d+))?$/i', $ref, $m)) {
                return ['dl' => strtoupper($m[1] . '|' . $m[2]), 'lv' => (int) ($m[4] ?? 0)];
            }
            return ['dl' => '', 'lv' => 0];
        };
        $pickR01 = static function (array $cands): ?array {
            if (! $cands) {
                return null;
            }
            $r01 = array_values(array_filter($cands, static fn ($c) => preg_match('/-R[0O]?0*1$/i', (string) ($c['ref'] ?? '')) === 1));
            foreach ($r01 as $c) {
                if (($c['result'] ?? 'PENDING') !== 'PENDING') {
                    return $c;
                }
            }
            if ($r01) {
                return $r01[0];
            }
            foreach ($cands as $c) {
                if (preg_match('/-R[0O]?\d+$/i', (string) ($c['ref'] ?? ''))) {
                    return $c;
                }
            }
            return end($cands) ?: null;
        };
        foreach ($retests as $rk => $rtInfo) {
            if ($rtInfo['of'] !== '') {
                $byOf[$rtInfo['of']][] = $rtInfo;
            }
            $stripped = (string) preg_replace('/-R[0O]?\d+$/i', '', $rk);
            if ($stripped !== $rk) {
                $byBase[$stripped][] = $rtInfo;
            }
            // Only index Daily (non-FA/Trial/Sample) retests into date+line lookup
            if (! preg_match('/^DTP\d{8}(FA|TRIAL|SAMPLE)/i', $rk)) {
                $lk = $lineKeyOf($rk);
                if ($lk['dl'] !== '') {
                    $byDateLine[$lk['dl']][] = $rtInfo + ['lv' => $lk['lv']];
                }
            }
        }

        // Failed DAILY bonding rows in [$minD..$maxD] with line, LabRefNo, why it failed, retest status, and days before retest
        $calcDays = static function (string $fromD, string $toD): ?int {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromD) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $toD)) {
                return null;
            }
            return max(0, (int) round((strtotime($toD) - strtotime($fromD)) / 86400));
        };
        $pName = ['U' => 'Upper to Foxing', 'F' => 'Foxing to Outsole', 'I' => 'Filler to Outsole'];
        $failedRows = [];
        $fQuery = $db->query("SELECT LabRefNo, ModelStyle, Color, TestType, Result, LEFT({$pd}, 10) AS d, LEFT(COALESCE(TestedDate,''), 10) AS td, Cause, FailPart, ModeOfFailure, PossibleCause, ProbableCause
            FROM tbl_dtr_bonding_info
            WHERE {$base0} AND {$dailyOnly} AND LEFT({$pd}, 10) BETWEEN {$eMin} AND {$eMax} AND LEFT({$pd}, 10) > {$eXl} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED')
            ORDER BY d DESC, LabRefNo ASC")->getResultArray();

        // the failed lines of the Excel record (up to $xlUntil): match original failed LabRefNo and passing retest LabRefNo from dbnexus
        $nxRef    = [];
        $nxAnyRef = [];
        $byEtrf   = [];
        if ($xlRows && $xlUntil !== '') {
            foreach ($db->query("SELECT LabRefNo, COALESCE(ETRFNo,'') AS ETRFNo, UPPER(TRIM(COALESCE(Result,''))) AS res, LEFT({$pd}, 10) AS d FROM tbl_dtr_bonding_info WHERE {$base0} AND LEFT({$pd}, 10) BETWEEN {$eMin} AND " . $db->escape($xlUntil) . " ORDER BY LabRefNo")->getResultArray() as $n) {
                $lref = trim((string) $n['LabRefNo']);
                $etrf = strtoupper(trim((string) $n['ETRFNo']));
                $resU = (string) $n['res'];
                $rst  = (str_starts_with($resU, 'FAIL') || $resU === 'REJECTED') ? 'FAILED' : (str_starts_with($resU, 'PASS') ? 'PASS' : (str_starts_with($resU, 'TOL') ? 'TOLERABLE' : 'PENDING'));
                if ($etrf !== '' && $lref !== '') {
                    $byEtrf[$etrf][] = ['ref' => $lref, 'result' => $rst];
                }
                if (preg_match('/^DTP\d{8}(?:SH)([AB]\d+)(?:-[LR])?$/i', $lref, $mm)) {
                    $kdl = $n['d'] . '|' . strtoupper($mm[1]);
                    if ($rst === 'FAILED') {
                        $nxRef[$kdl][] = $lref;
                    }
                    $nxAnyRef[$kdl][] = $lref;
                }
            }
            foreach ($db->query("SELECT LabRefNo, COALESCE(NULLIF(LEFT(ProductionDate,10),''), {$td}) AS d FROM {$trk} WHERE UPPER(COALESCE(TestType,'')) = 'DAILY' AND COALESCE(PullTest,0) = 0 AND COALESCE(Shutdown,0) = 0 AND COALESCE(NULLIF(LEFT(ProductionDate,10),''), {$td}) BETWEEN {$eMin} AND " . $db->escape($xlUntil) . " ORDER BY LabRefNo")->getResultArray() as $n) {
                $lref = trim((string) $n['LabRefNo']);
                if (preg_match('/^DTP\d{8}(?:SH)([AB]\d+)(?:-[LR])?$/i', $lref, $mm)) {
                    $kdl = $n['d'] . '|' . strtoupper($mm[1]);
                    if (! in_array($lref, $nxAnyRef[$kdl] ?? [], true)) {
                        $nxAnyRef[$kdl][] = $lref;
                    }
                }
            }
            // the Excel line has no cause typed yet: take it from the failed bonding test of the same Lab Ref (tbl_dtr_bonding_info)
            $nxWhy = [];
            $pnAll = ['U' => 'Upper to Foxing', 'F' => 'Foxing to Outsole', 'I' => 'Filler to Outsole'];
            foreach ($db->query("SELECT LabRefNo, Cause, FailPart, ModeOfFailure, PossibleCause, ProbableCause FROM tbl_dtr_bonding_info WHERE {$base0} AND LEFT({$pd}, 10) BETWEEN {$eMin} AND " . $db->escape($xlUntil) . " AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED')")->getResultArray() as $n) {
                $pcN = $this->failParse($n);
                $pN  = [];
                $tN  = [];
                foreach ($pcN as $pk => $cs) {
                    if ($pk !== '' && isset($pnAll[$pk])) {
                        $pN[] = $pnAll[$pk];
                    }
                    if ($cs) {
                        $tN[] = ($pk !== '' && isset($pnAll[$pk]) ? $pnAll[$pk] . ': ' : '') . implode(' / ', $cs);
                    }
                }
                if ($tN === []) {
                    $raw = trim((string) ($n['ProbableCause'] ?: $n['PossibleCause'] ?: $n['Cause'] ?: $n['ModeOfFailure']));
                    if ($raw !== '') {
                        $tN[] = $raw;
                    }
                }
                if ($tN !== []) {
                    $nxWhy[strtoupper(trim((string) $n['LabRefNo']))] = ['ports' => $pN, 'why' => $tN];
                }
            }
            $xlFail = [];
            foreach ($xlRows as $x) {
                if ($x['d'] > $xlUntil) {
                    continue;
                }
                $lnNo   = strtoupper(trim((string) $x['line_no']));
                $bldX   = $lnNo !== '' ? $lnNo[0] : '';
                $ports  = [];
                $why    = [];
                foreach (['upper_to_foxing' => 'Upper to Foxing', 'foxing_to_outsole' => 'Foxing to Outsole'] as $col => $nm) {
                    if (trim((string) $x[$col]) !== '') {
                        $ports[] = $nm;
                        $why[]   = $nm . ': ' . trim((string) $x[$col]);
                    }
                }
                $lineBase = preg_match('/^[AB]\d+/', $lnNo, $lm) ? $lm[0] : $lnNo;
                $key      = $x['d'] . '|' . $lineBase;
                $failEtrf = strtoupper(trim((string) $x['failed_ref']));
                $mref     = '';
                if ($failEtrf !== '' && ! empty($byEtrf[$failEtrf])) {
                    foreach ($byEtrf[$failEtrf] as $ec) {
                        if (! preg_match('/-R[0O]?\d+$/i', $ec['ref'])) {
                            $mref = $ec['ref'];
                            break;
                        }
                    }
                }
                if ($mref === '' && ! empty($nxRef[$key])) {
                    $mref = (string) array_shift($nxRef[$key]);
                }
                if ($mref === '' && ! empty($nxAnyRef[$key])) {
                    $mref = (string) array_shift($nxAnyRef[$key]);
                }
                if ($why === [] && $mref !== '' && isset($nxWhy[strtoupper($mref)])) {
                    $ports = $nxWhy[strtoupper($mref)]['ports'];
                    $why   = $nxWhy[strtoupper($mref)]['why'];
                }
                $hasRt = trim((string) $x['retest_ref']) !== '' && $x['retest_result'] !== 'CLOSED';
                $rsl  = ['PASS' => 'PASS', 'FAILED' => 'FAILED', 'TOLERABLE' => 'TOLERABLE', 'INSTRUCTION' => 'TOLERABLE'][$x['retest_result']] ?? 'PENDING';
                $nxRtRef = '';
                $xlRtDate = '';
                if ($hasRt) {
                    $mrefUp  = strtoupper($mref);
                    $rtEtrf  = strtoupper(trim((string) $x['retest_ref']));
                    $xlCands = ($mrefUp !== '' ? ($byOf[$mrefUp] ?? $byBase[$mrefUp] ?? []) : []);
                    if (! $xlCands) {
                        $dlKey = 'DTP' . str_replace('-', '', (string) $x['d']) . '|' . $lineBase;
                        if (isset($byDateLine[$dlKey])) {
                            $xlCands = $byDateLine[$dlKey];
                        }
                    }
                    if ($rtEtrf !== '' && ! empty($byEtrf[$rtEtrf])) {
                        $xlCands = array_merge($xlCands, $byEtrf[$rtEtrf]);
                    }
                    $pickedRt = $pickR01($xlCands);
                    if ($pickedRt !== null && trim((string) ($pickedRt['ref'] ?? '')) !== '') {
                        $nxRtRef = trim((string) $pickedRt['ref']);
                    }
                    // If original failed LabRefNo wasn't directly recorded, derive it by stripping -R01/-RO1 from the retest ref
                    if ($mref === '' && $nxRtRef !== '' && preg_match('/-R[0O]?\d+$/i', $nxRtRef)) {
                        $mref = (string) preg_replace('/-R[0O]?\d+$/i', '', $nxRtRef);
                    }
                    $rtRefStr = trim((string) $x['retest_ref']);
                    if (preg_match('/^DTP(\d{4})(\d{2})(\d{2})/i', $rtRefStr, $rm)) {
                        $xlRtDate = $rm[1] . '-' . $rm[2] . '-' . $rm[3];
                    } else {
                        $xlRtDate = (string) $x['td'];
                    }
                }
                $baseD = trim((string) $x['td']) !== '' ? (string) $x['td'] : (string) $x['d'];
                $xlFail[] = [
                    'date'         => (string) $x['d'],
                    'tested'       => (string) $x['td'],
                    'building'     => $bldX,
                    'line'         => $lnNo,
                    'ref'          => $mref !== '' ? $mref : trim((string) $x['failed_ref']),
                    'testType'     => 'DAILY',
                    'style'        => trim((string) $x['style']),
                    'color'        => trim((string) $x['colorway']),
                    'portion'      => implode(', ', $ports),
                    'why'          => implode(' | ', $why),
                    'retestDone'   => $hasRt,
                    'retestRef'    => $hasRt ? ($nxRtRef !== '' ? $nxRtRef : trim((string) $x['retest_ref'])) : '',
                    'retestResult' => $hasRt ? $rsl : '',
                    'retestDate'   => $xlRtDate,
                    'daysToRetest' => $hasRt ? $calcDays((string) $x['d'], $xlRtDate) : $calcDays($baseD, $asOf),
                    'retestCount'  => $hasRt ? 1 : 0,
                ];
            }
            // a failed line marked by hand on the Monthly Summary (Excel has it as PASS) that the Excel retest record does not list: its row comes from the Failed Bldg sheet,
            // so the Retest tab counts the same failed shoes as the Monthly Summary
            $have = [];
            foreach ($xlFail as $xf) { $have[$xf['date'] . '|' . strtoupper(preg_match('/^[AB]\d+/', (string) $xf['line'], $hm) ? $hm[0] : (string) $xf['line'])] = true; }
            try {
                $this->ensureMarksTable($db);
                foreach ($db->query("SELECT Building, `LineNo` AS ln, ProdDate AS d FROM tbl_an_bonding_marks WHERE Mark = '1' AND ProdDate BETWEEN {$eMin} AND " . $db->escape($xlUntil))->getResultArray() as $mk) {
                    $lk2 = strtoupper((string) $mk['Building']) . (int) $mk['ln'];
                    if (isset($have[$mk['d'] . '|' . $lk2])) {
                        continue;
                    }
                    $tbl = strtoupper((string) $mk['Building']) === 'A' ? 'tbl_Failed_BldgA' : 'tbl_Failed_BldgB';
                    $fs  = $db->query("SELECT tested_date, line_no, style, colorway, lab_ref, upper_to_foxing, foxing_to_outsole, retest_result FROM dbexcelbackup.{$tbl} WHERE production_date = ? AND UPPER(TRIM(line_no)) = ? LIMIT 1", [$mk['d'], $lk2])->getRowArray();
                    if (! $fs) {
                        continue;
                    }
                    $ports = [];
                    $why   = [];
                    foreach (['upper_to_foxing' => 'Upper to Foxing', 'foxing_to_outsole' => 'Foxing to Outsole'] as $col => $nm) {
                        if (trim((string) $fs[$col]) !== '') { $ports[] = $nm; $why[] = $nm . ': ' . trim((string) $fs[$col]); }
                    }
                    $fRef = trim((string) $fs['lab_ref']);
                    if ($why === [] && isset($nxWhy[strtoupper($fRef)])) {
                        $ports = $nxWhy[strtoupper($fRef)]['ports'];
                        $why   = $nxWhy[strtoupper($fRef)]['why'];
                    }
                    $baseD = trim((string) $fs['tested_date']) !== '' ? (string) $fs['tested_date'] : (string) $mk['d'];
                    $xlFail[] = [
                        'date' => (string) $mk['d'], 'tested' => (string) $fs['tested_date'], 'building' => strtoupper((string) $mk['Building']), 'line' => $lk2,
                        'ref' => $fRef, 'testType' => 'DAILY', 'style' => trim((string) $fs['style']), 'color' => trim((string) $fs['colorway']),
                        'portion' => implode(', ', $ports), 'why' => implode(' | ', $why),
                        'retestDone' => false, 'retestRef' => '', 'retestResult' => '', 'retestDate' => '',
                        'daysToRetest' => $calcDays($baseD, $asOf), 'retestCount' => 0,
                    ];
                }
            } catch (\Throwable $e) {
                // the Failed Bldg sheets are not on this server: the Excel retest record stays as it is
            }
        }

        foreach ($fQuery as $fr) {
            $ref   = trim((string) $fr['LabRefNo']);
            $refUp = strtoupper($ref);
            $bld   = '';
            $line  = '';
            if (preg_match('/^DTP\d{8}[A-Z]*?([AB])(\d+)(?:-([LR]))?/i', $ref, $m)) {
                $bld  = strtoupper($m[1]);
                $line = $bld . $m[2] . (! empty($m[3]) ? '-' . strtoupper($m[3]) : '');
            }

            $pc    = $this->failParse($fr);
            $ports = [];
            $caus  = [];
            $txt   = [];
            foreach ($pc as $p => $cs) {
                if ($p !== '' && isset($pName[$p])) {
                    $ports[$p] = $pName[$p];
                }
                foreach ($cs as $c) {
                    $caus[$c] = true;
                }
                if ($cs) {
                    $txt[] = ($p !== '' && isset($pName[$p]) ? $pName[$p] . ': ' : '') . implode(' / ', $cs);
                }
            }
            if (! $ports) {
                $allTx = strtoupper($fr['ModeOfFailure'] . ' | ' . $fr['PossibleCause'] . ' | ' . $fr['ProbableCause']);
                foreach (['U' => 'UPPER TO FOXING', 'F' => 'FOXING TO OUTSOLE', 'I' => 'FILLER TO OUTSOLE'] as $pk => $pt) {
                    if (str_contains($allTx, $pt)) {
                        $ports[$pk] = $pName[$pk];
                    }
                }
            }
            $whyText = implode(' | ', $txt);
            if ($whyText === '') {
                $whyText = trim((string) ($fr['ProbableCause'] ?: $fr['PossibleCause'] ?: $fr['Cause'] ?: $fr['ModeOfFailure']));
            }

            // Match retest(s) for this failed specimen
            $lk = $lineKeyOf($refUp);
            $matches = $byOf[$refUp] ?? $byBase[$refUp] ?? [];
            if (! $matches && $lk['dl'] !== '' && isset($byDateLine[$lk['dl']])) {
                foreach ($byDateLine[$lk['dl']] as $cand) {
                    if (strtoupper($cand['ref']) !== $refUp && $cand['lv'] >= $lk['lv']) {
                        $matches[] = $cand;
                    }
                }
            }

            $rtDone   = ! empty($matches);
            $bestRt   = $rtDone ? $pickR01($matches) : null;
            $failBase = trim((string) $fr['td']) !== '' ? (string) $fr['td'] : (string) $fr['d'];
            $rtDate   = $bestRt['date'] ?? '';

            $failedRows[] = [
                'date'         => (string) $fr['d'],
                'tested'       => (string) $fr['td'],
                'building'     => $bld,
                'line'         => $line,
                'ref'          => $ref,
                'testType'     => strtoupper(trim((string) $fr['TestType'])) ?: 'DAILY',
                'style'        => trim((string) $fr['ModelStyle']),
                'color'        => trim((string) $fr['Color']),
                'portion'      => implode(', ', array_values($ports)),
                'why'          => $whyText,
                'retestDone'   => $rtDone,
                'retestRef'    => $bestRt['ref'] ?? '',
                'retestResult' => $bestRt['result'] ?? '',
                'retestDate'   => $rtDate,
                'daysToRetest' => $rtDone ? $calcDays($failBase, $rtDate) : $calcDays($failBase, $asOf),
                'retestCount'  => count($matches),
            ];
        }

        if (! empty($xlFail)) {
            $failedRows = array_merge($failedRows, $xlFail);
            usort($failedRows, static fn ($a, $b) => [$b['date'], $a['line']] <=> [$a['date'], $b['line']]);
        }

        // every failed shoe is kept (a late or still-missing retest is exactly what this tab must show), so the total equals the Monthly Summary failed count

        // Aggregate KPIs, donuts, and chart series directly from the filtered Daily failedRows so every card and table count balances 100%
        foreach ($failedRows as $r) {
            $d = $r['date'];
            $day[$d] ??= $new();
            $day[$d]['failed']++;
            if ($r['retestDone']) {
                $day[$d]['retest']++;
                $day[$d][$cls((string) $r['retestResult'])]++;
            }
        }
        $sum = static function (array $days) use ($day, $new): array {
            $o = $new();
            foreach ($days as $d) {
                foreach ($o as $k => $_) {
                    $o[$k] += $day[$d][$k] ?? 0;
                }
            }

            return $o;
        };
        // tested / passed DAILY shoes per production day (same split as the Monthly Summary: Excel record before BACKUP_UNTIL, Nexus from it on),
        // so the failed baseline can be shown as a pass vs failed percentage
        $tp = [];   // [d] => [tested, pass]
        $bkDay = date('Y-m-d', strtotime(self::BACKUP_UNTIL . ' -1 day'));
        try {
            $bk = [];
            if ($minD <= $bkDay && $db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_monthlybondingsummary'")->getNumRows() > 0) {
                $bk = $db->query("SELECT production_date AS d, UPPER(TRIM(assembly_line)) AS ln, UPPER(TRIM(COALESCE(bonding_result, ''))) AS res FROM dbexcelbackup.tbl_monthlybondingsummary
                    WHERE production_date BETWEEN {$eMin} AND " . $db->escape(min($maxD, $bkDay)) . " AND is_shutdown <> 1 AND UPPER(TRIM(assembly_line)) REGEXP '^[A-Z][0-9]+$'")->getResultArray();
            }
            $haveD = array_flip(array_column($bk, 'd'));
            if ($minD <= $bkDay) {
                foreach ($db->query('SELECT production_date AS d, line_no AS ln, UPPER(TRIM(result)) AS res FROM dbexcelbackup.tbl_weekly_bonding_result WHERE production_date BETWEEN ' . $eMin . ' AND ' . $db->escape(min($maxD, $bkDay)) . " AND line_no REGEXP '^[A-Za-z][0-9]+$'")->getResultArray() as $wr) {
                    if (! isset($haveD[$wr['d']])) {
                        $bk[] = $wr;
                    }
                }
            }
            $seenTp = [];
            foreach ($bk as $r) {
                $rs = (string) $r['res'];
                if (! preg_match('/^(PASS|TOL|FAIL)/', $rs)) {
                    continue;
                }
                if (isset($seenTp[$r['d']][strtoupper((string) $r['ln'])])) {   // one tested line per day, like the Monthly Summary
                    continue;
                }
                $seenTp[$r['d']][strtoupper((string) $r['ln'])] = true;
                $tp[$r['d']][0] = ($tp[$r['d']][0] ?? 0) + 1;
                $tp[$r['d']][1] = ($tp[$r['d']][1] ?? 0) + (str_starts_with($rs, 'PASS') ? 1 : 0);
            }
            $nxFrom = max($minD, self::BACKUP_UNTIL);
            if ($nxFrom <= $maxD) {
                foreach ($db->query("SELECT LEFT({$pd}, 10) AS d, COUNT(DISTINCT SUBSTRING_INDEX(LabRefNo, '-', 1)) AS t, COUNT(DISTINCT CASE WHEN UPPER(Result) LIKE 'PASS%' THEN SUBSTRING_INDEX(LabRefNo, '-', 1) END) AS p FROM tbl_dtr_bonding_info
                    WHERE {$base0} AND {$dailyOnly} AND LEFT({$pd}, 10) BETWEEN " . $db->escape($nxFrom) . " AND {$eMax} GROUP BY d")->getResultArray() as $r) {
                    $tp[$r['d']] = [(int) $r['t'], (int) $r['p']];
                }
            }
        } catch (\Throwable $e) {
        }
        // the tested / passed counts are the ones of the Monthly Summary (one tested line per day, hand marks included), so this tab, the Monthly Summary and the other tabs agree
        $pendRt = ['d' => '', 'failed' => 0];
        try {
            [$sf2, $st2] = [$this->from, $this->to];
            $this->from  = $minD;
            $this->to    = $maxD;
            $mm          = $this->bondingMonthly($db);
            [$this->from, $this->to] = [$sf2, $st2];
            $tpM = [];
            foreach ($mm['days'] ?? [] as $dm) {
                $tpM[$dm['d']] = [(int) $dm['tested'], (int) ($dm['passed'] ?? 0)];
            }
            if ($tpM) {
                $tp = $tpM;
            }
        } catch (\Throwable $e) {
            [$this->from, $this->to] = [$sf2 ?? $this->from, $st2 ?? $this->to];
        }
        $out = [];
        foreach ($rg as $k => [$a, $b]) {
            $days = [];
            for ($t = strtotime($a); $t <= strtotime($b); $t += 86400) {
                $days[] = date('Y-m-d', $t);
            }
            $series = [];
            if ($k === 'yearly') {
                for ($m = 1; $m <= 12; $m++) {
                    $ds = array_filter($days, static fn ($d) => (int) substr($d, 5, 2) === $m);
                    $series[] = ['label' => date('M', mktime(0, 0, 0, $m, 1)), 'tip' => date('F Y', mktime(0, 0, 0, $m, 1, (int) substr($a, 0, 4)))] + $sum($ds);
                }
            } else {
                foreach ($days as $d) {
                    $series[] = ['label' => $k === 'monthly' ? date('j', strtotime($d)) : date('D j', strtotime($d)), 'tip' => $d] + $sum([$d]);
                }
            }
            $tot = $k === 'daily' ? $sum([$asOf]) : $sum($days);
            $tT = 0;
            $tP = 0;
            foreach ($k === 'daily' ? [$asOf] : $days as $dd) {
                $tT += $tp[$dd][0] ?? 0;
                $tP += $tp[$dd][1] ?? 0;
            }
            $tot += ['tested' => $tT, 'passed' => $tP, 'passPct' => $tT > 0 ? round($tP / $tT * 100, 1) : 0, 'failPct' => $tT > 0 ? round(($tot['failed'] - ($pendRt['d'] !== '' && $pendRt['d'] >= $a && $pendRt['d'] <= $b ? $pendRt['failed'] : 0)) / $tT * 100, 2) : 0];
            $out[$k] = $tot + ['from' => $k === 'daily' ? $asOf : $a, 'to' => $b, 'pct' => $tot['failed'] > 0 ? round($tot['retest'] / $tot['failed'] * 100, 1) : 0, 'series' => $series];
        }

        return ['months' => [], 'kpis' => [], 'lists' => [], 'chartTitle' => '', 'rt' => $out, 'rtRows' => $failedRows, 'asOf' => $asOf];
    }

    /** Bonding Failure Overview data: failed bonding shoes by building, by portion and by reason (Material / Rubber Foxing/Outsole / Workmanship) */
    public function bondingFailure($db): array
    {
        $pd   = self::BOND_DATE;
        // DAILY bonding only: Tech Trial, Trial and Sample tests are not production-line failures
        $base = $this->bondingBaseWhere($pd) . " AND (TestType IS NULL OR TRIM(TestType) = '' OR UPPER(TRIM(TestType)) = 'DAILY') AND UPPER(COALESCE(LabRefNo,'')) NOT LIKE '%TRIAL%' AND UPPER(COALESCE(LabRefNo,'')) NOT LIKE '%SAMPLE%'";
        // production dates up to $xlUntil come from the Excel failed-bonding record (dbexcelbackup.tbl_Failed_BldgA / tbl_Failed_BldgB, one row per failed shoe);
        // Nexus supplies only the dates after it
        $xl      = [];
        $xlUntil = '';
        try {
            foreach (['A' => 'tbl_Failed_BldgA', 'B' => 'tbl_Failed_BldgB'] as $xb => $xt) {
                if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE '{$xt}'")->getNumRows() < 1) {
                    continue;
                }
                $mx = (string) ($db->query("SELECT MAX(production_date) AS m FROM dbexcelbackup.{$xt} WHERE production_date <= CURDATE()")->getRowArray()['m'] ?? '');
                $xlUntil = max($xlUntil, $mx);
            }
            if ($xlUntil !== '') {
                $xw = [];
                if ($this->from !== '') {
                    $xw[] = 'production_date >= ' . $db->escape($this->from);
                }
                if ($this->to !== '') {
                    $xw[] = 'production_date <= ' . $db->escape($this->to);
                }
                $xw[] = 'production_date <= ' . $db->escape($xlUntil);
                foreach (['A' => 'tbl_Failed_BldgA', 'B' => 'tbl_Failed_BldgB'] as $xb => $xt) {
                    foreach ($db->query("SELECT * FROM dbexcelbackup.{$xt} WHERE " . implode(' AND ', $xw) . ' ORDER BY production_date ASC, source_row ASC')->getResultArray() as $xr) {
                        $xl[] = $xr + ['bld' => $xb];
                    }
                }
            }
        } catch (\Throwable $e) {
            $xl = [];
            $xlUntil = '';
        }
        // same failed count as the Monthly Summary: one failed shoe per line and production date, and before BACKUP_UNTIL only the lines the Monthly Summary
        // has as failed (Excel Monthly Summary FAILED, or marked failed by hand); a Failed Bldg row that is a repeat of that line / day, or that the Monthly Summary has as PASS, is not counted twice
        if ($xl) {
            $okKeys = null;
            try {
                $okKeys = [];
                if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_monthlybondingsummary'")->getNumRows() > 0) {
                    foreach ($db->query("SELECT production_date AS d, UPPER(TRIM(assembly_line)) AS ln FROM dbexcelbackup.tbl_monthlybondingsummary WHERE production_date < ? AND (UPPER(TRIM(bonding_result)) LIKE 'FAIL%' OR UPPER(TRIM(bonding_result)) = 'REJECTED')", [self::BACKUP_UNTIL])->getResultArray() as $k) {
                        $okKeys[$k['d'] . '|' . $k['ln']] = true;
                    }
                }
                $this->ensureMarksTable($db);
                foreach ($db->query("SELECT Building, `LineNo` AS ln, ProdDate AS d FROM tbl_an_bonding_marks WHERE Mark = '1' AND ProdDate < ?", [self::BACKUP_UNTIL])->getResultArray() as $k) {
                    $okKeys[$k['d'] . '|' . strtoupper((string) $k['Building']) . (int) $k['ln']] = true;
                }
            } catch (\Throwable $e) {
                $okKeys = null;
            }
            $seenXl = [];
            $xl = array_values(array_filter($xl, static function ($x) use ($okKeys, &$seenXl) {
                $d  = (string) $x['production_date'];
                $ln = strtoupper(trim((string) $x['line_no']));
                $ln = preg_match('/^[A-Z]\d+/', $ln, $lm) ? $lm[0] : $ln;
                if ($d === '' || $ln === '') {
                    return true;
                }
                if ($okKeys !== null && $d < self::BACKUP_UNTIL && ! isset($okKeys[$d . '|' . $ln])) {
                    return false;
                }
                if (isset($seenXl[$d . '|' . $ln])) {
                    return false;
                }
                $seenXl[$d . '|' . $ln] = true;

                return true;
            }));
        }
        $baseAll = $base;
        if ($xlUntil !== '') {
            $base .= ' AND ' . $pd . ' > ' . $db->escape($xlUntil);
        }
        $failSel = "SELECT LabRefNo, ModelStyle, Color, MTL, Technician, ReceivedDate, TestedDate, Result, {$pd} AS d, Cause, FailPart, ModeOfFailure, PossibleCause, ProbableCause FROM tbl_dtr_bonding_info";
        $rows = [];
        // Excel failed rows that only name the line (no Lab Ref / style / portions): take the real failed specimen from the Nexus record
        // for the same production date and line, so the log shows its Lab Ref, style, tester and portions
        $blank = [];
        foreach ($xl as $i => $x) {
            if (trim((string) $x['lab_ref']) === '' && trim((string) $x['production_date']) !== '') {
                $blank[$i] = $x;
            }
        }
        if ($blank) {
            try {
                $dts = array_values(array_unique(array_map(static fn ($x) => $db->escape((string) $x['production_date']), $blank)));
                $cand = $db->query($failSel . " WHERE {$baseAll} AND {$pd} IN (" . implode(',', $dts) . ") AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') ORDER BY LabRefNo ASC")->getResultArray();
                foreach ($blank as $i => $x) {
                    $xline = strtoupper(trim((string) $x['line_side']));
                    foreach ($cand as $ci => $cr) {
                        if (substr((string) $cr['d'], 0, 10) !== (string) $x['production_date']) {
                            continue;
                        }
                        if (! preg_match('/^DTP\d{8}[A-Z]{2}([A-Z]\d+)(?:-([LR]))?/i', (string) $cr['LabRefNo'], $cm)) {
                            continue;
                        }
                        $nline = strtoupper($cm[1]);
                        $nside = $nline . (! empty($cm[2]) ? '-' . strtoupper($cm[2]) : '');
                        if ($xline === $nside || $xline === $nline) {
                            $rows[] = $cr;
                            unset($cand[$ci], $xl[$i]);
                            break;
                        }
                    }
                }
                $xl = array_values($xl);
            } catch (\Throwable $e) {
            }
        }
        $rows = array_merge($rows, $db->query("SELECT LabRefNo, ModelStyle, Color, MTL, Technician, ReceivedDate, TestedDate, Result, {$pd} AS d, Cause, FailPart, ModeOfFailure, PossibleCause, ProbableCause
            FROM tbl_dtr_bonding_info WHERE {$base} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') ORDER BY d ASC, LabRefNo ASC")->getResultArray());

        $byB =['A' => ['por' => ['Upper to Foxing' => 0, 'Foxing to Outsole' => 0, 'Filler to Outsole' => 0], 'rea' => ['Material' => 0, 'Rubber Foxing/Outsole' => 0, 'Workmanship' => 0]],
                'B' => ['por' => ['Upper to Foxing' => 0, 'Foxing to Outsole' => 0, 'Filler to Outsole' => 0], 'rea' => ['Material' => 0, 'Rubber Foxing/Outsole' => 0, 'Workmanship' => 0]]];
        $log = [];
        $bld = ['A' => 0, 'B' => 0];
        $por = ['Upper to Foxing' => 0, 'Foxing to Outsole' => 0, 'Filler to Outsole' => 0];
        $rea = ['Material' => 0, 'Rubber Foxing/Outsole' => 0, 'Workmanship' => 0];
        $pName = ['U' => 'Upper to Foxing', 'F' => 'Foxing to Outsole', 'I' => 'Filler to Outsole'];
        $noPortion = 0;
        $noReason  = 0;
        foreach ($rows as $r) {
            $b = '';
            if (preg_match('/^DTP\d{8}[A-Z]{2}([A-Z])(\d+)(?:-([LR]))?/i', (string) $r['LabRefNo'], $m)) {
                $b = strtoupper($m[1]);
                $bld[$b] = ($bld[$b] ?? 0) + 1;
            }
            $line = $b !== '' ? $b . $m[2] . (! empty($m[3]) ? '-' . strtoupper($m[3]) : '') : '';
            $pc    = $this->failParse($r);
            $ports = [];
            $caus  = [];
            foreach ($pc as $p => $cs) {
                if ($p !== '') {
                    $ports[$p] = true;
                }
                foreach ($cs as $c) {
                    $caus[$c] = true;
                }
            }
            if (! $ports) {   // portion named only in the mode of failure text
                $all = strtoupper($r['ModeOfFailure'] . ' | ' . $r['PossibleCause'] . ' | ' . $r['ProbableCause']);
                foreach (['U' => 'UPPER TO FOXING', 'F' => 'FOXING TO OUTSOLE', 'I' => 'FILLER TO OUTSOLE'] as $k => $t) {
                    if (str_contains($all, $t)) {
                        $ports[$k] = true;
                    }
                }
            }
            if (! $ports) {
                $noPortion++;
            }
            foreach (array_keys($ports) as $p) {
                $por[$pName[$p]]++;
                if (isset($byB[$b])) {
                    $byB[$b]['por'][$pName[$p]]++;
                }
            }
            if (! $caus) {
                $noReason++;
            }
            $cat = [];
            foreach (array_keys($caus) as $c) {
                $cat[str_contains($c, 'MATERIAL') ? 'Material' : (preg_match('/FOXING|OUTSOLE|RUBBER/', $c) ? 'Rubber Foxing/Outsole' : 'Workmanship')] = true;
            }
            foreach (array_keys($cat) as $k) {
                $rea[$k]++;
                if (isset($byB[$b])) {
                    $byB[$b]['rea'][$k]++;
                }
            }
            $txt = [];
            foreach ($pc as $p => $cs) {
                $txt[] = ($p !== '' ? $pName[$p] . ': ' : '') . implode(' / ', $cs);
            }
            $log[] = [
                'prod' => substr((string) $r['d'], 0, 10), 'recv' => substr((string) $r['ReceivedDate'], 0, 10), 'line' => $line, 'mtl' => (string) $r['MTL'],
                'style' => (string) $r['ModelStyle'], 'color' => (string) $r['Color'], 'ref' => (string) $r['LabRefNo'], 'tech' => (string) $r['Technician'],
                'cause' => implode(' | ', $txt), 'portion' => implode(', ', array_map(static fn ($p) => $pName[$p], array_keys($ports))), 'reason' => implode(', ', array_keys($cat)),
                'tested' => substr((string) $r['TestedDate'], 0, 10), 'result' => strtoupper(trim((string) $r['Result'])),
            ];
        }

        // the Excel record: building from the sheet, portions from its three portion columns, reasons from its category column(s)
        foreach ($xl as $x) {
            $b = $x['bld'];
            $bld[$b]++;
            $pcols = ['Upper to Foxing' => 'upper_to_foxing', 'Foxing to Outsole' => 'foxing_to_outsole', 'Filler to Outsole' => 'filler_to_outsole'];
            $ports = [];
            $txt   = [];
            foreach ($pcols as $pn => $col) {
                if (trim((string) $x[$col]) !== '') {
                    $ports[] = $pn;
                    $por[$pn]++;
                    $byB[$b]['por'][$pn]++;
                    $txt[] = $pn . ': ' . trim((string) $x[$col]);
                }
            }
            if (! $ports) {
                $noPortion++;
            }
            $cat = [];
            foreach (['cat_workmanship' => 'Workmanship', 'cat_material' => 'Material', 'cat_rubber' => 'Rubber Foxing/Outsole'] as $col => $cn) {
                if ((int) $x[$col] === 1) {
                    $cat[] = $cn;
                    $rea[$cn]++;
                    $byB[$b]['rea'][$cn]++;
                }
            }
            if (! $cat) {
                $noReason++;
            }
            $log[] = [
                'prod' => (string) $x['production_date'], 'recv' => '', 'line' => (string) $x['line_side'], 'mtl' => (string) $x['mtl_no'],
                'style' => (string) $x['style'], 'color' => (string) $x['colorway'], 'ref' => (string) $x['lab_ref'], 'tech' => (string) $x['tested_by'],
                'cause' => implode(' | ', $txt), 'portion' => implode(', ', $ports), 'reason' => implode(', ', $cat),
                'tested' => (string) $x['tested_date'], 'result' => 'FAILED',
            ];
        }
        // the specimen log: most recent production date first (same day: by Lab Ref)
        usort($log, static fn ($a, $b2) => strcmp($b2['prod'], $a['prod']) ?: strcmp($a['ref'], $b2['ref']));

        return ['months' => [], 'kpis' => [], 'lists' => [], 'chartTitle' => '', 'fb' => ['bld' => $bld, 'por' => $por, 'rea' => $rea, 'byB' => $byB, 'log' => $log, 'failed' => count($rows) + count($xl), 'noPortion' => $noPortion, 'noReason' => $noReason]];
    }

    public function ensureTotalsTable($db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS tbl_an_bonding_totals (
            Kind CHAR(1) NOT NULL,
            Building CHAR(1) NOT NULL DEFAULT '-',
            ProdDate DATE NOT NULL,
            Val INT NOT NULL,
            UpdatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Kind, Building, ProdDate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function ensureMarksTable($db): void
    {
        $db->query('CREATE TABLE IF NOT EXISTS tbl_an_bonding_marks (
            Building CHAR(1) NOT NULL,
            `LineNo` SMALLINT NOT NULL,
            ProdDate DATE NOT NULL,
            Mark CHAR(1) NOT NULL,
            UpdatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Building, `LineNo`, ProdDate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** Bonding Result: pass / tolerable / failed counts per cement (adhesive) supplier, with a total row. */
    public function bondingResult($db): array
    {
        // same split as the Monthly Summary: the Excel weekly record (dbexcelbackup.tbl_weekly_bonding_result, with the cement of each line and day) for the production dates
        // BEFORE BACKUP_UNTIL, the live Nexus bonding records from it on; one tested line per day (the worse result of its L / R specimens), DAILY tests only
        $sup = static function (string $s): string {
            $s = strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
            if ($s === '' || str_starts_with($s, 'NOT PROVIDED')) {
                return '';
            }
            if (str_contains($s, 'NAN') || $s === 'NP') {
                return 'NANPAO';
            }
            if (str_contains($s, 'ZHONG') || $s === 'ZB') {
                return 'ZHONGBU';
            }

            return $s;
        };
        $sev  = ['FAIL' => 3, 'TOL' => 2, 'PASS' => 1];
        $kind = static function (string $r): string {
            $r = strtoupper(trim($r));

            return (str_starts_with($r, 'FAIL') || $r === 'REJECTED') ? 'FAIL' : (str_starts_with($r, 'PASS') ? 'PASS' : (str_starts_with($r, 'TOL') ? 'TOL' : ''));
        };
        $acc = [];   // [supplier][date|line] => PASS|TOL|FAIL
        $bkDates = [];
        try {
            if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_weekly_bonding_result'")->getNumRows() > 0) {
                $marks = [];
                $this->ensureMarksTable($db);
                foreach ($db->query('SELECT Building, `LineNo` AS ln, ProdDate AS d FROM tbl_an_bonding_marks WHERE Mark = ? AND ProdDate < ?', ['1', self::BACKUP_UNTIL])->getResultArray() as $mk) {
                    $marks[$mk['d'] . '|' . strtoupper((string) $mk['Building']) . (int) $mk['ln']] = true;
                }
                foreach ($db->query('SELECT production_date AS d, UPPER(TRIM(line_no)) AS ln, UPPER(TRIM(result)) AS res, cement FROM dbexcelbackup.tbl_weekly_bonding_result WHERE production_date < ? AND ' . $this->dateWhere('production_date') . " AND line_no REGEXP '^[A-Za-z][0-9]+$'", [self::BACKUP_UNTIL])->getResultArray() as $wr) {
                    $bkDates[$wr['d']] = true;
                    $k = $kind((string) $wr['res']);
                    $s = $sup((string) $wr['cement']);
                    if ($k === '' || $s === '') {
                        continue;
                    }
                    if ($k !== 'FAIL' && isset($marks[$wr['d'] . '|' . $wr['ln']])) {
                        $k = 'FAIL';   // marked failed by hand on the Monthly Summary
                    }
                    $key = $wr['d'] . '|' . $wr['ln'];
                    if (($sev[$k] ?? 0) > ($sev[$acc[$s][$key] ?? ''] ?? 0)) {
                        $acc[$s][$key] = $k;
                    }
                }
            }
        } catch (\Throwable $e) {
            // the Excel backup is not on this server: the Nexus records alone are used
        }
        $w     = $this->bondingBaseWhere(self::BOND_DATE) . " AND (TestType IS NULL OR TRIM(TestType) = '' OR UPPER(TestType) = 'DAILY')";
        $notBk = $bkDates ? ' AND ' . self::BOND_DATE . ' NOT IN (' . implode(',', array_map([$db, 'escape'], array_keys($bkDates))) . ')' : '';
        foreach ($db->query('SELECT ' . self::BOND_DATE . " AS d, SUBSTRING_INDEX(LabRefNo, '-', 1) AS ln, AdhesiveSupplier AS cement, Result AS res FROM tbl_dtr_bonding_info WHERE {$w}{$notBk} AND AdhesiveSupplier IS NOT NULL AND TRIM(AdhesiveSupplier) <> ''")->getResultArray() as $nr) {
            $k = $kind((string) $nr['res']);
            $s = $sup((string) $nr['cement']);
            if ($k === '' || $s === '') {
                continue;
            }
            $key = $nr['d'] . '|' . strtoupper((string) $nr['ln']);
            if (($sev[$k] ?? 0) > ($sev[$acc[$s][$key] ?? ''] ?? 0)) {
                $acc[$s][$key] = $k;
            }
        }
        $rows = [];
        foreach ($acc as $s => $byKey) {
            $c = array_count_values($byKey);
            $rows[] = ['name' => $s, 'total' => count($byKey), 'pass' => $c['PASS'] ?? 0, 'tol' => $c['TOL'] ?? 0, 'fail' => $c['FAIL'] ?? 0];
        }

        // the two regular cement suppliers always get a row + pie, even with no tests in the period
        $have = array_column($rows, 'name');
        foreach (['NANPAO', 'ZHONGBU'] as $n) {
            if (! in_array($n, $have, true)) {
                $rows[] = ['name' => $n, 'total' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0];
            }
        }
        usort($rows, static fn ($a, $b) => strcmp($a['name'], $b['name']));

        return ['suppliers' => $rows, 'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => []];
    }

    /** Daily Bonding: Left/Right daily matrix, building & line rankings, cement supplier fails, and portion/root-cause breakdown. */
    public function bondingDaily($db): array
    {
        $pd   = self::BOND_DATE;
        $base = $this->bondingBaseWhere($pd);
        $rows = $db->query("SELECT LabRefNo, Side, Result, ModeOfFailure, PossibleCause, ProbableCause, Cause, AdhesiveSupplier, TestedDate, {$pd} AS prod_date
            FROM tbl_dtr_bonding_info
            WHERE {$base}
            ORDER BY prod_date ASC, BondingTestID ASC")->getResultArray();

        $lines = ['A' => range(1, 12), 'B' => range(1, 14)];
        $matrix = [];       // [prodDate][B][line][L|R] = 'PASS'|'FAIL'|'TOL'|'PEND'
        $testedDates = [];  // [prodDate] => [testedDate => count]
        $dayStats = [];     // [prodDate] => ['tested'=>0,'pass'=>0,'fail'=>0,'tol'=>0]
        $lineStats = [];    // [B][line] => ['pass'=>0,'fail'=>0,'tol'=>0,'tested'=>0,'shut'=>0]
        $bldgStats = [
            'A' => ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'shut' => 0],
            'B' => ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'shut' => 0],
        ];
        $overall = ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'pend' => 0, 'shut' => 0];

        $supMap = [
            'NANPAO'  => ['name' => 'Nan Pao',  'specimens' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0],
            'ZHONGBU' => ['name' => 'Zhong Bu', 'specimens' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0],
        ];

        $portionCounts = [
            'Upper to Foxing'   => 0,
            'Foxing to Outsole' => 0,
            'Filler to Outsole' => 0,
            'Unspecified'       => 0,
        ];
        $causeCounts = [];
        $causePortion = [];   // [CAUSE][portion] => times
        $noCause     = 0;   // failed shoes with no root cause recorded
        // a cause as the Failure Monitoring sheet names it: OVER DRIED / (C) OVER-DRIED CEMENT -> OVERDRIED CEMENT, POOR PRIMMER -> POOR PRIMER ...
        $causeName = static function (string $c): string {
            $c = strtoupper(trim(preg_replace('/^\([A-Z]\)\s*/', '', trim($c))));
            $c = trim(preg_replace('/\s+/', ' ', str_replace(['OVER-DRIED', 'OVER DRIED', 'PRIMMER', 'MATERAIL', 'CEMENTNG'], ['OVERDRIED', 'OVERDRIED', 'PRIMER', 'MATERIAL', 'CEMENTING'], $c)));
            // a mode-of-failure code written instead of a cause is shown as the whole word
            $codes = ['AF' => 'ADHESIVE FAILURE', 'CF' => 'COHESIVE FAILURE', 'PF' => 'PRIMER FAILURE', 'SF' => 'SUBSTRATE FAILURE', 'MF' => 'MATERIAL TEAR'];

            return $codes[$c] ?? ($c === 'OVERDRIED' ? 'OVERDRIED CEMENT' : $c);
        };

        $prio = ['FAIL' => 4, 'TOL' => 3, 'PASS' => 2, 'PEND' => 1];

        foreach ($rows as $r) {
            $d = substr((string) ($r['prod_date'] ?? ''), 0, 10);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                continue;
            }
            $resUp = strtoupper(trim((string) ($r['Result'] ?? '')));
            $st = match (true) {
                str_starts_with($resUp, 'FAIL') || $resUp === 'REJECTED' => 'FAIL',
                str_starts_with($resUp, 'PASS')                         => 'PASS',
                str_starts_with($resUp, 'TOL')                          => 'TOL',
                default                                                 => 'PEND',
            };

            $overall['tested']++;
            if ($st === 'PASS') {
                $overall['pass']++;
            } elseif ($st === 'FAIL') {
                $overall['fail']++;
            } elseif ($st === 'TOL') {
                $overall['tol']++;
            } else {
                $overall['pend']++;
            }

            if (! isset($dayStats[$d])) {
                $dayStats[$d] = ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0];
            }
            $dayStats[$d]['tested']++;
            if ($st === 'PASS') {
                $dayStats[$d]['pass']++;
            } elseif ($st === 'FAIL') {
                $dayStats[$d]['fail']++;
            } elseif ($st === 'TOL') {
                $dayStats[$d]['tol']++;
            }

            $td = substr(trim((string) ($r['TestedDate'] ?? '')), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $td)) {
                $testedDates[$d][$td] = ($testedDates[$d][$td] ?? 0) + 1;
            }

            // Building, Line & Side
            $ref = (string) ($r['LabRefNo'] ?? '');
            if (preg_match('/^DTP\d{8}[A-Z]{2}([A-Z])(\d+)/i', $ref, $m)) {
                $b = strtoupper($m[1]);
                $n = (int) $m[2];
                if (! isset($lines[$b])) {
                    $lines[$b] = [];
                }
                if (! in_array($n, $lines[$b], true)) {
                    $lines[$b][] = $n;
                }
                if (! isset($bldgStats[$b])) {
                    $bldgStats[$b] = ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'shut' => 0];
                }
                $bldgStats[$b]['tested']++;
                if ($st === 'PASS') {
                    $bldgStats[$b]['pass']++;
                } elseif ($st === 'FAIL') {
                    $bldgStats[$b]['fail']++;
                } elseif ($st === 'TOL') {
                    $bldgStats[$b]['tol']++;
                }

                if (! isset($lineStats[$b][$n])) {
                    $lineStats[$b][$n] = ['pass' => 0, 'fail' => 0, 'tol' => 0, 'tested' => 0, 'shut' => 0];
                }
                $lineStats[$b][$n]['tested']++;
                if ($st === 'PASS') {
                    $lineStats[$b][$n]['pass']++;
                } elseif ($st === 'FAIL') {
                    $lineStats[$b][$n]['fail']++;
                } elseif ($st === 'TOL') {
                    $lineStats[$b][$n]['tol']++;
                }

                $sideUp = strtoupper(trim((string) ($r['Side'] ?? '')));
                $side = str_starts_with($sideUp, 'L') ? 'L' : (str_starts_with($sideUp, 'R') ? 'R' : '');
                if ($side === '' && preg_match('/-(L|R)(?:-R?\d+)?$/i', $ref, $sm)) {
                    $side = strtoupper($sm[1]);
                }
                if ($side === 'L' || $side === 'R') {
                    $cur = $matrix[$d][$b][$n][$side] ?? '';
                    if ($cur === '' || ($prio[$st] ?? 0) > ($prio[$cur] ?? 0)) {
                        $matrix[$d][$b][$n][$side] = $st;
                    }
                }
            }

            // Cement supplier
            $supRaw = strtoupper(preg_replace('/\s+/', '', (string) ($r['AdhesiveSupplier'] ?? '')));
            if ($supRaw !== '') {
                $supKey = match (true) {
                    str_contains($supRaw, 'NAN')   => 'NANPAO',
                    str_contains($supRaw, 'ZHONG') => 'ZHONGBU',
                    default                        => $supRaw,
                };
                if (! isset($supMap[$supKey])) {
                    $supMap[$supKey] = ['name' => ucwords(strtolower(trim((string) $r['AdhesiveSupplier']))), 'specimens' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0];
                }
                $supMap[$supKey]['specimens']++;
                if ($st === 'PASS') {
                    $supMap[$supKey]['pass']++;
                } elseif ($st === 'FAIL') {
                    $supMap[$supKey]['fail']++;
                } elseif ($st === 'TOL') {
                    $supMap[$supKey]['tol']++;
                }
            }

            // Failure Portions & Root Causes (for FAILED specimens)
            if ($st === 'FAIL') {
                // cause x portion grid (one cell = how many times that cause failed that portion)
                $allTx = strtoupper(($r['ModeOfFailure'] ?? '') . ' | ' . ($r['Cause'] ?? '') . ' | ' . ($r['PossibleCause'] ?? '') . ' | ' . ($r['ProbableCause'] ?? ''));
                $pHit  = [];
                foreach (['Upper to Foxing', 'Foxing to Outsole', 'Filler to Outsole'] as $pn) {
                    if (str_contains($allTx, strtoupper($pn))) {
                        $pHit[] = $pn;
                    }
                }
                $pHit  = $pHit ?: ['Unspecified'];
                $cSrc  = trim((string) ($r['Cause'] ?? ''));
                if ($cSrc === '') {
                    foreach ([(string) ($r['PossibleCause'] ?? ''), (string) ($r['ProbableCause'] ?? '')] as $alt) {
                        $alt = trim($alt);
                        if ($alt !== '' && $alt !== '-') {
                            $cSrc = trim((string) (str_contains($alt, ':') ? substr($alt, strrpos($alt, ':') + 1) : $alt));
                            break;
                        }
                    }
                }
                foreach (preg_split('/\s*[\/,;]+\s*/', $cSrc) as $cItem) {
                    $cn = $causeName((string) $cItem);
                    if ($cn === '' || $cn === '-' || in_array($cn, ['UPPER TO FOXING', 'FOXING TO OUTSOLE', 'FILLER TO OUTSOLE'], true)) {
                        continue;
                    }
                    foreach ($pHit as $pn) {
                        $causePortion[$cn][$pn] = ($causePortion[$cn][$pn] ?? 0) + 1;
                    }
                }
                $rawFail = trim((string) ($r['ModeOfFailure'] ?: $r['PossibleCause'] ?: ''));
                $before  = array_sum($causeCounts);
                if ($rawFail === '') {
                    $portionCounts['Unspecified']++;
                    // no mode of failure, but the Cause column may name the reason
                    foreach (preg_split('/\s*[\/,;]+\s*/', trim((string) ($r['Cause'] ?? ''))) as $cItem) {
                        $cn = $causeName((string) $cItem);
                        if ($cn !== '' && $cn !== '-') {
                            $cl = ucwords(strtolower($cn));
                            $causeCounts[$cl] = ($causeCounts[$cl] ?? 0) + 1;
                        }
                    }
                } else {
                    $up = strtoupper($rawFail);
                    $hitPortion = false;
                    if (str_contains($up, 'UPPER TO FOXING')) {
                        $portionCounts['Upper to Foxing']++;
                        $hitPortion = true;
                    }
                    if (str_contains($up, 'FOXING TO OUTSOLE')) {
                        $portionCounts['Foxing to Outsole']++;
                        $hitPortion = true;
                    }
                    if (str_contains($up, 'FILLER TO OUTSOLE')) {
                        $portionCounts['Filler to Outsole']++;
                        $hitPortion = true;
                    }
                    if (! $hitPortion) {
                        $portionCounts['Unspecified']++;
                    }

                    $causePart = str_contains($rawFail, '-') ? trim((string) substr($rawFail, strpos($rawFail, '-') + 1)) : $rawFail;
                    foreach (preg_split('/\s*[\/,;]+\s*/', $causePart) as $cItem) {
                        $cClean = trim($cItem);
                        if ($cClean === '') {
                            continue;
                        }
                        $cUp = strtoupper($cClean);
                        // Skip pure portion names if no dash separated them
                        if (in_array($cUp, ['UPPER TO FOXING', 'FOXING TO OUTSOLE', 'FILLER TO OUTSOLE'], true)) {
                            continue;
                        }
                        $cNorm = $causeName($cUp);
                        $cLabel = ucwords(strtolower($cNorm));
                        $causeCounts[$cLabel] = ($causeCounts[$cLabel] ?? 0) + 1;
                    }
                }
                if (array_sum($causeCounts) === $before) {
                    $noCause++;
                }
            }
        }

        // Shutdowns from tbl_dtr_BondingTracker + SHUTDOWN_ROW in tbl_dtr_bonding_info
        $shut = [];
        if (($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) && $db->fieldExists('Shutdown', 'tbl_dtr_BondingTracker')) {
            $sw = 'Shutdown = 1' . (($this->from !== '' || $this->to !== '') ? ' AND ' . $this->dateWhere('ProductionDate') : '');
            foreach ($db->query("SELECT Building, `LineNo` AS ln, ProductionDate AS d FROM tbl_dtr_BondingTracker WHERE {$sw}")->getResultArray() as $sr) {
                $b = strtoupper(trim((string) $sr['Building']));
                $n = (int) preg_replace('/\D/', '', (string) $sr['ln']);
                $d = substr((string) $sr['d'], 0, 10);
                if ($b !== '' && $n > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $shut[$d][$b][$n] = true;
                    if (! isset($dayStats[$d])) {
                        $dayStats[$d] = ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0];
                    }
                    $overall['shut']++;
                    if (isset($bldgStats[$b])) {
                        $bldgStats[$b]['shut']++;
                    }
                    if (! isset($lineStats[$b][$n])) {
                        $lineStats[$b][$n] = ['pass' => 0, 'fail' => 0, 'tol' => 0, 'tested' => 0, 'shut' => 0];
                    }
                    $lineStats[$b][$n]['shut']++;
                }
            }
        }

        foreach ($lines as &$ln) {
            sort($ln);
        }
        unset($ln);
        ksort($lines);

        // the counts of this tab follow the Monthly Summary (Excel record before BACKUP_UNTIL, one tested line per day, hand marks): tested / passed / tolerable / failed, the
        // failed lines of each building and the tested / failed of each day; the Left / Right matrix and the cause breakdown keep the specimen detail
        try {
            $mm  = $this->bondingMonthly($db);
            $ms  = $mm['status'] ?? [];
            $overall['pass']   = (int) ($ms['pass'] ?? 0);
            $overall['tol']    = (int) ($ms['tol'] ?? 0);
            $overall['fail']   = (int) ($ms['fail'] ?? 0);
            $overall['tested'] = $overall['pass'] + $overall['tol'] + $overall['fail'];
            $overall['rate_base'] = 0;
            foreach ($mm['days'] ?? [] as $dm) {
                $overall['rate_base'] += (int) $dm['tested'];
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dm['d'])) {
                    $dayStats[$dm['d']] ??= ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0];
                    $dayStats[$dm['d']]['tested'] = (int) $dm['tested'];
                    $dayStats[$dm['d']]['fail']   = (int) ($dm['failed'] ?? 0);
                }
            }
            foreach (['A', 'B'] as $bb) {
                $bf = 0;
                foreach ($mm['cells'][$bb] ?? [] as $byDay) {
                    $bf += count(array_filter($byDay));
                }
                $bldgStats[$bb]['fail']   = $bf;
                $bldgStats[$bb]['tested'] = (int) ($mm['bldgTested'][$bb] ?? $bldgStats[$bb]['tested']);
            }
        } catch (\Throwable $e) {
            // the Monthly Summary figures are not available: the specimen counts of this tab stay as they are
        }

        // Daily matrix rows + weekly aggregates
        $allDates = array_keys($dayStats);
        sort($allDates);

        $weeklyAgg = [];
        foreach ($allDates as $d) {
            $wk = date('o-W', strtotime($d));
            if (! isset($weeklyAgg[$wk])) {
                $weeklyAgg[$wk] = ['failed' => 0, 'tested' => 0, 'lastDate' => $d];
            }
            $weeklyAgg[$wk]['failed'] += $dayStats[$d]['fail'];
            $weeklyAgg[$wk]['tested'] += $dayStats[$d]['tested'];
            $weeklyAgg[$wk]['lastDate'] = $d;
        }

        $dailyRows = [];
        foreach ($allDates as $d) {
            $ts = strtotime($d);
            $wk = date('o-W', $ts);
            $tdBest = '';
            if (! empty($testedDates[$d])) {
                arsort($testedDates[$d]);
                $tdBest = (string) array_key_first($testedDates[$d]);
            }
            $dailyRows[] = [
                'prodDate'     => $d,
                'testedDate'   => $tdBest,
                'dow'          => date('D', $ts),
                'weekKey'      => $wk,
                'isWeekEnd'    => ($weeklyAgg[$wk]['lastDate'] ?? '') === $d,
                'weeklyFailed' => $weeklyAgg[$wk]['failed'] ?? 0,
                'weeklyTested' => $weeklyAgg[$wk]['tested'] ?? 0,
                'tested'       => $dayStats[$d]['tested'],
                'pass'         => $dayStats[$d]['pass'],
                'fail'         => $dayStats[$d]['fail'],
                'tol'          => $dayStats[$d]['tol'],
                'cells'        => $matrix[$d] ?? [],
                'shut'         => $shut[$d] ?? [],
            ];
        }

        // Line Rankings per Building
        $rankings = [];
        foreach (['A', 'B'] as $b) {
            $list = [];
            foreach ($lines[$b] ?? [] as $n) {
                $ls = $lineStats[$b][$n] ?? ['pass' => 0, 'fail' => 0, 'tol' => 0, 'tested' => 0, 'shut' => 0];
                $t  = $ls['tested'];
                $score = $t > 0 ? round($ls['pass'] / $t * 100, 1) : null;
                $list[] = [
                    'line'    => $b . $n,
                    'num'     => $n,
                    'passed'  => $ls['pass'],
                    'failed'  => $ls['fail'],
                    'tol'     => $ls['tol'],
                    'tested'  => $t,
                    'shut'    => $ls['shut'],
                    'score'   => $score,
                    'failPct' => $t > 0 ? round($ls['fail'] / $t * 100, 1) : 0.0,
                ];
            }
            // Rank active lines (tested > 0): higher pass score, then more passed, then fewer failed
            $active = array_values(array_filter($list, static fn ($x) => $x['tested'] > 0));
            usort($active, static function ($x, $y) {
                if ($x['score'] !== $y['score']) {
                    return $y['score'] <=> $x['score'];
                }
                if ($x['failed'] !== $y['failed']) {
                    return $x['failed'] <=> $y['failed'];
                }
                return $y['passed'] <=> $x['passed'];
            });
            $rankMap = [];
            foreach ($active as $idx => $item) {
                $rankMap[$item['line']] = $idx + 1;
            }
            foreach ($list as &$item) {
                $item['rank'] = $rankMap[$item['line']] ?? null;
            }
            unset($item);

            $topLines = array_slice(array_values(array_filter($active, static fn ($x) => $x['failed'] === 0)), 0, 5);
            if (! $topLines && $active) {
                $topLines = array_slice($active, 0, 3);
            }
            $lowLines = array_values(array_filter($active, static fn ($x) => $x['failed'] > 0));
            usort($lowLines, static function ($x, $y) {
                if ($x['failed'] !== $y['failed']) {
                    return $y['failed'] <=> $x['failed'];
                }
                return $x['score'] <=> $y['score'];
            });

            $rankings[$b] = [
                'lines' => $list,
                'top'   => $topLines,
                'low'   => array_slice($lowLines, 0, 5),
            ];
        }

        arsort($causeCounts);
        $suppliers = array_values($supMap);
        foreach ($suppliers as &$sp) {
            $sp['failPct'] = $sp['specimens'] > 0 ? round($sp['fail'] / $sp['specimens'] * 100, 2) : 0.0;
        }
        unset($sp);

        return [
            'dailyRows'     => $dailyRows,
            'lines'         => $lines,
            'overall'       => $overall,
            'bldgStats'     => $bldgStats,
            'rankings'      => $rankings,
            'suppliers'     => $suppliers,
            'portionCounts' => $portionCounts,
            'causeCounts'   => $causeCounts,
            'noCause'       => $noCause,
            'causePortion'  => $causePortion,
            'kpis'          => [],
            'chartTitle'    => '',
            'months'        => [],
            'lists'         => [],
        ];
    }

    public function bonding($db): array
    {
        $pd = self::BOND_DATE;
        $w  = $this->bondingBaseWhere($pd);
        $wChart = $w . ' AND ' . $this->dateWhere($pd);
        $t  = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(UPPER(Result) LIKE 'PASS%'), 0) AS pass, COALESCE(SUM(UPPER(Result) LIKE 'TOL%'), 0) AS tol, COALESCE(SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED'), 0) AS fail FROM tbl_dtr_bonding_info WHERE {$w}")->getRowArray();
        $n  = max(1, (int) $t['total']);
        $b  = $this->bucket($pd);

        return [
            'kpis' => [
                ['Total Lab Test Reports', number_format((int) $t['total']), 'Bonding Shoe Collection', ''],
                ['Passed Reports', number_format((int) $t['pass']), $this->pct((int) $t['pass'], $n) . '% pass rate', 'an-pass'],
                ['Tolerable / Data', number_format((int) $t['tol']), $this->pct((int) $t['tol'], $n) . '% of tests', 'an-tol'],
                ['Failed Reports', number_format((int) $t['fail']), $this->pct((int) $t['fail'], $n) . '% fail rate', 'an-fail'],
            ],
            'chartTitle' => 'Tests per ' . $this->periodWord(),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COUNT(*) AS total, SUM(UPPER(Result) LIKE 'PASS%') AS pass, SUM(UPPER(Result) LIKE 'TOL%') AS tol, SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AS fail
                FROM tbl_dtr_bonding_info WHERE {$wChart} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Styles with the most failures', 'ratio', $db->query("SELECT ModelStyle AS name, SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AS a, COUNT(*) AS b FROM tbl_dtr_bonding_info
                    WHERE {$w} AND ModelStyle IS NOT NULL AND ModelStyle <> '' GROUP BY ModelStyle HAVING a > 0 ORDER BY a DESC, b DESC LIMIT 8")->getResultArray()],
                ['Technician workload (fails / tests)', 'ratio', $db->query("SELECT Technician AS name, SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AS a, COUNT(*) AS b FROM tbl_dtr_bonding_info
                    WHERE {$w} AND Technician IS NOT NULL AND Technician <> '' GROUP BY Technician ORDER BY b DESC LIMIT 8")->getResultArray()],
                ['Top modes of failure', 'count', $db->query("SELECT ModeOfFailure AS name, COUNT(*) AS a FROM tbl_dtr_bonding_info
                    WHERE {$w} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AND ModeOfFailure IS NOT NULL AND ModeOfFailure <> '' GROUP BY ModeOfFailure ORDER BY a DESC LIMIT 8")->getResultArray()],
            ],
        ];
    }

    /** The RIVETS RESULT of the 2026 HARDWARE workbook covers production days up to this date; the live Lab Tracker takes over the day after. */
    public const RIVETS_EXCEL_UNTIL = '2026-11-12';

    /**
     * Rivets Monitoring. Up to RIVETS_EXCEL_UNTIL: dbexcelbackup.tbl_RIVETS (one row per colorway and month with Pass / Tolerable / Failed / Total qty).
     * After it: live, from tbl_dtr_BondingTracker rows whose PullTestType is RIVET; the result of each one is read from tbl_dtr_pull_tests by LabRefNo
     * (a rivet with no result yet counts in the total only).
     */
    public function bondingPullTest($db): array
    {
        $rows = [];
        // 1) Excel record
        try {
            if ($this->from === '' || $this->from <= self::RIVETS_EXCEL_UNTIL) {
                $w    = 'month_start <= ?';
                $args = [self::RIVETS_EXCEL_UNTIL];
                // the table is monthly: a month is in the range when any day of it falls between From and To
                if ($this->from !== '') {
                    $w .= ' AND LAST_DAY(month_start) >= ?';
                    $args[] = $this->from;
                }
                if ($this->to !== '') {
                    $w .= ' AND month_start <= ?';
                    $args[] = $this->to;
                }
                $rows = $db->query("SELECT month_no, month_start, colorway, pass_qty, tolerable_qty, failed_qty, total_qty
                    FROM dbexcelbackup.tbl_RIVETS
                    WHERE {$w}
                    ORDER BY month_start ASC, id ASC", $args)->getResultArray();
            }
        } catch (\Throwable $e) {
            $rows = [];
        }
        // 2) Live Lab Tracker (after the Excel record): PullTestType = RIVET, result from tbl_dtr_pull_tests (by LabRefNo)
        try {
            $live = $db->query("SELECT MONTH(t.ProductionDate) AS month_no, DATE_FORMAT(t.ProductionDate, '%Y-%m-01') AS month_start, TRIM(COALESCE(t.Colorway, '')) AS colorway,
                    SUM(r.res LIKE 'PASS%') AS pass_qty, SUM(r.res LIKE 'TOL%') AS tolerable_qty, SUM(r.res LIKE 'FAIL%' OR r.res = 'REJECTED') AS failed_qty, COUNT(*) AS total_qty
                FROM tbl_dtr_BondingTracker t
                LEFT JOIN (SELECT LabRefNo, SUBSTRING_INDEX(GROUP_CONCAT(UPPER(TRIM(Result)) ORDER BY (COALESCE(Result, '') = '') ASC, PullInfoID DESC SEPARATOR '|'), '|', 1) AS res
                           FROM tbl_dtr_pull_tests GROUP BY LabRefNo) r ON r.LabRefNo = t.LabRefNo
                WHERE UPPER(TRIM(t.PullTestType)) = 'RIVET' AND t.ProductionDate > ? AND " . $this->dateWhere('t.ProductionDate') . "
                GROUP BY month_no, month_start, colorway
                ORDER BY month_start ASC, colorway ASC", [self::RIVETS_EXCEL_UNTIL])->getResultArray();
            $rows = array_merge($rows, $live);
        } catch (\Throwable $e) {
            // the live part is skipped when the Lab Tracker cannot be read
        }

        $yearSrc = $this->from !== '' ? $this->from : ($this->to !== '' ? $this->to : date('Y-m-d'));
        $year    = (int) substr($yearSrc, 0, 4);
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }
        if ($rows) {
            $year = (int) substr((string) $rows[0]['month_start'], 0, 4);
        }

        $classifyFamily = static function (string $cw): string {
            $u = strtoupper(trim($cw));
            if ($u === '') {
                return 'Unspecified';
            }
            foreach ([
                'PEARLIZED'     => 'Pearlized',
                'FLOWER PEARL'  => 'Pearlized',
                'SOUVENIR'      => 'Souvenir',
                'FLORAL RIVETS' => 'Floral Rivets',
                'HARRIS TWEED'  => 'Harris Tweed',
                'IBIZA'         => 'Ibiza',
                'SATOSHI'       => 'Satoshi',
                'COMBAT'        => 'Combat',
                'PINNED'        => 'Pinned',
                'ARCHIVE'       => 'Archive',
                'CHECKERBOARD'  => 'Checkerboard',
                'STARS'         => 'Stars',
                'CARDIGAN'      => 'Cardigan',
            ] as $needle => $fam) {
                if (str_contains($u, $needle)) {
                    return $fam;
                }
            }
            return 'Other';
        };

        $byMonth = [];
        $records = [];
        $totPass = $totTol = $totFail = $totBlank = $total = 0;

        foreach ($rows as $r) {
            $mNo = (int) $r['month_no'];
            if ($mNo < 1 || $mNo > 12) {
                continue;
            }
            $p = (int) $r['pass_qty'];
            $t = (int) $r['tolerable_qty'];
            $f = (int) $r['failed_qty'];
            $n = (int) $r['total_qty'];
            $b = max(0, $n - $p - $t - $f);   // pieces with no result
            $n = max($n, $p + $t + $f);

            $byMonth[$mNo] ??= [
                'monthNo' => $mNo,
                'short'   => date('M', mktime(0, 0, 0, $mNo, 1, $year)),
                'full'    => date('F', mktime(0, 0, 0, $mNo, 1, $year)),
                'pass'    => 0, 'tol' => 0, 'fail' => 0, 'blank' => 0, 'total' => 0,
            ];
            $byMonth[$mNo]['pass']  += $p;
            $byMonth[$mNo]['tol']   += $t;
            $byMonth[$mNo]['fail']  += $f;
            $byMonth[$mNo]['blank'] += $b;
            $byMonth[$mNo]['total'] += $n;
            $totPass += $p; $totTol += $t; $totFail += $f; $totBlank += $b; $total += $n;

            $cw = trim((string) ($r['colorway'] ?? ''));
            $records[] = [
                'm'   => $mNo,
                'cw'  => $cw !== '' ? $cw : 'Unspecified',
                'fam' => $classifyFamily($cw),
                'p'   => $p, 't' => $t, 'f' => $f, 'b' => $b, 'n' => $n,
            ];
        }

        ksort($byMonth);
        $monthly = [];
        foreach ($byMonth as $mo) {
            $mo['rated']   = $mo['pass'] + $mo['tol'] + $mo['fail'];
            $mo['passPct'] = $mo['total'] > 0 ? round($mo['pass'] / $mo['total'] * 100, 1) : 0.0;
            $mo['failPct'] = $mo['total'] > 0 ? round($mo['fail'] / $mo['total'] * 100, 1) : 0.0;
            $monthly[] = $mo;
        }

        $firstM = $monthly ? $monthly[0] : null;
        $lastM  = $monthly ? end($monthly) : null;
        if ($firstM && $lastM) {
            $periodText = ($firstM['short'] === $lastM['short'] ? $firstM['full'] : "{$firstM['short']} - {$lastM['short']}") . " {$year}";
        } else {
            $periodText = (string) $year;
        }

        // months with no sheet inside the span (the workbook has no April)
        $missingShort = [];
        if ($firstM && $lastM && $firstM['monthNo'] < $lastM['monthNo']) {
            for ($mi = $firstM['monthNo']; $mi <= $lastM['monthNo']; $mi++) {
                if (! isset($byMonth[$mi])) {
                    $missingShort[] = date('M', mktime(0, 0, 0, $mi, 1, $year));
                }
            }
        }

        return [
            'months'     => [],
            'kpis'       => [],
            'lists'      => [],
            'chartTitle' => '',
            'pullTest'   => [
                'year'        => $year,
                'periodText'  => $periodText,
                'missingNote' => $missingShort ? ('No ' . implode(', ', $missingShort)) : '',
                'total'       => $total,
                'pass'        => $totPass,
                'tol'         => $totTol,
                'fail'        => $totFail,
                'blank'       => $totBlank,
                'passPct'     => $total > 0 ? round($totPass / $total * 100, 1) : 0.0,
                'tolPct'      => $total > 0 ? round($totTol / $total * 100, 1) : 0.0,
                'failPct'     => $total > 0 ? round($totFail / $total * 100, 1) : 0.0,
                'monthly'     => $monthly,
                'records'     => $records,
            ],
        ];
    }
}
