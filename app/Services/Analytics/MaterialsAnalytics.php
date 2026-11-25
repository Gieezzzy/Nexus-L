<?php

namespace App\Services\Analytics;

/** Materials: material test analytics and audit. */
class MaterialsAnalytics extends AnalyticsBase
{
    public function materials($db): array
    {
        try {
            if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_materialtesting'")->getNumRows() > 0) {
                return $this->materialsAudit($db);
            }
        } catch (\Throwable $e) {
            // fall back to unified_labrefs below
        }

        $ud = self::UNIFIED_DATE;
        $w  = "form_slug NOT IN ('bonding', 'wholeshoe', 'wst', 'pulltest') AND " . $this->dateWhere($ud);
        $t  = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(overall_result IN ('Pass','P')), 0) AS pass, COALESCE(SUM(overall_result = 'Tolerable'), 0) AS tol, COALESCE(SUM(overall_result = 'Fail'), 0) AS fail FROM tbl_dtr_unified_labrefs WHERE {$w}")->getRowArray();
        $n  = max(1, (int) $t['total']);
        $b  = $this->bucket($ud);

        $passPct = $this->pct((int) $t['pass'], $n);
        $tolPct  = $this->pct((int) $t['tol'], $n);
        $failPct = $this->pct((int) $t['fail'], $n);

        return [
            'kpis' => [
                ['Total Material Tests', number_format((int) $t['total']), 'All material test forms in period', '', ''],
                ['Passed Material Tests', number_format((int) $t['pass']), $passPct . '% pass rate · Meets specification', 'an-pass', $passPct . '%'],
                ['Tolerable / Data', number_format((int) $t['tol']), $tolPct . '% of tests · Within tolerance band', 'an-tol', $tolPct . '%'],
                ['Failed Material Tests', number_format((int) $t['fail']), $failPct . '% fail rate · Requires action', 'an-fail', $failPct . '%'],
            ],
            'summaryDonut' => [
                'title'  => 'Material Test Results Breakdown',
                'center' => number_format((int) $t['total']),
                'sub'    => 'TESTS',
                'parts'  => [
                    ['Passed',    (int) $t['pass'], '#10b981'],
                    ['Tolerable', (int) $t['tol'],  '#f59e0b'],
                    ['Failed',    (int) $t['fail'], '#ef4444'],
                ],
            ],
            'chartTitle' => 'Material Tests per ' . ucfirst($this->periodWord()),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COUNT(*) AS total, COALESCE(SUM(overall_result IN ('Pass','P')), 0) AS pass, COALESCE(SUM(overall_result = 'Tolerable'), 0) AS tol, COALESCE(SUM(overall_result = 'Fail'), 0) AS fail
                FROM tbl_dtr_unified_labrefs WHERE {$w} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Material Categories (Fails / Total Tests)', 'ratio', $db->query("SELECT COALESCE(NULLIF(TRIM(form_category),''), UPPER(form_slug)) AS name, COALESCE(SUM(overall_result = 'Fail'), 0) AS a, COUNT(*) AS b FROM tbl_dtr_unified_labrefs WHERE {$w} GROUP BY name ORDER BY b DESC LIMIT 8")->getResultArray(), 'Failure count against total tests per material category'],
                ['Material Suppliers (Fails / Total Tests)', 'ratio', $db->query("SELECT supplier AS name, COALESCE(SUM(overall_result = 'Fail'), 0) AS a, COUNT(*) AS b FROM tbl_dtr_unified_labrefs WHERE {$w} AND supplier IS NOT NULL AND TRIM(supplier) <> '' GROUP BY supplier ORDER BY b DESC LIMIT 8")->getResultArray(), 'Supplier test volume and failure ratio'],
                ['Lab Technicians (Tests Completed)', 'count', $db->query("SELECT technician AS name, COUNT(*) AS a FROM tbl_dtr_unified_labrefs WHERE {$w} AND technician IS NOT NULL AND TRIM(technician) <> '' GROUP BY technician ORDER BY a DESC LIMIT 8")->getResultArray(), 'Material test volume completed per technician'],
            ],
        ];
    }

    /** Material Test Analytics: Pass / Tolerable / Fail executive summary from dbexcelbackup.tbl_materialtesting (Jan–Nov). */
    public function materialsAudit($db): array
    {
        $yearSrc = $this->from !== '' ? $this->from : ($this->to !== '' ? $this->to : date('Y-m-d'));
        $year    = (int) substr($yearSrc, 0, 4);
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }

        $effDate = "COALESCE(NULLIF(test_date, ''), CASE WHEN lab_no REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(lab_no, 4, 4), '-', SUBSTR(lab_no, 8, 2), '-', SUBSTR(lab_no, 10, 2)) END)";
        $where   = "sheet_kind = 'DATA' AND month_no BETWEEN 1 AND 11 AND (UPPER(COALESCE(remarks,'')) LIKE 'PASS%' OR UPPER(COALESCE(remarks,'')) LIKE 'TOL%' OR UPPER(COALESCE(remarks,'')) LIKE 'FAIL%')";
        $isFullMonthRange = false;

        if ($this->from !== '' && $this->to !== '') {
            $yFrom = (int) substr($this->from, 0, 4);
            $yTo   = (int) substr($this->to, 0, 4);
            $mFrom = (int) substr($this->from, 5, 2);
            $mTo   = (int) substr($this->to, 5, 2);
            $dFrom = substr($this->from, 8, 2);
            $lastD = date('t', strtotime($this->to));
            $dTo   = substr($this->to, 8, 2);
            if ($yFrom === $yTo && $dFrom === '01' && $dTo === $lastD) {
                $isFullMonthRange = true;
                $where .= ' AND month_no BETWEEN ' . $mFrom . ' AND ' . $mTo . ' AND (' . $effDate . ' IS NULL OR LEFT(' . $effDate . ', 4) = ' . $db->escape((string) $yFrom) . ')';
            } else {
                $where .= ' AND ' . $effDate . ' BETWEEN ' . $db->escape($this->from) . ' AND ' . $db->escape($this->to);
            }
        } elseif ($this->from !== '' || $this->to !== '') {
            $dCond = [];
            if ($this->from !== '') {
                $dCond[] = $effDate . ' >= ' . $db->escape($this->from);
            }
            if ($this->to !== '') {
                $dCond[] = $effDate . ' <= ' . $db->escape($this->to);
            }
            $where .= ' AND ' . implode(' AND ', $dCond);
        }

        $rows = $db->query("SELECT month_no, {$effDate} AS eff_date, material_description, supplier, lab_no, remarks, tests_json
            FROM dbexcelbackup.tbl_materialtesting WHERE {$where} ORDER BY month_no ASC, id ASC")->getResultArray();

        $totPass = $totTol = $totFail = $retestCnt = 0;
        $byMonth = [];
        $byParam = [];
        $bySup   = [];
        $failLog = [];
        $tolLog  = [];

        foreach ($rows as $r) {
            $m  = (int) $r['month_no'];
            $rm = strtoupper(trim((string) ($r['remarks'] ?? '')));
            if (str_contains($rm, 'RETEST')) {
                $retestCnt++;
            }
            $bucket = str_starts_with($rm, 'PASS') ? 'pass' : (str_starts_with($rm, 'TOL') ? 'tol' : 'fail');

            if (! isset($byMonth[$m])) {
                $byMonth[$m] = [
                    'monthNo' => $m,
                    'short'   => date('M', mktime(0, 0, 0, $m, 1, $year)),
                    'full'    => date('F', mktime(0, 0, 0, $m, 1, $year)),
                    'pass'    => 0,
                    'tol'     => 0,
                    'fail'    => 0,
                    'total'   => 0,
                ];
            }
            $byMonth[$m][$bucket]++;
            $byMonth[$m]['total']++;

            if ($bucket === 'pass') {
                $totPass++;
            } elseif ($bucket === 'tol') {
                $totTol++;
            } else {
                $totFail++;
            }

            $sup = trim((string) ($r['supplier'] ?? ''));
            if ($sup !== '') {
                $bySup[$sup] ??= ['name' => $sup, 'pass' => 0, 'tol' => 0, 'fail' => 0, 'total' => 0];
                $bySup[$sup][$bucket]++;
                $bySup[$sup]['total']++;
            }

            $flaggedTests = [];
            $tj = json_decode((string) ($r['tests_json'] ?? ''), true);
            if (is_array($tj)) {
                foreach ($tj as $tk => $tv) {
                    $tvu = strtoupper(trim((string) $tv));
                    if ($tvu === 'P' || $tvu === 'PASS') {
                        $byParam[$tk] ??= ['name' => $tk, 'pass' => 0, 'tol' => 0, 'fail' => 0, 'total' => 0];
                        $byParam[$tk]['pass']++;
                        $byParam[$tk]['total']++;
                    } elseif ($tvu === 'T' || str_starts_with($tvu, 'TOL')) {
                        $byParam[$tk] ??= ['name' => $tk, 'pass' => 0, 'tol' => 0, 'fail' => 0, 'total' => 0];
                        $byParam[$tk]['tol']++;
                        $byParam[$tk]['total']++;
                        if ($bucket === 'tol') {
                            $flaggedTests[] = $tk . ' (T)';
                        }
                    } elseif ($tvu === 'F' || str_starts_with($tvu, 'F ') || str_starts_with($tvu, 'F(') || str_starts_with($tvu, 'FAIL')) {
                        $byParam[$tk] ??= ['name' => $tk, 'pass' => 0, 'tol' => 0, 'fail' => 0, 'total' => 0];
                        $byParam[$tk]['fail']++;
                        $byParam[$tk]['total']++;
                        $flaggedTests[] = $tk . ' (F)';
                    }
                }
            }

            if ($bucket === 'fail' || $bucket === 'tol') {
                $entry = [
                    'date'     => (string) ($r['eff_date'] ?? ''),
                    'month'    => $byMonth[$m]['short'] . ' ' . $year,
                    'labNo'    => trim((string) ($r['lab_no'] ?? '')),
                    'supplier' => $sup,
                    'material' => trim((string) ($r['material_description'] ?? '')),
                    'tests'    => $flaggedTests,
                    'remark'   => trim((string) ($r['remarks'] ?? '')),
                ];
                if ($bucket === 'fail') {
                    $failLog[] = $entry;
                } else {
                    $tolLog[] = $entry;
                }
            }
        }

        ksort($byMonth);
        $monthly = [];
        $bestMonth = null;
        $peakMonth = null;
        foreach ($byMonth as $mo) {
            $n = max(1, $mo['total']);
            $mo['passPct'] = round($mo['pass'] / $n * 100, 1);
            $mo['tolPct']  = round($mo['tol'] / $n * 100, 1);
            $mo['failPct'] = round($mo['fail'] / $n * 100, 1);
            $monthly[] = $mo;
            if ($bestMonth === null || $mo['passPct'] > $bestMonth['passPct'] || ($mo['passPct'] === $bestMonth['passPct'] && $mo['total'] > $bestMonth['total'])) {
                $bestMonth = $mo;
            }
            if ($peakMonth === null || $mo['fail'] > $peakMonth['fail'] || ($mo['fail'] === $peakMonth['fail'] && $mo['failPct'] > $peakMonth['failPct'])) {
                $peakMonth = $mo;
            }
        }

        // Top 8 test parameters with Tolerable / Failed occurrences (then volume)
        $params = array_values(array_filter($byParam, static fn ($p) => ($p['tol'] + $p['fail']) > 0));
        usort($params, static fn ($a, $b) => ($b['fail'] * 3 + $b['tol']) <=> ($a['fail'] * 3 + $a['tol']) ?: $b['total'] <=> $a['total']);
        $params = array_slice($params, 0, 8);

        // Top 8 suppliers with Tolerable / Failed batches (fallback to highest volume if none)
        $suppliers = array_values(array_filter($bySup, static fn ($s) => ($s['tol'] + $s['fail']) > 0));
        if (! $suppliers) {
            $suppliers = array_values($bySup);
        }
        usort($suppliers, static fn ($a, $b) => ($b['fail'] * 3 + $b['tol']) <=> ($a['fail'] * 3 + $a['tol']) ?: $b['total'] <=> $a['total']);
        $suppliers = array_slice($suppliers, 0, 8);

        // Sort logs newest first
        usort($failLog, static fn ($a, $b) => strcmp($b['date'], $a['date']));
        usort($tolLog, static fn ($a, $b) => strcmp($b['date'], $a['date']));

        $tot    = $totPass + $totTol + $totFail;
        $firstM = $monthly ? $monthly[0] : null;
        $lastM  = $monthly ? end($monthly) : null;
        $nMoCnt = count($monthly);
        if ($this->from !== '' || $this->to !== '') {
            if ($isFullMonthRange && $firstM && $lastM && $firstM['monthNo'] === $lastM['monthNo']) {
                $periodText    = "{$firstM['full']} {$year}";
                $monthSpanText = "{$firstM['full']} {$year}";
            } elseif ($this->from !== '' && $this->from === $this->to) {
                $ts            = strtotime($this->from);
                $periodText    = $ts ? date('F j, Y', $ts) : $this->from;
                $monthSpanText = $ts ? date('M j, Y', $ts) : $this->from;
            } elseif ($this->from !== '' && $this->to !== '') {
                $tsF = strtotime($this->from);
                $tsT = strtotime($this->to);
                if ($tsF && $tsT) {
                    $sameYr        = date('Y', $tsF) === date('Y', $tsT);
                    $periodText    = ($sameYr ? date('M j', $tsF) : date('M j, Y', $tsF)) . ' - ' . date('M j, Y', $tsT);
                    $monthSpanText = $periodText;
                } else {
                    $periodText    = $this->from . ' - ' . $this->to;
                    $monthSpanText = $this->from . ' to ' . $this->to;
                }
            } else {
                $periodText    = ($this->from ?: 'Start') . ' - ' . ($this->to ?: 'Present');
                $monthSpanText = ($this->from ?: 'Start') . ' to ' . ($this->to ?: 'Present');
            }
        } elseif ($firstM && $lastM) {
            $periodText    = ($firstM['full'] === $lastM['full'] ? $firstM['full'] : "{$firstM['full']} - {$lastM['full']}") . " {$year}";
            $monthSpanText = ($firstM['short'] === $lastM['short'] ? $firstM['short'] : "{$firstM['short']} - {$lastM['short']}") . " {$year} ({$nMoCnt} Month" . ($nMoCnt === 1 ? '' : 's') . ')';
        } else {
            $periodText    = (string) $year;
            $monthSpanText = (string) $year;
        }

        // Whole-year 12-month pie data (Jan..Dec) for the Yearly Pie Summary view (independent of single-month filter)
        $yrPiesMap = [];
        for ($mNo = 1; $mNo <= 12; $mNo++) {
            $yrPiesMap[$mNo] = [
                'month' => $mNo,
                'name'  => date('F', mktime(0, 0, 0, $mNo, 1, $year)),
                'pass'  => 0,
                'fail'  => 0,
                'tol'   => 0,
                'total' => 0,
            ];
        }
        $yrSql = "SELECT month_no,
                SUM(UPPER(COALESCE(remarks,'')) LIKE 'PASS%') AS p,
                SUM(UPPER(COALESCE(remarks,'')) LIKE 'FAIL%') AS f,
                SUM(UPPER(COALESCE(remarks,'')) LIKE 'TOL%') AS t
            FROM dbexcelbackup.tbl_materialtesting
            WHERE sheet_kind = 'DATA' AND month_no BETWEEN 1 AND 12
              AND (UPPER(COALESCE(remarks,'')) LIKE 'PASS%' OR UPPER(COALESCE(remarks,'')) LIKE 'TOL%' OR UPPER(COALESCE(remarks,'')) LIKE 'FAIL%')
              AND ({$effDate} IS NULL OR LEFT({$effDate}, 4) = " . $db->escape((string) $year) . ")
            GROUP BY month_no";
        foreach ($db->query($yrSql)->getResultArray() as $yrRow) {
            $mNo = (int) $yrRow['month_no'];
            if (! isset($yrPiesMap[$mNo])) {
                continue;
            }
            $p = (int) $yrRow['p'];
            $f = (int) $yrRow['f'];
            $t = (int) $yrRow['t'];
            $yrPiesMap[$mNo]['pass']  = $p;
            $yrPiesMap[$mNo]['fail']  = $f;
            $yrPiesMap[$mNo]['tol']   = $t;
            $yrPiesMap[$mNo]['total'] = $p + $f + $t;
        }

        return [
            'kpis'       => [],
            'months'     => [],
            'lists'      => [],
            'chartTitle' => '',
            'yearlyPies' => array_values($yrPiesMap),
            'yearlyYear' => $year,
            'matAudit'   => [
                'year'          => $year,
                'periodText'    => $periodText,
                'monthSpanText' => $monthSpanText,
                'total'         => $tot,
                'pass'          => $totPass,
                'tol'           => $totTol,
                'fail'          => $totFail,
                'passPct'       => $tot > 0 ? round($totPass / $tot * 100, 1) : 0.0,
                'tolPct'        => $tot > 0 ? round($totTol / $tot * 100, 1) : 0.0,
                'failPct'       => $tot > 0 ? round($totFail / $tot * 100, 1) : 0.0,
                'retestCount'   => $retestCnt,
                'monthly'       => $monthly,
                'params'        => $params,
                'suppliers'     => $suppliers,
                'failLog'       => $failLog,
                'tolLog'        => $tolLog,
                'bestMonth'     => $bestMonth,
                'peakMonth'     => $peakMonth,
            ],
        ];
    }
}
