<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Quality Insights: where the quality problems are. Three views of the same data the lab already records:
 *  - Line map     today's (or any day's) bonding result of every assembly line: green / amber / red
 *  - Failure modes failures and tolerables of a period by line, cement supplier, style or failure mode
 *  - Suppliers    pass rate per supplier and material over 30 / 90 / 180 days
 * Plain counts only (no guessing): the numbers are the same ones the Calendar Monitoring shows. Access is the module "insights" of the roles matrix.
 */
class QualityInsightsController extends BaseController
{
    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    public function index(): string
    {
        return $this->render('quality/index', [
            'title'   => 'Quality Insights',
            'withCss' => ['calendar.css', 'quality.css'],
            'withJs'  => ['quality-insights.js'],
            'today'   => date('Y-m-d'),
        ]);
    }

    /** "DTP20261103SHA9-R-R01" -> "A9", "DTP20261116FASHB14-R" (first article) -> "B14" (the building letter and the line number are part of the Lab Ref) */
    public static function lineOf(string $ref): string
    {
        return preg_match('/^DTP\d{8}(?:FA)?[A-Z]{2,3}([A-Z])(\d+)/', strtoupper($ref), $m) ? $m[1] . $m[2] : '';
    }

    private static function result(string $r): string
    {
        $r = strtoupper(trim($r));

        return in_array($r, ['PASS', 'PASSED', 'P'], true) ? 'PASS' : (in_array($r, ['FAIL', 'FAILED', 'F'], true) ? 'FAIL' : (in_array($r, ['TOLERABLE', 'TOL', 'TOLE'], true) ? 'TOLERABLE' : ''));
    }

    private static function date(?string $s, string $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $s) ? (string) $s : $fallback;
    }

    private static function natural(string $a, string $b): int
    {
        return strnatcasecmp($a, $b);
    }

    // The line map counts the way Analytics does: by the day the shoes were PRODUCED on the line (ProductionDate, else the date inside the Lab Ref) and by
    // the Result saved in tbl_dtr_bonding_info (one row per Lab Ref: the one that has a result). A test done two days later still belongs to its production day.
    private const BOND_DATE  = "COALESCE((CASE WHEN ProductionDate REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(ProductionDate, 7, 4), '-', SUBSTR(ProductionDate, 1, 2), '-', SUBSTR(ProductionDate, 4, 2)) WHEN ProductionDate REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(ProductionDate, 10) WHEN LabRefNo REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(LabRefNo, 4, 4), '-', SUBSTR(LabRefNo, 8, 2), '-', SUBSTR(LabRefNo, 10, 2)) END), LEFT(NULLIF(TestedDate, ''), 10), LEFT(NULLIF(ReceivedDate, ''), 10))";
    private const BOND_DEDUP = "(LabRefNo IS NULL OR LabRefNo = '' OR BondingTestID IN (SELECT keep_id FROM (SELECT COALESCE(MAX(CASE WHEN Result IS NOT NULL AND Result <> '' THEN BondingTestID END), MAX(BondingTestID)) AS keep_id FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' GROUP BY LabRefNo) k))";

    /** the same normalising as BOND_DATE, for the TestedDate column: 2026-11-16, 16-11-2026 style (MM-DD-YYYY) or nothing */
    private const TEST_DATE = "(CASE WHEN TestedDate REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(TestedDate, 7, 4), '-', SUBSTR(TestedDate, 1, 2), '-', SUBSTR(TestedDate, 4, 2)) WHEN TestedDate REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(TestedDate, 10) END)";

    /** how the line map assigns a Lab Ref to a day: 'tested' (the day its result was tested; waiting shoes by their production day) or 'production' */
    private function basis(): string
    {
        return $this->request->getGet('basis') === 'production' ? 'production' : 'tested';
    }

    /**
     * @return array<string,array{ref:string,result:string,line:string,day:string}> the bonding Lab Refs of from..to (trials, shutdowns and LACKING rows left out), by Lab Ref.
     * Result = the saved result of tbl_dtr_bonding_info (one row per Lab Ref: the one that has a result).
     */
    private function producedRefs(string $from, string $to, string $basis = 'tested'): array
    {
        $bd   = self::BOND_DATE;
        $td   = self::TEST_DATE;
        $rows = $this->db()->query("
            SELECT LabRefNo ref, Result, {$bd} AS pd, {$td} AS td FROM tbl_dtr_bonding_info
            WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' AND LabRefNo NOT LIKE '%TRIAL%' AND LabRefNo NOT LIKE '%SHUTDOWN%' AND COALESCE(POLotNO, '') <> 'SHUTDOWN'
              AND COALESCE(ModelStyle, '') <> 'SHUTDOWN' AND COALESCE(TestType, '') <> 'LACKING' AND " . self::BOND_DEDUP . "
              AND EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs ul WHERE ul.form_slug = 'bonding' AND ul.lab_ref_no = LabRefNo)
            HAVING (pd BETWEEN ? AND ?) OR (td BETWEEN ? AND ?)", [$from, $to, $from, $to])->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $line = self::lineOf((string) $r['ref']);
            if ($line === '') {
                continue;
            }
            $res = self::result((string) $r['Result']);
            // by tested date: a tested Lab Ref sits on the day it was tested; one with no result yet sits on its production day
            $day = $basis === 'tested' && $res !== '' && $r['td'] ? (string) $r['td'] : (string) $r['pd'];
            if ($day >= $from && $day <= $to) {
                $out[(string) $r['ref']] = ['ref' => (string) $r['ref'], 'result' => $res, 'line' => $line, 'day' => $day];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------------------------ line map
    /** the period asked for: from / to (a day, a week or a month), at most one year */
    private function period(): array
    {
        $to   = self::date($this->request->getGet('to'), self::date($this->request->getGet('date'), date('Y-m-d')));
        $from = self::date($this->request->getGet('from'), $to);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if (strtotime($to) - strtotime($from) > 366 * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -366 days'));
        }

        return [$from, $to];
    }

    /** GET quality-insights/line-map?from=&to= */
    public function lineMap(): ResponseInterface
    {
        [$from, $to] = $this->period();
        $basis   = $this->basis();
        $payload = \App\Libraries\DataCache::remember("qi_linemap4_{$basis}_{$from}_{$to}", 120, function () use ($from, $to, $basis) {
            $db = $this->db();

            // every line the lab has ever collected from, grouped by building
            $lines = [];
            foreach ($db->query('SELECT DISTINCT Building b, `LineNo` l FROM tbl_dtr_bondingtracker WHERE `LineNo` <> ""')->getResultArray() as $r) {
                $l = strtoupper(trim((string) $r['l']));
                $l = preg_match('/^[A-Z]/', $l) ? $l : strtoupper(trim((string) $r['b'])) . $l;
                if ($l !== '') {
                    $lines[$l] = true;
                }
            }
            $tile = static fn (string $l) => ['line' => $l, 'tested' => 0, 'pass' => 0, 'tolerable' => 0, 'fail' => 0, 'pending' => 0, 'color' => 'grey', 'fail_refs' => [], 'tol_refs' => []];
            $tiles = [];
            foreach (array_keys($lines) as $l) {
                $tiles[$l] = $tile($l);
            }

            // tested: the saved results of the shoes produced in the period
            $produced = $this->producedRefs($from, $to, $basis);
            foreach ($produced as $r) {
                $l = $r['line'];
                $tiles[$l] ??= $tile($l);
                if ($r['result'] === '') {
                    $tiles[$l]['pending']++;      // collected from the line, no result saved yet
                    continue;
                }
                $tiles[$l]['tested']++;
                if ($r['result'] === 'PASS') {
                    $tiles[$l]['pass']++;
                } elseif ($r['result'] === 'FAIL') {
                    $tiles[$l]['fail']++;
                    $tiles[$l]['fail_refs'][] = $r['ref'];
                } else {
                    $tiles[$l]['tolerable']++;
                    $tiles[$l]['tol_refs'][] = $r['ref'];
                }
            }
            // collected from the line but not yet a bonding report at all (the tracker rows that are not tested)
            foreach ((new CalendarMonitoringController())->records($from, $to) as $r) {
                if ($r['domain'] !== 'bonding' || $r['status'] === 'completed' || stripos($r['lab_ref_no'], 'TRIAL') !== false || isset($produced[$r['lab_ref_no']])) {
                    continue;
                }
                $l = self::lineOf($r['lab_ref_no']);
                if ($l !== '') {
                    $tiles[$l] ??= $tile($l);
                    $tiles[$l]['pending']++;
                }
            }

            $tot = ['tested' => 0, 'pass' => 0, 'tolerable' => 0, 'fail' => 0, 'pending' => 0, 'lines_red' => 0, 'lines_amber' => 0, 'lines_green' => 0];
            $byB = [];
            foreach ($tiles as $l => &$t) {
                $t['color'] = $t['fail'] > 0 ? 'red' : (($t['tolerable'] > 0 || $t['pending'] > 0) ? 'amber' : ($t['tested'] > 0 ? 'green' : 'grey'));
                foreach (['tested', 'pass', 'tolerable', 'fail', 'pending'] as $k) {
                    $tot[$k] += $t[$k];
                }
                if ($t['color'] !== 'grey') {
                    $tot['lines_' . $t['color']]++;
                }
                $byB[substr($l, 0, 1)][] = $t;
            }
            unset($t);
            ksort($byB);
            $buildings = [];
            foreach ($byB as $b => $ls) {
                usort($ls, static fn ($x, $y) => self::natural($x['line'], $y['line']));
                $buildings[] = ['building' => $b, 'lines' => $ls];
            }

            return ['ok' => true, 'from' => $from, 'to' => $to, 'basis' => $basis, 'buildings' => $buildings, 'totals' => $tot];
        });

        return $this->response->setJSON($payload);
    }

    /** GET quality-insights/line-details?from=&to=&line=A4: the tests of that line in the period (same shape as the Calendar day panel) */
    public function lineDetails(): ResponseInterface
    {
        [$from, $to] = $this->period();
        $line = strtoupper(trim((string) $this->request->getGet('line')));
        $items = [];
        // the Lab Refs of this line in the period (by the chosen date basis); a test may be done days later, so the Calendar rows are looked up over a longer window
        $mine = array_filter($this->producedRefs($from, $to, $this->basis()), static fn ($r) => $r['line'] === $line);
        $cal  = [];
        foreach ((new CalendarMonitoringController())->records(date('Y-m-d', strtotime($from . ' -30 days')), date('Y-m-d', strtotime($to . ' +30 days'))) as $r) {
            if ($r['domain'] === 'bonding' && self::lineOf($r['lab_ref_no']) === $line && stripos($r['lab_ref_no'], 'TRIAL') === false) {
                $cal[$r['lab_ref_no']] = $r;
            }
        }
        // the tested ones come straight from the bonding report (tbl_dtr_bonding_info): style, colour, tested date, result, mode of failure, cause, cement, technician
        if ($mine) {
            $in   = implode(',', array_map(fn ($x) => $this->db()->escape($x), array_keys($mine)));
            $rows = $this->db()->query("SELECT LabRefNo, ModelStyle, Color, TestedDate, Technician, Result, ModeOfFailure, PossibleCause, Remarks, AdhesiveSupplier, TestType, POLotNO, AgeCategory, STD
                FROM tbl_dtr_bonding_info WHERE LabRefNo IN ({$in}) AND " . self::BOND_DEDUP)->getResultArray();
            foreach ($rows as $r) {
                $ref = (string) $r['LabRefNo'];
                $res = self::result((string) $r['Result']);
                if ($res === '') {
                    continue;      // waiting: shown from the Calendar rows below
                }
                $c    = $cal[$ref] ?? [];
                $bits = [];
                if (trim((string) $r['ModeOfFailure']) !== '') {
                    $bits[] = 'Mode of failure: ' . trim((string) $r['ModeOfFailure']);
                }
                if (trim((string) $r['PossibleCause']) !== '') {
                    $bits[] = 'Possible cause: ' . trim((string) $r['PossibleCause']);
                }
                if (trim((string) $r['AdhesiveSupplier']) !== '') {
                    $bits[] = 'Cement: ' . $this->alias((string) $r['AdhesiveSupplier']);
                }
                if (trim((string) $r['Remarks']) !== '') {
                    $bits[] = 'Remarks: ' . trim((string) $r['Remarks']);
                }
                $items[] = [
                    'id' => 'b_' . $ref, 'domain' => 'bonding', 'domain_label' => 'Bonding (U60)', 'category' => 'Whole Shoe Bonding (U60)',
                    'lab_ref_no' => $ref, 'test_type' => trim((string) $r['TestType']) !== '' ? (string) $r['TestType'] : 'Bonding',
                    'style' => trim((string) $r['ModelStyle'] . ' ' . (trim((string) $r['Color']) !== '' ? '/ ' . trim((string) $r['Color']) : '')),
                    'test_date' => $mine[$ref]['day'] === '' ? substr((string) $r['TestedDate'], 0, 10) : (substr((string) $r['TestedDate'], 0, 10) ?: $mine[$ref]['day']),
                    'status' => 'completed', 'result' => $res, 'technician' => (string) $r['Technician'], 'remarks' => implode("\n", $bits),
                    'view_url' => (string) ($c['view_url'] ?? ''),
                ];
            }
        }
        // shoes collected from the line with no result yet
        foreach ($cal as $ref => $r) {
            if ($r['status'] !== 'completed' && (isset($mine[$ref]) || ($r['test_date'] >= $from && $r['test_date'] <= $to)) && ! array_filter($items, static fn ($i) => $i['lab_ref_no'] === $ref)) {
                $items[] = $r;
            }
        }
        $rank = ['FAIL' => 0, 'TOLERABLE' => 1, '' => 2, 'PASS' => 3];
        usort($items, static fn ($x, $y) => [$rank[$x['result']] ?? 2, $x['lab_ref_no']] <=> [$rank[$y['result']] ?? 2, $y['lab_ref_no']]);   // failures first

        return $this->response->setJSON(['ok' => true, 'from' => $from, 'to' => $to, 'line' => $line, 'items' => $items]);
    }

    // ------------------------------------------------------------------------------------------------------------------ failure modes
    /** the bonding reports of a period, one normal row each */
    private function bondingRows(string $from, string $to): array
    {
        $rows = $this->db()->query("
            SELECT BondingTestID id, LabRefNo ref, Result, ModeOfFailure mode, AdhesiveSupplier cement, ModelStyle style, TestedDate, TestType, Technician
            FROM tbl_dtr_bonding_info
            WHERE TestedDate BETWEEN ? AND ? AND LabRefNo NOT LIKE '%TRIAL%'
            ORDER BY TestedDate DESC, BondingTestID DESC", [$from, $to])->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $res = self::result((string) $r['Result']);
            $out[] = ['id' => (int) $r['id'], 'ref' => (string) $r['ref'], 'result' => $res, 'mode' => trim((string) $r['mode']), 'cement' => $this->alias((string) $r['cement']),
                'style' => trim((string) $r['style']), 'date' => substr((string) $r['TestedDate'], 0, 10), 'test_type' => (string) $r['TestType'], 'technician' => (string) $r['Technician'],
                'line' => self::lineOf((string) $r['ref'])];
        }

        return $out;
    }

    /** GET quality-insights/failures?from=&to=&group=line|cement|style|mode */
    public function failures(): ResponseInterface
    {
        $to    = self::date($this->request->getGet('to'), date('Y-m-d'));
        $from  = self::date($this->request->getGet('from'), date('Y-m-d', strtotime($to . ' -29 days')));
        $group = in_array($this->request->getGet('group'), ['line', 'cement', 'style', 'mode'], true) ? (string) $this->request->getGet('group') : 'line';

        $payload = \App\Libraries\DataCache::remember("qi_fail_{$from}_{$to}_{$group}", 120, function () use ($from, $to, $group) {
            $rows   = $this->bondingRows($from, $to);
            $groups = [];
            $modes  = [];
            $tested = 0;
            $bad    = 0;
            foreach ($rows as $r) {
                if ($r['result'] === '') {
                    continue;
                }
                $tested++;
                $key = $group === 'mode' ? ($r['result'] === 'PASS' ? '' : ($r['mode'] !== '' ? $r['mode'] : 'Not recorded')) : ($r[$group] !== '' ? $r[$group] : 'Unknown');
                if ($key === '') {
                    continue;
                }
                $g = &$groups[$key];
                $g ??= ['key' => $key, 'tested' => 0, 'pass' => 0, 'tolerable' => 0, 'fail' => 0, 'modes' => []];
                $g['tested']++;
                $g[strtolower($r['result']) === 'pass' ? 'pass' : (strtolower($r['result']) === 'fail' ? 'fail' : 'tolerable')]++;
                if ($r['result'] !== 'PASS') {
                    $m = $r['mode'] !== '' ? $r['mode'] : 'Not recorded';
                    $g['modes'][$m] = ($g['modes'][$m] ?? 0) + 1;
                }
                unset($g);
                if ($r['result'] !== 'PASS') {
                    $bad++;
                    $m = $r['mode'] !== '' ? $r['mode'] : 'Not recorded';
                    $modes[$m] = ($modes[$m] ?? 0) + 1;
                }
            }
            foreach ($groups as &$g) {
                $g['fail_rate'] = $g['tested'] ? round($g['fail'] * 100 / $g['tested'], 1) : 0;
                arsort($g['modes']);
                $g['top_mode'] = $g['modes'] ? (string) array_key_first($g['modes']) : '';
                unset($g['modes']);
            }
            unset($g);
            $list = array_values($groups);
            usort($list, static fn ($a, $b) => [$b['fail'] + $b['tolerable'], $b['fail_rate']] <=> [$a['fail'] + $a['tolerable'], $a['fail_rate']]);
            arsort($modes);
            $mlist = [];
            foreach ($modes as $m => $n) {
                $mlist[] = ['mode' => $m, 'count' => $n, 'share' => $bad ? round($n * 100 / $bad, 1) : 0];
            }

            return ['ok' => true, 'from' => $from, 'to' => $to, 'group' => $group, 'tested' => $tested, 'bad' => $bad,
                'recorded' => $bad - ($modes['Not recorded'] ?? 0), 'groups' => $list, 'modes' => $mlist];
        });

        return $this->response->setJSON($payload);
    }

    /** GET quality-insights/failure-details?from=&to=&group=&key= : the failed / tolerable tests behind one row of the table */
    public function failureDetails(): ResponseInterface
    {
        $to    = self::date($this->request->getGet('to'), date('Y-m-d'));
        $from  = self::date($this->request->getGet('from'), date('Y-m-d', strtotime($to . ' -29 days')));
        $group = in_array($this->request->getGet('group'), ['line', 'cement', 'style', 'mode'], true) ? (string) $this->request->getGet('group') : 'line';
        $key   = (string) $this->request->getGet('key');
        $items = [];
        foreach ($this->bondingRows($from, $to) as $r) {
            if ($r['result'] === '' || $r['result'] === 'PASS') {
                continue;
            }
            $v = $group === 'mode' ? ($r['mode'] !== '' ? $r['mode'] : 'Not recorded') : ($r[$group] !== '' ? $r[$group] : 'Unknown');
            if ($v === $key) {
                $items[] = $this->item($r['ref'], 'bonding', $r['id'], $r['result'], $r['date'], $r['style'], $r['technician'], $r['mode'] !== '' ? 'Mode of failure: ' . $r['mode'] : '', $r['cement'], 'Whole Shoe Bonding (U60)', $r['test_type']);
            }
        }

        return $this->response->setJSON(['ok' => true, 'items' => $items, 'title' => $key]);
    }

    // ------------------------------------------------------------------------------------------------------------------ suppliers
    /** the spelling variants of a supplier that the lab merged (Settings: insights.aliases, one "WRONG=RIGHT" per line) */
    private function alias(string $name): string
    {
        static $map = null;
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            return '';
        }
        if ($map === null) {
            $map = [];
            foreach (preg_split('/\r?\n/', (string) SystemSettings::get('insights.aliases', '')) as $l) {
                if (str_contains($l, '=')) {
                    [$a, $b] = array_map('trim', explode('=', $l, 2));
                    $map[strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $a))] = $b;
                }
            }
        }

        return $map[strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name))] ?? $name;
    }

    private function supplierRows(string $from, string $to): array
    {
        $rows = $this->db()->query("
            SELECT u.id, u.lab_ref_no ref, u.form_slug, u.form_category cat, u.supplier, u.model_style, u.material_description, u.overall_result, u.status, u.technician, u.source_table, u.source_id,
                   DATE_FORMAT(COALESCE(STR_TO_DATE(NULLIF(u.tested_date, ''), '%d %b %Y'), STR_TO_DATE(NULLIF(u.finished_date, ''), '%d %b %Y')), '%Y-%m-%d') AS d,
                   b.AdhesiveSupplier cement, b.ModeOfFailure mode
            FROM tbl_dtr_unified_labrefs u
            LEFT JOIN tbl_dtr_bonding_info b ON u.source_table = 'tbl_dtr_bonding_info' AND b.BondingTestID = u.source_id
            WHERE u.lab_ref_no NOT LIKE '%TRIAL%'
            HAVING d BETWEEN ? AND ?", [$from, $to])->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $res = self::result((string) $r['overall_result']);
            if ($res === '') {
                continue;
            }
            if ($r['form_slug'] === 'bonding') {
                $sup = $this->alias((string) $r['cement']);
                $mat = 'Bonding cement';
            } elseif ($r['form_slug'] === 'wholeshoe') {
                continue;                                       // a finished shoe, no supplier
            } else {
                $sup = $this->alias((string) $r['supplier']);
                $mat = trim(preg_replace('/\s*Test Report\s*/i', ' ', (string) $r['cat']));
            }
            if ($sup === '' || stripos($sup, 'DATIAN') !== false) {
                $sup = '';                                      // not named, or the factory itself
            }
            $out[] = ['id' => (int) $r['id'], 'ref' => (string) $r['ref'], 'slug' => (string) $r['form_slug'], 'supplier' => $sup, 'material' => $mat, 'result' => $res, 'date' => (string) $r['d'],
                'style' => (string) ($r['model_style'] ?: $r['material_description']), 'technician' => (string) $r['technician'], 'mode' => trim((string) $r['mode']),
                'source_table' => (string) $r['source_table'], 'source_id' => (int) $r['source_id']];
        }

        return $out;
    }

    /** GET quality-insights/suppliers?from=&to= */
    public function suppliers(): ResponseInterface
    {
        [$from, $to] = $this->period();
        $min = max(1, min(50, (int) ($this->request->getGet('min') ?: 5)));

        $payload = \App\Libraries\DataCache::remember("qi_sup_{$from}_{$to}_{$min}", 120, function () use ($from, $to, $min) {
            $g    = [];
            $skip = 0;
            foreach ($this->supplierRows($from, $to) as $r) {
                if ($r['supplier'] === '') {
                    $skip++;
                    continue;
                }
                $k = $r['supplier'] . '|' . $r['material'];
                $g[$k] ??= ['supplier' => $r['supplier'], 'material' => $r['material'], 'tested' => 0, 'pass' => 0, 'tolerable' => 0, 'fail' => 0];
                $g[$k]['tested']++;
                $g[$k][strtolower($r['result']) === 'pass' ? 'pass' : (strtolower($r['result']) === 'fail' ? 'fail' : 'tolerable')]++;
            }
            $list = array_values($g);
            foreach ($list as &$x) {
                $x['pass_rate'] = round($x['pass'] * 100 / $x['tested'], 1);
                $x['low_sample'] = $x['tested'] < $min;
            }
            unset($x);
            usort($list, static fn ($a, $b) => [$a['low_sample'], $a['pass_rate'], -$a['tested']] <=> [$b['low_sample'], $b['pass_rate'], -$b['tested']]);

            return ['ok' => true, 'from' => $from, 'to' => $to, 'min' => $min, 'rows' => $list, 'without_supplier' => $skip];
        });

        return $this->response->setJSON($payload);
    }

    /** GET quality-insights/supplier-details?from=&to=&supplier=&material= : the failed / tolerable tests of one supplier */
    public function supplierDetails(): ResponseInterface
    {
        [$from, $to] = $this->period();
        $sup  = (string) $this->request->getGet('supplier');
        $mat  = (string) $this->request->getGet('material');
        $items = [];
        foreach ($this->supplierRows($from, $to) as $r) {
            if ($r['supplier'] === $sup && $r['material'] === $mat && $r['result'] !== 'PASS') {
                $items[] = $this->item($r['ref'], $r['slug'] === 'bonding' ? 'bonding' : 'material', $r['source_id'], $r['result'], $r['date'], $r['style'], $r['technician'], $r['mode'] !== '' ? 'Mode of failure: ' . $r['mode'] : '', $sup, $mat, '', $r['slug']);
            }
        }

        return $this->response->setJSON(['ok' => true, 'items' => $items, 'title' => $sup . ' · ' . $mat]);
    }

    /** one test in the shape the Calendar day panel cards use */
    private function item(string $ref, string $domain, int $sourceId, string $result, string $date, string $style, string $tech, string $remarks, string $supplier, string $category, string $testType, string $slug = 'bonding'): array
    {
        return ['id' => $ref . $sourceId, 'domain' => $domain, 'domain_label' => $domain === 'bonding' ? 'Bonding (U60)' : 'Material', 'lab_ref_no' => $ref, 'test_type' => trim($category . ' ' . $testType),
            'style' => $style, 'color' => '', 'mtl' => '', 'tr_no' => '', 'category' => $category, 'test_date' => $date, 'prod_date' => '', 'status' => 'completed', 'result' => $result,
            'technician' => $tech, 'remarks' => trim($remarks . ($supplier !== '' ? ($remarks !== '' ? ' · ' : '') . 'Supplier: ' . $supplier : '')),
            'view_url' => CalendarMonitoringController::sheetUrl($domain, $slug, $sourceId, $ref, $domain === 'bonding' && $sourceId > 0)];
    }
}
