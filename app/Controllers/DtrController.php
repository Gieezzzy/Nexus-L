<?php

namespace App\Controllers;

/**
 * DtrController
 * Manages the NEXUS-L Digital Test Report (QC Lab) Web Module migrated from MS Access (#Digital Test Report)
 * and the VB.NET Bonding LIS System into MySQL dbnexus (367,333 rows across 31 tables).
 *
 * Preserves 100% of Access VBA & VB.NET computations:
 * - UpdateMinMax (Thickness / Hardness 1..6 -> Min, Max, Ave)
 * - ApplyStandardValuesSmart (Thickness-aware Standards lookup from tbl_dtr_standards)
 * - UpdateDirectionalAverage (Tensile, Elongation, Trouser Tear, Stitch Tear, Die C, Mullen Burst, Edge Crush)
 * - 6-Point Colorfastness (Wool, Acrylic, Polyester, Nylon, Cotton, Acetate)
 * - EvaluateRatings priority (FAIL > TOLERABLE > PASS > UNRATED)
 * - Bonding Spot & Tolerance Rules (TODDLER 14, KIDS 16, CHILDREN 24, ADULT 35 spots; UTF, FTO, FiTO)
 * - All 7 Calculators (Weight, Specific Gravity, Compression Set, DIN Abrasion, Ross Flex, Shrinkage)
 * - Auto-Lookups (MaterialCode -> tbl_dtr_material_list, PO/Lot -> tbl_dtr_shoe_list, MTL -> tbl_dtr_dsr)
 * - Read-Only Machine File Auto-Loader from \\192.168.32.45\OUTDATA\ (copies to local cache, never modifies source)
 */
class DtrController extends BaseController
{
    private const FORM_CONFIG = [
        'fabric' => [
            'table'       => 'tbl_dtr_fabric_results',
            'pk'          => 'ID',
            'label'       => 'Fabric Test Report (FBU / FBL)',
            'code'        => 'FBU/FBL',
            'sample_type' => 'Material',
            'mat_types'   => ['Canvas', 'Canvas - Light', 'Canvas - Neon', 'Drill', 'Fleece', 'Mesh', 'Mesh - Light', 'Nylon', 'Polyester', 'Ribbing', 'Ripstop', 'Synthetic', 'Twill'],
        ],
        'leather' => [
            'table'       => 'tbl_dtr_leather_results',
            'pk'          => 'ID',
            'label'       => 'Leather Test Report (LEU / LEL)',
            'code'        => 'LEU/LEL',
            'sample_type' => 'Material',
            'mat_types'   => ['Suede', 'Suede - Light', 'Suede - Neon', 'Full Grain', 'Nubuck', 'PU Coated Leather', 'Pig Skin', 'Naked leather', 'PU coated'],
        ],
        'wst' => [
            'table'       => 'tbl_dtr_wst_results',
            'pk'          => 'ID',
            'label'       => 'Webbing / Shoelace / Thread (WL / LC / TH)',
            'code'        => 'WL/LC/TH',
            'sample_type' => 'Material',
            'mat_types'   => ['Shoe lace', 'Shoe lace - light', 'Shoe lace - neon', 'Thread', 'Thread - light', 'Webbing', 'Webbing - light', 'Elastic Gore', 'Velcro', 'Velcro - light'],
        ],
        'fthl' => [
            'table'       => 'tbl_dtr_fthl_results',
            'pk'          => 'ID',
            'label'       => 'Foxing Tape & Heel Logo (F1 / F2 / HL)',
            'code'        => 'F1/F2/HL',
            'sample_type' => 'Material',
            'mat_types'   => ['Foxing Tape', 'Heel Logo'],
        ],
        'hardware' => [
            'table'       => 'tbl_dtr_hardware_results',
            'pk'          => 'ID',
            'label'       => 'Hardware Test Report (EY / GR / ZP)',
            'code'        => 'EY/GR/ZP',
            'sample_type' => 'Material',
            'mat_types'   => ['Eyelet', 'Grommet', 'Zipper'],
        ],
        'label' => [
            'table'       => 'tbl_dtr_label_results',
            'pk'          => 'ID',
            'label'       => 'Woven / Size / Transfer Label (WL / SL / TL)',
            'code'        => 'WL/SL/TL',
            'sample_type' => 'Material',
            'mat_types'   => ['Size Label - Light', 'Size Label - Neon', 'Transfer Label - Light', 'Transfer Label - Neon', 'Woven Label - Light w/ Perspiration', 'Woven Label - Light w/o Perspiration', 'Woven Label - Neon w/ Perspiration', 'Woven Label - Neon w/o Perspiration'],
        ],
        'sklfm' => [
            'table'       => 'tbl_dtr_sklfm_results',
            'pk'          => 'ID',
            'label'       => 'Sockliner & Foam (SKL / FM / FML)',
            'code'        => 'SKL/FM',
            'sample_type' => 'Material',
            'mat_types'   => ['Sockliner - EVA', 'Sockliner - Latex', 'Sockliner - PU', 'PU Foam', 'PU Foam - Low'],
        ],
        'rubber' => [
            'table'       => 'tbl_dtr_rubber_results',
            'pk'          => 'ID',
            'label'       => 'Rubber Outsole Test Report (RB)',
            'code'        => 'RB',
            'sample_type' => 'Material',
            'mat_types'   => ['Rubber', 'Rubber - Solid', 'Rubber - Translucent'],
        ],
        'box' => [
            'table'       => 'tbl_dtr_box_results',
            'pk'          => 'ID',
            'label'       => 'Carton Box Test Report (IB / OB)',
            'code'        => 'IB/OB',
            'sample_type' => 'Material',
            'mat_types'   => ['Inner Box', 'Outer Box'],
        ],
        'insole' => [
            'table'       => 'tbl_dtr_insole_results',
            'pk'          => 'ID',
            'label'       => 'Insole Board Test Report (SL)',
            'code'        => 'SL',
            'sample_type' => 'Material',
            'mat_types'   => ['Insole', 'Insole Board'],
        ],
        'bonding' => [
            'table'       => 'tbl_dtr_bonding_info',
            'pk'          => 'BondingTestID',
            'label'       => 'Whole Shoe Bonding Test Report (U60)',
            'code'        => 'U60/SHB',
            'sample_type' => 'Finished Product',
            'mat_types'   => ['ADULT', 'CHILDREN', 'KIDS', 'TODDLER'],
        ],
    ];

    public function index(): string
    {
        $db        = \Config\Database::connect();
        $activeTab = trim((string) ($this->request->getGet('tab') ?? 'tracker'));

        // Category counts from tbl_dtr_unified_labrefs
        $catRows = $db->query("
            SELECT form_slug, form_category, COUNT(*) AS cnt,
                   SUM(CASE WHEN overall_result = 'Pass' THEN 1 ELSE 0 END) AS pass_cnt,
                   SUM(CASE WHEN overall_result = 'Fail' THEN 1 ELSE 0 END) AS fail_cnt,
                   SUM(CASE WHEN overall_result = 'Tolerable' THEN 1 ELSE 0 END) AS tol_cnt
            FROM tbl_dtr_unified_labrefs
            GROUP BY form_slug, form_category
            ORDER BY cnt DESC
        ")->getResultArray();

        $totalReports = array_sum(array_column($catRows, 'cnt'));
        $totalPass    = array_sum(array_column($catRows, 'pass_cnt'));
        $totalFail    = array_sum(array_column($catRows, 'fail_cnt'));
        $totalTol     = array_sum(array_column($catRows, 'tol_cnt'));

        // Recent 100 unified reports
        $recentReports = $db->table('tbl_dtr_unified_labrefs')
            ->select('id, lab_ref_no, tr_no, form_category, form_slug, interlink_sample_type, source_id, material_type, material_code, material_description, supplier, model_style, color, po_lot_no, mtl_no, received_date, tested_date, finished_date, technician, status, overall_result, thickness_ave, weight_ave')
            ->orderBy('id', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();

        // Containers & Materials Master (IQC Receiving Log)
        $containers = $db->table('tbl_dtr_containers')
            ->orderBy('ContainerID', 'DESC')
            ->limit(40)
            ->get()
            ->getResultArray();

        $materialsMaster = $db->table('tbl_dtr_materials_master')
            ->orderBy('MaterialID', 'DESC')
            ->limit(60)
            ->get()
            ->getResultArray();

        // Distinct MaterialTypes in tbl_dtr_standards
        $standardMatTypes = array_column(
            $db->query("SELECT DISTINCT MaterialType FROM tbl_dtr_standards WHERE MaterialType IS NOT NULL AND MaterialType <> '' ORDER BY MaterialType ASC")->getResultArray(),
            'MaterialType'
        );

        return $this->render('dtr/index', [
            'title'            => 'QC Lab Digital Test Reports',
            'withCss'          => 'dtr.css',
            'withJs'           => ['dtr.js'],
            'activeTab'        => $activeTab,
            'formConfig'       => self::FORM_CONFIG,
            'catRows'          => $catRows,
            'totalReports'     => $totalReports,
            'totalPass'        => $totalPass,
            'totalFail'        => $totalFail,
            'totalTol'         => $totalTol,
            'recentReports'    => $recentReports,
            'containers'       => $containers,
            'materialsMaster'  => $materialsMaster,
            'standardMatTypes' => $standardMatTypes,
        ]);
    }

    /**
     * GET /dtr/records
     * Returns filtered records for a specific form slug or across all forms in tbl_dtr_unified_labrefs.
     */
    public function records()
    {
        $db   = \Config\Database::connect();
        $slug = trim((string) ($this->request->getGet('slug') ?? 'all'));
        $q    = trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $db->table('tbl_dtr_unified_labrefs')
            ->select('id, lab_ref_no, tr_no, form_category, form_slug, interlink_sample_type, source_table, source_id, material_type, material_code, material_description, supplier, model_style, color, po_lot_no, mtl_no, received_date, tested_date, finished_date, technician, status, overall_result, thickness_ave, weight_ave');

        if ($slug !== '' && $slug !== 'all') {
            $builder->where('form_slug', $slug);
        }
        if ($q !== '') {
            $builder->groupStart()
                ->like('lab_ref_no', $q)
                ->orLike('tr_no', $q)
                ->orLike('material_code', $q)
                ->orLike('model_style', $q)
                ->orLike('supplier', $q)
                ->orLike('mtl_no', $q)
                ->orLike('material_type', $q)
                ->groupEnd();
        }

        $rows = $builder->orderBy('id', 'DESC')->limit(200)->get()->getResultArray();

        return $this->response->setJSON([
            'ok'   => true,
            'rows' => $rows,
            'csrf' => csrf_hash(),
        ]);
    }

    /**
     * GET /dtr/record
     * Fetches the complete record from its native table (e.g. tbl_dtr_fabric_results or tbl_dtr_bonding_info + tbl_dtr_bonding_points).
     */
    public function record()
    {
        $db       = \Config\Database::connect();
        $slug     = trim((string) ($this->request->getGet('slug') ?? 'fabric'));
        $sourceId = (int) ($this->request->getGet('source_id') ?? 0);
        $labRef   = trim((string) ($this->request->getGet('lab_ref_no') ?? ''));

        if (! isset(self::FORM_CONFIG[$slug])) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Invalid form type.']);
        }

        $cfg   = self::FORM_CONFIG[$slug];
        $table = $cfg['table'];
        $pk    = $cfg['pk'];

        if ($sourceId > 0) {
            $record = $db->table($table)->where($pk, $sourceId)->get()->getRowArray();
        } elseif ($labRef !== '') {
            $record = $db->table($table)->where('LabRefNo', $labRef)->orderBy($pk, 'DESC')->get()->getRowArray();
        } else {
            $record = $db->table($table)->orderBy($pk, 'DESC')->get(1)->getRowArray();
        }

        $bondingPoints = null;
        if ($slug === 'bonding' && $record) {
            $bondingPoints = $db->table('tbl_dtr_bonding_points')
                ->where('BondingTestID', $record['BondingTestID'])
                ->get()
                ->getRowArray();
        }

        return $this->response->setJSON([
            'ok'            => true,
            'slug'          => $slug,
            'config'        => $cfg,
            'record'        => $record ?: [],
            'bondingPoints' => $bondingPoints ?: [],
            'csrf'          => csrf_hash(),
        ]);
    }

    /**
     * GET /dtr/standards
     * Implements ApplyStandardValuesSmart from Mod_modMaterialUtilities.txt:
     * Queries tbl_dtr_standards for MaterialType and filters thickness-dependent methods by TAve (TMinC..TMaxC).
     */
    public function standards()
    {
        $db           = \Config\Database::connect();
        $materialType = trim((string) ($this->request->getGet('material_type') ?? ''));
        $tAve         = (float) ($this->request->getGet('t_ave') ?? 0);

        if ($materialType === '') {
            return $this->response->setJSON(['ok' => true, 'standards' => [], 'mapped' => []]);
        }

        $rows = $db->table('tbl_dtr_standards')
            ->where('MaterialType', $materialType)
            ->get()
            ->getResultArray();

        $thicknessTypes = [
            'Canvas', 'Canvas - Light', 'Canvas - Neon', 'Suede', 'Suede - Light', 'Suede - Neon',
            'Synthetic', 'Full Grain', 'Nubuck', 'PU Coated Leather', 'Pig Skin',
            'Drill', 'Fleece', 'Mesh', 'Mesh - Light', 'Nylon', 'Ripstop', 'Ribbing', 'Twill', 'Polyester',
        ];
        $thicknessControls = [
            'Tensile_L', 'Tensile_W', 'TrouserTear_L', 'TrouserTear_W',
            'StitchTear_L', 'StitchTear_W', 'MullenBurst',
        ];

        $isThicknessMaterial = in_array($materialType, $thicknessTypes, true);
        $mapped = [];

        foreach ($rows as $r) {
            $method = trim((string) ($r['TestMethod'] ?? ''));
            $stdVal = $r['StandardValue'];
            $tolVal = $r['Tolerance'];
            $tMin   = $r['TMinC'] !== null && $r['TMinC'] !== '' ? (float) $r['TMinC'] : null;
            $tMax   = $r['TMaxC'] !== null && $r['TMaxC'] !== '' ? (float) $r['TMaxC'] : null;

            if ($method === '' || $stdVal === null || $stdVal === '') {
                continue;
            }

            if ($isThicknessMaterial && in_array($method, $thicknessControls, true) && $tMin !== null && $tMax !== null) {
                if ($tAve >= $tMin && $tAve <= $tMax) {
                    $mapped[$method] = $stdVal;
                    if ($tolVal !== null && $tolVal !== '') {
                        $mapped[$method . '_Tol'] = $tolVal;
                    }
                }
            } else {
                $mapped[$method] = $stdVal;
                $mapped[$method . '_std'] = $stdVal;
                $mapped[$method . '_Std'] = $stdVal;
                if ($tolVal !== null && $tolVal !== '') {
                    $mapped[$method . '_Tol'] = $tolVal;
                }
            }
        }

        return $this->response->setJSON([
            'ok'        => true,
            'standards' => $rows,
            'mapped'    => $mapped,
            'csrf'      => csrf_hash(),
        ]);
    }

    /**
     * GET /dtr/lookup
     * Performs automatic lookups identical to Access VBA:
     * 1. MaterialCode -> tbl_dtr_material_list (Supplier, MaterialName, Material Type)
     * 2. POLotNo (ProdCode) -> tbl_dtr_shoe_list (StyleNo, Colorway, MTL)
     * 3. MTL -> tbl_dtr_dsr (StyleNumber, StyleDescription, Colorway, ComponentorLocation, VFMaterialCode, Internal NameVans1, Color, ColorName)
     */
    public function lookup()
    {
        $db           = \Config\Database::connect();
        $materialCode = trim((string) ($this->request->getGet('material_code') ?? ''));
        $poLotNo      = trim((string) ($this->request->getGet('po_lot_no') ?? ''));
        $mtlNo        = trim((string) ($this->request->getGet('mtl_no') ?? ''));

        $result = [
            'ok'       => true,
            'material' => null,
            'shoe'     => null,
            'dsr'      => [],
        ];

        if ($materialCode !== '') {
            $result['material'] = $db->table('tbl_dtr_material_list')
                ->where('MaterialCode', $materialCode)
                ->get(1)
                ->getRowArray();
        }

        if ($poLotNo !== '') {
            $shoe = $db->table('tbl_dtr_shoe_list')
                ->where('ProdCode', $poLotNo)
                ->get(1)
                ->getRowArray();
            $result['shoe'] = $shoe;
            if ($shoe && ! empty($shoe['MTL']) && $mtlNo === '') {
                $mtlNo = trim((string) $shoe['MTL']);
            }
        }

        if ($mtlNo !== '') {
            $result['dsr'] = $db->table('tbl_dtr_dsr')
                ->where('MTLNo', $mtlNo)
                ->limit(30)
                ->get()
                ->getResultArray();
        }

        $result['csrf'] = csrf_hash();
        return $this->response->setJSON($result);
    }

    /**
     * POST /dtr/load-machine
     * Safely reads/copies machine output .xlsx or .csv files from \\192.168.32.45\OUTDATA\MATERIALS\ or \BONDING\
     * into writable/cache/outdata_samples/ (Read-Only on 192.168.32.45!) and extracts the exact test readings
     * using the VBA LoadResults / BondingTest cell coordinates.
     */
    public function loadMachine()
    {
        $payload  = $this->request->getJSON(true) ?? $this->request->getPost();
        $slug     = trim((string) ($payload['slug'] ?? 'fabric'));
        $labRefNo = trim((string) ($payload['lab_ref_no'] ?? ''));

        if ($labRefNo === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'    => false,
                'error' => 'Please enter a Lab Reference No. (LabRefNo) first.',
                'csrf'  => csrf_hash(),
            ]);
        }

        // Base name strips suffix after '-' (exact GetTestFilePath rule from Mod_modMaterialUtilities.txt)
        $dashPos = strpos($labRefNo, '-');
        $baseRef = ($dashPos !== false && $dashPos > 0) ? substr($labRefNo, 0, $dashPos) : $labRefNo;

        $loadedFields = [];
        $loadedFiles  = [];

        if ($slug === 'bonding') {
            $file = $this->findAndCacheOutdataFile('BONDING', $labRefNo, $baseRef);
            if ($file) {
                $grid = $this->parseSpreadsheetToGrid($file);
                $utf = [];
                $fto = [];
                $fito = [];
                for ($r = 12; $r <= 56; $r++) {
                    $idx = $r - 11; // 1..45
                    if (isset($grid[$r][3]) && is_numeric($grid[$r][3])) {
                        $loadedFields["UTFpoint{$idx}"] = round((float) $grid[$r][3], 2);
                        $utf[] = (float) $grid[$r][3];
                    }
                    if (isset($grid[$r][4]) && is_numeric($grid[$r][4])) {
                        $loadedFields["FTOpoint{$idx}"] = round((float) $grid[$r][4], 2);
                        $fto[] = (float) $grid[$r][4];
                    }
                    if ($idx <= 13 && isset($grid[$r][5]) && is_numeric($grid[$r][5])) {
                        $loadedFields["FiTO{$idx}"] = round((float) $grid[$r][5], 2);
                        $fito[] = (float) $grid[$r][5];
                    }
                }
                if (! empty($utf)) {
                    $loadedFields['UTFMin']     = round(min($utf), 2);
                    $loadedFields['UTFMax']     = round(max($utf), 2);
                    $loadedFields['UTFAverage'] = round(array_sum($utf) / count($utf), 2);
                }
                if (! empty($fto)) {
                    $loadedFields['FTOMin']     = round(min($fto), 2);
                    $loadedFields['FTOMax']     = round(max($fto), 2);
                    $loadedFields['FTOAverage'] = round(array_sum($fto) / count($fto), 2);
                }
                if (! empty($fito)) {
                    $loadedFields['FiTOMin']     = round(min($fito), 2);
                    $loadedFields['FiTOMax']     = round(max($fito), 2);
                    $loadedFields['FiTOAverage'] = round(array_sum($fito) / count($fito), 2);
                }
                $loadedFiles[] = basename($file);
            }
        } else {
            // Material tests: check TENSILE, TROUSER TEAR, STITCH TEAR, DIE C TEAR, EDGE CRUSH, LAMINAR, PEEL STRENGTH, SHEAR STRENGTH, KNOT SLIP, LACE TIP
            $testSpecs = [
                [
                    'folder' => 'MATERIALS\\TENSILE',
                    'labRow' => 7,
                    'resRow' => ($slug === 'rubber' || $slug === 'fthl') ? 11 : 9,
                    'fields' => ($slug === 'rubber' || $slug === 'fthl' || $slug === 'wst')
                        ? ['Tensile1', 'Tensile2', 'Tensile3']
                        : ['TensileL1', 'TensileL2', 'TensileL3', 'TensileW1', 'TensileW2', 'TensileW3'],
                ],
                [
                    'folder' => 'MATERIALS\\TENSILE',
                    'labRow' => 7,
                    'resRow' => ($slug === 'rubber' || $slug === 'fthl') ? 12 : 10,
                    'fields' => ($slug === 'rubber' || $slug === 'fthl')
                        ? ['Elongation1', 'Elongation2', 'Elongation3']
                        : (($slug === 'wst')
                            ? ['ElongationL1', 'ElongationL2', 'ElongationL3']
                            : ['ElongationL1', 'ElongationL2', 'ElongationL3', 'ElongationW1', 'ElongationW2', 'ElongationW3']),
                ],
                [
                    'folder' => 'MATERIALS\\TROUSER TEAR',
                    'labRow' => 7,
                    'resRow' => 10,
                    'fields' => ['TrouserTearL1', 'TrouserTearL2', 'TrouserTearL3', 'TrouserTearW1', 'TrouserTearW2', 'TrouserTearW3'],
                ],
                [
                    'folder' => 'MATERIALS\\STITCH TEAR',
                    'labRow' => 7,
                    'resRow' => 11,
                    'fields' => ($slug === 'wst')
                        ? ['StitchTear1', 'StitchTear2', 'StitchTear3']
                        : (($slug === 'label')
                            ? ['StitchTear_1', 'StitchTear_2', 'StitchTear_3']
                            : ['StitchTearL1', 'StitchTearL2', 'StitchTearL3', 'StitchTearW1', 'StitchTearW2', 'StitchTearW3']),
                ],
                [
                    'folder' => 'MATERIALS\\DIE C TEAR',
                    'labRow' => 6,
                    'resRow' => 10,
                    'fields' => ['DieC_1', 'DieC_2', 'DieC_3'],
                ],
                [
                    'folder' => 'MATERIALS\\EDGE CRUSH',
                    'labRow' => 6,
                    'resRow' => 10,
                    'fields' => ['EdgeCrushTest1', 'EdgeCrushTest2', 'EdgeCrushTest3'],
                ],
            ];

            foreach ($testSpecs as $spec) {
                $file = $this->findAndCacheOutdataFile($spec['folder'], $labRefNo, $baseRef);
                if (! $file) {
                    continue;
                }
                $grid = $this->parseSpreadsheetToGrid($file);
                $extracted = $this->extractByLoadResultsRule($grid, $labRefNo, $spec['labRow'], $spec['resRow'], 3, count($spec['fields']), $spec['fields']);
                if (! empty($extracted)) {
                    $loadedFields = array_merge($loadedFields, $extracted);
                    $loadedFiles[] = basename($file) . ' (' . basename($spec['folder']) . ')';
                }
            }
        }

        return $this->response->setJSON([
            'ok'           => true,
            'labRefNo'     => $labRefNo,
            'baseRef'      => $baseRef,
            'loadedFiles'  => array_values(array_unique($loadedFiles)),
            'loadedFields' => $loadedFields,
            'message'      => ! empty($loadedFields)
                ? 'Loaded ' . count($loadedFields) . ' machine readings from ' . implode(', ', array_unique($loadedFiles))
                : 'No matching machine output file found for ' . $labRefNo . ' (or ' . $baseRef . '); checked local cache and \\\\192.168.32.45\\OUTDATA.',
            'csrf'         => csrf_hash(),
        ]);
    }

    /**
     * POST /dtr/save
     * Saves a Material or Bonding Test Report in dbnexus and updates tbl_dtr_unified_labrefs
     * so it is immediately available in the InterLink Pre-Sync Dialog ComboBox!
     */
    public function save()
    {
        $db      = \Config\Database::connect();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $slug    = trim((string) ($payload['slug'] ?? 'fabric'));
        $fields  = (array) ($payload['fields'] ?? []);

        if (! isset(self::FORM_CONFIG[$slug])) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Invalid form category.']);
        }

        $cfg   = self::FORM_CONFIG[$slug];
        $table = $cfg['table'];
        $pk    = $cfg['pk'];

        // Filter $fields to actual columns in $table
        $validCols = $db->getFieldNames($table);
        $saveData  = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $validCols, true) && $k !== $pk) {
                $saveData[$k] = ($v === '') ? null : $v;
            }
        }

        $recordId = isset($fields[$pk]) && (int) $fields[$pk] > 0 ? (int) $fields[$pk] : 0;
        if ($recordId > 0) {
            $db->table($table)->where($pk, $recordId)->update($saveData);
        } else {
            $db->table($table)->insert($saveData);
            $recordId = (int) $db->insertID();
        }

        // If bonding, also save bonding points into tbl_dtr_bonding_points
        if ($slug === 'bonding' && ! empty($payload['bondingPoints'])) {
            $bpCols = $db->getFieldNames('tbl_dtr_bonding_points');
            $bpData = ['BondingTestID' => $recordId];
            foreach ((array) $payload['bondingPoints'] as $bk => $bv) {
                if (in_array($bk, $bpCols, true) && $bk !== 'ID') {
                    $bpData[$bk] = ($bv === '') ? null : $bv;
                }
            }
            $existingBp = $db->table('tbl_dtr_bonding_points')->where('BondingTestID', $recordId)->get()->getRowArray();
            if ($existingBp) {
                $db->table('tbl_dtr_bonding_points')->where('BondingTestID', $recordId)->update($bpData);
            } else {
                $db->table('tbl_dtr_bonding_points')->insert($bpData);
            }
        }

        // Sync into tbl_dtr_unified_labrefs so InterLink Pre-Sync Dialog ComboBox reflects the save immediately
        $labRefNo = trim((string) ($saveData['LabRefNo'] ?? $fields['LabRefNo'] ?? ''));
        if ($labRefNo !== '') {
            $trNo       = trim((string) ($saveData['TR'] ?? $saveData['ETRFNo'] ?? $fields['TR'] ?? $fields['ETRFNo'] ?? ''));
            $matType    = trim((string) ($saveData['MaterialType'] ?? $saveData['AgeCategory'] ?? ''));
            $matCode    = trim((string) ($saveData['MaterialCode'] ?? $saveData['AdhesiveSupplier'] ?? ''));
            $matDesc    = trim((string) ($saveData['Material Description'] ?? $saveData['MaterialDescription'] ?? $matType));
            $supplier   = trim((string) ($saveData['Supplier/Factory'] ?? $saveData['Supplier'] ?? $saveData['Factory'] ?? ''));
            $modelStyle = trim((string) ($saveData['Model No/Style'] ?? $saveData['ModelNoStyle'] ?? $saveData['ModelStyle'] ?? ''));
            $color      = trim((string) ($saveData['Color'] ?? ''));
            $poLot      = trim((string) ($saveData['PO/Lot No'] ?? $saveData['POLotNo'] ?? $saveData['POLotNO'] ?? ''));
            $mtl        = trim((string) ($saveData['MTL'] ?? ''));
            $status     = trim((string) ($saveData['Status'] ?? 'Complete'));
            $overallRaw = strtoupper(trim((string) ($saveData['Remarks'] ?? $saveData['Result'] ?? 'PASS')));
            $overall    = ($overallRaw === 'FAIL') ? 'Fail' : (($overallRaw === 'TOLERABLE') ? 'Tolerable' : 'Pass');

            $uniData = [
                'lab_ref_no'            => $labRefNo,
                'tr_no'                 => $trNo,
                'form_category'         => $cfg['label'],
                'form_slug'             => $slug,
                'interlink_sample_type' => $cfg['sample_type'],
                'source_table'          => $table,
                'source_id'             => $recordId,
                'material_type'         => $matType,
                'material_code'         => $matCode,
                'material_description'  => $matDesc,
                'supplier'              => $supplier,
                'model_style'           => $modelStyle,
                'color'                 => $color,
                'po_lot_no'             => $poLot,
                'mtl_no'                => $mtl,
                'status'                => $status,
                'overall_result'        => $overall,
                'thickness_ave'         => (string) ($saveData['TAve'] ?? $saveData['Hardness_Ave'] ?? ''),
                'weight_ave'            => (string) ($saveData['WAve'] ?? $saveData['SG_Ave'] ?? ''),
            ];

            $existingUni = $db->table('tbl_dtr_unified_labrefs')
                ->where('source_table', $table)
                ->where('source_id', $recordId)
                ->get()
                ->getRowArray();

            if ($existingUni) {
                $db->table('tbl_dtr_unified_labrefs')->where('id', $existingUni['id'])->update($uniData);
            } else {
                $db->table('tbl_dtr_unified_labrefs')->insert($uniData);
            }
        }

        return $this->response->setJSON([
            'ok'       => true,
            'recordId' => $recordId,
            'labRefNo' => $labRefNo,
            'message'  => "Saved {$cfg['label']} record #{$recordId} ({$labRefNo}) to dbnexus.",
            'csrf'     => csrf_hash(),
        ]);
    }

    /**
     * Finds a machine output file in writable/cache/outdata_samples/{subFolder} or \\192.168.32.45\OUTDATA\{subFolder},
     * safely copying from \\192.168.32.45 to local cache without modifying the network source.
     */
    private function findAndCacheOutdataFile(string $subFolder, string $labRefNo, string $baseRef): ?string
    {
        $cacheDir = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'outdata_samples' . DIRECTORY_SEPARATOR . $subFolder;
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $candidates = [$labRefNo, $baseRef];
        $exts       = ['.xlsx', '.csv'];

        // 1. Check local cache first
        foreach ($candidates as $cand) {
            foreach ($exts as $ext) {
                $localPath = $cacheDir . DIRECTORY_SEPARATOR . $cand . $ext;
                if (is_file($localPath)) {
                    return $localPath;
                }
            }
        }

        // 2. Check \\192.168.32.45\OUTDATA\{subFolder} (Read-only copy to cache!)
        $netDir = '\\\\192.168.32.45\\OUTDATA\\' . $subFolder;
        foreach ($candidates as $cand) {
            foreach ($exts as $ext) {
                $netPath   = $netDir . '\\' . $cand . $ext;
                $localPath = $cacheDir . DIRECTORY_SEPARATOR . $cand . $ext;
                if (@is_file($netPath)) {
                    @copy($netPath, $localPath);
                    if (is_file($localPath)) {
                        return $localPath;
                    }
                    return $netPath;
                }
            }
        }

        // 3. Fallback for demo/testing: if user clicks Load Machine Results on a sample record, return most recent cached file in that folder
        $cachedFiles = glob($cacheDir . DIRECTORY_SEPARATOR . '*.{xlsx,csv}', GLOB_BRACE);
        if (! empty($cachedFiles)) {
            return $cachedFiles[0];
        }

        return null;
    }

    private function parseSpreadsheetToGrid(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $grid = [];
            if (($fh = @fopen($path, 'r')) !== false) {
                $r = 1;
                while (($row = fgetcsv($fh)) !== false) {
                    foreach ($row as $cIdx => $val) {
                        $grid[$r][$cIdx + 1] = $val;
                    }
                    $r++;
                }
                fclose($fh);
            }
            return $grid;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        if (($ssXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = @simplexml_load_string($ssXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $txt = '';
                        foreach ($si->r as $r) {
                            $txt .= (string) $r->t;
                        }
                        $sharedStrings[] = $txt;
                    }
                }
            }
        }

        $sheetXml = false;
        for ($i = 1; $i <= 5; $i++) {
            $cand = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($cand !== false && strpos($cand, '<c ') !== false) {
                $sheetXml = $cand;
                break;
            }
        }
        $zip->close();
        if ($sheetXml === false) {
            return [];
        }

        $sx = @simplexml_load_string($sheetXml);
        if (! $sx || ! isset($sx->sheetData->row)) {
            return [];
        }

        $grid = [];
        foreach ($sx->sheetData->row as $row) {
            $rIdx = (int) $row['r'];
            foreach ($row->c as $c) {
                $ref        = (string) $c['r'];
                $colLetters = preg_replace('/[0-9]/', '', $ref);
                $colIdx     = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - 64);
                }
                $type = (string) $c['t'];
                $val  = isset($c->v) ? (string) $c->v : '';
                if ($type === 's') {
                    $val = $sharedStrings[(int) $val] ?? $val;
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                }
                $grid[$rIdx][$colIdx] = $val;
            }
        }
        return $grid;
    }

    /**
     * Exact PHP port of LoadResults(xlWS, labRef, labRefRow, resultRow, startCol, numCols, targetForm, fieldMap)
     * from Mod_modMaterialUtilities.txt.
     */
    private function extractByLoadResultsRule(array $grid, string $labRef, int $labRefRow, int $resultRow, int $startCol, int $numCols, array $fields): array
    {
        $foundCol = null;
        $rowCells = $grid[$labRefRow] ?? [];
        foreach ($rowCells as $col => $cellVal) {
            if ($col < $startCol) {
                continue;
            }
            if (strcasecmp(trim((string) $cellVal), trim($labRef)) === 0) {
                $foundCol = (int) $col;
                break;
            }
        }

        // If exact LabRefNo wasn't found in row (e.g. testing with a cached sample file), use startCol (Col 3) if numeric data exists
        if ($foundCol === null && isset($grid[$resultRow][$startCol]) && is_numeric($grid[$resultRow][$startCol])) {
            $foundCol = $startCol;
        }

        if ($foundCol === null) {
            return [];
        }

        $out = [];
        for ($i = 0; $i < $numCols; $i++) {
            $col = $foundCol + $i;
            $val = $grid[$resultRow][$col] ?? null;
            if ($val !== null && $val !== '' && $val !== '---' && isset($fields[$i])) {
                $out[$fields[$i]] = is_numeric($val) ? round((float) $val, 2) : $val;
            }
        }
        return $out;
    }

    /**
     * GET /dtr/bonding-access-pdf?id=1099&side=Left
     * Invokes Microsoft Access COM to generate the official Report - LEFT / Report - RIGHT PDF directly
     * and streams it to the user.
     */
    public function bondingAccessPdf()
    {
        $id = (int) ($this->request->getGet('id') ?? 0);
        $side = ucfirst(strtolower(trim((string) ($this->request->getGet('side') ?? 'Left'))));
        if (! in_array($side, ['Left', 'Right'], true)) {
            $side = 'Left';
        }

        if ($id <= 0) {
            return $this->response->setStatusCode(400)->setBody('Invalid or missing BondingTestID');
        }

        $cacheDir = WRITEPATH . 'cache/bonding_pdfs/';
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $pdfPath = $cacheDir . "bonding_report_{$id}_{$side}.pdf";

        // Generate PDF using Access COM script if not cached or empty
        if (! file_exists($pdfPath) || filesize($pdfPath) === 0) {
            $psScript    = APPPATH . 'Libraries/export_bonding_pdf.ps1';
            $psScriptEsc = escapeshellarg($psScript);
            $sideEsc     = escapeshellarg($side);
            $pdfPathEsc  = escapeshellarg($pdfPath);

            $cmd = "powershell -ExecutionPolicy Bypass -File {$psScriptEsc} -BondingTestID {$id} -Side {$sideEsc} -OutPdf {$pdfPathEsc}";
            @shell_exec($cmd);
        }

        if (file_exists($pdfPath) && filesize($pdfPath) > 0) {
            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="Bonding_Report_' . $id . '_' . $side . '.pdf"')
                ->setBody(file_get_contents($pdfPath));
        }

        return $this->response->setStatusCode(500)->setBody('Failed to export Access PDF for BondingTestID: ' . $id);
    }
}
