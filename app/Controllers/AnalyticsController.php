<?php

namespace App\Controllers;

/**
 * Analytics, one page per area (?tab=): bonding (Shoe Bonding U60), wholeshoe (Whole Shoe intake) and materials (Material Tests).
 * Each tab hands the view the same shape: kpis, a monthly series and ranked lists.
 */
class AnalyticsController extends BaseController
{
    private string $group = 'month';
    private string $from = '';
    private string $to = '';

    private array $svcCache = [];

    /** The Analytics area service (bonding | wholeshoe | materials | daily), given the page's current date range. */
    private function svc(string $key): \App\Services\Analytics\AnalyticsBase
    {
        $map = [
            'bonding'   => \App\Services\Analytics\BondingAnalytics::class,
            'wholeshoe' => \App\Services\Analytics\WholeShoeAnalytics::class,
            'materials' => \App\Services\Analytics\MaterialsAnalytics::class,
            'daily'     => \App\Services\Analytics\DailyWorkloadAnalytics::class,
        ];
        $s = $this->svcCache[$key] ??= new $map[$key]();
        $s->setRange($this->from, $this->to, $this->group);
        if ($key === 'daily') {
            $s->request = $this->request;
        }

        return $s;
    }

    public function index(): string
    {
        $rawTab = (string) ($this->request->getGet('tab') ?? 'bonding');
        $tab    = in_array($rawTab, ['bonding', 'wholeshoe', 'materials'], true) ? $rawTab : 'bonding';
        $v      = (string) ($this->request->getGet('view') ?? '');
        if ($rawTab === 'daily' && $v === '') {
            $v = 'workload';
        }
        $db = \Config\Database::connect();

        $allowedViews = match ($tab) {
            'bonding'   => ['workload', 'failure', 'retest', 'daily', 'pulltest', 'monthly', 'result', 'utm'],
            'wholeshoe' => ['analytics', 'monthfail'],
            'materials' => ['workload', 'analytics', 'hotspots', 'batches'],
        };
        $defaultView = match ($tab) {
            'bonding'   => 'monthly',
            'wholeshoe' => 'analytics',
            'materials' => 'analytics',
        };
        $view = in_array($v, $allowedViews, true) ? $v : $defaultView;

        if (in_array($view, ['workload', 'encoding'], true)) {
            $wk = \App\Libraries\DataCache::remember('analytics_daily_' . md5(json_encode($this->request->getGet()) . date('Y-m-d')), 60, fn () => $this->svc('daily')->dailyWorks($db));   // 60 s: a new entry shows within a minute

            return $this->render('analytics/daily', ['title' => 'Analytics'] + $wk + ['tab' => $tab, 'view' => $view]);
        }

        // period filter: group = day | week | month | year (bucket size of the chart), from / to = custom date range (YYYY-MM-DD)
        $g = (string) ($this->request->getGet('group') ?? 'month');
        $this->group = in_array($g, ['day', 'week', 'month', 'year'], true) ? $g : 'month';
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from === '' && $this->to === '') {
            if ($tab === 'wholeshoe' && $view === 'analytics' && $this->request->getGet('all') === null) {
                // Whole Shoe Monthly Summary opens on the current month; the calendar filter (or "All dates") changes it
                $this->from  = date('Y-m-01');
                $this->to    = date('Y-m-t');
                $this->group = 'month';
            } elseif ($this->request->getGet('all') !== null || ($tab === 'bonding' && $view === 'pulltest') || ($tab === 'wholeshoe' && in_array($view, ['analytics', 'breakdown', 'monthfail'], true)) || ($tab === 'materials' && in_array($view, ['analytics', 'hotspots', 'batches'], true))) {
                $this->group = 'month';   // All dates (picked explicitly) or Pull Test / Whole Shoe / Material Test Executive Presentation is charted by month
            } else {
                // default view: the current month, by day
                $this->from  = date('Y-m-01');
                $this->to    = date('Y-m-t');
                $this->group = 'day';
            }
        }
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }

        $cacheKey = 'analytics_' . md5("{$tab}|{$view}|{$this->group}|{$this->from}|{$this->to}");
        $data = \App\Libraries\DataCache::remember($cacheKey, $view === 'utm' ? 1 : 120, fn () => match (true) {   // the machine tab is live: kept 1 second only
            $tab === 'wholeshoe' => $this->svc('wholeshoe')->wholeShoe($db),
            $tab === 'materials' => $this->svc('materials')->materials($db),
            $view === 'result'   => $this->svc('bonding')->bondingResult($db),
            $view === 'monthly'  => $this->svc('bonding')->bondingMonthly($db),
            $view === 'daily'    => $this->svc('bonding')->bondingDaily($db),
            $view === 'pulltest' => $this->svc('bonding')->bondingPullTest($db),
            $view === 'failure'  => $this->svc('bonding')->bondingFailure($db),
            $view === 'retest'   => $this->svc('bonding')->bondingRetest($db),
            $view === 'utm'      => $this->svc('bonding')->bondingUtm($db),
            default              => $this->svc('bonding')->bonding($db),
        });

        // the failure overview carries no chart data; defaults also cover a cached copy made before they existed
        return $this->render('analytics/index', ['title' => 'Analytics'] + $data + (in_array($view, ['failure', 'retest', 'utm'], true) ? ['months' => [], 'kpis' => [], 'lists' => [], 'chartTitle' => ''] : []) + ['tab' => $tab, 'view' => $view, 'group' => $this->group, 'from' => $this->from, 'to' => $this->to]);
    }

    /**
     * GET analytics/monthly-report?tab=bonding|wholeshoe|materials&year=YYYY : print-ready 12-month Monthly Summary Report
     * (whole-year pie charts + STATUS/COUNT tables per month, ready to print or save as PDF).
     */
    public function monthlyReport(): string
    {
        $rawTab = (string) ($this->request->getGet('tab') ?? 'bonding');
        $tab    = in_array($rawTab, ['bonding', 'wholeshoe', 'materials'], true) ? $rawTab : 'bonding';
        $yrStr  = (string) ($this->request->getGet('year') ?? '');
        if (! preg_match('/^20\d{2}$/', $yrStr)) {
            $m = (string) ($this->request->getGet('month') ?? '');
            $yrStr = preg_match('/^(20\d{2})/', $m, $mm) ? $mm[1] : date('Y');
        }
        $year = (int) $yrStr;
        $db   = \Config\Database::connect();
        $pies = $this->yearlyPiesForArea($db, $tab, $year);

        return view('analytics/monthly_report', [
            'tab'        => $tab,
            'yearlyYear' => $year,
            'yearlyPies' => $pies,
            'printedBy'  => (string) ($this->auth->user()['name'] ?? ''),
        ]);
    }

    /** Shared 12-month (Jan..Dec) pie dataset for Shoe Bonding, Whole Shoe, or Materials. */
    private function yearlyPiesForArea($db, string $tab, int $year): array
    {
        [$sf, $st] = [$this->from, $this->to];
        $this->from = '';
        $this->to   = '';
        try {
            if ($tab === 'wholeshoe') {
                $this->from = sprintf('%04d-01-01', $year);
                $this->to   = sprintf('%04d-12-31', $year);
                $pies = $this->svc('wholeshoe')->wholeShoeAudit($db)['yearlyPies'] ?? [];
            } elseif ($tab === 'materials') {
                $this->from = sprintf('%04d-01-01', $year);
                $this->to   = sprintf('%04d-12-31', $year);
                $pies = $this->svc('materials')->materialsAudit($db)['yearlyPies'] ?? [];
            } else {
                $pies = $this->svc('bonding')->bondingYearlySummary($db, $year);
            }
        } finally {
            [$this->from, $this->to] = [$sf, $st];
        }

        return $pies;
    }

    /** GET analytics/export-yearly-pies?tab=bonding|wholeshoe|materials&year=YYYY : 12-month Pie Charts + Summary Tables as Excel (.xlsx). */
    public function exportYearlyPies()
    {
        $rawTab = (string) ($this->request->getGet('tab') ?? 'bonding');
        $tab    = in_array($rawTab, ['bonding', 'wholeshoe', 'materials'], true) ? $rawTab : 'bonding';
        $yrStr  = (string) ($this->request->getGet('year') ?? '');
        if (! preg_match('/^20\d{2}$/', $yrStr)) {
            $from = (string) ($this->request->getGet('from') ?? '');
            $yrStr = preg_match('/^(20\d{2})/', $from, $mm) ? $mm[1] : date('Y');
        }
        $year = (int) $yrStr;
        $db   = \Config\Database::connect();
        $pies = $this->yearlyPiesForArea($db, $tab, $year);

        $areaTitle = match ($tab) {
            'wholeshoe' => 'Whole Shoe',
            'materials' => 'Materials',
            default     => 'Shoe Bonding',
        };
        $filePrefix = match ($tab) {
            'wholeshoe' => 'WholeShoe',
            'materials' => 'Materials',
            default     => 'ShoeBonding',
        };

        $yrPass = $yrFail = $yrTol = $yrTot = 0;
        foreach ($pies as $mp) {
            $yrPass += (int) ($mp['pass'] ?? 0);
            $yrFail += (int) ($mp['fail'] ?? 0);
            $yrTol  += (int) ($mp['tol'] ?? 0);
            $yrTot  += (int) ($mp['total'] ?? 0);
        }

        $x      = new \App\Libraries\XlsxBuilder('Monthly Summary');
        $navy   = '0F2A47';
        $sTitle = $x->style(['bold' => true, 'size' => 14, 'color' => 'FFFFFF', 'fill' => $navy, 'h' => 'center']);
        $sSub   = $x->style(['bold' => true, 'size' => 10, 'color' => $navy, 'fill' => 'DCE9F8', 'h' => 'center']);
        $sShL   = $x->style(['bold' => true, 'size' => 9, 'color' => '334155', 'fill' => 'F1F5F9', 'border' => true, 'h' => 'left']);
        $sShR   = $x->style(['bold' => true, 'size' => 9, 'color' => '334155', 'fill' => 'F1F5F9', 'border' => true, 'h' => 'right']);
        $sTxtL  = $x->style(['bold' => true, 'size' => 10, 'color' => '0F172A', 'border' => true, 'h' => 'left']);
        $sNumR  = $x->style(['bold' => true, 'size' => 10, 'color' => '0F172A', 'border' => true, 'h' => 'right']);
        $sTotL  = $x->style(['bold' => true, 'size' => 10, 'color' => '0F172A', 'fill' => 'F8FAFC', 'border' => true, 'h' => 'left']);
        $sTotR  = $x->style(['bold' => true, 'size' => 10, 'color' => '0F172A', 'fill' => 'F8FAFC', 'border' => true, 'h' => 'right']);

        $hdrHex = [
            1 => '7E22CE', 2 => 'B91C1C', 3 => '15803D', 4 => '1D4ED8',
            5 => 'B45309', 6 => '0F766E', 7 => '4338CA', 8 => 'BE123C',
            9 => '047857', 10 => 'C2410C', 11 => '0369A1', 12 => '334155',
        ];

        // 3 cards across: Cols 1-4, spacer 5, Cols 6-9, spacer 10, Cols 11-14
        foreach ([1 => 14, 2 => 10, 3 => 10, 4 => 12, 5 => 3, 6 => 14, 7 => 10, 8 => 10, 9 => 12, 10 => 3, 11 => 14, 12 => 10, 13 => 10, 14 => 12] as $col => $w) {
            $x->width($col, $w);
        }
        $x->merge(1, 1, 1, 14, strtoupper("{$year} {$areaTitle} Monthly Summary Report"), $sTitle);
        $x->height(1, 28);
        $pPct = $yrTot > 0 ? round($yrPass / $yrTot * 100, 1) : 0;
        $fPct = $yrTot > 0 ? round($yrFail / $yrTot * 100, 1) : 0;
        $tPct = $yrTot > 0 ? round($yrTol / $yrTot * 100, 1) : 0;
        $x->merge(2, 1, 2, 14, "PASS: {$yrPass} ({$pPct}%)   |   FAILED: {$yrFail} ({$fPct}%)   |   TOLERABLE: {$yrTol} ({$tPct}%)   |   TOTAL: {$yrTot}", $sSub);
        $x->height(2, 20);

        $pieColors = ['3B82F6', 'F97316', '94A3B8'];
        foreach ($pies as $idx => $mp) {
            $mNo   = (int) ($mp['month'] ?? ($idx + 1));
            $mName = (string) ($mp['name'] ?? date('F', mktime(0, 0, 0, $mNo, 1, $year)));
            $pCnt  = (int) ($mp['pass'] ?? 0);
            $fCnt  = (int) ($mp['fail'] ?? 0);
            $tCnt  = (int) ($mp['tol'] ?? 0);
            $totC  = (int) ($mp['total'] ?? ($pCnt + $fCnt + $tCnt));

            $gridCol = $idx % 3;
            $gridRow = intdiv($idx, 3);
            $c0      = $gridCol * 5 + 1;          // 1, 6, 11
            $rChart  = 4 + $gridRow * 21;         // 4, 25, 46, 67
            $rTbl    = $rChart + 14;              // 18, 39, 60, 81

            $mStyle = $x->style(['bold' => true, 'size' => 10, 'color' => 'FFFFFF', 'fill' => $hdrHex[$mNo] ?? $navy, 'border' => true, 'h' => 'center']);
            $x->merge($rTbl, $c0, $rTbl, $c0 + 3, strtoupper($mName), $mStyle);
            $x->height($rTbl, 20);
            $x->merge($rTbl + 1, $c0, $rTbl + 1, $c0 + 2, 'STATUS', $sShL);
            $x->set($rTbl + 1, $c0 + 3, 'COUNT', $sShR);
            $x->merge($rTbl + 2, $c0, $rTbl + 2, $c0 + 2, 'PASS', $sTxtL);
            $x->set($rTbl + 2, $c0 + 3, $pCnt, $sNumR);
            $x->merge($rTbl + 3, $c0, $rTbl + 3, $c0 + 2, 'FAILED', $sTxtL);
            $x->set($rTbl + 3, $c0 + 3, $fCnt, $sNumR);
            $x->merge($rTbl + 4, $c0, $rTbl + 4, $c0 + 2, 'TOLERABLE', $sTxtL);
            $x->set($rTbl + 4, $c0 + 3, $tCnt, $sNumR);
            $x->merge($rTbl + 5, $c0, $rTbl + 5, $c0 + 2, 'Total:', $sTotL);
            $x->set($rTbl + 5, $c0 + 3, $totC, $sTotR);

            if ($totC > 0) {
                $catCol = \App\Libraries\XlsxBuilder::col($c0);
                $valCol = \App\Libraries\XlsxBuilder::col($c0 + 3);
                $x->chart([
                    'type'   => 'pie',
                    'dark'   => false,
                    'anchor' => [$c0 - 1, $rChart - 1, $c0 + 3, $rTbl - 1],
                    'title'  => $mName . ' Summary',
                    'name'   => $mName,
                    'cat'    => $catCol . ($rTbl + 2) . ':' . $catCol . ($rTbl + 4),
                    'val'    => $valCol . ($rTbl + 2) . ':' . $valCol . ($rTbl + 4),
                    'colors' => $pieColors,
                ]);
            }
        }

        $name = "{$filePrefix}_Monthly_Summary_Report_{$year}.xlsx";

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($x->build());
    }

    /** Pass / tolerable / fail totals of one calendar month for each Analytics area, plus the few things that need attention. Never throws. */
    private function monthSummary($db, string $ym): array
    {
        $this->from  = $ym . '-01';
        $this->to    = date('Y-m-t', strtotime($this->from));
        $this->group = 'day';
        $cache = static fn (string $k, callable $fn) => \App\Libraries\DataCache::remember('analytics_mr_' . $k . '_' . md5($ym), 120, $fn);
        $out   = ['bonding' => null, 'wholeshoe' => null, 'materials' => null];
        $mk    = static function (int $pass, int $tol, int $fail): array {
            $rated = $pass + $tol + $fail;

            return ['pass' => $pass, 'tol' => $tol, 'fail' => $fail, 'rated' => $rated, 'passPct' => $rated > 0 ? round($pass / $rated * 100, 1) : null];
        };

        try {
            $b  = $cache('bonding', fn () => $this->svc('bonding')->bondingMonthly($db));
            $st = $b['status'] ?? [];
            $out['bonding'] = $mk((int) ($st['pass'] ?? 0), (int) ($st['tol'] ?? 0), (int) ($st['fail'] ?? 0)) + ['shut' => (int) ($st['shut'] ?? 0)];
            $f  = $cache('failure', fn () => $this->svc('bonding')->bondingFailure($db))['fb'] ?? [];
            $top = static function (array $a): ?array {
                arsort($a);
                $k = array_key_first($a);

                return ($k !== null && (int) $a[$k] > 0) ? ['name' => (string) $k, 'n' => (int) $a[$k]] : null;
            };
            $out['bonding']['topBuilding'] = $top($f['bld'] ?? []);
            $out['bonding']['topPortion']  = $top($f['por'] ?? []);
            $out['bonding']['topCause']    = $top($f['rea'] ?? []);
        } catch (\Throwable $e) {
            log_message('error', 'monthly-report bonding: ' . $e->getMessage());
        }

        try {
            $w = $cache('wholeshoe', fn () => $this->svc('wholeshoe')->wholeShoe($db))['wsAudit'] ?? null;
            if ($w) {
                $out['wholeshoe'] = $mk((int) $w['passTotal'], (int) $w['tolTotal'], (int) $w['failTotal']) + ['tested' => (int) $w['totalSamples'], 'pending' => (int) $w['pendTotal']];
                $ms = array_values(array_filter($w['methods'] ?? [], static fn ($x) => (int) ($x['failTotal'] ?? 0) > 0));
                usort($ms, static fn ($a, $b) => ($b['failPct'] ?? 0) <=> ($a['failPct'] ?? 0));
                $out['wholeshoe']['worst'] = array_slice(array_map(static fn ($x) => ['name' => (string) $x['label'], 'fail' => (int) $x['failTotal'], 'failPct' => (float) $x['failPct']], $ms), 0, 3);
            }
        } catch (\Throwable $e) {
            log_message('error', 'monthly-report wholeshoe: ' . $e->getMessage());
        }

        try {
            $a = $cache('materials', fn () => $this->svc('materials')->materials($db))['matAudit'] ?? null;
            if ($a) {
                $out['materials'] = $mk((int) $a['pass'], (int) $a['tol'], (int) $a['fail']);
                $out['materials']['suppliers'] = array_slice(array_map(static fn ($s) => ['name' => (string) $s['name'], 'fail' => (int) $s['fail'], 'tol' => (int) $s['tol'], 'total' => (int) $s['total']], array_filter($a['suppliers'] ?? [], static fn ($s) => ((int) $s['fail'] + (int) $s['tol']) > 0)), 0, 3);
                $out['materials']['retest'] = (int) ($a['retestCount'] ?? 0);
            }
        } catch (\Throwable $e) {
            log_message('error', 'monthly-report materials: ' . $e->getMessage());
        }

        return $out;
    }

    /** GET analytics/export-monthly?from=&to= : the Monthly Summary of the chosen dates as an Excel file (supplier performance, weekly trend chart, failed per line per week, failure categories). */
    public function exportMonthly()
    {
        $db = \Config\Database::connect();
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from === '' && $this->to === '' && $this->request->getGet('all') === null) {
            $this->from = date('Y-m-01');
            $this->to   = date('Y-m-t');
        }
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
        $m     = $this->svc('bonding')->bondingMonthly($db);
        $days  = $m['days'];
        $dayD  = array_column($days, 'd');
        $first = $this->from !== '' ? $this->from : (date('Y-m-01', strtotime($dayD[0] ?? 'now')));
        $last  = $this->to !== '' ? $this->to : (date('Y-m-t', strtotime($first)));
        $dates = [];
        for ($t = strtotime($first); $t <= strtotime($last) && count($dates) < 400; $t = strtotime('+1 day', $t)) {
            $dates[] = date('Y-m-d', $t);
        }

        // the same numbers as the grid on the page: counted failed per line / date, hand marks and typed totals included
        $lines = $m['lines'];
        $cells = $m['cells'];
        $totF  = array_fill_keys($dates, 0);
        $totT  = array_fill_keys($dates, 0);
        foreach ($days as $d) {
            if (isset($totT[$d['d']])) { $totT[$d['d']] = (int) $d['tested']; }
        }
        $bTot = [];
        foreach ($lines as $bld => $nums) {
            $bTot[$bld] = array_fill_keys($dates, 0);
            foreach ($nums as $nn) {
                foreach ($dates as $d) {
                    $v = (int) ($cells[$bld][$nn][$d] ?? 0);
                    $bTot[$bld][$d] += $v;
                    $totF[$d]       += $v;
                }
            }
        }
        $ovr = $m['ovr'] ?? ['B' => [], 'F' => [], 'T' => []];
        foreach ($bTot as $bk => &$arr) {
            foreach ($dates as $d) { if (isset($ovr['B'][$bk][$d])) { $arr[$d] = $ovr['B'][$bk][$d]; } }
        }
        unset($arr);
        foreach ($dates as $d) {
            if (isset($ovr['F'][$d])) { $totF[$d] = $ovr['F'][$d]; }
            if (isset($ovr['T'][$d])) { $totT[$d] = $ovr['T'][$d]; }
        }

        // weeks (Monday to Sunday) inside the dates
        $weeks = [];
        foreach ($dates as $d) {
            $wk = date('o-W', strtotime($d));
            $weeks[$wk][] = $d;
        }
        $weeks = array_values($weeks);
        $wLabel = static function (array $ds): string {
            $a = $ds[0]; $b = end($ds);
            $s = date('m/d', strtotime($a)) . '–' . (date('m', strtotime($a)) === date('m', strtotime($b)) ? date('d', strtotime($b)) : date('m/d', strtotime($b)));
            return $a === $b ? date('m/d', strtotime($a)) : $s;
        };
        $W = max(1, count($weeks));

        // supplier performance and failure categories
        $pd   = \App\Services\Analytics\AnalyticsBase::BOND_DATE;
        $base = $this->svc('bonding')->bondingBaseWhere($pd) . ' AND ' . $this->svc('bonding')->dateWhere($pd);
        $sup  = [];
        foreach ($db->query("SELECT CASE WHEN UPPER(TRIM(COALESCE(AdhesiveSupplier,''))) REGEXP '^(NANPAO|NNAPAO|NP)' THEN 'NANPAO' WHEN UPPER(TRIM(COALESCE(AdhesiveSupplier,''))) LIKE 'ZHONGBU%' THEN 'ZHONGBU' ELSE 'NOT RECORDED' END AS s,
                COALESCE(SUM(UPPER(Result) LIKE 'PASS%'),0) AS pass, COALESCE(SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED'),0) AS fail, COALESCE(SUM(UPPER(Result) LIKE 'TOL%'),0) AS tol
            FROM tbl_dtr_bonding_info WHERE {$base} GROUP BY s")->getResultArray() as $r) {
            $sup[$r['s']] = [(int) $r['pass'], (int) $r['fail'], (int) $r['tol']];
        }
        $cats = [];
        $failedRows = 0;
        foreach ($db->query("SELECT Cause, ModeOfFailure, PossibleCause FROM tbl_dtr_bonding_info WHERE {$base} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED')")->getResultArray() as $r) {
            $failedRows++;
            $cat = trim((string) $r['Cause']);
            if ($cat === '') {
                foreach ([(string) $r['PossibleCause'], (string) $r['ModeOfFailure']] as $alt) {
                    $alt = trim($alt);
                    if ($alt !== '' && $alt !== '-') { $cat = trim((string) (str_contains($alt, ' - ') ? substr($alt, strrpos($alt, ' - ') + 3) : $alt)); break; }
                }
            }
            $cat = strtoupper(preg_replace('/\s+/', ' ', str_replace(['OVER DRIED', 'PRIMMER'], ['OVERDRIED', 'PRIMER'], strtoupper($cat))));
            $cat = $cat === '' || $cat === '-' ? 'NOT RECORDED' : $cat;
            $cats[$cat] = ($cats[$cat] ?? 0) + 1;
        }
        arsort($cats);
        $st      = $m['status'];
        $tested  = (int) $st['pass'] + (int) $st['fail'] + (int) $st['tol'];
        $shutN   = (int) ($st['shut'] ?? 0);
        $monthTx = strtoupper(date('F Y', strtotime($first)));

        // ---- the sheet: top = supplier performance + weekly chart, then each building day by day (the table of the page), then the categories ----
        $x     = new \App\Libraries\XlsxBuilder('Monthly Summary');
        $navy  = '0F2A47';
        $nD    = count($dates);
        $lastC = max($nD + 2, 33);
        $sTitle = $x->style(['bold' => true, 'size' => 14, 'color' => $navy, 'wrap' => true]);
        $sSub   = $x->style(['size' => 9, 'color' => '64748B']);
        $sInfoB = $x->style(['bold' => true, 'size' => 9, 'color' => $navy, 'h' => 'right']);
        $sSec   = $x->style(['bold' => true, 'size' => 11, 'color' => $navy, 'underline' => true]);
        $sTh    = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'center', 'wrap' => true, 'size' => 9]);
        $sThL   = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'left', 'size' => 9]);
        $sTxt   = $x->style(['bold' => true, 'border' => true, 'h' => 'left']);
        $sTxtN  = $x->style(['border' => true, 'h' => 'left']);
        $sNum   = $x->style(['border' => true, 'h' => 'center']);
        $sPct   = $x->style(['border' => true, 'h' => 'center', 'fmt' => 10, 'bold' => true, 'color' => 'C0392B']);
        $sPctN  = $x->style(['border' => true, 'h' => 'center', 'fmt' => 10]);
        $sTotL  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'DCE9F8']);
        $sTot   = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'DCE9F8']);
        $sTotP  = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'DCE9F8', 'fmt' => 10, 'color' => 'C0392B']);
        $sRateL = $x->style(['bold' => true, 'size' => 9, 'color' => '475569', 'border' => true, 'h' => 'center', 'fill' => 'F1F5F9']);
        $sRateV = $x->style(['bold' => true, 'size' => 15, 'color' => $navy, 'border' => true, 'h' => 'center', 'fmt' => 10]);
        $sRateR = $x->style(['bold' => true, 'size' => 15, 'color' => 'C0392B', 'border' => true, 'h' => 'center', 'fmt' => 10]);
        $sNote  = $x->style(['size' => 9, 'fill' => 'FFF8E1', 'wrap' => true, 'color' => '7C5A00', 'border' => true]);
        $sWk    = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'center', 'wrap' => true, 'size' => 7]);
        $dSun   = $x->style(['bold' => true, 'color' => 'E11D48', 'fill' => 'FFE4E6', 'border' => true, 'h' => 'center', 'size' => 8]);
        $dHdr   = $x->style(['bold' => true, 'color' => '334155', 'fill' => 'F8FAFC', 'border' => true, 'h' => 'center', 'size' => 8]);
        $dHdrL  = $x->style(['bold' => true, 'color' => '1E293B', 'fill' => 'F1F5F9', 'border' => true, 'h' => 'left', 'size' => 8]);
        $dCellS = $x->style(['border' => true, 'h' => 'center', 'fill' => 'FFF5F6', 'color' => '94A3B8', 'size' => 9]);
        $dFail  = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'color' => 'FFFFFF', 'fill' => 'DC2626', 'size' => 9]);
        $dShut  = $x->style(['border' => true, 'h' => 'center', 'italic' => true, 'color' => '475569', 'fill' => 'E2E8F0', 'size' => 7]);
        $dZero  = $x->style(['border' => true, 'h' => 'center', 'color' => '94A3B8', 'size' => 9]);
        $dLine  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'F1F5F9']);
        $dBld   = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'FFF7ED', 'color' => '9A3412', 'size' => 9]);
        $dBldL  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'FFEDD5', 'color' => '9A3412', 'size' => 9]);
        $dBldT  = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'FFEDD5', 'color' => '1D4ED8', 'size' => 10]);
        $dRowT  = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'F8FAFC']);
        $dMx    = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'EA580C', 'color' => 'FFFFFF']);
        $dRedL  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'FEE2E2', 'color' => '991B1B', 'size' => 9]);
        $dRed   = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'FEF2F2', 'color' => 'DC2626', 'size' => 9]);
        $dRedP  = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'FEF2F2', 'color' => 'DC2626', 'fmt' => 10, 'size' => 7]);
        $dTstL  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'F1F5F9', 'size' => 9]);
        $dTst   = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'F8FAFC', 'size' => 9]);

        $x->width(1, 24);
        for ($c = 2; $c <= $lastC; $c++) { $x->width($c, 6.2); }
        $x->width($nD + 2, 11);

        $x->merge(1, 1, 1, 16, "{$monthTx} — IN-LINE PROCESS NEW IMPROVEMENT MONITORING", $sTitle);
        $x->height(1, 38);
        $x->merge(2, 1, 2, 16, 'Bonding Inspection Summary & Daily Defect Trend Analysis (Building A & Building B)', $sSub);
        $x->merge(1, 18, 1, $lastC, 'Prod. Period: ' . date('M d', strtotime($first)) . ' – ' . date('M d, Y', strtotime($last)), $sInfoB);
        $x->merge(2, 18, 2, $lastC, 'As of Date: ' . date('M d, Y', min(time(), strtotime($last))) . '  |  Logs: ' . number_format($tested + $shutN) . ' (' . number_format($tested) . ' Tested, ' . number_format($shutN) . ' Shutdown)', $sInfoB);

        // 1. cement supplier performance (A, then B-D / E-G / H-J / K-M / N-P)
        $x->merge(4, 1, 4, 16, '1. CEMENT SUPPLIER PERFORMANCE', $sSec);
        $trip = [[2, 4], [5, 7], [8, 10], [11, 13], [14, 16]];
        $x->set(5, 1, 'Cement Supplier', $sThL);
        foreach (['Pass', 'Failed', 'Tolerable', 'Specimens', '% Failed'] as $i => $h) { $x->merge(5, $trip[$i][0], 5, $trip[$i][1], $h, $sTh); }
        $r = 6; $tp = $tf = $tt = 0;
        foreach (['NANPAO', 'ZHONGBU', 'NOT RECORDED'] as $nm) {
            [$p, $f, $t] = $sup[$nm] ?? [0, 0, 0];
            if ($nm === 'NOT RECORDED' && $p + $f + $t === 0) { continue; }
            $n = $p + $f + $t; $tp += $p; $tf += $f; $tt += $t;
            $x->set($r, 1, $nm, $sTxt);
            foreach ([$p, $f, $t, $n] as $i => $vv) { $x->merge($r, $trip[$i][0], $r, $trip[$i][1], $vv, $sNum); }
            $x->merge($r, 14, $r, 16, $n ? $f / $n : 0, $sPct);
            $r++;
        }
        $tn = $tp + $tf + $tt;
        $x->set($r, 1, 'TOTAL', $sTotL);
        foreach ([$tp, $tf, $tt, $tn] as $i => $vv) { $x->merge($r, $trip[$i][0], $r, $trip[$i][1], $vv, $sTot); }
        $x->merge($r, 14, $r, 16, $tn ? $tf / $tn : 0, $sTotP);
        $r += 2;
        foreach ([['Pass Rate', $tn ? $tp / $tn : 0, $sRateV, 1, 4], ['Tolerable Rate', $tn ? $tt / $tn : 0, $sRateV, 5, 10], ['Overall Fail Rate', $tn ? $tf / $tn : 0, $sRateR, 11, 16]] as [$lb, $vl, $stv, $c1, $c2]) {
            $x->merge($r, $c1, $r, $c2, $lb, $sRateL);
            $x->merge($r + 1, $c1, $r + 1, $c2, $vl, $stv);
        }
        $x->height($r + 1, 26);

        // weekly summary: the numbers of the chart
        $rW = 14;
        $wkTot = array_fill(0, $W, 0); $wkTested = array_fill(0, $W, 0);
        foreach ($weeks as $wi => $ds) { foreach ($ds as $d) { $wkTot[$wi] += (int) $totF[$d]; $wkTested[$wi] += (int) $totT[$d]; } }
        $x->height($rW, 44);
        $x->set($rW, 1, 'WEEKLY SUMMARY', $sThL);
        $x->set($rW + 1, 1, 'FAILED', $sTotL); $x->set($rW + 2, 1, 'TESTED', $sTxt); $x->set($rW + 3, 1, 'FAILURE %', $sTotL);
        $gF = $gT = 0;
        foreach ($weeks as $wi => $ds) {
            $x->set($rW, 2 + $wi, 'Wk ' . ($wi + 1) . "\n(" . $wLabel($ds) . ')', $sWk);
            $x->set($rW + 1, 2 + $wi, $wkTot[$wi], $sTot); $x->set($rW + 2, 2 + $wi, $wkTested[$wi], $sNum);
            $x->set($rW + 3, 2 + $wi, $wkTested[$wi] ? $wkTot[$wi] / $wkTested[$wi] : 0, $sTotP);
            $gF += $wkTot[$wi]; $gT += $wkTested[$wi];
        }
        $x->set($rW, 2 + $W, 'Total', $sWk); $x->set($rW + 1, 2 + $W, $gF, $sTot); $x->set($rW + 2, 2 + $W, $gT, $sNum); $x->set($rW + 3, 2 + $W, $gT ? $gF / $gT : 0, $sTotP);

        // 2. chart (right of section 1)
        $x->merge(4, 18, 4, $lastC, '2. WEEKLY BONDING-FAILED COUNT & FAILURE RATE TREND', $sSec);
        $x->chart([
            'anchor' => [17, 4, $lastC, 18],
            'title'  => 'Failed Specimens (Bars) vs. Failure Percentage (Line) by Production Week',
            'cat'    => 'B' . $rW . ':' . \App\Libraries\XlsxBuilder::col($W + 1) . $rW,
            'bars'   => ['Failed Count', 'B' . ($rW + 1) . ':' . \App\Libraries\XlsxBuilder::col($W + 1) . ($rW + 1), '1F4FD8'],
            'line'   => ['Failure %', 'B' . ($rW + 3) . ':' . \App\Libraries\XlsxBuilder::col($W + 1) . ($rW + 3), 'DC2626'],
        ]);

        // 3. failed per line and production date, one table per building (same as the page)
        $r3 = 20;
        $x->merge($r3, 1, $r3, $lastC, '3. COUNT OF BONDING-FAILED SPECIMENS BY LINE & PRODUCTION DATE — ' . $monthTx, $sSec);
        $rd = $r3 + 1;
        $gridFailed = 0;
        foreach (array_keys($lines) as $bld) {
            $x->set($rd, 1, "BUILDING {$bld} · PRODUCTION DATE", $dHdrL);
            foreach ($dates as $di => $d) { $x->set($rd, 2 + $di, date('m-d', strtotime($d)), date('w', strtotime($d)) === '0' ? $dSun : $dHdr); }
            $x->set($rd, $nD + 2, "TOTAL FAILED PER LINE AS OF " . date('Y-m-d', min(time(), strtotime($last))), $x->style(['bold' => true, 'color' => '1E293B', 'fill' => 'F1F5F9', 'border' => true, 'h' => 'center', 'wrap' => true, 'size' => 7]));
            $x->height($rd, 40);
            $rowSums = [];
            foreach ($lines[$bld] as $nn) { $rowSums[$nn] = 0; foreach ($dates as $d) { $rowSums[$nn] += (int) ($cells[$bld][$nn][$d] ?? 0); } }
            $mxRow = $rowSums ? max($rowSums) : 0;
            $rl = $rd + 1;
            foreach ($lines[$bld] as $nn) {
                $x->set($rl, 1, $bld . $nn, $dLine);
                foreach ($dates as $di => $d) {
                    $v = (int) ($cells[$bld][$nn][$d] ?? 0);
                    $sun = date('w', strtotime($d)) === '0';
                    if (! empty($m['shut'][$bld][$nn][$d]) && $v === 0) { $x->set($rl, 2 + $di, 'SD', $dShut); }
                    elseif ($v > 0) { $x->set($rl, 2 + $di, $v, $dFail); }
                    else { $x->set($rl, 2 + $di, 0, $sun ? $dCellS : $dZero); }
                }
                $x->set($rl, $nD + 2, $rowSums[$nn], ($mxRow > 0 && $rowSums[$nn] === $mxRow) ? $dMx : $dRowT);
                $rl++;
            }
            $x->set($rl, 1, "TOTAL FAILED BLDG {$bld}", $dBldL);
            $bs = 0;
            foreach ($dates as $di => $d) { $v = (int) ($bTot[$bld][$d] ?? 0); $bs += $v; $x->set($rl, 2 + $di, $v, $dBld); }
            $x->set($rl, $nD + 2, $bs, $dBldT);
            $gridFailed += $bs;
            $rd = $rl + 2;
        }
        $x->set($rd, 1, 'TOTAL FAILED PER PRODUCTION DATE', $dRedL);
        $x->set($rd + 1, 1, 'TOTAL TESTED PER PRODUCTION DATE', $dTstL);
        $x->set($rd + 2, 1, 'TOTAL FAILURE PERCENTAGE', $dRedL);
        $x->height($rd, 20); $x->height($rd + 1, 20); $x->height($rd + 2, 20);
        $sf = $st2 = 0;
        foreach ($dates as $di => $d) {
            $fv = (int) $totF[$d]; $tv = (int) $totT[$d]; $sf += $fv; $st2 += $tv;
            $x->set($rd, 2 + $di, $fv, $dRed); $x->set($rd + 1, 2 + $di, $tv, $dTst); $x->set($rd + 2, 2 + $di, $tv ? $fv / $tv : 0, $dRedP);
        }
        $x->set($rd, $nD + 2, $sf, $dRed); $x->set($rd + 1, $nD + 2, $st2, $dTst); $x->set($rd + 2, $nD + 2, $st2 ? $sf / $st2 : 0, $dRedP);
        $gridFailed = $sf;
        $x->merge($rd + 3, 1, $rd + 3, 16, 'SD = line shutdown · Sunday columns are shaded · orange = highest line of the building', $sSub);

        // 4. failure categories
        $r4 = $rd + 5;
        $x->merge($r4, 1, $r4, 16, '4. FAILURE CATEGORY BREAKDOWN (' . $failedRows . ' FAILED)', $sSec);
        $x->merge($r4 + 1, 1, $r4 + 1, 8, 'Failure Root Cause / Category', $sThL);
        $x->merge($r4 + 1, 9, $r4 + 1, 11, 'Count', $sTh); $x->merge($r4 + 1, 12, $r4 + 1, 14, 'Share', $sTh);
        $rr = $r4 + 2;
        foreach ($cats as $nm => $n) {
            $x->merge($rr, 1, $rr, 8, $nm, $sTxtN);
            $x->merge($rr, 9, $rr, 11, $n, $sNum); $x->merge($rr, 12, $rr, 14, $failedRows ? $n / $failedRows : 0, $sPctN);
            $rr++;
        }
        $x->merge($rr, 1, $rr, 8, 'TOTAL FAILED SPECIMENS', $sTotL);
        $x->merge($rr, 9, $rr, 11, $failedRows, $sTot); $x->merge($rr, 12, $rr, 14, $failedRows ? 1 : 0, $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'DCE9F8', 'fmt' => 10]));
        if ($gridFailed !== $failedRows) {
            $x->merge($rr + 2, 1, $rr + 2, 30, '*Note: the line table counts ' . $gridFailed . ' failed specimen(s) (failed Lab Ref Nos that carry a building / line, plus any marks or totals typed in the app); the categories count all ' . $failedRows . ' failed record(s) of the dates.', $sNote);
            $x->height($rr + 2, 30);
        }
        $bin  = $x->build();
        $name = 'Bonding_Monthly_Summary_' . date('Y-m', strtotime($first)) . '.xlsx';

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($bin);
    }

    /**
     * GET analytics/export-failure-overview?from=&to= : the Bonding Failure Overview as an Excel file. Sheet 1 = the pie charts with their count tables
     * (failed by building, portion and reason for Building A & B, then Building A and Building B alone); sheet 2 = the failed specimen log.
     */
    public function exportFailureOverview()
    {
        $db = \Config\Database::connect();
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from === '' && $this->to === '' && $this->request->getGet('all') === null) {
            $this->from = date('Y-m-01');
            $this->to   = date('Y-m-t');
        }
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
        $fb    = $this->svc('bonding')->bondingFailure($db)['fb'];
        $asOf  = $this->to !== '' ? $this->to : date('Y-m-d');
        $year  = date('Y', strtotime($asOf));
        $label = $this->from !== '' ? ($this->from === $this->to ? $this->from : $this->from . ' to ' . ($this->to ?: '...')) : 'ALL DATES';

        $x = new \App\Libraries\XlsxBuilder('Failure Overview');
        $sBanner = $x->style(['bold' => true, 'size' => 14, 'color' => 'FFFFFF', 'fill' => '000000', 'h' => 'center']);
        $sSub    = $x->style(['size' => 9, 'color' => '475569', 'h' => 'left']);
        $sGrp    = $x->style(['bold' => true, 'size' => 11, 'color' => '0F2A47', 'underline' => true]);
        $sTitle  = $x->style(['bold' => true, 'size' => 9, 'fill' => '9DC3E6', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sCat    = $x->style(['bold' => true, 'size' => 9, 'fill' => 'DEEAF6', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sNum    = $x->style(['bold' => true, 'size' => 14, 'fill' => 'FFFFFF', 'border' => true, 'h' => 'center']);
        $sTotN   = $x->style(['bold' => true, 'size' => 14, 'color' => 'FF0000', 'fill' => 'FFFFFF', 'border' => true, 'h' => 'center']);
        $sNote   = $x->style(['size' => 8, 'color' => '7C5A00', 'fill' => 'FFF8E1', 'wrap' => true, 'border' => true]);
        for ($c = 1; $c <= 16; $c++) {
            $x->width($c, 11);
        }
        $x->merge(1, 1, 1, 16, 'BONDING FAILURE OVERVIEW ' . strtoupper($label), $sBanner);
        $x->height(1, 24);
        $x->set(2, 1, 'Total failure as of ' . $asOf . ' · ' . (int) $fb['failed'] . ' failed bonding reports', $sSub);

        $blue = ['5B9BD5', 'ED7D31', 'A5A5A5'];
        // one chart block: a pie over rows $r..$r+17, its table (title | categories | total) right under it; $k = block position (4 columns each)
        $block = function (int $k, int $r, string $chartTitle, string $tblTitle, array $data) use ($x, $sTitle, $sCat, $sNum, $sTotN, $asOf, $blue): void {
            $c0  = $k * 4 + 1;
            $n   = count($data);
            $tot = array_sum($data);
            $tr  = $r + 17;
            $x->merge($tr, $c0, $tr, $c0 + 2, $tblTitle, $sTitle);
            $x->set($tr, $c0 + 3, 'TOTAL FAILURE AS OF', $sTitle);
            $x->height($tr, 26);
            $i = 0;
            foreach ($data as $name => $v) {
                $cc = $c0 + $i;
                $span = ($n === 2 && $i === 1) ? 2 : 1;   // two categories share three columns
                $x->merge($tr + 1, $cc, $tr + 1, $cc + $span - 1, (string) $name, $sCat);
                $x->merge($tr + 2, $cc, $tr + 2, $cc + $span - 1, (int) $v, $sNum);
                $i++;
            }
            $x->set($tr + 1, $c0 + 3, $asOf, $sCat);
            $x->set($tr + 2, $c0 + 3, (int) $tot, $sTotN);
            $x->height($tr + 1, 26);
            $x->height($tr + 2, 24);
            $x->chart(['type' => 'pie', 'dark' => true, 'anchor' => [$c0 - 1, $r - 1, $c0 + 3, $r + 16], 'title' => $chartTitle, 'name' => $tblTitle,
                'cat' => \App\Libraries\XlsxBuilder::col($c0) . ($tr + 1) . ':' . \App\Libraries\XlsxBuilder::col($c0 + ($n - 1)) . ($tr + 1),
                'val' => \App\Libraries\XlsxBuilder::col($c0) . ($tr + 2) . ':' . \App\Libraries\XlsxBuilder::col($c0 + ($n - 1)) . ($tr + 2),
                'colors' => array_slice($blue, 0, $n)]);
        };

        $x->set(3, 1, 'BUILDING A & B', $sGrp);
        $block(0, 4, 'FAILED BONDING BY BUILDING ' . $year . ': A & B', 'FAILED BONDING BY BUILDING', $fb['bld']);
        $block(1, 4, 'BUILDING A & B: PORTION OF FAILURE ' . $year, 'BUILDING A & B: PORTION OF FAILURE', $fb['por']);
        $block(2, 4, 'BUILDING A & B: REASON OF FAILURE ' . $year, 'BUILDING A & B: REASON OF FAILURE', $fb['rea']);
        $x->set(26, 1, 'BUILDING A AND BUILDING B', $sGrp);
        $block(0, 27, 'BUILDING A FAILED SPECIMENS ' . $year . ': PORTION OF FAILURE', 'BUILDING A: PORTION OF FAILURE', $fb['byB']['A']['por']);
        $block(1, 27, 'BUILDING A FAILED SPECIMENS ' . $year . ': REASON OF FAILURE', 'BUILDING A: REASON OF FAILURE', $fb['byB']['A']['rea']);
        $block(2, 27, 'BUILDING B FAILED SPECIMENS ' . $year . ': PORTION OF FAILURE', 'BUILDING B: PORTION OF FAILURE', $fb['byB']['B']['por']);
        $block(3, 27, 'BUILDING B FAILED SPECIMENS ' . $year . ': REASON OF FAILURE', 'BUILDING B: REASON OF FAILURE', $fb['byB']['B']['rea']);
        $x->merge(48, 1, 48, 16, 'Portion and Reason count a failed specimen once per portion / reason it names, so their totals can be higher than the failed reports. Reason: Material = weak material; Rubber Foxing/Outsole = foxing tape, rubber outsole; Workmanship = cementing, priming, buffing, pressing and the rest.'
            . ($fb['noPortion'] || $fb['noReason'] ? ' Not counted: ' . (int) $fb['noPortion'] . ' without a portion, ' . (int) $fb['noReason'] . ' without a reason recorded.' : ''), $sNote);
        $x->height(48, 34);

        // sheet 2: the failed specimen log
        $l = new \App\Libraries\XlsxBuilder('Failure Log', $x);
        $lHd  = $x->style(['bold' => true, 'size' => 9, 'fill' => '9DC3E6', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $lTx  = $x->style(['size' => 8, 'border' => true, 'h' => 'center', 'wrap' => true]);
        $lTxL = $x->style(['size' => 8, 'border' => true, 'h' => 'left', 'wrap' => true]);
        $lWm  = $x->style(['size' => 8, 'border' => true, 'h' => 'center', 'wrap' => true, 'fill' => 'DEEAF6']);
        $lFail = $x->style(['bold' => true, 'size' => 8, 'color' => 'FF0000', 'border' => true, 'h' => 'center']);
        $l->merge(1, 1, 1, 13, 'FAILED BONDING SPECIMENS ' . strtoupper($label), $sBanner);
        $l->height(1, 24);
        $heads = ['PRODUCTION DATE', 'COLLECTED DATE', 'LINE', 'MTL', 'STYLE', 'COLORWAY', 'LAB REF NO', 'TECHNICIAN', 'POSSIBLE CAUSE', 'PORTION OF FAILURE', 'REASON OF FAILURE', 'TESTED DATE', 'RESULT'];
        $widths = [13, 13, 8, 16, 26, 26, 24, 15, 44, 24, 20, 13, 9];
        foreach ($heads as $i => $h) {
            $l->set(2, $i + 1, $h, $lHd);
            $l->width($i + 1, $widths[$i]);
        }
        $l->height(2, 28);
        $r = 3;
        foreach ($fb['log'] as $row) {
            foreach ([$row['prod'], $row['recv'], $row['line'], $row['mtl'], $row['style'], $row['color'], $row['ref'], $row['tech'], $row['cause'], $row['portion'], $row['reason'], $row['tested']] as $i => $v) {
                $l->set($r, $i + 1, $v, $i === 8 ? $lTxL : ($i === 10 ? $lWm : $lTx));
            }
            $l->set($r, 13, $row['result'], $lFail);
            $r++;
        }
        $l->merge($r, 1, $r, 12, 'TOTAL FAILED BONDING SPECIMENS', $lHd);
        $l->set($r, 13, count($fb['log']), $lFail);

        $name = 'Bonding_Failure_Overview_' . ($this->from !== '' ? $this->from . '_to_' . ($this->to ?: 'now') : 'all') . '.xlsx';

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($x->build());
    }

    /**
     * GET analytics/export-failed-portion?from=&to= : "Overall Failed Portion Times Monitoring" as an Excel file: one row per failed shoe with its possible
     * cause under Upper to Foxing / Foxing to Outsole (Medial, Toe, Lateral, Heel) and Filler to Outsole (Center), the failed times per cause and portion
     * with their %, and the failure-cause pie chart.
     */
    public function exportFailedPortion()
    {
        $db = \Config\Database::connect();
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from === '' && $this->to === '' && $this->request->getGet('all') === null) {
            $this->from = date('Y-m-01');
            $this->to   = date('Y-m-t');
        }
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
        $pd   = \App\Services\Analytics\AnalyticsBase::BOND_DATE;
        $base = $this->svc('bonding')->bondingBaseWhere($pd) . ' AND ' . $this->svc('bonding')->dateWhere($pd);
        $rows = $db->query("SELECT LabRefNo, ModelStyle, Color, {$pd} AS d, Cause, FailPart, ModeOfFailure, PossibleCause, ProbableCause
            FROM tbl_dtr_bonding_info WHERE {$base} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') ORDER BY d ASC, LabRefNo ASC")->getResultArray();

        // columns 6..9 Upper to Foxing, 10..13 Foxing to Outsole, 14 Filler to Outsole (Center)
        $colsOf = ['U' => [6, 7, 8, 9], 'F' => [10, 11, 12, 13], 'I' => [14]];
        $parse  = fn (array $r): array => $this->svc('bonding')->failParse($r);   // [portion letter => [causes]]

        $cnt = [];       // [cause][col] => times
        $shoes = [];
        $noPortion = 0;
        foreach ($rows as $r) {
            $pc  = $parse($r);
            $txt = [];   // col => [causes]
            $any = false;
            foreach ($pc as $p => $causes) {
                if ($p === '') {
                    continue;
                }
                foreach ($colsOf[$p] as $col) {
                    foreach ($causes as $cn) {
                        $cnt[$cn][$col] = ($cnt[$cn][$col] ?? 0) + 1;
                        $txt[$col][]    = $cn;
                        $any            = true;
                    }
                }
            }
            if (! $any) {
                $noPortion++;
            }
            $line = preg_match('/^DTP\d{8}[A-Z]{2}([A-Z]\d+)-([LR])/i', (string) $r['LabRefNo'], $m) ? strtoupper($m[1] . '-' . $m[2]) : (preg_match('/-([LR])(?:-|$)/i', (string) $r['LabRefNo'], $m2) ? (stripos((string) $r['LabRefNo'], 'TRIAL') !== false ? 'TRIAL' : '') . '-' . strtoupper($m2[1]) : '');
            $shoes[] = ['d' => date('m-d-Y', strtotime((string) $r['d'])), 'line' => $line, 'style' => (string) $r['ModelStyle'], 'color' => (string) $r['Color'], 'txt' => $txt, 'any' => $any];
        }
        $shoes = array_values(array_filter($shoes, static fn ($s) => $s['any']));

        $order = ['OVERDRIED CEMENT', 'POOR CEMENT', 'POOR CEMENTING', 'POOR PRIMER', 'POOR PRIMING', 'POOR BUFFING', 'DUSTY FOXING TAPE', 'SPLITTING OF FOXING TAPE', 'WEAK MATERIAL', 'POOR PRESSING', 'DUSTY OUTSOLE', 'SPLITTING OF RUBBER OUTSOLE', 'INSUFFICIENT SKIRTING', 'INK DELAMINATION'];
        $causes = $order;
        foreach (array_keys($cnt) as $cn) {
            if (! in_array($cn, $causes, true)) {
                $causes[] = $cn;
            }
        }
        $colTot = array_fill(6, 9, 0);
        $grand  = 0;
        foreach ($cnt as $cn => $byCol) {
            foreach ($byCol as $col => $n) {
                $colTot[$col] += $n;
                $grand        += $n;
            }
        }

        $x = new \App\Libraries\XlsxBuilder('Failed Portion');
        $S = static fn (array $o) => $o;
        $sBlack  = $x->style(['bold' => true, 'size' => 14, 'color' => 'FFFFFF', 'fill' => '000000', 'h' => 'center']);
        $sHdr    = $x->style(['bold' => true, 'size' => 8, 'fill' => '9DC3E6', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sPC     = $x->style(['bold' => true, 'size' => 8, 'fill' => '9DC3E6', 'border' => true, 'h' => 'center']);
        $sUp     = $x->style(['bold' => true, 'size' => 8, 'fill' => 'F4B183', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sFo     = $x->style(['bold' => true, 'size' => 8, 'fill' => 'FFD966', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sFi     = $x->style(['bold' => true, 'size' => 8, 'fill' => '70AD47', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sTxt    = $x->style(['size' => 8, 'border' => true, 'h' => 'center']);
        $sTxtL   = $x->style(['size' => 8, 'border' => true, 'h' => 'left']);
        $sRed    = $x->style(['size' => 8, 'border' => true, 'h' => 'center', 'color' => 'FF0000']);
        $sGreen  = $x->style(['bold' => true, 'size' => 8, 'fill' => 'A9D08E', 'border' => true, 'h' => 'center']);
        $sOrange = $x->style(['bold' => true, 'size' => 20, 'fill' => 'F4B183', 'border' => true, 'h' => 'center', 'wrap' => true]);
        $sBlue   = $x->style(['bold' => true, 'size' => 48, 'fill' => '9DC3E6', 'border' => true, 'h' => 'center']);
        $sLabB   = $x->style(['bold' => true, 'size' => 9, 'fill' => '9DC3E6', 'border' => true, 'h' => 'center']);
        $sLabO   = $x->style(['bold' => true, 'size' => 9, 'fill' => 'F4B183', 'border' => true, 'h' => 'center']);
        $sPLab   = $x->style(['bold' => true, 'size' => 6, 'fill' => 'DEEAF6', 'border' => true, 'h' => 'center']);
        $sPLabO  = $x->style(['bold' => true, 'size' => 6, 'fill' => 'FBE5D6', 'border' => true, 'h' => 'center']);
        $sVB     = $x->style(['size' => 7, 'fill' => 'DEEAF6', 'border' => true, 'h' => 'center']);
        $sVO     = $x->style(['size' => 7, 'fill' => 'FBE5D6', 'border' => true, 'h' => 'center']);
        $sPB     = $x->style(['size' => 7, 'fill' => 'DEEAF6', 'border' => true, 'h' => 'center', 'fmt' => 10]);
        $sPO     = $x->style(['size' => 7, 'fill' => 'FBE5D6', 'border' => true, 'h' => 'center', 'fmt' => 10]);
        $sTB     = $x->style(['bold' => true, 'size' => 8, 'color' => 'FF0000', 'fill' => 'DEEAF6', 'border' => true, 'h' => 'center']);
        $sTO     = $x->style(['bold' => true, 'size' => 8, 'color' => 'FF0000', 'fill' => 'FBE5D6', 'border' => true, 'h' => 'center']);
        $sTPB    = $x->style(['bold' => true, 'size' => 7, 'color' => 'FF0000', 'fill' => 'DEEAF6', 'border' => true, 'h' => 'center', 'fmt' => 10]);
        $sTPO    = $x->style(['bold' => true, 'size' => 7, 'color' => 'FF0000', 'fill' => 'FBE5D6', 'border' => true, 'h' => 'center', 'fmt' => 10]);
        $sSec    = $x->style(['bold' => true, 'size' => 11, 'color' => '0F2A47']);
        $sNote   = $x->style(['size' => 8, 'color' => '7C5A00', 'fill' => 'FFF8E1', 'wrap' => true, 'border' => true]);

        $widths = [1 => 12, 2 => 8, 3 => 17, 4 => 15, 5 => 34];
        foreach ($widths as $c => $w) {
            $x->width($c, $w);
        }
        for ($c = 6; $c <= 14; $c++) {
            $x->width($c, 19);
        }
        $x->width(15, 9);

        $period = $this->from !== '' ? strtoupper(date('F Y', strtotime($this->from))) . ($this->to !== '' && date('Y-m', strtotime($this->to)) !== date('Y-m', strtotime($this->from)) ? ' - ' . strtoupper(date('F Y', strtotime($this->to))) : '') : 'ALL DATES';
        $x->merge(1, 1, 1, 15, 'OVERALL FAILED PORTION TIMES MONITORING ' . $period, $sBlack);
        $x->height(1, 24);
        foreach ([1 => 'PRODUCTION DATE', 2 => 'LINE', 3 => 'STYLE', 4 => 'MATERIAL TOE/HEEL', 5 => 'COLORWAY'] as $c => $t) {
            $x->merge(2, $c, 4, $c, $t, $sHdr);
        }
        $x->merge(2, 6, 2, 14, 'POSSIBLE CAUSE', $sPC);
        $x->merge(3, 6, 3, 9, 'UPPER TO FOXING', $sUp);
        $x->merge(3, 10, 3, 13, 'FOXING TO OUTSOLE', $sFo);
        $x->set(3, 14, 'FILLER TO OUTSOLE', $sFi);
        foreach (['MEDIAL', 'TOE', 'LATERAL', 'HEEL'] as $i => $pn) {
            $x->set(4, 6 + $i, $pn . "\n(POSSIBLE CAUSE)", $sUp);
            $x->set(4, 10 + $i, $pn . "\n(POSSIBLE CAUSE)", $sFo);
        }
        $x->set(4, 14, 'CENTER', $sFi);
        $x->merge(2, 15, 4, 15, 'TOTAL', $sHdr);
        $x->height(4, 26);

        $r = 5;
        $nRows = max(12, count($shoes));
        for ($i = 0; $i < $nRows; $i++, $r++) {
            $s = $shoes[$i] ?? null;
            $x->set($r, 1, $s['d'] ?? '', $sTxt);
            $x->set($r, 2, $s['line'] ?? '', $sRed);
            $x->set($r, 3, $s['style'] ?? '', $sRed);
            $x->set($r, 4, '', $sRed);
            $x->set($r, 5, $s['color'] ?? '', $sRed);
            for ($c = 6; $c <= 14; $c++) {
                $x->set($r, $c, $s ? implode(' / ', $s['txt'][$c] ?? []) : '', $sRed);
            }
            $x->set($r, 15, '', $sTxt);
        }
        // failed times per column
        $x->merge($r, 1, $r, 5, '', $sTxt);
        for ($c = 6; $c <= 14; $c++) {
            $x->set($r, $c, $colTot[$c], $sGreen);
        }
        $x->set($r, 15, $grand, $sGreen);
        $r++;

        // the failed times of every cause per column (count, then its % of all failed times)
        $r0 = $r;
        $rEnd = $r0 + count($causes) * 2 - 1;
        $x->merge($r0, 1, $rEnd, 2, "Total\nFAILED\ntimes\nper portion", $sOrange);
        $x->merge($r0, 3, $rEnd, 4, $grand, $sBlue);
        $k = 0;
        foreach ($causes as $cn) {
            $blue = $k % 2 === 0;
            $tot  = array_sum($cnt[$cn] ?? []);
            $x->set($r, 5, $cn, $blue ? $sLabB : $sLabO);
            $x->set($r + 1, 5, 'TOTAL PERCENTAGE:', $blue ? $sPLab : $sPLabO);
            for ($c = 6; $c <= 14; $c++) {
                $v = (int) ($cnt[$cn][$c] ?? 0);
                $x->set($r, $c, $v, $blue ? $sVB : $sVO);
                $x->set($r + 1, $c, $grand > 0 ? $v / $grand : 0, $blue ? $sPB : $sPO);
            }
            $x->set($r, 15, $tot, $blue ? $sTB : $sTO);
            $x->set($r + 1, 15, $grand > 0 ? $tot / $grand : 0, $blue ? $sTPB : $sTPO);
            $r += 2;
            $k++;
        }

        // pie chart of the failure causes (its numbers are this small table)
        $r += 1;
        $x->merge($r, 5, $r, 7, 'FAILURE CAUSE DISTRIBUTION', $sSec);
        $r++;
        $pieR1 = $r;
        $pieCol = ['OVERDRIED CEMENT' => '5B9BD5', 'POOR CEMENT' => 'ED7D31', 'POOR CEMENTING' => 'A5A5A5', 'POOR PRIMER' => 'FFC000', 'POOR PRIMING' => '70AD47', 'POOR BUFFING' => '9E480E',
            'DUSTY FOXING TAPE' => '636363', 'SPLITTING OF FOXING TAPE' => '997300', 'WEAK MATERIAL' => '264478', 'POOR PRESSING' => '4472C4', 'DUSTY OUTSOLE' => '43682B',
            'SPLITTING OF RUBBER OUTSOLE' => 'B35A1F', 'INSUFFICIENT SKIRTING' => '7CAFDD', 'INK DELAMINATION' => 'FFCD33'];
        $fb = ['8E7CC3', 'C27BA0', '76A5AF', 'E06666', '93C47D', 'F6B26B', '6D9EEB', 'B4A7D6'];
        $fi = 0;
        $colors = [];
        $pieTot = 0;
        foreach ($causes as $cn) {
            $t = array_sum($cnt[$cn] ?? []);
            if ($t <= 0) {
                continue;
            }
            $pieTot += $t;
        }
        foreach ($causes as $cn) {
            $t = array_sum($cnt[$cn] ?? []);
            if ($t <= 0) {
                continue;
            }
            $x->set($r, 5, $cn, $sTxtL);
            $x->set($r, 6, $t, $sTxt);
            $x->set($r, 7, $pieTot > 0 ? $t / $pieTot : 0, $x->style(['size' => 8, 'border' => true, 'h' => 'center', 'fmt' => 10]));
            $colors[] = $pieCol[$cn] ?? $fb[$fi++ % count($fb)];
            $r++;
        }
        if ($pieTot > 0) {
            $x->chart(['type' => 'pie', 'anchor' => [7, $pieR1 - 2, 14, $pieR1 + 17], 'title' => 'Distribution of Failure Cause (%) - ' . $period, 'name' => 'Failed times', 'cat' => 'E' . $pieR1 . ':E' . ($r - 1), 'val' => 'F' . $pieR1 . ':F' . ($r - 1), 'colors' => $colors]);
            $r = max($r, $pieR1 + 18);
        }
        if ($noPortion > 0) {
            $x->merge($r + 1, 1, $r + 1, 14, '*Note: ' . $noPortion . ' failed shoe(s) of the dates have no portion / cause recorded and are not counted above. A cause is counted once in every position of its portion (Medial, Toe, Lateral, Heel), the Filler to Outsole portion in Center.', $sNote);
            $x->height($r + 1, 28);
        }
        $bin  = $x->build();
        $name = 'Overall_Failed_Portion_Times_' . ($this->from !== '' ? date('Y-m', strtotime($this->from)) : 'All') . '.xlsx';

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($bin);
    }
    /** Edit mode, bottom rows of the failed-specimen grid: a typed total (kind B / F / T) for one date; an empty value clears it (back to the counted total). */
    public function bondingTotal()
    {
        $db   = \Config\Database::connect();
        $kind = strtoupper(trim((string) $this->request->getPost('kind')));
        $b    = strtoupper(trim((string) $this->request->getPost('building')));
        $d    = trim((string) $this->request->getPost('date'));
        $v    = trim((string) $this->request->getPost('value'));
        $b    = $kind === 'B' ? $b : '-';
        if (! in_array($kind, ['B', 'F', 'T'], true) || ! in_array($b, ['A', 'B', '-'], true) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || ($v !== '' && ! preg_match('/^\d{1,5}$/', $v))) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Type a whole number.', 'csrf' => csrf_hash()]);
        }
        $this->svc('bonding')->ensureTotalsTable($db);
        if ($v === '') {
            $db->table('tbl_an_bonding_totals')->where(['Kind' => $kind, 'Building' => $b, 'ProdDate' => $d])->delete();
        } else {
            $db->query('INSERT INTO tbl_an_bonding_totals (Kind, Building, ProdDate, Val) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE Val = VALUES(Val)', [$kind, $b, $d, (int) $v]);
        }
        \App\Libraries\DataCache::bump();

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** Edit mode of the failed-specimen grid: Mark = '1' (failed), 'S' (shutdown) or '' (clear) for one line and production date. */
    public function bondingMark()
    {
        $db = \Config\Database::connect();
        $b  = strtoupper(trim((string) $this->request->getPost('building')));
        $n  = (int) $this->request->getPost('line');
        $d  = trim((string) $this->request->getPost('date'));
        $m  = strtoupper(trim((string) $this->request->getPost('mark')));
        if (! in_array($b, ['A', 'B'], true) || $n < 1 || $n > 30 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || ! in_array($m, ['', '0', '1', 'S', 'N'], true)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Invalid cell.', 'csrf' => csrf_hash()]);
        }
        $this->svc('bonding')->ensureMarksTable($db);
        $key = ['Building' => $b, 'LineNo' => $n, 'ProdDate' => $d];
        if ($m === '') {
            $db->table('tbl_an_bonding_marks')->where($key)->delete();
        } else {
            $db->query('INSERT INTO tbl_an_bonding_marks (Building, `LineNo`, ProdDate, Mark) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE Mark = VALUES(Mark)', [$b, $n, $d, $m]);
        }
        \App\Libraries\DataCache::bump();

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** GET analytics/export-wholeshoe?from=&to= : Whole Shoe Physical Test Monthly Summary as Excel (.xlsx). */
    public function exportWholeShoe()
    {
        $db = \Config\Database::connect();
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
        $wa = $this->svc('wholeshoe')->wholeShoeAudit($db)['wsAudit'];

        $x      = new \App\Libraries\XlsxBuilder('Whole Shoe Summary');
        $navy   = '0F2A47';
        $sTitle = $x->style(['bold' => true, 'size' => 14, 'color' => $navy]);
        $sSub   = $x->style(['size' => 9, 'color' => '64748B']);
        $sSec   = $x->style(['bold' => true, 'size' => 11, 'color' => $navy]);
        $sTh    = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'center', 'size' => 10]);
        $sThL   = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'left', 'size' => 10]);
        $sTxt   = $x->style(['border' => true, 'h' => 'left']);
        $sNum   = $x->style(['border' => true, 'h' => 'center']);
        $sTotL  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'DCE9F8']);
        $sTot   = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'DCE9F8']);

        foreach ([1 => 22, 2 => 15, 3 => 14, 4 => 14, 5 => 14, 6 => 16, 7 => 15] as $col => $w) {
            $x->width($col, $w);
        }
        $x->merge(1, 1, 1, 7, 'WHOLE SHOE PHYSICAL TEST — MONTHLY SUMMARY', $sTitle);
        $x->merge(2, 1, 2, 7, 'Period: ' . $wa['periodText'] . ' · Total Samples: ' . $wa['totalSamples'], $sSub);
        foreach (['Month', 'Total Tested', 'Passed', 'Tolerable', 'Failed', 'Pending Remark', 'Pass Rate (%)'] as $ci => $h) {
            $x->set(4, $ci + 1, $h, $ci === 0 ? $sThL : $sTh);
        }
        $r = 5;
        foreach ($wa['monthly'] as $mo) {
            $rated = (int) $mo['pass'] + (int) $mo['tol'] + (int) $mo['fail'];
            $pRate = $rated > 0 ? round((int) $mo['pass'] / $rated * 100, 1) . '%' : '—';
            $x->set($r, 1, $mo['full'] . ' ' . $wa['year'], $sTxt);
            $x->set($r, 2, (int) $mo['total'], $sNum);
            $x->set($r, 3, (int) $mo['pass'], $sNum);
            $x->set($r, 4, (int) $mo['tol'], $sNum);
            $x->set($r, 5, (int) $mo['fail'], $sNum);
            $x->set($r, 6, (int) $mo['pend'], $sNum);
            $x->set($r, 7, $pRate, $sNum);
            $r++;
        }
        $x->set($r, 1, 'TOTAL', $sTotL);
        $x->set($r, 2, (int) $wa['totalSamples'], $sTot);
        $x->set($r, 3, (int) $wa['passTotal'], $sTot);
        $x->set($r, 4, (int) $wa['tolTotal'], $sTot);
        $x->set($r, 5, (int) $wa['failTotal'], $sTot);
        $x->set($r, 6, (int) $wa['pendTotal'], $sTot);
        $x->set($r, 7, $wa['passPct'] . '%', $sTot);

        $r += 2;
        $x->merge($r, 1, $r, 7, 'TEST METHOD PASS / TOLERABLE / FAIL DISTRIBUTION', $sSec);
        $r++;
        foreach (['Test Method', 'Evaluated (n)', 'Passed', 'Tolerable', 'Initial Fails', 'Passed on Retest', 'Pass Rate (%)'] as $ci => $h) {
            $x->set($r, $ci + 1, $h, $ci === 0 ? $sThL : $sTh);
        }
        $r++;
        foreach ($wa['methods'] as $me) {
            $x->set($r, 1, $me['label'], $sTxt);
            $x->set($r, 2, (int) $me['n'], $sNum);
            $x->set($r, 3, (int) $me['pass'], $sNum);
            $x->set($r, 4, (int) $me['tol'], $sNum);
            $x->set($r, 5, (int) $me['failTotal'], $sNum);
            $x->set($r, 6, (int) $me['failRetest'], $sNum);
            $x->set($r, 7, $me['passPct'] . '%', $sNum);
            $r++;
        }

        $name = 'WholeShoe_Monthly_Summary_' . ($this->from !== '' ? $this->from . '_to_' . ($this->to ?: 'now') : $wa['year']) . '.xlsx';

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($x->build());
    }

    /** GET analytics/export-materials?from=&to= : Material Test Monthly Summary (Pass / Tolerable / Fail) as Excel (.xlsx). */
    public function exportMaterials()
    {
        $db = \Config\Database::connect();
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
        $ma = $this->svc('materials')->materialsAudit($db)['matAudit'];

        $x      = new \App\Libraries\XlsxBuilder('Materials Summary');
        $navy   = '0F2A47';
        $sTitle = $x->style(['bold' => true, 'size' => 14, 'color' => $navy]);
        $sSub   = $x->style(['size' => 9, 'color' => '64748B']);
        $sSec   = $x->style(['bold' => true, 'size' => 11, 'color' => $navy]);
        $sTh    = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'center', 'size' => 10]);
        $sThL   = $x->style(['bold' => true, 'color' => 'FFFFFF', 'fill' => $navy, 'border' => true, 'h' => 'left', 'size' => 10]);
        $sTxt   = $x->style(['border' => true, 'h' => 'left']);
        $sNum   = $x->style(['border' => true, 'h' => 'center']);
        $sTotL  = $x->style(['bold' => true, 'border' => true, 'h' => 'left', 'fill' => 'DCE9F8']);
        $sTot   = $x->style(['bold' => true, 'border' => true, 'h' => 'center', 'fill' => 'DCE9F8']);

        foreach ([1 => 20, 2 => 22, 3 => 22, 4 => 42, 5 => 28, 6 => 16] as $col => $w) {
            $x->width($col, $w);
        }
        $x->merge(1, 1, 1, 6, 'MATERIAL TEST — MONTHLY SUMMARY (PASS / TOLERABLE / FAIL)', $sTitle);
        $x->merge(2, 1, 2, 6, 'Period: ' . $ma['periodText'] . ' · Total Evaluated: ' . $ma['total'], $sSub);
        foreach (['Month', 'Total Tested', 'Passed', 'Tolerable', 'Failed', 'Pass Rate (%)'] as $ci => $h) {
            $x->set(4, $ci + 1, $h, $ci === 0 ? $sThL : $sTh);
        }
        $r = 5;
        foreach ($ma['monthly'] as $mo) {
            $x->set($r, 1, $mo['full'] . ' ' . $ma['year'], $sTxt);
            $x->set($r, 2, (int) $mo['total'], $sNum);
            $x->set($r, 3, (int) $mo['pass'], $sNum);
            $x->set($r, 4, (int) $mo['tol'], $sNum);
            $x->set($r, 5, (int) $mo['fail'], $sNum);
            $x->set($r, 6, $mo['passPct'] . '%', $sNum);
            $r++;
        }
        $x->set($r, 1, 'TOTAL', $sTotL);
        $x->set($r, 2, (int) $ma['total'], $sTot);
        $x->set($r, 3, (int) $ma['pass'], $sTot);
        $x->set($r, 4, (int) $ma['tol'], $sTot);
        $x->set($r, 5, (int) $ma['fail'], $sTot);
        $x->set($r, 6, $ma['passPct'] . '%', $sTot);

        $r += 2;
        $x->merge($r, 1, $r, 6, 'FAILED & TOLERABLE MATERIAL BATCHES', $sSec);
        $r++;
        foreach (['Date', 'Lab Ref No.', 'Supplier', 'Material Description', 'Flagged Test(s)', 'Result'] as $ci => $h) {
            $x->set($r, $ci + 1, $h, $ci === 5 ? $sTh : $sThL);
        }
        $r++;
        foreach (array_merge($ma['failLog'], $ma['tolLog']) as $row) {
            $x->set($r, 1, $row['date'] ?: $row['month'], $sTxt);
            $x->set($r, 2, $row['labNo'], $sTxt);
            $x->set($r, 3, $row['supplier'], $sTxt);
            $x->set($r, 4, $row['material'], $sTxt);
            $x->set($r, 5, implode(', ', $row['tests'] ?? []), $sTxt);
            $x->set($r, 6, $row['remark'], $sNum);
            $r++;
        }

        $name = 'Materials_Monthly_Summary_' . ($this->from !== '' ? $this->from . '_to_' . ($this->to ?: 'now') : $ma['year']) . '.xlsx';

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($x->build());
    }
}
