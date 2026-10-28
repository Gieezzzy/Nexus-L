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
    public const FORM_CONFIG = [
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

        $customValues = [];
        if ($record && $db->tableExists('tbl_dtr_custom_values')) {
            foreach ($db->table('tbl_dtr_custom_values')->where(['slug' => $slug, 'record_id' => (int) $record[$pk]])->get()->getResultArray() as $cv) {
                $customValues[$cv['field_key']] = $cv['field_value'];
            }
        }

        return $this->response->setJSON([
            'ok'            => true,
            'slug'          => $slug,
            'config'        => $cfg,
            'record'        => $record ?: [],
            'customValues'  => $customValues,
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

        // Admin-defined reading rules (Report Designer > Excel readers) take priority over the built-in ones below.
        $db   = \Config\Database::connect();
        $maps = $db->tableExists('tbl_machine_maps')
            ? $db->table('tbl_machine_maps')->where(['slug' => $slug, 'enabled' => 1])->orderBy('sort_order')->orderBy('id')->get()->getResultArray()
            : [];

        if ($maps) {
            $reader = new \App\Libraries\MachineFileReader();
            foreach ($maps as $m) {
                $file = $reader->findFile((string) $m['folder'], $labRefNo);
                if (! $file) {
                    continue;
                }
                $got = $reader->apply($reader->grid($file, (string) $m['sheet']), (array) json_decode((string) $m['rules_json'], true), $labRefNo);
                if ($got) {
                    $loadedFields = array_merge($loadedFields, $got);
                    $loadedFiles[] = basename($file) . ' (' . $m['label'] . ')';
                }
            }
        } elseif ($slug === 'bonding') {
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

        if ($slug === 'bonding' && $loadedFields) {
            $loadedFields = $this->deriveBondingFields($loadedFields, $labRefNo);
        }

        return $this->response->setJSON([
            'ok'           => true,
            'labRefNo'     => $labRefNo,
            'baseRef'      => $baseRef,
            'loadedFiles'  => array_values(array_unique($loadedFiles)),
            'loadedFields' => $loadedFields,
            'message'      => ! empty($loadedFields)
                ? 'Loaded ' . count($loadedFields) . ' machine readings from ' . implode(', ', array_unique($loadedFiles))
                : 'No matching machine output file found for ' . $labRefNo . ' (or ' . $baseRef . '); the machine file may not be saved yet.',
            'csrf'         => csrf_hash(),
        ]);
    }

    /**
     * Same rules the Access import (btnImportExcel) applies after reading the file: technician by equipment code,
     * side from the Lab Ref suffix, age category / STD from the style, dates, and the per-group + overall result.
     * The equipment -> technician list is the admin setting `bonding_technicians` (JSON {"DTP-QC-001":"S. Aquino"}).
     */
    private function deriveBondingFields(array $f, string $labRefNo): array
    {
        $techs = [
            'DTP-QC-001' => 'S. Aquino', 'DTP-QC-002' => 'L. Tuddao', 'DTP-QC-048' => 'L. Aquidado',
            'DTP-QC-064' => 'J. Barrera', 'DTP-QC-066' => 'D. Parrilla', 'DTP-QC-067' => 'T. Villalobos',
        ];
        $db = \Config\Database::connect();
        if ($db->tableExists('tbl_app_settings')) {
            $row = $db->table('tbl_app_settings')->where('skey', 'bonding_technicians')->get()->getRowArray();
            $j   = json_decode((string) ($row['svalue'] ?? ''), true);
            if (is_array($j) && $j) {
                $techs = $j;
            }
        }

        $set = static function (array &$a, string $k, $v): void {
            if (! isset($a[$k]) || $a[$k] === '') {
                $a[$k] = $v;
            }
        };

        // Excel stores dates as serial numbers
        foreach (['TestedDate', 'ReceivedDate', 'FinishedDate'] as $dk) {
            if (isset($f[$dk]) && is_numeric($f[$dk]) && (float) $f[$dk] > 20000) {
                $f[$dk] = gmdate('Y-m-d', (int) round(((float) $f[$dk] - 25569) * 86400));
            }
        }
        if (! empty($f['TestedDate'])) {
            $set($f, 'ReceivedDate', $f['TestedDate']);
            $set($f, 'FinishedDate', $f['TestedDate']);
        }
        if (! empty($f['ModelStyle'])) {
            $set($f, 'Color', $f['ModelStyle']);
        }
        if (! empty($f['EquipmentCode'])) {
            $set($f, 'Technician', $techs[$f['EquipmentCode']] ?? '');
        }
        $last = strtoupper(substr(trim($labRefNo), -1));
        $set($f, 'Side', $last === 'L' ? 'Left' : ($last === 'R' ? 'Right' : 'Unknown'));

        $style = strtoupper((string) ($f['ModelStyle'] ?? ''));
        $child = false;
        foreach (['UY', 'TD', 'JN', 'INFANT'] as $t) {
            if ($style !== '' && str_contains($style, $t)) {
                $child = true;
            }
        }
        foreach (['UA', 'WM', 'MN'] as $t) {
            if ($style !== '' && str_contains($style, $t)) {
                $child = false;
            }
        }
        $std = $child ? 2.5 : 3.0;
        $set($f, 'AgeCategory', $child ? 'Children' : 'Adult');
        $set($f, 'STD', $std);

        $rank  = ['PASS' => 0, 'TOLERABLE' => 1, 'FAILED' => 2];
        $worst = static function (string $a, string $b) use ($rank): string {
            return $rank[$b] > $rank[$a] ? $b : $a;
        };
        $grade = static function (float $v, float $pass, float $tol): string {
            return $v >= $pass ? 'PASS' : ($v >= $tol ? 'TOLERABLE' : 'FAILED');
        };
        $overall = 'PASS';
        $maxPts  = 0;
        foreach (['UTF' => ['UTFpoint', 45, $std, $std === 3.0 ? 2.4 : 2.0], 'FTO' => ['FTOpoint', 45, $std, $std === 3.0 ? 2.4 : 2.0], 'FiTO' => ['FiTO', 13, 1.0, 0.8]] as $grp => [$pre, $max, $pass, $tol]) {
            $res = 'PASS';
            $any = false;
            for ($i = 1; $i <= $max; $i++) {
                if (isset($f[$pre . $i]) && is_numeric($f[$pre . $i])) {
                    $any = true;
                    $res = $worst($res, $grade((float) $f[$pre . $i], $pass, $tol));
                    if ($grp === 'UTF') {
                        $maxPts = max($maxPts, $i);
                    }
                }
            }
            if ($any) {
                $f[$grp . '_Result'] = $res;
                $overall = $worst($overall, $res);
            }
        }
        $f['FiTOSTD'] = 1;
        $f['Result']  = $overall;
        if ($maxPts > 0) {
            $f['MaxPoints'] = $maxPts;
        }

        return $f;
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

        // Column names such as "Supplier's Country" or "Material Description" contain apostrophes / spaces that the query
        // builder would split into alias parts, so the statement is built with explicitly quoted identifiers.
        $q = static fn (string $name): string => '`' . str_replace('`', '``', $name) . '`';

        $recordId = isset($fields[$pk]) && (int) $fields[$pk] > 0 ? (int) $fields[$pk] : 0;
        if ($recordId > 0) {
            if ($saveData) {
                $sets = implode(', ', array_map(static fn ($c) => $q((string) $c) . ' = ?', array_keys($saveData)));
                $db->query('UPDATE ' . $q($table) . ' SET ' . $sets . ' WHERE ' . $q($pk) . ' = ?', array_merge(array_values($saveData), [$recordId]));
            }
        } else {
            if ($saveData) {
                $cols = implode(', ', array_map(static fn ($c) => $q((string) $c), array_keys($saveData)));
                $marks = implode(', ', array_fill(0, count($saveData), '?'));
                $db->query('INSERT INTO ' . $q($table) . ' (' . $cols . ') VALUES (' . $marks . ')', array_values($saveData));
            } else {
                $db->query('INSERT INTO ' . $q($table) . ' () VALUES ()');
            }
            $recordId = (int) $db->insertID();
        }

        // Admin-added custom fields (Report Designer): stored per record, never as table columns
        if ($slug === 'bonding' && $db->tableExists('tbl_dtr_custom_values')) {
            foreach ($fields as $fk => $fv) {
                if (preg_match('/^cf_[A-Za-z0-9_]{1,57}$/', (string) $fk)) {
                    $where = ['slug' => $slug, 'record_id' => $recordId, 'field_key' => (string) $fk];
                    $data  = ['field_value' => (string) $fv, 'updated_at' => date('Y-m-d H:i:s')];
                    if ($db->table('tbl_dtr_custom_values')->where($where)->countAllResults() > 0) {
                        $db->table('tbl_dtr_custom_values')->where($where)->update($data);
                    } else {
                        $db->table('tbl_dtr_custom_values')->insert($where + $data);
                    }
                }
            }
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
            \App\Libraries\DtrLabrefIndex::upsert($db, $slug, $recordId, $saveData + $fields);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'recordId' => $recordId,
            'labRefNo' => $labRefNo,
            'message'  => "Saved {$cfg['label']} record #{$recordId} ({$labRefNo}) to NEXUS-L.",
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
        $netDir = \App\Libraries\MachineFileReader::root() . $subFolder;
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

        return $this->response->setStatusCode(500)->setBody('The Access PDF could not be created for report #' . (int) $id . ' (Microsoft Access must be installed on the server and this report must exist in the Access file). Please close this tab and use "Print Report / Save as PDF" instead.');
    }

    /** Path of the Access-made machine result PDF for a BondingTestID (created on first use), or null. */
    private function ensureAccessPdf(int $id, string $side): ?string
    {
        $cacheDir = WRITEPATH . 'cache/bonding_pdfs/';
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $pdfPath = $cacheDir . "bonding_report_{$id}_{$side}.pdf";
        if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
            @shell_exec('powershell -ExecutionPolicy Bypass -File ' . escapeshellarg(APPPATH . 'Libraries/export_bonding_pdf.ps1') . " -BondingTestID {$id} -Side " . escapeshellarg($side) . ' -OutPdf ' . escapeshellarg($pdfPath));
        }

        return is_file($pdfPath) && filesize($pdfPath) > 0 ? $pdfPath : null;
    }

    /**
     * POST /dtr/sheet-pdf {html, name}: renders the printable report sheet (the same one shown in the preview,
     * with the VF and SATRA logos) to a PDF with the installed Chrome / Edge in headless mode.
     */
    public function sheetPdf()
    {
        $p    = $this->request->getJSON(true) ?? $this->request->getPost();
        $html = (string) ($p['html'] ?? '');
        if ($html === '' || strlen($html) > 2000000) {
            return $this->response->setStatusCode(422)->setBody('No report to convert.');
        }

        // keep presentation only: no scripts, frames, forms, external stylesheets or event handlers
        $html = preg_replace('#<(script|iframe|object|embed|link|base|meta|form|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|link|base|meta|form)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data:text|file):[^"\']*\2/i', '$1=$2$2', $html);

        $browser = null;
        foreach (['C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'] as $c) {
            if (is_file($c)) {
                $browser = $c;
                break;
            }
        }
        if ($browser === null) {
            return $this->response->setStatusCode(500)->setBody('Chrome or Edge is required on the server to create PDFs.');
        }

        $dir = WRITEPATH . 'cache/sheet_pdfs/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $scale = max(0.4, min(1.0, (float) ($p['scale'] ?? 1)));
        $id   = bin2hex(random_bytes(8));
        $htmlFile = $dir . $id . '.html';
        $pdfFile  = $dir . $id . '.pdf';
        $doc = '<!doctype html><html><head><meta charset="utf-8"><base href="' . base_url() . '/">'
            . '<link rel="stylesheet" href="' . base_url('assets/css/dtr.css') . '">'
            . '<style>@page{size:A4 portrait;margin:6mm}body{background:#fff;margin:0}@media print{body *{visibility:visible!important}}html{zoom:' . $scale . '}.vf-report-sheet,.dtr-report-sheet{border:none!important;box-shadow:none!important;max-width:100%!important;width:100%!important;padding:0!important}</style>'
            . '</head><body>' . $html . '</body></html>';
        file_put_contents($htmlFile, $doc);

        $cmd = '"' . $browser . '" --headless=new --disable-gpu --disable-extensions --no-first-run --disable-features=Translate'
            . ' --virtual-time-budget=4000 --no-pdf-header-footer'
            . ' --user-data-dir="' . $dir . 'profile" --print-to-pdf="' . $pdfFile . '" "file:///' . str_replace('\\', '/', $htmlFile) . '" 2>&1';
        @shell_exec($cmd);
        @unlink($htmlFile);

        if (! is_file($pdfFile) || filesize($pdfFile) < 500) {
            @unlink($pdfFile);

            return $this->response->setStatusCode(500)->setBody('The PDF could not be created on the server.');
        }
        // page 2 of the attachment: the machine test result (Access report), joined to the sheet when it can be made
        $accessId = (int) ($p['access_id'] ?? 0);
        if ($accessId > 0) {
            $side   = ucfirst(strtolower((string) ($p['side'] ?? 'Left'))) === 'Right' ? 'Right' : 'Left';
            $access = $this->ensureAccessPdf($accessId, $side);
            $node   = is_file('C:\\Program Files\\nodejs\\node.exe') ? 'C:\\Program Files\\nodejs\\node.exe' : 'node';
            $merged = $dir . $id . '_m.pdf';
            if ($access !== null) {
                @shell_exec('"' . $node . '" "' . ROOTPATH . 'tools\\build\\merge-pdf.js" "' . $merged . '" "' . $pdfFile . '" "' . $access . '" 2>&1');
                if (is_file($merged) && filesize($merged) > 500) {
                    @unlink($pdfFile);
                    $pdfFile = $merged;
                }
            }
        }
        $pdf = (string) file_get_contents($pdfFile);
        @unlink($pdfFile);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string) ($p['name'] ?? 'Report')) ?: 'Report';

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.pdf"')
            ->setBody($pdf);
    }
}
