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
            'userName'   => (string) (service('auth')->user()['name'] ?? service('auth')->user()['username'] ?? ''),
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
        $mothers = [];
        $kids = [];
        foreach ($b->get()->getResultArray() as $m0) {      // one line per mother Lab Ref; rows saved by Material Receive are child Lab Refs (one per sample per test) of it
            $mk = trim((string) ($m0['MotherLabRefNo'] ?? '')) !== '' ? (string) $m0['MotherLabRefNo'] : (string) $m0['LabRefNo'];
            if (! isset($mothers[$mk])) {
                $mothers[$mk] = ['LabRefNo' => $mk] + $m0;
            }
            if (trim((string) ($m0['TestCode'] ?? '')) !== '' && $mk !== (string) $m0['LabRefNo']) {
                $kids[$mk][] = [
                    'src' => 't', 'id' => (int) $m0['TrackerID'], 'child' => (string) $m0['LabRefNo'], 'code' => (string) $m0['TestCode'], 'name' => (string) ($m0['TestType'] ?? ''), 'method' => '', 'unit' => '',
                    'status' => (string) (($m0['Status'] ?? '') ?: 'Pending'), 'result' => (string) ($m0['Result'] ?? ''), 'tested' => '', 'tech' => '', 'remarks' => (string) ($m0['Remarks'] ?? ''),
                ];
            }
        }
        $mothers = array_values($mothers);
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
        $mine   = array_flip(array_column($mothers, 'LabRefNo'));
        $counts = array_fill_keys(array_keys($names), 0);
        $allCodes = array_unique(array_filter(array_column($mothers, 'MaterialCode'), fn ($c) => $c !== '' && $c !== null));
        $specMap  = [];
        if ($allCodes) {
            foreach ($db->table('tbl_dtr_material_type_status')->select('MaterialCode, MaterialType')->whereIn('MaterialCode', array_values($allCodes))->get()->getResultArray() as $sm) {
                $specMap[$sm['MaterialCode']] = (string) $sm['MaterialType'];
            }
        }
        $rows   = [];
        foreach ($mothers as $m) {
            $spec = $specMap[$m['MaterialCode']] ?? '';
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
            $tol   = count(array_filter($tests, static fn ($t) => strtoupper($t['result']) === 'TOLERABLE'));
            if ($st !== '' && $st !== $state) {
                continue;
            }
            $rows[] = [
                'id' => (int) $m['TrackerID'], 'lab_ref' => (string) $m['LabRefNo'], 'received' => (string) $m['ReceivedDate'], 'arrived' => (string) $m['DateArrived'], 'code' => (string) $m['MaterialCode'],
                'name' => (string) $m['MaterialName'], 'type' => $res['type'] ?? (string) $m['MaterialType'], 'erp_type' => (string) $m['MaterialType'], 'category' => $slug, 'category_name' => $names[$slug] ?? '',
                'supplier' => (string) $m['Supplier'], 'style' => (string) $m['Style'], 'colorway' => (string) $m['Colorway'], 'mtl' => (string) $m['MTL'], 'prod' => (string) $m['ProductionCode'],
                'qty' => (string) $m['Qty'], 'qcqty' => (string) $m['QCQty'], 'unit' => (string) ($m['Unit'] ?? ''), 'tests' => $tests, 'done' => $done, 'total' => count($tests), 'fail' => $fail, 'tol' => $tol, 'state' => $state,
            ];
        }

        // the Lab Material Test Collection of the Lab Tracker (the bench log of the machines: tests with machine, technician, result) is part of this list too
        foreach (self::benchGroups($q, $from, $to) as $g) {
            if (isset($mine[$g['lab_ref']])) {
                continue;
            }
            if ($g['category'] !== '') {
                $counts[$g['category']] = ($counts[$g['category']] ?? 0) + 1;
            }
            if (($cat !== '' && $cat !== $g['category']) || ($st !== '' && $st !== $g['state'])) {
                continue;
            }
            $rows[] = $g;
        }

        return $this->response->setJSON(['ok' => true, 'can_edit' => self::canEdit(), 'rows' => $rows, 'counts' => $counts, 'names' => $names, 'total' => count($mothers) + count(array_filter(self::benchGroups($q, $from, $to), static fn ($g) => ! isset($mine[$g['lab_ref']])))]);
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
        \App\Libraries\DataCache::bump('material');
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
        $ov     = \App\Libraries\MaterialSheet::cleanOverride($values['_layout'] ?? null);
        unset($values['_layout']);
        $calc   = \App\Libraries\MaterialSheet::compute((string) $t['TestCode'], $ty['type'], $ty['cat'], $values + ['_layout' => $ov], $gauge);

        return [
            'id' => (int) $t['TestID'], 'child' => (string) $t['ChildLabRefNo'], 'code' => (string) $t['TestCode'], 'name' => (string) $t['TestType'], 'method' => (string) ($t['TestMethod'] ?? ''), 'unit' => (string) ($t['Unit'] ?? ''),
            'layout' => \App\Libraries\MaterialSheet::layout((string) $t['TestCode'], $ty['type'], $ty['cat'], $ov), 'override' => $ov, 'removed' => \App\Libraries\MaterialSheet::removedRows((string) $t['TestCode'], $ty['type'], $ty['cat'], $ov),
            'values' => (object) $values, 'calc' => $calc,
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
                $g = \App\Libraries\MaterialSheet::compute('GAU', $ty['type'], $ty['cat'], (array) ($t['values'] ?? []) + ['_layout' => \App\Libraries\MaterialSheet::cleanOverride($t['layout'] ?? null)], null);
                $gauge = $g['rows']['T']['ave'] ?? null;
            }
        }
        $gauge ??= \App\Libraries\MaterialSheet::gaugeOf((string) $mother['LabRefNo']);
        $out = [];
        foreach ($tests as $t) {
            $out[(int) ($t['id'] ?? 0)] = \App\Libraries\MaterialSheet::compute((string) ($t['code'] ?? ''), $ty['type'], $ty['cat'], (array) ($t['values'] ?? []) + ['_layout' => \App\Libraries\MaterialSheet::cleanOverride($t['layout'] ?? null)], $gauge);
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
        if (\App\Libraries\ReportPdf::signedLocked((string) $t['ChildLabRefNo']) || \App\Libraries\ReportPdf::signedLocked((string) $t['MotherLabRefNo'])) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'This report is approved and signed. Only an Admin can change it.', 'csrf' => csrf_hash()]);
        }
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', $t['MotherLabRefNo'])->get()->getRowArray();
        $ty     = $this->sheetType($mother ?: ['MaterialCode' => '', 'MaterialType' => (string) $t['MaterialType']]);
        $clean  = [];
        foreach ((array) ($p['values'] ?? []) as $k => $v) {          // only the boxes of the test, as text
            if (preg_match('/^[A-Za-z0-9_]{1,30}$/', (string) $k) && (is_scalar($v) || $v === null) && trim((string) $v) !== '') {
                $clean[(string) $k] = mb_substr(trim((string) $v), 0, 200);
            }
        }
        $lay   = array_key_exists('layout', $p) ? \App\Libraries\MaterialSheet::cleanOverride($p['layout']) : \App\Libraries\MaterialSheet::cleanOverride((json_decode((string) ($t['ValuesJson'] ?? ''), true) ?: [])['_layout'] ?? null);
        $gauge = $t['TestCode'] === 'GAU' ? null : \App\Libraries\MaterialSheet::gaugeOf((string) $t['MotherLabRefNo']);
        $calc  = \App\Libraries\MaterialSheet::compute((string) $t['TestCode'], $ty['type'], $ty['cat'], $clean + ['_layout' => $lay], $gauge);
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
            'ValuesJson' => ($clean || $lay['hide'] || $lay['n']) ? json_encode($clean + (($lay['hide'] || $lay['n']) ? ['_layout' => $lay] : []), JSON_UNESCAPED_UNICODE) : null, 'Result' => $calc['result'] ?: null, 'Status' => $status, 'TestedDate' => $status === 'Pending' ? null : $date,
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
        \App\Libraries\DataCache::bump('material');
        \App\Libraries\ActivityLog::record('materials', 'sheet-save', (string) $t['ChildLabRefNo'], $status . ($calc['result'] !== '' ? ' ' . $calc['result'] : ''));

        return $this->response->setJSON(['ok' => true, 'message' => 'Saved: ' . $status . ($calc['result'] !== '' ? ' - ' . $calc['result'] : '') . '.', 'status' => $status, 'result' => $calc['result'], 'calc' => $calc, 'csrf' => csrf_hash()]);
    }

    private function noEdit(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only the laboratory staff can change a material test.', 'csrf' => csrf_hash()]);
    }

    /**
     * POST /materials/sheet-layout {id, layout:{hide:[], n:{}}, values}: a test item was removed / brought back or its number of samples changed on the sheet.
     * The layout is kept with the test; the stored result is worked out again with it; the answer carries the new layout and the numbers of the typed values (nothing typed is saved here).
     */
    public function sheetLayout(): ResponseInterface
    {
        if (! self::canEdit()) {
            return $this->noEdit();
        }
        $p  = $this->request->getJSON(true) ?? [];
        $db = \Config\Database::connect();
        $t  = $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) ($p['id'] ?? 0))->get()->getRowArray();
        if (! $t) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That test no longer exists.', 'csrf' => csrf_hash()]);
        }
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', $t['MotherLabRefNo'])->get()->getRowArray();
        $ty     = $this->sheetType($mother ?: ['MaterialCode' => '', 'MaterialType' => (string) $t['MaterialType']]);
        $ov     = \App\Libraries\MaterialSheet::cleanOverride($p['layout'] ?? null);
        $stored = json_decode((string) ($t['ValuesJson'] ?? ''), true) ?: [];
        unset($stored['_layout']);
        $gauge  = $t['TestCode'] === 'GAU' ? null : \App\Libraries\MaterialSheet::gaugeOf((string) $t['MotherLabRefNo']);
        $c      = \App\Libraries\MaterialSheet::compute((string) $t['TestCode'], $ty['type'], $ty['cat'], $stored + ['_layout' => $ov], $gauge);
        $status = $c['result'] !== '' ? 'Completed' : ($c['any'] ? 'Ongoing' : 'Pending');
        $u      = service('auth')->user();
        $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) $t['TestID'])->update([
            'ValuesJson' => ($stored || $ov['hide'] || $ov['n']) ? json_encode($stored + (($ov['hide'] || $ov['n']) ? ['_layout' => $ov] : []), JSON_UNESCAPED_UNICODE) : null,
            'Result' => $c['result'] ?: null, 'Status' => $stored ? $status : (string) ($t['Status'] ?: 'Pending'), 'UpdatedBy' => mb_substr((string) ($u['name'] ?? $u['username'] ?? ''), 0, 120), 'UpdatedAt' => date('Y-m-d H:i:s'),
        ]);
        $typed = [];
        foreach ((array) ($p['values'] ?? []) as $k => $v) {
            if (preg_match('/^[A-Za-z0-9_]{1,30}$/', (string) $k) && (is_scalar($v) || $v === null) && trim((string) $v) !== '') {
                $typed[(string) $k] = mb_substr(trim((string) $v), 0, 200);
            }
        }
        $calc = \App\Libraries\MaterialSheet::compute((string) $t['TestCode'], $ty['type'], $ty['cat'], $typed + ['_layout' => $ov], $gauge);
        \App\Libraries\DataCache::bump('material');
        \App\Libraries\ActivityLog::record('materials', 'sheet-layout', (string) $t['ChildLabRefNo'], 'removed: ' . implode(',', $ov['hide']) . ' samples: ' . json_encode($ov['n']));

        return $this->response->setJSON(['ok' => true, 'layout' => \App\Libraries\MaterialSheet::layout((string) $t['TestCode'], $ty['type'], $ty['cat'], $ov), 'override' => $ov, 'removed' => \App\Libraries\MaterialSheet::removedRows((string) $t['TestCode'], $ty['type'], $ty['cat'], $ov),
            'calc' => $calc, 'csrf' => csrf_hash()]);
    }

    /** POST /materials/test-remove {id}: a test type that does not apply to this Lab Ref is taken off its list (it can be added again with test-add) */
    public function testRemove(): ResponseInterface
    {
        if (! self::canEdit()) {
            return $this->noEdit();
        }
        $p  = $this->request->getJSON(true) ?? [];
        $db = \Config\Database::connect();
        if (($p['src'] ?? '') === 't') {   // a Material Receive child Lab Ref (a row of tbl_dtr_MaterialTracker)
            $row = $db->table('tbl_dtr_MaterialTracker')->where('TrackerID', (int) ($p['id'] ?? 0))->where('MotherLabRefNo !=', '')->where('TestCode !=', '')->get()->getRowArray();
            if (! $row) {
                return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That test is already gone.', 'csrf' => csrf_hash()]);
            }
            $db->table('tbl_dtr_MaterialTracker')->where('TrackerID', (int) $row['TrackerID'])->delete();
            \App\Libraries\DataCache::bump('material');
            \App\Libraries\ActivityLog::record('materials', 'test-remove', (string) $row['LabRefNo'], 'deleted: ' . $row['TestType'] . ($row['Result'] ? ' (it had result ' . $row['Result'] . ')' : ''));

            return $this->response->setJSON(['ok' => true, 'message' => $row['TestType'] . ' (' . $row['LabRefNo'] . ') was deleted.', 'csrf' => csrf_hash()]);
        }
        $t  = $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) ($p['id'] ?? 0))->get()->getRowArray();
        if (! $t) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That test is already gone.', 'csrf' => csrf_hash()]);
        }
        $db->table('tbl_dtr_MaterialTrackerTests')->where('TestID', (int) $t['TestID'])->delete();
        \App\Libraries\DataCache::bump('material');
        \App\Libraries\ActivityLog::record('materials', 'test-remove', (string) $t['ChildLabRefNo'], 'not applicable: ' . $t['TestType'] . ($t['Result'] ? ' (it had result ' . $t['Result'] . ')' : ''));

        return $this->response->setJSON(['ok' => true, 'message' => $t['TestType'] . ' was removed from ' . $t['MotherLabRefNo'] . '.', 'csrf' => csrf_hash()]);
    }

    /** the tests of the material type that this Lab Ref does not have (any more) */
    private function missingTests(array $mother): array
    {
        $db   = \Config\Database::connect();
        $spec = (string) ($db->query('SELECT MaterialType FROM tbl_dtr_material_type_status WHERE MaterialCode = ? LIMIT 1', [$mother['MaterialCode']])->getRow('MaterialType') ?? '');
        $res  = \App\Libraries\MaterialCatalog::resolve($spec !== '' ? $spec : (string) $mother['MaterialType']) ?? \App\Libraries\MaterialCatalog::resolve((string) $mother['MaterialType']);
        $have = array_column($db->table('tbl_dtr_MaterialTrackerTests')->select('TestCode')->where('MotherLabRefNo', $mother['LabRefNo'])->get()->getResultArray(), 'TestCode');
        $out  = [];
        if (! $res) {   // the material type has no test list: any test of the Select-tests list can be added
            $i = 0;
            foreach (\App\Controllers\DtrController::MATERIAL_TESTS as $name => $code) {
                $i++;
                if (! in_array($code, $have, true)) {
                    $out[] = ['code' => $code, 'name' => $name, 'method' => '', 'sort' => $i];
                }
            }

            return [$out, ['type' => (string) $mother['MaterialType'], 'tests' => []]];
        }
        foreach ($res['tests'] as $i => $code) {
            if (! in_array($code, $have, true)) {
                $d     = \App\Libraries\MaterialCatalog::test($code);
                $out[] = ['code' => $code, 'name' => $d['name'], 'method' => $d['method'], 'sort' => $i + 1];
            }
        }

        return [$out, $res];
    }

    /** POST /materials/delete {mother}: the whole received material (its Lab Ref, every test and result, its container log line) is deleted */
    public function deleteMaterial(): ResponseInterface
    {
        if (! self::canEdit()) {
            return $this->noEdit();
        }
        $p   = $this->request->getJSON(true) ?? [];
        $ref = trim((string) ($p['mother'] ?? ''));
        $db  = \Config\Database::connect();
        if ($ref === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'No Lab Reference given.', 'csrf' => csrf_hash()]);
        }
        $rows = $db->table('tbl_dtr_MaterialTracker')->groupStart()->where('LabRefNo', $ref)->orWhere('MotherLabRefNo', $ref)->groupEnd()->get()->getResultArray();
        if (! $rows) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That material is already gone.', 'csrf' => csrf_hash()]);
        }
        $kids = array_merge([$ref], array_column($rows, 'LabRefNo'), array_column($db->table('tbl_dtr_MaterialTrackerTests')->select('ChildLabRefNo')->where('MotherLabRefNo', $ref)->get()->getResultArray(), 'ChildLabRefNo'));
        foreach ($kids as $k) {
            if (\App\Libraries\ReportPdf::signedLocked(trim((string) $k))) {
                return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'A Lab Ref of this material is approved and signed. Only an Admin can delete it.', 'csrf' => csrf_hash()]);
            }
        }
        $db->transStart();
        $db->table('tbl_dtr_MaterialTrackerTests')->where('MotherLabRefNo', $ref)->delete();
        $db->table('tbl_dtr_MaterialTracker')->groupStart()->where('LabRefNo', $ref)->orWhere('MotherLabRefNo', $ref)->groupEnd()->delete();
        $db->table('tbl_dtr_containers')->where('ContainerNo', $ref)->delete();
        $db->transComplete();
        \App\Libraries\DataCache::bump('material');
        \App\Libraries\ActivityLog::record('materials', 'material-delete', $ref, 'material deleted with ' . count($rows) . ' Lab Ref row(s)');

        return $this->response->setJSON(['ok' => $db->transStatus(), 'message' => $ref . ' was deleted completely.', 'csrf' => csrf_hash()]);
    }

    /** a Material Receive line (child Lab Refs under a mother): its rows, and the tests of the Select-tests list that are not on it yet */
    private function receiveTests(string $mother): ?array
    {
        $rows = \Config\Database::connect()->table('tbl_dtr_MaterialTracker')->where('MotherLabRefNo', $mother)->orderBy('TrackerID')->get()->getResultArray();
        if (! $rows) {
            return null;
        }
        $have = array_column($rows, 'TestType');
        $opts = [];
        foreach (\App\Controllers\DtrController::MATERIAL_TESTS as $name => $code) {
            if (! in_array($name, $have, true)) {
                $opts[] = ['code' => $code, 'name' => $name, 'method' => ''];
            }
        }

        return ['rows' => $rows, 'options' => $opts];
    }

    /** the test is added to a Material Receive line: one child Lab Ref per sample, numbered on from the line's last one */
    private function addReceiveTest(array $rc, string $code): ResponseInterface
    {
        $name = array_search($code, \App\Controllers\DtrController::MATERIAL_TESTS, true);
        $ok   = false;
        foreach ($rc['options'] as $o) {
            $ok = $ok || $o['code'] === $code;
        }
        if (! $ok || $name === false) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'That test is already on the list.', 'csrf' => csrf_hash()]);
        }
        $db   = \Config\Database::connect();
        $base = $rc['rows'][0];
        $per  = [];
        foreach ($rc['rows'] as $r) {
            $per[$r['TestType']] = ($per[$r['TestType']] ?? 0) + 1;
        }
        $n = max(1, (int) max($per));
        if (! preg_match('/^(.*)-(\d+)$/', (string) $base['LabRefNo'], $m)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Could not number the new Lab Ref.', 'csrf' => csrf_hash()]);
        }
        $last = 0;
        foreach ($db->query('SELECT LabRefNo FROM tbl_dtr_MaterialTracker WHERE LabRefNo LIKE ?', [$db->escapeLikeString($m[1]) . '-%'])->getResultArray() as $x) {
            if (preg_match('/-(\d+)$/', (string) $x['LabRefNo'], $mm)) {
                $last = max($last, (int) $mm[1]);
            }
        }
        unset($base['TrackerID']);
        $made = 0;
        for ($i = 1; $i <= $n; $i++) {
            try {
                $made += $db->table('tbl_dtr_MaterialTracker')->insert(array_merge($base, ['LabRefNo' => $m[1] . '-' . ($last + $i), 'TestType' => $name, 'TestCode' => $code, 'Status' => 'Pending', 'Result' => null, 'CreatedAt' => date('Y-m-d H:i:s')])) ? 1 : 0;
            } catch (\Throwable $e) {
            }
        }
        \App\Libraries\DataCache::bump('material');
        \App\Libraries\ActivityLog::record('materials', 'test-add', $base['MotherLabRefNo'], 'test added: ' . $name . ' x' . $made);

        return $this->response->setJSON(['ok' => $made > 0, 'message' => $name . ' was added to ' . $base['MotherLabRefNo'] . ' (' . $made . ' Lab Ref' . ($made === 1 ? '' : 's') . ').', 'csrf' => csrf_hash()]);
    }

    /** GET /materials/test-options?mother=: the tests that can be added back to this Lab Ref */
    public function testOptions(): ResponseInterface
    {
        $ref    = trim((string) $this->request->getGet('mother'));
        $mother = \Config\Database::connect()->table('tbl_dtr_MaterialTracker')->where('LabRefNo', $ref)->get()->getRowArray();
        if (! $mother && ($rc = $this->receiveTests($ref)) !== null) {
            return $this->response->setJSON(['ok' => true, 'options' => $rc['options'], 'can_edit' => self::canEdit()]);
        }
        if (! $mother) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That Lab Reference was not found.']);
        }
        [$opts] = $this->missingTests($mother);

        return $this->response->setJSON(['ok' => true, 'options' => $opts, 'can_edit' => self::canEdit()]);
    }

    /** POST /materials/test-add {mother, code}: a test type of the material is put back on the Lab Ref */
    public function testAdd(): ResponseInterface
    {
        if (! self::canEdit()) {
            return $this->noEdit();
        }
        $p      = $this->request->getJSON(true) ?? [];
        $db     = \Config\Database::connect();
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', trim((string) ($p['mother'] ?? '')))->get()->getRowArray();
        if (! $mother && ($rc = $this->receiveTests(trim((string) ($p['mother'] ?? '')))) !== null) {
            return $this->addReceiveTest($rc, trim((string) ($p['code'] ?? '')));
        }
        if (! $mother) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'That Lab Reference was not found.', 'csrf' => csrf_hash()]);
        }
        [$opts, $res] = $this->missingTests($mother);
        $pick = null;
        foreach ($opts as $o) {
            if ($o['code'] === (string) ($p['code'] ?? '')) {
                $pick = $o;
            }
        }
        if (! $pick || ! $res) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'That test is not one of this material type\'s tests, or it is already on the list.', 'csrf' => csrf_hash()]);
        }
        $d = \App\Libraries\MaterialCatalog::test($pick['code']);
        $d = ['name' => $d['name'] ?? $pick['name'], 'method' => $d['method'] ?? '', 'unit' => $d['unit'] ?? ''];
        $db->query('INSERT IGNORE INTO tbl_dtr_MaterialTrackerTests (TrackerID, MotherLabRefNo, ChildLabRefNo, MaterialCode, MaterialType, TestType, TestCode, Status, CreatedAt, TestMethod, Unit, SortNo)
            VALUES (?, ?, ?, ?, ?, ?, ?, "Pending", NOW(), ?, ?, ?)', [(int) $mother['TrackerID'], $mother['LabRefNo'], $mother['LabRefNo'] . '-' . $pick['code'], (string) ($mother['MaterialCode'] ?? ''), $res['type'], $d['name'], $pick['code'], $d['method'], $d['unit'], $pick['sort']]);
        \App\Libraries\DataCache::bump('material');
        \App\Libraries\ActivityLog::record('materials', 'test-add', $mother['LabRefNo'] . '-' . $pick['code'], 'test added: ' . $d['name']);

        return $this->response->setJSON(['ok' => true, 'message' => $d['name'] . ' was added to ' . $mother['LabRefNo'] . '.', 'csrf' => csrf_hash()]);
    }

    // ------------------------------------------------------------------------------------------------------------------ the Lab Material Test Collection (bench log)
    /** category name of the bench log (AppSheet) -> category of the catalog */
    private const BENCH_CAT = ['fabric' => 'fabric', 'leather' => 'leather', 'synthetic' => 'leather', 'foxing' => 'foxing-heel-logo', 'heel logo' => 'foxing-heel-logo', 'outsole' => 'rubber-outsole', 'rubber' => 'rubber-outsole',
        'sockliner' => 'sockliner-foam', 'foam' => 'sockliner-foam', 'latex foam' => 'sockliner-foam', 'latex' => 'sockliner-foam', 'velcro' => 'velcro', 'nylon webbing' => 'webbing', 'webbing' => 'webbing',
        'elastic gore' => 'elastic-gore', 'thread' => 'thread', 'shoelace' => 'shoelace', 'insole' => 'insole', 'carton' => 'box', 'box' => 'box', 'eyelet' => 'eyelet-grommet', 'grommet' => 'eyelet-grommet', 'label' => 'label'];

    private static function resultOf(string $r): string
    {
        $u = strtoupper(trim($r));

        return str_starts_with($u, 'PASS') ? 'PASS' : (str_starts_with($u, 'FAIL') || $u === 'REJECTED' ? 'FAIL' : (str_starts_with($u, 'TOL') ? 'TOLERABLE' : ''));
    }

    /**
     * The Lab Refs of the bench log (tbl_dtr_material_bench_log) as groups like the received materials: the tests done on each one (machine, technician, result, details) as read-only children.
     * @return list<array<string,mixed>>
     */
    private static function benchGroups(string $q, string $from, string $to): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('tbl_dtr_material_bench_log')) {
            return [];
        }
        $names = array_column(\App\Libraries\MaterialCatalog::categories(), 'name', 'slug');
        $b = $db->table('tbl_dtr_material_bench_log m')->select("m.id, m.lab_ref_no, m.test_code, m.material_category_name, m.material_test_name, m.technician, m.machine_number, m.result, m.possible_cause, m.remarks, m.iqc_received_date,
            LEFT(COALESCE(NULLIF(m.finished_at, ''), NULLIF(m.started_at, ''), NULLIF(m.iqc_received_date, ''), NULLIF(m.created_at, ''), ''), 10) AS test_date, COALESCE(m.finished_at, '') AS finished_at", false)
            ->where("COALESCE(m.lab_ref_no, '') <>", '')->orderBy("COALESCE(DATE(m.finished_at), DATE(m.started_at), m.iqc_received_date, DATE(m.created_at), '1970-01-01')", 'DESC', false)->orderBy('m.id', 'DESC')->limit(2500);
        if ($q !== '') {
            $b->groupStart()->like('m.lab_ref_no', $q)->orLike('m.material_test_name', $q)->orLike('m.material_category_name', $q)->orLike('m.technician', $q)->orLike('m.machine_number', $q)->groupEnd();
        }
        $groups = [];
        foreach ($b->get()->getResultArray() as $r) {
            $ref = (string) $r['lab_ref_no'];
            $groups[$ref]['rows'][] = $r;
        }
        $out = [];
        foreach ($groups as $ref => $g) {
            $rows  = $g['rows'];
            $dates = array_filter(array_column($rows, 'test_date'));
            if (($from !== '' && (! $dates || max($dates) < $from)) || ($to !== '' && (! $dates || min($dates) > $to))) {
                continue;
            }
            $catName = trim((string) $rows[0]['material_category_name']);
            $slug    = self::BENCH_CAT[strtolower($catName)] ?? '';
            $tests   = [];
            $done    = 0;
            $fail    = 0;
            $tol     = 0;
            foreach (array_reverse($rows) as $r) {
                $res = self::resultOf((string) $r['result']);
                $done += $res !== '' ? 1 : 0;
                $fail += $res === 'FAIL' ? 1 : 0;
                $tol  += $res === 'TOLERABLE' ? 1 : 0;
                $tests[] = ['id' => 'b' . $r['id'], 'child' => (string) $r['test_code'], 'code' => (string) $r['test_code'], 'name' => (string) $r['material_test_name'], 'method' => '', 'unit' => '', 'status' => $res !== '' ? 'Completed' : 'Pending',
                    'result' => $res, 'tested' => (string) $r['test_date'], 'tech' => (string) $r['technician'], 'remarks' => trim(implode(' · ', array_filter([(string) $r['machine_number'] !== '' ? 'Machine ' . $r['machine_number'] : '', (string) $r['possible_cause'], (string) $r['remarks']])))];
            }
            $total = count($tests);
            $out[] = ['id' => 'L:' . $ref, 'source' => 'bench', 'lab_ref' => $ref, 'received' => (string) ($rows[0]['iqc_received_date'] ?? ''), 'arrived' => '', 'code' => '', 'name' => $catName . ' · ' . $total . ' test' . ($total === 1 ? '' : 's'),
                'type' => $catName, 'erp_type' => $catName, 'category' => $slug, 'category_name' => $names[$slug] ?? $catName, 'supplier' => '', 'style' => '', 'colorway' => '', 'mtl' => '', 'prod' => '', 'qty' => '', 'qcqty' => '', 'unit' => '',
                'tests' => $tests, 'done' => $done, 'total' => $total, 'fail' => $fail, 'tol' => $tol, 'state' => $done === $total ? 'Completed' : ($done > 0 ? 'Ongoing' : 'Pending')];
        }

        return $out;
    }

    /** the received materials in the shape of the Collection's rows (Lab Tracker > Lab Material Test Collection), so what is received there shows there too */
    public static function collectionRows(string $q = ''): array
    {
        \App\Libraries\MaterialCatalog::ensureTables();
        $db    = \Config\Database::connect();
        $names = array_column(\App\Libraries\MaterialCatalog::categories(), 'name', 'slug');
        $b     = $db->table('tbl_dtr_MaterialTracker')->orderBy('ReceivedDate', 'DESC')->orderBy('TrackerID', 'DESC')->limit(300);
        if ($q !== '') {
            $b->groupStart()->like('LabRefNo', $q)->orLike('MaterialName', $q)->orLike('MaterialCode', $q)->orLike('Supplier', $q)->groupEnd();
        }
        $seen    = [];
        $allRows = [];
        foreach ($b->get()->getResultArray() as $m) {
            if (isset($seen[$m['LabRefNo']])) {
                continue;
            }
            $seen[$m['LabRefNo']] = true;
            $allRows[] = $m;
        }
        $crCodes  = array_unique(array_filter(array_column($allRows, 'MaterialCode'), fn ($c) => $c !== '' && $c !== null));
        $crSpec   = [];
        if ($crCodes) {
            foreach ($db->table('tbl_dtr_material_type_status')->select('MaterialCode, MaterialType')->whereIn('MaterialCode', array_values($crCodes))->get()->getResultArray() as $sm) {
                $crSpec[$sm['MaterialCode']] = (string) $sm['MaterialType'];
            }
        }
        $crRefs = array_column($allRows, 'LabRefNo');
        $crKids = [];
        if ($crRefs) {
            foreach ($db->table('tbl_dtr_MaterialTrackerTests')->whereIn('MotherLabRefNo', $crRefs)->orderBy('SortNo')->orderBy('TestID')->get()->getResultArray() as $k) {
                $crKids[$k['MotherLabRefNo']][] = $k;
            }
        }
        $out = [];
        foreach ($allRows as $m) {
            $spec = $crSpec[$m['MaterialCode']] ?? '';
            $res  = \App\Libraries\MaterialCatalog::resolve($spec !== '' ? $spec : (string) $m['MaterialType']) ?? \App\Libraries\MaterialCatalog::resolve((string) $m['MaterialType']);
            $cat  = $names[$res['category'] ?? ''] ?? (string) $m['MaterialType'];
            $kids = $crKids[$m['LabRefNo']] ?? [];
            $base = ['appsheet_material_id' => '', 'lab_ref_no' => $m['LabRefNo'], 'material_category_name' => $cat, 'machine_number' => '', 'possible_cause' => '', 'detail_count' => 0, 'started_at_fmt' => '', 'finished_at' => ''];
            if (! $kids) {
                $out[] = $base + ['id' => 'm' . $m['TrackerID'], 'test_code' => '', 'material_test_name' => (string) $m['MaterialName'], 'technician' => (string) $m['EncodedBy'], 'result' => 'Pending', 'remarks' => 'Received ' . substr((string) $m['ReceivedDate'], 0, 10), 'test_date' => substr((string) $m['ReceivedDate'], 0, 10), 'details_summary' => 'No test list for this material type'];
                continue;
            }
            foreach ($kids as $k) {
                $out[] = $base + ['id' => 'k' . $k['TestID'], 'test_code' => (string) $k['TestCode'], 'material_test_name' => (string) $k['TestType'], 'technician' => (string) ($k['Technician'] ?: $m['EncodedBy']),
                    'result' => (string) (($k['Result'] ?? '') ?: ($k['Status'] ?: 'Pending')), 'remarks' => (string) ($k['Remarks'] ?? ''), 'test_date' => substr((string) ($k['TestedDate'] ?: $m['ReceivedDate']), 0, 10),
                    'details_summary' => 'Material Testing: ' . $m['MaterialName'], 'detail_count' => 0];
            }
        }

        return $out;
    }

    /** POST /materials/to-report {lab}: the Material Test Report of a received material (created / completed from the material and its test sheet); returns the form and record to open */
    public function toReport(): ResponseInterface
    {
        $p   = $this->request->getJSON(true) ?? [];
        $lab = trim((string) ($p['lab'] ?? ''));
        $db  = \Config\Database::connect();
        $u   = service('auth')->user();
        if ($lab === '' || ! $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', $lab)->countAllResults()) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Not a received material.', 'csrf' => csrf_hash()]);
        }
        if (! \App\Controllers\DtrController::canEditMaterial()) {            // others read the report that exists
            $b   = \App\Libraries\MaterialReport::build($lab);
            $row = $b ? $db->table($b['table'])->select('ID')->where('LabRefNo', $lab)->orderBy('ID', 'DESC')->get(1)->getRowArray() : null;

            return $row ? $this->response->setJSON(['ok' => true, 'slug' => $b['slug'], 'id' => (int) $row['ID'], 'created' => false, 'csrf' => csrf_hash()])
                : $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'No report was made for this material yet. An Admin or a Material PIC makes it from the Material Testing sheet.', 'csrf' => csrf_hash()]);
        }
        $r = \App\Libraries\MaterialReport::push($lab, (string) ($u['name'] ?? $u['username'] ?? ''));

        return $this->response->setStatusCode($r['ok'] ? 200 : 422)->setJSON($r + ['csrf' => csrf_hash()]);
    }
}
