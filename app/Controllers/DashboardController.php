<?php

namespace App\Controllers;

/**
 * Lab dashboard: work in progress, failures, reports waiting, OEE of the bonding machines and test tracking.
 * The page loads once; GET dashboard/data?days=N returns every figure as JSON so the period can change without a reload.
 */
class DashboardController extends BaseController
{
    /** The six bonding (U60) testers. */
    private const MACHINES = ['DTP-QC-001', 'DTP-QC-002', 'DTP-QC-048', 'DTP-QC-064', 'DTP-QC-066', 'DTP-QC-067'];

    /** Bonding tests one machine can finish in a working day (performance = tests / capacity). */
    private const CAPACITY_PER_DAY = 12;

    private const OEE_TARGET = 80;

    /** Lab-reference date stored as text ("27 Oct 2026"). */
    private const LR_DATE = "STR_TO_DATE(tested_date, '%d %b %Y')";

    public function index(): string
    {
        return $this->render('dashboard/index', ['title' => 'Dashboard', 'withCss' => 'labdash.css', 'withJs' => ['labdash.js']]);
    }

    public function data()
    {
        $days = (int) ($this->request->getGet('days') ?? 30);
        $days = in_array($days, [1, 7, 30, 90, 365], true) ? $days : 30;
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to   = date('Y-m-d');
        // one section per request (?part=), so the page fills in section by section instead of waiting for everything
        $parts = [
            'overview'  => ['tiles' => 'tiles', 'daily' => 'daily'],
            'bonding'   => ['oee' => 'oee', 'trend' => 'trend', 'strength' => 'bondStrength', 'pull' => 'pullTests', 'ongoing' => 'ongoingBonding'],
            'materials' => ['category' => 'categories', 'items' => 'testItems', 'running' => 'runningMachines'],
            'wholeshoe' => ['tracking' => 'tracking'],
            'people'    => ['people' => 'technicians', 'supplier' => 'suppliers'],
            'reports'   => ['reports' => 'reports'],
        ];
        $part = (string) $this->request->getGet('part');
        $want = isset($parts[$part]) ? [$part => $parts[$part]] : $parts;

        // the lab figures change a few times an hour: every section is kept for 90 seconds per period, so many people opening
        // the dashboard (and the 2-minute refresh) cost the database one query set, not one each; ?fresh=1 skips it
        $cache = cache();
        $out   = ['ok' => true, 'days' => $days, 'from' => $from, 'to' => $to];
        $db    = null;
        foreach ($want as $name => $methods) {
            $key = "labdash_{$name}_{$days}_" . $to;
            $sec = $this->request->getGet('fresh') ? null : $cache->get($key);
            if (! is_array($sec)) {
                $db ??= \Config\Database::connect();
                $sec = ['updated' => date('M d, Y h:i A')];
                foreach ($methods as $field => $method) {
                    $sec[$field] = in_array($method, ['ongoingBonding', 'runningMachines', 'reports'], true) ? $this->{$method}($db) : $this->{$method}($db, $from, $to);
                }
                $cache->save($key, $sec, 90);
            }
            $out += $sec;
        }

        return $this->response->setJSON($out)->setHeader('Cache-Control', 'private, max-age=30');
    }

    /** Finished lab references per day: total, failed, and the pass rate (line chart and KPI sparklines). */
    private function daily($db, string $from, string $to): array
    {
        $start = $from;
        if ((strtotime($to) - strtotime($from)) / 86400 < 13) {
            $start = date('Y-m-d', strtotime($to . ' -13 days'));      // short periods still show two weeks of context
        }
        // one row per lab reference, so each point of the chart can list the lab references behind it
        $rows   = $this->q($db, 'SELECT ' . self::LR_DATE . " d, lab_ref_no, form_slug, source_id, overall_result
            FROM tbl_dtr_unified_labrefs WHERE " . self::LR_DATE . ' BETWEEN ? AND ? ORDER BY lab_ref_no', [$start, $to]);
        $weekly = (strtotime($to) - strtotime($start)) / 86400 > 60;      // 90-day / year views are grouped per week
        $names  = $this->categoryNames();
        $out    = [];
        for ($t = strtotime($start); $t <= strtotime($to); $t += 86400) {
            $key = $weekly ? date('o-\WW', $t) : date('Y-m-d', $t);
            $out[$key] ??= ['label' => $weekly ? 'Wk ' . date('W', $t) : date('M d', $t),
                'title' => $weekly ? 'Week of ' . date('M d', $t) : date('D, M d Y', $t),
                'total' => 0, 'fail' => 0, 'tol' => 0, 'bonding' => 0, 'refs' => []];
        }
        $mix = ['Pass' => 0, 'Tolerable' => 0, 'Fail' => 0, 'Other' => 0];
        $bmix = ['Pass' => 0, 'Tolerable' => 0, 'Fail' => 0, 'Other' => 0];
        foreach ($rows as $r) {
            $key = $weekly ? date('o-\WW', strtotime($r['d'])) : $r['d'];
            if (! isset($out[$key])) {
                continue;
            }
            $res = in_array($r['overall_result'], ['Pass', 'P'], true) ? 'Pass' : (in_array($r['overall_result'], ['Tolerable', 'Fail'], true) ? $r['overall_result'] : 'Other');
            $o   = &$out[$key];
            $o['total']++;
            $o['fail'] += $res === 'Fail' ? 1 : 0;
            $o['tol'] += $res === 'Tolerable' ? 1 : 0;
            $o['bonding'] += $r['form_slug'] === 'bonding' ? 1 : 0;
            if (count($o['refs']) < 12 || $res === 'Fail') {             // failures are always listed
                $o['refs'][] = ['ref' => $r['lab_ref_no'], 'cat' => $names[$r['form_slug']] ?? $r['form_slug'], 'res' => $res,
                    'slug' => $r['form_slug'], 'sid' => (int) $r['source_id']];
            }
            unset($o);
            if ($r['d'] >= $from) {
                $mix[$res]++;
                if ($r['form_slug'] === 'bonding') {
                    $bmix[$res]++;
                }
            }
        }

        return ['series' => array_values($out), 'mix' => $mix, 'bonding_mix' => $bmix];
    }

    /** Average bond strength (kgf/cm) per day for Upper-to-Foxing, Foxing-to-Outsole and Filler-to-Outsole. */
    private function bondStrength($db, string $from, string $to): array
    {
        $start = min($from, date('Y-m-d', strtotime($to . ' -29 days')));
        $rows  = $this->q($db, "SELECT TestDate d, ROUND(AVG(NULLIF(UTFAverage, 0)), 2) utf, ROUND(AVG(NULLIF(FTOAverage, 0)), 2) fto,
                ROUND(AVG(NULLIF(FiTOAverage, 0)), 2) fito, COUNT(*) n
            FROM tbl_dtr_bonding_points WHERE TestDate BETWEEN ? AND ? GROUP BY d ORDER BY d", [$start, $to]);
        $part = $this->q($db, "SELECT SUM(UPPER(UTF_Result) LIKE 'P%') utf_p, SUM(UPPER(UTF_Result) LIKE 'T%') utf_t, SUM(UPPER(UTF_Result) LIKE 'F%') utf_f,
                SUM(UPPER(FTO_Result) LIKE 'P%') fto_p, SUM(UPPER(FTO_Result) LIKE 'T%') fto_t, SUM(UPPER(FTO_Result) LIKE 'F%') fto_f,
                SUM(UPPER(FiTO_Result) LIKE 'P%') fito_p, SUM(UPPER(FiTO_Result) LIKE 'T%') fito_t, SUM(UPPER(FiTO_Result) LIKE 'F%') fito_f
            FROM tbl_dtr_bonding_points WHERE TestDate BETWEEN ? AND ?", [$from, $to])[0] ?? [];

        return [
            'labels' => array_map(static fn ($r) => date('M d', strtotime($r['d'])), $rows),
            'utf'    => array_map(static fn ($r) => $r['utf'] === null ? null : (float) $r['utf'], $rows),
            'fto'    => array_map(static fn ($r) => $r['fto'] === null ? null : (float) $r['fto'], $rows),
            'fito'   => array_map(static fn ($r) => $r['fito'] === null ? null : (float) $r['fito'], $rows),
            'standard' => 2.5,
            'parts'  => [
                ['name' => 'Upper to Foxing', 'pass' => (int) ($part['utf_p'] ?? 0), 'tol' => (int) ($part['utf_t'] ?? 0), 'fail' => (int) ($part['utf_f'] ?? 0)],
                ['name' => 'Foxing to Outsole', 'pass' => (int) ($part['fto_p'] ?? 0), 'tol' => (int) ($part['fto_t'] ?? 0), 'fail' => (int) ($part['fto_f'] ?? 0)],
                ['name' => 'Filler to Outsole', 'pass' => (int) ($part['fito_p'] ?? 0), 'tol' => (int) ($part['fito_t'] ?? 0), 'fail' => (int) ($part['fito_f'] ?? 0)],
            ],
        ];
    }

    /** Every test item inside the reports of the period (tensile, tear, crocking, bonding parts...), with pass / fail counts. */
    private function testItems($db, string $from, string $to): array
    {
        $rows  = $this->q($db, "SELECT form_slug, test_items_json FROM tbl_dtr_unified_labrefs
            WHERE test_items_json LIKE '{%' AND " . self::LR_DATE . ' BETWEEN ? AND ?', [$from, $to]);
        $names = $this->categoryNames();
        $items = [];
        $tests = [];
        foreach ($rows as $r) {
            foreach ((array) (json_decode((string) $r['test_items_json'], true)['items'] ?? []) as $i) {
                $name = trim(preg_replace('/\s*\(.*?\)/', '', (string) ($i['test_name'] ?? '')));
                if ($name === '') {
                    continue;
                }
                $st   = strtoupper((string) ($i['result_status'] ?? ''));
                $kind = str_starts_with($st, 'F') ? 'fail' : (str_starts_with($st, 'T') ? 'tol' : 'pass');
                $k    = $r['form_slug'] . '|' . $name;
                $items[$k] ??= ['category' => $names[$r['form_slug']] ?? $r['form_slug'], 'test' => $name, 'pass' => 0, 'tol' => 0, 'fail' => 0];
                $items[$k][$kind]++;
                $tests[$name] ??= ['test' => $name, 'total' => 0, 'fail' => 0];
                $tests[$name]['total']++;
                $tests[$name]['fail'] += $kind === 'fail' ? 1 : 0;
            }
        }
        $items = array_values($items);
        usort($items, static fn ($a, $b) => [$b['fail'], $b['pass'] + $b['tol'] + $b['fail']] <=> [$a['fail'], $a['pass'] + $a['tol'] + $a['fail']]);
        $tests = array_values($tests);
        usort($tests, static fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['by_item' => array_slice($items, 0, 14), 'by_test' => array_slice($tests, 0, 12), 'kinds' => count($tests)];
    }

    /** Pull / eyelet / rivet tensile tests (machine pulls) in the period. */
    private function pullTests($db, string $from, string $to): array
    {
        return $this->q($db, "SELECT TRIM(REPLACE(i.Method, '  ', ' ')) method, i.EquipmentCode machine, COUNT(DISTINCT i.PullInfoID) samples, COUNT(t.PullTestID) pulls,
                ROUND(AVG(t.PeakKgf), 2) avg_peak, ROUND(MIN(t.PeakKgf), 2) min_peak, ROUND(MAX(t.PeakKgf), 2) max_peak, MAX(i.TestedDate) last_test
            FROM tbl_dtr_pull_info i LEFT JOIN tbl_dtr_pull_tests t ON t.PullInfoID = i.PullInfoID
            WHERE i.TestedDate BETWEEN ? AND ? GROUP BY method, machine ORDER BY pulls DESC", [$from, $to]);
    }

    /** Flex / abrasion testers and how far each running test is. */
    private function runningMachines($db): array
    {
        return $this->q($db, "SELECT labreference lab_ref, category, type, machine_number machine, status, target_cycles target, current_cycles current,
                recorded_cycles, operator, testdate FROM tbl_material_tests ORDER BY status = 'RUNNING' DESC, updated_at DESC LIMIT 12");
    }

    private function technicians($db, string $from, string $to): array
    {
        return $this->q($db, "SELECT COALESCE(NULLIF(TRIM(technician), ''), 'Not recorded') name, COUNT(*) n, SUM(overall_result = 'Fail') f
            FROM tbl_dtr_unified_labrefs WHERE " . self::LR_DATE . ' BETWEEN ? AND ? GROUP BY name ORDER BY n DESC LIMIT 8', [$from, $to]);
    }

    private function suppliers($db, string $from, string $to): array
    {
        return $this->q($db, "SELECT COALESCE(NULLIF(TRIM(supplier), ''), 'Not recorded') name, COUNT(*) n, SUM(overall_result = 'Fail') f, SUM(overall_result = 'Tolerable') t
            FROM tbl_dtr_unified_labrefs WHERE " . self::LR_DATE . ' BETWEEN ? AND ? GROUP BY name HAVING n > 0 ORDER BY f DESC, n DESC LIMIT 8', [$from, $to]);
    }

    /** InterLink applications by job status and report approvals waiting. */
    private function reports($db): array
    {
        return [
            'interlink' => $this->q($db, "SELECT COALESCE(NULLIF(job_status, ''), 'Unknown') status, COUNT(*) n FROM tbl_interlink_reports GROUP BY status ORDER BY n DESC"),
            'pending_approvals' => (int) ($this->q($db, "SELECT COUNT(*) n FROM tbl_dtr_report_approvals WHERE status = 'pending'")[0]['n'] ?? 0),
            'approved_7d' => (int) ($this->q($db, "SELECT COUNT(*) n FROM tbl_dtr_report_approvals WHERE status = 'approved' AND decided_at >= NOW() - INTERVAL 7 DAY")[0]['n'] ?? 0),
        ];
    }

    private function categoryNames(): array
    {
        return ['bonding' => 'Whole Shoe Bonding', 'fabric' => 'Fabric', 'wst' => 'Webbing / Lace / Thread', 'hardware' => 'Hardware', 'fthl' => 'Foxing Tape & Heel Logo',
            'leather' => 'Leather', 'label' => 'Labels', 'sklfm' => 'Sockliner & Foam', 'rubber' => 'Rubber Outsole', 'box' => 'Carton Box', 'insole' => 'Insole Board'];
    }

    /** Ongoing / failed / waiting-for-report counts for Bonding, Material and Whole Shoe. */
    private function tiles($db, string $from, string $to): array
    {
        // the approved lab references are read once (a few dozen rows) instead of a per-row sub-query across two collations
        $approved = array_flip(array_column($this->q($db, "SELECT DISTINCT lab_ref_no FROM tbl_dtr_report_approvals WHERE status = 'approved'"), 'lab_ref_no'));
        $lr = $this->q($db, "SELECT CASE WHEN form_slug = 'bonding' THEN 'bonding' ELSE 'material' END AS grp, lab_ref_no,
                status NOT LIKE 'Complete%' AS open_, overall_result = 'Fail' AS fail_, " . self::LR_DATE . ' BETWEEN ? AND ? AS in_period
            FROM tbl_dtr_unified_labrefs WHERE status NOT LIKE \'Complete%\' OR ' . self::LR_DATE . ' BETWEEN ? AND ?', [$from, $to, $from, $to]);
        $t = ['bonding' => ['ongoing' => 0, 'failed' => 0, 'for_report' => 0], 'material' => ['ongoing' => 0, 'failed' => 0, 'for_report' => 0]];
        foreach ($lr as $r) {
            $g = $r['grp'];
            if ($r['open_']) {
                $t[$g]['ongoing']++;
            } elseif ($r['in_period']) {
                $t[$g]['failed'] += (int) $r['fail_'];
                $t[$g]['for_report'] += isset($approved[$r['lab_ref_no']]) ? 0 : 1;
            }
        }

        // bonding samples collected (tracker) but without a test result yet
        $t['bonding']['ongoing'] += (int) ($this->q($db, "SELECT COUNT(*) n FROM tbl_dtr_bondingtracker k WHERE k.Shutdown = 0 AND k.CollectionDate BETWEEN ? AND ?
            AND (k.LabRefNo IS NULL OR NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo COLLATE utf8mb4_general_ci = k.LabRefNo COLLATE utf8mb4_general_ci AND b.Result IS NOT NULL))", [$from, $to])[0]['n'] ?? 0);

        // whole shoe: each test of each collected shoe
        $ws = $this->q($db, "SELECT w.LabRefNo, l.status, l.overall_result, l.lab_ref_no AS done_ref FROM tbl_dtr_wholeshoetracker w
            LEFT JOIN tbl_dtr_unified_labrefs l ON l.lab_ref_no COLLATE utf8mb4_general_ci = w.LabRefNo COLLATE utf8mb4_general_ci WHERE w.CollectionDate BETWEEN ? AND ?", [$from, $to]);
        $w = ['ongoing' => 0, 'failed' => 0, 'for_report' => 0];
        foreach ($ws as $r) {
            if (! $r['done_ref'] || stripos((string) $r['status'], 'Complete') !== 0) {
                $w['ongoing']++;
            } elseif ($r['overall_result'] === 'Fail') {
                $w['failed']++;
            } else {
                $w['for_report']++;
            }
        }
        $t['wholeshoe'] = $w;

        return $t;
    }

    /** Availability x Performance x Quality for each bonding machine over the period. */
    private function oee($db, string $from, string $to): array
    {
        $working = 0;
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $working += date('w', $d) === '0' ? 0 : 1;          // Monday to Saturday
        }
        $working = max(1, $working);

        $rows = $this->q($db, "SELECT CASE WHEN EquipmentCode = 'DTP-QC-66' THEN 'DTP-QC-066' ELSE EquipmentCode END AS m,
                COUNT(*) AS tests, COUNT(DISTINCT TestDate) AS active_days,
                SUM(UPPER(Result) LIKE 'P%' OR UPPER(Result) LIKE 'T%') AS good, SUM(UPPER(Result) LIKE 'F%') AS bad, MAX(TestDate) AS last_test
            FROM tbl_dtr_bonding_points WHERE TestDate BETWEEN ? AND ? GROUP BY m", [$from, $to]);
        $by = array_column($rows, null, 'm');

        $machines = [];
        foreach (self::MACHINES as $code) {
            $r      = $by[$code] ?? ['tests' => 0, 'active_days' => 0, 'good' => 0, 'bad' => 0, 'last_test' => null];
            $tests  = (int) $r['tests'];
            $active = (int) $r['active_days'];
            $avail  = min(1, $active / $working);
            $perf   = $active ? min(1, $tests / ($active * self::CAPACITY_PER_DAY)) : 0;
            $judged = (int) $r['good'] + (int) $r['bad'];
            $qual   = $judged ? (int) $r['good'] / $judged : ($tests ? 1 : 0);
            $machines[] = [
                'code' => $code, 'tests' => $tests, 'active_days' => $active, 'fails' => (int) $r['bad'],
                'availability' => round($avail * 100, 1), 'performance' => round($perf * 100, 1), 'quality' => round($qual * 100, 1),
                'oee' => round($avail * $perf * $qual * 100, 1), 'last_test' => $r['last_test'],
            ];
        }
        $used = array_filter($machines, static fn ($m) => $m['tests'] > 0);

        return [
            'target' => self::OEE_TARGET, 'capacity' => self::CAPACITY_PER_DAY, 'working_days' => $working,
            'overall' => $used ? round(array_sum(array_column($used, 'oee')) / count($used), 1) : 0,
            'machines' => $machines,
        ];
    }

    /** Bonding tests per day and machine (last 14 days of the period at most). */
    private function trend($db, string $from, string $to): array
    {
        $start = max($from, date('Y-m-d', strtotime($to . ' -13 days')));
        $rows  = $this->q($db, "SELECT TestDate d, CASE WHEN EquipmentCode = 'DTP-QC-66' THEN 'DTP-QC-066' ELSE EquipmentCode END m, COUNT(*) n,
                SUM(UPPER(Result) LIKE 'F%') f FROM tbl_dtr_bonding_points WHERE TestDate BETWEEN ? AND ? GROUP BY d, m", [$start, $to]);
        $labels = [];
        for ($d = strtotime($start); $d <= strtotime($to); $d += 86400) {
            $labels[] = date('Y-m-d', $d);
        }
        $series = [];
        foreach (self::MACHINES as $code) {
            $series[$code] = array_fill_keys($labels, 0);
        }
        $fails = array_fill_keys($labels, 0);
        foreach ($rows as $r) {
            if (isset($series[$r['m']][$r['d']])) {
                $series[$r['m']][$r['d']] = (int) $r['n'];
                $fails[$r['d']] += (int) $r['f'];
            }
        }

        return [
            'labels' => array_map(static fn ($d) => date('M d', strtotime($d)), $labels),
            'series' => array_map(static fn ($c) => ['name' => $c, 'data' => array_values($series[$c])], self::MACHINES),
            'fails'  => array_values($fails),
        ];
    }

    /** Results per test category over the period. */
    private function categories($db, string $from, string $to): array
    {
        $rows = $this->q($db, "SELECT form_slug,
                SUM(overall_result IN ('Pass','P')) pass, SUM(overall_result = 'Tolerable') tol, SUM(overall_result = 'Fail') fail,
                SUM(status NOT LIKE 'Complete%') ongoing, COUNT(*) total
            FROM tbl_dtr_unified_labrefs WHERE " . self::LR_DATE . ' BETWEEN ? AND ? OR status NOT LIKE \'Complete%\' GROUP BY form_slug ORDER BY total DESC', [$from, $to]);
        $names = ['bonding' => 'Whole Shoe Bonding', 'fabric' => 'Fabric', 'wst' => 'Webbing / Lace / Thread', 'hardware' => 'Hardware', 'fthl' => 'Foxing Tape & Heel Logo',
            'leather' => 'Leather', 'label' => 'Labels', 'sklfm' => 'Sockliner & Foam', 'rubber' => 'Rubber Outsole', 'box' => 'Carton Box', 'insole' => 'Insole Board'];

        return array_map(static fn ($r) => [
            'slug' => $r['form_slug'], 'name' => $names[$r['form_slug']] ?? ucfirst((string) $r['form_slug']),
            'pass' => (int) $r['pass'], 'tolerable' => (int) $r['tol'], 'fail' => (int) $r['fail'], 'ongoing' => (int) $r['ongoing'], 'total' => (int) $r['total'],
        ], $rows);
    }

    /** Bonding samples on the tracker (newest first) with their result when tested. */
    private function ongoingBonding($db): array
    {
        return $this->q($db, "SELECT k.LabRefNo lab_ref, COALESCE(k.ProductionCode, '') prod_code, COALESCE(k.Style, b.ModelStyle, '') style,
                CONCAT_WS('', k.Building, '') building, k.LineNo line, k.ProductionDate prod_date, b.TestedDate test_date,
                COALESCE(b.Technician, k.Technician, '') technician, COALESCE(b.Result, CASE WHEN k.Shutdown = 1 THEN 'SHUTDOWN' ELSE 'Waiting' END) result,
                k.TestType test_type, x.id bonding_id
            FROM tbl_dtr_bondingtracker k
            LEFT JOIN (SELECT LabRefNo, MAX(BondingTestID) id FROM tbl_dtr_bonding_info WHERE Result IS NOT NULL GROUP BY LabRefNo) x
                ON x.LabRefNo COLLATE utf8mb4_general_ci = k.LabRefNo COLLATE utf8mb4_general_ci
            LEFT JOIN tbl_dtr_bonding_info b ON b.BondingTestID = x.id
            ORDER BY k.CollectionDate DESC, k.TrackerID DESC LIMIT 40");
    }

    /** Progress per test type: whole-shoe tests (tracker) and material categories with work open. */
    private function tracking($db, string $from, string $to): array
    {
        $out = [];
        $ws  = $this->q($db, "SELECT w.TestType t, COUNT(*) total, SUM(l.status LIKE 'Complete%') done, SUM(l.overall_result = 'Fail') fail
            FROM tbl_dtr_wholeshoetracker w LEFT JOIN tbl_dtr_unified_labrefs l ON l.lab_ref_no COLLATE utf8mb4_general_ci = w.LabRefNo COLLATE utf8mb4_general_ci
            WHERE w.CollectionDate BETWEEN ? AND ? GROUP BY w.TestType ORDER BY w.TestType", [$from, $to]);
        foreach ($ws as $r) {
            $out[] = ['group' => 'Whole Shoe', 'test' => $r['t'], 'total' => (int) $r['total'], 'done' => (int) $r['done'], 'fail' => (int) $r['fail']];
        }
        $bt = $this->q($db, "SELECT COUNT(*) total, SUM(b.Result IS NOT NULL) done, SUM(UPPER(b.Result) LIKE 'F%') fail FROM tbl_dtr_bondingtracker k
            LEFT JOIN (SELECT LabRefNo, MAX(Result) Result FROM tbl_dtr_bonding_info WHERE Result IS NOT NULL GROUP BY LabRefNo) b
            ON b.LabRefNo COLLATE utf8mb4_general_ci = k.LabRefNo COLLATE utf8mb4_general_ci WHERE k.Shutdown = 0 AND k.CollectionDate BETWEEN ? AND ?", [$from, $to])[0] ?? null;
        if ($bt && (int) $bt['total']) {
            array_unshift($out, ['group' => 'Bonding', 'test' => 'Daily bonding (tracker)', 'total' => (int) $bt['total'], 'done' => (int) $bt['done'], 'fail' => (int) $bt['fail']]);
        }

        return $out;
    }

    private function q($db, string $sql, array $bind = []): array
    {
        try {
            return $db->query($sql, $bind)->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard query: ' . $e->getMessage());

            return [];
        }
    }
}
