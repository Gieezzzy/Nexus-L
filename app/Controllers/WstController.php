<?php

namespace App\Controllers;

use App\Models\WstTestModel;
use App\Models\WstTestResultModel;
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

    private function fetchSummaryRecords(array $filters): array
    {
        $suite    = ! empty($filters['suite']) ? strtolower(trim((string) $filters['suite'])) : 'all';
        $category = ! empty($filters['category']) ? strtolower(trim((string) $filters['category'])) : 'all';
        $status   = ! empty($filters['status']) ? strtoupper(trim((string) $filters['status'])) : 'ALL';
        $outcome  = ! empty($filters['outcome']) ? strtoupper(trim((string) $filters['outcome'])) : 'ALL';
        $q        = trim((string) ($filters['q'] ?? ''));
        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        $toDate   = trim((string) ($filters['to_date'] ?? ''));

        $model   = new WstTestModel();
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
        $resultsByTestId = [];
        if (! empty($testIds)) {
            $resultsModel = new WstTestResultModel();
            $results = $resultsModel->whereIn('test_id', $testIds)->findAll();
            foreach ($results as $res) {
                $resultsByTestId[$res['test_id']][] = $res;
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

            $st     = strtoupper(trim((string) ($row['status'] ?? 'PENDING')));
            $isRun  = ((int) ($row['is_running'] ?? 0) === 1) || $st === 'RUNNING';
            $isDone = in_array($st, ['COMPLETED', 'COMPLETE'], true);

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

            if ($hasFail) {
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
                'status'           => $row['status'] ?? 'PENDING',
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
        $model  = new WstTestModel();
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

        return $this->response->setJSON([
            'success'     => true,
            'record'      => $record,
            'groups'      => $groups,
            'state'       => $state,
            'isCompleted' => in_array(strtoupper(trim((string) $record['status'])), ['COMPLETED', 'COMPLETE', 'STOPPED'], true),
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
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

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

        $model = new WstTestModel();

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
    public function start(int $id): ResponseInterface
    {
        $model  = new WstTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $model->update($id, [
            'status'     => 'RUNNING',
            'is_running' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Test session started.']);
    }

    /**
     * POST /wst/stop/{id}
     */
    public function stop(int $id): ResponseInterface
    {
        $model  = new WstTestModel();
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
        $resultsModel = new WstTestResultModel();

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
        $model  = new WstTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $samples = is_array($payload['samples'] ?? null) ? $payload['samples'] : [];

        if (empty($samples)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select at least one running sample to stop.',
            ]);
        }

        $resultsModel = new WstTestResultModel();
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
        $model  = new WstTestModel();
        $record = $model->find($id);

        if (! $record) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Record not found']);
        }

        $category = $record['category'] ?? 'bally-flex';
        $groups   = $this->buildGroups($record, $category);
        $state    = $this->loadState($id, $record['labreference'], $groups);
        $allDone  = $this->isAllSamplesFinished($groups, $state);

        return $this->response->setJSON([
            'success'        => true,
            'status'         => $record['status'] ?? 'PENDING',
            'is_running'     => (int) ($record['is_running'] ?? 0),
            'completed'      => $allDone || in_array(strtoupper(trim((string) $record['status'])), ['COMPLETED', 'COMPLETE', 'STOPPED'], true),
            'recorded'       => $state['recorded'],
            'stopped'        => $state['stopped'],
            'current_cycles' => (int) ($record['current_cycles'] ?? 0),
        ]);
    }

    /**
     * POST /wst/log-findings/{id}
     */
    public function logFindings(int $id): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $model  = new WstTestModel();
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

        $resultsModel = new WstTestResultModel();
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
                $categoryCycles = [5000, 10000, 15000, 20000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 100000];
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

        $model = new WstTestModel();
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
                $cycles = [5000, 10000, 15000, 20000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 100000];
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
        $resultsModel = new WstTestResultModel();
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
