<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Calendar Monitoring: every test of the lab on a month / week calendar.
 *
 * Where the data comes from (dbnexus):
 *  - tbl_dtr_unified_labrefs  one row per finished or logged report of every kind (bonding, whole shoe, leather, fabric, rubber, ...): the
 *                             day is its Tested Date (text "08 Nov 2026"), the status is Complete / Pending / In Progress, the result Pass / Fail / Tolerable
 *  - tbl_dtr_bonding_info     the Production Date of a bonding report (joined on source_id)
 *  - tbl_dtr_bondingtracker   shoes collected from the lines that have no report yet (Lacking = 1 -> pending, else ongoing)
 *  - tbl_dtr_wholeshoetracker whole shoe tests with their own Status (RUNNING / PENDING / COMPLETED) and FinalResult
 *  - tbl_material_tests       machine tests (flexing, UV light, washing, ...) with RUNNING / PENDING / COMPLETED and their outcome
 * A Lab Ref that is already in the unified table is not counted a second time from a tracker.
 */
class CalendarMonitoringController extends BaseController
{
    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    /** Main Calendar Monitoring View */
    public function index(): string
    {
        return $this->render('calendar/index', [
            'title'    => 'Calendar Monitoring',
            'withCss'  => 'calendar.css',
            'withJs'   => ['calendar-monitoring.js'],
            'today'    => date('Y-m-d'),
            'curYear'  => (int) date('Y'),
            'curMonth' => (int) date('m'),
        ]);
    }

    private static function ymd(?string $s): string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        $t = strtotime($s);

        return $t ? date('Y-m-d', $t) : '';
    }

    private static function status(string $s): string
    {
        $s = strtolower(trim($s));
        if ($s === '') {
            return 'completed';
        }
        if (str_contains($s, 'progress') || $s === 'running') {
            return 'ongoing';
        }
        if (str_contains($s, 'pending') || $s === 'queued' || $s === 'lacking') {
            return 'pending';
        }

        return 'completed';   // Complete, Completed, Approved
    }

    /** Pass / Fail / Tolerable, or '' when the lab did not rate it (running, pending, "UNRATED", odd notes) */
    private static function result(string $r): string
    {
        $r = strtoupper(trim($r));
        if (in_array($r, ['PASS', 'PASSED', 'P'], true)) {
            return 'PASS';
        }
        if (in_array($r, ['FAIL', 'FAILED', 'F'], true)) {
            return 'FAIL';
        }
        if (in_array($r, ['TOLERABLE', 'TOL', 'TOLE'], true)) {
            return 'TOLERABLE';
        }

        return '';
    }

    /** the address of the actual report of a Lab Ref: the DTR page opens that record by itself (no searching) */
    public static function sheetUrl(string $domain, string $slug, int $sourceId, string $labRef, bool $exact): string
    {
        $ref = rawurlencode($labRef);
        if ($domain === 'wholeshoe') {
            return site_url('dtr?open=wholeshoe&labref=' . $ref);
        }
        if ($domain === 'bonding') {
            return $exact && $sourceId > 0 ? site_url('dtr?open=bonding:' . $sourceId) : site_url('dtr?open=bonding&ref=' . $ref);
        }

        return $sourceId > 0 && preg_match('/^[a-z]+$/', $slug) ? site_url('dtr?open=' . $slug . ':' . $sourceId) : site_url('dtr?q=' . $ref);
    }

    private static function domainOf(string $slug): string
    {
        return $slug === 'bonding' ? 'bonding' : (in_array($slug, ['wholeshoe', 'whole'], true) ? 'wholeshoe' : 'material');
    }

    /** every test whose day (test date, else collection date) falls in $start..$end, as one normal shape */
    public function records(string $start, string $end): array
    {
        $db   = $this->db();
        $rows = [];
        $seen = [];

        // 1. reports (all categories)
        $u = $db->query("
            SELECT u.id, u.lab_ref_no, u.tr_no, u.form_slug, u.form_category, u.material_type, u.material_description, u.supplier, u.model_style, u.color, u.mtl_no,
                   u.technician, u.status, u.overall_result, u.erp_status, u.source_table, u.source_id,
                   DATE_FORMAT(COALESCE(STR_TO_DATE(NULLIF(u.tested_date, ''), '%d %b %Y'), STR_TO_DATE(NULLIF(u.finished_date, ''), '%d %b %Y'),
                                        STR_TO_DATE(NULLIF(u.received_date, ''), '%d %b %Y')), '%Y-%m-%d') AS d,
                   DATE_FORMAT(STR_TO_DATE(NULLIF(u.received_date, ''), '%d %b %Y'), '%Y-%m-%d') AS received,
                   b.ProductionDate AS b_prod, b.Remarks AS b_remarks, b.ModeOfFailure AS b_mode
            FROM tbl_dtr_unified_labrefs u
            LEFT JOIN tbl_dtr_bonding_info b ON u.source_table = 'tbl_dtr_bonding_info' AND b.BondingTestID = u.source_id
            HAVING d BETWEEN ? AND ?
            ORDER BY d, u.lab_ref_no", [$start, $end])->getResultArray();
        foreach ($u as $r) {
            $dom  = self::domainOf((string) $r['form_slug']);
            $cat  = trim(preg_replace('/\s*Test Report\s*/i', ' ', (string) $r['form_category']));
            $st   = self::status((string) $r['status']);
            $rows[] = [
                'id'           => 'u_' . $r['id'],
                'raw_id'       => (int) $r['id'],
                'domain'       => $dom,
                'domain_label' => $dom === 'bonding' ? 'Bonding (U60)' : ($dom === 'wholeshoe' ? 'Whole Shoe' : 'Material'),
                'category'     => $cat,
                'lab_ref_no'   => (string) $r['lab_ref_no'],
                'tr_no'        => (string) $r['tr_no'],
                'test_type'    => $cat,
                'style'        => (string) ($r['model_style'] ?: $r['material_description'] ?: $r['material_type']),
                'color'        => (string) $r['color'],
                'mtl'          => (string) $r['mtl_no'],
                'supplier'     => (string) $r['supplier'],
                'test_date'    => (string) $r['d'],
                'prod_date'    => self::ymd($r['b_prod'] ?: ''),
                'received'     => (string) $r['received'],
                'status'       => $st,
                'result'       => $st === 'completed' ? self::result((string) $r['overall_result']) : '',
                'result_raw'   => (string) $r['overall_result'],
                'technician'   => (string) $r['technician'],
                'erp_status'   => (string) $r['erp_status'],
                'remarks'      => trim((string) ($r['b_mode'] ? 'Mode of failure: ' . $r['b_mode'] : ($r['b_remarks'] ?: ''))),
                'time_slot'    => '',
                'view_url'     => self::sheetUrl($dom, (string) $r['form_slug'], (int) $r['source_id'], (string) $r['lab_ref_no'], $r['source_table'] === 'tbl_dtr_bonding_info'),
            ];
            $seen[strtoupper((string) $r['lab_ref_no'])] = true;
        }

        // 2. whole shoe tests being run
        $w = $db->query("
            SELECT TrackerID, LabRefNo, WholeshoeLabRefNo, TestType, Style, Colorway, MTL, Status, FinalResult, Findings, EncodedBy, Building, `LineNo`,
                   CollectionDate, ProductionDate, DATE_FORMAT(COALESCE(TimeStarted, CreatedAt), '%H:%i') AS t
            FROM tbl_dtr_wholeshoetracker
            WHERE CollectionDate BETWEEN ? AND ?
            ORDER BY CollectionDate, TrackerID", [$start, $end])->getResultArray();
        foreach ($w as $r) {
            if (isset($seen[strtoupper((string) $r['LabRefNo'])])) {
                continue;
            }
            $st = self::status((string) $r['Status']);
            $rows[] = [
                'id' => 'w_' . $r['TrackerID'], 'raw_id' => (int) $r['TrackerID'], 'domain' => 'wholeshoe', 'domain_label' => 'Whole Shoe', 'category' => 'Whole Shoe',
                'lab_ref_no' => (string) $r['LabRefNo'], 'tr_no' => (string) $r['WholeshoeLabRefNo'], 'test_type' => (string) ($r['TestType'] ?: 'Whole Shoe Performance'),
                'style' => (string) $r['Style'], 'color' => (string) $r['Colorway'], 'mtl' => (string) $r['MTL'], 'supplier' => '',
                'test_date' => self::ymd($r['CollectionDate']), 'prod_date' => self::ymd($r['ProductionDate']), 'received' => '',
                'status' => $st, 'result' => $st === 'completed' ? self::result((string) $r['FinalResult']) : '', 'result_raw' => (string) $r['FinalResult'],
                'technician' => (string) $r['EncodedBy'], 'erp_status' => '',
                'remarks' => (string) ($r['Findings'] ?: 'Line ' . $r['LineNo'] . ', Building ' . $r['Building']),
                'time_slot' => (string) $r['t'], 'view_url' => self::sheetUrl('wholeshoe', 'wholeshoe', 0, (string) $r['LabRefNo'], false),
            ];
            $seen[strtoupper((string) $r['LabRefNo'])] = true;
        }

        // 3. bonding shoes collected from the lines that have no report yet
        $bt = $db->query("
            SELECT TrackerID, LabRefNo, Style, Colorway, MTL, TestType, Technician, EncodedBy, Lacking, Building, `LineNo`, Side, CollectionDate, ProductionDate,
                   DATE_FORMAT(CreatedAt, '%H:%i') AS t
            FROM tbl_dtr_bondingtracker
            WHERE CollectionDate BETWEEN ? AND ?
            ORDER BY CollectionDate, TrackerID", [$start, $end])->getResultArray();
        foreach ($bt as $r) {
            if (isset($seen[strtoupper((string) $r['LabRefNo'])])) {
                continue;
            }
            $rows[] = [
                'id' => 'bt_' . $r['TrackerID'], 'raw_id' => (int) $r['TrackerID'], 'domain' => 'bonding', 'domain_label' => 'Bonding (U60)', 'category' => 'Whole Shoe Bonding (U60)',
                'lab_ref_no' => (string) $r['LabRefNo'], 'tr_no' => '', 'test_type' => 'Whole Shoe Bonding (U60)',
                'style' => (string) $r['Style'], 'color' => (string) $r['Colorway'], 'mtl' => (string) $r['MTL'], 'supplier' => '',
                'test_date' => self::ymd($r['CollectionDate']), 'prod_date' => self::ymd($r['ProductionDate']), 'received' => '',
                'status' => (int) $r['Lacking'] === 1 ? 'pending' : 'ongoing', 'result' => '', 'result_raw' => '',
                'technician' => (string) ($r['Technician'] ?: $r['EncodedBy']), 'erp_status' => '',
                'remarks' => 'Line ' . $r['LineNo'] . ' ' . $r['Side'] . ', Building ' . $r['Building'] . ((int) $r['Lacking'] === 1 ? ' - lacking' : ' - collected, report not saved yet'),
                'time_slot' => (string) $r['t'], 'view_url' => self::sheetUrl('bonding', 'bonding', 0, (string) $r['LabRefNo'], false),
            ];
        }

        // 4. machine tests (flexing, UV light, washing ...)
        $m = $db->query("
            SELECT id, category, labreference, testdate, type, machine_number, current_cycles, target_cycles, status, overall_outcome, technician, operator, remarks,
                   DATE_FORMAT(COALESCE(timer_started_at, created_at), '%H:%i') AS t
            FROM tbl_material_tests
            WHERE testdate BETWEEN ? AND ?
            ORDER BY testdate, id", [$start, $end])->getResultArray();
        foreach ($m as $r) {
            if ($r['labreference'] !== '' && isset($seen[strtoupper((string) $r['labreference'])])) {
                continue;
            }
            $st = self::status((string) $r['status']);
            $rows[] = [
                'id' => 'm_' . $r['id'], 'raw_id' => (int) $r['id'], 'domain' => 'material', 'domain_label' => 'Material', 'category' => ucfirst((string) $r['category']),
                'lab_ref_no' => (string) $r['labreference'], 'tr_no' => '', 'test_type' => ucfirst((string) $r['category']) . ' test',
                'style' => (string) $r['type'], 'color' => '', 'mtl' => '', 'supplier' => '',
                'test_date' => self::ymd($r['testdate']), 'prod_date' => '', 'received' => '',
                'status' => $st, 'result' => $st === 'completed' ? self::result((string) $r['overall_outcome']) : '', 'result_raw' => (string) $r['overall_outcome'],
                'technician' => (string) ($r['technician'] ?: $r['operator']), 'erp_status' => '',
                'remarks' => 'Machine ' . ($r['machine_number'] ?: '-') . ' - ' . (int) $r['current_cycles'] . ' / ' . (int) $r['target_cycles'] . ' cycles' . ($r['remarks'] ? ' - ' . $r['remarks'] : ''),
                'time_slot' => (string) $r['t'], 'view_url' => site_url('materials?ref=' . rawurlencode((string) $r['labreference'])),
            ];
        }

        return $rows;
    }

    private static function blankDay(string $d): array
    {
        $t = strtotime($d);
        $c = static fn () => ['total' => 0, 'ongoing' => 0, 'pending' => 0, 'completed' => 0, 'pass' => 0, 'fail' => 0, 'tolerable' => 0];

        return ['date' => $d, 'day_name' => date('D', $t), 'day_num' => (int) date('j', $t), 'total' => 0, 'ongoing' => 0, 'pending' => 0, 'completed' => 0,
            'passed' => 0, 'failed' => 0, 'tolerable' => 0, 'bonding' => $c(), 'material' => $c(), 'wholeshoe' => $c(), 'preview_events' => []];
    }

    /**
     * GET /calendar-monitoring/events?start=YYYY-MM-DD&end=YYYY-MM-DD
     * The counts of every day of the range (per kind of test, status and result), a few preview chips per day, and the tests of the range for the week view.
     */
    public function events(): ResponseInterface
    {
        $start = (string) ($this->request->getGet('start') ?? date('Y-m-01', strtotime('-7 days')));
        $end   = (string) ($this->request->getGet('end') ?? date('Y-m-t', strtotime('+7 days')));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = date('Y-m-t');
        }
        if (strtotime($end) - strtotime($start) > 62 * 86400) {   // never more than about two months at once
            $end = date('Y-m-d', strtotime($start . ' +62 days'));
        }

        $days = [];
        for ($t = strtotime($start); $t <= strtotime($end); $t = strtotime('+1 day', $t)) {
            $days[date('Y-m-d', $t)] = self::blankDay(date('Y-m-d', $t));
        }

        $items = [];
        foreach ($this->records($start, $end) as $r) {
            $d = $r['test_date'];
            if (! isset($days[$d])) {
                continue;
            }
            $dom = $r['domain'];
            $st  = $r['status'];
            foreach ([&$days[$d][$dom], &$days[$d]] as &$bucket) {
                $bucket['total']++;
                $bucket[$st]++;
                if ($r['result'] === 'PASS') {
                    $bucket[isset($bucket['pass']) ? 'pass' : 'passed']++;
                } elseif ($r['result'] === 'FAIL') {
                    $bucket[isset($bucket['fail']) ? 'fail' : 'failed']++;
                } elseif ($r['result'] === 'TOLERABLE') {
                    $bucket['tolerable']++;
                }
            }
            unset($bucket);
            if (count($days[$d]['preview_events']) < 4) {
                $days[$d]['preview_events'][] = ['id' => $r['id'], 'domain' => $dom, 'test_type' => $r['test_type'], 'lab_ref' => $r['lab_ref_no'], 'style' => $r['style'],
                    'status' => $st, 'result' => $r['result'], 'time_slot' => $r['time_slot']];
            }
            // the week view lists the tests themselves
            $items[] = ['id' => $r['id'], 'domain' => $dom, 'test_type' => $r['test_type'], 'lab_ref' => $r['lab_ref_no'], 'style' => $r['style'], 'colorway' => $r['color'],
                'test_date' => $r['test_date'], 'prod_date' => $r['prod_date'], 'result' => $r['result'], 'status' => $st, 'technician' => $r['technician'],
                'time_slot' => $r['time_slot'], 'date_key' => $d];
        }

        $sum = static fn (string $k) => array_sum(array_column($days, $k));

        return $this->response->setJSON([
            'ok'         => true,
            'start'      => $start,
            'end'        => $end,
            'days'       => $days,
            'week_items' => array_slice($items, 0, 1500),
            'kpis'       => ['total_tests' => $sum('total'), 'ongoing' => $sum('ongoing'), 'pending' => $sum('pending'), 'completed' => $sum('completed'),
                'passed' => $sum('passed'), 'failed' => $sum('failed'), 'tolerable' => $sum('tolerable')],
        ]);
    }

    /** GET /calendar-monitoring/day-details?date=YYYY-MM-DD: every test of that day with status, result, Lab Ref, test and production date ... */
    public function dayDetails(): ResponseInterface
    {
        $date = (string) ($this->request->getGet('date') ?? date('Y-m-d'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $items = $this->records($date, $date);
        $n     = static fn (callable $f) => count(array_filter($items, $f));

        return $this->response->setJSON([
            'ok'             => true,
            'date'           => $date,
            'formatted_date' => date('l, F j, Y', strtotime($date)),
            'summary'        => [
                'total'     => count($items),
                'ongoing'   => $n(static fn ($i) => $i['status'] === 'ongoing'),
                'pending'   => $n(static fn ($i) => $i['status'] === 'pending'),
                'completed' => $n(static fn ($i) => $i['status'] === 'completed'),
                'bonding'   => $n(static fn ($i) => $i['domain'] === 'bonding'),
                'material'  => $n(static fn ($i) => $i['domain'] === 'material'),
                'wholeshoe' => $n(static fn ($i) => $i['domain'] === 'wholeshoe'),
                'passed'    => $n(static fn ($i) => $i['result'] === 'PASS'),
                'failed'    => $n(static fn ($i) => $i['result'] === 'FAIL'),
                'tolerable' => $n(static fn ($i) => $i['result'] === 'TOLERABLE'),
            ],
            'items'          => $items,
        ]);
    }

    /** Quick stats for badges */
    public function quickStats(): ResponseInterface
    {
        $db = $this->db();
        $n  = (int) $db->table('tbl_dtr_bondingtracker')->where('Lacking', 1)->countAllResults()
            + (int) $db->table('tbl_material_tests')->where('status', 'RUNNING')->countAllResults()
            + (int) $db->table('tbl_dtr_wholeshoetracker')->where('Status', 'RUNNING')->countAllResults();

        return $this->response->setJSON(['ok' => true, 'today' => date('Y-m-d'), 'ongoing' => $n]);
    }
}
