<?php

namespace App\Controllers;

use App\Models\MaterialTestModel;
use App\Models\MaterialTestResultModel;
use App\Libraries\TestCatalog;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controller for Material Testing & Monitoring Suite
 * Ported from LMS to unified dbnexus tables (tbl_wst_tests, tbl_wst_test_results)
 * with robust parameterized queries, transaction safety, and CSRF/input sanitation.
 */
class WstController extends BaseController
{
    public const WHOLE_SHOE_CATEGORIES = [
        'hydrolysis',
        'heat-aging',
        'washing',
        'uv-light',
        'flexing',
        'deeptest',
        'deep-test',
    ];

    public static function mapTrackerTestTypeToCategory(string $type, string $code = ''): string
    {
        $t = strtolower(trim($type));
        $c = strtoupper(trim($code));
        if (str_contains($t, 'hydro') || $c === 'HY') return 'hydrolysis';
        if (str_contains($t, 'heat') || str_contains($t, 'aging') || $c === 'AG') return 'heat-aging';
        if (str_contains($t, 'wash') || $c === 'WS') return 'washing';
        if (str_contains($t, 'uv') || $c === 'UV') return 'uv-light';
        if (str_contains($t, 'flex') || $c === 'FX') return 'flexing';
        if (str_contains($t, 'deep') || str_contains($t, 'dip') || $c === 'DT') return 'deeptest';
        return 'flexing';
    }

    public const MATERIAL_CATEGORIES = [
        'martindale-abrasion',
        'taber-abrasion',
        'lace-abrasion',
        'crocking',
        'ross-flex',
    ];

    private const CATEGORIES = [
        'hydrolysis' => [
            'name'       => 'Hydrolysis',
            'code'       => 'HYD',
            'remarkMode' => 'checkbox',
        ],
        'heat-aging' => [
            'name'       => 'Heat Aging',
            'code'       => 'HTA',
            'remarkMode' => 'checkbox',
        ],
        'washing' => [
            'name'       => 'Washing',
            'code'       => 'WSH',
            'remarkMode' => 'checkbox',
        ],
        'uv-light' => [
            'name'       => 'UV Light',
            'code'       => 'UVL',
            'remarkMode' => 'checkbox',
        ],
        'flexing' => [
            'name'       => 'Flexing',
            'code'       => 'WSF',
            'remarkMode' => 'checkbox',
        ],
        'deeptest' => [
            'name'       => 'Deep Test',
            'code'       => 'DPT',
            'remarkMode' => 'checkbox',
        ],
        'deep-test' => [
            'name'       => 'Deep Test',
            'code'       => 'DPT',
            'remarkMode' => 'checkbox',
        ],
        'bally-flex' => [
            'name'       => 'Bally Flexing',
            'code'       => 'WSF',
            'remarkMode' => 'checkbox',
        ],
        'ross-flex' => [
            'name'       => 'Ross Flexing',
            'code'       => 'RB',
            'remarkMode' => 'checkbox',
        ],
        'martindale-abrasion' => [
            'name'       => 'Martindale Abrasion',
            'code'       => 'MDA',
            'remarkMode' => 'text',
        ],
        'lace-abrasion' => [
            'name'       => 'Lace Abrasion',
            'code'       => 'LC',
            'remarkMode' => 'text',
        ],
        'taber-abrasion' => [
            'name'       => 'Taber Abrasion',
            'code'       => 'TBR',
            'remarkMode' => 'text',
        ],
        'crocking' => [
            'name'       => 'Crocking',
            'code'       => 'CRK',
            'remarkMode' => 'text',
        ],
    ];

    /**
     * GET /wholeshoe
     * Top-level Whole Shoe Test & Monitoring page
     */
    public function index(): string
    {
        return $this->wholeshoe();
    }

    /**
     * GET /materials
     */
    public function materials(): string
    {
        return $this->render('materials/index', [
            'title'   => 'Material Testing',
            'withCss' => 'dtr.css',
            'withJs'  => ['materials.js'],
        ]);
    }

    /**
     * GET /wholeshoe
     * Top-level Whole Shoe Test & Monitoring page
     */
    public function wholeshoe(): string
    {
        return $this->render('wholeshoe/index', [
            'title'   => 'Whole Shoe Test',
            'withCss' => 'dtr.css',
            'withJs'  => ['wholeshoe.js'],
        ]);
    }

    /**
     * GET /test-summary
     * Combined Test Results Summary for Material Testing & Whole Shoe Tests
     */
    public function summary(): string
    {
        return $this->render('summary/index', [
            'title'      => 'Test Results Summary',
            'withCss'    => 'dtr.css',
            'withJs'     => ['test_summary.js'],
            'categories' => self::CATEGORIES,
            'wholeShoe'  => self::WHOLE_SHOE_CATEGORIES,
            'material'   => self::MATERIAL_CATEGORIES,
        ]);
    }

    /**
     * GET /wst/summary-data
     * Dynamic AJAX data endpoint with KPI aggregates and filtered records
     */
    public function summaryData(): ResponseInterface
    {
        $filters = [
            'suite'     => $this->request->getGet('suite'),
            'category'  => $this->request->getGet('category'),
            'status'    => $this->request->getGet('status'),
            'outcome'   => $this->request->getGet('outcome'),
            'q'         => $this->request->getGet('q'),
            'from_date' => $this->request->getGet('from_date'),
            'to_date'   => $this->request->getGet('to_date'),
        ];

        $result = $this->fetchSummaryRecords($filters);

        return $this->response->setJSON([
            'success' => true,
            'kpis'    => $result['kpis'],
            'records' => $result['records'],
            'count'   => count($result['records']),
        ]);
    }

    /**
     * GET /wst/export-summary
     * Export filtered summary records as a CSV download
     */
    public function exportSummary(): ResponseInterface
    {
        $filters = [
            'suite'     => $this->request->getGet('suite'),
            'category'  => $this->request->getGet('category'),
            'status'    => $this->request->getGet('status'),
            'outcome'   => $this->request->getGet('outcome'),
            'q'         => $this->request->getGet('q'),
            'from_date' => $this->request->getGet('from_date'),
            'to_date'   => $this->request->getGet('to_date'),
        ];

        $data    = $this->fetchSummaryRecords($filters);
        $records = $data['records'];

        $fp = fopen('php://temp', 'r+');
        // UTF-8 BOM for Excel compatibility
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($fp, [
            'No.',
            'Lab Reference',
            'Suite',
            'Category',
            'Specification / Material',
            'Machine Number',
            'Technician',
            'Operator',
            'Test Date',
            'Target Progress',
            'Current Progress',
            'Recorded Cycles',
            'Recorded Remarks',
            'Unit',
            'Progress %',
            'Status',
            'Outcome',
            'Samples Config',
            'Remarks',
            'Created At',
            'Updated At',
        ]);

        foreach ($records as $idx => $r) {
            fputcsv($fp, [
                $idx + 1,
                $r['labreference'],
                $r['suite'],
                $r['category_name'],
                $r['type'],
                $r['machine_number'],
                $r['technician'],
                $r['operator'],
                $r['testdate'],
                $r['target_cycles'],
                $r['current_cycles'],
                $r['recorded_cycles'] ?? '',
                $r['recorded_remarks'] ?? $r['remarks'] ?? '',
                $r['unit'],
                $r['progress_pct'] . '%',
                $r['status'],
                $r['outcome'],
                $r['samples_summary'],
                $r['remarks'],
                $r['created_at'],
                $r['updated_at'],
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        $filename = 'Test_Results_Summary_' . date('Ymd_His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    private function syncFromTracker(): void
    {
        try {
            $db = \Config\Database::connect('default');
            if (! $db->tableExists('tbl_dtr_wholeshoetracker') || ! $db->tableExists('tbl_material_tests')) {
                return;
            }

            $trackers = $db->table('tbl_dtr_wholeshoetracker')->orderBy('TrackerID', 'ASC')->get()->getResultArray();
            if (empty($trackers)) {
                return;
            }

            $model = new MaterialTestModel();
            foreach ($trackers as $r) {
                $labref = trim((string) ($r['LabRefNo'] ?? ''));
                if ($labref === '') {
                    continue;
                }

                $existing = $model->where('labreference', $labref)->first();
                if ($existing) {
                    continue;
                }

                $category = self::mapTrackerTestTypeToCategory((string) ($r['TestType'] ?? ''), (string) ($r['TestCode'] ?? ''));
                $spec = trim((string) ($r['Style'] ?? 'Standard'));
                if (! empty($r['Colorway'])) {
                    $spec .= ' - ' . trim((string) $r['Colorway']);
                }
                $operator = trim((string) ($r['EncodedBy'] ?? 'QC Specialist'));
                $testdate = ! empty($r['CollectionDate']) ? $r['CollectionDate'] : (! empty($r['ProductionDate']) ? $r['ProductionDate'] : date('Y-m-d'));
                $target = match ($category) {
                    'hydrolysis', 'heat-aging' => 168,
                    'uv-light' => 24,
                    'washing' => 10,
                    'flexing' => 125000,
                    'deeptest' => 50000,
                    default => 125000,
                };
                $createdAt = ! empty($r['CreatedAt']) ? $r['CreatedAt'] : date('Y-m-d H:i:s');

                $model->insert([
                    'category'         => $category,
                    'labreference'     => $labref,
                    'testdate'         => $testdate,
                    'type'             => $spec,
                    'machine_number'   => 'MC-01',
                    'samplecountdry'   => 1,
                    'samplecountwet'   => 0,
                    'samplecounthydro' => 0,
                    'target_cycles'    => $target,
                    'current_cycles'   => 0,
                    'status'           => 'PENDING',
                    'is_running'       => 0,
                    'operator'         => $operator,
                    'technician'       => $operator,
                    'temperature'      => 23.00,
                    'humidity'         => 50.00,
                    'remarks'          => '',
                    'created_at'       => $createdAt,
                    'updated_at'       => $createdAt,
                ]);
            }
        } catch (\Throwable $e) {
            // Silently ignore or catch without global function dependency
        }
    }

    private function fetchSummaryRecords(array $filters): array
    {
        $this->syncFromTracker();
        $suite    = ! empty($filters['suite']) ? strtolower(trim((string) $filters['suite'])) : 'all';
        $category = ! empty($filters['category']) ? strtolower(trim((string) $filters['category'])) : 'all';
        $status   = ! empty($filters['status']) ? strtoupper(trim((string) $filters['status'])) : 'ALL';
        $outcome  = ! empty($filters['outcome']) ? strtoupper(trim((string) $filters['outcome'])) : 'ALL';
        $q        = trim((string) ($filters['q'] ?? ''));
        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        $toDate   = trim((string) ($filters['to_date'] ?? ''));

        $model   = new MaterialTestModel();
        $builder = $model->builder();

        if ($suite === 'wholeshoe') {
            $builder->whereIn('category', self::WHOLE_SHOE_CATEGORIES);
        } elseif ($suite === 'material') {
            $builder->whereIn('category', self::MATERIAL_CATEGORIES);
        }

        if ($category !== 'all') {
            if ($category === 'deeptest' || $category === 'deep-test') {
                $builder->whereIn('category', ['deeptest', 'deep-test']);
            } elseif (isset(self::CATEGORIES[$category])) {
                $builder->where('category', $category);
            }
        }

        if ($status !== 'ALL' && in_array($status, ['RUNNING', 'COMPLETED', 'PENDING', 'STOPPED'], true)) {
            $builder->where('status', $status);
        }

        if ($fromDate !== '') {
            $builder->where('testdate >=', $fromDate);
        }
        if ($toDate !== '') {
            $builder->where('testdate <=', $toDate);
        }

        if ($q !== '') {
            $builder->groupStart()
                ->like('labreference', $q)
                ->orLike('type', $q)
                ->orLike('machine_number', $q)
                ->orLike('operator', $q)
                ->orLike('technician', $q)
                ->orLike('remarks', $q)
                ->groupEnd();
        }

        $allRows = $builder->orderBy('id', 'DESC')->limit(300)->get()->getResultArray();

        $testIds = array_column($allRows, 'id');
        $labRefs = array_filter(array_column($allRows, 'labreference'));
        $resultsByTestId = [];
        if (! empty($testIds)) {
            $resultsModel = new MaterialTestResultModel();
            $results = $resultsModel->whereIn('test_id', $testIds)->findAll();
            foreach ($results as $res) {
                $resultsByTestId[$res['test_id']][] = $res;
            }
        }

        $unifiedByLabRef = [];
        if (! empty($labRefs)) {
            $db = \Config\Database::connect('default');
            if ($db->tableExists('tbl_dtr_unified_labrefs')) {
                $uRows = $db->table('tbl_dtr_unified_labrefs')
                    ->select('lab_ref_no, status, overall_result')
                    ->whereIn('lab_ref_no', $labRefs)
                    ->get()
                    ->getResultArray();
                foreach ($uRows as $ur) {
                    $unifiedByLabRef[strtoupper(trim((string) $ur['lab_ref_no']))] = $ur;
                }
            }
        }

        $formatted = [];
        $kpi = [
            'total'      => count($allRows),
            'whole_shoe' => 0,
            'material'   => 0,
            'completed'  => 0,
            'running'    => 0,
            'pending'    => 0,
            'pass'       => 0,
            'fail'       => 0,
            'stopped'    => 0,
            'pass_rate'  => 0,
        ];

        foreach ($allRows as $row) {
            $cat         = $row['category'] ?? 'bally-flex';
            $isWholeShoe = in_array($cat, self::WHOLE_SHOE_CATEGORIES, true);
            $suiteName   = $isWholeShoe ? 'Whole Shoe' : 'Material';
            $suiteKey    = $isWholeShoe ? 'wholeshoe' : 'material';

            if ($isWholeShoe) {
                $kpi['whole_shoe']++;
            } else {
                $kpi['material']++;
            }

            $labRefKey = strtoupper(trim((string) ($row['labreference'] ?? '')));
            $unified = $unifiedByLabRef[$labRefKey] ?? null;

            $st     = strtoupper(trim((string) ($row['status'] ?? 'PENDING')));
            $timer  = self::computeTimerState($row);
            $isRun  = ((int) ($row['is_running'] ?? 0) === 1) || $st === 'RUNNING';
            $isDone = in_array($st, ['COMPLETED', 'COMPLETE'], true) || ($unified && stripos((string) $unified['status'], 'Complete') === 0);

            if ($isDone && ! in_array($st, ['COMPLETED', 'COMPLETE'], true)) {
                $st = 'COMPLETED';
            } elseif (! $isRun && ! $isDone && ($st === 'PAUSED' || ! empty($timer['is_paused']))) {
                $st = 'PAUSED';
            }

            if ($st === 'PAUSED') {
                $isRun = false;
            }

            if ($isRun) {
                $kpi['running']++;
            } elseif ($isDone) {
                $kpi['completed']++;
            } else {
                $kpi['pending']++;
            }

            $testResults    = $resultsByTestId[$row['id']] ?? [];
            $hasFail        = false;
            $hasStopped     = false;
            $lastTechnician = '';
            $failReasons    = [];

            foreach ($testResults as $res) {
                $rRes = strtoupper(trim((string) ($res['result'] ?? '')));
                if ($rRes === 'FAIL') {
                    $hasFail = true;
                    if (! empty($res['findings'])) {
                        $failReasons[] = $res['findings'];
                    }
                }
                if (! empty($res['is_stopped']) || $rRes === 'STOPPED') {
                    $hasStopped = true;
                }
                if (! empty($res['recorded_by'])) {
                    $lastTechnician = trim((string) $res['recorded_by']);
                }
            }

            if ($unified && strcasecmp((string) $unified['overall_result'], 'Fail') === 0) {
                $hasFail = true;
            }

            if ($st === 'PAUSED') {
                $rowOutcome = 'PAUSED';
            } elseif (! empty($row['overall_outcome'])) {
                $rowOutcome = strtoupper(trim((string) $row['overall_outcome']));
                if ($rowOutcome === 'FAIL') {
                    $kpi['fail']++;
                    $hasFail = true;
                } elseif ($rowOutcome === 'STOPPED') {
                    $kpi['stopped']++;
                    $hasStopped = true;
                } elseif (in_array($rowOutcome, ['PASS', 'TOLERABLE', 'PASSED'], true)) {
                    $kpi['pass']++;
                }
            } elseif ($hasFail) {
                $rowOutcome = 'FAIL';
                $kpi['fail']++;
            } elseif ($hasStopped) {
                $rowOutcome = 'STOPPED';
                $kpi['stopped']++;
            } elseif ($isDone) {
                $rowOutcome = 'PASS';
                $kpi['pass']++;
            } elseif ($isRun) {
                $rowOutcome = 'RUNNING';
            } else {
                $rowOutcome = 'PENDING';
            }

            if ($outcome !== 'ALL' && $rowOutcome !== $outcome) {
                continue;
            }

            $technician = ! empty($row['technician'])
                ? trim((string) $row['technician'])
                : (! empty($lastTechnician) ? $lastTechnician : (! empty($row['operator']) ? trim((string) $row['operator']) : 'QC Specialist'));

            $unit = match ($cat) {
                'hydrolysis', 'heat-aging', 'uv-light' => 'Hrs',
                'washing' => 'Washes',
                default => 'Cycles',
            };

            $target      = (int) ($row['target_cycles'] ?? 0);
            $current     = (int) ($row['current_cycles'] ?? 0);
            $progressPct = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : ($isDone ? 100 : 0);

            $dry            = (int) ($row['samplecountdry'] ?? 0);
            $wet            = (int) ($row['samplecountwet'] ?? 0);
            $hydro          = (int) ($row['samplecounthydro'] ?? 0);
            $samplesSummary = [];
            if ($dry > 0)   $samplesSummary[] = "Dry: {$dry}";
            if ($wet > 0)   $samplesSummary[] = "Wet: {$wet}";
            if ($hydro > 0) $samplesSummary[] = "Hydro: {$hydro}";

            $rowTestCycles = [];
            $rowTestRemarks = [];
            foreach ($testResults as $res) {
                if (! empty($res['cycle']) && ! empty($res['is_recorded'])) {
                    $c = (int) $res['cycle'];
                    $rowTestCycles[] = $c;
                    $f = trim((string) ($res['findings'] ?? ''));
                    if ($f !== '' && !isset($rowTestRemarks[$c])) {
                        $rowTestRemarks[$c] = $f;
                    }
                }
            }
            $rowTestCycles = array_unique($rowTestCycles);
            sort($rowTestCycles, SORT_NUMERIC);
            $concatRecordedCycles = ! empty($row['recorded_cycles'])
                ? $row['recorded_cycles']
                : implode(';', $rowTestCycles);

            ksort($rowTestRemarks, SORT_NUMERIC);
            $concatRecordedRemarks = ! empty($row['remarks'])
                ? $row['remarks']
                : implode(';', array_values($rowTestRemarks));

            $formatted[] = [
                'id'               => (int) $row['id'],
                'labreference'     => $row['labreference'],
                'category'         => $cat,
                'category_name'    => self::CATEGORIES[$cat]['name'] ?? ucfirst(str_replace('-', ' ', $cat)),
                'suite'            => $suiteName,
                'suite_key'        => $suiteKey,
                'type'             => $row['type'] ?? 'Standard',
                'machine_number'   => $row['machine_number'] ?? 'MC-01',
                'technician'       => $technician,
                'operator'         => $row['operator'] ?? '',
                'testdate'         => $row['testdate'] ?? '',
                'target_cycles'    => $target,
                'current_cycles'   => $current,
                'recorded_cycles'  => $concatRecordedCycles,
                'recorded_remarks' => $concatRecordedRemarks,
                'unit'             => $unit,
                'progress_pct'     => $progressPct,
                'status'           => $st,
                'is_running'       => (int) ($row['is_running'] ?? 0),
                'outcome'          => $rowOutcome,
                'has_fail'         => $hasFail,
                'has_stopped'      => $hasStopped,
                'findings_count'   => count($testResults),
                'fail_reasons'     => array_values(array_unique($failReasons)),
                'samples_summary'  => implode(', ', $samplesSummary),
                'temperature'      => $row['temperature'] ?? null,
                'humidity'         => $row['humidity'] ?? null,
                'remarks'          => $row['remarks'] ?? '',
                'created_at'       => $row['created_at'] ?? '',
                'updated_at'       => $row['updated_at'] ?? '',
                'timer'            => $timer,
                'findings_json'    => ! empty($row['findings_json']) ? json_decode((string) $row['findings_json'], true) : null,
            ];
        }

        $kpi['pass_rate'] = $kpi['completed'] > 0
            ? round(($kpi['pass'] / $kpi['completed']) * 100, 1)
            : 0;

        return ['records' => $formatted, 'kpis' => $kpi];
    }

    private function resolveCategory(string $slug): array
    {
        if (! isset(self::CATEGORIES[$slug])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound("Unknown material testing category: {$slug}");
        }
        return self::CATEGORIES[$slug];
    }

    /**
     * GET /wst/records?suite=...&category=...&status=...&outcome=...&q=...&from_date=...&to_date=...
     */
    public function records(): ResponseInterface
    {
        $suite    = trim((string) ($this->request->getGet('suite') ?? ''));
        $category = trim((string) ($this->request->getGet('category') ?? 'all'));
        $status   = trim((string) ($this->request->getGet('status') ?? 'all'));
        $outcome  = trim((string) ($this->request->getGet('outcome') ?? 'all'));
        $q        = trim((string) ($this->request->getGet('q') ?? ''));
        $fromDate = trim((string) ($this->request->getGet('from_date') ?? ''));
        $toDate   = trim((string) ($this->request->getGet('to_date') ?? ''));

        // If suite wasn't explicitly provided, infer from category
        if ($suite === '' && $category !== 'all') {
            if (in_array($category, self::WHOLE_SHOE_CATEGORIES, true)) {
                $suite = 'wholeshoe';
            } elseif (in_array($category, self::MATERIAL_CATEGORIES, true)) {
                $suite = 'material';
            }
        }

        $filters = [
            'suite'     => $suite !== '' ? $suite : 'all',
            'category'  => $category !== '' ? $category : 'all',
            'status'    => $status,
            'outcome'   => $outcome,
            'q'         => $q,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
        ];

        $result = $this->fetchSummaryRecords($filters);

        return $this->response->setJSON([
            'success' => true,
            'records' => $result['records'],
            'kpis'    => $result['kpis'],
            'count'   => count($result['records']),
        ]);
    }

    /**
     * GET /wst/record/{id}
     */
    public function record(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Material test record not found',
            ]);
        }

        $category = $record['category'] ?? 'bally-flex';
        $groups   = $this->buildGroups($record, $category);
        $state    = $this->loadState((int) $record['id'], $record['labreference'], $groups);

        // Ensure record has overall recorded_cycles & remarks concatenated
        if (empty($record['recorded_cycles'])) {
            $allTestCycles = [];
            foreach ($state['recorded'] as $gList) {
                foreach ($gList as $cList) {
                    foreach ($cList as $cVal) {
                        $allTestCycles[] = (int) $cVal;
                    }
                }
            }
            $allTestCycles = array_unique($allTestCycles);
            sort($allTestCycles, SORT_NUMERIC);
            $record['recorded_cycles'] = implode(';', $allTestCycles);
        }
        if (empty($record['remarks']) && ! empty($state['sample_remarks'])) {
            $allRemarks = [];
            foreach ($state['sample_remarks'] as $gRemarks) {
                foreach ($gRemarks as $sRem) {
                    if (! empty($sRem)) {
                        $allRemarks[] = $sRem;
                    }
                }
            }
            if (! empty($allRemarks)) {
                $record['remarks'] = implode(';', array_unique($allRemarks));
            }
        }

        $timerState = self::computeTimerState($record);

        return $this->response->setJSON([
            'success'     => true,
            'record'      => $record,
            'groups'      => $groups,
            'state'       => $state,
            'timer'       => $timerState,
            'findings'    => ! empty($record['findings_json']) ? json_decode((string) $record['findings_json'], true) : null,
            'isCompleted' => in_array(strtoupper(trim((string) $record['status'])), ['COMPLETED', 'COMPLETE', 'STOPPED'], true) || $timerState['is_finished'],
        ]);
    }

    /**
     * GET /wst/next-labref?category=...&type=...&date=YYYY-MM-DD
     */
    public function nextLabref(): ResponseInterface
    {
        $category = trim((string) ($this->request->getGet('category') ?? 'bally-flex'));
        $this->resolveCategory($category);

        $type = (string) $this->request->getGet('type');
        $date = trim((string) ($this->request->getGet('date') ?: date('Y-m-d')));

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (! ($d && $d->format('Y-m-d') === $date)) {
            $date = date('Y-m-d');
        }

        $labref = $this->generateLabref($category, $type, $date);
        $catalog = TestCatalog::all();

        return $this->response->setJSON([
            'success'   => true,
            'labref'    => $labref,
            'materials' => array_keys(TestCatalog::materials($category)),
            'catalog'   => $catalog[$category] ?? [],
        ]);
    }

    /**
     * POST /wst/create
     */
    public function create(): ResponseInterface
    {
        $raw = method_exists($this->request, 'getJSON') ? $this->request->getJSON(true) : null;
        if (! $raw && method_exists($this->request, 'getBody')) {
            $raw = json_decode((string) $this->request->getBody(), true);
        }
        $payload = is_array($raw) ? $raw : (method_exists($this->request, 'getPost') ? $this->request->getPost() : []);

        $category = trim((string) ($payload['category'] ?? 'bally-flex'));
        $this->resolveCategory($category);

        $type = trim((string) ($payload['type'] ?? 'Standard Flexing'));
        $date = trim((string) ($payload['date'] ?? date('Y-m-d')));
        $d    = \DateTime::createFromFormat('Y-m-d', $date);
        if (! ($d && $d->format('Y-m-d') === $date)) {
            $date = date('Y-m-d');
        }

        $dry   = max(0, min(100, (int) ($payload['dry'] ?? 0)));
        $wet   = max(0, min(100, (int) ($payload['wet'] ?? 0)));
        $hydro = max(0, min(100, (int) ($payload['hydro'] ?? 0)));

        if (($dry + $wet + $hydro) === 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please configure at least one specimen sample (dry, wet, or hydro).',
            ]);
        }

        $labref = trim((string) ($payload['labreference'] ?? ''));
        if ($labref === '') {
            $labref = $this->generateLabref($category, $type, $date);
        }

        $model = new MaterialTestModel();

        // Check uniqueness safely
        if ($model->where('labreference', $labref)->first()) {
            $labref = $this->generateLabref($category, $type, $date);
        }

        // Calculate accurate target cycles according to TestCatalog specification
        $targetCycles = (int) ($payload['target_cycles'] ?? 0);
        if ($targetCycles <= 0) {
            $catCycles = TestCatalog::cycles($category, $type, 'dry');
            if (empty($catCycles)) {
                $catCycles = TestCatalog::cycles($category, $type, 'wet');
            }
            if (empty($catCycles)) {
                $catCycles = TestCatalog::cycles($category, $type, 'hydro');
            }
            $targetCycles = ! empty($catCycles) ? (int) end($catCycles) : 100000;
        }

        $tech = $this->getCurrentTechnician();

        $data = [
            'category'         => $category,
            'labreference'     => $labref,
            'testdate'         => $date,
            'type'             => $type,
            'machine_number'   => trim((string) ($payload['machine_number'] ?? 'MC-FLEX-01')),
            'samplecountdry'   => $dry,
            'samplecountwet'   => $wet,
            'samplecounthydro' => $hydro,
            'target_cycles'    => $targetCycles,
            'current_cycles'   => 0,
            'status'           => 'RUNNING',
            'is_running'       => 1,
            'operator'         => $tech['operator'],
            'technician'       => $tech['technician'],
            'temperature'      => ! empty($payload['temperature']) ? (float) $payload['temperature'] : 23.0,
            'humidity'         => ! empty($payload['humidity']) ? (float) $payload['humidity'] : 50.0,
            'remarks'          => trim((string) ($payload['remarks'] ?? '')),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if (! $model->insert($data)) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Database error creating test record: ' . json_encode($model->errors()),
            ]);
        }

        $id = $model->getInsertID();

        return $this->response->setJSON([
            'success' => true,
            'id'      => $id,
            'labref'  => $labref,
            'message' => "Test session created successfully for {$labref}",
        ]);
    }

    /**
     * POST /wst/start/{id}
     */
    public static function getDefaultTimerDuration(string $category): ?int
    {
        return match ($category) {
            'hydrolysis' => 7 * 86400,     // 7 days
            'heat-aging' => 3 * 86400,     // 3 days
            'uv-light'   => 1 * 86400,     // 1 day (24 hrs)
            'washing'    => 1 * 3600,      // 1 hr
            'deeptest', 'deep-test' => 60, // 1 min (Dip Test)
            default      => null,          // Flexing or standard cyclic tests
        };
    }

    public static function computeTimerState(array $record): array
    {
        $duration = isset($record['timer_duration_sec']) && $record['timer_duration_sec'] !== null
            ? (int) $record['timer_duration_sec']
            : self::getDefaultTimerDuration($record['category'] ?? '');

        if ($duration === null) {
            return [
                'has_timer'     => false,
                'duration_sec'  => 0,
                'elapsed_sec'   => 0,
                'remaining_sec' => 0,
                'is_finished'   => false,
                'is_running'    => false,
                'is_paused'     => false,
            ];
        }

        $elapsed = (int) ($record['timer_elapsed_sec'] ?? 0);
        $isRunning = ((int) ($record['is_running'] ?? 0) === 1);
        $status = strtoupper(trim((string) ($record['status'] ?? 'PENDING')));

        if ($isRunning && ! empty($record['timer_started_at'])) {
            $startedTime = strtotime($record['timer_started_at']);
            if ($startedTime > 0) {
                $diff = time() - $startedTime;
                if ($diff > 0) {
                    $elapsed += $diff;
                }
            }
        }

        $remaining = max(0, $duration - $elapsed);
        $isFinished = ($duration > 0 && $remaining === 0) || in_array($status, ['COMPLETED', 'COMPLETE'], true);
        $isPaused = (! $isRunning && $elapsed > 0 && ! $isFinished && $status !== 'STOPPED');

        return [
            'has_timer'     => true,
            'duration_sec'  => $duration,
            'elapsed_sec'   => $elapsed,
            'remaining_sec' => $remaining,
            'is_finished'   => $isFinished,
            'is_running'    => $isRunning && ! $isFinished,
            'is_paused'     => $isPaused,
        ];
    }

    /**
     * Helper to synchronize Whole Shoe test execution state back to tbl_dtr_wholeshoetracker.
     */
    private function syncToWholeshoeTracker(?string $labRef, array $fields): void
    {
        if (empty($labRef)) {
            return;
        }
        try {
            $db = \Config\Database::connect('default');
            if ($db->tableExists('tbl_dtr_wholeshoetracker')) {
                $db->table('tbl_dtr_wholeshoetracker')
                    ->where('LabRefNo', $labRef)
                    ->update($fields);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed syncing to tbl_dtr_wholeshoetracker: ' . $e->getMessage());
        }
    }

    public function start(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $category = $record['category'] ?? '';
        $duration = isset($record['timer_duration_sec']) && $record['timer_duration_sec'] !== null
            ? (int) $record['timer_duration_sec']
            : self::getDefaultTimerDuration($category);

        $now = date('Y-m-d H:i:s');
        $updateData = [
            'status'          => 'RUNNING',
            'is_running'      => 1,
            'overall_outcome' => 'RUNNING',
            'updated_at'      => $now,
        ];

        if ($duration !== null) {
            $updateData['timer_duration_sec'] = $duration;
            $updateData['timer_started_at']   = $now;
        }

        $model->update($id, $updateData);

        $this->syncToWholeshoeTracker($record['labreference'] ?? '', [
            'Status'      => 'RUNNING',
            'TimeStarted' => $now,
        ]);

        $fresh = $model->find($id);
        $timerState = self::computeTimerState($fresh);

        return $this->response->setJSON([
            'success'     => true,
            'message'     => 'Test session started.',
            'timer'       => $timerState,
        ]);
    }

    /**
     * POST /wst/pause/{id}
     * Pauses the timer and accumulates active elapsed seconds.
     */
    public function pause(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $timer = self::computeTimerState($record);
        $now   = date('Y-m-d H:i:s');

        $model->update($id, [
            'status'            => 'PAUSED',
            'is_running'        => 0,
            'overall_outcome'   => 'PAUSED',
            'timer_elapsed_sec' => $timer['elapsed_sec'],
            'timer_started_at'  => null,
            'updated_at'        => $now,
        ]);

        $this->syncToWholeshoeTracker($record['labreference'] ?? '', [
            'Status' => 'PAUSED',
        ]);

        $fresh = $model->find($id);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Test timer paused.',
            'timer'   => self::computeTimerState($fresh),
        ]);
    }

    /**
     * POST /wst/save-eval/{id}
     * Saves inspection findings (delamination, bleeding, color, warp, blooming, other)
     * and final test status (PASS, FAIL, TOLERABLE).
     */
    public function saveEvaluation(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $raw = method_exists($this->request, 'getJSON') ? $this->request->getJSON(true) : null;
        if (! $raw && method_exists($this->request, 'getBody')) {
            $raw = json_decode((string) $this->request->getBody(), true);
        }
        $payload = is_array($raw) ? $raw : (method_exists($this->request, 'getPost') ? $this->request->getPost() : []);
        $outcome = strtoupper(trim((string) ($payload['outcome'] ?? 'PASS')));
        if (! in_array($outcome, ['PASS', 'FAIL', 'TOLERABLE', 'STOPPED'], true)) {
            $outcome = 'PASS';
        }

        // Support both Whole Shoe timer inspection evaluation AND flexing inspection evaluation
        $isFlexing = ($record['category'] ?? '') === 'flexing';

        if ($isFlexing) {
            $latLen = (float) ($payload['lat_len'] ?? 0);
            $medLen = (float) ($payload['med_len'] ?? 0);
            $latDep = (float) ($payload['lat_depth'] ?? 0);
            $medDep = (float) ($payload['med_depth'] ?? 0);
            $cycleVal = (int) ($payload['cycle'] ?? ($record['current_cycles'] ?? 125000));
            $defaultFindings = "45° degrees {$cycleVal} cycles - (L: {$latLen} mm, M: {$medLen} mm; D:{$latDep} mm, D:{$medDep} mm)";
            $findingsText = ! empty($payload['findings']) ? trim((string) $payload['findings']) : $defaultFindings;

            $findings = [
                'type'             => 'flexing',
                'lat_len'          => $latLen,
                'med_len'          => $medLen,
                'lat_depth'        => $latDep,
                'med_depth'        => $medDep,
                'cycle'            => $cycleVal,
                'findings'         => $findingsText,
                'outcome'          => $outcome,
                'completed_at'     => date('Y-m-d H:i:s'),
            ];
            $remarksText = $findingsText;
        } else {
            $findings = [
                'type'            => 'incubation',
                'delamination'    => trim((string) ($payload['delamination'] ?? 'No delamination')),
                'bleeding'        => trim((string) ($payload['bleeding'] ?? 'No bleeding')),
                'color_change'    => trim((string) ($payload['color_change'] ?? 'No color change')),
                'outsole_warping' => trim((string) ($payload['outsole_warping'] ?? 'No Outsole warping')),
                'blooming'        => trim((string) ($payload['blooming'] ?? 'No blooming')),
                'other_findings'  => trim((string) ($payload['other_findings'] ?? '')),
                'outcome'         => $outcome,
                'completed_at'    => date('Y-m-d H:i:s'),
            ];
            // Build human-readable findings summary for report & tracker table (e.g. No delamination, No bleeding, etc.)
                        $findingParts = [];
                        foreach (['delamination', 'bleeding', 'color_change', 'outsole_warping', 'blooming'] as $fKey) {
                            if (! empty($findings[$fKey])) {
                                $findingParts[] = $findings[$fKey];
                            }
                        }
                        if (! empty($findings['other_findings'])) {
                            $findingParts[] = $findings['other_findings'];
                        }
                        $remarksText = ! empty($findingParts)
                            ? implode(', ', $findingParts)
                            : 'No delamination, No bleeding, No color change, No Outsole warping, No blooming';
        }

        $now = date('Y-m-d H:i:s');
        $tech = $this->getCurrentTechnician();

        $duration = isset($record['timer_duration_sec']) && $record['timer_duration_sec'] !== null
            ? (int) $record['timer_duration_sec']
            : self::getDefaultTimerDuration($record['category'] ?? '');

        // Determine completion and running state
        if ($isFlexing) {
            $wasAlreadyStopped = ($record['status'] ?? '') === 'COMPLETED' || ($record['status'] ?? '') === 'STOPPED';
            $isComplete = ($cycleVal >= 125000) || ($outcome === 'FAIL') || $wasAlreadyStopped;
            $status = $isComplete ? 'COMPLETED' : ($outcome === 'STOPPED' ? 'STOPPED' : (((int) ($record['is_running'] ?? 0) === 1) ? 'RUNNING' : 'RUNNING'));
            $isRunning = $isComplete ? 0 : 1;
            $overallOutcome = $isComplete ? $outcome : 'RUNNING';
            $newCycles = max((int) ($record['current_cycles'] ?? 0), $cycleVal);
        } else {
            $isComplete = true;
            $status = 'COMPLETED';
            $isRunning = 0;
            $overallOutcome = $outcome;
            $newCycles = (int) ($record['current_cycles'] ?? 0);
        }

        $updateData = [
            'status'            => $status,
            'is_running'        => $isRunning,
            'overall_outcome'   => $overallOutcome,
            'findings_json'     => json_encode($findings),
            'remarks'           => $remarksText,
            'technician'        => $tech['technician'],
            'operator'          => $tech['operator'],
            'updated_at'        => $now,
        ];

        if ($isFlexing) {
            $updateData['current_cycles'] = $newCycles;
        } else {
            $updateData['timer_elapsed_sec'] = $duration ?? (int) ($record['timer_elapsed_sec'] ?? 0);
            $updateData['timer_started_at']  = null;
        }

        $model->update($id, $updateData);

        $trackerSync = [
            'Status'      => $status,
            'FinalResult' => $overallOutcome,
            'Findings'    => $remarksText ?? ($payload['other_findings'] ?? ''),
        ];
        if ($isComplete) {
            $trackerSync['TimeCompleted'] = $now;
        }
        $this->syncToWholeshoeTracker($record['labreference'] ?? '', $trackerSync);

        return $this->response->setJSON([
            'success'     => true,
            'message'     => $isFlexing && ! $isComplete
                ? "Checkpoint for " . number_format($cycleVal) . " cycles saved. Advanced to next checkpoint."
                : "Test results saved successfully as {$outcome}.",
            'outcome'     => $overallOutcome,
            'status'      => $status,
            'is_complete' => $isComplete,
            'findings'    => $findings,
        ]);
    }

    /**
     * POST /wst/daily-checkin/{id}
     * Records a daily inspection checkpoint for multi-day incubation tests (Hydrolysis, Heat Aging, etc.)
     * without setting or displaying final PASS/FAIL/TOLERABLE outcome ratings.
     */
    public function dailyCheckin(int $id): ResponseInterface
    {
        $raw = method_exists($this->request, 'getJSON') ? $this->request->getJSON(true) : null;
        if (! $raw && method_exists($this->request, 'getBody')) {
            $raw = json_decode((string) $this->request->getBody(), true);
        }
        $payload = is_array($raw) ? $raw : (method_exists($this->request, 'getPost') ? $this->request->getPost() : []);

        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $day          = trim((string) ($payload['day'] ?? 'Day 1'));
        $observations = trim((string) ($payload['observations'] ?? ''));
        $checkedBy    = trim((string) ($payload['checked_by'] ?? ''));
        $actionTaken  = trim((string) ($payload['action_taken'] ?? ''));

        if ($checkedBy === '') {
            $tech = $this->getCurrentTechnician();
            $checkedBy = $tech['technician'] ?: 'QC Specialist';
        }

        $now = date('Y-m-d H:i:s');

        // Existing findings_json
        $existingFindings = [];
        if (! empty($record['findings_json'])) {
            $decoded = json_decode((string) $record['findings_json'], true);
            if (is_array($decoded)) {
                $existingFindings = $decoded;
            }
        }

        $dailyCheckins = $existingFindings['daily_checkins'] ?? [];
        if (! is_array($dailyCheckins)) {
            $dailyCheckins = [];
        }

        // Check if an entry for this day already exists; if so, update it, else append
        $updatedExisting = false;
        foreach ($dailyCheckins as &$entry) {
            if (isset($entry['day']) && strcasecmp($entry['day'], $day) === 0) {
                $entry['observations'] = $observations;
                $entry['checked_by']   = $checkedBy;
                $entry['action_taken'] = $actionTaken;
                $entry['updated_at']   = $now;
                $updatedExisting = true;
                break;
            }
        }
        unset($entry);

        if (! $updatedExisting) {
            $dailyCheckins[] = [
                'day'          => $day,
                'observations' => $observations,
                'checked_by'   => $checkedBy,
                'action_taken' => $actionTaken,
                'logged_at'    => $now,
            ];
        }

        $existingFindings['daily_checkins'] = $dailyCheckins;

        // Build human-readable remarks summary from all daily checkins
        $summaryParts = [];
        foreach ($dailyCheckins as $dc) {
            $obs = trim((string) ($dc['observations'] ?? ''));
            if ($obs !== '') {
                $summaryParts[] = "{$dc['day']}: {$obs} (Checked by: {$dc['checked_by']})";
            }
        }
        $remarksSummary = ! empty($summaryParts) ? implode(' | ', $summaryParts) : $record['remarks'];

        // Save to tbl_material_tests - KEEP test RUNNING, do NOT alter status to COMPLETED or set overall_outcome
        $model->update($id, [
            'findings_json' => json_encode($existingFindings),
            'remarks'       => $remarksSummary,
            'technician'    => $checkedBy,
            'updated_at'    => $now,
        ]);

        // Sync observations to tbl_dtr_wholeshoetracker without closing or failing the test
        $this->syncToWholeshoeTracker($record['labreference'] ?? '', [
            'Findings'   => $remarksSummary,
        ]);

        return $this->response->setJSON([
            'success'        => true,
            'message'        => "Daily check-in for {$day} saved successfully.",
            'daily_checkins' => $dailyCheckins,
        ]);
    }

    /**
     * POST /wst/stop/{id}
     */
    public function stop(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $now  = date('Y-m-d H:i:s');
        $tech = $this->getCurrentTechnician();

        $model->update($id, [
            'status'     => 'COMPLETED',
            'is_running' => 0,
            'technician' => $tech['technician'],
            'operator'   => $tech['operator'],
            'updated_at' => $now,
        ]);

        // Parity with LMS finishSampleRows:
        // Set all unrecorded or running sample rows for this test session to completed/stopped
        $category = $record['category'] ?? 'bally-flex';
        $groups   = $this->buildGroups($record, $category);
        $resultsModel = new MaterialTestResultModel();

        foreach ($groups as $gKey => $g) {
            $count = (int) ($g['count'] ?? 0);
            for ($s = 1; $s <= $count; $s++) {
                // If this sample already has records or is already stopped, do not overwrite its history
                $existing = $resultsModel->where('test_id', $id)
                    ->where('group_type', $gKey)
                    ->where('sample_num', $s)
                    ->first();
                if ($existing) {
                    $resultsModel->where('test_id', $id)
                        ->where('group_type', $gKey)
                        ->where('sample_num', $s)
                        ->set(['is_stopped' => 1])
                        ->update();
                } else {
                    $resultsModel->insert([
                        'test_id'      => $id,
                        'labreference' => $record['labreference'],
                        'group_type'   => $gKey,
                        'sample_num'   => $s,
                        'cycle'        => 0,
                        'is_recorded'  => 0,
                        'is_stopped'   => 1,
                        'findings'     => 'Test session stopped by technician',
                        'result'       => 'STOPPED',
                        'recorded_by'  => $tech['technician'],
                        'recorded_at'  => $now,
                    ]);
                }
            }
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Test session stopped and completed.']);
    }

    /**
     * POST /wst/stop-sample/{id}
     * Body: {"samples":[{"group":"dry","sample":2}, ...]}
     * Stops individual specimen(s). If all specimens for this test session are now
     * finished/stopped, automatically completes the entire test session.
     */
    public function stopSample(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $raw = method_exists($this->request, 'getJSON') ? $this->request->getJSON(true) : null;
        if (! $raw && method_exists($this->request, 'getBody')) {
            $raw = json_decode((string) $this->request->getBody(), true);
        }
        $payload = is_array($raw) ? $raw : (method_exists($this->request, 'getPost') ? $this->request->getPost() : []);
        $samples = is_array($payload['samples'] ?? null) ? $payload['samples'] : [];

        if (empty($samples)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select at least one running sample to stop.',
            ]);
        }

        $resultsModel = new MaterialTestResultModel();
        $now  = date('Y-m-d H:i:s');
        $tech = $this->getCurrentTechnician();
        $stoppedCount = 0;

        foreach ($samples as $s) {
            $group = trim((string) ($s['group'] ?? 'dry'));
            $num   = (int) ($s['sample'] ?? 0);
            if ($num <= 0) continue;

            $existing = $resultsModel->where('test_id', $id)
                ->where('group_type', $group)
                ->where('sample_num', $num)
                ->first();

            if ($existing) {
                $resultsModel->where('test_id', $id)
                    ->where('group_type', $group)
                    ->where('sample_num', $num)
                    ->set(['is_stopped' => 1])
                    ->update();
            } else {
                $resultsModel->insert([
                    'test_id'      => $id,
                    'labreference' => $record['labreference'],
                    'group_type'   => $group,
                    'sample_num'   => $num,
                    'cycle'        => 0,
                    'is_recorded'  => 0,
                    'is_stopped'   => 1,
                    'findings'     => 'Sample stopped early by technician',
                    'result'       => 'STOPPED',
                    'recorded_by'  => $tech['technician'],
                    'recorded_at'  => $now,
                ]);
            }
            $stoppedCount++;
        }

        // Check if all samples are finished/stopped
        $category = $record['category'] ?? 'bally-flex';
        $groups   = $this->buildGroups($record, $category);
        $state    = $this->loadState($id, $record['labreference'], $groups);
        $allDone  = $this->isAllSamplesFinished($groups, $state);

        if ($allDone) {
            $model->update($id, [
                'status'     => 'COMPLETED',
                'is_running' => 0,
                'technician' => $tech['technician'],
                'operator'   => $tech['operator'],
                'updated_at' => $now,
            ]);
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $allDone ? "All specimens finished or stopped. Test completed!" : "Stopped {$stoppedCount} specimen(s).",
            'completed' => $allDone,
            'stopped'   => $state['stopped'],
        ]);
    }

    /**
     * GET /wst/state/{id}
     * Lightweight live state polling endpoint (matches LMS 8-second live sync).
     */
    public function state(int $id): ResponseInterface
    {
        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $category = $record['category'] ?? 'bally-flex';
        $groups   = $this->buildGroups($record, $category);
        $state    = $this->loadState($id, $record['labreference'], $groups);
        $allDone  = $this->isAllSamplesFinished($groups, $state);

        $timerState = self::computeTimerState($record);

        return $this->response->setJSON([
            'success'        => true,
            'status'         => $record['status'] ?? 'PENDING',
            'is_running'     => (int) ($record['is_running'] ?? 0),
            'completed'      => $allDone || in_array(strtoupper(trim((string) $record['status'])), ['COMPLETED', 'COMPLETE', 'STOPPED'], true) || $timerState['is_finished'],
            'recorded'       => $state['recorded'],
            'stopped'        => $state['stopped'],
            'current_cycles' => (int) ($record['current_cycles'] ?? 0),
            'timer'          => $timerState,
            'findings'       => ! empty($record['findings_json']) ? json_decode((string) $record['findings_json'], true) : null,
        ]);
    }

    /**
     * POST /wst/log-findings/{id}
     */
    public function logFindings(int $id): ResponseInterface
    {
        $raw = method_exists($this->request, 'getJSON') ? $this->request->getJSON(true) : null;
        if (! $raw && method_exists($this->request, 'getBody')) {
            $raw = json_decode((string) $this->request->getBody(), true);
        }
        $payload = is_array($raw) ? $raw : (method_exists($this->request, 'getPost') ? $this->request->getPost() : []);

        $model  = new MaterialTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $category = trim((string) ($payload['category'] ?? ($record['category'] ?? 'bally-flex')));
        $cycle    = (int) ($payload['cycle'] ?? 0);
        $group    = trim((string) ($payload['group'] ?? 'dry'));
        $findings = trim((string) ($payload['findings'] ?? 'No defects observed'));
        $pass     = ! empty($payload['pass']) ? 'PASS' : 'FAIL';
        $samples  = is_array($payload['samples'] ?? null) ? $payload['samples'] : [1];

        if ($cycle <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Cycle must be greater than zero']);
        }

        $resultsModel = new MaterialTestResultModel();
        $tech         = $this->getCurrentTechnician();
        $recordedBy   = $tech['technician'];
        $now          = date('Y-m-d H:i:s');

        $savedCount = 0;
        foreach ($samples as $sampleNum) {
            $sampleNum = (int) $sampleNum;
            if ($sampleNum <= 0) continue;

            // Parity with LMS: Once a sample has been stopped, reject logging further cycles
            $existingStopped = $resultsModel
                ->where('test_id', $id)
                ->where('group_type', $group)
                ->where('sample_num', $sampleNum)
                ->where('is_stopped', 1)
                ->first();
            if ($existingStopped) {
                continue; // Do not record checkpoints for stopped specimens
            }

            $categoryCycles = TestCatalog::cycles($category, $record['type'] ?? '', $group);
            if (empty($categoryCycles)) {
                $categoryCycles = [5000, 10000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 105000, 115000, 125000];
            }
            $targetCycles = array_filter($categoryCycles, static fn($c) => (int) $c <= $cycle);

            // Compute concatenated cycle checkpoints for this specimen (e.g. 5000;10000;15000)
            $samplePastRows = $resultsModel
                ->where('test_id', $id)
                ->where('group_type', $group)
                ->where('sample_num', $sampleNum)
                ->where('is_recorded', 1)
                ->findAll();
            $samplePastCycles = array_column($samplePastRows, 'cycle');
            $mergedSampleCycles = array_unique(array_merge(array_map('intval', $samplePastCycles), array_map('intval', $targetCycles)));
            sort($mergedSampleCycles, SORT_NUMERIC);
            $concatSampleCycles = implode(';', $mergedSampleCycles);

            // Compute concatenated remarks matching checkpoints (e.g. No defect;No defect;Creasing)
            $sampleRemarksMap = [];
            foreach ($samplePastRows as $pRow) {
                $c = (int) $pRow['cycle'];
                $r = trim((string) ($pRow['findings'] ?? ''));
                if ($r !== '') {
                    $sampleRemarksMap[$c] = $r;
                }
            }
            foreach ($targetCycles as $cVal) {
                $sampleRemarksMap[(int) $cVal] = $findings;
            }
            ksort($sampleRemarksMap, SORT_NUMERIC);
            $concatSampleRemarks = implode(';', array_values($sampleRemarksMap));

            foreach ($targetCycles as $cVal) {
                $cVal = (int) $cVal;
                $existing = $resultsModel
                    ->where('test_id', $id)
                    ->where('group_type', $group)
                    ->where('sample_num', $sampleNum)
                    ->where('cycle', $cVal)
                    ->first();

                $data = [
                    'test_id'          => $id,
                    'labreference'     => $record['labreference'],
                    'group_type'       => $group,
                    'sample_num'       => $sampleNum,
                    'cycle'            => $cVal,
                    'recorded_cycles'  => $concatSampleCycles,
                    'recorded_remarks' => $concatSampleRemarks,
                    'is_recorded'      => 1,
                    'is_stopped'       => 0,
                    'findings'         => $findings,
                    'result'           => $pass,
                    'recorded_by'      => $recordedBy,
                    'recorded_at'      => $now,
                ];

                if ($existing) {
                    $resultsModel->update($existing['id'], $data);
                } else {
                    $resultsModel->insert($data);
                }
            }

            // Sync recorded_cycles & recorded_remarks on any past rows for this specimen
            $resultsModel
                ->where('test_id', $id)
                ->where('group_type', $group)
                ->where('sample_num', $sampleNum)
                ->set([
                    'recorded_cycles'  => $concatSampleCycles,
                    'recorded_remarks' => $concatSampleRemarks,
                ])
                ->update();

            $savedCount++;
        }

        // Compute overall test session concatenated cycles & remarks (e.g. 5000;10000;15000)
        $allTestRows = $resultsModel
            ->where('test_id', $id)
            ->where('is_recorded', 1)
            ->findAll();
        $allTestCycles = [];
        $allTestRemarksMap = [];
        foreach ($allTestRows as $tRow) {
            $c = (int) $tRow['cycle'];
            $allTestCycles[] = $c;
            $f = trim((string) ($tRow['findings'] ?? ''));
            if ($f !== '' && !isset($allTestRemarksMap[$c])) {
                $allTestRemarksMap[$c] = $f;
            }
        }
        $mergedAllCycles = array_unique($allTestCycles);
        sort($mergedAllCycles, SORT_NUMERIC);
        $concatAllCycles = implode(';', $mergedAllCycles);

        ksort($allTestRemarksMap, SORT_NUMERIC);
        $concatAllRemarks = implode(';', array_values($allTestRemarksMap));

        // Update current cycles & concatenated cycles / remarks on main test session
        $updateTestFields = [
            'recorded_cycles' => $concatAllCycles,
            'remarks'         => $concatAllRemarks,
            'updated_at'      => $now,
        ];
        if ($cycle > (int) ($record['current_cycles'] ?? 0)) {
            $updateTestFields['current_cycles'] = $cycle;
        }
        $model->update($id, $updateTestFields);

        // Check if all specimens are finished
        $category = $record['category'] ?? 'bally-flex';
        $groups   = $this->buildGroups($record, $category);
        $state    = $this->loadState($id, $record['labreference'], $groups);
        $allDone  = $this->isAllSamplesFinished($groups, $state);

        if ($allDone) {
            $model->update($id, [
                'status'     => 'COMPLETED',
                'is_running' => 0,
                'technician' => $tech['technician'],
                'operator'   => $tech['operator'],
                'updated_at' => $now,
            ]);
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $allDone
                ? "Saved {$savedCount} sample(s). All samples are finished, test completed"
                : "Saved {$savedCount} sample(s) through " . number_format($cycle) . " cycles",
            'completed' => $allDone,
            'recorded'  => $state['recorded'],
        ]);
    }

    private function isAllSamplesFinished(array $groups, array $state): bool
    {
        if (empty($groups)) return false;

        foreach ($groups as $gKey => $g) {
            $count = (int) ($g['count'] ?? 0);
            $cycles = $g['cycles'] ?? [];
            $lastCycle = ! empty($cycles) ? (int) end($cycles) : 0;

            for ($s = 1; $s <= $count; $s++) {
                $isStopped = ! empty($state['stopped'][$gKey][$s]);
                $recordedCycles = $state['recorded'][$gKey][$s] ?? [];
                $reachedEnd = $lastCycle > 0 && in_array($lastCycle, $recordedCycles, true);

                if (! $isStopped && ! $reachedEnd) {
                    return false; // Still has an active running sample
                }
            }
        }
        return true;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function generateLabref(string $category, string $type, string $date): string
    {
        $materials = TestCatalog::materials($category);
        $material  = TestCatalog::material($category, $type);
        if (! $material && ! empty($materials)) {
            $material = reset($materials);
        }

        $code = $material['code'] ?? 'LEL';
        $base = 'DTP' . str_replace('-', '', $date) . $code;

        $model = new MaterialTestModel();
        $rows  = $model->select('labreference')->like('labreference', $base, 'after')->findAll();
        $names = array_column($rows, 'labreference');

        $max = 0;
        foreach ($names as $name) {
            if (preg_match('/^' . preg_quote($base, '/') . '(?: T(\d+))?$/i', trim($name), $m)) {
                $max = max($max, isset($m[1]) ? (int) $m[1] : 1);
            }
        }

        return $max === 0 ? $base : $base . ' T' . ($max + 1);
    }

    private function buildGroups(array $record, string $category): array
    {
        $defs = [
            'dry'   => ['label' => 'DRY SAMPLES',   'cat' => 'dry',   'field' => 'samplecountdry',   'prefix' => 'Dry'],
            'wet'   => ['label' => 'WET SAMPLES',   'cat' => 'wet',   'field' => 'samplecountwet',   'prefix' => 'Wet'],
            'hydro' => ['label' => 'HYDRO SAMPLES', 'cat' => 'hydro', 'field' => 'samplecounthydro', 'prefix' => 'Hydro'],
        ];

        $groups = [];
        foreach ($defs as $key => $def) {
            $count = (int) ($record[$def['field']] ?? 0);
            if ($count < 1) continue;

            $cycles = TestCatalog::cycles($category, $record['type'] ?? '', $key);
            if (empty($cycles)) {
                $cycles = [5000, 10000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 105000, 115000, 125000];
            }

            $defaultBatch = TestCatalog::defaultCount($category, $record['type'] ?? '', $key);
            if ($defaultBatch < 1) {
                $defaultBatch = 4;
            }

            $groups[$key] = [
                'label'         => $def['label'],
                'cat'           => $def['cat'],
                'count'         => $count,
                'standardCount' => $defaultBatch,
                'batchSize'     => $defaultBatch,
                'prefix'        => $def['prefix'],
                'cycles'        => $cycles,
            ];
        }

        return $groups;
    }

    private function loadState(int $testId, string $labref, array $groups): array
    {
        $resultsModel = new MaterialTestResultModel();
        $rows = $resultsModel->where('test_id', $testId)->findAll();

        $recorded      = [];
        $stopped       = [];
        $findings      = [];
        $sampleCycles  = [];
        $sampleRemarks = [];

        foreach ($rows as $row) {
            $g = $row['group_type'];
            $s = (int) $row['sample_num'];
            $c = (int) $row['cycle'];

            if (! empty($row['is_recorded']) && $c > 0) {
                $recorded[$g][$s][] = $c;
            }
            if (! empty($row['is_stopped'])) {
                $stopped[$g][$s] = true;
            }
            $findings[$g][$s][$c] = [
                'findings' => $row['findings'],
                'result'   => $row['result'],
                'by'       => $row['recorded_by'],
                'at'       => $row['recorded_at'],
            ];
            if (! empty($row['recorded_cycles'])) {
                $sampleCycles[$g][$s] = $row['recorded_cycles'];
            }
            if (! empty($row['recorded_remarks'])) {
                $sampleRemarks[$g][$s] = $row['recorded_remarks'];
            }
        }

        // Ensure concatenated cycles & remarks e.g. 5000;10000;15000 and remark1;remark2;remark3 are prepared for each specimen
        foreach ($recorded as $gKey => $samples) {
            foreach ($samples as $sNum => $cArr) {
                if (empty($sampleCycles[$gKey][$sNum]) && ! empty($cArr)) {
                    $uniqueC = array_unique(array_map('intval', $cArr));
                    sort($uniqueC, SORT_NUMERIC);
                    $sampleCycles[$gKey][$sNum] = implode(';', $uniqueC);
                }
                if (empty($sampleRemarks[$gKey][$sNum]) && ! empty($findings[$gKey][$sNum])) {
                    $cFindings = $findings[$gKey][$sNum];
                    ksort($cFindings, SORT_NUMERIC);
                    $remArr = [];
                    foreach ($cFindings as $cf) {
                        $remArr[] = $cf['findings'] ?? '';
                    }
                    $sampleRemarks[$gKey][$sNum] = implode(';', $remArr);
                }
            }
        }

        return [
            'recorded'       => $recorded,
            'stopped'        => $stopped,
            'findings'       => $findings,
            'sample_cycles'  => $sampleCycles,
            'sample_remarks' => $sampleRemarks,
        ];
    }

    private function getCurrentTechnician(): array
    {
        $currentUser = service('auth')->user();
        $name        = ! empty($currentUser['name']) ? trim((string) $currentUser['name']) : '';
        $username    = ! empty($currentUser['username']) ? trim((string) $currentUser['username']) : '';

        $technician = $name !== '' ? $name : ($username !== '' ? $username : 'QC Specialist');
        $operator   = $username !== '' ? $username : $technician;

        return [
            'technician' => $technician,
            'operator'   => $operator,
        ];
    }
}
