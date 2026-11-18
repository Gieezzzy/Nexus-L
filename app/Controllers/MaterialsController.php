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
}
