<?php

namespace App\Services\Analytics;

/** Whole Shoe: audit presentation from dbexcelbackup.tbl_wholeshoetest, falling back to the tracker / intake tables. */
class WholeShoeAnalytics extends AnalyticsBase
{
    /** Whole Shoe Analytics: executive audit presentation from dbexcelbackup.tbl_wholeshoetest when present, falling back to tbl_dtr_WholeshoeTracker / intake. */
    public function wholeShoe($db): array
    {
        try {
            if ($db->query("SHOW TABLES FROM dbexcelbackup LIKE 'tbl_wholeshoetest'")->getNumRows() > 0) {
                return $this->wholeShoeAudit($db);
            }
        } catch (\Throwable $e) {
            // fall back below
        }

        return $db->tableExists('tbl_dtr_WholeshoeTracker') ? $this->wholeShoeTracker($db) : $this->wholeShoeIntake($db);
    }

    /** Whole Shoe Physical Lab Audit — Visual Executive Presentation powered by dbexcelbackup.tbl_wholeshoetest. */
    public function wholeShoeAudit($db): array
    {
        $yearSrc = $this->from !== '' ? $this->from : ($this->to !== '' ? $this->to : date('Y-m-d'));
        $year    = (int) substr($yearSrc, 0, 4);
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }

        $where = "sheet_kind = 'DATA' AND month_no BETWEEN 1 AND 12 AND (COALESCE(TRIM(style),'') <> '' OR COALESCE(TRIM(mtl),'') <> '' OR COALESCE(TRIM(lab_no),'') <> '')";
        $effDate = "COALESCE(NULLIF(test_date, ''), CASE WHEN lab_no REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(lab_no, 4, 4), '-', SUBSTR(lab_no, 8, 2), '-', SUBSTR(lab_no, 10, 2)) END)";
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
                // Exact calendar month(s) selected: match by month_no (and year check via dated rows)
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

        $rows = $db->query("SELECT month_no, {$effDate} AS eff_date, style, mtl, lab_no, tr_no, remark, bonding, flexing, aging, hydrolysis, uv_light, washing
            FROM dbexcelbackup.tbl_wholeshoetest WHERE {$where} ORDER BY month_no ASC, id ASC")->getResultArray();

        // Whole Shoe physical test methods (excludes Pull Strength)
        $methodDefs = [
            'aging'      => 'Aging Test',
            'uv_light'   => 'UV Light',
            'flexing'    => 'Flexing Test',
            'hydrolysis' => 'Hydrolysis',
            'washing'    => 'Washing Test',
            'bonding'    => 'Bonding',
        ];
        $mStats = [];
        foreach ($methodDefs as $col => $lbl) {
            $mStats[$col] = ['key' => $col, 'label' => $lbl, 'pass' => 0, 'tol' => 0, 'failPure' => 0, 'failRetest' => 0, 'failTotal' => 0, 'n' => 0];
        }

        $byMonth = [];
        $byStyle = [];
        $records = [];
        $mMonth  = [];   // [test column][month_no] => pass / tol / fail / retest counts of that physical test
        $totPass = $totTol = $totFail = $totPend = 0;
        $trCount = $labCount = $blankRemarkCount = 0;

        foreach ($rows as $r) {
            $m = (int) $r['month_no'];
            if (! isset($byMonth[$m])) {
                $byMonth[$m] = [
                    'monthNo' => $m,
                    'short'   => date('M', mktime(0, 0, 0, $m, 1, $year)),
                    'full'    => date('F', mktime(0, 0, 0, $m, 1, $year)),
                    'pass'    => 0,
                    'tol'     => 0,
                    'fail'    => 0,
                    'pend'    => 0,
                    'total'   => 0,
                ];
            }
            $byMonth[$m]['total']++;
            $rm = strtoupper(trim((string) ($r['remark'] ?? '')));
            if ($rm === '') {
                // Remark not filled yet (TR pending): read the overall result from the test cells.
                $cells = [];
                foreach (['bonding', 'flexing', 'aging', 'hydrolysis', 'uv_light', 'washing'] as $cc) {
                    $cells[] = strtoupper(trim((string) ($r[$cc] ?? '')));
                }
                if (array_intersect($cells, ['F', 'FAIL', 'FAILED'])) {
                    $rm = 'FAILED';
                } elseif (array_filter($cells, static fn ($v) => $v === 'T' || str_starts_with($v, 'TOL'))) {
                    $rm = 'TOLERABLE';
                } elseif (array_filter($cells, static fn ($v) => $v === 'P' || $v === 'PASS' || $v === 'F (RETEST - PASS)')) {
                    $rm = 'PASS';
                }
            }
            if ($rm === 'PASS' || str_starts_with($rm, 'PASS')) {
                $byMonth[$m]['pass']++;
                $totPass++;
                $statusKey = 'pass';
            } elseif (str_starts_with($rm, 'TOL')) {
                $byMonth[$m]['tol']++;
                $totTol++;
                $statusKey = 'tol';
            } elseif (str_starts_with($rm, 'FAIL') || $rm === 'REJECTED') {
                $byMonth[$m]['fail']++;
                $totFail++;
                $statusKey = 'fail';
            } else {
                $byMonth[$m]['pend']++;
                $totPend++;
                $statusKey = 'pend';
            }

            if (trim((string) ($r['tr_no'] ?? '')) !== '') {
                $trCount++;
            }
            if (trim((string) ($r['lab_no'] ?? '')) !== '') {
                $labCount++;
            }
            if (trim((string) ($r['remark'] ?? '')) === '') {
                $blankRemarkCount++;
            }

            $records[] = [
                'monthNo'    => $m,
                'monthName'  => $byMonth[$m]['full'],
                'date'       => (string) ($r['eff_date'] ?? ''),
                'style'      => trim((string) ($r['style'] ?? '')),
                'mtl'        => trim((string) ($r['mtl'] ?? '')),
                'labNo'      => trim((string) ($r['lab_no'] ?? '')),
                'trNo'       => trim((string) ($r['tr_no'] ?? '')),
                'status'     => $statusKey,
                'remark'     => trim((string) ($r['remark'] ?? '')),
                'bonding'    => trim((string) ($r['bonding'] ?? '')),
                'flexing'    => trim((string) ($r['flexing'] ?? '')),
                'aging'      => trim((string) ($r['aging'] ?? '')),
                'hydrolysis' => trim((string) ($r['hydrolysis'] ?? '')),
                'uv_light'   => trim((string) ($r['uv_light'] ?? '')),
                'washing'    => trim((string) ($r['washing'] ?? '')),
            ];

            $styleKey = strtoupper(trim((string) ($r['style'] ?? '')));
            if ($styleKey !== '') {
                $byStyle[$styleKey] ??= ['style' => $styleKey, 'n' => 0, 'totalFails' => 0, 'bonding' => 0, 'washing' => 0, 'flexing' => 0, 'hydrolysis' => 0];
                $byStyle[$styleKey]['n']++;
            }

            foreach ($methodDefs as $col => $lbl) {
                $v = strtoupper(trim((string) ($r[$col] ?? '')));
                if ($v === '') {
                    continue;
                }
                $mmK = ($v === 'P' || $v === 'PASS') ? 'pass' : (($v === 'T' || str_starts_with($v, 'TOL')) ? 'tol' : (str_starts_with($v, 'F') ? 'fail' : ''));
                if ($mmK !== '') {
                    $mMonth[$col][$m] ??= ['pass' => 0, 'tol' => 0, 'fail' => 0, 'retest' => 0];
                    $mMonth[$col][$m][$mmK]++;
                    if ($mmK === 'fail' && (str_contains($v, 'RETEST') || str_contains($v, 'PASS'))) {
                        $mMonth[$col][$m]['retest']++;
                    }
                }
                if ($v === 'P' || $v === 'PASS') {
                    $mStats[$col]['pass']++;
                } elseif ($v === 'T' || str_starts_with($v, 'TOL')) {
                    $mStats[$col]['tol']++;
                } elseif ($v === 'F' || $v === 'FAIL' || $v === 'FAILED') {
                    $mStats[$col]['failPure']++;
                    $mStats[$col]['failTotal']++;
                    if ($styleKey !== '' && isset($byStyle[$styleKey][$col])) {
                        $byStyle[$styleKey][$col]++;
                        $byStyle[$styleKey]['totalFails']++;
                    }
                } elseif (str_starts_with($v, 'F') && (str_contains($v, 'RETEST') || str_contains($v, 'PASS'))) {
                    $mStats[$col]['failRetest']++;
                    $mStats[$col]['failTotal']++;
                    if ($styleKey !== '' && isset($byStyle[$styleKey][$col])) {
                        $byStyle[$styleKey][$col]++;
                        $byStyle[$styleKey]['totalFails']++;
                    }
                }
            }
        }
        ksort($byMonth);
        $monthly = array_values($byMonth);

        // Compute method percentages & sort highest pass % to lowest
        $methods = [];
        foreach ($mStats as $st) {
            $n = $st['pass'] + $st['tol'] + $st['failTotal'];
            if ($n <= 0) {
                continue;
            }
            $st['n']           = $n;
            $st['passPct']     = round($st['pass'] / $n * 100, 1);
            $st['tolPct']      = round($st['tol'] / $n * 100, 1);
            $st['failPct']     = round($st['failTotal'] / $n * 100, 1);
            $st['recoveryPct'] = $st['failTotal'] > 0 ? (int) round($st['failRetest'] / $st['failTotal'] * 100) : 0;
            $methods[]         = $st;
        }
        usort($methods, static fn ($a, $b) => $b['passPct'] <=> $a['passPct'] ?: $a['failTotal'] <=> $b['failTotal']);

        // Retest recovery across failing Whole Shoe physical test methods
        $retestMethods = array_values(array_filter($methods, static fn ($m) => $m['failTotal'] > 0));
        usort($retestMethods, static fn ($a, $b) => $b['failTotal'] <=> $a['failTotal']);

        // Top 10 failing shoe silhouettes (Whole Shoe physical test failures only)
        $silhouettes = array_values(array_filter($byStyle, static fn ($s) => $s['totalFails'] > 0));
        usort($silhouettes, static fn ($a, $b) => $b['totalFails'] <=> $a['totalFails'] ?: $b['n'] <=> $a['n']);
        $silhouettes = array_slice($silhouettes, 0, 10);

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

        $totSamples = count($rows);
        $ratedTot   = $totPass + $totTol + $totFail;

        // Whole-year 12-month pie data (Jan..Dec) for Yearly Pie Summary toggle
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
        // Overall result = Remark when filled; while Remark is still blank (TR not issued yet) it is read from the test cells:
        // any F = FAILED, else any T = TOLERABLE, else any P = PASS (a retested "F (RETEST - PASS)" counts as pass).
        $rk  = "UPPER(TRIM(COALESCE(remark,'')))";
        $cat = "UPPER(CONCAT_WS('|', TRIM(bonding), TRIM(flexing), TRIM(aging), TRIM(hydrolysis), TRIM(uv_light), TRIM(washing)))";
        $dF  = "{$cat} REGEXP '(^|[|])(F|FAIL|FAILED)([|]|\$)'";
        $dT  = "{$cat} REGEXP '(^|[|])(T|TOL[A-Z]*)([|]|\$)'";
        $dP  = "{$cat} REGEXP '(^|[|])(P|PASS|F \\\\(RETEST - PASS\\\\))([|]|\$)'";
        $yrSql = "SELECT month_no,
                SUM({$rk} LIKE 'PASS%' OR ({$rk} = '' AND NOT ({$dF}) AND NOT ({$dT}) AND ({$dP}))) AS p,
                SUM({$rk} LIKE 'FAIL%' OR {$rk} = 'REJECTED' OR ({$rk} = '' AND ({$dF}))) AS f,
                SUM({$rk} LIKE 'TOL%' OR ({$rk} = '' AND NOT ({$dF}) AND ({$dT}))) AS t
            FROM dbexcelbackup.tbl_wholeshoetest
            WHERE sheet_kind = 'DATA' AND month_no BETWEEN 1 AND 12
              AND (COALESCE(TRIM(style),'') <> '' OR COALESCE(TRIM(mtl),'') <> '' OR COALESCE(TRIM(lab_no),'') <> '')
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
            'wsAudit'    => [
                'year'          => $year,
                'periodText'    => $periodText,
                'monthSpanText' => $monthSpanText,
                'totalSamples'  => $totSamples,
                'ratedTotal'    => $ratedTot,
                'passTotal'     => $totPass,
                'tolTotal'      => $totTol,
                'failTotal'     => $totFail,
                'pendTotal'     => $totPend,
                'passPct'       => $ratedTot > 0 ? round($totPass / $ratedTot * 100, 1) : 0.0,
                'tolPct'        => $ratedTot > 0 ? round($totTol / $ratedTot * 100, 1) : 0.0,
                'failPct'       => $ratedTot > 0 ? round($totFail / $ratedTot * 100, 1) : 0.0,
                'methodCount'   => count($methodDefs),
                'monthly'       => $monthly,
                'methods'       => $methods,
                'silhouettes'   => $silhouettes,
                'retestMethods' => $retestMethods,
                'methodLabels'  => $methodDefs,
                'methodMonthly' => $mMonth,
                'auditMeta'     => [
                    'trCount'          => $trCount,
                    'labCount'         => $labCount,
                    'blankRemarkCount' => $blankRemarkCount,
                    'trPct'            => $totSamples > 0 ? round($trCount / $totSamples * 100, 1) : 0.0,
                    'labPct'           => $totSamples > 0 ? round($labCount / $totSamples * 100, 1) : 0.0,
                ],
                'records'       => array_reverse($records),
            ],
        ];
    }

    public function wholeShoeTracker($db): array
    {
        $w    = $this->dateWhere('CollectionDate');
        $b    = $this->bucket('CollectionDate');
        $fr   = "UPPER(COALESCE(FinalResult, ''))";
        $pass = "({$fr} LIKE 'PASS%' OR {$fr} LIKE 'NO WATER%')";
        $tol  = "({$fr} LIKE 'TOL%')";
        $fail = "({$fr} LIKE 'FAIL%')";
        $pend = "(UPPER(COALESCE(Status, '')) IN ('PENDING', 'RUNNING', 'PAUSED', '') AND NOT ({$pass} OR {$tol} OR {$fail}))";
        $mom  = "COALESCE(NULLIF(TRIM(WholeshoeLabRefNo), ''), LabRefNo)";
        $tt   = "CASE WHEN TestType IN ('UV Light', 'UV') THEN 'UV' WHEN TestType LIKE 'Dip%' THEN 'Dip Test' WHEN COALESCE(TestType, '') = '' THEN 'Other' ELSE TestType END";

        $t = $db->query("SELECT COUNT(*) AS tests, COUNT(DISTINCT {$mom}) AS shoes, COALESCE(SUM({$pass}), 0) AS pass, COALESCE(SUM({$tol}), 0) AS tol, COALESCE(SUM({$fail}), 0) AS fail, COALESCE(SUM({$pend}), 0) AS pend
            FROM tbl_dtr_WholeshoeTracker WHERE {$w}")->getRowArray();
        $tests = (int) $t['tests'];
        $pass  = (int) $t['pass'];
        $tol   = (int) $t['tol'];
        $fail  = (int) $t['fail'];
        $pend  = (int) $t['pend'];
        $fin   = $pass + $tol + $fail;

        $rows = static fn (array $r): array => $r;

        return [
            'kpis' => [
                ['Whole Shoes Collected', number_format((int) $t['shoes']), number_format($tests) . ' test Lab Refs created', '', ''],
                ['Tests Finished', number_format($fin), $pend . ' still pending / running', '', $this->pct($fin, max(1, $tests)) . '% Done'],
                ['Passed Tests', number_format($pass), $this->pct($pass, max(1, $fin)) . '% pass rate of finished', 'an-pass', ''],
                ['Failed Tests', number_format($fail), $tol . ' tolerable · ' . $this->pct($fail, max(1, $fin)) . '% fail rate', 'an-fail', ''],
            ],
            'summaryDonut' => [
                'title'  => 'Whole Shoe Test Results',
                'center' => number_format($tests),
                'sub'    => 'TESTS',
                'parts'  => [
                    ['Passed', $pass, '#10b981'],
                    ['Tolerable', $tol, '#f59e0b'],
                    ['Failed', $fail, '#ef4444'],
                    ['Pending / Running', $pend, '#94a3b8'],
                ],
            ],
            'chartTitle' => 'Whole Shoe Tests Finished per ' . ucfirst($this->periodWord()),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COALESCE(SUM({$pass} OR {$tol} OR {$fail}), 0) AS total, COALESCE(SUM({$pass}), 0) AS pass, COALESCE(SUM({$tol}), 0) AS tol, COALESCE(SUM({$fail}), 0) AS fail
                FROM tbl_dtr_WholeshoeTracker WHERE {$w} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Failure Rate per Test Type (fails / finished)', 'ratio', $rows($db->query("SELECT {$tt} AS name, COALESCE(SUM({$fail}), 0) AS a, SUM({$pass} OR {$tol} OR {$fail}) AS b
                    FROM tbl_dtr_WholeshoeTracker WHERE {$w} GROUP BY name HAVING b > 0 ORDER BY b DESC LIMIT 8")->getResultArray()), 'Washing, Flexing, Heat Aging, Hydrolysis, UV ... ranked by volume'],
                ['Styles with the most failures', 'ratio', $rows($db->query("SELECT Style AS name, SUM({$fail}) AS a, SUM({$pass} OR {$tol} OR {$fail}) AS b
                    FROM tbl_dtr_WholeshoeTracker WHERE {$w} AND COALESCE(Style, '') <> '' GROUP BY Style HAVING a > 0 ORDER BY a DESC, b DESC LIMIT 8")->getResultArray()), 'Shoe styles with failed whole shoe tests'],
                ['Lines with the most failures', 'ratio', $rows($db->query("SELECT CONCAT('Line ', `LineNo`) AS name, SUM({$fail}) AS a, SUM({$pass} OR {$tol} OR {$fail}) AS b
                    FROM tbl_dtr_WholeshoeTracker WHERE {$w} AND COALESCE(`LineNo`, '') <> '' GROUP BY `LineNo` HAVING a > 0 ORDER BY a DESC, b DESC LIMIT 8")->getResultArray()), 'Production lines the failed shoes came from'],
                ['Pending / Running by Test Type', 'count', $rows($db->query("SELECT {$tt} AS name, SUM({$pend}) AS a FROM tbl_dtr_WholeshoeTracker WHERE {$w} GROUP BY name HAVING a > 0 ORDER BY a DESC LIMIT 8")->getResultArray()), 'Backlog: tests not finished yet'],
                ['Shoes Collected By', 'count', $rows($db->query("SELECT EncodedBy AS name, COUNT(DISTINCT {$mom}) AS a FROM tbl_dtr_WholeshoeTracker WHERE {$w} AND COALESCE(EncodedBy, '') <> '' GROUP BY EncodedBy ORDER BY a DESC LIMIT 8")->getResultArray()), 'Lab staff who encoded the whole shoe collection'],
            ],
        ];
    }

    public function wholeShoeIntake($db): array
    {
        if (! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return ['kpis' => [['Whole Shoe Lines', '0', 'nothing saved yet', '']], 'chartTitle' => 'Whole shoes collected per ' . $this->periodWord(), 'months' => [], 'lists' => [], 'empty' => 'No whole shoe intake saved yet. Use + Whole Shoe Collected on the Lab Tracker.'];
        }
        $w = $this->dateWhere('CollectionDate');
        $t = $db->query("SELECT COUNT(*) AS lines_, COALESCE(SUM(NoOfShoes), 0) AS shoes, COALESCE(SUM(Building = 'A'), 0) AS a, COALESCE(SUM(Building = 'B'), 0) AS b FROM tbl_dtr_wholeshoe_intake WHERE {$w}")->getRowArray();
        $b = $this->bucket('CollectionDate');

        $uWhere = "form_slug IN ('wholeshoe', 'wst') AND " . $this->dateWhere(self::UNIFIED_DATE);
        $uRow   = $db->tableExists('tbl_dtr_unified_labrefs')
            ? $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(overall_result IN ('Pass','P')), 0) AS pass, COALESCE(SUM(overall_result = 'Tolerable'), 0) AS tol, COALESCE(SUM(overall_result = 'Fail'), 0) AS fail FROM tbl_dtr_unified_labrefs WHERE {$uWhere}")->getRowArray()
            : ['total' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0];
        $repTot = (int) ($uRow['total'] ?? 0);
        if ($repTot === 0 && $db->tableExists('tbl_dtr_wholeshoe_reports')) {
            $repTot = (int) $db->table('tbl_dtr_wholeshoe_reports')->countAllResults();
        }
        $repPassPct = $repTot > 0 ? $this->pct((int) ($uRow['pass'] ?? 0), $repTot) : 0.0;

        // each ticked test type counts once per line
        $tests = [];
        foreach ($db->query("SELECT TestTypes FROM tbl_dtr_wholeshoe_intake WHERE {$w} AND TestTypes <> ''")->getResultArray() as $r) {
            foreach (array_filter(array_map('trim', explode(',', (string) $r['TestTypes']))) as $x) {
                $tests[$x] = ($tests[$x] ?? 0) + 1;
            }
        }
        arsort($tests);
        $testRows = [];
        foreach ($tests as $k => $v) {
            $testRows[] = ['name' => $k, 'a' => $v];
        }

        $bA = (int) $t['a'];
        $bB = (int) $t['b'];
        $bTot = max(1, $bA + $bB);

        return [
            'kpis' => [
                ['Whole Shoe Lines', number_format((int) $t['lines_']), 'Lines collected at intake', '', ''],
                ['Shoes Collected', number_format((int) $t['shoes']), 'Total No. of Shoes submitted', '', ''],
                ['Building A / B Split', $bA . ' / ' . $bB, $this->pct($bA, $bTot) . '% Bldg A · ' . $this->pct($bB, $bTot) . '% Bldg B', '', $this->pct($bA, $bTot) . '% A'],
                ['Test Reports Completed', number_format($repTot), ((int) ($uRow['pass'] ?? 0)) . ' passed · ' . ((int) ($uRow['fail'] ?? 0)) . ' failed', 'an-pass', $repTot > 0 ? $repPassPct . '% Pass' : ''],
            ],
            'summaryDonut' => [
                'title'  => 'Building A vs. Building B Intake',
                'center' => number_format((int) $t['lines_']),
                'sub'    => 'LINES',
                'parts'  => [
                    ['Building A', $bA, '#2563eb'],
                    ['Building B', $bB, '#f97316'],
                ],
            ],
            'chartTitle' => 'Whole Shoes Collected per ' . ucfirst($this->periodWord()),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COALESCE(SUM(NoOfShoes), 0) AS total FROM tbl_dtr_wholeshoe_intake
                WHERE {$w} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Tests Requested Most', 'count', $testRows, 'Most frequent whole-shoe test procedures ticked at intake'],
                ['Top Collected Shoe Styles', 'count', $db->query("SELECT s.StyleNo AS name, COALESCE(SUM(i.NoOfShoes), 0) AS a FROM tbl_dtr_wholeshoe_intake i JOIN tbl_dtr_shoe_list s ON s.ProdCode = i.ProdCode
                    WHERE " . $this->dateWhere('i.CollectionDate') . " AND s.StyleNo <> '' GROUP BY s.StyleNo ORDER BY a DESC LIMIT 8")->getResultArray(), 'Shoe styles with the highest volume of collected specimens'],
                ['Intake Encoded By (Technicians)', 'count', $db->query("SELECT EncodedBy AS name, COUNT(*) AS a FROM tbl_dtr_wholeshoe_intake WHERE {$w} AND EncodedBy <> '' GROUP BY EncodedBy ORDER BY a DESC LIMIT 8")->getResultArray(), 'Lab staff recording whole-shoe intake lines'],
            ],
        ];
    }
}
