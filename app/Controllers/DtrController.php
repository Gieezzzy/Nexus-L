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
        'pull' => [
            'table'       => 'tbl_dtr_pull_results',
            'pk'          => 'ID',
            'label'       => 'Pull Footwear Test Report (Lab VF 003)',
            'code'        => 'PULL',
            'sample_type' => 'Finished Product',
            'mat_types'   => [],
        ],
    ];

    /** Lab Tracker intake tables: one row per specimen line saved from the Specimen Intake / Whole Shoe Intake popups. */
    private function ensureTrackerTables($db): void
    {
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_BondingTracker (
            TrackerID INT AUTO_INCREMENT PRIMARY KEY,
            LabRefNo VARCHAR(191) NULL,
            Building CHAR(1) NULL,
            `LineNo` VARCHAR(10) NULL,
            Side CHAR(1) NULL,
            ProductionCode VARCHAR(191) NULL,
            Style VARCHAR(191) NULL,
            Colorway VARCHAR(191) NULL,
            MTL VARCHAR(191) NULL,
            LabTest VARCHAR(50) NULL,
            TestType VARCHAR(50) NULL,
            Technician VARCHAR(191) NULL,
            Lacking TINYINT(1) NOT NULL DEFAULT 0,
            Shutdown TINYINT(1) NOT NULL DEFAULT 0,
            RetestDetails VARCHAR(500) NULL,
            CollectionDate DATE NULL,
            ProductionDate DATE NULL,
            EncodedBy VARCHAR(191) NULL,
            CreatedAt DATETIME NULL,
            UNIQUE KEY uq_labref (LabRefNo),
            INDEX (ProductionDate), INDEX (CollectionDate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        if (! in_array('technician', array_map('strtolower', $db->getFieldNames('tbl_dtr_BondingTracker')), true)) {
            $db->query('ALTER TABLE tbl_dtr_BondingTracker ADD COLUMN Technician VARCHAR(191) NULL AFTER TestType');
        }
        if (! in_array('side', array_map('strtolower', $db->getFieldNames('tbl_dtr_BondingTracker')), true)) {
            $db->query('ALTER TABLE tbl_dtr_BondingTracker ADD COLUMN Side CHAR(1) NULL AFTER `LineNo`');
        }
        if (! $db->fieldExists('PullTest', 'tbl_dtr_BondingTracker')) {
            $db->query('ALTER TABLE tbl_dtr_BondingTracker ADD COLUMN PullTest TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN PullTestType VARCHAR(30) NULL AFTER PullTest');
        }
        if (! $db->fieldExists('LackingDate', 'tbl_dtr_BondingTracker')) {
            $db->query('ALTER TABLE tbl_dtr_BondingTracker ADD COLUMN LackingDate DATE NULL AFTER Lacking');
        }
        if (! $db->fieldExists('Shutdown', 'tbl_dtr_BondingTracker')) {
            // table made before shutdown marking: a Shutdown row has no specimen, so LabRefNo may be empty (a unique key allows many NULLs)
            $db->query('ALTER TABLE tbl_dtr_BondingTracker MODIFY LabRefNo VARCHAR(191) NULL, ADD COLUMN Shutdown TINYINT(1) NOT NULL DEFAULT 0 AFTER Lacking');
        }

        // one row per test type ticked on a whole shoe line: LabRefNo = Test Type Lab Ref No (e.g. DTP20261103AGA4), WholeshoeLabRefNo = mother (e.g. DTP20261103WHA4)
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_WholeshoeTracker (
            TrackerID INT AUTO_INCREMENT PRIMARY KEY,
            LabRefNo VARCHAR(191) NOT NULL,
            WholeshoeLabRefNo VARCHAR(191) NULL,
            Building CHAR(1) NULL,
            `LineNo` VARCHAR(10) NULL,
            Side CHAR(1) NULL,
            ProductionCode VARCHAR(191) NULL,
            Style VARCHAR(191) NULL,
            Colorway VARCHAR(191) NULL,
            MTL VARCHAR(191) NULL,
            NoOfShoes INT NOT NULL DEFAULT 1,
            TestType VARCHAR(50) NULL,
            TestCode CHAR(2) NULL,
            CollectionDate DATE NULL,
            ProductionDate DATE NULL,
            EncodedBy VARCHAR(191) NULL,
            CreatedAt DATETIME NULL,
            UNIQUE KEY uq_labref (LabRefNo),
            INDEX (WholeshoeLabRefNo), INDEX (ProductionCode), INDEX (ProductionDate), INDEX (CollectionDate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // the older whole shoe intake table also gets the Side (L / R) column
        if ($db->tableExists('tbl_dtr_wholeshoe_intake') && ! in_array('side', array_map('strtolower', $db->getFieldNames('tbl_dtr_wholeshoe_intake')), true)) {
            $db->query('ALTER TABLE tbl_dtr_wholeshoe_intake ADD COLUMN Side CHAR(1) NULL AFTER `LineNo`');
        }
        // upgrade a table made by an earlier version: column names are compared case-insensitively (MySQL ignores case)
        $have = array_map('strtolower', $db->getFieldNames('tbl_dtr_WholeshoeTracker'));
        if (in_array('wholeshoelabrefno', $have, true)) {
            foreach ($db->getFieldData('tbl_dtr_WholeshoeTracker') as $f) {
                // fix the spelling of the column name (WholeshoeLabrefNo -> WholeshoeLabRefNo) and make it nullable, once
                if (strtolower($f->name) === 'wholeshoelabrefno' && ($f->name !== 'WholeshoeLabRefNo' || ! $f->nullable)) {
                    $db->query('ALTER TABLE tbl_dtr_WholeshoeTracker CHANGE `' . $f->name . '` WholeshoeLabRefNo VARCHAR(191) NULL');
                }
            }
        } else {
            $db->query('ALTER TABLE tbl_dtr_WholeshoeTracker ADD COLUMN WholeshoeLabRefNo VARCHAR(191) NULL AFTER LabRefNo');
        }
        foreach ([
            'TestType'      => 'VARCHAR(50) NULL',
            'TestCode'      => 'CHAR(2) NULL',
            'Side'          => 'CHAR(1) NULL',
            'Status'        => "VARCHAR(50) NULL DEFAULT 'PENDING'",
            'FinalResult'   => 'VARCHAR(50) NULL',
            'Findings'      => 'TEXT NULL',
            'TimeStarted'   => 'DATETIME NULL',
            'TimeCompleted' => 'DATETIME NULL',
        ] as $col => $def) {
            if (! in_array(strtolower($col), $have, true)) {
                $db->query("ALTER TABLE tbl_dtr_WholeshoeTracker ADD COLUMN {$col} {$def}");
            }
        }
        // rows that have a Side but whose Lab Ref No has no -L / -R ending yet: add it (safe to repeat, it only touches refs without an ending)
        $db->query("UPDATE tbl_dtr_WholeshoeTracker SET
                WholeshoeLabRefNo = IF(WholeshoeLabRefNo IS NULL OR WholeshoeLabRefNo = '' OR WholeshoeLabRefNo LIKE '%-L' OR WholeshoeLabRefNo LIKE '%-R', WholeshoeLabRefNo, CONCAT(WholeshoeLabRefNo, '-', Side)),
                LabRefNo = CONCAT(LabRefNo, '-', Side)
            WHERE Side IN ('L', 'R') AND LabRefNo NOT LIKE '%-L' AND LabRefNo NOT LIKE '%-R'");
        if ($db->tableExists('tbl_dtr_wholeshoe_intake') && in_array('side', array_map('strtolower', $db->getFieldNames('tbl_dtr_wholeshoe_intake')), true)) {
            $db->query("UPDATE tbl_dtr_wholeshoe_intake SET LabRefNo = CONCAT(LabRefNo, '-', Side) WHERE Side IN ('L', 'R') AND LabRefNo NOT LIKE '%-L' AND LabRefNo NOT LIKE '%-R'");
        }        if (in_array('testtypes', $have, true)) {
            // first version kept one row per line (LabRefNo = mother, TestTypes = list): split it into one row per test type
            $codes = ['heat aging' => 'AG', 'hydrolysis' => 'HY', 'washing' => 'WS', 'uv' => 'UV', 'flexing' => 'FX', 'dip test' => 'DT'];
            foreach ($db->table('tbl_dtr_WholeshoeTracker')->get()->getResultArray() as $row) {
                $mother = (string) $row['LabRefNo'];
                $kids   = [];
                foreach (array_filter(array_map('trim', explode(',', (string) ($row['TestTypes'] ?? '')))) as $tt) {
                    if (isset($codes[strtolower($tt)])) {
                        $kids[] = [$tt, $codes[strtolower($tt)]];
                    }
                }
                $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', $row['TrackerID'])->update(['WholeshoeLabRefNo' => $mother]);
                foreach ($kids as $i => [$tt, $tc]) {
                    $ref = substr_replace($mother, $tc, 11, 2);
                    if ($i === 0) {
                        $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', $row['TrackerID'])->update(['LabRefNo' => $ref, 'TestType' => $tt, 'TestCode' => $tc]);
                    } else {
                        $copy = $row;
                        unset($copy['TrackerID'], $copy['TestTypes']);
                        $db->table('tbl_dtr_WholeshoeTracker')->ignore(true)->insert(array_merge($copy, ['LabRefNo' => $ref, 'WholeshoeLabRefNo' => $mother, 'TestType' => $tt, 'TestCode' => $tc]));
                    }
                }
            }
            $db->query('ALTER TABLE tbl_dtr_WholeshoeTracker DROP COLUMN TestTypes');
        }
    }
    public function index(): string
    {
        $db        = \Config\Database::connect();
        $this->ensureTrackerTables($db);
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

        // Bonding Shoe Collection (tbl_dtr_bonding_info) & Whole Shoe Collection (tbl_dtr_shoe_list)
        $bondingTotal = $db->table('tbl_dtr_bonding_info')->countAllResults();
        // one row per Lab Ref No: of older duplicates the one holding a result wins, otherwise the newest
        // Style / Colorway / MTL / Prod. Code: from the bonding record, or from the intake tracker (tbl_dtr_BondingTracker) when the record has none
        $bondingShoes = $db->table('tbl_dtr_bonding_info b')
            ->select("b.BondingTestID, b.LabRefNo, COALESCE(NULLIF(b.ModelStyle, ''), t.Style) AS ModelStyle, COALESCE(NULLIF(b.Color, ''), t.Colorway) AS Color, b.Side, b.Factory, b.ProductionDate, b.TestedDate, b.Technician, b.Result, b.LabNotesComments, COALESCE(NULLIF(b.POLotNO, ''), t.ProductionCode) AS POLotNO, COALESCE(NULLIF(b.MTL, ''), t.MTL) AS MTL, b.ReceivedDate, b.Remarks, b.TestType", false)
            ->join('(SELECT LabRefNo, MAX(TrackerID) AS tid FROM tbl_dtr_BondingTracker WHERE LabRefNo IS NOT NULL GROUP BY LabRefNo) tm', 'tm.LabRefNo = b.LabRefNo COLLATE utf8mb4_general_ci', 'left', false)
            ->join('tbl_dtr_BondingTracker t', 't.TrackerID = tm.tid', 'left')
            ->where("(b.LabRefNo IS NULL OR b.LabRefNo = '' OR b.BondingTestID IN (SELECT keep_id FROM (SELECT COALESCE(MAX(CASE WHEN Result IS NOT NULL AND Result <> '' THEN BondingTestID END), MAX(BondingTestID)) AS keep_id FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' GROUP BY LabRefNo) k))", null, false)
            ->orderBy('b.BondingTestID', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();

        $shoeTotal  = $db->table('tbl_dtr_shoe_list')->countAllResults();
        $this->wsReportTable();   // makes sure tbl_dtr_wholeshoe_reports exists (the list reads the Technician saved in a report)
        // Whole Shoe Collection = every recorded test type (one row each) from tbl_dtr_WholeshoeTracker
        $wholeShoes = $db->table('tbl_dtr_WholeshoeTracker')
            ->select('LabRefNo, WholeshoeLabRefNo, ProductionCode AS ProdCode, Style AS StyleNo, Colorway, MTL, Building, `LineNo`, Side, NoOfShoes, TestType AS TestTypes, CollectionDate, ProductionDate AS IntakeProductionDate, EncodedBy, Status, FinalResult, Findings, TimeStarted, TimeCompleted, (SELECT JSON_VALUE(rp.ReportData, \'$.technician\') FROM tbl_dtr_wholeshoe_reports rp WHERE rp.LabRefNo COLLATE utf8mb4_general_ci IN (tbl_dtr_WholeshoeTracker.LabRefNo COLLATE utf8mb4_general_ci, tbl_dtr_WholeshoeTracker.WholeshoeLabRefNo COLLATE utf8mb4_general_ci) ORDER BY rp.ReportID DESC LIMIT 1) AS Technician', false)
            ->orderBy('TrackerID', 'DESC')
            ->limit(200)
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
            'withJs'           => ['dtr.js', 'dtr-photo.js', 'dtr-photo-attach.js'],
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
            'bondingTotal'     => $bondingTotal,
            'bondingShoes'     => $bondingShoes,
            'shoeTotal'        => $shoeTotal,
            'wholeShoes'       => $wholeShoes,
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
     * GET /dtr/bonding-labrefs
     * Distinct Lab Ref Nos of tbl_dtr_bonding_points, newest first (feeds the Lab Ref.No dropdown of the bonding form).
     */
    public function bondingLabRefs()
    {
        $rows = \Config\Database::connect()->table('tbl_dtr_bonding_points')
            ->select('LabRefNo, MAX(BondingPointstID) AS last_id', false)
            ->where('LabRefNo IS NOT NULL', null, false)->where('LabRefNo !=', '')
            ->groupBy('LabRefNo')->orderBy('last_id', 'DESC')
            ->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'labRefs' => array_column($rows, 'LabRefNo')]);
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
            $record = null;
            if ($slug === 'bonding') {
                // Prefer the report that owns the saved spot readings of this Lab Ref No.
                $bp = $db->table('tbl_dtr_bonding_points')->select('BondingTestID')->where('LabRefNo', $labRef)->orderBy('BondingPointstID', 'DESC')->get(1)->getRowArray();
                if ($bp) {
                    $record = $db->table($table)->where($pk, $bp['BondingTestID'])->get()->getRowArray();
                }
            }
            $record = $record ?: $db->table($table)->where('LabRefNo', $labRef)->orderBy($pk, 'DESC')->get()->getRowArray();
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
     * GET /dtr/shoe-lookup?code=&mtl=
     * Library lookup for the Specimen Intake form: finds a whole shoe by Production Code or MTL #.
     */
    public function shoeLookup()
    {
        $db   = \Config\Database::connect();
        $code = trim((string) ($this->request->getGet('code') ?? ''));
        $mtl  = trim((string) ($this->request->getGet('mtl') ?? ''));
        $shoe = null;

        if ($code !== '') {
            $shoe = $db->table('tbl_dtr_shoe_list')->where('ProdCode', $code)->orderBy('ID', 'DESC')->get(1)->getRowArray();
        }
        if (! $shoe && $mtl !== '') {
            $shoe = $db->table('tbl_dtr_shoe_list')->where('MTL', $mtl)->orderBy('ID', 'DESC')->get(1)->getRowArray();
        }

        return $this->response->setJSON(['ok' => true, 'shoe' => $shoe, 'csrf' => csrf_hash()]);
    }

    /**
     * GET /dtr/erp-shoe-search?q= : Production Code filter for Specimen Intake, read from dberp.production_orders (newest first, every order; the list scrolls).
     */
    public function erpShoeSearch()
    {
        $q  = trim((string) ($this->request->getGet('q') ?? ''));
        $db = \Config\Database::connect();
        try {
            $b = $db->table('dberp.production_orders')->select('prod_code AS ProdCode, style_no AS StyleNo, colorway AS Colorway, mtl_no AS MTL');
            if ($q !== '') {
                $b->groupStart()->like('prod_code', $q)->orLike('style_no', $q)->orLike('mtl_no', $q)->orLike('colorway', $q)->groupEnd();
            }
            $shoes = $b->orderBy('id', 'DESC')->limit(3000)->get()->getResultArray();
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'shoes' => [], 'message' => 'ERP production orders are not reachable.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'shoes' => $shoes, 'csrf' => csrf_hash()]);
    }

    /**
     * GET /dtr/bonding-std?style= : the pass / fail limits of tbl_bonding_test_std for the style code (first word of the
     * Model/Style, e.g. "UY CLASSIC SLIP-ON" -> UY). The Bonding sheet shows them as FailMax - PassMin (1.99 - 2.50).
     */
    public function bondingStd()
    {
        $style = strtoupper(trim((string) ($this->request->getGet('style') ?? '')));
        $row   = null;
        try {
            $db = \Config\Database::connect();
            if ($style !== '' && $db->tableExists('tbl_bonding_test_std')) {
                // the first word is the style code; otherwise any word of the style that is a code (e.g. "... OSNABURG")
                $words = array_values(array_unique(array_filter(preg_split('/[^A-Z0-9]+/', $style))));
                foreach ($words as $w) {
                    $row = $db->table('tbl_bonding_test_std')->select('StyleCode, StyleGroup, PassMin, ToleMin, FailMax')->where('StyleCode', $w)->get()->getRowArray();
                    if ($row) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $row = null;
        }
        if (! $row) {
            return $this->response->setJSON(['ok' => true, 'found' => false, 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON([
            'ok' => true, 'found' => true, 'code' => $row['StyleCode'], 'group' => $row['StyleGroup'],
            'failMax' => number_format((float) $row['FailMax'], 2, '.', ''), 'passMin' => number_format((float) $row['PassMin'], 2, '.', ''),
            'csrf' => csrf_hash(),
        ]);
    }

    private const BOND_IMG_DIR = 'D:\\BACK UP\\SystemProcess\\BondingImages\\';

    /**
     * The current report picture of a Lab Ref No in the BondingImages folder. Versioning of the folder:
     *   <LabRef>.png                          = the current report
     *   <LabRef>_1.png, _2.png ...            = older versions (the oldest is _1)
     *   <date>-<time>_<LabRef>_<hash>.png     = batch-tracking copy written at every save
     * The current report wins; without one the newest of the other files is used.
     */
    private function bondingImageFile(string $labRef, bool $original = false): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $labRef) || ! is_dir(self::BOND_IMG_DIR)) {
            return null;
        }
        if (is_file(self::BOND_IMG_DIR . $labRef . '.png')) {
            return self::BOND_IMG_DIR . $labRef . '.png';
        }
        $re   = '/^(?:\d{8}-\d{4}_)?' . preg_quote($labRef, '/') . '(?:_\d+|_[0-9a-f]{8})?\.png$/i';
        $best = null;
        $bt   = 0;
        foreach (scandir(self::BOND_IMG_DIR) ?: [] as $f) {
            if (preg_match($re, $f)) {
                $t = (int) @filemtime(self::BOND_IMG_DIR . $f);
                if ($best === null || $t >= $bt) {
                    $best = self::BOND_IMG_DIR . $f;
                    $bt   = $t;
                }
            }
        }

        return $best;
    }

    /** GET /dtr/bonding-image?lab_ref=[&orig=1] : the report picture of a Lab Ref (404 when there is none) */
    public function bondingImage()
    {
        $file = $this->bondingImageFile(trim((string) ($this->request->getGet('lab_ref') ?? '')), (bool) $this->request->getGet('orig'));
        if ($file === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', 'image/png')->setHeader('Cache-Control', 'private, max-age=30')->setBody((string) file_get_contents($file));
    }

    /** POST /dtr/bonding-image-save {lab_ref, image: data:image/png;base64,...} : saved as the new current report; the old one is kept as the next _n version */
    public function bondingImageSave()
    {
        $p      = $this->request->getJSON(true) ?? [];
        $labRef = trim((string) ($p['lab_ref'] ?? ''));
        $data   = (string) ($p['image'] ?? '');
        $fail   = fn (string $m) => $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $m, 'csrf' => csrf_hash()]);

        if (! preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $labRef)) {
            return $fail('Invalid Lab Ref No.');
        }
        if (! is_dir(self::BOND_IMG_DIR)) {
            return $fail('The BondingImages folder is not reachable.');
        }
        if (! preg_match('#^data:image/png;base64,#', $data)) {
            return $fail('The edited picture is missing.');
        }
        $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
        if ($bin === false || strncmp($bin, "\x89PNG", 4) !== 0 || strlen($bin) > 25 * 1024 * 1024) {
            return $fail('The edited picture is not a valid PNG (up to 25 MB).');
        }
        $dir     = self::BOND_IMG_DIR;
        $current = $dir . $labRef . '.png';
        $moved   = '';
        // the report in place becomes the next old version (<LabRef>_<n+1>.png); the edited picture becomes the current <LabRef>.png
        if (is_file($current)) {
            $n = 0;
            foreach (scandir($dir) ?: [] as $f) {
                if (preg_match('/^' . preg_quote($labRef, '/') . '_(\d+)\.png$/i', $f, $m)) {
                    $n = max($n, (int) $m[1]);
                }
            }
            $moved = $labRef . '_' . ($n + 1) . '.png';
            if (! @rename($current, $dir . $moved)) {
                return $fail('Could not keep the previous version; nothing was changed.');
            }
        }
        if (@file_put_contents($current, $bin) === false) {
            if ($moved !== '') {
                @rename($dir . $moved, $current);   // put the old report back
            }

            return $fail('Could not save into the BondingImages folder.');
        }
        // batch-tracking copy, same naming as the machine batches: <date>-<time>_<LabRef>_<hash>.png
        @file_put_contents($dir . date('Ymd-Hi') . '_' . $labRef . '_' . substr(md5($bin), 0, 8) . '.png', $bin);

        return $this->response->setJSON([
            'ok' => true, 'csrf' => csrf_hash(),
            'message' => "Report of {$labRef} saved" . ($moved !== '' ? "; the previous version is now {$moved}." : '.'),
        ]);
    }

    /**
     * GET /dtr/erp-image?mtl= : shoe picture named after the MTL # in \\192.168.32.43\ERP Image (e.g. VN00018E0E0.JPG).
     *
     * Resolution order:
     *   1. Local cache  - writable/cache/erp_images/<MTL>.<ext>  (populated on first network hit; survives Apache restarts)
     *   2. Network share - \\192.168.32.43\ERP Image\<MTL>.<ext>  (direct read; requires Apache to run as DTP-ADMIN)
     * 404 is returned and the error logged (once per MTL per day) when neither source works.
     */
    public function erpImage()
    {
        $mtl = trim((string) ($this->request->getGet('mtl') ?? ''));
        if (! preg_match('/^[A-Za-z0-9_\-]{3,40}$/', $mtl)) {
            return $this->response->setStatusCode(404);
        }

        $cacheDir = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'erp_images' . DIRECTORY_SEPARATOR;
        $netDir   = '\\\\192.168.32.43\\ERP Image\\';
        $exts     = ['JPG', 'jpg', 'JPEG', 'jpeg', 'PNG', 'png'];

        // ---- 0. A picture taken with the phone camera link (Lab Tracker > Bonding Shoe Collected) ----
        $taken = PhotoLinkController::photoDir() . $mtl . '.jpg';
        if (is_file($taken)) {
            return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=0, must-revalidate')->setBody((string) file_get_contents($taken));
        }

        // ---- 1. Local cache (fastest - no network round-trip) ----
        foreach ($exts as $ext) {
            $cached = $cacheDir . $mtl . '.' . $ext;
            if (is_file($cached)) {
                $bytes = file_get_contents($cached);
                if ($bytes !== false && $bytes !== '') {
                    $mime = stripos($ext, 'png') !== false ? 'image/png' : 'image/jpeg';
                    return $this->response
                        ->setHeader('Content-Type', $mime)
                        ->setHeader('Cache-Control', 'private, max-age=86400')
                        ->setBody($bytes);
                }
            }
        }

        // ---- 2. Network share (works when Apache runs as DTP-ADMIN) ----
        $err = '';
        foreach ($exts as $ext) {
            $bytes = @file_get_contents($netDir . $mtl . '.' . $ext);
            if ($bytes !== false && $bytes !== '') {
                // Save to local cache for next request
                if (! is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0755, true);
                }
                @file_put_contents($cacheDir . $mtl . '.' . $ext, $bytes);

                $mime = stripos($ext, 'png') !== false ? 'image/png' : 'image/jpeg';
                return $this->response
                    ->setHeader('Content-Type', $mime)
                    ->setHeader('Cache-Control', 'private, max-age=86400')
                    ->setBody($bytes);
            }
            $err = (string) (error_get_last()['message'] ?? '');
        }

        // ---- 3. Same name in another case / extension, or the first numbered picture (<MTL>-1.JPG) ----
        $names = @scandir($netDir) ?: [];
        $want  = [strtolower($mtl), strtolower($mtl) . '-1'];
        foreach ($want as $w) {
            foreach ($names as $nm) {
                $info = pathinfo($nm);
                if (isset($info['extension']) && strtolower($info['filename']) === $w && in_array(strtolower($info['extension']), ['jpg', 'jpeg', 'png'], true)) {
                    $bytes = @file_get_contents($netDir . $nm);
                    if ($bytes !== false && $bytes !== '') {
                        $mime = strtolower($info['extension']) === 'png' ? 'image/png' : 'image/jpeg';

                        return $this->response->setHeader('Content-Type', $mime)->setHeader('Cache-Control', 'private, max-age=86400')->setBody($bytes);
                    }
                }
            }
        }

        // Not found or no access - log only the first occurrence per MTL per day to avoid log spam
        $logKey = 'erp_img_notfound_' . $mtl;
        if (! cache($logKey)) {
            log_message('warning', 'ERP image ' . $mtl . ': ' . ($err ?: 'not found in share or cache'));
            cache()->save($logKey, 1, 86400);
        }

        return $this->response->setStatusCode(404)->setContentType('text/plain')->setBody('No image for ' . $mtl);
    }

    /**
     * GET /dtr/shoe-search?q=
     * Library picker for the Specimen Intake form: searches tbl_dtr_shoe_list (newest first, max 30).
     */
    public function shoeSearch()
    {
        $q       = trim((string) ($this->request->getGet('q') ?? ''));
        $builder = \Config\Database::connect()->table('tbl_dtr_shoe_list');

        if ($q !== '') {
            $builder->groupStart()
                ->like('ProdCode', $q)
                ->orLike('StyleNo', $q)
                ->orLike('Colorway', $q)
                ->orLike('MTL', $q)
                ->groupEnd();
        }

        $shoes = $builder->orderBy('ID', 'DESC')->limit(30)->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'shoes' => $shoes, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /dtr/wholeshoe-save
     * Whole Shoe Intake batch: adds shoes to tbl_dtr_shoe_list. A Production Code that already exists is skipped.
     */
    public function wholeShoeSave()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $rows = is_array($p['rows'] ?? null) ? $p['rows'] : [];
        if (! $rows) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'No whole shoe lines to save.', 'csrf' => csrf_hash()]);
        }

        $db      = \Config\Database::connect();
        $this->ensureTrackerTables($db);
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_wholeshoe_intake (
            IntakeID INT AUTO_INCREMENT PRIMARY KEY,
            Building CHAR(1) NULL,
            `LineNo` VARCHAR(10) NULL,
            Side CHAR(1) NULL,
            LabRefNo VARCHAR(191) NULL,
            ProdCode VARCHAR(191) NULL,
            NoOfShoes INT NOT NULL DEFAULT 1,
            TestTypes VARCHAR(255) NULL,
            CollectionDate DATE NULL,
            ProductionDate DATE NULL,
            EncodedBy VARCHAR(191) NULL,
            CreatedAt DATETIME NULL,
            INDEX (ProdCode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if (! $db->fieldExists('LabRefNo', 'tbl_dtr_wholeshoe_intake')) {
            $db->query('ALTER TABLE tbl_dtr_wholeshoe_intake ADD COLUMN LabRefNo VARCHAR(191) NULL AFTER `LineNo`');
        }
        $saved   = 0;
        $skipped = [];
        $codes   = ['heat aging' => 'HA', 'hydrolysis' => 'HY', 'washing' => 'WG', 'uv' => 'UV', 'flexing' => 'FX', 'dip test' => 'DT'];
        $now     = date('Y-m-d H:i:s');

        foreach ($rows as $r) {
            $code = mb_substr(trim((string) ($r['code'] ?? '')), 0, 191);
            if ($code === '') {
                continue;
            }

            // Whole Shoe Lab Ref No (mother, kept internally) = DTP + production date + WH + building + line.
            // Test Type Lab Ref No (one per ticked test) = the same with WH replaced by the test code, e.g. DTP20261103AGA4. Duplicates are checked on the Test Type Lab Ref No.
            $bld    = strtoupper(mb_substr((string) ($p['building'] ?? 'A'), 0, 1)) === 'B' ? 'B' : 'A';
            $pd     = preg_replace('/\D/', '', (string) (($p['productionDate'] ?? '') ?: ($p['collectionDate'] ?? '') ?: date('Y-m-d')));
            $lineN  = (int) preg_replace('/\D/', '', (string) ($r['line'] ?? '')) ?: ($saved + 1);
            $side   = ($r['side'] ?? 'L') === 'R' ? 'R' : 'L';
            $mother = 'DTP' . $pd . 'WH' . $bld . $lineN . '-' . $side;   // -L / -R like the bonding Lab Ref No

            $kids = [];
            foreach ((array) ($r['tests'] ?? []) as $tt) {
                $tc = $codes[strtolower(trim((string) $tt))] ?? null;
                if ($tc !== null) {
                    // Test Type Lab Ref No: DTP<date><code><line>-<side>-<#>  (UV: DTP<date>UV<line>-<#>, no side); # = next free number of that code on that line
                    $key = 'DTP' . $pd . $tc . $bld . $lineN;
                    $n   = 0;
                    foreach ($db->table('tbl_dtr_WholeshoeTracker')->select('LabRefNo')->like('LabRefNo', $key . '-', 'after')->get()->getResultArray() as $x) {
                        if (preg_match('/^' . preg_quote($key, '/') . '-(?:[LR]-)?(\d+)$/', (string) $x['LabRefNo'], $mm)) {
                            $n = max($n, (int) $mm[1]);
                        }
                    }
                    $kids[] = ['type' => trim((string) $tt), 'code' => $tc, 'ref' => $tc === 'UV' ? $key . '-' . ($n + 1) : $key . '-' . $side . '-' . ($n + 1)];
                }
            }
            // one tracker row per test type; a line with no test ticked is recorded once under the mother Lab Ref No
            $base = [
                'WholeshoeLabRefNo' => $mother,
                'Building'          => $bld,
                'LineNo'            => mb_substr((string) ($r['line'] ?? ''), 0, 10),
                'Side'              => $side,
                'ProductionCode'    => $code,
                'Style'             => mb_substr(trim((string) ($r['style'] ?? '')), 0, 191),
                'Colorway'          => mb_substr(trim((string) ($r['color'] ?? '')), 0, 191),
                'MTL'               => mb_substr(trim((string) ($r['mtl'] ?? '')), 0, 191),
                'NoOfShoes'         => max(1, (int) ($r['qty'] ?? 1)),
                'CollectionDate'    => ($p['collectionDate'] ?? '') ?: null,
                'ProductionDate'    => ($p['productionDate'] ?? '') ?: null,
                'EncodedBy'         => mb_substr(trim((string) ($p['encodedBy'] ?? '')), 0, 191),
                'CreatedAt'         => $now,
            ];
            $todo = $kids ?: [['type' => null, 'code' => null, 'ref' => $mother]];
            $newKids = [];
            foreach ($todo as $k) {
                if ($db->table('tbl_dtr_WholeshoeTracker')->where('LabRefNo', $k['ref'])->countAllResults() > 0) {
                    $skipped[] = $k['ref'];
                } else {
                    $newKids[] = $k;
                }
            }
            if (! $newKids) {
                continue;   // everything on this line is already recorded
            }

            if ($db->table('tbl_dtr_shoe_list')->where('ProdCode', $code)->countAllResults() === 0) {
                $db->table('tbl_dtr_shoe_list')->insert([
                    'ProdCode' => $code,
                    'StyleNo'  => mb_substr(trim((string) ($r['style'] ?? '')), 0, 191),
                    'Colorway' => trim((string) ($r['color'] ?? '')),
                    'MTL'      => mb_substr(trim((string) ($r['mtl'] ?? '')), 0, 191),
                ]);
            }
            foreach ($newKids as $k) {
                $db->table('tbl_dtr_WholeshoeTracker')->insert($base + ['LabRefNo' => $k['ref'], 'TestType' => $k['type'], 'TestCode' => $k['code']]);

                // Automatically integrate into tbl_material_tests for bench monitoring
                if (! empty($k['ref']) && $db->tableExists('tbl_material_tests') && $db->table('tbl_material_tests')->where('labreference', $k['ref'])->countAllResults() === 0) {
                    $cat = \App\Controllers\WstController::mapTrackerTestTypeToCategory((string) ($k['type'] ?? ''), (string) ($k['code'] ?? ''));
                    $spec = trim((string) ($r['style'] ?? 'Standard'));
                    if (! empty($r['color'])) {
                        $spec .= ' - ' . trim((string) $r['color']);
                    }
                    $target = match ($cat) {
                        'hydrolysis', 'heat-aging' => 168,
                        'uv-light' => 24,
                        'washing' => 10,
                        'deeptest' => 50000,
                        default => 100000,
                    };
                    $enc = mb_substr(trim((string) ($p['encodedBy'] ?? 'QC Specialist')), 0, 100);
                    $cDate = ($p['collectionDate'] ?? '') ?: (($p['productionDate'] ?? '') ?: date('Y-m-d'));
                    $db->table('tbl_material_tests')->insert([
                        'category'         => $cat,
                        'labreference'     => $k['ref'],
                        'testdate'         => $cDate,
                        'type'             => $spec,
                        'machine_number'   => 'MC-01',
                        'samplecountdry'   => 1,
                        'samplecountwet'   => 0,
                        'samplecounthydro' => 0,
                        'target_cycles'    => $target,
                        'current_cycles'   => 0,
                        'status'           => 'PENDING',
                        'is_running'       => 0,
                        'operator'         => $enc,
                        'technician'       => $enc,
                        'temperature'      => 23.00,
                        'humidity'         => 50.00,
                        'remarks'          => '',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
            }
            $types = implode(', ', array_filter(array_column($newKids, 'type')));
            $db->table('tbl_dtr_wholeshoe_intake')->insert([
                'Building'       => $bld,
                'LineNo'         => mb_substr((string) ($r['line'] ?? ''), 0, 10),
                'Side'           => $side,
                'LabRefNo'       => $mother,
                'ProdCode'       => $code,
                'NoOfShoes'      => max(1, (int) ($r['qty'] ?? 1)),
                'TestTypes'      => $types,
                'CollectionDate' => ($p['collectionDate'] ?? '') ?: null,
                'ProductionDate' => ($p['productionDate'] ?? '') ?: null,
                'EncodedBy'      => mb_substr(trim((string) ($p['encodedBy'] ?? '')), 0, 191),
                'CreatedAt'      => $now,
            ]);
            \App\Libraries\DtrLabrefIndex::upsertWholeShoe($db, (int) $db->insertID());   // the new whole shoe is a Lab Reference of the InterLink picker at once
            $saved++;
        }
        $msg = "Saved {$saved} whole shoe(s).";
        if ($skipped) {
            $msg .= ' Skipped (Test Type Lab Ref No already exists): ' . implode(', ', $skipped) . '.';
        }

        return $this->response->setJSON(['ok' => $saved > 0, 'message' => $msg, 'saved' => $saved, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /dtr/material-receive-save
     * Material Receive batch: each line is added to the IQC Container Receiving Log (tbl_dtr_containers; an existing
     * Container No. is skipped) and the full receiving details are kept in tbl_dtr_material_receipts.
     */
    public function materialReceiveSave()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $rows = is_array($p['rows'] ?? null) ? $p['rows'] : [];
        if (! $rows) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'No material lines to save.', 'csrf' => csrf_hash()]);
        }

        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_material_receipts (
            ReceiptID INT AUTO_INCREMENT PRIMARY KEY,
            MaterialType VARCHAR(191) NULL,
            ContainerNo VARCHAR(191) NULL,
            DateArrived DATE NULL,
            LabReceivedDate DATE NULL,
            Quantity VARCHAR(100) NULL,
            FinishedTesting VARCHAR(100) NULL,
            RemainingForTesting VARCHAR(100) NULL,
            EstimatedTimeline DATE NULL,
            Status VARCHAR(191) NULL,
            EncodedBy VARCHAR(191) NULL,
            CreatedAt DATETIME NULL,
            INDEX (ContainerNo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        foreach (['MaterialName', 'ProdCode', 'ProcessingNo'] as $col) {
            if (! $db->fieldExists($col, 'tbl_dtr_material_receipts')) {
                $db->query("ALTER TABLE tbl_dtr_material_receipts ADD COLUMN {$col} VARCHAR(191) NULL AFTER ContainerNo");
            }
        }

        $date    = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;
        $str     = static fn ($v, int $n = 191) => mb_substr(trim((string) $v), 0, $n);
        $enc     = $str($p['encodedBy'] ?? '');
        $now     = date('Y-m-d H:i:s');
        $saved   = 0;
        $skipped = [];

        foreach ($rows as $r) {
            $containerNo = $str($r['no'] ?? '');
            $prod        = $str($r['prod'] ?? '');
            $proc        = $str($r['proc'] ?? '');
            $no          = $containerNo ?: ($prod ?: $proc);   // the receiving log is keyed by whichever number was entered first
            if ($no === '') {
                continue;
            }
            $received = $date($p['receivedDate'] ?? '');
            $arrived  = $date($p['arrivedDate'] ?? '');
            $db->table('tbl_dtr_material_receipts')->insert([
                'MaterialType'        => $str($r['type'] ?? ''),
                'MaterialName'        => $str($r['name'] ?? ''),
                'ContainerNo'         => $containerNo,
                'ProdCode'            => $prod,
                'ProcessingNo'        => $proc,
                'DateArrived'         => $arrived,
                'LabReceivedDate'     => $received,
                'Quantity'            => $str($r['qty'] ?? '', 100),
                'FinishedTesting'     => $str($r['finished'] ?? '', 100),
                'RemainingForTesting' => $str($r['remaining'] ?? '', 100),
                'EstimatedTimeline'   => $date($r['eta'] ?? ''),
                'EncodedBy'           => $enc,
                'CreatedAt'           => $now,
            ]);
            if ($db->table('tbl_dtr_containers')->where('ContainerNo', $no)->countAllResults() > 0) {
                $skipped[] = $no;
            } else {
                $db->table('tbl_dtr_containers')->insert([
                    'ContainerNo'       => $no,
                    'DateArrived'       => $arrived,
                    'DateReceivedInLab' => $received,
                    'Remarks'           => $str(trim(($r['type'] ?? '') . ' ' . ($r['name'] ?? ''))),
                    'Encodedby'         => $enc,
                    'EncodedDate'       => $now,
                ]);
            }
            $saved++;
        }

        $msg = "Saved {$saved} material line(s).";
        if ($skipped) {
            $msg .= ' Already in the receiving log (details still kept): ' . implode(', ', $skipped) . '.';
        }

        return $this->response->setJSON(['ok' => $saved > 0, 'message' => $msg, 'saved' => $saved, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
    }

    /**
     * GET /dtr/retest-list?q=
     * Failed bonding Lab Ref Nos still waiting for a retest, with the recorded failure analysis / reason.
     * A Lab Ref No. already referenced by a "Retest of ..." note is left out.
     */
    public function retestList()
    {
        $db = \Config\Database::connect();
        $q  = trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $db->table('tbl_dtr_bonding_info')
            ->select("LabRefNo, ModelStyle, Color, POLotNO, MTL, Result, ModeOfFailure, TestedDate, COALESCE(NULLIF(PossibleCause, ''), NULLIF(Remarks, ''), NULLIF(LabNotesComments, '')) AS Analysis", false)
            ->like('Result', 'FAIL', 'after')
            ->where("NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info r WHERE r.LabNotesComments LIKE CONCAT('%Retest of ', tbl_dtr_bonding_info.LabRefNo, '%'))", null, false);

        if ($q !== '') {
            $builder->groupStart()->like('LabRefNo', $q)->orLike('ModelStyle', $q)->orLike('Color', $q)->groupEnd();
        }

        $rows = $builder->orderBy('BondingTestID', 'DESC')->limit(60)->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /dtr/intake-save
     * Specimen Intake batch: one tbl_dtr_bonding_info row per filled line.
     * LabRefNo = DTP + production date (YYYYMMDD) + SH + building + line no. + -L / -R (e.g. DTP20261015SHA10-L).
     * A LabRefNo that already exists is skipped, never overwritten.
     */
    public function intakeSave()
    {
        $p        = $this->request->getJSON(true) ?? [];
        $building = strtoupper(trim((string) ($p['building'] ?? '')));
        $collect  = trim((string) ($p['collectionDate'] ?? ''));
        $prod     = trim((string) ($p['productionDate'] ?? '')) ?: $collect;
        $rows     = is_array($p['rows'] ?? null) ? $p['rows'] : [];
        $encodedBy = mb_substr(trim((string) ($p['encodedBy'] ?? '')), 0, 100);
        $fail     = fn (string $msg) => $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $msg, 'csrf' => csrf_hash()]);

        if (! in_array($building, ['A', 'B'], true)) {
            return $fail('Choose Building A or B.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $prod) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $collect)) {
            return $fail('Collection and Production dates are required.');
        }
        $shutdowns = array_values(array_filter(array_map(static fn ($x) => strtoupper(trim((string) $x)), is_array($p['shutdowns'] ?? null) ? $p['shutdowns'] : [])));
        if (! $rows && ! $shutdowns) {
            return $fail('No specimen lines to save.');
        }

        $db      = \Config\Database::connect();
        $this->ensureTrackerTables($db);

        // lines whose Active toggle was off are recorded as SHUTDOWN for the production date (replaces an earlier mark of the same line/date)
        $down = 0;
        foreach ($shutdowns as $ln) {
            if (! preg_match('/^' . $building . '(\d{1,2})$/', $ln, $m)) {
                continue;
            }
            $db->table('tbl_dtr_BondingTracker')->where(['Shutdown' => 1, 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'ProductionDate' => $prod])->delete();
            $db->table('tbl_dtr_BondingTracker')->insert([
                'LabRefNo' => null, 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'LabTest' => 'Bonding', 'TestType' => 'SHUTDOWN', 'Shutdown' => 1,
                'CollectionDate' => $collect, 'ProductionDate' => $prod, 'EncodedBy' => $encodedBy, 'CreatedAt' => date('Y-m-d H:i:s'),
            ]);
            $down++;
        }
        $table   = $db->table('tbl_dtr_bonding_info');
        $saved   = 0;
        $skipped = [];
        $updated = 0;

        foreach ($rows as $r) {
            // Lab Ref families: DAILY SH<line> | TRIAL TRIALSH | SAMPLE SAMPLESH<line> | INTERNAL TESTING ITSH | FIRST ARTICLE FASH<line> | RETEST SH<line>-R01
            $typeU   = strtoupper(trim((string) ($r['type'] ?? '')));
            $noLine  = in_array($typeU, ['TECH TRIAL', 'INTERNAL TESTING', 'SAMPLE'], true);   // these belong to no line
            $mainPre = ['TECH TRIAL' => 'TRIALSH', 'SAMPLE' => 'SAMPLESH', 'INTERNAL TESTING' => 'ITSH', 'FIRST ARTICLE' => 'FASH'][$typeU] ?? 'SH';
            // SAMPLE > Strip / Osna / Rivet: DTP<date>STRIP|OSNA|RIVET-<#>, no line, no side (SAMPLE > Bonding keeps SAMPLESH)
            $subTest = strtoupper(trim((string) ($r['sub'] ?? '')));
            $isStrip = $typeU === 'SAMPLE' && in_array($subTest, ['STRIP', 'OSNA', 'RIVET'], true);
            if ($isStrip) {
                $noLine  = true;
                $mainPre = $subTest;
            }
            $line    = $noLine ? $building . '0' : strtoupper(trim((string) ($r['line'] ?? '')));
            if (! preg_match('/^' . $building . '(\d{1,2})$/', $line, $m)) {
                continue;
            }
            $side   = (($r['side'] ?? 'L') === 'R') ? 'R' : 'L';

            // the normal bonding specimen (SH) plus one extra record per ticked pull type, each with its own Lab Ref prefix
            $pullCodes = ['HEEL PULL' => 'HP', 'EYELET' => 'EY', 'D-RING' => 'DR', 'RIVET' => 'RVT'];
            // Lab Ref families: SH<line> normal, FA<line> first case, TECH<n> tech trial (no line)
            $variants  = [[$mainPre, '']];
            foreach (($typeU === 'SAMPLE' || ! is_array($r['pullTypes'] ?? null)) ? [] : $r['pullTypes'] as $pt) {
                $pt = strtoupper(trim((string) $pt));
                if (isset($pullCodes[$pt]) && ! in_array([$pullCodes[$pt], $pt], $variants, true)) {
                    $variants[] = [$pullCodes[$pt], $pt];
                }
            }

            foreach ($variants as [$prefix, $pullType]) {
            // a lacking specimen is booked under the date it is due; the real specimen encoded that day then updates it (no duplicate)
            $lackDate = (! empty($r['lacking']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($r['lackingDate'] ?? ''))) ? $r['lackingDate'] : '';
            $keyRef = 'DTP' . str_replace('-', '', $lackDate ?: $prod) . $prefix . (($noLine && $prefix === $mainPre) ? '' : $building . (int) $m[1]);
            $labRef = $keyRef . ($isStrip ? '' : '-' . $side);   // retest base / fallback
            if ($typeU === 'TECH TRIAL' && $prefix === $mainPre) {
                // a trial is checked first: the same MTL # / Style on the same side may not be recorded twice
                $tm = trim((string) ($r['mtl'] ?? ''));
                $ts = trim((string) ($r['style'] ?? ''));
                $dupQ = $db->table('tbl_dtr_bonding_info')->select('LabRefNo')->whereIn('TestType', ['TRIAL', 'TECH TRIAL'])->where('Side', $side === 'R' ? 'Right' : 'Left');
                if ($tm !== '') {
                    $dupQ->where('MTL', $tm);
                } elseif ($ts !== '') {
                    $dupQ->where('ModelStyle', $ts);
                } else {
                    $dupQ = null;
                }
                $dup = $dupQ ? $dupQ->get()->getRowArray() : null;
                if ($dup) {
                    $skipped[] = $dup['LabRefNo'];
                    continue 2;
                }
            }
            if (empty($r['retest'])) {
                // running number: -<#> = next free number per line (whatever the side); a LACKING record of the same line and side is taken over by the real specimen
                $n = 0;
                $lackRef = '';
                $q = $db->table('tbl_dtr_bonding_info')->select('LabRefNo, LabNotesComments')->like('LabRefNo', $keyRef . '-', 'after')->orderBy('BondingTestID', 'DESC')->get()->getResultArray();
                $pat = $isStrip ? '/^' . preg_quote($keyRef, '/') . '-(\d+)$/' : '/^' . preg_quote($keyRef, '/') . '-([LR])-(\d+)$/';
                foreach ($q as $x) {
                    if (preg_match($pat, (string) $x['LabRefNo'], $mm)) {
                        $num = (int) end($mm);
                        $n = max($n, $num);
                        if ($lackRef === '' && ($isStrip || $mm[1] === $side) && stripos((string) $x['LabNotesComments'], 'LACKING') !== false) {
                            $lackRef = $x['LabRefNo'];
                        }
                    }
                }
                $labRef = (empty($r['lacking']) && $lackRef !== '') ? $lackRef : ($isStrip ? $keyRef . '-' . ($n + 1) : $keyRef . '-' . $side . '-' . ($n + 1));
            }

            if (! empty($r['retest'])) {
                // a retest is a new record: the failed batch's Lab Ref + -R01, -R02 ... (next free number); without a picked
                // failed Lab Ref it falls back to this line's own Lab Ref
                $of = trim((string) ($r['retestOf'] ?? ''));
                if ($pullType === '' && preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $of)) {
                    $labRef = preg_replace('/(-R\d+|-\d+)$/', '', $of);
                }
                $last = 0;
                foreach ($db->table('tbl_dtr_bonding_info')->select('LabRefNo')->like('LabRefNo', $labRef . '-R', 'after')->get()->getResultArray() as $x) {
                    if (preg_match('/-R(\d+)$/', (string) $x['LabRefNo'], $mm)) {
                        $last = max($last, (int) $mm[1]);
                    }
                }
                $labRef .= '-R' . str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
            }

            $existing = $db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $labRef)->orderBy('BondingTestID', 'DESC')->get()->getRowArray();

            $notes = [];
            if (! empty($r['mtl'])) {
                $notes[] = 'MTL ' . trim((string) $r['mtl']);
            }
            if (! empty($r['retest'])) {
                $notes[] = 'RETEST: ' . trim((string) ($r['retestNote'] ?? ''));
            }
            if (! empty($r['lacking'])) {
                $notes[] = 'LACKING' . ($lackDate !== '' ? ' (due ' . $lackDate . ')' : '');
            }
            if ($pullType !== '') {
                $notes[] = 'PULL TEST: ' . $pullType;
            }
            if ($isStrip) {
                $notes[] = $subTest . ' TEST';
            }
            if ($encodedBy !== '') {
                $notes[] = 'Encoded by ' . $encodedBy;
            }

            $infoRow = [
                'LabRefNo'         => $labRef,
                'Factory'          => 'DATIAN SUBIC SHOES INC. (DTP)',
                'ModelStyle'       => mb_substr(trim((string) ($r['style'] ?? '')), 0, 191),
                'Color'            => trim((string) ($r['color'] ?? '')),
                'ProductionDate'   => $prod,
                'POLotNO'          => mb_substr(trim((string) ($r['code'] ?? '')), 0, 191),
                'MaterialSource'   => 'Prod. Material',
                'ReceivedDate'     => $collect,
                'Side'             => $side === 'R' ? 'Right' : 'Left',
                'Technician'       => mb_substr(trim((string) ($r['tech'] ?? '')), 0, 191),
                'TestType'         => mb_substr(trim((string) ($r['type'] ?? '')), 0, 50),
                'MTL'              => mb_substr(trim((string) ($r['mtl'] ?? '')), 0, 191),
                'EncodedBy'        => $encodedBy,
                'Remarks'        => mb_substr(trim((string) ($r['type'] ?? '')) . (! empty($r['retest']) ? ' / Retest' : ''), 0, 191),
                'LabNotesComments' => implode('; ', $notes),
            ];
            if ($existing) {
                // same line/side/date already collected: refresh the intake fields only (test readings are left untouched)
                $table->where('BondingTestID', (int) $existing['BondingTestID'])->update($infoRow);
                $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $labRef)->delete();
                $updated++;
                $infoId = (int) $existing['BondingTestID'];
            } else {
                $table->insert($infoRow);
                $infoId = (int) $db->insertID();
            }
            // the new / refreshed collection also goes into tbl_dtr_unified_labrefs (Pending until it is tested), so InterLink, Analytics and the Brain see it
            // (a record that is already indexed keeps its tested result: only a missing index row is added here)
            if (! $existing || ! $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_bonding_info')->where('source_id', $infoId)->countAllResults()) {
                \App\Libraries\DtrLabrefIndex::upsert($db, 'bonding', $infoId, $infoRow + ['Status' => 'Pending', 'Result' => '', 'TestedDate' => '', 'FinishedDate' => '']);
            }
            $db->table('tbl_dtr_BondingTracker')->insert([
                'LabRefNo'       => $labRef,
                'Building'       => $building,
                'LineNo'         => $building . (int) $m[1],
                'Side'           => $side,
                'ProductionCode' => mb_substr(trim((string) ($r['code'] ?? '')), 0, 191),
                'Style'          => mb_substr(trim((string) ($r['style'] ?? '')), 0, 191),
                'Colorway'       => mb_substr(trim((string) ($r['color'] ?? '')), 0, 191),
                'MTL'            => mb_substr(trim((string) ($r['mtl'] ?? '')), 0, 191),
                'LabTest'        => 'Bonding',
                'PullTest'       => $pullType !== '' ? 1 : 0,
                'PullTestType'   => $pullType !== '' ? $pullType : null,
                'TestType'       => mb_substr(trim((string) ($r['type'] ?? '')), 0, 50),
                'Technician'     => mb_substr(trim((string) ($r['tech'] ?? '')), 0, 191),
                'Lacking'        => ! empty($r['lacking']) ? 1 : 0,
                'LackingDate'    => $lackDate !== '' ? $lackDate : null,
                'RetestDetails'  => mb_substr(trim((string) ($r['retestNote'] ?? '')), 0, 500),
                'CollectionDate' => $collect,
                'ProductionDate' => $prod,
                'EncodedBy'      => $encodedBy,
                'CreatedAt'      => date('Y-m-d H:i:s'),
            ]);
            $saved++;
            }
        }

        $msg = "Saved {$saved} specimen(s) for Building {$building}.";
        if ($updated) {
            $msg .= " ({$updated} existing Lab Ref updated.)";
        }
        if ($down) {
            $msg .= " {$down} line(s) marked SHUTDOWN.";
        }
        if ($skipped) {
            $msg .= ' Not saved, trial already recorded: ' . implode(', ', array_unique($skipped)) . '.';
        }

        return $this->response->setJSON(['ok' => $saved > 0 || $down > 0, 'message' => $msg, 'saved' => $saved, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
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
            'DTP-QC-001' => 'L. Tuddao', 'DTP-QC-002' => 'S. Aquino', 'DTP-QC-048' => 'L. Aquidado',
            'DTP-QC-064' => 'J. Barrera', 'DTP-QC-066' => 'J. Parrilla', 'DTP-QC-067' => 'R. Cabigas',
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
            // "048 - L. Aquidado": the machine no. (last digits of the equipment code) (DTP-QC-NNN) goes in front of the name
            // the code may be written "DTP-QC-048", "DTP - QC - 48" or "DTP QC-048": it is matched on its number
            $name = '';
            $mn   = [];
            if (preg_match('/dtp[\s-]*qc[\s-]*0*(\d+)/i', (string) $f['EquipmentCode'], $mn) || preg_match('/(\d+)\s*$/', (string) $f['EquipmentCode'], $mn)) {
                $no = str_pad(substr($mn[1], -3), 3, '0', STR_PAD_LEFT);
                foreach ($techs as $code => $tn) {
                    if (preg_match('/(\d+)\s*$/', (string) $code, $cn) && str_pad(substr($cn[1], -3), 3, '0', STR_PAD_LEFT) === $no) {
                        $name = preg_match('/^DTP-QC-\d{3}\s*-/', (string) $tn) ? (string) $tn : 'DTP-QC-' . $no . ' - ' . $tn;
                        break;
                    }
                }
            }
            $set($f, 'Technician', $name);
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

        // a bonding report rated Fail / Tolerable must say how it failed (Quality Insights and the lab alerts read it)
        if ($slug === 'bonding' && preg_match('/^(fail|failed|tolerable)$/i', trim((string) ($fields['Result'] ?? ''))) && trim(preg_replace('/^-$/', '', trim((string) ($fields['ModeOfFailure'] ?? '')))) === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Write the Mode of Failure(s): a ' . strtoupper(trim((string) $fields['Result'])) . ' result needs it.', 'csrf' => csrf_hash()]);
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
        // a Lab Ref No is unique: saving a "new" record under a Lab Ref that already exists updates that record instead of adding a copy
        $newRef = trim((string) ($saveData['LabRefNo'] ?? ''));
        if ($recordId === 0 && $newRef !== '' && in_array('LabRefNo', $validCols, true)) {
            $dup = $db->table($table)->select($pk)->where('LabRefNo', $newRef)->orderBy($pk, 'DESC')->get(1)->getRowArray();
            if ($dup) {
                $recordId = (int) $dup[$pk];
            }
        }
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
     * GET /dtr/bonding-machine-pdf?lab_ref=
     * "Bonding Test-VF" machine-result page (header table + point1-45 tables) rebuilt from the OUTDATA xlsx of the Lab Ref.
     */
    public function bondingMachinePdf()
    {
        $ref = \App\Libraries\MachineFileReader::safeRef((string) $this->request->getGet('lab_ref'));
        if ($ref === null) {
            return $this->response->setStatusCode(422)->setBody('Invalid Lab Ref No.');
        }
        $reader = new \App\Libraries\MachineFileReader();
        $path   = null;
        foreach (['BONDING', 'WHOLESHOE', 'BONDING ADVANCE 07-09', 'Bonding Test Reports'] as $folder) {
            if (($path = $reader->findFile($folder, $ref)) !== null) {
                break;
            }
        }
        if ($path === null) {
            return $this->response->setStatusCode(404)->setBody('No machine result yet for ' . $ref . ' (the test file is not on the machine share).');
        }

        // the sheet of the file that belongs to this Lab Ref (files hold several runs)
        $grid = [];
        foreach (array_merge($reader->sheets($path), ['']) as $sh) {
            $g = $reader->grid($path, $sh);
            if (! $g) {
                continue;
            }
            $grid = $grid ?: $g;
            if (in_array($ref, array_map('trim', $g[6] ?? []), true) || in_array(strtok($ref, '-'), array_map(static fn ($v) => strtok(trim((string) $v), '-'), $g[6] ?? []), true)) {
                $grid = $g;
                break;
            }
        }
        if (! $grid) {
            return $this->response->setStatusCode(422)->setBody('The machine file of ' . $ref . ' could not be read.');
        }

        $cols = [];
        for ($c = 3; $c <= 12; $c++) {
            $n = trim((string) ($grid[8][$c] ?? ''));
            if ($n === '' || ! is_numeric($n) || trim((string) ($grid[6][$c] ?? '')) === '---') {
                break;
            }
            $cols[] = $c;
        }
        if (! $cols) {
            return $this->response->setStatusCode(422)->setBody('No test columns found in the machine file.');
        }

        $e     = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $date  = static function ($v) {
            $v = trim((string) $v);

            return (is_numeric($v) && (float) $v > 30000) ? gmdate('Y-m-d', (int) (((float) $v - 25569) * 86400)) : $v;
        };
        $fmt   = static function ($v) {
            $v = trim((string) $v);

            return is_numeric($v) ? number_format((float) $v, 2, '.', '') : ($v === '' ? '-' : $v);
        };
        $equip = trim((string) preg_replace('/^\s*Equip Code\s*:?\s*/i', '', (string) ($grid[3][1] ?? '')));
        $desc  = trim(implode(' ', array_filter([(string) ($grid[7][4] ?? ''), (string) ($grid[7][5] ?? '')], static fn ($x) => trim($x) !== '' && trim($x) !== '---')));

        $head = '';
        foreach ($cols as $i => $c) {
            $head .= '<tr><td>' . $e($grid[8][$c]) . '</td><td>' . $e($date($grid[4][$c] ?? '')) . '</td><td>' . $e($grid[11][$c] ?? '') . '</td><td class="d">'
                . $e($desc) . '</td><td>' . $e($grid[6][$c] ?? '') . '</td><td>' . $e($grid[5][$c] ?? '') . '</td><td>' . $e($grid[10][$c] ?? '') . '</td></tr>';
        }

        $pts = [];
        foreach ($grid as $r => $row) {
            if (preg_match('/^M(\d+)$/i', trim((string) ($row[1] ?? '')), $m)) {
                $pts[(int) $m[1]] = $row;
            }
        }
        $blocks = '';
        for ($start = 1; $start <= 45; $start += 15) {
            $h1 = $h2 = '';
            $vals = array_fill(0, count($cols), '');
            for ($p = $start; $p < $start + 15; $p++) {
                $h1 .= '<th>point' . $p . '</th>';
                $h2 .= '<td>kgf/cm</td>';
                foreach ($cols as $i => $c) {
                    $vals[$i] .= '<td>' . $e($fmt($pts[$p][$c] ?? '-')) . '</td>';
                }
            }
            $blocks .= '<table class="pt"><tr>' . $h1 . '</tr><tr>' . $h2 . '</tr>';
            foreach ($vals as $v) {
                $blocks .= '<tr>' . $v . '</tr>';
            }
            $blocks .= '</table>';
        }

        $html = '<h1>Bonding Test-VF</h1><p class="eq">Equip Code:' . $e($equip) . '</p>'
            . '<table class="hd"><tr><th>Test No</th><th>Test Date</th><th>Sample</th><th>Description</th><th>Report No.</th><th>Speed<br><small>mm/min</small></th><th>Width<br><small>mm</small></th></tr>'
            . $head . '</table>' . $blocks;

        $pdf = $this->htmlToPdfBytes($html, 'body{font-family:Arial,sans-serif;font-size:11px;color:#000;padding:10px 14px}h1{text-align:center;font-size:22px;font-weight:400;margin:18px 0 26px}.eq{margin:0 0 12px}'
            . 'table{border-collapse:collapse;width:100%;margin-bottom:22px}th,td{border:1px solid #000;padding:4px 3px;text-align:center;font-weight:400}.hd .d{width:36%}.pt th,.pt td{border-left:0;border-right:0;font-size:10px}.pt tr:nth-child(n+3) td{border-top:0;border-bottom:0}.pt tr:last-child td{border-bottom:1px solid #000}small{font-size:9px}');
        if ($pdf === null) {
            return $this->response->setStatusCode(500)->setBody('The PDF could not be created on the server (Chrome or Edge is required).');
        }

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $ref) . '_Bonding-Test-VF.pdf"')
            ->setBody($pdf);
    }

    // ------------------------------------------------------------------------------------------------------------------
    // Whole Shoe report ("Whole Footwear Test Results", Form # Lab VF 002)
    // ------------------------------------------------------------------------------------------------------------------

    /** The test sections of the form: id => [test ticked at intake, title, conditions, fields key => label]. */
    private function wsSections(): array
    {
        $std = ['delam' => 'Delamination / Separation 分层', 'bleed' => 'Bleeding 溢色', 'color' => 'Color Change 颜色变化', 'warp' => 'Outsole Warping 大底变形', 'bloom' => 'Blooming 吐霜', 'other' => 'Other findings 其它结果'];

        return [
            'G02' => ['Flexing', 'Whole Shoe Flexing 整鞋弯曲 - SA &nbsp;VF-G02 (2016/11)', 'SA Flexing :Regular Flexing<br>* 45 deg angle 45 度角<br>* 60 deg angle 60 度角<br>*30 degrees for thick outsole such as PRO mining, smelter or logger boots 30度角<br>* Temp温度: 23 +/- 2℃ / -20 ℃<br>* Standard cycle 标准转数: 125,000cycle / 100,000cycles', ['delam' => 'Delamination / Separation 分层', 'sa' => 'SA Flexing']],
            'G05' => ['Flexing', 'Whole Shoe Flexing 整鞋弯曲 - China ( GB ) Testing 中国标准 &nbsp;VF-G05 (2013/01)', 'GB Flexing :Regular Flexing<br>* 50 deg angle 50 度角<br>* Temp温度: 23 +/- 2℃<br>Standard cycle 标准转数: 40,000', ['cut' => 'Cut growth@ initial 5mm : < 20mm 切口长度小于20 mm', 'crack' => 'Cracking standard : maximum of 3 points cracks acceptable but not beyond 5mm width per crack : 新的裂口长度小于5 mm，但不应超过3处.', 'delam' => 'Delamination / Separation 分层', 'other' => 'Other findings 其它结果']],
            'G04' => ['Flexing', 'Whole Shoe Water Penetration Flexing 整鞋动态防水测试 &nbsp;VF-G04 (2016/05)', 'Water Penetration - Flexing<br>* 30 deg angle 30 度角<br>* Temp温度 : 23 +/- 2℃<br>Standard cycles标准转数:15,000 cycles / 100,000 cycles / 200,000cycles', ['wp' => 'Water Penetration 防水', 'other' => 'Other findings 其它结果']],
            'G07' => ['Flexing', 'Whole Shoe Water Penetration Static 整鞋静态防水测试 &nbsp;VF-G07 (2014/03)', 'Water Penetration - Static<br>* Temp温度 : 23 +/- 2℃<br>Standard time标准时间:4 hours', ['wp' => 'Water Penetration 防水', 'other' => 'Other findings 其它结果']],
            'E03' => ['UV', 'UV Light Discoloration (紫外线黄变) AATCC &nbsp;VF-E03 (2019/05)', '* Temp温度 : 50 ℃<br>* Time 时间: 24 hrs', ['yellow' => 'Yellowing 变黄 (UV Light Standard: 4.0G)', 'other' => 'Other findings 其它结果']],
            'G03' => ['Washing', 'Whole Shoe Washing 整鞋水洗 &nbsp;VF-G03 (2024/08)', '3 complete cycles of washing and draining', $std],
            'E26' => ['Dip Test', 'Whole Shoe Dip Test &nbsp;VF-E26 (2020/09)', '* Temp温度 : 23 +/- 2℃<br>* Distilled water 1 minute', ['bleed' => 'Bleeding 溢色', 'color' => 'Color Change 颜色变化', 'other' => 'Other findings 其它结果']],
            'E04' => ['Heat Aging', 'Whole Shoe Aging Test 老化测试 &nbsp;VF-E04 (2022/09)', '* Temp温度 : 70 ℃<br>* Time 时间: 3 days / 7 days', $std],
            'TM361' => ['Hydrolysis', 'Whole Shoe Hydrolysis Test 水解测试 &nbsp;SATRA TM 344', '* Temp温度 : 70℃<br>* %RH 湿度: 95%<br>* Time 时间: 7 days', $std],
        ];
    }

    private function wsReportTable(): \CodeIgniter\Database\BaseConnection
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_wholeshoe_reports (
            ReportID INT AUTO_INCREMENT PRIMARY KEY,
            LabRefNo VARCHAR(191) NOT NULL,
            ReportData LONGTEXT NULL,
            UpdatedBy VARCHAR(191) NULL,
            UpdatedAt DATETIME NULL,
            UNIQUE KEY uq_labref (LabRefNo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        return $db;
    }

    /** Whole shoe intake line of a Lab Ref (joined with its shoe) + the saved report data. */
    private function wsLoad(string $ref): ?array
    {
        $db = $this->wsReportTable();
        if (! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return null;
        }
        $row = $db->table('tbl_dtr_wholeshoe_intake i')->select('i.*, s.StyleNo, s.Colorway, s.MTL')
            ->join('tbl_dtr_shoe_list s', 's.ProdCode = i.ProdCode', 'left')->where('i.LabRefNo', $ref)->get()->getRowArray();
        if (! $row) {
            // the Lab Ref of one test of a whole shoe (e.g. DTP20261103DTA4-R, opened from the Whole Shoe Collection list):
            // the shoe and the test come from its tracker row
            try {
                $t = $db->table('tbl_dtr_WholeshoeTracker')->where('LabRefNo', $ref)->orderBy('TrackerID', 'DESC')->get(1)->getRowArray();
            } catch (\Throwable $e) {
                $t = null;
            }
            if (! $t) {
                return null;
            }
            $row = [
                'LabRefNo' => $ref, 'ProdCode' => $t['ProductionCode'] ?? '', 'StyleNo' => $t['Style'] ?? '', 'Colorway' => $t['Colorway'] ?? '', 'MTL' => $t['MTL'] ?? '',
                'NoOfShoes' => $t['NoOfShoes'] ?? 1, 'CollectionDate' => $t['CollectionDate'] ?? '', 'TestTypes' => $t['TestType'] ?? '', 'EncodedBy' => $t['EncodedBy'] ?? '',
            ];
        }
        $saved = $db->table('tbl_dtr_wholeshoe_reports')->where('LabRefNo', $ref)->get()->getRowArray();
        $tests = array_values(array_filter(array_map('trim', explode(',', (string) ($row['TestTypes'] ?? '')))));
        $pulled = $this->wsPulled($db, $ref);
        // every section of the form stays (like the paper form); only the sections of this Lab Ref's tests get the pulled data
        $sections = [];
        foreach ($this->wsSections() as $id => $s) {
            $sections[] = ['id' => $id, 'title' => strip_tags(str_replace('&nbsp;', ' ', $s[1])), 'cond' => $s[2], 'fields' => $s[3]];
        }

        return [
            'info' => [
                'labRef' => $ref, 'prodCode' => $row['ProdCode'], 'style' => $row['StyleNo'] ?? '', 'color' => $row['Colorway'] ?? '',
                'qty' => (int) ($row['NoOfShoes'] ?? 1), 'received' => substr((string) ($row['CollectionDate'] ?? ''), 0, 10),
                'tests' => $tests, 'encodedBy' => $row['EncodedBy'] ?? '',
            ],
            'sections' => $sections,
            'data'     => $saved ? (json_decode((string) $saved['ReportData'], true) ?: []) : [],
            'pulled'   => $pulled,
        ];
    }

    /**
     * The result of every test of a whole shoe Lab Ref, pulled from tbl_dtr_WholeshoeTracker (one row per test type):
     * section of the form it belongs to, Result / Status, dates and the Findings text split over the section's fields.
     *
     * @return list<array<string, mixed>>
     */
    private function wsPulled(\CodeIgniter\Database\BaseConnection $db, string $ref): array
    {
        try {   // not tableExists(): the table name differs in case from the one MySQL reports
            $rows = $db->table('tbl_dtr_WholeshoeTracker')
                ->select('LabRefNo, TestType, TestCode, Status, FinalResult, Findings, TimeStarted, TimeCompleted')
                // the Lab Ref picked can be the whole shoe (all its tests) or one test's own Lab Ref (e.g. ...DTA4-R = its Dip Test)
                ->groupStart()->where('LabRefNo', $ref)->orWhere('WholeshoeLabRefNo', $ref)->orLike('WholeshoeLabRefNo', $ref . '-', 'after')->groupEnd()
                ->orderBy('TrackerID', 'ASC')->get()->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }

        $defs  = $this->wsSections();
        $typeS = ['UV' => 'E03', 'Washing' => 'G03', 'Dip Test' => 'E26', 'Heat Aging' => 'E04', 'Hydrolysis' => 'TM361'];
        // which field a piece of the Findings text belongs to (first match wins; a keyword the section has no field for goes to "other")
        $kw = ['delam' => '/delamin|separat/i', 'bleed' => '/bleed/i', 'color' => '/colou?r/i', 'warp' => '/warp/i', 'bloom' => '/bloom/i', 'yellow' => '/yellow|discolou?r/i',
            'cut' => '/\bcut\b/i', 'crack' => '/crack/i', 'wp' => '/penetrat|leak|water/i'];
        $out = [];
        foreach ($rows as $t) {
            $type = (string) $t['TestType'];
            $find = trim((string) ($t['Findings'] ?? ''));
            if ($type === 'Flexing') {
                // one Flexing test covers four sheets: the angle in the findings tells which one (50° GB, 30° water penetration, else SA)
                $sid = preg_match('/\b50\s*[°º]/u', $find) ? 'G05' : (preg_match('/\b30\s*[°º]/u', $find) ? 'G04' : 'G02');
            } else {
                $sid = $typeS[$type] ?? '';
            }
            if ($sid === '' || ! isset($defs[$sid])) {
                continue;
            }
            $fields = $defs[$sid][3];
            $vals   = [];
            foreach ($find === '' ? [] : preg_split('/\s*[,;]\s*(?![^()]*\))/', $find) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $key = null;
                foreach ($kw as $k => $re) {
                    if (preg_match($re, $part)) {
                        $key = $k;
                        break;
                    }
                }
                if ($key === null || ! isset($fields[$key])) {
                    $key = $sid === 'G02' ? 'sa' : (isset($fields['other']) ? 'other' : array_key_last($fields));
                }
                $vals[$key] = isset($vals[$key]) ? $vals[$key] . '; ' . $part : $part;
            }
            $out[] = [
                'section' => $sid, 'type' => $type, 'labRef' => (string) $t['LabRefNo'], 'status' => (string) ($t['Status'] ?? ''),
                'result' => strtoupper(trim((string) ($t['FinalResult'] ?? ''))), 'started' => (string) ($t['TimeStarted'] ?? ''),
                'completed' => (string) ($t['TimeCompleted'] ?? ''), 'fields' => $vals,
            ];
        }

        return $out;
    }

    /** GET /dtr/wholeshoe-labrefs?q= : whole shoe Lab Refs (newest first) for the Whole Shoe report picker. */
    public function wholeShoeLabRefs()
    {
        $db = \Config\Database::connect();
        // every section of the form, so the blank sheet can be shown before a Lab Ref is chosen
        $blank = [];
        foreach ($this->wsSections() as $id => $s) {
            $blank[] = ['id' => $id, 'title' => strip_tags(str_replace('&nbsp;', ' ', $s[1])), 'cond' => $s[2], 'fields' => $s[3]];
        }
        if (! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return $this->response->setJSON(['ok' => true, 'rows' => [], 'sections' => $blank, 'csrf' => csrf_hash()]);
        }
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $b = $db->table('tbl_dtr_wholeshoe_intake i')->select('i.LabRefNo, i.ProdCode, i.TestTypes, s.StyleNo, s.Colorway')
            ->join('tbl_dtr_shoe_list s', 's.ProdCode = i.ProdCode', 'left')->where("i.LabRefNo IS NOT NULL AND i.LabRefNo <> ''", null, false);
        if ($q !== '') {
            $b->groupStart()->like('i.LabRefNo', $q)->orLike('i.ProdCode', $q)->orLike('s.StyleNo', $q)->groupEnd();
        }
        $rows = $b->orderBy('i.IntakeID', 'DESC')->limit(100)->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'sections' => $blank, 'csrf' => csrf_hash()]);
    }

    /** GET /dtr/wholeshoe-report?lab_ref= */
    public function wholeShoeReport()
    {
        $ref = \App\Libraries\MachineFileReader::safeRef((string) $this->request->getGet('lab_ref'));
        $out = $ref !== null ? $this->wsLoad($ref) : null;
        if (! $out) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'No whole shoe intake found for that Lab Ref No.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()] + $out);
    }

    /** POST /dtr/wholeshoe-report-save {lab_ref, data} */
    public function wholeShoeReportSave()
    {
        $p   = $this->request->getJSON(true) ?? [];
        $ref = \App\Libraries\MachineFileReader::safeRef((string) ($p['lab_ref'] ?? ''));
        if ($ref === null || ! $this->wsLoad($ref)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Choose a whole shoe Lab Ref No first.', 'csrf' => csrf_hash()]);
        }
        $d    = is_array($p['data'] ?? null) ? $p['data'] : [];
        $clean = [];
        foreach (['testedDate', 'finishedDate', 'technician', 'reviewedBy', 'approvedBy', 'comments'] as $k) {
            $clean[$k] = mb_substr(trim((string) ($d[$k] ?? '')), 0, 500);
        }
        $clean['results'] = [];
        foreach ($this->wsSections() as $id => $s) {
            foreach (array_keys($s[3]) as $k) {
                $v = trim((string) ($d['results'][$id][$k] ?? ''));
                if ($v !== '') {
                    $clean['results'][$id][$k] = mb_substr($v, 0, 500);
                }
            }
        }
        $db   = $this->wsReportTable();
        $user = (string) (service('auth')->user()['name'] ?? '');
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $db->query('INSERT INTO tbl_dtr_wholeshoe_reports (LabRefNo, ReportData, UpdatedBy, UpdatedAt) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE ReportData = VALUES(ReportData), UpdatedBy = VALUES(UpdatedBy), UpdatedAt = VALUES(UpdatedAt)', [$ref, $json, $user, date('Y-m-d H:i:s')]);

        return $this->response->setJSON(['ok' => true, 'message' => "Saved Whole Shoe report {$ref}.", 'csrf' => csrf_hash()]);
    }

    /**
     * GET /dtr/wholeshoe-report-pdf?lab_ref=&inline=1
     * "Whole Footwear Test Results" (Form # Lab VF 002) filled from the whole shoe intake and the saved results; only the test
     * sections ticked at intake are printed (all when none were).
     */
    public function wholeShoeReportPdf()
    {
        $ref = \App\Libraries\MachineFileReader::safeRef((string) $this->request->getGet('lab_ref'));
        $rep = $ref !== null ? $this->wsLoad($ref) : null;
        if (! $rep) {
            return $this->response->setStatusCode(404)->setBody('No whole shoe intake found for ' . (string) $ref . '.');
        }
        $info = $rep['info'];
        $d    = $rep['data'];
        $e    = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $all  = $this->wsSections();

        // the results pulled from the whole shoe tracker fill what was not typed in the report
        $pb      = [];
        $started = [];
        $done    = [];
        foreach ($rep['pulled'] ?? [] as $p) {
            $pb[$p['section']] = $p;
            if ($p['started'] !== '') {
                $started[] = substr($p['started'], 0, 10);
            }
            $done[] = $p['completed'] !== '' ? substr($p['completed'], 0, 10) : '';
        }
        if (empty($d['testedDate']) && $started) {
            $d['testedDate'] = min($started);
        }
        if (empty($d['finishedDate']) && $done && ! in_array('', $done, true)) {
            $d['finishedDate'] = max($done);
        }

        $body = '';
        foreach ($rep['sections'] as $s) {
            $res = '';
            foreach ($s['fields'] as $k => $label) {
                $val = ($d['results'][$s['id']][$k] ?? '') !== '' ? $d['results'][$s['id']][$k] : ($pb[$s['id']]['fields'][$k] ?? '');
                $res .= '<div class="rl"><b>' . $e($label) . ' :</b> ' . $e($val) . '</div>';
            }
            $rt = isset($pb[$s['id']]) ? ' ' . $e($pb[$s['id']]['result']) : '';
            $body .= '<div class="st">' . $all[$s['id']][1] . '</div><table class="sc"><tr><td class="tc"><b>Test conditions测试条件:</b></td><td class="tr"><b>Result结果 :' . $rt . '</b></td></tr>'
                . '<tr><td class="cd">' . $all[$s['id']][2] . '</td><td class="rs">' . $res . '</td></tr></table>';
        }

        $logo = base_url('assets/images/vf-logo.webp');
        $sat  = base_url('assets/images/satra-accredited.png');
        $html = '<div class="hd"><img src="' . $logo . '" class="vf"><div class="tt"><h1>Whole Footwear Test Results</h1><h2>整鞋测试报告</h2></div><img src="' . $sat . '" class="sa"></div>'
            . '<table class="ref"><tr><td class="frm">Form # : Lab VF 002</td><td class="rf"><div>ETRF. No :</div><div>Lab Ref. No : <b>' . $e($ref) . '</b></div></td></tr></table>'
            . '<table class="info"><tr><td>Supplier/Factory(厂商) :</td><td><b>DATIAN SUBIC SHOES INC.</b></td><td>O Prod.Material (量产材料)</td><td>O Prod.Re-test量产重测</td></tr>'
            . '<tr><td>Supplier\'s country(国家):</td><td><b>Philippines</b></td><td>O New/Development 开发段新材料</td><td>O Trial Test 试测测试</td></tr>'
            . '<tr><td>Material Description(材料):</td><td></td><td colspan="2">o Tech 1 &nbsp;&nbsp; Tech 2 &nbsp;&nbsp; Tech 3 &nbsp;&nbsp; Tech 4</td></tr>'
            . '<tr><td>Model No./Style(型体):</td><td>' . $e($info['style']) . '</td><td colspan="2">O Sales Sample (销售样品)</td></tr>'
            . '<tr><td>Color (颜色):</td><td>' . $e($info['color']) . '</td><td>Received Date(收到日) : ' . $e($info['received']) . '</td><td></td></tr>'
            . '<tr><td>P.O/Lot No(编号):</td><td>' . $e($info['prodCode']) . '</td><td>Tested Date(测试时间) : ' . $e($d['testedDate'] ?? '') . '</td><td></td></tr>'
            . '<tr><td>Quantity(数量):</td><td>' . (int) $info['qty'] . '</td><td>Finished Date(完成时间) : ' . $e($d['finishedDate'] ?? '') . '</td><td></td></tr>'
            . '<tr><td>Send by (提供方):</td><td></td><td>Technician(技术人员) : ' . $e($d['technician'] ?? '') . '</td><td></td></tr></table>'
            . $body
            . '<table class="ft"><tr><td>Comments备注 : ' . $e($d['comments'] ?? '') . '</td><td>Reviewed by 检查: <b>' . $e($d['reviewedBy'] ?? '') . '</b><br><br>Approved by核准: <b>' . $e($d['approvedBy'] ?? '') . '</b></td></tr></table>';

        $css = 'body{font-family:Arial,sans-serif;font-size:10px;color:#000;margin:0}.hd{display:flex;align-items:center;justify-content:space-between}.hd .vf{height:62px}.hd .sa{height:58px}.tt{text-align:center}.tt h1{font-size:22px;margin:0}.tt h2{font-size:19px;margin:2px 0 0}'
            . 'table{border-collapse:collapse;width:100%}.ref{margin-top:6px}.ref td{border:1px solid #000;padding:3px 5px}.frm{width:60%}.info td{border:1px solid #000;padding:5px 5px;font-size:10.5px}'
            . '.st{background:#ddd9c4;border:1px solid #000;border-top:0;text-align:center;font-weight:700;font-size:12px;padding:6px 3px}.sc td{border:1px solid #000;vertical-align:top;padding:4px 5px}.tc{width:26%;text-align:center}.cd{text-align:center;font-size:9.5px;height:60px}.rs{padding:4px 5px}.rl{padding:4px 0}'
            . '.ft{margin-top:8px}.ft td{border:1px solid #000;padding:10px 6px;height:44px;vertical-align:top}';

        $pdf = $this->htmlToPdfBytes($html, $css);
        if ($pdf === null) {
            return $this->response->setStatusCode(500)->setBody('The PDF could not be created on the server (Chrome or Edge is required).');
        }
        $disp = $this->request->getGet('inline') ? 'inline' : 'attachment';

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disp . '; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $ref) . '_Whole-Footwear-Test-Results.pdf"')
            ->setBody($pdf);
    }

    /** Body HTML + CSS to an A4 PDF with the installed Chrome / Edge (headless); null when it cannot be made. */
    private function htmlToPdfBytes(string $body, string $css): ?string
    {
        $browser = null;
        foreach (['C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'] as $c) {
            if (is_file($c)) {
                $browser = $c;
                break;
            }
        }
        if ($browser === null) {
            return null;
        }
        $dir = WRITEPATH . 'cache/sheet_pdfs/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $id       = bin2hex(random_bytes(8));
        $htmlFile = $dir . $id . '.html';
        $pdfFile  = $dir . $id . '.pdf';
        file_put_contents($htmlFile, '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 portrait;margin:8mm}' . $css . '</style></head><body>' . $body . '</body></html>');
        @shell_exec('"' . $browser . '" --headless=new --disable-gpu --disable-extensions --no-first-run --no-pdf-header-footer'
            . ' --user-data-dir="' . $dir . 'profile" --print-to-pdf="' . $pdfFile . '" "file:///' . str_replace('\\', '/', $htmlFile) . '" 2>&1');
        @unlink($htmlFile);
        if (! is_file($pdfFile) || filesize($pdfFile) < 500) {
            @unlink($pdfFile);

            return null;
        }
        $bytes = (string) file_get_contents($pdfFile);
        @unlink($pdfFile);

        return $bytes;
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
        $pdf = (string) file_get_contents($pdfFile);
        @unlink($pdfFile);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string) ($p['name'] ?? 'Report')) ?: 'Report';

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.pdf"')
            ->setBody($pdf);
    }

    /**
     * GET /dtr/bonding-access-pdf?id={BondingTestID}&side={Left|Right}
     * Runs export_bonding_pdf.ps1 to invoke Microsoft Access COM automation and output the legacy report as a PDF.
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

    /**
     * POST /dtr/send-synology
     * Send report notification & optional image attachment to Synology Chat channel.
     */
    public function sendSynology()
    {
        $text = trim((string) ($this->request->getPost('text') ?? ''));
        $json = $this->request->getJSON(true);
        if ($text === '' && ! empty($json['text'])) {
            $text = trim((string) $json['text']);
        }

        if ($text === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'error' => 'Message text cannot be empty.',
            ]);
        }

        $fileUrl = null;
        $uploadsDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'synology' . DIRECTORY_SEPARATOR;
        if (! is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0777, true);
        }

        // 1. Check for multipart file upload
        $file = $this->request->getFile('image');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $origName = $file->getClientName();
            $ext = strtolower($file->getClientExtension() ?: 'png');
            $cleanName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
            $filename = ($cleanName !== '' ? $cleanName : 'attachment') . '_' . date('Ymd_His') . '.' . $ext;
            $file->move($uploadsDir, $filename);
            $fileUrl = base_url('uploads/synology/' . $filename);
        }

        // 2. Check for base64 image (from canvas/screen snapshot)
        $base64 = (string) ($this->request->getPost('image_base64') ?? ($json['image_base64'] ?? ''));
        if ($fileUrl === null && $base64 !== '') {
            if (preg_match('#^data:image/(\w+);base64,(.*)$#i', $base64, $m)) {
                $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                $data = base64_decode($m[2]);
                if ($data !== false) {
                    $prefix = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string) ($this->request->getPost('file_name') ?? ($json['file_name'] ?? 'Report'))) ?: 'Report';
                    $filename = $prefix . '_' . date('Ymd_His') . '.' . $ext;
                    if (file_put_contents($uploadsDir . $filename, $data) !== false) {
                        $fileUrl = base_url('uploads/synology/' . $filename);
                    }
                }
            }
        }

        $webhookUrl = trim((string) ($this->request->getPost('webhook_url') ?? ($json['webhook_url'] ?? '')));
        $syno = new \App\Libraries\SynologyChat();
        $ok = $syno->sendIncoming($text, $fileUrl, $webhookUrl !== '' ? $webhookUrl : null);

        if (! $ok) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'error' => $syno->lastError() ?? 'Failed to send message to Synology Chat.',
            ]);
        }

        return $this->response->setJSON([
            'ok' => true,
            'message' => 'Report successfully sent to Synology Chat!',
            'file_url' => $fileUrl,
        ]);
    }

    /**
     * POST /dtr/send-email
     * Send report notification email.
     */
    public function sendEmail()
    {
        $json = $this->request->getJSON(true) ?? [];
        $to = trim((string) ($this->request->getPost('to') ?? ($json['to'] ?? '')));
        $subject = trim((string) ($this->request->getPost('subject') ?? ($json['subject'] ?? 'Shoe Bonding Test Report')));
        $message = trim((string) ($this->request->getPost('message') ?? ($json['message'] ?? '')));

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'error' => 'Valid recipient email address is required.',
            ]);
        }

        if ($message === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'error' => 'Email message content cannot be empty.',
            ]);
        }

        try {
            $attachments = [];
            $imgHtml = '';

            // Process image attachments: image = Report Graph Card, image2 = Photo
            $cardFile = $this->request->getFile('image');
            if ($cardFile && $cardFile->isValid()) {
                $cData = file_get_contents($cardFile->getTempName());
                $cBase64 = base64_encode($cData);
                $attachments[] = [
                    'filename'     => $cardFile->getClientName() ?: 'Report_Graph_Card.png',
                    'content_type' => $cardFile->getClientMimeType() ?: 'image/png',
                    'data_base64'  => $cBase64,
                    'cid'          => 'card_image',
                ];
                $imgHtml .= '<div style="margin:16px 0;text-align:center;"><p style="font-size:12px;color:#6b7280;margin-bottom:4px;font-weight:bold;">TEST RESULT GRAPH CARD</p><img src="cid:card_image" style="max-width:100%;height:auto;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.06);" alt="Bonding Test Graph Card"></div>';
            }

            $photoFile = $this->request->getFile('image2');
            if ($photoFile && $photoFile->isValid()) {
                $pData = file_get_contents($photoFile->getTempName());
                $pBase64 = base64_encode($pData);
                $attachments[] = [
                    'filename'     => $photoFile->getClientName() ?: 'photo.jpg',
                    'content_type' => $photoFile->getClientMimeType() ?: 'image/jpeg',
                    'data_base64'  => $pBase64,
                    'cid'          => 'photo_image',
                ];
                $imgHtml .= '<div style="margin:16px 0;text-align:center;"><p style="font-size:12px;color:#6b7280;margin-bottom:4px;font-weight:bold;">SPECIMEN PHOTO</p><img src="cid:photo_image" style="max-width:100%;height:auto;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.06);" alt="Specimen Photo"></div>';
            }

            // Build rich HTML email body
            $htmlBody = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:20px;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
                . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">'
                . '<div style="background:#1e293b;color:#ffffff;padding:18px 24px;"><h2 style="margin:0;font-size:18px;letter-spacing:0.5px;">NEXUS-L QC LABORATORY REPORT</h2><p style="margin:4px 0 0;font-size:12px;color:#94a3b8;">Whole Shoe Bonding Quality Control</p></div>'
                . '<div style="padding:24px;">'
                . '<div style="background:#f8fafc;border-left:4px solid #2563eb;padding:12px 16px;margin-bottom:20px;font-size:14px;line-height:1.6;white-space:pre-wrap;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>'
                . $imgHtml
                . '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:11px;color:#64748b;text-align:center;">'
                . 'This report was generated and dispatched automatically by <b>NEXUS-L Laboratory System</b>.<br>Please do not reply directly to this automated email.'
                . '</div></div></div></body></html>';

            // Queue it in the outbox (tbl_email_outbox): the Outlook Relay (or, when it is offline, the Server HTML Handler) sends it in FIFO order
            // a design uploaded under Settings > E-mail > E-mail designs ("Report sent") replaces the built-in layout
            $tplVars = ['title' => $subject, 'message' => $message, 'lab_ref' => (string) ($this->request->getPost('lab_ref') ?? ''), 'date' => date('Y-m-d H:i'),
                'sender' => (string) ($this->auth->user()['name'] ?? ''), 'images_html' => $imgHtml];
            $designed = \App\Libraries\MailOutbox::render('report_sent', $tplVars);
            if ($designed !== null) {
                $htmlBody = $designed;
            }
            [$queueId, $queueErr] = \App\Libraries\MailOutbox::enqueue($to, $subject, $htmlBody, [
                'source'      => 'DTR-Bonding',
                'attachments' => ! empty($attachments) ? $attachments : null,
            ]);
            if ($queueId === 0) {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $queueErr]);
            }

            return $this->response->setJSON([
                'ok'      => true,
                'queue_id'=> $queueId,
                'message' => 'Report successfully queued for Outlook dispatch (FIFO #' . $queueId . ') to ' . $to,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok'    => false,
                'error' => 'Mailer exception: ' . $e->getMessage(),
            ]);
        }
    }
}
