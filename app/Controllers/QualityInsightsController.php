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

    /** "DTP20261103SHA9-R-R01" -> "A9" (the building letter and the line number are part of the Lab Ref) */
    public static function lineOf(string $ref): string
    {
        return preg_match('/^DTP\d{8}[A-Z]{2,3}([A-Z])(\d+)/', strtoupper($ref), $m) ? $m[1] . $m[2] : '';
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
        $payload = \App\Libraries\DataCache::remember("qi_linemap_{$from}_{$to}", 120, function () use ($from, $to) {
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

            foreach ((new CalendarMonitoringController())->records($from, $to) as $r) {
                if ($r['domain'] !== 'bonding' || stripos($r['lab_ref_no'], 'TRIAL') !== false) {
                    continue;
                }
                $l = self::lineOf($r['lab_ref_no']);
                if ($l === '') {
                    continue;
                }
                $tiles[$l] ??= $tile($l);
                if ($r['status'] === 'completed') {
                    $tiles[$l]['tested']++;
                    if ($r['result'] === 'PASS') {
                        $tiles[$l]['pass']++;
                    } elseif ($r['result'] === 'FAIL') {
                        $tiles[$l]['fail']++;
                        $tiles[$l]['fail_refs'][] = $r['lab_ref_no'];
                    } elseif ($r['result'] === 'TOLERABLE') {
                        $tiles[$l]['tolerable']++;
                        $tiles[$l]['tol_refs'][] = $r['lab_ref_no'];
                    }
                } else {
                    $tiles[$l]['pending']++;      // collected from the line, not tested yet
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

            return ['ok' => true, 'from' => $from, 'to' => $to, 'buildings' => $buildings, 'totals' => $tot];
        });

        return $this->response->setJSON($payload);
    }

    /** GET quality-insights/line-details?from=&to=&line=A4: the tests of that line in the period (same shape as the Calendar day panel) */
    public function lineDetails(): ResponseInterface
    {
        [$from, $to] = $this->period();
        $line = strtoupper(trim((string) $this->request->getGet('line')));
        $items = [];
        foreach ((new CalendarMonitoringController())->records($from, $to) as $r) {
            if ($r['domain'] === 'bonding' && self::lineOf($r['lab_ref_no']) === $line && stripos($r['lab_ref_no'], 'TRIAL') === false) {
                $items[] = $r;
            }
        }

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
