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

    /** Lab-reference date stored as text: "27 Oct 2026" (most rows) or "2026-11-18" (newer ones, e.g. pull tests). */
    private const LR_DATE = "COALESCE(STR_TO_DATE(tested_date, '%d %b %Y'), STR_TO_DATE(LEFT(tested_date, 10), '%Y-%m-%d'))";

    /** Both spellings of a failed report exist ("Fail" and "FAILED"). */
    private const FAILED = "overall_result IN ('Fail', 'FAILED')";

    /**
     * A lab reference still being worked on. Whole shoe and pull test rows of the unified list are intake placeholders (their tests are counted
     * from their own trackers), so they are never "ongoing".
     */
    private const OPEN = "(status NOT LIKE 'Complete%' AND form_slug NOT IN ('wholeshoe', 'pulltest'))";

    /**
     * The lab references the figures are made of, one row per lab reference and form:
     * - bonding comes from tbl_dtr_bonding_info (the latest row of each lab reference): that is where the test result is entered, while the unified
     *   list lags behind it (about 100 bonding results of the last 30 days differ, and 1,900 rows are still "Pending" after the result was entered);
     * - every other form comes from tbl_dtr_unified_labrefs (the intake keeps one row per shoe / specimen, so the latest row of a lab reference is used).
     */
    private const LR = "(SELECT u.id, CONVERT(u.form_slug USING utf8mb4) COLLATE utf8mb4_unicode_ci form_slug, CONVERT(u.lab_ref_no USING utf8mb4) COLLATE utf8mb4_unicode_ci lab_ref_no, u.source_id,
            CONVERT(u.overall_result USING utf8mb4) COLLATE utf8mb4_unicode_ci overall_result, CONVERT(u.tested_date USING utf8mb4) COLLATE utf8mb4_unicode_ci tested_date,
            CONVERT(u.status USING utf8mb4) COLLATE utf8mb4_unicode_ci status, CONVERT(u.technician USING utf8mb4) COLLATE utf8mb4_unicode_ci technician,
            CONVERT(u.supplier USING utf8mb4) COLLATE utf8mb4_unicode_ci supplier, u.test_items_json
            FROM tbl_dtr_unified_labrefs u JOIN (SELECT MAX(id) mid FROM tbl_dtr_unified_labrefs WHERE form_slug <> 'bonding' GROUP BY form_slug, lab_ref_no) d ON d.mid = u.id
        UNION ALL
        SELECT b.BondingTestID, CONVERT('bonding' USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(b.LabRefNo USING utf8mb4) COLLATE utf8mb4_unicode_ci, b.BondingTestID,
            CONVERT(CASE UPPER(LEFT(b.Result, 1)) WHEN 'P' THEN 'Pass' WHEN 'T' THEN 'Tolerable' WHEN 'F' THEN 'Fail' ELSE '' END USING utf8mb4) COLLATE utf8mb4_unicode_ci,
            CONVERT(LEFT(CAST(b.TestedDate AS CHAR), 10) USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(IF(COALESCE(b.Result, '') = '', 'Pending', 'Complete') USING utf8mb4) COLLATE utf8mb4_unicode_ci,
            CONVERT(b.Technician USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(b.Factory USING utf8mb4) COLLATE utf8mb4_unicode_ci, NULL
            FROM tbl_dtr_bonding_info b JOIN (SELECT MAX(BondingTestID) mid FROM tbl_dtr_bonding_info GROUP BY LabRefNo) x ON x.mid = b.BondingTestID) lr";

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
        $ver   = \App\Libraries\DataCache::version();
        $out   = ['ok' => true, 'days' => $days, 'from' => $from, 'to' => $to];
        $db    = null;
        foreach ($want as $name => $methods) {
            $key = "labdash_v{$ver}_{$name}_{$days}_" . $to;
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
            FROM " . self::LR . " WHERE " . self::LR_DATE . ' BETWEEN ? AND ? ORDER BY lab_ref_no', [$start, $to]);
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
            $ov  = strtoupper(trim((string) $r['overall_result']));
            $res = in_array($ov, ['PASS', 'P'], true) ? 'Pass' : ($ov === 'TOLERABLE' ? 'Tolerable' : (in_array($ov, ['FAIL', 'FAILED'], true) ? 'Fail' : 'Other'));
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
        $rows  = $this->q($db, "SELECT form_slug, test_items_json FROM " . self::LR . "
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
        if ($db->tableExists('tbl_dtr_pull_info')) {
            return $this->q($db, "SELECT TRIM(REPLACE(i.Method, '  ', ' ')) method, i.EquipmentCode machine, COUNT(DISTINCT i.PullInfoID) samples, COUNT(t.PullTestID) pulls,
                    ROUND(AVG(t.PeakKgf), 2) avg_peak, ROUND(MIN(t.PeakKgf), 2) min_peak, ROUND(MAX(t.PeakKgf), 2) max_peak, MAX(i.TestedDate) last_test
                FROM tbl_dtr_pull_info i LEFT JOIN tbl_dtr_pull_tests t ON t.PullInfoID = i.PullInfoID
                WHERE i.TestedDate BETWEEN ? AND ? GROUP BY method, machine ORDER BY pulls DESC", [$from, $to]);
        }
        if (! $db->tableExists('tbl_dtr_pull_tests')) {
            return [];
        }
        $rows = $this->q($db, 'SELECT * FROM tbl_dtr_pull_tests WHERE TestedDate BETWEEN ? AND ?', [$from, $to]);
        $agg  = [];
        foreach ($rows as $r) {
            $method  = trim(preg_replace('/\s+/', ' ', (string) ($r['Method'] ?? '')));
            $machine = trim((string) ($r['EquipmentCode'] ?? ''));
            $k       = $method . '|' . $machine;
            $agg[$k] ??= ['method' => $method, 'machine' => $machine, 'samples' => 0, 'pulls' => 0, 'sum' => 0.0, 'cnt' => 0, 'min' => null, 'max' => null, 'last_test' => ''];
            $agg[$k]['samples']++;
            $td = (string) ($r['TestedDate'] ?? '');
            if ($td > $agg[$k]['last_test']) {
                $agg[$k]['last_test'] = $td;
            }
            $rowPulls = 0;
            for ($i = 1; $i <= 30; $i++) {
                $pv = $r["Peak{$i}"] ?? null;
                if ($pv !== null && $pv !== '' && is_numeric($pv)) {
                    $v = (float) $pv;
                    $agg[$k]['sum'] += $v;
                    $agg[$k]['cnt']++;
                    $agg[$k]['min'] = $agg[$k]['min'] === null ? $v : min($agg[$k]['min'], $v);
                    $agg[$k]['max'] = $agg[$k]['max'] === null ? $v : max($agg[$k]['max'], $v);
                    $rowPulls++;
                }
            }
            $agg[$k]['pulls'] += $rowPulls ?: (int) ($r['TestCount'] ?? 0);
        }
        $out = [];
        foreach ($agg as $g) {
            $out[] = [
                'method'    => $g['method'],
                'machine'   => $g['machine'],
                'samples'   => $g['samples'],
                'pulls'     => $g['pulls'],
                'avg_peak'  => $g['cnt'] > 0 ? round($g['sum'] / $g['cnt'], 2) : null,
                'min_peak'  => $g['min'] !== null ? round($g['min'], 2) : null,
                'max_peak'  => $g['max'] !== null ? round($g['max'], 2) : null,
                'last_test' => $g['last_test'],
            ];
        }
        usort($out, static fn ($a, $b) => $b['pulls'] <=> $a['pulls']);

        return $out;
    }

    /** Flex / abrasion testers and how far each running test is. */
    private function runningMachines($db): array
    {
        return $this->q($db, "SELECT labreference lab_ref, category, type, machine_number machine, status, target_cycles target, current_cycles current,
                recorded_cycles, operator, testdate FROM tbl_material_tests ORDER BY status = 'RUNNING' DESC, updated_at DESC LIMIT 12");
    }

    private function technicians($db, string $from, string $to): array
    {
        return $this->q($db, "SELECT COALESCE(NULLIF(TRIM(technician), ''), 'Not recorded') name, COUNT(*) n, SUM(overall_result IN ('Fail', 'FAILED')) f
            FROM " . self::LR . " WHERE " . self::LR_DATE . ' BETWEEN ? AND ? GROUP BY name ORDER BY n DESC LIMIT 8', [$from, $to]);
    }

    private function suppliers($db, string $from, string $to): array
    {
        return $this->q($db, "SELECT COALESCE(NULLIF(TRIM(supplier), ''), 'Not recorded') name, COUNT(*) n, SUM(overall_result IN ('Fail', 'FAILED')) f, SUM(overall_result = 'Tolerable') t
            FROM " . self::LR . " WHERE " . self::LR_DATE . ' BETWEEN ? AND ? GROUP BY name HAVING n > 0 ORDER BY f DESC, n DESC LIMIT 8', [$from, $to]);
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
            'leather' => 'Leather', 'label' => 'Labels', 'sklfm' => 'Sockliner & Foam', 'rubber' => 'Rubber Outsole', 'box' => 'Carton Box', 'insole' => 'Insole Board',
            'wholeshoe' => 'Whole Shoe', 'pulltest' => 'Pull Test'];
    }

    /** Ongoing / failed / waiting-for-report counts for Bonding, Material and Whole Shoe. */
    private function tiles($db, string $from, string $to): array
    {
        // the approved lab references are read once (a few dozen rows) instead of a per-row sub-query across two collations
        $approved = array_flip(array_column($this->q($db, "SELECT DISTINCT lab_ref_no FROM tbl_dtr_report_approvals WHERE status = 'approved'"), 'lab_ref_no'));
        $lr = $this->q($db, "SELECT CASE WHEN form_slug = 'bonding' THEN 'bonding' ELSE 'material' END AS grp, lab_ref_no,
                " . self::OPEN . " AND NOT (form_slug = 'bonding' AND EXISTS (SELECT 1 FROM tbl_dtr_bondingtracker tk WHERE tk.LabRefNo = lab_ref_no COLLATE utf8mb4_unicode_ci)) AS open_, " . self::FAILED . " AS fail_, " . self::LR_DATE . ' BETWEEN ? AND ? AS in_period
            FROM ' . self::LR . ' WHERE form_slug NOT IN (\'wholeshoe\', \'pulltest\') AND (status NOT LIKE \'Complete%\' OR ' . self::LR_DATE . ' BETWEEN ? AND ?)', [$from, $to, $from, $to]);
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
            AND (k.LabRefNo IS NULL OR NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo = k.LabRefNo COLLATE utf8mb4_unicode_ci AND b.Result IS NOT NULL))", [$from, $to])[0]['n'] ?? 0);

        // whole shoe: each test of each collected shoe, by the tracker's own status (a test lab ref has no row in the unified list)
        $ws = $this->q($db, "SELECT UPPER(COALESCE(Status, '')) st, UPPER(COALESCE(FinalResult, '')) fr, COUNT(*) n FROM tbl_dtr_wholeshoetracker
            WHERE CollectionDate BETWEEN ? AND ? GROUP BY st, fr", [$from, $to]);
        $w = ['ongoing' => 0, 'failed' => 0, 'for_report' => 0];
        foreach ($ws as $r) {
            if ($r['st'] !== 'COMPLETED') {
                $w['ongoing'] += (int) $r['n'];
            } elseif (str_starts_with($r['fr'], 'FAIL')) {
                $w['failed'] += (int) $r['n'];
            } else {
                $w['for_report'] += (int) $r['n'];
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
                SUM(overall_result IN ('Pass','PASS','P')) pass, SUM(overall_result = 'Tolerable') tol, SUM(overall_result IN ('Fail', 'FAILED')) fail,
                SUM(" . self::OPEN . ") ongoing, COUNT(*) total
            FROM " . self::LR . " WHERE " . self::LR_DATE . " BETWEEN ? AND ? OR (form_slug <> 'bonding' AND " . self::OPEN . ') GROUP BY form_slug ORDER BY total DESC', [$from, $to]);
        $names = $this->categoryNames();
        $bondOpen = $this->bondingOpen($db, $from, $to);   // same number as the Bonding tile: samples on the tracker without a result + bonding rows without one

        return array_map(static fn ($r) => [
            'slug' => $r['form_slug'], 'name' => $names[$r['form_slug']] ?? ucfirst((string) $r['form_slug']),
            'pass' => (int) $r['pass'], 'tolerable' => (int) $r['tol'], 'fail' => (int) $r['fail'],
            'ongoing' => $r['form_slug'] === 'bonding' ? $bondOpen : (int) $r['ongoing'], 'total' => (int) $r['total'] + ($r['form_slug'] === 'bonding' ? $bondOpen : 0),
        ], $rows);
    }

    /** Bonding still without a result: tracker samples of the period + bonding rows (retests, first articles) that are not on the tracker. */
    private function bondingOpen($db, string $from, string $to): int
    {
        $a = (int) ($this->q($db, 'SELECT COUNT(*) n FROM ' . self::LR . " WHERE form_slug = 'bonding' AND status NOT LIKE 'Complete%'
            AND NOT EXISTS (SELECT 1 FROM tbl_dtr_bondingtracker tk WHERE tk.LabRefNo = lab_ref_no COLLATE utf8mb4_unicode_ci)")[0]['n'] ?? 0);
        $b = (int) ($this->q($db, "SELECT COUNT(*) n FROM tbl_dtr_bondingtracker k WHERE k.Shutdown = 0 AND k.CollectionDate BETWEEN ? AND ?
            AND (k.LabRefNo IS NULL OR NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo = k.LabRefNo COLLATE utf8mb4_unicode_ci AND b.Result IS NOT NULL))", [$from, $to])[0]['n'] ?? 0);

        return $a + $b;
    }

    /** Bonding samples on the tracker (newest first) with their result when tested. */
    private function ongoingBonding($db): array
    {
        return $this->q($db, "SELECT k.LabRefNo lab_ref, COALESCE(k.ProductionCode, '') prod_code, COALESCE(k.Style, b.ModelStyle, '') style,
                CONCAT_WS('', k.Building, '') building, k.LineNo line, k.ProductionDate prod_date, b.TestedDate test_date,
                COALESCE(b.Technician, k.Technician, '') technician, COALESCE(b.Result, CASE WHEN k.Shutdown = 1 THEN 'SHUTDOWN' ELSE 'Waiting' END) result,
                k.TestType test_type, x.id bonding_id
            FROM (SELECT * FROM tbl_dtr_bondingtracker ORDER BY CollectionDate DESC, TrackerID DESC LIMIT 40) k
            LEFT JOIN (SELECT k2.TrackerID, (SELECT MAX(i.BondingTestID) FROM tbl_dtr_bonding_info i WHERE i.LabRefNo = k2.LabRefNo COLLATE utf8mb4_unicode_ci AND i.Result IS NOT NULL) id
                       FROM (SELECT TrackerID, LabRefNo FROM tbl_dtr_bondingtracker ORDER BY CollectionDate DESC, TrackerID DESC LIMIT 40) k2) x ON x.TrackerID = k.TrackerID
            LEFT JOIN tbl_dtr_bonding_info b ON b.BondingTestID = x.id
            ORDER BY k.CollectionDate DESC, k.TrackerID DESC");
    }

    /** Progress per test type: whole-shoe tests (tracker) and material categories with work open. */
    private function tracking($db, string $from, string $to): array
    {
        $out = [];
        $ws  = $this->q($db, "SELECT CASE WHEN TestType IN ('UV', 'UV Light') THEN 'UV Light' ELSE TestType END t, COUNT(*) total, SUM(UPPER(Status) = 'COMPLETED') done,
                SUM(UPPER(COALESCE(FinalResult, '')) LIKE 'FAIL%') fail
            FROM tbl_dtr_wholeshoetracker WHERE CollectionDate BETWEEN ? AND ? AND COALESCE(TestType, '') <> '' GROUP BY t ORDER BY t", [$from, $to]);
        foreach ($ws as $r) {
            $out[] = ['group' => 'Whole Shoe', 'test' => $r['t'], 'total' => (int) $r['total'], 'done' => (int) $r['done'], 'fail' => (int) $r['fail']];
        }
        $bt = $this->q($db, "SELECT COUNT(*) total, SUM(b.Result IS NOT NULL) done, SUM(UPPER(b.Result) LIKE 'F%') fail FROM tbl_dtr_bondingtracker k
            LEFT JOIN (SELECT LabRefNo, MAX(Result) Result FROM tbl_dtr_bonding_info WHERE Result IS NOT NULL GROUP BY LabRefNo) b
            ON b.LabRefNo = k.LabRefNo COLLATE utf8mb4_unicode_ci WHERE k.Shutdown = 0 AND k.CollectionDate BETWEEN ? AND ?", [$from, $to])[0] ?? null;
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
