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
        $rawTab = (string) ($this->request->getGet('tab') ?? 'bonding');
        $tab    = in_array($rawTab, ['bonding', 'wholeshoe', 'materials'], true) ? $rawTab : 'bonding';
        $v      = (string) ($this->request->getGet('view') ?? '');
        if ($rawTab === 'daily' && $v === '') {
            $v = 'workload';
        }
        $db = \Config\Database::connect();

        $allowedViews = match ($tab) {
            'bonding'   => ['workload', 'daily', 'monthly', 'analytics', 'result', 'encoding'],
            'wholeshoe' => ['workload', 'analytics', 'encoding'],
            'materials' => ['workload', 'analytics', 'encoding'],
        };
        $defaultView = match ($tab) {
            'bonding'   => 'workload',
            'wholeshoe' => 'workload',
            'materials' => 'workload',
        };
        $view = in_array($v, $allowedViews, true) ? $v : $defaultView;

        if (in_array($view, ['workload', 'encoding'], true)) {
            $wk = \App\Libraries\DataCache::remember('analytics_daily_' . md5(json_encode($this->request->getGet()) . date('Y-m-d')), 60, fn () => $this->dailyWorks($db));   // 60 s: a new entry shows within a minute

            return $this->render('analytics/daily', ['title' => 'Analytics'] + $wk + ['tab' => $tab, 'view' => $view]);
        }

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

        $cacheKey = 'analytics_' . md5("{$tab}|{$view}|{$this->group}|{$this->from}|{$this->to}");
        $data = \App\Libraries\DataCache::remember($cacheKey, 120, fn () => match (true) {
            $tab === 'wholeshoe' => $this->wholeShoe($db),
            $tab === 'materials' => $this->materials($db),
            $view === 'result'   => $this->bondingResult($db),
            $view === 'monthly'  => $this->bondingMonthly($db),
            $view === 'daily'    => $this->bondingDaily($db),
            default              => $this->bonding($db),
        });

        return $this->render('analytics/index', ['title' => 'Analytics'] + $data + ['tab' => $tab, 'view' => $view, 'group' => $this->group, 'from' => $this->from, 'to' => $this->to]);
    }

    private const SHUTDOWN_ROW = "(COALESCE(LabRefNo, '') LIKE '%SHUTDOWN%' OR COALESCE(POLotNO, '') = 'SHUTDOWN' OR COALESCE(ModelStyle, '') = 'SHUTDOWN')";
    private const BOND_DEDUP   = "(LabRefNo IS NULL OR LabRefNo = '' OR BondingTestID IN (SELECT keep_id FROM (SELECT COALESCE(MAX(CASE WHEN Result IS NOT NULL AND Result <> '' THEN BondingTestID END), MAX(BondingTestID)) AS keep_id FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' GROUP BY LabRefNo) k))";
    private const BOND_DATE    = "COALESCE((CASE WHEN ProductionDate REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(ProductionDate, 7, 4), '-', SUBSTR(ProductionDate, 1, 2), '-', SUBSTR(ProductionDate, 4, 2)) WHEN ProductionDate REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(ProductionDate, 10) WHEN LabRefNo REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(LabRefNo, 4, 4), '-', SUBSTR(LabRefNo, 8, 2), '-', SUBSTR(LabRefNo, 10, 2)) END), LEFT(NULLIF(TestedDate, ''), 10), LEFT(NULLIF(ReceivedDate, ''), 10))";

    private function bondingBaseWhere(string $dateCol = self::BOND_DATE): string
    {
        $w = 'NOT (' . self::SHUTDOWN_ROW . ") AND COALESCE(TestType, '') <> 'LACKING' AND " . self::BOND_DEDUP;
        // Shoe Bonding counts only the reports that the unified Lab Ref index (tbl_dtr_unified_labrefs) lists as a bonding test (form_slug = 'bonding');
        // a pull test or any other test of the index is not a bonding test, so it never shows here
        $w .= " AND (LabRefNo IS NULL OR LabRefNo = '' OR EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs ul WHERE ul.form_slug = 'bonding' AND ul.lab_ref_no = LabRefNo))";
        if ($this->from !== '' || $this->to !== '') {
            $w .= ' AND ' . $this->dateWhere($dateCol);
        }

        return $w;
    }

    /** Monthly Summary (by production date): daily tested / failed trend, status totals and failed count per line and day. */
    private function bondingMonthly($db): array
    {
        $pd   = self::BOND_DATE;
        $base = $this->bondingBaseWhere($pd);
        $wDay = $base . ' AND ' . $this->dateWhere($pd);
        $days = $db->query("SELECT {$pd} AS d, COUNT(*) AS tested, SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AS failed FROM tbl_dtr_bonding_info WHERE {$wDay} GROUP BY d ORDER BY d")->getResultArray();
        $st   = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(UPPER(Result) LIKE 'PASS%'), 0) AS pass, COALESCE(SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED'), 0) AS fail, COALESCE(SUM(UPPER(Result) LIKE 'TOL%'), 0) AS tol FROM tbl_dtr_bonding_info WHERE {$base}")->getRowArray();

        // failed specimens per building line and production date; the line comes from the Lab Ref No (DTP<date>SH<building><line>-<side>)
        $lines = ['A' => range(1, 12), 'B' => range(1, 14)];
        $cells = [];
        foreach ($db->query("SELECT LabRefNo, {$pd} AS d FROM tbl_dtr_bonding_info WHERE {$wDay} AND (UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED')")->getResultArray() as $r) {
            if (preg_match('/^DTP\d{8}[A-Z]{2}([A-Z])(\d+)/i', (string) $r['LabRefNo'], $m)) {
                $b = strtoupper($m[1]); $n = (int) $m[2];
                $cells[$b][$n][$r['d']] = ($cells[$b][$n][$r['d']] ?? 0) + 1;
                if (! in_array($n, $lines[$b] ?? [], true)) {
                    $lines[$b][] = $n;
                }
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

        return ['days' => $days, 'shut' => $shut, 'status' => $st, 'lines' => $lines, 'cells' => $cells, 'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => []];
    }

    /** Bonding Result: pass / tolerable / failed counts per cement (adhesive) supplier, with a total row. */
    private function bondingResult($db): array
    {
        $w = $this->bondingBaseWhere(self::BOND_DATE);
        $rows = $db->query("SELECT UPPER(TRIM(AdhesiveSupplier)) AS name, COUNT(*) AS total, SUM(UPPER(Result) LIKE 'PASS%') AS pass, SUM(UPPER(Result) LIKE 'TOL%') AS tol, SUM(UPPER(Result) LIKE 'FAIL%' OR UPPER(Result) = 'REJECTED') AS fail
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

    /** Daily Bonding: Left/Right daily matrix, building & line rankings, cement supplier fails, and portion/root-cause breakdown. */
    private function bondingDaily($db): array
    {
        $pd   = self::BOND_DATE;
        $base = $this->bondingBaseWhere($pd);
        $rows = $db->query("SELECT LabRefNo, Side, Result, ModeOfFailure, PossibleCause, AdhesiveSupplier, TestedDate, {$pd} AS prod_date
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
                $rawFail = trim((string) ($r['ModeOfFailure'] ?: $r['PossibleCause'] ?: ''));
                if ($rawFail === '') {
                    $portionCounts['Unspecified']++;
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
                        $cNorm = str_ireplace(['MATERAIL', 'CEMENTNG'], ['MATERIAL', 'CEMENTING'], $cUp);
                        $cLabel = ucwords(strtolower($cNorm));
                        $causeCounts[$cLabel] = ($causeCounts[$cLabel] ?? 0) + 1;
                    }
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
            'kpis'          => [],
            'chartTitle'    => '',
            'months'        => [],
            'lists'         => [],
        ];
    }

    private function pct(int $n, int $d): float
    {
        return $d > 0 ? round($n / $d * 100, 1) : 0.0;
    }

    /** Daily Works Monitoring: year of materials received vs encoded (+ by type) and the day-by-day lab workload with encoding progress. */
    private function dailyWorks($db): array
    {
        $ok     = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $period = strtolower((string) ($this->request->getGet('period') ?: 'daily'));
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            $period = 'daily';
        }
        $year = (int) ($this->request->getGet('year') ?: date('Y'));
        $year = ($year >= 2020 && $year <= 2100) ? $year : (int) date('Y');
        $asof = $ok($this->request->getGet('asof')) ?: date('Y-m-d');
        $reqWeek  = (string) ($this->request->getGet('week') ?: '');
        $reqMonth = (int) ($this->request->getGet('month') ?: 0);

        if ($period === 'weekly') {
            if (preg_match('/^(\d{4})-W(0[1-9]|[1-4]\d|5[0-3])$/', $reqWeek, $wm)) {
                $wy = (int) $wm[1];
                $ww = (int) $wm[2];
                $year = ($wy >= 2020 && $wy <= 2100) ? $wy : $year;
            } else {
                $baseTs = (int) date('Y', strtotime($asof)) === $year ? strtotime($asof) : strtotime("{$year}-01-04");
                $wy = $year;
                $ww = (int) date('W', $baseTs);
            }
            $mon = (new \DateTimeImmutable())->setISODate($wy, $ww, 1);
            $sun = $mon->modify('+6 days');
            $winFrom = $mon->format('Y-m-d');
            $winTo   = $sun->format('Y-m-d');
            $asof    = $winTo;
            $week    = $mon->format('o-\WW');
            $month   = (int) $mon->format('n');
            $encFrom = $winFrom;
            $encTo   = $winTo;
            $periodTitle = 'Week ' . $mon->format('W') . ' (' . $mon->format('M j') . ' – ' . $sun->format('M j, Y') . ')';
            $windowTag   = 'Weekly';
            $windowShort = 'Weekly';
            $windowLabel = 'Weekly (' . $mon->format('M j') . ' – ' . $sun->format('M j, Y') . ')';
            $buckets = [];
            for ($k = 6; $k >= 0; $k--) {
                $dt = $mon->modify("+{$k} days");
                $ds = $dt->format('Y-m-d');
                $buckets[] = ['date' => $ds, 'from' => $ds, 'to' => $ds, 'label' => $dt->format('M j, Y'), 'short' => $dt->format('D m/d')];
            }
        } elseif ($period === 'monthly') {
            $month = ($reqMonth >= 1 && $reqMonth <= 12) ? $reqMonth : (int) date('n', strtotime($asof));
            $m1    = sprintf('%04d-%02d-01', $year, $month);
            $mEnd  = date('Y-m-t', strtotime($m1));
            $winFrom = $m1;
            $winTo   = $mEnd;
            $asof    = $mEnd;
            $week    = date('o-\WW', strtotime($m1));
            $encFrom = $m1;
            $encTo   = $mEnd;
            $periodTitle = date('F Y', strtotime($m1));
            $windowTag   = date('M', strtotime($m1));
            $windowShort = date('M Y', strtotime($m1));
            $windowLabel = 'Monthly (' . date('F Y', strtotime($m1)) . ')';
            $buckets = [];
            $cur = new \DateTimeImmutable($m1);
            $end = new \DateTimeImmutable($mEnd);
            while ($cur <= $end) {
                $dow    = (int) $cur->format('N'); // 1=Mon .. 7=Sun
                $sliceEnd = $cur->modify('+' . (7 - $dow) . ' days');
                if ($sliceEnd > $end) {
                    $sliceEnd = $end;
                }
                $bFrom = $cur->format('Y-m-d');
                $bTo   = $sliceEnd->format('Y-m-d');
                $bShort = $bFrom === $bTo ? $cur->format('M j') : ($cur->format('M j') . '–' . $sliceEnd->format('j'));
                $buckets[] = ['date' => $bTo, 'from' => $bFrom, 'to' => $bTo, 'label' => $bShort . ', ' . $year, 'short' => $bShort];
                $cur = $sliceEnd->modify('+1 day');
            }
            $buckets = array_reverse($buckets); // newest slice first to match daily order
        } else {
            $winFrom = $asof;
            $winTo   = $asof;
            $week    = date('o-\WW', strtotime($asof));
            $month   = (int) date('n', strtotime($asof));
            $encFrom = $asof;
            $encTo   = $asof;
            $periodTitle = date('M j, Y', strtotime($asof));
            $windowTag   = date('M j', strtotime($asof));
            $windowShort = 'Daily';
            $windowLabel = 'Daily (' . date('M j, Y', strtotime($asof)) . ')';
            $buckets = [
                ['date' => $asof, 'from' => $asof, 'to' => $asof, 'label' => date('M j, Y', strtotime($asof)), 'short' => date('M j', strtotime($asof))],
            ];
        }

        // ---- A. materials received per month, encoded in INTERLINK, per material type ----
        $types  = ['Fabric', 'Leather', 'Synthetic', 'Accessories', 'Rubber'];
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = ['recv' => 0, 'enc' => 0, 'types' => array_fill_keys($types, 0)];
        }
        // 1) All material test reports in tbl_dtr_unified_labrefs (covers Jan..Dec across all material categories)
        $uMo = "CAST(COALESCE(
            CASE WHEN lab_ref_no REGEXP '^DTP{$year}[0-1][0-9]' THEN SUBSTR(lab_ref_no, 8, 2) END,
            MONTH(STR_TO_DATE(received_date, '%d %b %Y')),
            MONTH(STR_TO_DATE(tested_date, '%d %b %Y')),
            CASE WHEN received_date REGEXP '^{$year}-[0-9]{2}' THEN SUBSTR(received_date, 6, 2) END,
            CASE WHEN tested_date REGEXP '^{$year}-[0-9]{2}' THEN SUBSTR(tested_date, 6, 2) END
        ) AS UNSIGNED)";
        $uTy = "CASE
            WHEN UPPER(COALESCE(material_type,'')) LIKE '%SYNTHETIC%' OR UPPER(COALESCE(material_type,'')) LIKE '%SUEDE%' THEN 'Synthetic'
            WHEN form_slug = 'leather' OR UPPER(COALESCE(material_type,'')) LIKE '%LEATHER%' THEN 'Leather'
            WHEN form_slug = 'fabric' OR UPPER(COALESCE(material_type,'')) REGEXP 'FABRIC|LINING|MESH|CANVAS|TEXTILE' THEN 'Fabric'
            WHEN form_slug IN ('rubber', 'fthl') OR UPPER(COALESCE(material_type,'')) REGEXP 'RUBBER|OUTSOLE|FOXING|SOLE|HEEL LOGO' THEN 'Rubber'
            ELSE 'Accessories'
        END";
        $uEnc = "(COALESCE(overall_result,'') <> '' OR COALESCE(interlink_flag,'') <> '' OR COALESCE(status,'') IN ('Completed','Done','Pass','Passed','Fail','Failed'))";
        if ($db->tableExists('tbl_dtr_unified_labrefs')) {
            $uRows = $db->query("SELECT {$uMo} mo, {$uTy} tp, COUNT(*) n, SUM({$uEnc}) e
                FROM tbl_dtr_unified_labrefs
                WHERE form_slug NOT IN ('bonding', 'wholeshoe', 'pulltest')
                  AND (lab_ref_no LIKE 'DTP{$year}%' OR received_date LIKE '%{$year}%' OR tested_date LIKE '%{$year}%')
                GROUP BY mo, tp")->getResultArray();
            foreach ($uRows as $r) {
                $mo = (int) $r['mo'];
                if ($mo < 1 || $mo > 12) {
                    continue;
                }
                $months[$mo]['recv'] += (int) $r['n'];
                $months[$mo]['enc']  += (int) $r['e'];
                $months[$mo]['types'][$r['tp']] = ($months[$mo]['types'][$r['tp']] ?? 0) + (int) $r['n'];
            }
        }
        // 2) Supplement with any months in tbl_dtr_materials_master that have additional IQC intake records not yet in unified_labrefs
        $md  = "COALESCE(NULLIF(LEFT(m.MaterialDateReceivedInLab,10),'0000-00-00'), NULLIF(LEFT(c.DateReceivedInLab,10),'0000-00-00'), NULLIF(LEFT(m.MaterialDateArrived,10),'0000-00-00'), NULLIF(LEFT(c.DateArrived,10),'0000-00-00'))";
        $ty  = "CASE WHEN UPPER(m.MaterialType) LIKE '%LEATHER%' THEN 'Leather' WHEN UPPER(m.MaterialType) REGEXP 'RUBBER|OUTSOLE|FOXING|SOLE' THEN 'Rubber' WHEN UPPER(m.MaterialType) LIKE '%SYNTHETIC%' OR UPPER(m.MaterialType) LIKE '%SUEDE%' THEN 'Synthetic' WHEN UPPER(m.MaterialType) REGEXP 'FABRIC|LINING|MESH|CANVAS|TEXTILE' THEN 'Fabric' ELSE 'Accessories' END";
        $enc = "(UPPER(COALESCE(m.IQCERP,''))='OK' OR UPPER(COALESCE(m.FinalStatus,'')) IN ('PASS','TOLERABLE','FAILED') OR EXISTS (SELECT 1 FROM tbl_interlink_reports r WHERE r.synced_to_interlink=1 AND r.material_code<>'' AND r.material_code = m.MaterialCode COLLATE utf8mb4_general_ci))";
        $rows = $db->query("SELECT CAST(SUBSTR({$md},6,2) AS UNSIGNED) mo, {$ty} tp, COUNT(*) n, SUM({$enc}) e
            FROM tbl_dtr_materials_master m LEFT JOIN tbl_dtr_containers c ON c.ContainerID = m.ContainerID
            WHERE {$md} BETWEEN '{$year}-01-01' AND '{$year}-12-31'
              AND (m.LabReference IS NULL OR m.LabReference = '' OR NOT EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs u WHERE u.lab_ref_no = m.LabReference))
            GROUP BY mo, tp")->getResultArray();
        foreach ($rows as $r) {
            $mo = (int) $r['mo'];
            if ($mo < 1 || $mo > 12 || $months[$mo]['recv'] > 0) {
                continue;
            }
            $months[$mo]['recv'] += (int) $r['n'];
            $months[$mo]['enc']  += (int) $r['e'];
            $months[$mo]['types'][$r['tp']] = ($months[$mo]['types'][$r['tp']] ?? 0) + (int) $r['n'];
        }

        // ---- B. day-by-day bonding workload (specimens collected that day) ----
        $isPull = static fn (string $lab): bool => (bool) preg_match('/^DTP\d{8}(EY|DR|RVT|HP|LLH)/i', $lab);
        $classify = static function (array $r): array {
            $t = strtoupper((string) $r['TestType']); $lab = strtoupper((string) $r['LabRefNo']);
            if (trim((string) $r['RetestOf']) !== '' || str_contains($t, 'RETEST') || preg_match('/-R\d+$/', $lab)) {
                return ['retest', 'Daily Bonding'];
            }
            if (str_contains($t, 'SAMPLE')) {
                if (preg_match('/STRIP|OSNA/', $lab)) { return ['sample', 'Rubber Strip Test']; }
                if (str_contains($t, 'RIVET') || str_contains($lab, 'RIVET')) { return ['sample', 'Rivet']; }

                return ['sample', 'Bonding Shoe'];
            }
            if (str_contains($t, 'RIVET')) { return ['sample', 'Rivet']; }
            if (str_contains($t, 'FIRST')) { return ['first', 'Bonding Shoe']; }
            if (str_contains($t, 'TRIAL')) { return ['trial', 'Bonding Shoe']; }
            if (! str_contains($t, 'DAILY')) { return ['', '']; }   // only what the Lab Tracker counts as Daily

            return ['daily', 'Daily Bonding Shoe'];
        };
        $sections = [
            'daily'  => ['DAILY BONDING TEST', ['Daily Bonding Shoe', 'Daily Bonding Shoe (Advance)', 'Daily Bonding Shoe (Lacking)']],
            'retest' => ['BONDING FAILED RE-TEST', ['Daily Bonding']],
            'first'  => ['PRODUCTION FIRST ARTICLE', ['Bonding Shoe', 'Whole Shoe']],
            'sample' => ['SAMPLE', ['Bonding Shoe', 'Rubber Strip Test', 'Rivet']],
            'trial'  => ['TECH TRIAL', ['Bonding Shoe']],
        ];
        $dayExpr = "LEFT(COALESCE(NULLIF(ReceivedDate,''),NULLIF(TestedDate,''),NULLIF(ProductionDate,'')),10)";
        $baseW   = 'NOT (' . self::SHUTDOWN_ROW . ') AND ' . self::BOND_DEDUP;
        // the tracker table is tbl_dtr_bondingtracker on some servers: tableExists() is case sensitive, so both spellings are tried (the queries themselves are not)
        $hasTrk  = ($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) && $db->fieldExists('Shutdown', 'tbl_dtr_BondingTracker');
        $hasWs   = $db->tableExists('tbl_dtr_WholeshoeTracker');
        $days    = [];
        $eWinFrom = $db->escape($winFrom);
        $eWinTo   = $db->escape($winTo);
        // Same rules as the Lab Tracker chips: Test Type from the intake tracker, COLLECTION date (Daily counts the next working day, Saturday -> Monday),
        // Advance / Lacking / Shutdown count on the day they were loaded (CreatedAt) or on the day their Daily batch counts
        $eCollFrom = $db->escape(date('Y-m-d', strtotime($winFrom . ' -3 day')));
        $bondRows = $db->query("SELECT b.LabRefNo, b.Result, b.RetestOf, b.TestedDate, b.ProductionDate, COALESCE(" . ($hasTrk ? "NULLIF(LEFT(t.ProductionDate, 10), ''), " : '') . "LEFT(b.ProductionDate, 10)) AS pd2,
                UPPER(COALESCE(" . ($hasTrk ? "NULLIF(t.TestType COLLATE utf8mb4_general_ci, ''), " : '') . "NULLIF(b.TestType COLLATE utf8mb4_general_ci, ''), '')) AS TestType,
                COALESCE(" . ($hasTrk ? "NULLIF(LEFT(t.CollectionDate, 10), ''), " : '') . "NULLIF(LEFT(b.ReceivedDate, 10), ''), NULLIF(LEFT(b.TestedDate, 10), ''), LEFT(b.ProductionDate, 10)) AS cd
            FROM (SELECT * FROM tbl_dtr_bonding_info WHERE {$baseW} AND COALESCE(TestType, '') <> 'LACKING') b"
            . ($hasTrk ? " LEFT JOIN (SELECT LabRefNo, MAX(TrackerID) AS tid FROM tbl_dtr_BondingTracker WHERE LabRefNo IS NOT NULL GROUP BY LabRefNo) tm ON tm.LabRefNo = b.LabRefNo COLLATE utf8mb4_general_ci
                LEFT JOIN tbl_dtr_BondingTracker t ON t.TrackerID = tm.tid" : '') . "
            HAVING cd BETWEEN {$eCollFrom} AND {$eWinTo}")->getResultArray();
        $effOf = static function (string $tt, string $lab, string $cd, string $pd = ''): string {
            $daily = str_contains($tt, 'DAILY') && ! preg_match('/^DTP\d{8}(HP|EY|DR|RVT|LLH)/i', $lab);
            if (! $daily || $cd === '') {
                return $cd;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd) && $pd < $cd) {
                $cd = $pd;   // collected late: it stays with its production batch (same rule as the Lab Tracker)
            }

            return date('Y-m-d', strtotime($cd . ((int) date('N', strtotime($cd)) === 6 ? ' +2 day' : ' +1 day')));
        };
        $resByRef = [];
        foreach ($bondRows as $r) {
            if (trim((string) $r['Result']) !== '') { $resByRef[strtoupper((string) $r['LabRefNo'])] = true; }
        }
        // a shoe whose pull test (EY / RVT / DR / HP / LLH of the same date, line and side) already has a result counts as finished too, like the Lab Tracker row result
        $pullDone = [];
        if ($db->tableExists('tbl_dtr_pull_tests')) {
            foreach ($db->query("SELECT LabRefNo FROM tbl_dtr_pull_tests WHERE Result IS NOT NULL AND Result <> '' AND LabRefNo REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)[A-Z][0-9]+-[LR]' AND LEFT(LabRefNo, 11) >= " . $db->escape('DTP' . date('Ym', strtotime($winFrom . ' -3 day')) . '00'))->getResultArray() as $pr) {
                if (preg_match('/^(DTP\d{8})(?:HP|EY|DR|RVT|LLH)([A-Z]\d+)-([LR])/i', (string) $pr['LabRefNo'], $pm)) {
                    $pullDone[strtoupper($pm[1] . '|' . $pm[2] . '|' . $pm[3])] = true;
                }
            }
        }
        $trkMarks = $hasTrk ? $db->query("SELECT LabRefNo, Lacking, Shutdown, UPPER(TestType) tt, COALESCE(PullTestType, '') pt, LEFT(CreatedAt, 10) cr, LEFT(CollectionDate, 10) cd
            FROM tbl_dtr_BondingTracker WHERE (Lacking IN (1, 2) OR Shutdown = 1 OR UPPER(TestType) = 'SHUTDOWN')
              AND (LEFT(CreatedAt, 10) BETWEEN {$eWinFrom} AND {$eWinTo} OR LEFT(CollectionDate, 10) BETWEEN {$eCollFrom} AND {$eWinTo})")->getResultArray() : [];
        $wsRows   = $hasWs ? $db->query("SELECT LEFT(CollectionDate,10) d, Status, FinalResult, ProductionDate, TimeCompleted FROM tbl_dtr_WholeshoeTracker WHERE LEFT(CollectionDate,10) BETWEEN {$eWinFrom} AND {$eWinTo}")->getResultArray() : [];
        $shutInfoRows = $db->query('SELECT ' . $dayExpr . ' d, COUNT(*) c FROM tbl_dtr_bonding_info WHERE ' . self::SHUTDOWN_ROW . " AND {$dayExpr} BETWEEN {$eWinFrom} AND {$eWinTo} GROUP BY d")->getResultArray();

        foreach ($buckets as $bk) {
            $bFrom = $bk['from'];
            $bTo   = $bk['to'];
            $cells = [];
            foreach ($sections as $sk => [$title, $labels]) {
                foreach ($labels as $lb) {
                    $cells[$sk][$lb] = ['q' => 0, 'f' => 0, 'prod' => '', 'test' => ''];
                }
            }
            foreach ($bondRows as $r) {
                $rd = $effOf((string) $r['TestType'], (string) $r['LabRefNo'], (string) $r['cd'], (string) $r['pd2']);
                if ($rd < $bFrom || $rd > $bTo || $isPull((string) $r['LabRefNo'])) {
                    continue;
                }
                [$sk, $lb] = $classify($r);
                if ($sk === '') {
                    continue;
                }
                $c = &$cells[$sk][$lb];
                $c['q']++;
                $pd = substr((string) $r['ProductionDate'], 0, 10);
                if ($pd > $c['prod']) { $c['prod'] = $pd; }
                $pullKey = preg_match('/^(DTP\d{8})SH([A-Z]\d+)-([LR])/i', (string) $r['LabRefNo'], $bm) ? strtoupper($bm[1] . '|' . $bm[2] . '|' . $bm[3]) : '';
                if (trim((string) $r['Result']) !== '' || ($pullKey !== '' && isset($pullDone[$pullKey]))) {
                    $c['f']++;
                    $td = substr((string) $r['TestedDate'], 0, 10);
                    if ($td > $c['test']) { $c['test'] = $td; }
                }
                unset($c);
            }
            foreach ($wsRows as $r) {
                $rd = (string) $r['d'];
                if ($rd < $bFrom || $rd > $bTo) {
                    continue;
                }
                $c = &$cells['first']['Whole Shoe'];
                $c['q']++;
                $pd = substr((string) $r['ProductionDate'], 0, 10);
                if ($pd > $c['prod']) { $c['prod'] = $pd; }
                if (trim((string) $r['FinalResult']) !== '' || in_array(strtoupper((string) $r['Status']), ['COMPLETED', 'DONE', 'FINISHED', 'PASSED', 'FAILED'], true)) {
                    $c['f']++;
                    $td = substr((string) $r['TimeCompleted'], 0, 10) ?: $rd;
                    if ($td > $c['test']) { $c['test'] = $td; }
                }
                unset($c);
            }
            $shut = 0;
            foreach ($shutInfoRows as $sr) {
                $rd = (string) $sr['d'];
                if ($rd >= $bFrom && $rd <= $bTo) {
                    $shut += (int) $sr['c'];
                }
            }
            foreach ($trkMarks as $mk) {
                $cr  = (string) $mk['cr'];
                $sh  = $mk['cd'] !== '' ? date('Y-m-d', strtotime($mk['cd'] . ((int) date('N', strtotime($mk['cd'])) === 6 ? ' +2 day' : ' +1 day'))) : '';
                if (! (($cr >= $bFrom && $cr <= $bTo) || ($sh >= $bFrom && $sh <= $bTo))) {
                    continue;
                }
                $noPull = trim((string) $mk['pt']) === '';
                if ((int) $mk['Lacking'] === 1 && ! ($sh >= $bFrom && $sh <= $bTo)) {
                    continue;   // a LACKING line belongs to the testing day it is due (next working day), not the day it was marked
                }
                if ((int) $mk['Lacking'] === 2 && ! ($cr >= $bFrom && $cr <= $bTo) && isset($resByRef[strtoupper((string) $mk['LabRefNo'])])) {
                    continue;   // an Advance shoe already tested is in the Daily record of its day, no longer an Advance of a later day (same rule as the Lab Tracker)
                }
                if ((int) $mk['Shutdown'] === 1 || $mk['tt'] === 'SHUTDOWN') {
                    $shut++;
                } elseif ((int) $mk['Lacking'] === 2 && $noPull) {
                    $c = &$cells['daily']['Daily Bonding Shoe (Advance)'];
                    $c['q']++;
                    if (isset($resByRef[strtoupper((string) $mk['LabRefNo'])])) { $c['f']++; }
                    unset($c);
                } elseif ((int) $mk['Lacking'] === 1 && ($mk['tt'] === 'LACKING' || $noPull)) {
                    $cells['daily']['Daily Bonding Shoe (Lacking)']['q']++;
                }
            }
            $tot = ['q' => 0, 'f' => 0];
            foreach ($cells as $sec) { foreach ($sec as $c) { $tot['q'] += $c['q']; $tot['f'] += $c['f']; } }
            $days[] = ['date' => $bk['date'], 'from' => $bFrom, 'to' => $bTo, 'label' => $bk['label'], 'short' => $bk['short'], 'cells' => $cells, 'total' => $tot, 'shut' => $shut];
        }

        // ---- C. test reports encoded in the selected period from tbl_dtr_unified_labrefs: ETRF / INTERLINK / ERP ----
        $eEncFrom = $db->escape($encFrom);
        $eEncTo   = $db->escape($encTo);
        $cat = [
            'Bonding'    => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
            'Pull Test'  => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
            'Re-Test'    => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
            'Whole Shoe' => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
        ];
        $materials = ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0];
        if ($db->tableExists('tbl_dtr_unified_labrefs')) {
            \App\Libraries\ErpMonitor::ensureColumns();
            $uDateExpr = "COALESCE(
                CASE WHEN lab_ref_no REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(lab_ref_no, 4, 4), '-', SUBSTR(lab_ref_no, 8, 2), '-', SUBSTR(lab_ref_no, 10, 2)) END,
                CASE WHEN received_date REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(received_date, 10) END,
                CASE WHEN received_date REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(received_date, 7, 4), '-', SUBSTR(received_date, 1, 2), '-', SUBSTR(received_date, 4, 2)) END,
                DATE_FORMAT(STR_TO_DATE(received_date, '%d %b %Y'), '%Y-%m-%d'),
                CASE WHEN tested_date REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(tested_date, 10) END,
                CASE WHEN tested_date REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(tested_date, 7, 4), '-', SUBSTR(tested_date, 1, 2), '-', SUBSTR(tested_date, 4, 2)) END,
                DATE_FORMAT(STR_TO_DATE(tested_date, '%d %b %Y'), '%Y-%m-%d'),
                ''
            )";
            $uEncRows = $db->query("SELECT lab_ref_no, tr_no, form_slug, form_category, status, overall_result,
                etrf_mat_app, etrf_mat_hist, etrf_prod_app, etrf_prod_hist, etrf_pull_app, etrf_pull_hist,
                etrf_bonding_app, etrf_bonding_hist, etrf_whole_app, etrf_whole_hist, etrf_adult_app, etrf_adult_hist,
                erp_status, interlink_flag
                FROM tbl_dtr_unified_labrefs
                WHERE {$uDateExpr} BETWEEN {$eEncFrom} AND {$eEncTo}")->getResultArray();
            $extraPullEtrf = 0;
            $extraPullIl   = 0;
            $extraPullErp  = 0;
            foreach ($uEncRows as $r) {
                $slug = strtolower(trim((string) $r['form_slug']));
                $lab  = trim((string) $r['lab_ref_no']);
                $fcat = strtoupper((string) $r['form_category']);
                $st   = strtoupper(trim((string) $r['status']));
                if ($slug === 'pulltest' || ($slug === 'bonding' && $isPull($lab))) {
                    $k = 'Pull Test';
                } elseif ($slug === 'bonding' && (preg_match('/-R\d+$/i', $lab) || str_contains($fcat, 'RETEST') || str_contains($st, 'RETEST'))) {
                    $k = 'Re-Test';
                } elseif ($slug === 'bonding') {
                    $k = 'Bonding';
                } elseif (in_array($slug, ['wholeshoe', 'wst'], true)) {
                    $k = 'Whole Shoe';
                } else {
                    $k = 'Materials';
                }
                $trNo = trim((string) $r['tr_no']);
                $isEtrf = match ($k) {
                    'Pull Test'  => $trNo !== '' || trim((string) $r['etrf_pull_app']) !== '' || trim((string) $r['etrf_pull_hist']) !== '',
                    'Bonding', 'Re-Test' => $trNo !== '' || trim((string) $r['etrf_bonding_app']) !== '' || trim((string) $r['etrf_bonding_hist']) !== '' || trim((string) $r['etrf_prod_app']) !== '' || trim((string) $r['etrf_adult_app']) !== '',
                    'Whole Shoe' => $trNo !== '' || trim((string) $r['etrf_whole_app']) !== '' || trim((string) $r['etrf_whole_hist']) !== '' || trim((string) $r['etrf_prod_app']) !== '' || trim((string) $r['etrf_adult_app']) !== '',
                    default      => $trNo !== '' || trim((string) $r['etrf_mat_app']) !== '' || trim((string) $r['etrf_mat_hist']) !== '',
                };
                $hasRes = trim((string) $r['overall_result']) !== '' || in_array($st, ['COMPLETE', 'COMPLETED', 'DONE', 'PASS', 'PASSED', 'FAIL', 'FAILED'], true);
                $isIl   = trim((string) $r['interlink_flag']) !== '' || (($k === 'Materials' || $k === 'Whole Shoe') && $isEtrf && $hasRes);
                $isErp  = in_array(strtoupper(trim((string) $r['erp_status'])), ['OK', 'SUBMITTED', 'DONE', '1', 'COMPLETED'], true);
                if ($k === 'Materials') {
                    $materials['q']++;
                    if ($isEtrf) { $materials['etrf']++; }
                    if ($isIl) { $materials['il']++; }
                    if ($isErp) { $materials['erp']++; }
                } else {
                    $cat[$k]['q']++;
                    if ($isEtrf) { $cat[$k]['etrf']++; }
                    if ($isIl) { $cat[$k]['il']++; }
                    if ($isErp) { $cat[$k]['erp']++; }
                }
                // Bonding rows that also carry a Pull Test TR in etrf_pull_app/etrf_pull_hist
                if ($k === 'Bonding' && (trim((string) $r['etrf_pull_app']) !== '' || trim((string) $r['etrf_pull_hist']) !== '')) {
                    $extraPullEtrf++;
                    if ($isIl) { $extraPullIl++; }
                    if ($isErp) { $extraPullErp++; }
                }
            }
            if ($extraPullEtrf > 0) {
                if ($cat['Pull Test']['q'] < $cat['Pull Test']['etrf'] + $extraPullEtrf) {
                    $cat['Pull Test']['q'] = $cat['Pull Test']['etrf'] + $extraPullEtrf;
                }
                $cat['Pull Test']['etrf'] = min($cat['Pull Test']['q'], $cat['Pull Test']['etrf'] + $extraPullEtrf);
                $cat['Pull Test']['il']   = min($cat['Pull Test']['q'], $cat['Pull Test']['il'] + $extraPullIl);
                $cat['Pull Test']['erp']  = min($cat['Pull Test']['q'], $cat['Pull Test']['erp'] + $extraPullErp);
            }
        }

        return ['dw' => ['year' => $year, 'asof' => $asof, 'period' => $period, 'week' => $week, 'month' => $month, 'winFrom' => $winFrom, 'winTo' => $winTo, 'encFrom' => $encFrom, 'encTo' => $encTo, 'windowTag' => $windowTag, 'windowShort' => $windowShort, 'windowLabel' => $windowLabel, 'types' => $types, 'months' => $months, 'days' => $days, 'sections' => $sections, 'cat' => $cat, 'materials' => $materials, 'monthName' => $periodTitle],
            'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => []];
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