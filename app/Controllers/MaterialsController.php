<?php

namespace App\Controllers;

use App\Models\MaterialTestModel;
use App\Models\MaterialTestResultModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controller for Material Testing Suite
 * Dedicated controller separated from Whole Shoe testing.
 */
class MaterialsController extends BaseController
{
    public const MATERIAL_CATEGORIES = [
        'martindale-abrasion',
        'taber-abrasion',
        'lace-abrasion',
        'crocking',
        'ross-flex',
    ];

    private const CATEGORIES = [
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
     * GET /materials
     * Material Testing: the materials received in the Lab Tracker (Material Received), each Lab Ref with the tests its material type has as children
     */
    public function index(): string
    {
        \App\Libraries\MaterialCatalog::backfill();

        return $this->render('materials/index', [
            'title'      => 'Material Testing',
            'withCss'    => 'dtr.css',
            'withJs'     => ['material-collection.js'],
            'categories' => \App\Libraries\MaterialCatalog::categories(),
            'canEdit'    => self::canEdit(),
        ]);
    }

    /** GET /materials/machines: the machine monitoring of the cyclic tests (Martindale, Taber, Lace Abrasion, Crocking, Bally and Ross Flex), kept as it was; no menu entry */
    public function machines(): string
    {
        return $this->render('materials/machines', ['title' => 'Material machine tests', 'withCss' => 'dtr.css', 'withJs' => ['materials.js']]);
    }

    /** who may record the status / result of a material test */
    public static function canEdit(): bool
    {
        return (bool) array_intersect(['Admin', 'Supervisor', 'Manager', 'Material PIC', 'Materials', 'Technician'], \App\Libraries\Roles::parse((string) (service('auth')->user()['role'] ?? '')));
    }

    /**
     * GET /materials/list?q=&category=&status=&from=&to=
     * The received materials (newest first) with their tests; counts per category for the Material Categories list.
     */
    public function list(): ResponseInterface
    {
        \App\Libraries\MaterialCatalog::ensureTables();
        $db   = \Config\Database::connect();
        $q    = trim((string) $this->request->getGet('q'));
        $cat  = trim((string) $this->request->getGet('category'));
        $st   = trim((string) $this->request->getGet('status'));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('from')) ? (string) $this->request->getGet('from') : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('to')) ? (string) $this->request->getGet('to') : '';

        $b = $db->table('tbl_dtr_MaterialTracker')->orderBy('ReceivedDate', 'DESC')->orderBy('TrackerID', 'DESC')->limit(600);
        if ($q !== '') {
            $b->groupStart()->like('LabRefNo', $q)->orLike('MaterialName', $q)->orLike('MaterialCode', $q)->orLike('Supplier', $q)->orLike('Style', $q)->orLike('ProductionCode', $q)->orLike('MTL', $q)->groupEnd();
        }
        if ($from !== '') {
            $b->where('ReceivedDate >=', $from);
        }
        if ($to !== '') {
            $b->where('ReceivedDate <=', $to);
        }
        $mothers = $b->get()->getResultArray();
        $kids    = [];
        if ($mothers) {
            foreach ($db->table('tbl_dtr_MaterialTrackerTests')->whereIn('MotherLabRefNo', array_column($mothers, 'LabRefNo'))->orderBy('SortNo')->orderBy('TestID')->get()->getResultArray() as $k) {
                $kids[$k['MotherLabRefNo']][] = [
                    'id' => (int) $k['TestID'], 'child' => (string) $k['ChildLabRefNo'], 'code' => (string) $k['TestCode'], 'name' => (string) $k['TestType'], 'method' => (string) ($k['TestMethod'] ?? ''),
                    'unit' => (string) ($k['Unit'] ?? ''), 'status' => (string) ($k['Status'] ?: 'Pending'), 'result' => (string) ($k['Result'] ?? ''), 'tested' => (string) ($k['TestedDate'] ?? ''),
                    'tech' => (string) ($k['Technician'] ?? ''), 'remarks' => (string) ($k['Remarks'] ?? ''),
                ];
            }
        }
        $names  = array_column(\App\Libraries\MaterialCatalog::categories(), 'name', 'slug');
        $counts = array_fill_keys(array_keys($names), 0);
        $rows   = [];
        foreach ($mothers as $m) {
            $spec = (string) ($db->query('SELECT MaterialType FROM tbl_dtr_material_type_status WHERE MaterialCode = ? LIMIT 1', [$m['MaterialCode']])->getRow('MaterialType') ?? '');
            $res  = \App\Libraries\MaterialCatalog::resolve($spec !== '' ? $spec : (string) $m['MaterialType']) ?? \App\Libraries\MaterialCatalog::resolve((string) $m['MaterialType']);
            $slug = $res['category'] ?? '';
            if ($slug !== '') {
                $counts[$slug]++;
            }
            if ($cat !== '' && $cat !== $slug) {
                continue;
            }
            $tests = $kids[$m['LabRefNo']] ?? [];
            $done  = count(array_filter($tests, static fn ($t) => $t['status'] === 'Completed'));
            $any   = count(array_filter($tests, static fn ($t) => $t['status'] === 'Ongoing'));
            $state = ! $tests ? 'No tests' : ($done === count($tests) ? 'Completed' : ($done > 0 || $any > 0 ? 'Ongoing' : 'Pending'));
            $fail  = count(array_filter($tests, static fn ($t) => strtoupper($t['result']) === 'FAIL'));
            if ($st !== '' && $st !== $state) {
                continue;
            }
            $rows[] = [
                'id' => (int) $m['TrackerID'], 'lab_ref' => (string) $m['LabRefNo'], 'received' => (string) $m['ReceivedDate'], 'arrived' => (string) $m['DateArrived'], 'code' => (string) $m['MaterialCode'],
                'name' => (string) $m['MaterialName'], 'type' => $res['type'] ?? (string) $m['MaterialType'], 'erp_type' => (string) $m['MaterialType'], 'category' => $slug, 'category_name' => $names[$slug] ?? '',
                'supplier' => (string) $m['Supplier'], 'style' => (string) $m['Style'], 'colorway' => (string) $m['Colorway'], 'mtl' => (string) $m['MTL'], 'prod' => (string) $m['ProductionCode'],
                'qty' => (string) $m['Qty'], 'qcqty' => (string) $m['QCQty'], 'unit' => (string) ($m['Unit'] ?? ''), 'tests' => $tests, 'done' => $done, 'total' => count($tests), 'fail' => $fail, 'state' => $state,
            ];
        }

        return $this->response->setJSON(['ok' => true, 'can_edit' => self::canEdit(), 'rows' => $rows, 'counts' => $counts, 'names' => $names, 'total' => count($mothers)]);
    }

    /** POST /materials/test-update {id, status, result, tested_date, technician, remarks}: Admin, Supervisor, Manager, Material PIC, Materials, Technician */
    public function testUpdate(): ResponseInterface
    {
        if (! self::canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only the laboratory staff can record a material test.', 'csrf' => csrf_hash()]);
        }
        $p    = $this->request->getJSON(true) ?? [];
        $db   = \Config\Database::connect();
        $row  = $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) ($p['id'] ?? 0))->get()->getRowArray();
        $fail = fn (string $m, int $c = 422) => $this->response->setStatusCode($c)->setJSON(['ok' => false, 'message' => $m, 'csrf' => csrf_hash()]);
        if (! $row) {
            return $fail('That test no longer exists.', 404);
        }
        $status = (string) ($p['status'] ?? $row['Status']);
        if (! in_array($status, ['Pending', 'Ongoing', 'Completed'], true)) {
            return $fail('Status must be Pending, Ongoing or Completed.');
        }
        $result = strtoupper(trim((string) ($p['result'] ?? '')));
        if ($result !== '' && ! in_array($result, ['PASS', 'TOLERABLE', 'FAIL'], true)) {
            return $fail('Result must be PASS, TOLERABLE or FAIL.');
        }
        if ($status === 'Completed' && $result === '') {
            return $fail('Choose the result (PASS, TOLERABLE or FAIL) to complete a test.');
        }
        $date = trim((string) ($p['tested_date'] ?? ''));
        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fail('The tested date is not valid.');
        }
        if ($status === 'Completed' && $date === '') {
            $date = date('Y-m-d');
        }
        $u = service('auth')->user();
        $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) $row['TestID'])->update([
            'Status' => $status, 'Result' => $status === 'Pending' ? null : ($result ?: null), 'TestedDate' => $status === 'Pending' ? null : ($date ?: null),
            'Technician' => mb_substr(trim((string) ($p['technician'] ?? '')), 0, 120), 'Remarks' => mb_substr(trim((string) ($p['remarks'] ?? '')), 0, 500),
            'UpdatedBy' => mb_substr((string) ($u['name'] ?? $u['username'] ?? ''), 0, 120), 'UpdatedAt' => date('Y-m-d H:i:s'),
        ]);
        \App\Libraries\DataCache::bump();
        \App\Libraries\ActivityLog::record('materials', 'test-update', (string) $row['ChildLabRefNo'], $status . ($result !== '' ? ' ' . $result : ''));

        return $this->response->setJSON(['ok' => true, 'message' => 'Saved.', 'csrf' => csrf_hash()]);
    }

    // ------------------------------------------------------------------------------------------------------------------ the test sheet (values, average, standard, rating)
    /** the Access type name(s) and category of a received material, for the standards: 'A|B' for a combined type */
    private function sheetType(array $mother): array
    {
        $db   = \Config\Database::connect();
        $spec = (string) ($db->query('SELECT MaterialType FROM tbl_dtr_material_type_status WHERE MaterialCode = ? LIMIT 1', [$mother['MaterialCode']])->getRow('MaterialType') ?? '');
        $res  = \App\Libraries\MaterialCatalog::resolve($spec !== '' ? $spec : (string) $mother['MaterialType']) ?? \App\Libraries\MaterialCatalog::resolve((string) $mother['MaterialType']);

        return $res ? ['type' => implode('|', $res['types']), 'label' => $res['type'], 'cat' => $res['category']] : ['type' => '', 'label' => '', 'cat' => ''];
    }

    private function sheetTest(array $t, array $ty, ?float $gauge): array
    {
        $values = json_decode((string) ($t['ValuesJson'] ?? ''), true) ?: [];
        $calc   = \App\Libraries\MaterialSheet::compute((string) $t['TestCode'], $ty['type'], $ty['cat'], $values, $gauge);

        return [
            'id' => (int) $t['TestID'], 'child' => (string) $t['ChildLabRefNo'], 'code' => (string) $t['TestCode'], 'name' => (string) $t['TestType'], 'method' => (string) ($t['TestMethod'] ?? ''), 'unit' => (string) ($t['Unit'] ?? ''),
            'layout' => \App\Libraries\MaterialSheet::layout((string) $t['TestCode'], $ty['type'], $ty['cat']), 'values' => (object) $values, 'calc' => $calc,
            'status' => (string) ($t['Status'] ?: 'Pending'), 'result' => (string) ($t['Result'] ?? ''), 'tested' => (string) ($t['TestedDate'] ?? ''), 'tech' => (string) ($t['Technician'] ?? ''), 'remarks' => (string) ($t['Remarks'] ?? ''),
        ];
    }

    /** GET /materials/sheet?mother=<Lab Ref>[&test=<TestID>]: the material and its test sheet (all tests of the Lab Ref, or the one test) */
    public function sheet(): ResponseInterface
    {
        \App\Libraries\MaterialCatalog::ensureTables();
        \App\Libraries\MaterialSheet::ensureColumns();
        $db     = \Config\Database::connect();
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', trim((string) $this->request->getGet('mother')))->get()->getRowArray();
        if (! $mother) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That Lab Reference was not found.']);
        }
        $ty    = $this->sheetType($mother);
        $only  = (int) $this->request->getGet('test');
        $b     = $db->table('tbl_dtr_MaterialTrackerTests')->where('MotherLabRefNo', $mother['LabRefNo'])->orderBy('SortNo')->orderBy('TestID');
        if ($only > 0) {
            $b->where('TestID', $only);
        }
        $gauge = \App\Libraries\MaterialSheet::gaugeOf((string) $mother['LabRefNo']);
        $tests = array_map(fn ($t) => $this->sheetTest($t, $ty, $gauge), $b->get()->getResultArray());

        return $this->response->setJSON(['ok' => true, 'can_edit' => self::canEdit(), 'gauge' => $gauge, 'mother' => [
            'lab_ref' => $mother['LabRefNo'], 'type' => $ty['label'], 'received' => (string) $mother['ReceivedDate'], 'arrived' => (string) $mother['DateArrived'], 'code' => (string) $mother['MaterialCode'],
            'name' => (string) $mother['MaterialName'], 'supplier' => (string) $mother['Supplier'], 'style' => (string) $mother['Style'], 'colorway' => (string) $mother['Colorway'], 'mtl' => (string) $mother['MTL'],
            'prod' => (string) $mother['ProductionCode'], 'qty' => (string) $mother['Qty'], 'unit' => (string) ($mother['Unit'] ?? ''),
        ], 'tests' => $tests]);
    }

    /** POST /materials/sheet-calc {mother, tests:[{id, values}]}: averages, standards and ratings of what is typed, nothing saved */
    public function sheetCalc(): ResponseInterface
    {
        $p      = $this->request->getJSON(true) ?? [];
        $db     = \Config\Database::connect();
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', trim((string) ($p['mother'] ?? '')))->get()->getRowArray();
        if (! $mother) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That Lab Reference was not found.', 'csrf' => csrf_hash()]);
        }
        $ty    = $this->sheetType($mother);
        $gauge = null;
        $tests = (array) ($p['tests'] ?? []);
        foreach ($tests as $t) {                    // the thickness typed on the sheet decides the standards of the other tests
            if (($t['code'] ?? '') === 'GAU') {
                $g = \App\Libraries\MaterialSheet::compute('GAU', $ty['type'], $ty['cat'], (array) ($t['values'] ?? []), null);
                $gauge = $g['rows']['T']['ave'] ?? null;
            }
        }
        $gauge ??= \App\Libraries\MaterialSheet::gaugeOf((string) $mother['LabRefNo']);
        $out = [];
        foreach ($tests as $t) {
            $out[(int) ($t['id'] ?? 0)] = \App\Libraries\MaterialSheet::compute((string) ($t['code'] ?? ''), $ty['type'], $ty['cat'], (array) ($t['values'] ?? []), $gauge);
        }

        return $this->response->setJSON(['ok' => true, 'gauge' => $gauge, 'tests' => $out, 'csrf' => csrf_hash()]);
    }

    /** POST /materials/sheet-save {id, values, technician, tested_date, remarks}: saves the values; average, rating and status are worked out here */
    public function sheetSave(): ResponseInterface
    {
        if (! self::canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only the laboratory staff can record a material test.', 'csrf' => csrf_hash()]);
        }
        \App\Libraries\MaterialSheet::ensureColumns();
        $p   = $this->request->getJSON(true) ?? [];
        $db  = \Config\Database::connect();
        $t   = $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) ($p['id'] ?? 0))->get()->getRowArray();
        if (! $t) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That test no longer exists.', 'csrf' => csrf_hash()]);
        }
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', $t['MotherLabRefNo'])->get()->getRowArray();
        $ty     = $this->sheetType($mother ?: ['MaterialCode' => '', 'MaterialType' => (string) $t['MaterialType']]);
        $clean  = [];
        foreach ((array) ($p['values'] ?? []) as $k => $v) {          // only the boxes of the test, as text
            if (preg_match('/^[A-Za-z0-9_]{1,30}$/', (string) $k) && (is_scalar($v) || $v === null) && trim((string) $v) !== '') {
                $clean[(string) $k] = mb_substr(trim((string) $v), 0, 200);
            }
        }
        $gauge = $t['TestCode'] === 'GAU' ? null : \App\Libraries\MaterialSheet::gaugeOf((string) $t['MotherLabRefNo']);
        $calc  = \App\Libraries\MaterialSheet::compute((string) $t['TestCode'], $ty['type'], $ty['cat'], $clean, $gauge);
        $date  = trim((string) ($p['tested_date'] ?? ''));
        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'The tested date is not valid.', 'csrf' => csrf_hash()]);
        }
        $status = $calc['result'] !== '' ? 'Completed' : ($calc['any'] ? 'Ongoing' : 'Pending');
        if ($status !== 'Pending' && $date === '') {
            $date = (string) ($t['TestedDate'] ?: date('Y-m-d'));
        }
        $u = service('auth')->user();
        $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) $t['TestID'])->update([
            'ValuesJson' => $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null, 'Result' => $calc['result'] ?: null, 'Status' => $status, 'TestedDate' => $status === 'Pending' ? null : $date,
            'Technician' => mb_substr(trim((string) ($p['technician'] ?? '')), 0, 120), 'Remarks' => mb_substr(trim((string) ($p['remarks'] ?? '')), 0, 500),
            'UpdatedBy' => mb_substr((string) ($u['name'] ?? $u['username'] ?? ''), 0, 120), 'UpdatedAt' => date('Y-m-d H:i:s'),
        ]);
        if ($t['TestCode'] === 'GAU') {            // the thickness decides the standard of the tensile / tear / burst tests: they are rated again
            $g = $calc['rows']['T']['ave'] ?? null;
            foreach ($db->table('tbl_dtr_MaterialTrackerTests')->where('MotherLabRefNo', $t['MotherLabRefNo'])->whereIn('TestCode', ['TEN', 'ELO', 'TRT', 'STT', 'MUL'])->where('ValuesJson IS NOT NULL', null, false)->get()->getResultArray() as $d) {
                $c = \App\Libraries\MaterialSheet::compute((string) $d['TestCode'], $ty['type'], $ty['cat'], json_decode((string) $d['ValuesJson'], true) ?: [], $g);
                $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) $d['TestID'])->update(['Result' => $c['result'] ?: null, 'Status' => $c['result'] !== '' ? 'Completed' : ($c['any'] ? 'Ongoing' : 'Pending')]);
            }
        }
        \App\Libraries\DataCache::bump();
        \App\Libraries\ActivityLog::record('materials', 'sheet-save', (string) $t['ChildLabRefNo'], $status . ($calc['result'] !== '' ? ' ' . $calc['result'] : ''));

        return $this->response->setJSON(['ok' => true, 'message' => 'Saved: ' . $status . ($calc['result'] !== '' ? ' - ' . $calc['result'] : '') . '.', 'status' => $status, 'result' => $calc['result'], 'calc' => $calc, 'csrf' => csrf_hash()]);
    }
}
