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

    public function index(): string
    {
        $tab = (string) ($this->request->getGet('tab') ?? 'bonding');
        $tab = in_array($tab, ['bonding', 'wholeshoe', 'materials'], true) ? $tab : 'bonding';
        $db  = \Config\Database::connect();

        // period filter: group = day | week | month | year (bucket size of the chart), from / to = custom date range (YYYY-MM-DD)
        $g = (string) ($this->request->getGet('group') ?? 'month');
        $this->group = in_array($g, ['day', 'week', 'month', 'year'], true) ? $g : 'month';
        $ok = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $this->from = $ok($this->request->getGet('from'));
        $this->to   = $ok($this->request->getGet('to'));
        if ($this->from === '' && $this->to === '') {
            if ($this->request->getGet('all') !== null) {
                $this->group = 'month';   // All dates (picked explicitly) is charted by month
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

        // Shoe Bonding has two sub-tabs: analytics (charts) and result (per cement supplier summary)
        $view = ($tab === 'bonding' && $this->request->getGet('view') === 'result') ? 'result' : 'analytics';

        $data = match (true) {
            $tab === 'wholeshoe' => $this->wholeShoe($db),
            $tab === 'materials' => $this->materials($db),
            $view === 'result'   => $this->bondingResult($db),
            default              => $this->bonding($db),
        };

        return $this->render('analytics/index', ['title' => 'Analytics'] + $data + ['tab' => $tab, 'view' => $view, 'group' => $this->group, 'from' => $this->from, 'to' => $this->to]);
    }

    /** Bonding Result: pass / tolerable / failed counts per cement (adhesive) supplier, with a total row. */
    private function bondingResult($db): array
    {
        $w = $this->dateWhere('TestedDate');
        $rows = $db->query("SELECT UPPER(TRIM(AdhesiveSupplier)) AS name, COUNT(*) AS total, SUM(Result = 'PASS') AS pass, SUM(Result = 'TOLERABLE') AS tol, SUM(Result LIKE 'FAIL%') AS fail
            FROM tbl_dtr_bonding_info WHERE {$w} AND AdhesiveSupplier IS NOT NULL AND TRIM(AdhesiveSupplier) <> '' GROUP BY name ORDER BY total DESC")->getResultArray();

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

    private function pct(int $n, int $d): float
    {
        return $d > 0 ? round($n / $d * 100, 1) : 0.0;
    }

    /** SQL condition: the column holds a real date and falls inside the chosen range. */
    private function dateWhere(string $col): string
    {
        $w = "{$col} REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}'";
        if ($this->from !== '') {
            $w .= " AND LEFT({$col}, 10) >= '{$this->from}'";
        }
        if ($this->to !== '') {
            $w .= " AND LEFT({$col}, 10) <= '{$this->to}'";
        }

        return $w;
    }

    /** SQL expression of the chart bucket (day / week / month / year) of a date column. */
    private function bucket(string $col): string
    {
        return match ($this->group) {
            'day'   => "LEFT({$col}, 10)",
            'week'  => "DATE_FORMAT(STR_TO_DATE(LEFT({$col}, 10), '%Y-%m-%d'), '%x-W%v')",
            'year'  => "LEFT({$col}, 4)",
            default => "LEFT({$col}, 7)",
        };
    }

    /** Buckets kept: the latest N for the chosen period, more when a custom range is set. */
    private function bucketLimit(): int
    {
        $custom = $this->from !== '' || $this->to !== '';

        return match ($this->group) {
            'day'   => $custom ? 92 : 31,
            'week'  => $custom ? 53 : 12,
            'year'  => 10,
            default => $custom ? 36 : 24,
        };
    }

    private function periodWord(): string
    {
        return ['day' => 'day', 'week' => 'week', 'month' => 'month', 'year' => 'year'][$this->group];
    }

    private function bonding($db): array
    {
        $w = $this->dateWhere('TestedDate');
        $t = $db->query("SELECT COUNT(*) AS total, SUM(Result = 'PASS') AS pass, SUM(Result = 'TOLERABLE') AS tol, SUM(Result LIKE 'FAIL%') AS fail FROM tbl_dtr_bonding_info WHERE {$w}")->getRowArray();
        $n = max(1, (int) $t['total']);
        $b = $this->bucket('TestedDate');

        return [
            'kpis' => [
                ['Tests recorded', number_format((int) $t['total']), 'with a tested date', ''],
                ['Passed', number_format((int) $t['pass']), $this->pct((int) $t['pass'], $n) . '% pass rate', 'an-pass'],
                ['Tolerable', number_format((int) $t['tol']), $this->pct((int) $t['tol'], $n) . '% of tests', 'an-tol'],
                ['Failed', number_format((int) $t['fail']), $this->pct((int) $t['fail'], $n) . '% fail rate', 'an-fail'],
            ],
            'chartTitle' => 'Tests per ' . $this->periodWord(),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COUNT(*) AS total, SUM(Result = 'PASS') AS pass, SUM(Result = 'TOLERABLE') AS tol, SUM(Result LIKE 'FAIL%') AS fail
                FROM tbl_dtr_bonding_info WHERE {$w} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Styles with the most failures', 'ratio', $db->query("SELECT ModelStyle AS name, SUM(Result LIKE 'FAIL%') AS a, COUNT(*) AS b FROM tbl_dtr_bonding_info
                    WHERE {$w} AND ModelStyle IS NOT NULL AND ModelStyle <> '' GROUP BY ModelStyle HAVING a > 0 ORDER BY a DESC, b DESC LIMIT 8")->getResultArray()],
                ['Technician workload (fails / tests)', 'ratio', $db->query("SELECT Technician AS name, SUM(Result LIKE 'FAIL%') AS a, COUNT(*) AS b FROM tbl_dtr_bonding_info
                    WHERE {$w} AND Technician IS NOT NULL AND Technician <> '' GROUP BY Technician ORDER BY b DESC LIMIT 8")->getResultArray()],
                ['Top modes of failure', 'count', $db->query("SELECT ModeOfFailure AS name, COUNT(*) AS a FROM tbl_dtr_bonding_info
                    WHERE {$w} AND Result LIKE 'FAIL%' AND ModeOfFailure IS NOT NULL AND ModeOfFailure <> '' GROUP BY ModeOfFailure ORDER BY a DESC LIMIT 8")->getResultArray()],
            ],
        ];
    }

    private function wholeShoe($db): array
    {
        if (! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return ['kpis' => [['Whole shoe lines', '0', 'nothing saved yet', '']], 'chartTitle' => 'Whole shoes collected per ' . $this->periodWord(), 'months' => [], 'lists' => [], 'empty' => 'No whole shoe intake saved yet. Use + Whole Shoe Collected on the Lab Tracker.'];
        }
        $w = $this->dateWhere('CollectionDate');
        $t = $db->query("SELECT COUNT(*) AS lines_, COALESCE(SUM(NoOfShoes), 0) AS shoes, COALESCE(SUM(Building = 'A'), 0) AS a, COALESCE(SUM(Building = 'B'), 0) AS b FROM tbl_dtr_wholeshoe_intake WHERE {$w}")->getRowArray();
        $reports = $db->tableExists('tbl_dtr_wholeshoe_reports') ? (int) $db->table('tbl_dtr_wholeshoe_reports')->countAllResults() : 0;
        $b = $this->bucket('CollectionDate');

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

        return [
            'kpis' => [
                ['Whole shoe lines', number_format((int) $t['lines_']), 'saved at intake', ''],
                ['Shoes collected', number_format((int) $t['shoes']), 'total No. of Shoes', ''],
                ['Building A / B', (int) $t['a'] . ' / ' . (int) $t['b'], 'lines per building', ''],
                ['Reports saved', number_format($reports), 'Whole Footwear Test Results', 'an-pass'],
            ],
            'chartTitle' => 'Whole shoes collected per ' . $this->periodWord(),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COALESCE(SUM(NoOfShoes), 0) AS total FROM tbl_dtr_wholeshoe_intake
                WHERE {$w} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Tests requested', 'count', $testRows],
                ['Styles collected most', 'count', $db->query("SELECT s.StyleNo AS name, COALESCE(SUM(i.NoOfShoes), 0) AS a FROM tbl_dtr_wholeshoe_intake i JOIN tbl_dtr_shoe_list s ON s.ProdCode = i.ProdCode
                    WHERE " . $this->dateWhere('i.CollectionDate') . " AND s.StyleNo <> '' GROUP BY s.StyleNo ORDER BY a DESC LIMIT 8")->getResultArray()],
                ['Encoded by', 'count', $db->query("SELECT EncodedBy AS name, COUNT(*) AS a FROM tbl_dtr_wholeshoe_intake WHERE {$w} AND EncodedBy <> '' GROUP BY EncodedBy ORDER BY a DESC LIMIT 8")->getResultArray()],
            ],
        ];
    }

    private function materials($db): array
    {
        $w = "form_slug <> 'bonding' AND " . $this->dateWhere('tested_date');
        $t = $db->query("SELECT COUNT(*) AS total, SUM(overall_result IN ('Pass','P')) AS pass, SUM(overall_result = 'Tolerable') AS tol, SUM(overall_result = 'Fail') AS fail FROM tbl_dtr_unified_labrefs WHERE {$w}")->getRowArray();
        $n = max(1, (int) $t['total']);
        $b = $this->bucket('tested_date');

        return [
            'kpis' => [
                ['Material tests', number_format((int) $t['total']), 'all material forms', ''],
                ['Passed', number_format((int) $t['pass']), $this->pct((int) $t['pass'], $n) . '% pass rate', 'an-pass'],
                ['Tolerable', number_format((int) $t['tol']), $this->pct((int) $t['tol'], $n) . '% of tests', 'an-tol'],
                ['Failed', number_format((int) $t['fail']), $this->pct((int) $t['fail'], $n) . '% fail rate', 'an-fail'],
            ],
            'chartTitle' => 'Material tests per ' . $this->periodWord(),
            'months'     => array_reverse($db->query("SELECT {$b} AS m, COUNT(*) AS total, SUM(overall_result IN ('Pass','P')) AS pass, SUM(overall_result = 'Tolerable') AS tol, SUM(overall_result = 'Fail') AS fail
                FROM tbl_dtr_unified_labrefs WHERE {$w} GROUP BY m ORDER BY m DESC LIMIT " . $this->bucketLimit())->getResultArray()),
            'lists' => [
                ['Material categories (fails / tests)', 'ratio', $db->query("SELECT form_category AS name, SUM(overall_result = 'Fail') AS a, COUNT(*) AS b FROM tbl_dtr_unified_labrefs WHERE {$w} GROUP BY form_category ORDER BY b DESC LIMIT 8")->getResultArray()],
                ['Suppliers (fails / tests)', 'ratio', $db->query("SELECT supplier AS name, SUM(overall_result = 'Fail') AS a, COUNT(*) AS b FROM tbl_dtr_unified_labrefs WHERE {$w} AND supplier <> '' GROUP BY supplier ORDER BY b DESC LIMIT 8")->getResultArray()],
                ['Technicians', 'count', $db->query("SELECT technician AS name, COUNT(*) AS a FROM tbl_dtr_unified_labrefs WHERE {$w} AND technician <> '' GROUP BY technician ORDER BY a DESC LIMIT 8")->getResultArray()],
            ],
        ];
    }
}