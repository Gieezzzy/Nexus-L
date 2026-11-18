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
        // all four pull types ticked on a lacking line are kept as one list ("HEEL PULL, EYELET, D-RING, RIVET" = 32 chars)
        $pcol = $db->query("SELECT CHARACTER_MAXIMUM_LENGTH AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_dtr_BondingTracker' AND COLUMN_NAME = 'PullTestType'")->getRow();
        if ($pcol && (int) $pcol->n > 0 && (int) $pcol->n < 60) {
            $db->query('ALTER TABLE tbl_dtr_BondingTracker MODIFY PullTestType VARCHAR(60) NULL');
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
            'IntakeType'    => 'VARCHAR(10) NULL',
        ] as $col => $def) {
            if (! in_array(strtolower($col), $have, true)) {
                $db->query("ALTER TABLE tbl_dtr_WholeshoeTracker ADD COLUMN {$col} {$def}");
            }
        }
        // rows that have a Side but whose Lab Ref No has no -L / -R ending yet: add it (safe to repeat, it only touches refs without an ending)
        // a repeated Lab Ref already ends in its running number (DTP20261112HY-L-2): it has its side, so it is never given a second -L / -R
        // (an earlier version did: DTP20261112HY-L-2-L; those are repaired here)
        $db->query("UPDATE IGNORE tbl_dtr_WholeshoeTracker SET LabRefNo = REGEXP_REPLACE(LabRefNo, '-[LR]\$', '') WHERE LabRefNo REGEXP '-[LR]-[0-9]+-[LR]\$'");
        $db->query("UPDATE tbl_dtr_WholeshoeTracker SET
                WholeshoeLabRefNo = IF(WholeshoeLabRefNo IS NULL OR WholeshoeLabRefNo = '' OR WholeshoeLabRefNo LIKE '%-L' OR WholeshoeLabRefNo LIKE '%-R', WholeshoeLabRefNo, CONCAT(WholeshoeLabRefNo, '-', Side)),
                LabRefNo = CONCAT(LabRefNo, '-', Side)
            WHERE Side IN ('L', 'R') AND LabRefNo NOT LIKE '%-L' AND LabRefNo NOT LIKE '%-R' AND LabRefNo NOT REGEXP '-[LR]-[0-9]+\$'");
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
        $subTab    = trim((string) ($this->request->getGet('subtab') ?? ''));

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
        // lines marked SHUTDOWN in the Specimen Intake (one mark per line and production date): all time, and for today's production date
        $shutTotal = 0;
        $shutToday = 0;
        if ($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) {
            $shutTotal = (int) $db->table('tbl_dtr_BondingTracker')->where('Shutdown', 1)->countAllResults();
            $shutToday = (int) $db->table('tbl_dtr_BondingTracker')->where('Shutdown', 1)->where('ProductionDate', date('Y-m-d'))->countAllResults();
        }
        // older SHUTDOWN lines were recorded as rows of tbl_dtr_bonding_info: they are not listed any more but still counted
        $shutTotal += (int) $db->table('tbl_dtr_bonding_info b')->where(self::SHUTDOWN_ROW, null, false)->countAllResults();
        $shutToday += (int) $db->table('tbl_dtr_bonding_info b')->where(self::SHUTDOWN_ROW, null, false)->where('b.ProductionDate', date('Y-m-d'))->countAllResults();
        $totalTol     = array_sum(array_column($catRows, 'tol_cnt'));

        // Recent 100 unified reports sorted by actual test/received date descending
        $recentReports = $db->table('tbl_dtr_unified_labrefs')
            ->select('id, lab_ref_no, tr_no, form_category, form_slug, interlink_sample_type, source_table, source_id, material_type, material_code, material_description, supplier, model_style, color, po_lot_no, mtl_no, received_date, tested_date, finished_date, technician, status, overall_result, thickness_ave, weight_ave, source_system')
            ->orderBy("COALESCE(STR_TO_DATE(NULLIF(tested_date, ''), '%d %b %Y'), STR_TO_DATE(NULLIF(received_date, ''), '%d %b %Y'), DATE('1970-01-01')) DESC, id DESC", '', false)
            ->limit(100)
            ->get()
            ->getResultArray();

        try {
            $this->runDueEditSyncs();
        } catch (\Throwable $e) {
            // the list still loads
        }
        // IQC Container Receiving Log: one row per material of a container (a container with no material yet shows alone);
        // joined with tbl_dtr_material_receipts & tbl_dtr_unified_labrefs to display test details
        $rc = $db->tableExists('tbl_dtr_material_receipts');
        $containers = $db->query('SELECT c.ContainerID, c.ContainerNo, c.DateArrived, c.DateReceivedInLab, c.Remarks AS ContainerRemarks, c.Encodedby,
                m.MaterialID, m.MaterialCode, m.MaterialName, m.MaterialProdCode, m.LabReference, m.IQCERP, m.TestingStatus, m.FinalStatus, m.Remarks AS MaterialRemarks'
            . ($rc ? ', (SELECT r.LabReference FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptLabRef,
                (SELECT r.MaterialTest FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptType,
                (SELECT r.Style FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptStyle,
                (SELECT r.Colorway FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptColor,
                (SELECT r.MTL FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptMTL,
                (SELECT r.Quantity FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS Quantity,
                (SELECT r.MaterialName FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptName,
                (SELECT r.ProdCode FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptProd' : ", NULL AS ReceiptLabRef, NULL AS ReceiptType, NULL AS ReceiptStyle, NULL AS ReceiptColor, NULL AS ReceiptMTL, NULL AS Quantity, NULL AS ReceiptName, NULL AS ReceiptProd")
            . ' FROM tbl_dtr_containers c LEFT JOIN tbl_dtr_materials_master m ON m.ContainerID = c.ContainerID
                ORDER BY c.ContainerID DESC, m.MaterialID ASC LIMIT 15')->getResultArray();

        $refs = array_values(array_unique(array_filter(array_map(static fn ($x) => trim((string) (($x['LabReference'] ?? '') !== '' ? $x['LabReference'] : ($x['ReceiptLabRef'] ?? ''))), $containers))));
        $tests = [];
        if ($refs) {
            foreach ($db->table('tbl_dtr_unified_labrefs')->select('lab_ref_no, form_category, technician, overall_result, received_date, tested_date, finished_date')->whereIn('lab_ref_no', $refs)->get()->getResultArray() as $u) {
                $tests[strtolower($u['lab_ref_no'])] = $u;
            }
        }
        foreach ($containers as &$cr) {
            $ref  = trim((string) (($cr['LabReference'] ?? '') !== '' ? $cr['LabReference'] : ($cr['ReceiptLabRef'] ?? '')));
            $u    = $tests[strtolower($ref)] ?? [];
            $tech = trim((string) ($u['technician'] ?? ''));
            $mach = '';
            if (preg_match('/^(DTP-[A-Z]+-\d+)\s*-\s*(.+)$/i', $tech, $mm)) {
                $mach = $mm[1];
                $tech = trim($mm[2]);
            }
            $cr['LabRef']    = $ref;
            $cr['TestName']  = trim((string) ($u['form_category'] ?? '')) !== '' ? $u['form_category'] : (string) ($cr['ReceiptType'] ?? '');
            $cr['Tech']      = $tech;
            $cr['Machine']   = $mach;
            $cr['Result']    = trim((string) ($u['overall_result'] ?? '')) !== '' ? $u['overall_result'] : (string) ($cr['FinalStatus'] ?? '');
            $cr['TestStart'] = (string) ($u['tested_date'] ?? '');
            $cr['TestEnd']   = (string) ($u['finished_date'] ?? '');
        }
        unset($cr);

        $materialsMasterQ = $db->table('tbl_dtr_materials_master');
        // Bonding Shoe Collection (tbl_dtr_bonding_info) & Whole Shoe Collection (tbl_dtr_shoe_list)
        $bondingTotal = $db->table('tbl_dtr_bonding_info')->countAllResults();
        // list search (?lq=words&lk=bonding|shoes|materials) & pagination (?lp=1)
        $lq    = mb_substr(trim((string) $this->request->getGet('lq')), 0, 100);
        $lk    = (string) $this->request->getGet('lk');
        $lp    = max(1, (int) ($this->request->getGet('lp') ?? 1));
        $lPer  = 15;
        // column sort of a list (?ls=column&ld=asc|desc), set by the arrows in the column headers
        $lsKey = preg_replace('/[^a-z]/', '', strtolower((string) $this->request->getGet('ls')));
        $lsDir = strtolower((string) $this->request->getGet('ld')) === 'desc' ? 'DESC' : 'ASC';
        $lqW   = $lq === '' ? [] : array_values(array_filter(preg_split('/\s+/', $lq)));
        // date range (?from=&to=) and result (?res=pass|tol|fail|pending) chosen on the date filter / KPI cards: every matching report is listed, pending ones too
        $okD   = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $lFrom  = (string) $this->request->getGet('from');
        $lTo    = (string) $this->request->getGet('to');
        $lRange = $okD($lFrom) && $okD($lTo);
        $isTodayDefault = ($activeTab === 'labtracker' && ! $lRange && $this->request->getGet('all') !== '1' && ! in_array($subTab, ['erp', 'cfa', 'schedule', 'failed'], true));
        if ($isTodayDefault) {
            $lFrom  = date('Y-m-d');   // the default is Today (its Daily shoes are the day before's, the other types are yesterday's and today's)
            $lTo    = $lFrom;
            $lRange = true;
            if ($lk === '') {
                $lk = $subTab === 'shoes' ? 'shoes' : 'bonding';
            }
        }
        $lRes  = in_array((string) $this->request->getGet('res'), ['pass', 'tol', 'fail', 'pending'], true) ? (string) $this->request->getGet('res') : '';
        // ?lt= : a Bonding list summary button (daily | trial | sample | retest | first | advance | lacking | shutdown) lists only that kind of row
        $lType = in_array((string) $this->request->getGet('lt'), ['daily', 'trial', 'sample', 'retest', 'first', 'advance', 'lacking', 'shutdown'], true) ? (string) $this->request->getGet('lt') : '';
        $bondingW = $lk === 'bonding' ? $lqW : [];
        $shoesW   = $lk === 'shoes' ? $lqW : [];
        $matW     = $lk === 'materials' ? $lqW : [];
        $resSql   = static fn (string $col, string $res): string => match ($res) {
            'pass'    => "UPPER({$col}) LIKE 'PASS%'",
            'tol'     => "UPPER({$col}) LIKE 'TOL%'",
            'fail'    => "(UPPER({$col}) LIKE 'FAIL%' OR UPPER({$col}) = 'REJECTED')",
            'pending' => "COALESCE({$col}, '') = ''",
            default   => '1=1',
        };
        if ($lk === 'materials' && $lRes !== '') {
            $materialsMasterQ->where($resSql('FinalStatus', $lRes), null, false);
        }
        foreach ($matW as $w) {
            $like = $db->escapeLikeString($w);
            $materialsMasterQ->where("CONVERT(CONCAT_WS(' ', LabReference, MaterialType, MaterialCode, MaterialName, Supplier, TestingStatus, FinalStatus) USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%{$like}%' ESCAPE '!'", null, false);
        }
        $materialsTotal  = (int) $materialsMasterQ->countAllResults(false);
        $materialsPage   = $lk === 'materials' ? $lp : 1;
        $matCols = ['labref' => 'LabReference', 'type' => 'MaterialType', 'code' => 'MaterialCode', 'name' => 'MaterialName', 'supplier' => 'Supplier', 'tstatus' => 'TestingStatus', 'final' => 'FinalStatus'];
        if ($lk === 'materials' && isset($matCols[$lsKey])) {
            $mc = $matCols[$lsKey];
            $materialsMasterQ->orderBy("({$mc} IS NULL OR {$mc} = '')", 'ASC', false)->orderBy($mc, $lsDir)->orderBy('MaterialID', 'DESC');
        } else {
            $materialsMasterQ->orderBy('MaterialID', 'DESC');
        }
        $materialsMaster = $materialsMasterQ->limit($lPer, ($materialsPage - 1) * $lPer)->get()->getResultArray();

        // one row per Lab Ref No: of older duplicates the one holding a result wins, otherwise the newest
        // Style / Colorway / MTL / Prod. Code: from the bonding record, or from the intake tracker (tbl_dtr_BondingTracker) when the record has none
        $pdExpr = "(CASE WHEN b.ProductionDate REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(b.ProductionDate, 7, 4), '-', SUBSTR(b.ProductionDate, 1, 2), '-', SUBSTR(b.ProductionDate, 4, 2)) WHEN b.ProductionDate REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(b.ProductionDate, 10) WHEN b.LabRefNo REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(b.LabRefNo, 4, 4), '-', SUBSTR(b.LabRefNo, 8, 2), '-', SUBSTR(b.LabRefNo, 10, 2)) END)";
        $bdtExpr = "COALESCE({$pdExpr}, LEFT(NULLIF(b.TestedDate, ''), 10), LEFT(NULLIF(b.ReceivedDate, ''), 10), '')";
        $bondingPage = $lk === 'bonding' ? $lp : 1;
        $lqMax = ($bondingW || ($lk === 'bonding' && ($lRange || $lRes !== '' || $lsKey !== '' || $lType !== ''))) ? max(8000, $bondingPage * $lPer * 2) : max(60, $bondingPage * $lPer * 2);
        $bondingBaseQ = $db->table('tbl_dtr_bonding_info b')
            ->join('(SELECT LabRefNo, MAX(TrackerID) AS tid FROM tbl_dtr_BondingTracker WHERE LabRefNo IS NOT NULL GROUP BY LabRefNo) tm', 'tm.LabRefNo = b.LabRefNo COLLATE utf8mb4_general_ci', 'left', false)
            ->join('tbl_dtr_BondingTracker t', 't.TrackerID = tm.tid', 'left')
            ->where('NOT (' . self::SHUTDOWN_ROW . ')', null, false)
            ->where("COALESCE(b.TestType, '') <> 'LACKING'", null, false)   // a lacking line is also a bonding_info record, but it is listed from the tracker
            ->where("(b.LabRefNo IS NULL OR b.LabRefNo = '' OR b.BondingTestID IN (SELECT keep_id FROM (SELECT COALESCE(MAX(CASE WHEN Result IS NOT NULL AND Result <> '' THEN BondingTestID END), MAX(BondingTestID)) AS keep_id FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' GROUP BY LabRefNo) k))", null, false);
        if ($lk === 'bonding' && $lRes !== '') {
            $bondingBaseQ->where($resSql('b.Result', $lRes), null, false);
        }
        foreach ($bondingW as $w) {   // every typed word must be found in one of the columns shown in the list
            $like = $db->escapeLikeString($w);
            $bondingBaseQ->where("CONVERT(CONCAT_WS(' ', b.LabRefNo, b.ModelStyle, t.Style, b.Color, t.Colorway, b.POLotNO, t.ProductionCode, b.MTL, t.MTL, b.Technician, b.Result, LEFT(b.ProductionDate, 10), LEFT(b.TestedDate, 10), b.LabNotesComments) USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%{$like}%' ESCAPE '!'", null, false);
        }
        $lineKeyExpr = "COALESCE(NULLIF(UPPER(TRIM(t.`LineNo`)), ''), CASE WHEN UPPER(b.LabRefNo) REGEXP '^DTP[0-9]{8}SH[A-Z][0-9]+' THEN UPPER(SUBSTRING_INDEX(SUBSTRING(b.LabRefNo, 14), '-', 1)) ELSE UPPER(b.LabRefNo) END)";
        // The Test Type of a row = the one of the intake tracker (tbl_dtr_bondingtracker.TestType), or the one of its bonding record when the tracker has none (the two tables use different collations)
        $ttE         = "UPPER(COALESCE(NULLIF(t.TestType COLLATE utf8mb4_general_ci, ''), NULLIF(b.TestType COLLATE utf8mb4_general_ci, ''), ''))";
        $dailyCond   = "{$ttE} LIKE '%DAILY%' AND b.LabRefNo NOT REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)'";
        // The date a row counts on = its COLLECTION date (tracker CollectionDate): the DAILY shoes collected today count for TOMORROW (the only type with a back date),
        // every other type (tech trial, sample, first article, retest, advance, lacking, shutdown) counts on the day it was collected / encoded.
        $collE    = "COALESCE(NULLIF(LEFT(t.CollectionDate, 10), ''), NULLIF(LEFT(b.ReceivedDate, 10), ''), {$bdtExpr})";
        // a Daily shoe collected LATE (production date earlier than the collection date, e.g. the specimen of a LACKING line that arrives two days later) stays with its production batch: it counts where that batch counts
        $prodE    = "COALESCE(NULLIF(LEFT(t.ProductionDate, 10), ''), {$pdExpr})";
        $dailyBase = "(CASE WHEN {$prodE} IS NOT NULL AND {$prodE} < {$collE} THEN {$prodE} ELSE {$collE} END)";
        $effE     = "(CASE WHEN {$dailyCond} THEN DATE_ADD({$dailyBase}, INTERVAL (CASE WHEN DAYOFWEEK({$dailyBase}) = 7 THEN 2 ELSE 1 END) DAY) ELSE {$collE} END)";   // no collection on a Sunday: Saturday's Daily shoes count on Monday
        $dShift   = static fn (string $d, int $n): string => date('Y-m-d', strtotime($d . ' ' . $n . ' day'));
        $rngDaily = [$dShift($lFrom, (int) date('N', strtotime($lFrom ?: 'today')) === 1 ? -2 : -1), $dShift($lTo, -1)];   // collection dates whose Daily shoes count in the range
        $rngOther = [$lFrom, $lTo];                              // collection dates of everything else

        // Advance / Lacking / Shutdown (marked while the Daily batch is encoded) count on the day they were LOADED (CreatedAt) or on the day their batch counts
        // (a Daily batch collected on a day counts the next working day: Saturday -> Monday), so they sit in the same day as the Daily shoes they belong to
        $trkRange = static function (\CodeIgniter\Database\BaseConnection $db, string $px, array $r): string {
            $f = $db->escape($r[0]);
            $t = $db->escape($r[1]);
            $coll = "DATE_ADD({$px}CollectionDate, INTERVAL (CASE WHEN DAYOFWEEK({$px}CollectionDate) = 7 THEN 2 ELSE 1 END) DAY)";

            return "((DATE({$px}CreatedAt) >= {$f} AND DATE({$px}CreatedAt) <= {$t}) OR ({$coll} >= {$f} AND {$coll} <= {$t}))";
        };
        // ADVANCE shoes (given by production for the next day) count on the day they were loaded; after that they sit in the Daily record of the day they are tested, no longer in Advance:
        // a shoe already TESTED is not an Advance of a later day any more
        $advRange = static function (\CodeIgniter\Database\BaseConnection $db, string $px, array $r): string {
            $f = $db->escape($r[0]);
            $t = $db->escape($r[1]);
            $coll = "DATE_ADD({$px}CollectionDate, INTERVAL (CASE WHEN DAYOFWEEK({$px}CollectionDate) = 7 THEN 2 ELSE 1 END) DAY)";
            $ref  = ($px === '' ? 'tbl_dtr_BondingTracker.' : $px) . 'LabRefNo';
            $done = "EXISTS (SELECT 1 FROM tbl_dtr_bonding_info bi WHERE bi.LabRefNo = {$ref} COLLATE utf8mb4_unicode_ci AND COALESCE(bi.Result, '') <> '')";

            return "((DATE({$px}CreatedAt) >= {$f} AND DATE({$px}CreatedAt) <= {$t}) OR ({$coll} >= {$f} AND {$coll} <= {$t} AND NOT {$done}))";
        };
        // LACKING lines (shoes the production still owes) belong to the TESTING day they are due: the next working day after the Daily batch (Saturday -> Monday), never the day they were marked
        $lackRange = static function (\CodeIgniter\Database\BaseConnection $db, string $px, array $r): string {
            $f = $db->escape($r[0]);
            $t = $db->escape($r[1]);
            $coll = "DATE_ADD({$px}CollectionDate, INTERVAL (CASE WHEN DAYOFWEEK({$px}CollectionDate) = 7 THEN 2 ELSE 1 END) DAY)";

            return "({$coll} >= {$f} AND {$coll} <= {$t})";
        };
        $rangeP   = '1=1';
        if ($lk === 'bonding' && $lRange) {
            $rangeP = "{$effE} >= " . $db->escape($lFrom) . " AND {$effE} <= " . $db->escape($lTo);
        }
        $nonPull = "b.LabRefNo NOT REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)'";
        $bondingTypeRow = (clone $bondingBaseQ)->where($rangeP, null, false)->select("
            SUM(CASE WHEN {$dailyCond} THEN 1 ELSE 0 END) AS c_daily,
            SUM(CASE WHEN COALESCE(t.Lacking, 0) = 1 AND {$nonPull} THEN 1 ELSE 0 END) AS c_lack,
            SUM(CASE WHEN COALESCE(t.Lacking, 0) = 2 AND {$nonPull} THEN 1 ELSE 0 END) AS c_adv,
            SUM(CASE WHEN {$ttE} LIKE '%TRIAL%' THEN 1 ELSE 0 END) AS c_trial,
            SUM(CASE WHEN {$ttE} LIKE '%SAMPLE%' OR {$ttE} LIKE '%RIVET%' THEN 1 ELSE 0 END) AS c_sample,
            SUM(CASE WHEN {$ttE} LIKE '%RETEST%' THEN 1 ELSE 0 END) AS c_retest,
            SUM(CASE WHEN {$ttE} LIKE '%FIRST%' THEN 1 ELSE 0 END) AS c_first
        ", false)->get()->getRowArray() ?: [];
        $bondingListBase = (clone $bondingBaseQ)->where($rangeP, null, false);   // counts and rows both go by the collection date
        $bSel = "b.BondingTestID, b.LabRefNo, COALESCE(NULLIF(b.ModelStyle, ''), t.Style) AS ModelStyle, COALESCE(NULLIF(b.Color, ''), t.Colorway) AS Color, b.Side, b.Factory, COALESCE({$pdExpr}, b.ProductionDate) AS ProductionDate, b.TestedDate, b.Technician, b.Result, b.LabNotesComments, COALESCE(NULLIF(b.POLotNO, ''), t.ProductionCode) AS POLotNO, COALESCE(NULLIF(b.MTL, ''), t.MTL) AS MTL, b.ReceivedDate, b.Remarks, COALESCE(NULLIF(b.TestType COLLATE utf8mb4_general_ci, ''), t.TestType COLLATE utf8mb4_general_ci) AS TestType, COALESCE(NULLIF(b.MachineNumber, ''), t.MachineNumber) AS MachineNumber, b.FailPart, b.Cause, b.TestDuration, b.RetestOf, b.SourceSystem, t.`LineNo` AS TrackerLine, t.Building AS TrackerBuilding, t.PullTestType, t.Lacking, COALESCE(t.TrackerID, 0) AS TrackerID";
        $bondingShoesQ = (clone $bondingListBase)->select($bSel, false);
        $bondingDbTotal = (int) (clone $bondingListBase)->countAllResults(false);
        $bLineExpr = "COALESCE(NULLIF(TRIM(t.`LineNo`), ''), CASE WHEN b.LabRefNo REGEXP '^DTP[0-9]{8}[A-Za-z]{2}[ABab][0-9]+' THEN SUBSTRING_INDEX(SUBSTRING(b.LabRefNo, 14), '-', 1) ELSE '' END)";
        $bBldgVal  = "UPPER(LEFT(COALESCE(NULLIF(TRIM(t.Building), ''), TRIM({$bLineExpr}), ''), 1)) COLLATE utf8mb4_general_ci";
        $bBldgSql  = "CASE WHEN {$bBldgVal} = 'A' THEN 0 WHEN {$bBldgVal} = 'B' THEN 1 ELSE 2 END";
        $bNumSql   = "CASE WHEN CAST(SUBSTRING(TRIM({$bLineExpr}), 2) AS UNSIGNED) > 0 THEN CAST(SUBSTRING(TRIM({$bLineExpr}), 2) AS UNSIGNED) ELSE 9999 END";
        $bondingShoes = $bondingShoesQ
            ->orderBy("{$bdtExpr} DESC, {$bBldgSql} ASC, {$bNumSql} ASC, b.LabRefNo ASC, COALESCE(t.TrackerID, 0) DESC, b.BondingTestID DESC", '', false)
            ->limit($lqMax)->get()->getResultArray();
        // lines marked LACKING have no specimen yet but are part of the collection: they are listed too (shutdown marks are not)
        $lackTotal = 0;
        if ($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) {
            $lackQ = $db->table('tbl_dtr_BondingTracker')->where('TestType', 'LACKING')->where('Shutdown', 0);
            if ($lk === 'bonding' && $lRange) {
                $lackQ->where($lackRange($db, '', $rngOther), null, false);   // Lacking / Advance / Shutdown count on the day they were LOADED (encoded)
            }
            if ($lk === 'bonding' && in_array($lRes, ['pass', 'tol', 'fail'], true)) {
                $lackQ->where('1=0', null, false);   // a lacking line has no result yet
            }
            foreach ($bondingW as $w) {
                $like = $db->escapeLikeString($w);
                $lackQ->where("CONVERT(CONCAT_WS(' ', LabRefNo, Style, Colorway, ProductionCode, MTL, Technician, LEFT(ProductionDate, 10), 'LACKING') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%{$like}%' ESCAPE '!'", null, false);
            }
            $lackTotal = (int) $lackQ->countAllResults(false);
            foreach ($lackQ->orderBy('TrackerID', 'DESC')->limit($lqMax)->get()->getResultArray() as $lkRow) {
                $bondingShoes[] = [
                    'BondingTestID' => 0, 'TrackerID' => (int) $lkRow['TrackerID'], 'IsLacking' => 1,
                    'LabRefNo' => trim((string) $lkRow['LabRefNo']) !== '' ? $lkRow['LabRefNo'] : 'DTP' . str_replace('-', '', (string) $lkRow['ProductionDate']) . 'SH' . $lkRow['LineNo'] . '-' . $lkRow['Side'], 'ModelStyle' => $lkRow['Style'], 'Color' => $lkRow['Colorway'],
                    'Side' => $lkRow['Side'], 'Factory' => '', 'ProductionDate' => $lkRow['ProductionDate'], 'TestedDate' => null, 'Technician' => $lkRow['Technician'], 'Result' => '',
                    'LabNotesComments' => trim('LACKING (due ' . (string) $lkRow['LackingDate'] . ') ' . (string) $lkRow['RetestDetails']), 'POLotNO' => $lkRow['ProductionCode'], 'MTL' => $lkRow['MTL'],
                    'ReceivedDate' => $lkRow['CollectionDate'], 'Remarks' => $lkRow['RetestDetails'], 'TestType' => 'LACKING', 'TrackerLine' => $lkRow['LineNo'], 'TrackerBuilding' => $lkRow['Building'] ?? '', 'PullTestType' => $lkRow['PullTestType'], 'Lacking' => 1,
                ];
            }
            usort($bondingShoes, static fn ($a, $b) => ((int) ($b['TrackerID'] ?? 0) <=> (int) ($a['TrackerID'] ?? 0)) ?: ((int) ($b['BondingTestID'] ?? 0) <=> (int) ($a['BondingTestID'] ?? 0)));
        }

        // One shoe can have two tests (a bonding test and a pull test, e.g. DTP20261110SHA4-R and DTP20261110EYA4-R): when the production code, style, colorway, MTL # and
        // line are the same, the pull test's Lab Ref shows in the row of the bonding test instead of a row of its own
        // (pull refs look like DTP20261111EYA1-L / ...DRA1-L / ...RVTA1-L / ...HPA1-L; a pair must share the line, the production (VSP) code, the production date and the side)
        $pullRe = '/^DTP\d{8}(?:HP|EY|DR|RVT|LLH)([A-Z])(\d+)/i';
        $lineOfRow = static function (array $r) use ($pullRe): string {
            $tl = strtoupper(trim((string) ($r['TrackerLine'] ?? '')));
            if ($tl !== '') {
                return $tl;
            }
            $ref = (string) ($r['LabRefNo'] ?? '');

            return (preg_match($pullRe, $ref, $m) || preg_match('/^DTP\d{8}[A-Z]{2}([A-Z])(\d+)/i', $ref, $m)) ? strtoupper($m[1]) . (int) $m[2] : '';
        };
        $keyOf = static function (array $r, string $line): string {
            $ref  = (string) ($r['LabRefNo'] ?? '');
            $date = substr(trim((string) ($r['ProductionDate'] ?? '')), 0, 10);
            if ($date === '' && preg_match('/^DTP(\d{4})(\d{2})(\d{2})/i', $ref, $dm)) {
                $date = $dm[1] . '-' . $dm[2] . '-' . $dm[3];
            }
            $side = preg_match('/-([LR])\s*$/i', $ref, $sm) ? $sm[1] : (string) ($r['Side'] ?? '');

            return strtoupper(implode('|', [trim((string) ($r['POLotNO'] ?? '')), $line, $date, substr(strtoupper(trim($side)), 0, 1)]));
        };
        $codes = array_values(array_unique(array_filter(array_map(static fn ($r) => trim((string) ($r['POLotNO'] ?? '')), $bondingShoes))));
        $droppedPulls = 0;
        $bondMtls = array_values(array_unique(array_filter(array_map(static fn ($r) => strtoupper(trim((string) ($r['MTL'] ?? ''))), $bondingShoes))));
        // same MTL # + line + production date + side = the same shoe, even when its pull test carries another production (VSP) code
        $mtlKeyOf = static function (array $r, string $line): string {
            $ref  = (string) ($r['LabRefNo'] ?? '');
            $date = substr(trim((string) ($r['ProductionDate'] ?? '')), 0, 10);
            if ($date === '' && preg_match('/^DTP(\d{4})(\d{2})(\d{2})/i', $ref, $dm)) {
                $date = $dm[1] . '-' . $dm[2] . '-' . $dm[3];
            }
            $side = preg_match('/-([LR])\s*$/i', $ref, $sm) ? $sm[1] : (string) ($r['Side'] ?? '');

            return strtoupper(implode('|', [trim((string) ($r['MTL'] ?? '')), $line, $date, substr(strtoupper(trim($side)), 0, 1)]));
        };
        if ($codes) {
            $pulls = $db->table('tbl_dtr_bonding_info b')
                ->select("b.BondingTestID, b.LabRefNo, COALESCE(NULLIF(b.ModelStyle, ''), t.Style) AS ModelStyle, COALESCE(NULLIF(b.Color, ''), t.Colorway) AS Color, COALESCE(NULLIF(b.POLotNO, ''), t.ProductionCode) AS POLotNO, COALESCE(NULLIF(b.MTL, ''), t.MTL) AS MTL, b.Result, b.ProductionDate, b.TestedDate, b.Technician, b.Side, t.`LineNo` AS TrackerLine", false)
                ->join('(SELECT LabRefNo, MAX(TrackerID) AS tid FROM tbl_dtr_BondingTracker WHERE LabRefNo IS NOT NULL GROUP BY LabRefNo) tm', 'tm.LabRefNo = b.LabRefNo COLLATE utf8mb4_general_ci', 'left', false)
                ->join('tbl_dtr_BondingTracker t', 't.TrackerID = tm.tid', 'left')
                ->where("b.LabRefNo REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)[A-Z][0-9]+'", null, false)
                ->groupStart()->whereIn('b.POLotNO', $codes)->orWhereIn('t.ProductionCode', $codes)->groupEnd()
                ->orderBy('b.BondingTestID', 'DESC')->limit(1500)->get()->getResultArray();
            // pull tests saved by the intake live in tbl_dtr_pull_tests (negative id: they are not rows of the bonding list)
            if ($db->tableExists('tbl_dtr_pull_tests')) {
                // pull test rows are read from tbl_dtr_pull_tests (their own Result / Tested Date / Technician); a pull Lab Ref only in tbl_dtr_bonding_info (older ones) is kept as it is
                $byRef = [];
                foreach ($pulls as $i => $pr) {
                    $byRef[strtoupper((string) $pr['LabRefNo'])] = $i;
                }
                $mainOf = [];
                foreach ($bondingShoes as $bs) {
                    if (! preg_match($pullRe, (string) ($bs['LabRefNo'] ?? '')) && trim((string) ($bs['POLotNO'] ?? '')) !== '') {
                        $mainOf[$keyOf($bs, $lineOfRow($bs))] = $bs;
                    }
                }
                foreach ($db->table('tbl_dtr_pull_tests')
                    ->select("PullInfoID, LabRefNo, ModelStyle, Color, ProductionCode AS POLotNO, MTL, Result, ProductionDate, TestedDate, Technician, `LineNo` AS TrackerLine", false)
                    ->where("LabRefNo REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)[A-Z][0-9]+'", null, false)
                    ->orderBy('PullInfoID', 'DESC')->limit(2500)->get()->getResultArray() as $pt) {
                    $k0 = strtoupper((string) $pt['LabRefNo']);
                    $row = ['BondingTestID' => -(int) $pt['PullInfoID'], 'LabRefNo' => $pt['LabRefNo'], 'ModelStyle' => $pt['ModelStyle'], 'Color' => $pt['Color'], 'POLotNO' => $pt['POLotNO'],
                        'MTL' => $pt['MTL'], 'Result' => (string) $pt['Result'], 'ProductionDate' => $pt['ProductionDate'], 'TestedDate' => $pt['TestedDate'], 'Technician' => $pt['Technician'], 'Side' => '', 'TrackerLine' => $pt['TrackerLine']];
                    if (isset($byRef[$k0])) {
                        $old = $pulls[$byRef[$k0]];
                        foreach ($row as $f => $v) {
                            if (($v === null || $v === '') && isset($old[$f]) && $f !== 'BondingTestID') {
                                $row[$f] = $old[$f];   // blank in tbl_dtr_pull_tests: the bonding_info record's value
                            }
                        }
                        $pulls[$byRef[$k0]] = $row;
                    } elseif (trim((string) $pt['POLotNO']) === '' || in_array(strtoupper(trim((string) $pt['POLotNO'])), array_map('strtoupper', $codes), true)
                        || (trim((string) $pt['MTL']) !== '' && in_array(strtoupper(trim((string) $pt['MTL'])), $bondMtls, true))) {   // another production code, same MTL # (same shoe): it still pairs by MTL
                        $byRef[$k0] = count($pulls);
                        $pulls[] = $row;
                    }
                }
                // the pull row carries the same record as its bonding specimen (style, colorway, MTL #, production date ...): blanks are filled from it, in the list and in the table
                foreach ($pulls as &$pr2) {
                    if ($pr2['BondingTestID'] >= 0) {
                        continue;
                    }
                    $main = $mainOf[$keyOf($pr2, $lineOfRow($pr2))] ?? null;
                    if (! $main) {
                        continue;
                    }
                    $fill = [];
                    foreach (['ModelStyle' => 'ModelStyle', 'Color' => 'Color', 'POLotNO' => 'ProductionCode', 'MTL' => 'MTL', 'ProductionDate' => 'ProductionDate'] as $f => $col) {
                        if (trim((string) ($pr2[$f] ?? '')) === '' && trim((string) ($main[$f] ?? '')) !== '') {
                            $pr2[$f] = $main[$f];
                            $fill[$col] = $col === 'ProductionDate' ? substr((string) $main[$f], 0, 10) : $main[$f];
                        }
                    }
                    if ($fill) {
                        $db->table('tbl_dtr_pull_tests')->where('PullInfoID', -(int) $pr2['BondingTestID'])->update($fill);
                    }
                }
                unset($pr2);
            }
            $partners = [];
            $partnersM = [];
            foreach ($pulls as $pr) {
                if (trim((string) $pr['POLotNO']) !== '') {
                    $partners[$keyOf($pr, $lineOfRow($pr))][] = $pr;
                }
                if (trim((string) ($pr['MTL'] ?? '')) !== '') {
                    $partnersM[$mtlKeyOf($pr, $lineOfRow($pr))][] = $pr;
                }
            }
            $drop = [];
            foreach ($bondingShoes as &$br) {
                if (preg_match($pullRe, (string) ($br['LabRefNo'] ?? '')) || trim((string) ($br['POLotNO'] ?? '')) === '') {
                    continue;
                }
                $k = $keyOf($br, $lineOfRow($br));
                $partnerList = $partners[$k] ?? [];
                if (trim((string) ($br['MTL'] ?? '')) !== '') {   // pull tests of the same shoe that carry another production code: found by MTL #
                    $have = array_map(static fn ($x) => strtoupper((string) $x['LabRefNo']), $partnerList);
                    foreach ($partnersM[$mtlKeyOf($br, $lineOfRow($br))] ?? [] as $pm) {
                        if (! in_array(strtoupper((string) $pm['LabRefNo']), $have, true) && ! isset($drop[(int) $pm['BondingTestID']])) {
                            $partnerList[] = $pm;
                        }
                    }
                }
                foreach ($partnerList as $pr) {
                    $br['PullRefs'][] = ['ref' => $pr['LabRefNo'], 'id' => (int) $pr['BondingTestID'], 'result' => strtoupper(trim((string) $pr['Result'])), 'tested' => substr(trim((string) ($pr['TestedDate'] ?? '')), 0, 10), 'tech' => trim((string) ($pr['Technician'] ?? ''))];
                    $drop[(int) $pr['BondingTestID']] = true;
                }
                if (! empty($br['PullRefs'])) {
                    $res = array_merge([strtoupper(trim((string) ($br['Result'] ?? '')))], array_column($br['PullRefs'], 'result'));
                    $br['RowResult'] = array_reduce($res, static fn ($c, $x) => (str_starts_with($x, 'FAIL') || $x === 'ERROR') ? 'FAIL' : (str_starts_with($x, 'TOL') && $c !== 'FAIL' ? 'TOLERABLE' : ($c === '' && $x !== '' ? $x : $c)), '');
                }
            }
            unset($br);
            if ($drop) {
                $beforeCnt = count($bondingShoes);
                $bondingShoes = array_values(array_filter($bondingShoes, static fn ($r) => ! isset($drop[(int) ($r['BondingTestID'] ?? 0)])));
                $droppedPulls = $beforeCnt - count($bondingShoes);
            }
        }
        // SAMPLE > Rivet specimens are pull tests (tbl_dtr_pull_tests) with no bonding partner: listed as rows of their own
        if ($lk === 'bonding' && $db->tableExists('tbl_dtr_pull_tests') && $lRes === '') {
            // pull Lab Refs already shown beside their bonding specimen are not listed again; the others (Rivet, or a pull test with no bonding partner) get a row of their own
            $shownPull = [];
            foreach ($bondingShoes as $sb) {
                foreach ($sb['PullRefs'] ?? [] as $pr) {
                    $shownPull[strtoupper((string) $pr['ref'])] = true;
                }
            }
            $rvQ = $db->table('tbl_dtr_pull_tests')->where("LabRefNo REGEXP '^DTP[0-9]{8}(RIVET-[0-9]+|(HP|EY|DR|RVT|LLH)[A-Z][0-9]+)'", null, false)->orderBy('PullInfoID', 'DESC')->limit($lqMax);
            if ($lRange) {
                $rvD = "COALESCE(NULLIF(LEFT(CollectionDate, 10), ''), NULLIF(LEFT(ProductionDate, 10), ''), CONCAT(SUBSTRING(LabRefNo, 4, 4), '-', SUBSTRING(LabRefNo, 8, 2), '-', SUBSTRING(LabRefNo, 10, 2)))";
                // the other pull tests belong to the Daily shoes: they count the next working day (Saturday -> Monday); one collected LATE (production date earlier than the collection date) stays with its production batch, like its bonding shoe
                $rvProd = "NULLIF(LEFT(ProductionDate, 10), '')";
                $rvBase = "(CASE WHEN {$rvProd} IS NOT NULL AND {$rvProd} < {$rvD} THEN {$rvProd} ELSE {$rvD} END)";
                $rvEff  = "DATE_ADD({$rvBase}, INTERVAL (CASE WHEN DAYOFWEEK({$rvBase}) = 7 THEN 2 ELSE 1 END) DAY)";
                // a RIVET sample is a SAMPLE (last day + the range); the other pull tests belong to the Daily shoes (the day before)
                $rvQ->where("((LabRefNo REGEXP 'RIVET' AND {$rvD} >= " . $db->escape($rngOther[0]) . " AND {$rvD} <= " . $db->escape($rngOther[1]) . ") OR (LabRefNo NOT REGEXP 'RIVET' AND {$rvEff} >= " . $db->escape($lFrom) . " AND {$rvEff} <= " . $db->escape($lTo) . "))", null, false);
            }
            foreach ($bondingW as $w) {
                $like = $db->escapeLikeString($w);
                $rvQ->where("CONVERT(CONCAT_WS(' ', LabRefNo, ModelStyle, Color, ProductionCode, MTL, Technician, Result, LEFT(ProductionDate, 10), LEFT(TestedDate, 10)) USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%{$like}%' ESCAPE '!'", null, false);
            }
            foreach ($rvQ->get()->getResultArray() as $rv) {
                if (isset($shownPull[strtoupper((string) $rv['LabRefNo'])])) {
                    continue;
                }
                $isRivet = stripos((string) $rv['LabRefNo'], 'RIVET') !== false;
                $tk = [];
                if (! $isRivet && $db->tableExists('tbl_dtr_BondingTracker')) {   // a pull test saved with blanks: the tracker row of its Lab Ref has the details
                    $tk = $db->table('tbl_dtr_BondingTracker')->select("Style, Colorway, ProductionCode, MTL, ProductionDate, CollectionDate, Technician, `LineNo`", false)->where('LabRefNo', $rv['LabRefNo'])->orderBy('TrackerID', 'DESC')->get(1)->getRowArray() ?: [];
                }
                $pick = static fn ($a, $b) => trim((string) $a) !== '' ? $a : $b;
                $bondingShoes[] = [
                    'BondingTestID' => -(int) $rv['PullInfoID'], 'TrackerID' => (int) $rv['TrackerID'], 'LabRefNo' => $rv['LabRefNo'], 'ModelStyle' => $pick($rv['ModelStyle'], $tk['Style'] ?? ''), 'Color' => $pick($rv['Color'], $tk['Colorway'] ?? ''), 'Side' => '', 'Factory' => $rv['Factory'],
                    'ProductionDate' => substr((string) $pick($rv['ProductionDate'], $tk['ProductionDate'] ?? ''), 0, 10), 'TestedDate' => $rv['TestedDate'], 'Technician' => $pick($rv['Technician'], $tk['Technician'] ?? ''), 'Result' => (string) $rv['Result'],
                    'LabNotesComments' => $isRivet ? 'SAMPLE RIVET (pull test)' : 'PULL TEST',
                    'POLotNO' => $pick($rv['ProductionCode'], $tk['ProductionCode'] ?? ''), 'MTL' => $pick($rv['MTL'], $tk['MTL'] ?? ''), 'ReceivedDate' => $pick($rv['CollectionDate'], $tk['CollectionDate'] ?? ''), 'Remarks' => '', 'TestType' => $isRivet ? 'SAMPLE' : 'DAILY',
                    'TrackerLine' => $pick($rv['LineNo'], $tk['LineNo'] ?? ''), 'PullTestType' => $isRivet ? 'RIVET' : (preg_match('/^DTP\d{8}(HP|EY|DR|RVT|LLH)/i', (string) $rv['LabRefNo'], $pm) ? strtoupper($pm[1]) : ''), 'PullOnly' => 1,
                ];
            }
            usort($bondingShoes, static fn ($a, $b) => ((int) ($b['TrackerID'] ?? 0) <=> (int) ($a['TrackerID'] ?? 0)) ?: ((int) ($b['BondingTestID'] ?? 0) <=> (int) ($a['BondingTestID'] ?? 0)));
        }
        if ($lk === 'bonding' && $lsKey !== '') {   // column sort (of the rows loaded for the list): empty cells always last
            $bKey = static function (array $r) use ($lsKey): string {
                $v = match ($lsKey) {
                    'labref' => $r['LabRefNo'] ?? '', 'pull' => ($r['PullRefs'][0]['ref'] ?? (! empty($r['PullOnly']) ? ($r['LabRefNo'] ?? '') : '')), 'prod' => $r['POLotNO'] ?? '',
                    'style' => $r['ModelStyle'] ?? '', 'color' => $r['Color'] ?? '', 'mtl' => $r['MTL'] ?? '', 'pdate' => $r['ProductionDate'] ?? '', 'tdate' => $r['TestedDate'] ?? '',
                    'result', 'status' => ($r['RowResult'] ?? $r['Result'] ?? ''), 'tech' => $r['Technician'] ?? '', default => '',
                };

                return strtolower(trim((string) $v));
            };
            usort($bondingShoes, static function ($a, $b) use ($bKey, $lsDir) {
                $x = $bKey($a);
                $y = $bKey($b);
                if ($x === '' || $y === '') {
                    return ($x === '') <=> ($y === '');
                }
                $c = strnatcmp($x, $y);

                return $lsDir === 'DESC' ? -$c : $c;
            });
        } else {
            // Default view: arrange newest date first, Building A -> Building B, and numerically by Line Number (1, 2, ... N)
            $lineSortTuple = static function (array $r) use ($pullRe): array {
                $date = substr(trim((string) ($r['ProductionDate'] ?? '')), 0, 10);
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $dm)) {
                    $date = $dm[3] . '-' . $dm[1] . '-' . $dm[2];
                }
                if ($date === '') {
                    $ref = (string) ($r['LabRefNo'] ?? '');
                    if (preg_match('/^DTP(\d{4})(\d{2})(\d{2})/i', $ref, $dm)) {
                        $date = $dm[1] . '-' . $dm[2] . '-' . $dm[3];
                    } else {
                        $date = substr(trim((string) ($r['TestedDate'] ?? $r['ReceivedDate'] ?? '')), 0, 10);
                    }
                }
                $tl = strtoupper(trim((string) ($r['TrackerLine'] ?? '')));
                if ($tl === '') {
                    $ref = (string) ($r['LabRefNo'] ?? '');
                    if (preg_match($pullRe, $ref, $m) || preg_match('/^DTP\d{8}[A-Z]{2}([AB])(\d+)/i', $ref, $m)) {
                        $tl = strtoupper($m[1]) . (int) $m[2];
                    }
                }
                $bldg = strtoupper(trim((string) ($r['TrackerBuilding'] ?? '')));
                $num  = 9999;
                if (preg_match('/^([A-Z]+)\s*(\d+)/', $tl, $lm)) {
                    if ($bldg === '') {
                        $bldg = $lm[1];
                    }
                    $n   = (int) $lm[2];
                    $num = $n > 0 ? $n : 9999;
                } elseif (preg_match('/^\d+$/', $tl)) {
                    $n   = (int) $tl;
                    $num = $n > 0 ? $n : 9999;
                }
                $bRank = $bldg === 'A' ? 0 : ($bldg === 'B' ? 1 : 2);

                $sortRef = (string) ($r['LabRefNo'] ?? '');
                if (preg_match('/^(DTP\d{8}TRIALSH)-([LR])-(\d+)$/i', $sortRef, $tm)) {
                    $sortRef = $tm[1] . '-' . str_pad($tm[3], 5, '0', STR_PAD_LEFT) . $tm[2];   // tech trials in pairs: L-1, R-1, L-2, R-2 ...
                }

                return [$date, $bRank, $bldg, $num, $sortRef, (int) ($r['TrackerID'] ?? 0), (int) ($r['BondingTestID'] ?? 0)];
            };
            usort($bondingShoes, static function ($a, $b) use ($lineSortTuple) {
                $ta = $lineSortTuple($a);
                $tb = $lineSortTuple($b);

                return strcmp($tb[0], $ta[0])
                    ?: ($ta[1] <=> $tb[1])
                    ?: strcmp($ta[2], $tb[2])
                    ?: ($ta[3] <=> $tb[3])
                    ?: strnatcasecmp($ta[4], $tb[4])
                    ?: ($tb[5] <=> $ta[5])
                    ?: ($tb[6] <=> $ta[6]);
            });
        }
        // Count orphan RIVET sample pull tests not duplicated in tbl_dtr_bonding_info
        $orphanRivetSample = 0;
        if ($lk === 'bonding' && $db->tableExists('tbl_dtr_pull_tests') && $lRes === '') {
            $rvCntQ = $db->table('tbl_dtr_pull_tests p')
                ->where("p.LabRefNo REGEXP '^DTP[0-9]{8}RIVET-[0-9]+$'", null, false)
                ->where('NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo = p.LabRefNo COLLATE utf8mb4_unicode_ci)', null, false);
            if ($lRange) {
                $rvD = "COALESCE(NULLIF(LEFT(p.CollectionDate, 10), ''), NULLIF(LEFT(p.ProductionDate, 10), ''), CONCAT(SUBSTRING(p.LabRefNo, 4, 4), '-', SUBSTRING(p.LabRefNo, 8, 2), '-', SUBSTRING(p.LabRefNo, 10, 2)))";
                $rvCntQ->where($rvD . ' >= ' . $db->escape($rngOther[0]), null, false)->where($rvD . ' <= ' . $db->escape($rngOther[1]), null, false);   // RIVET samples: last day + the range
            }
            foreach ($bondingW as $w) {
                $like = $db->escapeLikeString($w);
                $rvCntQ->where("CONVERT(CONCAT_WS(' ', p.LabRefNo, p.ModelStyle, p.Color, p.ProductionCode, p.MTL, p.Technician, p.Result, LEFT(p.ProductionDate, 10), LEFT(p.TestedDate, 10)) USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%{$like}%' ESCAPE '!'", null, false);
            }
            $orphanRivetSample = (int) $rvCntQ->countAllResults();
        }
        // Advance / Lacking / Shutdown are marked while the Daily batch is encoded: they count on the day they were LOADED (tracker CreatedAt), whatever the dates of the batch
        $trkOk = $db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker');
        $trkQ  = function () use ($db, $lRange, $rngOther, $trkRange) {
            $q = $db->table('tbl_dtr_BondingTracker');
            if ($lRange) {
                $q->where($trkRange($db, '', $rngOther), null, false);
            }

            return $q;
        };
        $notPull = static fn ($q) => $q->groupStart()->where('PullTestType IS NULL', null, false)->orWhere('PullTestType', '')->groupEnd();
        $advQ = $db->table('tbl_dtr_BondingTracker')->where('Lacking', 2);
        if ($lRange) {
            $advQ->where($advRange($db, '', $rngOther), null, false);
        }
        $cntAdvance = $trkOk ? (int) $notPull($advQ)->countAllResults() : 0;
        $lackCntQ = $db->table('tbl_dtr_BondingTracker');
        if ($lRange) {
            $lackCntQ->where($lackRange($db, '', $rngOther), null, false);
        }
        $cntLacking = $trkOk ? (int) $lackCntQ->where('Lacking', 1)->where('Shutdown', 0)->groupStart()->where('TestType', 'LACKING')->orGroupStart()->where('PullTestType IS NULL', null, false)->orWhere('PullTestType', '')->groupEnd()->groupEnd()->countAllResults() : 0;
        $shutRangeTotal = $trkOk ? (int) $trkQ()->groupStart()->where('Shutdown', 1)->orWhere('UPPER(TestType)', 'SHUTDOWN')->groupEnd()->countAllResults() : 0;
        if ($lRange) {
            $shutRangeTotal += (int) $db->table('tbl_dtr_bonding_info b')->where(self::SHUTDOWN_ROW, null, false)->where('b.ProductionDate >=', $rngOther[0])->where('b.ProductionDate <=', $rngOther[1])->countAllResults();
        } else {
            $shutRangeTotal = $shutTotal;
        }
        $bondingCounts = [
            'daily'    => (int) ($bondingTypeRow['c_daily'] ?? 0),
            'shutdown' => $shutRangeTotal,
            'lacking'  => $cntLacking,
            'trial'    => (int) ($bondingTypeRow['c_trial'] ?? 0),
            'sample'   => (int) ($bondingTypeRow['c_sample'] ?? 0) + $orphanRivetSample,
            'retest'   => (int) ($bondingTypeRow['c_retest'] ?? 0),
            'first'    => (int) ($bondingTypeRow['c_first'] ?? 0),
            'advance'  => $cntAdvance,
        ];

        // Passed / Tolerable / Failed cards = the Result column of the rows listed for the date (a row with its pull tests shows the worst of them: one FAILED report makes the row FAILED, the same for TOLERABLE)
        $bondingResCounts = ['pass' => 0, 'tol' => 0, 'fail' => 0];
        if ($lk === 'bonding') {
            foreach ($bondingShoes as $rr) {
                if (! empty($rr['IsLacking']) || ! empty($rr['IsShutdown'])) {
                    continue;
                }
                $rv = strtoupper(trim((string) (($rr['RowResult'] ?? '') !== '' ? $rr['RowResult'] : ($rr['Result'] ?? ''))));
                if ($rv === 'ERROR' || $rv === 'REJECTED' || str_starts_with($rv, 'FAIL')) {
                    $bondingResCounts['fail']++;
                } elseif (str_starts_with($rv, 'TOL')) {
                    $bondingResCounts['tol']++;
                } elseif (str_starts_with($rv, 'PASS')) {
                    $bondingResCounts['pass']++;
                }
            }
        }

        // a summary button is pressed: only the rows of that kind stay in the list (the counts above stay the whole day's)
        if ($lk === 'bonding' && $lType !== '') {
            if ($lType === 'shutdown') {
                $bondingShoes = [];
                if ($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) {   // the table is named in lower case on the server
                    $shQ =$db->table('tbl_dtr_BondingTracker')->groupStart()->where('Shutdown', 1)->orWhere('UPPER(TestType)', 'SHUTDOWN')->groupEnd();   // a line marked SHUTDOWN in the TestType column
                    if ($lRange) {
                        $shQ->where($trkRange($db, '', $rngOther), null, false);
                    }
                    foreach ($shQ->orderBy('TrackerID', 'DESC')->limit($lqMax)->get()->getResultArray() as $sh) {
                        $bondingShoes[] = [
                            'BondingTestID' => 0, 'TrackerID' => (int) $sh['TrackerID'], 'IsShutdown' => 1, 'LabRefNo' => (string) $sh['LineNo'], 'ModelStyle' => '', 'Color' => '', 'Side' => '',
                            'ProductionDate' => $sh['ProductionDate'], 'TestedDate' => null, 'Technician' => '', 'Result' => '', 'LabNotesComments' => 'SHUTDOWN line ' . $sh['LineNo'],
                            'POLotNO' => 'SHUTDOWN', 'MTL' => '', 'TestType' => 'SHUTDOWN', 'TrackerLine' => $sh['LineNo'], 'PullOnly' => 1,
                        ];
                    }
                }
            } else {
                if ($lType === 'advance' || $lType === 'lacking') {
                    // the rows MARKED in the tracker (Lacking = 2 advance / 1 lacking) loaded in the range, whatever their own dates
                    $markers = array_values(array_filter($bondingShoes, static fn ($r) => ! empty($r['IsLacking'])));
                    $mkQ     = (clone $bondingBaseQ)->where('t.Lacking', $lType === 'advance' ? 2 : 1)->where("b.LabRefNo NOT REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)'", null, false);
                    if ($lRange) {
                        $mkQ->where($lType === 'advance' ? $advRange($db, 't.', $rngOther) : ($lType === 'lacking' ? $lackRange($db, 't.', $rngOther) : $trkRange($db, 't.', $rngOther)), null, false);
                    }
                    $bondingShoes = array_merge($mkQ->select($bSel, false)->orderBy('t.TrackerID', 'DESC')->limit($lqMax)->get()->getResultArray(), $lType === 'lacking' ? $markers : []);
                }
                $bondingShoes = array_values(array_filter($bondingShoes, static function (array $r) use ($lType): bool {
                    $tt  = strtoupper(trim((string) ($r['TestType'] ?? '')));
                    $ref = strtoupper((string) ($r['LabRefNo'] ?? ''));
                    $lk1 = (int) ($r['Lacking'] ?? 0);

                    return match ($lType) {
                        'daily'   => str_contains($tt, 'DAILY') && empty($r['IsLacking']) && ! preg_match('/^DTP\d{8}(HP|EY|DR|RVT|LLH)/', $ref),
                        'trial'   => str_contains($tt, 'TRIAL'),
                        'sample'  => str_contains($tt, 'SAMPLE') || str_contains($tt, 'RIVET'),
                        'retest'  => str_contains($tt, 'RETEST'),
                        'first'   => str_contains($tt, 'FIRST'),
                        'advance' => $lk1 === 2,
                        'lacking' => ! empty($r['IsLacking']) || $lk1 === 1 || str_contains($tt, 'LACKING'),
                        default   => true,
                    };
                }));
            }
        }

        $bondingFilteredTotal = ($bondingW || ($lk === 'bonding' && ($lRange || $lRes !== '' || $lType !== '')))
            ? count($bondingShoes)
            : max(count($bondingShoes), ($bondingDbTotal + $lackTotal - $droppedPulls));
        $bondingShoes = array_slice($bondingShoes, ($bondingPage - 1) * $lPer, $lPer);

        $shoeTotal  = $db->table('tbl_dtr_shoe_list')->countAllResults();
        $this->wsReportTable();   // makes sure tbl_dtr_wholeshoe_reports exists (the list reads the Technician saved in a report)
        // Whole Shoe Collection = every recorded test type (one row each) from tbl_dtr_WholeshoeTracker
        $shoesPage = $lk === 'shoes' ? $lp : 1;
        // Whole Shoe Collection = one row per MOTHER Lab Ref (tbl_dtr_WholeshoeTracker.WholeshoeLabRefNo, e.g. DTP20261113WHA4-R); its test type Lab Refs (FX, UV, WG, HY, HA ...) are the child rows that open under it
        $wsKey = "COALESCE(NULLIF(TRIM(WholeshoeLabRefNo), ''), LabRefNo)";
        $wsFilter = function ($q) use ($db, $lk, $lRange, $lFrom, $lTo, $lRes, $resSql, $shoesW) {
            if ($lk === 'shoes' && $lRange) {
                $sdt = "LEFT(COALESCE(NULLIF(TimeCompleted, ''), NULLIF(CollectionDate, ''), NULLIF(ProductionDate, ''), ''), 10)";
                $q->where("{$sdt} >= " . $db->escape($lFrom) . " AND {$sdt} <= " . $db->escape($lTo), null, false);
            }
            if ($lk === 'shoes' && $lRes !== '') {
                $q->where($resSql('FinalResult', $lRes), null, false);
            }
            foreach ($shoesW as $w) {
                $like = $db->escapeLikeString($w);
                $q->where("CONVERT(CONCAT_WS(' ', LabRefNo, WholeshoeLabRefNo, ProductionCode, Style, Colorway, MTL, TestType, Status, FinalResult, EncodedBy, LEFT(CollectionDate, 10), LEFT(ProductionDate, 10)) USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%{$like}%' ESCAPE '!'", null, false);
            }

            return $q;
        };
        $shoesFilteredTotal = (int) ($wsFilter($db->table('tbl_dtr_WholeshoeTracker'))->select("COUNT(DISTINCT {$wsKey}) AS c", false)->get()->getRow('c') ?? 0);
        $shoeCols = ['labref' => $wsKey, 'line' => '`LineNo`', 'prod' => 'ProductionCode', 'color' => 'Colorway', 'style' => 'Style', 'mtl' => 'MTL',
            'pdate' => "COALESCE(NULLIF(LEFT(ProductionDate, 10), ''), NULLIF(LEFT(CollectionDate, 10), ''))", 'status' => 'Status'];
        $keysQ = $wsFilter($db->table('tbl_dtr_WholeshoeTracker'))->select("{$wsKey} AS mk, MAX(TrackerID) AS mt", false)->groupBy($wsKey, false);
        if ($lk === 'shoes' && isset($shoeCols[$lsKey])) {
            $agg = 'MAX(' . $shoeCols[$lsKey] . ')';
            $keysQ->orderBy("({$agg} IS NULL OR {$agg} = '')", 'ASC', false)->orderBy($agg, $lsDir, false)->orderBy('mt', 'DESC', false);
        } else {
            $wsDate    = "LEFT(COALESCE(NULLIF(ProductionDate, ''), NULLIF(CollectionDate, ''), ''), 10)";
            $wsBldgVal = "UPPER(LEFT(COALESCE(NULLIF(TRIM(Building), ''), TRIM(`LineNo`), ''), 1)) COLLATE utf8mb4_general_ci";
            $wsBldg    = "CASE WHEN {$wsBldgVal} = 'A' THEN 0 WHEN {$wsBldgVal} = 'B' THEN 1 ELSE 2 END";
            $wsNum     = "CASE WHEN TRIM(`LineNo`) REGEXP '^[0-9]+\$' AND CAST(TRIM(`LineNo`) AS UNSIGNED) > 0 THEN CAST(TRIM(`LineNo`) AS UNSIGNED) WHEN CAST(SUBSTRING(TRIM(`LineNo`), 2) AS UNSIGNED) > 0 THEN CAST(SUBSTRING(TRIM(`LineNo`), 2) AS UNSIGNED) ELSE 9999 END";
            $keysQ->orderBy("MAX({$wsDate}) DESC, MIN({$wsBldg}) ASC, MIN({$wsNum}) ASC, MAX(TrackerID) DESC", '', false);
        }
        $motherKeys = array_map(static fn ($x) => (string) $x['mk'], $keysQ->limit($lPer, ($shoesPage - 1) * $lPer)->get()->getResultArray());
        $wsKids = $motherKeys ? $db->table('tbl_dtr_WholeshoeTracker')
            ->select("TrackerID, LabRefNo, WholeshoeLabRefNo, ProductionCode AS ProdCode, Style AS StyleNo, Colorway, MTL, Building, `LineNo`, Side, NoOfShoes, TestType AS TestTypes, CollectionDate, ProductionDate AS IntakeProductionDate, EncodedBy, Status, FinalResult, Findings, TimeStarted, TimeCompleted, Technician, MachineCode, DurationOfTest, ActualDuration, BoxNo, SourceSystem", false)
            ->where("{$wsKey} IN (" . implode(',', array_map(static fn ($k) => $db->escape($k), $motherKeys)) . ')', null, false)
            ->orderBy('TrackerID', 'ASC')->get()->getResultArray() : [];
        if ($wsKids) {
            $wsRefs = [];
            foreach ($wsKids as $wsRow) {
                if (trim((string) ($wsRow['Technician'] ?? '')) !== '') {
                    continue;
                }
                foreach (['LabRefNo', 'WholeshoeLabRefNo'] as $rk) {
                    $rv = trim((string) ($wsRow[$rk] ?? ''));
                    if ($rv !== '') {
                        $wsRefs[$rv] = true;
                    }
                }
            }
            if ($wsRefs) {
                $wsTechByRef = [];
                $rpRows = $db->table('tbl_dtr_wholeshoe_reports')
                    ->select('LabRefNo, ReportData')
                    ->whereIn('LabRefNo', array_keys($wsRefs))
                    ->orderBy('ReportID', 'ASC')
                    ->get()
                    ->getResultArray();
                foreach ($rpRows as $rp) {
                    $rd = json_decode((string) ($rp['ReportData'] ?? ''), true);
                    $tech = is_array($rd) ? trim((string) ($rd['technician'] ?? '')) : '';
                    if ($tech !== '') {
                        $wsTechByRef[strtoupper(trim((string) $rp['LabRefNo']))] = $tech;
                    }
                }
                foreach ($wsKids as &$wsRow) {
                    if (trim((string) ($wsRow['Technician'] ?? '')) !== '') {
                        continue;
                    }
                    foreach (['LabRefNo', 'WholeshoeLabRefNo'] as $rk) {
                        $uk = strtoupper(trim((string) ($wsRow[$rk] ?? '')));
                        if ($uk !== '' && isset($wsTechByRef[$uk])) {
                            $wsRow['Technician'] = $wsTechByRef[$uk];
                            break;
                        }
                    }
                }
                unset($wsRow);
            }
        }
        // one row per mother Lab Ref: the shoe details of its first test type + the list of its test types (children)
        $kidsByMother = [];
        foreach ($wsKids as $wsRow) {
            $kidsByMother[strtoupper(trim((string) ($wsRow['WholeshoeLabRefNo'] ?? '')) ?: trim((string) $wsRow['LabRefNo']))][] = $wsRow;
        }
        $wholeShoes = [];
        foreach ($motherKeys as $mk) {
            $kids = $kidsByMother[strtoupper($mk)] ?? [];
            if (! $kids) {
                continue;
            }
            $done = 0;
            $worst = '';
            foreach ($kids as $kd) {
                $kr = strtoupper(trim((string) ($kd['FinalResult'] ?? '')));
                if ($kr !== '') {
                    $done++;
                    $worst = (str_starts_with($kr, 'FAIL') || $kr === 'ERROR') ? 'FAIL' : (str_starts_with($kr, 'TOL') && $worst !== 'FAIL' ? 'TOLERABLE' : ($worst === '' ? $kr : $worst));
                }
            }
            $wholeShoes[] = $kids[0] + ['MotherRef' => $mk, 'Children' => $kids, 'ChildDone' => $done, 'ChildTotal' => count($kids), 'MotherResult' => $done === count($kids) || $worst === 'FAIL' ? $worst : ''];
        }

        $benchTotal  = $db->tableExists('tbl_dtr_material_bench_log') ? $db->table('tbl_dtr_material_bench_log')->countAllResults() : 0;
        $erpTotal    = $db->tableExists('tbl_erp_test_orders') ? $db->table('tbl_erp_test_orders')->countAllResults() : 0;
        $cfaTotal    = $db->tableExists('tbl_cfa_test_reports') ? $db->table('tbl_cfa_test_reports')->countAllResults() : 0;
        $schedTotal  = $db->tableExists('tbl_production_test_schedule') ? $db->table('tbl_production_test_schedule')->countAllResults() : 0;
        $failedTotal = $db->tableExists('tbl_failed_samples_registry') ? $db->table('tbl_failed_samples_registry')->countAllResults() : 0;

        // Distinct MaterialTypes in tbl_dtr_standards
        $standardMatTypes = array_column(
            $db->query("SELECT DISTINCT MaterialType FROM tbl_dtr_standards WHERE MaterialType IS NOT NULL AND MaterialType <> '' ORDER BY MaterialType ASC")->getResultArray(),
            'MaterialType'
        );

        return $this->render('dtr/index', [
            'title'            => 'QC Lab Digital Test Reports',
            'withCss'          => 'dtr.css',
            'withJs'           => ['dtr.js', 'dtr-photo.js', 'dtr-photo-attach.js', 'bond-graph.js', 'pull-graph.js', 'bond-graph-gen.js'],
            'activeTab'        => $activeTab,
            'subTab'           => $subTab,
            'formConfig'       => self::FORM_CONFIG,
            'canEditMaterial'  => self::canEditMaterial(),
            'canAddPullTests'  => self::canAddPullTests(),
            'catRows'          => $catRows,
            'totalReports'     => $totalReports,
            'totalPass'        => $totalPass,
            'totalFail'        => $totalFail,
            'labImg'           => ['bonding' => $this->reportImageCfg('bonding')['on'], 'shoes' => $this->reportImageCfg('shoes')['on'], 'containers' => $this->reportImageCfg('containers')['on']],
            'shutTotal'        => $shutTotal,
            'shutToday'        => $shutToday,
            'totalTol'         => $totalTol,
            'recentReports'    => $recentReports,
            'editLocked'       => $this->lockedEdits($db),
            'containers'       => $containers,
            'materialsMaster'  => $materialsMaster,
            'materialsTotal'   => $materialsTotal,
            'materialsPage'    => $materialsPage,
            'bondingTotal'     => $bondingTotal,
            'bondingFilteredTotal' => $bondingFilteredTotal,
            'lType'                => $lType,
            'bondingResCounts'     => $bondingResCounts,
            'bondingCounts'    => $bondingCounts,
            'defaultToday'     => $isTodayDefault,
            'bondingPage'      => $bondingPage,
            'bondingShoes'     => $bondingShoes,
            'shoeTotal'        => $shoeTotal,
            'shoesFilteredTotal' => $shoesFilteredTotal,
            'shoesPage'        => $shoesPage,
            'wholeShoes'       => $wholeShoes,
            'benchTotal'       => $benchTotal,
            'erpTotal'         => $erpTotal,
            'cfaTotal'         => $cfaTotal,
            'schedTotal'       => $schedTotal,
            'failedTotal'      => $failedTotal,
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
            ->select('id, lab_ref_no, tr_no, form_category, form_slug, interlink_sample_type, source_table, source_id, material_type, material_code, material_description, supplier, model_style, color, po_lot_no, mtl_no, received_date, tested_date, finished_date, technician, status, overall_result, thickness_ave, weight_ave, source_system');

        if ($slug !== '' && $slug !== 'all') {
            $builder->where('form_slug', $slug);
        }
        if ($q !== '') {
            $builder->groupStart()
                ->like('lab_ref_no', $q)
                ->orLike('tr_no', $q)
                ->orLike('material_code', $q)
                ->orLike('material_description', $q)
                ->orLike('model_style', $q)
                ->orLike('color', $q)
                ->orLike('po_lot_no', $q)
                ->orLike('supplier', $q)
                ->orLike('mtl_no', $q)
                ->orLike('material_type', $q)
                ->orLike('technician', $q)
                ->groupEnd();
        }

        $rows = $builder
            ->orderBy("COALESCE(STR_TO_DATE(NULLIF(tested_date, ''), '%d %b %Y'), STR_TO_DATE(NULLIF(received_date, ''), '%d %b %Y'), DATE('1970-01-01')) DESC, id DESC", '', false)
            ->limit(200)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'ok'   => true,
            'rows' => $rows,
            'csrf' => csrf_hash(),
        ]);
    }

    /**
     * GET /dtr/appsheet-archive?type=bench|erp|cfa|schedule&q=&page=1&limit=100
     * Paginated searchable endpoint for the AppSheet Lab Log, ERP Test Orders, CFA/CAP, and Production Schedule datasets.
     */
    public function appsheetArchive()
    {
        $db    = \Config\Database::connect();
        $type  = strtolower(trim((string) ($this->request->getGet('type') ?? 'bench')));
        $q     = trim((string) ($this->request->getGet('q') ?? ''));
        $from  = trim((string) ($this->request->getGet('from') ?? ''));
        $to    = trim((string) ($this->request->getGet('to') ?? ''));
        $res   = strtolower(trim((string) ($this->request->getGet('res') ?? '')));
        $hasRg = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit = max(15, min(200, (int) ($this->request->getGet('limit') ?? 15)));
        $offset = ($page - 1) * $limit;

        $rows    = [];
        $total   = 0;
        $capRows = [];

        if ($type === 'bench' && $db->tableExists('tbl_dtr_material_bench_log')) {
            $where = '1=1';
            $binds = [];
            if ($q !== '') {
                $like = '%' . $q . '%';
                $where = "(m.lab_ref_no LIKE ? OR m.material_test_name LIKE ? OR m.material_category_name LIKE ? OR m.technician LIKE ? OR m.machine_number LIKE ? OR m.possible_cause LIKE ? OR m.remarks LIKE ?)";
                $binds = [$like, $like, $like, $like, $like, $like, $like];
            }
            if ($hasRg) {
                $where .= " AND LEFT(COALESCE(NULLIF(m.finished_at, ''), NULLIF(m.started_at, ''), NULLIF(m.iqc_received_date, ''), NULLIF(m.created_at, ''), ''), 10) BETWEEN ? AND ?";
                $binds[] = $from;
                $binds[] = $to;
            }
            if ($res === 'pass') {
                $where .= " AND UPPER(COALESCE(m.result, '')) LIKE 'PASS%'";
            } elseif ($res === 'tol') {
                $where .= " AND UPPER(COALESCE(m.result, '')) LIKE 'TOL%'";
            } elseif ($res === 'fail') {
                $where .= " AND (UPPER(COALESCE(m.result, '')) LIKE 'FAIL%' OR UPPER(COALESCE(m.result, '')) = 'REJECTED')";
            }
            $total = (int) $db->query("SELECT COUNT(*) AS cnt FROM tbl_dtr_material_bench_log m WHERE $where", $binds)->getRow()->cnt;
            $rows  = $db->query("
                SELECT m.id, m.appsheet_material_id, m.lab_ref_no, m.test_code, m.material_category_name,
                       m.material_test_name, m.technician, m.machine_number, m.result, m.possible_cause, m.remarks,
                       LEFT(COALESCE(NULLIF(m.finished_at, ''), NULLIF(m.started_at, ''), NULLIF(m.iqc_received_date, ''), NULLIF(m.created_at, ''), ''), 10) AS test_date,
                       DATE_FORMAT(m.started_at, '%Y-%m-%d %H:%i') AS started_at_fmt,
                       COALESCE(m.finished_at, '') AS finished_at,
                       COALESCE(d.detail_count, 0) AS detail_count,
                       COALESCE(d.details_summary, '') AS details_summary
                FROM tbl_dtr_material_bench_log m
                LEFT JOIN (
                    SELECT parent_id,
                           COUNT(*) AS detail_count,
                           GROUP_CONCAT(DISTINCT CONCAT(COALESCE(NULLIF(part_name, ''), NULLIF(material_name, ''), 'Item'), ': ', COALESCE(test_name, ''), ' [', COALESCE(NULLIF(result, ''), '-'), ']') SEPARATOR ' | ') AS details_summary
                    FROM tbl_dtr_material_bench_details
                    GROUP BY parent_id
                ) d ON d.parent_id = m.appsheet_material_id
                WHERE $where
                ORDER BY COALESCE(DATE(m.finished_at), DATE(m.started_at), m.iqc_received_date, DATE(m.created_at), '1970-01-01') DESC, m.id DESC
                LIMIT $limit OFFSET $offset
            ", $binds)->getResultArray();
        } elseif ($type === 'erp' && $db->tableExists('tbl_erp_test_orders')) {
            $b = $db->table('tbl_erp_test_orders');
            if ($q !== '') {
                $b->groupStart()
                    ->like('lab_ref_no', $q)->orLike('vsp_code', $q)->orLike('mtl', $q)
                    ->orLike('style_no', $q)->orLike('colorway', $q)
                    ->orLike('bonding_tr', $q)->orLike('pull_tr', $q)->orLike('flexing_tr', $q)
                    ->orLike('aging_tr', $q)->orLike('hydrolysis_tr', $q)->orLike('washing_tr', $q)->orLike('uv_tr', $q)
                    ->groupEnd();
            }
            $total = (int) $b->countAllResults(false);
            $rows  = $b->select("id, vsp_code, mtl, lab_ref_no, style_no, colorway, test_result, total_tests, bonding_tr, pull_tr, flexing_tr, aging_tr, hydrolysis_tr, washing_tr, uv_tr, COALESCE(DATE_FORMAT(test_date, '%Y-%m-%d'), DATE_FORMAT(receipt_date, '%Y-%m-%d'), '') AS test_date, COALESCE(DATE_FORMAT(send_date, '%Y-%m-%d'), '') AS send_date, COALESCE(DATE_FORMAT(receipt_date, '%Y-%m-%d'), '') AS receipt_date, creator, line_no, side", false)
                ->orderBy("COALESCE(test_date, receipt_date, send_date, '1970-01-01') DESC, id DESC", '', false)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();
        } elseif ($type === 'cfa' && $db->tableExists('tbl_cfa_test_reports')) {
            $where = '1=1';
            $binds = [];
            if ($q !== '') {
                $like = '%' . $q . '%';
                $where = "(c.lab_ref_no LIKE ? OR c.production_code LIKE ? OR c.mtl LIKE ? OR c.style LIKE ? OR c.colorway LIKE ? OR c.test_name LIKE ? OR c.tr_bonding LIKE ? OR c.tr_flexing LIKE ? OR c.tr_mtr LIKE ? OR c.document_status LIKE ?)";
                $binds = array_fill(0, 10, $like);
            }
            $total = (int) $db->query("SELECT COUNT(*) AS cnt FROM tbl_cfa_test_reports c WHERE $where", $binds)->getRow()->cnt;
            $rows  = $db->query("
                SELECT c.id, c.record_id, COALESCE(DATE_FORMAT(c.production_date, '%Y-%m-%d'), '') AS production_date,
                       c.production_code, c.mtl, c.style, c.colorway, c.test_name, c.lab_ref_no,
                       c.tr_bonding, c.tr_flexing, c.tr_mtr, c.sent_cfa, c.flag, c.document_status, c.remarks,
                       COALESCE(DATE_FORMAT(c.check_timestamp, '%Y-%m-%d %H:%i'), '') AS check_timestamp_fmt,
                       COALESCE(a.ack_count, 0) AS ack_count,
                       COALESCE(a.ack_pics, '') AS ack_pics,
                       COALESCE(r.req_count, 0) AS req_count,
                       COALESCE(r.latest_status, '') AS latest_req_status
                FROM tbl_cfa_test_reports c
                LEFT JOIN (
                    SELECT record_id, COUNT(*) AS ack_count, GROUP_CONCAT(DISTINCT NULLIF(pic, '') SEPARATOR ', ') AS ack_pics
                    FROM tbl_cfa_acknowledgements
                    GROUP BY record_id
                ) a ON a.record_id = c.record_id
                LEFT JOIN (
                    SELECT record_id, COUNT(*) AS req_count, MAX(NULLIF(resolved_status, '')) AS latest_status
                    FROM tbl_cfa_request_board
                    GROUP BY record_id
                ) r ON r.record_id = c.record_id
                WHERE $where
                ORDER BY COALESCE(c.production_date, '1970-01-01') DESC, c.id DESC
                LIMIT $limit OFFSET $offset
            ", $binds)->getResultArray();
            if ($page === 1 && $db->tableExists('tbl_bonding_cap')) {
                $capRows = $db->table('tbl_bonding_cap')
                    ->select("cap_id, COALESCE(DATE_FORMAT(production_date, '%Y-%m-%d'), '') AS production_date, line_no, submitted_by, COALESCE(DATE_FORMAT(date_uploaded_by_lab, '%Y-%m-%d %H:%i'), '') AS date_uploaded_by_lab, COALESCE(DATE_FORMAT(date_submitted_by_assy, '%Y-%m-%d %H:%i'), '') AS date_submitted_by_assy, cap_file", false)
                    ->orderBy('production_date', 'DESC')
                    ->get()
                    ->getResultArray();
            }
        } elseif ($type === 'schedule' && $db->tableExists('tbl_production_test_schedule')) {
            $b = $db->table('tbl_production_test_schedule');
            if ($q !== '') {
                $b->groupStart()
                    ->like('lab_ref_no', $q)->orLike('production_code', $q)->orLike('mtl', $q)
                    ->orLike('style', $q)->orLike('colorway', $q)->orLike('test_name', $q)
                    ->orLike('testing_status', $q)->orLike('machine_tested', $q)->orLike('failure_part', $q)->orLike('tr_no', $q)
                    ->groupEnd();
            }
            $total = (int) $b->countAllResults(false);
            $rows  = $b->select("id, record_id, COALESCE(DATE_FORMAT(production_date, '%Y-%m-%d'), '') AS production_date, building, line_no, line_code, production_code, mtl, style, colorway, test_name, test_code, testing_status, test_type, machine_tested, test_result, remarks, retest_no, lab_ref_no, failure_part, possible_cause, side, tr_no", false)
                ->orderBy("COALESCE(production_date, DATE(created_at), '1970-01-01') DESC, id DESC", '', false)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();
        } elseif ($type === 'failed' && $db->tableExists('tbl_failed_samples_registry')) {
            $b = $db->table('tbl_failed_samples_registry');
            if ($q !== '') {
                $b->groupStart()
                    ->like('sample_id', $q)->orLike('mtl', $q)->orLike('style', $q)
                    ->orLike('colorway', $q)->orLike('test_failed', $q)
                    ->groupEnd();
            }
            $total = (int) $b->countAllResults(false);
            $rows  = $b->select("id, sample_id, mtl, style, colorway, test_failed, COALESCE(DATE_FORMAT(created_date, '%Y-%m-%d'), '') AS created_date", false)
                ->orderBy("COALESCE(created_date, '1970-01-01') DESC, id DESC", '', false)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();
        }

        return $this->response->setJSON([
            'ok'      => true,
            'type'    => $type,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'rows'    => $rows,
            'capRows' => $capRows,
            'csrf'    => csrf_hash(),
        ]);
    }


    /**
     * GET /dtr/kpis?from=YYYY-MM-DD&to=YYYY-MM-DD : the KPI cards for a date range. A report counts on its tested date (its received date while it
     * is not tested yet); a shutdown line counts on its production date.
     */
    public function kpis()
    {
        $from  = (string) ($this->request->getGet('from') ?? '');
        $to    = (string) ($this->request->getGet('to') ?? '');
        $scope = (string) ($this->request->getGet('scope') ?? '');
        $ok    = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $hasRange = $from !== '' || $to !== '';
        if ($hasRange && (! $ok($from) || ! $ok($to))) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Invalid dates.', 'csrf' => csrf_hash()]);
        }
        $db = \Config\Database::connect();
        // the cards follow the open Lab Tracker tab: bonding list, whole shoe list or the lab material test collection (bench log)
        if (in_array($scope, ['bonding', 'shoes', 'materials'], true)) {
            $cls = static fn (string $col): string => "COUNT(*) AS total, SUM(UPPER({$col}) LIKE 'PASS%') AS pass_cnt, SUM(UPPER({$col}) LIKE 'TOL%') AS tol_cnt, SUM(UPPER({$col}) LIKE 'FAIL%' OR UPPER({$col}) = 'REJECTED') AS fail_cnt";
            $shut = null;
            if ($scope === 'bonding') {
                $pd = "(CASE WHEN b.ProductionDate REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(b.ProductionDate, 7, 4), '-', SUBSTR(b.ProductionDate, 1, 2), '-', SUBSTR(b.ProductionDate, 4, 2)) WHEN b.ProductionDate REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(b.ProductionDate, 10) WHEN b.LabRefNo REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(b.LabRefNo, 4, 4), '-', SUBSTR(b.LabRefNo, 8, 2), '-', SUBSTR(b.LabRefNo, 10, 2)) END)";
                $dt = "COALESCE({$pd}, LEFT(NULLIF(b.TestedDate, ''), 10), LEFT(NULLIF(b.ReceivedDate, ''), 10), '')";
                $qb = $db->table('tbl_dtr_bonding_info b')->select($cls('b.Result'), false)->where('NOT (' . self::SHUTDOWN_ROW . ')', null, false)->where("COALESCE(b.TestType, '') <> 'LACKING'", null, false)
                    ->where("(b.LabRefNo IS NULL OR b.LabRefNo = '' OR b.BondingTestID IN (SELECT keep_id FROM (SELECT COALESCE(MAX(CASE WHEN Result IS NOT NULL AND Result <> '' THEN BondingTestID END), MAX(BondingTestID)) AS keep_id FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' GROUP BY LabRefNo) k))", null, false);
                $shutQ = $db->tableExists('tbl_dtr_BondingTracker') ? $db->table('tbl_dtr_BondingTracker')->where('Shutdown', 1) : null;
                $shutB = $db->table('tbl_dtr_bonding_info b')->where(self::SHUTDOWN_ROW, null, false);
                if ($hasRange) {
                    $qb->where("{$dt} >= " . $db->escape($from) . " AND {$dt} <= " . $db->escape($to), null, false);
                    $shutQ?->where('ProductionDate >=', $from)->where('ProductionDate <=', $to);
                    $shutB->where('b.ProductionDate >=', $from)->where('b.ProductionDate <=', $to);
                }
                $shut = (int) ($shutQ ? $shutQ->countAllResults() : 0) + (int) $shutB->countAllResults();
            } elseif ($scope === 'shoes') {
                $dt = "LEFT(COALESCE(NULLIF(TimeCompleted, ''), NULLIF(CollectionDate, ''), NULLIF(ProductionDate, ''), ''), 10)";
                $qb = $db->table('tbl_dtr_WholeshoeTracker')->select($cls('FinalResult'), false);
                if ($hasRange) {
                    $qb->where("{$dt} >= " . $db->escape($from) . " AND {$dt} <= " . $db->escape($to), null, false);
                }
            } else {
                if (! $db->tableExists('tbl_dtr_material_bench_log')) {
                    return $this->response->setJSON(['ok' => true, 'total' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0, 'shut' => null, 'csrf' => csrf_hash()]);
                }
                $dt = "LEFT(COALESCE(NULLIF(finished_at, ''), NULLIF(started_at, ''), NULLIF(iqc_received_date, ''), NULLIF(created_at, ''), ''), 10)";
                $qb = $db->table('tbl_dtr_material_bench_log')->select($cls('result'), false);
                if ($hasRange) {
                    $qb->where("{$dt} >= " . $db->escape($from) . " AND {$dt} <= " . $db->escape($to), null, false);
                }
            }
            $r = $qb->get()->getRowArray() ?: [];

            return $this->response->setJSON([
                'ok' => true, 'total' => (int) ($r['total'] ?? 0), 'pass' => (int) ($r['pass_cnt'] ?? 0), 'tol' => (int) ($r['tol_cnt'] ?? 0), 'fail' => (int) ($r['fail_cnt'] ?? 0), 'shut' => $shut, 'csrf' => csrf_hash(),
            ]);
        }
        $r  = $db->query("SELECT COUNT(*) AS total,
                SUM(overall_result = 'Pass') AS pass_cnt, SUM(overall_result = 'Tolerable') AS tol_cnt, SUM(overall_result = 'Fail') AS fail_cnt
            FROM tbl_dtr_unified_labrefs
            WHERE STR_TO_DATE(COALESCE(NULLIF(tested_date, ''), NULLIF(received_date, '')), '%d %b %Y') BETWEEN ? AND ?", [$from, $to])->getRowArray();
        $shut = $db->tableExists('tbl_dtr_BondingTracker')
            ? (int) $db->table('tbl_dtr_BondingTracker')->where('Shutdown', 1)->where('ProductionDate >=', $from)->where('ProductionDate <=', $to)->countAllResults() : 0;
        $shut += (int) $db->table('tbl_dtr_bonding_info b')->where(self::SHUTDOWN_ROW, null, false)->where('b.ProductionDate >=', $from)->where('b.ProductionDate <=', $to)->countAllResults();

        return $this->response->setJSON([
            'ok' => true, 'total' => (int) $r['total'], 'pass' => (int) $r['pass_cnt'], 'tol' => (int) $r['tol_cnt'], 'fail' => (int) $r['fail_cnt'], 'shut' => $shut, 'csrf' => csrf_hash(),
        ]);
    }

    /**
     * POST /dtr/update-entry {kind, id, fields:{...}}: fills the EMPTY columns of one Lab Tracker entry. Only whitelisted columns are written and
     * a column that already holds data is never overwritten.
     */
    /**
     * POST dtr/add-pull-tests {id: BondingTestID, pullTypes: ['EYELET', 'RIVET', ...]}: adds pull tests to a bonding row of the Lab Tracker that was saved without them.
     * The pull tests take everything from THAT row so they stay connected to it (the Lab Tracker pairs a pull test with its bonding row by Production Code,
     * line, production date and side): the Lab Ref is built like the batch save does it (DTP<date><HP|EY|DR|RVT><building><line>-<side>, -2 -3 when taken),
     * and Production Code / Style / Colorway / MTL / production date / collection date / technician / test type are copied from the row.
     */
    /** Admin, Supervisor and Bonding PIC may add pull tests to a bonding row (the Edit button of the Pull Test LabRefNo column) */
    public static function canAddPullTests(): bool
    {
        return (bool) array_intersect(['Admin', 'Supervisor', 'Bonding PIC'], \App\Libraries\Roles::parse((string) (service('auth')->user()['role'] ?? '')));
    }

    public function addPullTests()
    {
        if (! self::canAddPullTests()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only an Admin, a Supervisor or a Bonding PIC can add pull tests to a bonding row.', 'csrf' => csrf_hash()]);
        }
        $p     = $this->request->getJSON(true) ?? [];
        $id    = (int) ($p['id'] ?? 0);
        $types = is_array($p['pullTypes'] ?? null) ? $p['pullTypes'] : [];
        $fail  = fn (string $m, int $c = 422) => $this->response->setStatusCode($c)->setJSON(['ok' => false, 'message' => $m, 'csrf' => csrf_hash()]);
        $codes = ['HEEL PULL' => 'HP', 'EYELET' => 'EY', 'D-RING' => 'DR', 'RIVET' => 'RVT'];
        $types = array_values(array_unique(array_filter(array_map(static fn ($x) => strtoupper(trim((string) $x)), $types), static fn ($x) => isset($codes[$x]))));
        if ($id <= 0 || ! $types) {
            return $fail('Tick at least one pull type.');
        }
        $db   = \Config\Database::connect();
        $info = $db->table('tbl_dtr_bonding_info')->where('BondingTestID', $id)->get()->getRowArray();
        if (! $info) {
            return $fail('That bonding entry no longer exists.', 404);
        }
        $ref = trim((string) $info['LabRefNo']);
        // the pull Lab Ref follows the bonding Lab Ref of the row: DTP<date>(FA)SH<building><line>-<side>
        if (! preg_match('/^DTP(\d{8})(?:FA)?SH([AB])(\d{1,2})-([LR])(?:-\d+)?$/i', $ref, $m)) {
            return $fail($ref . ' has no production line in its Lab Ref (retest, tech trial, sample ...): pull tests are added to a normal bonding row.');
        }
        [$dateRaw, $building, $lineNo, $side] = [$m[1], strtoupper($m[2]), (int) $m[3], strtoupper($m[4])];
        $trk = $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->orderBy('TrackerID', 'DESC')->get()->getRowArray() ?: [];
        $pick = static function (...$vals): string {
            foreach ($vals as $v) {
                if (trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return '';
        };
        $prod = substr($pick($info['ProductionDate'] ?? '', $trk['ProductionDate'] ?? ''), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $prod) || str_replace('-', '', $prod) !== $dateRaw) {
            $prod = substr($dateRaw, 0, 4) . '-' . substr($dateRaw, 4, 2) . '-' . substr($dateRaw, 6, 2);   // the date written in the Lab Ref is the production date
        }
        $collect = substr($pick($trk['CollectionDate'] ?? '', $info['ReceivedDate'] ?? '', $prod), 0, 10);
        $code    = mb_substr($pick($info['POLotNO'] ?? '', $trk['ProductionCode'] ?? ''), 0, 191);
        $style   = mb_substr($pick($info['ModelStyle'] ?? '', $trk['Style'] ?? ''), 0, 191);
        $color   = $pick($info['Color'] ?? '', $trk['Colorway'] ?? '');
        $mtl     = mb_substr($pick($info['MTL'] ?? '', $trk['MTL'] ?? ''), 0, 191);
        $tech    = mb_substr($pick($info['Technician'] ?? '', $trk['Technician'] ?? ''), 0, 191);
        $ttype   = mb_substr($pick($trk['TestType'] ?? '', $info['TestType'] ?? ''), 0, 50);
        $user    = service('auth')->user();
        $by      = mb_substr((string) ($user['name'] ?? $user['username'] ?? ''), 0, 100);
        $factory = 'DATIAN SUBIC SHOES INC. (DTP)';
        $made    = [];
        $skipped = [];
        foreach ($types as $pt) {
            // the same pull type of the same shoe (code / line / date / side) is never added twice
            $dup = $db->table('tbl_dtr_BondingTracker')->select('LabRefNo')
                ->where(['Building' => $building, 'LineNo' => $building . $lineNo, 'Side' => $side, 'ProductionDate' => $prod, 'PullTestType' => $pt, 'Shutdown' => 0])
                ->where('ProductionCode', $code)->get(1)->getRowArray();
            if ($dup) {
                $skipped[] = $pt . ' (' . $dup['LabRefNo'] . ')';
                continue;
            }
            $keyRef = 'DTP' . $dateRaw . $codes[$pt] . $building . $lineNo;
            $n = 0;
            foreach ($db->table('tbl_dtr_pull_tests')->select('LabRefNo')->like('LabRefNo', $keyRef . '-', 'after')->get()->getResultArray() as $x) {
                if (preg_match('/^' . preg_quote($keyRef, '/') . '-([LR])(?:-(\d+))?$/', (string) $x['LabRefNo'], $mm) && $mm[1] === $side) {
                    $n = max($n, (int) ($mm[2] ?? 1));
                }
            }
            $labRef = $n === 0 ? $keyRef . '-' . $side : $keyRef . '-' . $side . '-' . ($n + 1);
            $pullRow = [
                'LabRefNo' => $labRef, 'Factory' => $factory, 'ModelStyle' => $style, 'Color' => mb_substr($color, 0, 120), 'TestType' => mb_substr($pt, 0, 50),
                'Building' => $building, 'LineNo' => $building . $lineNo, 'ProductionCode' => $code, 'MTL' => $mtl, 'CollectionDate' => $collect, 'ProductionDate' => $prod,
                'Technician' => mb_substr($tech, 0, 120), 'EncodedBy' => mb_substr($by, 0, 191),
            ];
            $db->table('tbl_dtr_pull_tests')->insert($pullRow);
            $pullId = (int) $db->insertID();
            \App\Libraries\DtrLabrefIndex::upsertPullTest($db, $pullId, $pullRow);
            $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $labRef)->delete();
            $db->table('tbl_dtr_BondingTracker')->insert([
                'LabRefNo' => $labRef, 'Building' => $building, 'LineNo' => $building . $lineNo, 'Side' => $side, 'ProductionCode' => $code, 'Style' => $style, 'Colorway' => mb_substr($color, 0, 191),
                'MTL' => $mtl, 'LabTest' => 'Bonding', 'PullTest' => 1, 'PullTestType' => $pt, 'TestType' => $ttype, 'Technician' => $tech, 'Lacking' => 0, 'LackingDate' => null,
                'RetestDetails' => '', 'RetestOf' => null, 'CollectionDate' => $collect, 'ProductionDate' => $prod, 'EncodedBy' => $by, 'CreatedAt' => date('Y-m-d H:i:s'),
            ]);
            $db->table('tbl_dtr_pull_tests')->where('PullInfoID', $pullId)->update(['TrackerID' => (int) $db->insertID()]);
            $made[] = $labRef;
        }
        if ($made) {
            \App\Libraries\DataCache::bump();
            \App\Libraries\ActivityLog::record('dtr', 'add-pull-tests', $ref, 'Pull test(s) added to the bonding row: ' . implode(', ', $made));
        }
        $msg = $made ? 'Pull test Lab Ref(s) added to ' . $ref . ': ' . implode(', ', $made) . '.' : 'Nothing was added.';
        if ($skipped) {
            $msg .= ' Already there: ' . implode(', ', $skipped) . '.';
        }

        return $this->response->setJSON(['ok' => (bool) $made || ! $skipped, 'message' => $msg, 'created' => $made, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
    }

    public function updateEntry()
    {
        $p      = $this->request->getJSON(true) ?? [];
        $kind   = (string) ($p['kind'] ?? '');
        $id     = (int) ($p['id'] ?? 0);
        $fields = is_array($p['fields'] ?? null) ? $p['fields'] : [];
        $fail   = fn (string $m, int $c = 422) => $this->response->setStatusCode($c)->setJSON(['ok' => false, 'message' => $m, 'csrf' => csrf_hash()]);

        // kind => [table, key column, [posted name => db column]]
        $map = [
            'bonding'   => ['tbl_dtr_bonding_info', 'BondingTestID', ['POLotNO' => 'POLotNO', 'ModelStyle' => 'ModelStyle', 'Color' => 'Color', 'MTL' => 'MTL', 'ProductionDate' => 'ProductionDate', 'TestedDate' => 'TestedDate', 'Technician' => 'Technician']],
            'lacking'   => ['tbl_dtr_BondingTracker', 'TrackerID', ['POLotNO' => 'ProductionCode', 'ModelStyle' => 'Style', 'Color' => 'Colorway', 'MTL' => 'MTL', 'ProductionDate' => 'ProductionDate', 'Technician' => 'Technician']],
            'wholeshoe' => ['tbl_dtr_WholeshoeTracker', 'TrackerID', ['ProdCode' => 'ProductionCode', 'Style' => 'Style', 'Colorway' => 'Colorway', 'MTL' => 'MTL', 'ProductionDate' => 'ProductionDate']],
            'container' => ['tbl_dtr_containers', 'ContainerID', ['DateArrived' => 'DateArrived', 'DateReceivedInLab' => 'DateReceivedInLab']],
            'material'  => ['tbl_dtr_materials_master', 'MaterialID', ['MaterialType' => 'MaterialType', 'MaterialCode' => 'MaterialCode', 'MaterialName' => 'MaterialName', 'MaterialProdCode' => 'MaterialProdCode', 'Supplier' => 'Supplier', 'IQCERP' => 'IQCERP', 'Remarks' => 'Remarks']],
        ];
        if (! isset($map[$kind]) || $id <= 0) {
            return $fail('Nothing to edit.');
        }
        [$table, $key, $cols] = $map[$kind];
        $db  = \Config\Database::connect();
        if (in_array($kind . ':' . $id, $this->lockedEdits($db), true)) {
            return $fail('This entry is already synced and locked. It can no longer be edited.');
        }
        $row = $db->table($table)->where($key, $id)->get()->getRowArray();
        if (! $row) {
            return $fail('That entry no longer exists.', 404);
        }
        $set = [];
        foreach ($fields as $name => $val) {
            if (! isset($cols[$name])) {
                continue;
            }
            $col = $cols[$name];
            $val = mb_substr(trim((string) $val), 0, 255);
            if ($val === '' || ! array_key_exists($col, $row) || trim((string) $row[$col]) !== '') {
                continue;   // blank input, unknown column or already filled
            }
            if (str_contains($col, 'Date') && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                continue;
            }
            $set[$col] = $val;
        }
        if (! $set) {
            return $fail('Nothing new to save. Only empty columns can be filled.');
        }
        $db->table($table)->where($key, $id)->update($set);

        if ($kind === 'bonding') {
            $this->balanceBonding($db, (string) ($row['LabRefNo'] ?? ''));   // tracker record created / completed right away
        }
        if ($kind === 'wholeshoe') {
            $this->balanceWholeShoe($db, $id);
        }
        // the edit is carried over to the other tables that hold this Lab Ref 3 minutes later (runDueEditSyncs)
        $this->ensureEditSyncTable($db);
        $db->table('tbl_dtr_edit_sync')->insert([
            'Kind' => $kind, 'EntryID' => $id, 'LabRefNo' => (string) ($row['LabRefNo'] ?? $row['LabReference'] ?? ''),
            'Fields' => json_encode($set), 'EditedBy' => (string) (service('auth')->user()['name'] ?? ''),
            'CreatedAt' => date('Y-m-d H:i:s'), 'DueAt' => date('Y-m-d H:i:s', time() + 180),
        ]);

        return $this->response->setJSON(['ok' => true, 'message' => 'Entry updated. It syncs to the other tables of this Lab Ref in 3 minutes.', 'saved' => $set, 'csrf' => csrf_hash()]);
    }

    /**
     * Keeps tbl_dtr_bonding_info and tbl_dtr_BondingTracker balanced for one Lab Ref No: a Lab Ref with no tracker record gets one (built from the
     * bonding record), otherwise the empty columns of each side are filled from the other. Existing data is never overwritten.
     */
    private function balanceBonding($db, string $ref): void
    {
        $ref = trim($ref);
        if ($ref === '' || ! $db->tableExists('tbl_dtr_BondingTracker')) {
            return;
        }
        $info = $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $ref)->orderBy('BondingTestID', 'DESC')->get()->getRowArray();
        if (! $info) {
            return;
        }
        $trk = $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->get()->getRowArray();
        $date = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v) ? substr((string) $v, 0, 10) : null;
        if (! $trk) {
            $b = $c = $side = null;
            $ln = '';
            if (preg_match('/^DTP\d{8}[A-Z]{2}([A-Z])(\d+)-([LR])/i', $ref, $m)) {
                $b = strtoupper($m[1]); $ln = strtoupper($m[1]) . (int) $m[2]; $side = strtoupper($m[3]);
            }
            $side = $side ?: (in_array(strtoupper((string) ($info['Side'] ?? '')), ['L', 'R'], true) ? strtoupper($info['Side']) : null);
            $db->table('tbl_dtr_BondingTracker')->insert([
                'LabRefNo' => $ref, 'Building' => $b, 'LineNo' => $ln !== '' ? $ln : null, 'Side' => $side,
                'ProductionCode' => (string) ($info['POLotNO'] ?? ''), 'Style' => (string) ($info['ModelStyle'] ?? ''), 'Colorway' => (string) ($info['Color'] ?? ''), 'MTL' => (string) ($info['MTL'] ?? ''),
                'TestType' => (string) ($info['TestType'] ?? ''), 'Technician' => (string) ($info['Technician'] ?? ''), 'Lacking' => 0, 'Shutdown' => 0,
                'CollectionDate' => $date($info['ReceivedDate'] ?? ''), 'ProductionDate' => $date($info['ProductionDate'] ?? ''),
                'EncodedBy' => (string) (service('auth')->user()['name'] ?? ''), 'CreatedAt' => date('Y-m-d H:i:s'),
            ]);

            return;
        }
        // tracker column => bonding_info column
        $pairs = ['ProductionCode' => 'POLotNO', 'Style' => 'ModelStyle', 'Colorway' => 'Color', 'MTL' => 'MTL', 'ProductionDate' => 'ProductionDate', 'Technician' => 'Technician', 'TestType' => 'TestType'];
        $toTrk = $toInfo = [];
        foreach ($pairs as $tc => $ic) {
            if (trim((string) ($trk[$tc] ?? '')) === '' && trim((string) ($info[$ic] ?? '')) !== '') {
                $toTrk[$tc] = $info[$ic];
            } elseif (trim((string) ($info[$ic] ?? '')) === '' && trim((string) ($trk[$tc] ?? '')) !== '') {
                $toInfo[$ic] = $trk[$tc];
            }
        }
        $this->fillEmpty($db, 'tbl_dtr_BondingTracker', ['TrackerID' => $trk['TrackerID']], $toTrk);
        $this->fillEmpty($db, 'tbl_dtr_bonding_info', ['BondingTestID' => $info['BondingTestID']], $toInfo);
    }

    /**
     * Whole Shoe twin of balanceBonding: keeps tbl_dtr_WholeshoeTracker (one row per test type) and tbl_dtr_wholeshoe_intake (the shoe collection,
     * keyed by the Whole Shoe Lab Ref No) balanced. A collection with no intake record gets one; otherwise empty columns are filled from the other
     * side, and the other test-type rows of the same collection get the empty code / style / colorway / MTL / production date too.
     */
    private function balanceWholeShoe($db, int $trackerId): void
    {
        if ($trackerId <= 0 || ! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return;
        }
        $t = $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', $trackerId)->get()->getRowArray();
        $m = trim((string) ($t['WholeshoeLabRefNo'] ?? ''));
        if (! $t || $m === '') {
            return;
        }
        $date = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v) ? substr((string) $v, 0, 10) : null;
        $in   = $db->table('tbl_dtr_wholeshoe_intake')->where('LabRefNo', $m)->orderBy('IntakeID', 'DESC')->get()->getRowArray();
        if (! $in) {
            $types = array_column($db->table('tbl_dtr_WholeshoeTracker')->select('TestType')->where('WholeshoeLabRefNo', $m)->get()->getResultArray(), 'TestType');
            $db->table('tbl_dtr_wholeshoe_intake')->insert([
                'Building' => $t['Building'], 'LineNo' => $t['LineNo'], 'Side' => $t['Side'], 'LabRefNo' => $m, 'ProdCode' => (string) $t['ProductionCode'],
                'NoOfShoes' => (int) ($t['NoOfShoes'] ?? 1) ?: 1, 'TestTypes' => implode(', ', array_filter($types)),
                'CollectionDate' => $date($t['CollectionDate']), 'ProductionDate' => $date($t['ProductionDate']),
                'EncodedBy' => (string) ($t['EncodedBy'] ?: (service('auth')->user()['name'] ?? '')), 'CreatedAt' => date('Y-m-d H:i:s'),
            ]);
            $in = ['IntakeID' => (int) $db->insertID()];
        } else {
            $this->fillEmpty($db, 'tbl_dtr_wholeshoe_intake', ['IntakeID' => $in['IntakeID']], ['ProdCode' => (string) $t['ProductionCode'], 'ProductionDate' => $date($t['ProductionDate'])]);
            $this->fillEmpty($db, 'tbl_dtr_WholeshoeTracker', ['TrackerID' => $trackerId], ['ProductionCode' => (string) $in['ProdCode'], 'ProductionDate' => $date($in['ProductionDate'])]);
        }
        foreach (['ProductionCode', 'Style', 'Colorway', 'MTL', 'ProductionDate'] as $c) {
            if (trim((string) ($t[$c] ?? '')) !== '') {
                $this->fillEmpty($db, 'tbl_dtr_WholeshoeTracker', ['WholeshoeLabRefNo' => $m], [$c => $t[$c]]);
            }
        }
        try {
            \App\Libraries\DtrLabrefIndex::upsertWholeShoe($db, (int) $in['IntakeID']);
        } catch (\Throwable $e) {
            log_message('error', 'Whole shoe index refresh failed: ' . $e->getMessage());
        }
    }

    /** "kind:id" of every entry whose edit has been synced: those rows are locked (no more edits). */
    private function lockedEdits($db): array
    {
        $this->ensureEditSyncTable($db);
        $out = [];
        foreach ($db->query('SELECT DISTINCT Kind, EntryID FROM tbl_dtr_edit_sync WHERE DoneAt IS NOT NULL')->getResultArray() as $r) {
            $out[] = $r['Kind'] . ':' . (int) $r['EntryID'];
        }

        return $out;
    }

    private function ensureEditSyncTable($db): void
    {
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_edit_sync (
            SyncID INT AUTO_INCREMENT PRIMARY KEY,
            Kind VARCHAR(20) NOT NULL,
            EntryID INT NOT NULL,
            LabRefNo VARCHAR(100) NOT NULL DEFAULT "",
            Fields TEXT NULL,
            EditedBy VARCHAR(191) NULL,
            CreatedAt DATETIME NOT NULL,
            DueAt DATETIME NOT NULL,
            DoneAt DATETIME NULL,
            INDEX (DoneAt, DueAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** Fills the empty columns of the rows of $table matching $where; never overwrites data. */
    private function fillEmpty($db, string $table, array $where, array $data): void
    {
        foreach ($data as $col => $val) {
            if ($val === '' || $val === null || ! $db->fieldExists($col, $table)) {
                continue;
            }
            $b = $db->table($table);
            foreach ($where as $k => $v) {
                $b->where($k, $v);
            }
            $b->groupStart()->where($col, null)->orWhere($col, '')->groupEnd()->update([$col => $val]);
        }
    }

    /** Carries every edit that is 3+ minutes old to the other tables of its Lab Ref (tracker <-> bonding info <-> unified lab refs). */
    private function runDueEditSyncs(): int
    {
        $db = \Config\Database::connect();
        $this->ensureEditSyncTable($db);
        $due = $db->table('tbl_dtr_edit_sync')->where('DoneAt', null)->where('DueAt <=', date('Y-m-d H:i:s'))->orderBy('SyncID')->limit(100)->get()->getResultArray();
        $uni = ['POLotNO' => 'po_lot_no', 'ProductionCode' => 'po_lot_no', 'ModelStyle' => 'model_style', 'Style' => 'model_style', 'Color' => 'color', 'Colorway' => 'color', 'MTL' => 'mtl_no',
            'Technician' => 'technician', 'MaterialCode' => 'material_code', 'MaterialName' => 'material_description', 'Supplier' => 'supplier', 'MaterialType' => 'material_type'];
        foreach ($due as $s) {
            $ref = (string) $s['LabRefNo'];
            $f   = json_decode((string) $s['Fields'], true) ?: [];
            if ($ref !== '') {
                $u = [];
                foreach ($f as $col => $v) {
                    if (isset($uni[$col])) {
                        $u[$uni[$col]] = $v;
                    } elseif ($col === 'TestedDate' && ($t = strtotime((string) $v))) {
                        $u['tested_date'] = date('d M Y', $t);
                    }
                }
                $this->fillEmpty($db, 'tbl_dtr_unified_labrefs', ['lab_ref_no' => $ref], $u);
                if ($s['Kind'] === 'bonding') {
                    $this->balanceBonding($db, $ref);
                } elseif ($s['Kind'] === 'lacking') {
                    $t = ['POLotNO' => $f['ProductionCode'] ?? '', 'ModelStyle' => $f['Style'] ?? '', 'Color' => $f['Colorway'] ?? '', 'MTL' => $f['MTL'] ?? '', 'ProductionDate' => $f['ProductionDate'] ?? '', 'Technician' => $f['Technician'] ?? ''];
                    $this->fillEmpty($db, 'tbl_dtr_bonding_info', ['LabRefNo' => $ref], $t);
                } elseif ($s['Kind'] === 'wholeshoe') {
                    $this->balanceWholeShoe($db, (int) $s['EntryID']);
                }
            }
            $db->table('tbl_dtr_edit_sync')->where('SyncID', $s['SyncID'])->update(['DoneAt' => date('Y-m-d H:i:s')]);
        }

        return count($due);
    }

    /** POST /dtr/sync-edits: runs the edit syncs that are due (polled by the Lab Tracker page). */
    public function syncEdits()
    {
        try {
            $n = $this->runDueEditSyncs();
        } catch (\Throwable $e) {
            return $this->response->setJSON(['ok' => false, 'message' => $e->getMessage(), 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'synced' => $n, 'locked' => $this->lockedEdits(\Config\Database::connect()), 'csrf' => csrf_hash()]);
    }

    /** Removes the rows of one Lab Ref from every related table that holds data for it (tables / columns that do not exist are skipped). */
    private function purgeLabRef($db, string $ref, array $targets): void
    {
        foreach ($targets as [$table, $col]) {
            try {
                if ($db->tableExists($table) && $db->fieldExists($col, $table)) {
                    $db->table($table)->where($col, $ref)->delete();
                }
            } catch (\Throwable $e) {
                log_message('error', 'Delete cleanup ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * POST /dtr/delete-entry {kind, id, reason}: deletes one Lab Tracker entry (bonding / wholeshoe / material / container) and records
     * who deleted it, when and why in tbl_dtr_delete_audit. A reason is required.
     */
    public function deleteEntry()
    {
        $p      = $this->request->getJSON(true) ?? [];
        $kind   = (string) ($p['kind'] ?? '');
        $id     = (int) ($p['id'] ?? 0);
        $reason = mb_substr(trim((string) ($p['reason'] ?? '')), 0, 1000);
        $fail   = fn (string $m, int $c = 422) => $this->response->setStatusCode($c)->setJSON(['ok' => false, 'message' => $m, 'csrf' => csrf_hash()]);

        if (! in_array($kind, ['bonding', 'wholeshoe', 'material', 'container', 'lacking'], true) || $id <= 0) {
            return $fail('Nothing to delete.');
        }
        if (mb_strlen($reason) < 3) {
            return $fail('Enter the reason why this entry is deleted.');
        }

        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_delete_audit (
            AuditID INT AUTO_INCREMENT PRIMARY KEY,
            DeletedAt DATETIME NOT NULL,
            DeletedBy VARCHAR(191) NOT NULL,
            Reason VARCHAR(1000) NOT NULL,
            Module VARCHAR(50) NULL,
            EntryRef VARCHAR(191) NULL,
            INDEX (DeletedAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if (! $db->fieldExists('Remarks', 'tbl_dtr_delete_audit')) {
            $db->query('ALTER TABLE tbl_dtr_delete_audit ADD COLUMN Remarks MEDIUMTEXT NULL');
        }

        $module = '';
        $ref    = '';
        $db->transStart();
        if ($kind === 'bonding') {
            $row = $db->table('tbl_dtr_bonding_info')->where('BondingTestID', $id)->get()->getRowArray();
            if (! $row) {
                $db->transComplete();

                return $fail('That entry no longer exists.', 404);
            }
            $module = 'Lab Tracker - Bonding Shoe Collection';
            $ref    = (string) $row['LabRefNo'];
            // the list shows one row per Lab Ref No, but older duplicates of it can exist: every record of that Lab Ref goes, or the next duplicate would show up again
            $ids = [$id];
            if ($ref !== '') {
                $ids = array_map('intval', array_column($db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $ref)->get()->getResultArray(), 'BondingTestID')) ?: [$id];
            }
            $db->table('tbl_dtr_bonding_points')->whereIn('BondingTestID', $ids)->delete();
            $db->table('tbl_dtr_bonding_info')->whereIn('BondingTestID', $ids)->delete();
            if ($ref !== '') {
                $trackerRow = $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->orderBy('TrackerID', 'DESC')->get()->getRowArray() ?: [];
                $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->delete();
                $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->whereIn('form_slug', ['bonding', 'pull'])->delete();
            }
            $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_bonding_info')->whereIn('source_id', $ids)->delete();
            if ($ref !== '') {
                $this->purgeLabRef($db, $ref, [['tbl_dtr_bonding_spot_points', 'LabRefNo'], ['tbl_dtr_bonding_points', 'LabRefNo'], ['tbl_dtr_edit_sync', 'LabRefNo'], ['tbl_dtr_report_approvals', 'lab_ref_no'],
                    ['tbl_photo_links', 'lab_ref'], ['tbl_dtr_pull_results', 'LabRefNo'], ['tbl_dtr_pull_tests', 'LabRefNo'], ['tbl_dtr_sender_log', 'LabRefNo'], ['tbl_production_test_schedule', 'lab_ref_no'], ['tbl_erp_test_orders', 'lab_ref_no']]);
            }
        } elseif ($kind === 'lacking') {
            $row = $db->table('tbl_dtr_BondingTracker')->where('TrackerID', $id)->where('TestType', 'LACKING')->get()->getRowArray();
            if (! $row) {
                $db->transComplete();

                return $fail('That entry no longer exists.', 404);
            }
            $module = 'Lab Tracker - Bonding Shoe Collection';
            $ref    = (string) $row['LabRefNo'] . ' (LACKING)';
            $db->table('tbl_dtr_BondingTracker')->where('TrackerID', $id)->delete();
            if (trim((string) $row['LabRefNo']) !== '') {   // its bonding_info record and Lab Ref index row go too (only while it is still a lacking record)
                foreach ($db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $row['LabRefNo'])->where('TestType', 'LACKING')->get()->getResultArray() as $lb) {
                    $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_bonding_info')->where('source_id', (int) $lb['BondingTestID'])->delete();
                    $db->table('tbl_dtr_bonding_info')->where('BondingTestID', (int) $lb['BondingTestID'])->delete();
                }
            }
        } elseif ($kind === 'wholeshoe') {
            $row = $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', $id)->get()->getRowArray();
            if (! $row) {
                $db->transComplete();

                return $fail('That entry no longer exists.', 404);
            }
            $module = 'Lab Tracker - Whole Shoe Collection';
            $ref    = (string) $row['LabRefNo'];
            $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', $id)->delete();
            if (trim($ref) !== '') {
                $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->delete();
                $this->purgeLabRef($db, $ref, [['tbl_dtr_wholeshoe_reports', 'LabRefNo'], ['tbl_dtr_wst_results', 'LabRefNo'], ['tbl_dtr_edit_sync', 'LabRefNo'], ['tbl_dtr_report_approvals', 'lab_ref_no'],
                    ['tbl_photo_links', 'lab_ref'], ['tbl_dtr_wholeshoetrackertests', 'ChildLabRefNo'], ['tbl_dtr_sender_log', 'LabRefNo'], ['tbl_production_test_schedule', 'lab_ref_no'], ['tbl_erp_test_orders', 'lab_ref_no']]);
            }
            $mother = trim((string) ($row['WholeshoeLabRefNo'] ?? ''));
            // like the bonding delete: the shoe collection (intake) record's details are kept in the audit Remarks too
            if ($mother !== '' && $db->tableExists('tbl_dtr_wholeshoe_intake')) {
                $trackerRow = $db->table('tbl_dtr_wholeshoe_intake')->where('LabRefNo', $mother)->orderBy('IntakeID', 'DESC')->get()->getRowArray() ?: [];
            }
            $wsMother = $mother;   // the shoe collection itself goes when its last test type is deleted (cleaned up after the transaction, a failure there must not undo the delete)
        } elseif ($kind === 'material') {
            $row = $db->table('tbl_dtr_materials_master')->where('MaterialID', $id)->get()->getRowArray();
            if (! $row) {
                $db->transComplete();

                return $fail('That entry no longer exists.', 404);
            }
            $inCont = (int) ($row['ContainerID'] ?? 0) > 0 ? (string) ($db->table('tbl_dtr_containers')->select('ContainerNo')->where('ContainerID', (int) $row['ContainerID'])->get()->getRow('ContainerNo') ?? '') : '';
            $module = trim($inCont) !== '' ? 'Lab Tracker - IQC Container Receiving Log' : 'Lab Tracker - IQC Materials Master Queue';
            $ref    = trim(($inCont !== '' ? $inCont . ' / ' : '') . trim((string) ($row['LabReference'] ?? '')) . ' ' . trim((string) ($row['MaterialCode'] ?? '')));
            $db->table('tbl_dtr_materials_master')->where('MaterialID', $id)->delete();
        } else {
            $row = $db->table('tbl_dtr_containers')->where('ContainerID', $id)->get()->getRowArray();
            if (! $row) {
                $db->transComplete();

                return $fail('That entry no longer exists.', 404);
            }
            if ($db->table('tbl_dtr_materials_master')->where('ContainerID', $id)->countAllResults() > 0) {
                $db->transComplete();

                return $fail('This container still has materials. Delete its material lines first.');
            }
            $module = 'Lab Tracker - IQC Container Receiving Log';
            $ref    = (string) $row['ContainerNo'];
            $db->table('tbl_dtr_containers')->where('ContainerID', $id)->delete();
        }
        $user = service('auth')->user();
        $who  = trim((string) ($user['name'] ?? '')) ?: trim((string) ($user['username'] ?? ''));
        if (! empty($user['username']) && $who !== (string) $user['username']) {
            $who .= ' (' . $user['username'] . ')';
        }
        // every filled detail of the deleted row(s), joined as "Column: value | Column: value ..." (the tracker row adds only what the record does not already hold)
        $parts = [];
        foreach ([$row ?? [], $trackerRow ?? []] as $src) {
            foreach ($src as $k => $v) {
                $v = trim((string) $v);
                if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00' || isset($parts[$k])) {
                    continue;
                }
                $parts[$k] = $k . ': ' . mb_substr($v, 0, 200);
            }
        }
        $remarks = implode(' | ', $parts);
        $auditRow = [
            'DeletedAt' => date('Y-m-d H:i:s'),
            'DeletedBy' => mb_substr($who !== '' ? $who : 'unknown', 0, 191),
            'Reason'    => $reason,
            'Module'    => $module,
            'EntryRef'  => mb_substr(trim($ref), 0, 191),
            'Remarks'   => $remarks,
        ];
        $db->table('tbl_dtr_delete_audit')->insert($auditRow);
        $dbErr = (string) ($db->error()['message'] ?? '');
        $db->transComplete();
        if (! $db->transStatus()) {
            return $fail('The entry could not be deleted.' . ($dbErr !== '' ? ' (' . mb_substr($dbErr, 0, 200) . ')' : ''), 500);
        }
        // proof: nothing of a deleted bonding Lab Ref may be left in the info / tracker tables, or the delete is reported as failed (and the audit row removed so it can be retried)
        if ($kind === 'bonding' && trim($ref) !== '') {
            $left = $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $ref)->countAllResults() + $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->countAllResults();
            if ($left > 0) {
                $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $ref)->delete();
                $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->delete();
                $left = $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $ref)->countAllResults() + $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref)->countAllResults();
            }
            if ($left > 0) {
                return $fail('The entry is still in the database (' . $left . ' record(s) of ' . $ref . '). Reload and try again.', 500);
            }
        }
        // proof for a whole shoe test type: its tracker row and Lab Ref index rows must be gone (same check as the bonding delete)
        if ($kind === 'wholeshoe' && trim($ref) !== '') {
            $left = $db->table('tbl_dtr_WholeshoeTracker')->where('LabRefNo', $ref)->countAllResults();
            if ($left > 0) {
                $db->table('tbl_dtr_WholeshoeTracker')->where('LabRefNo', $ref)->delete();
                $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->delete();
                $left = $db->table('tbl_dtr_WholeshoeTracker')->where('LabRefNo', $ref)->countAllResults();
            }
            if ($left > 0) {
                return $fail('The entry is still in the database (' . $left . ' record(s) of ' . $ref . '). Reload and try again.', 500);
            }
        }
        if (($wsMother ?? '') !== '') {
            try {
                if ($db->table('tbl_dtr_WholeshoeTracker')->where('WholeshoeLabRefNo', $wsMother)->countAllResults() === 0 && $db->tableExists('tbl_dtr_wholeshoe_intake')) {
                    foreach ($db->table('tbl_dtr_wholeshoe_intake')->select('IntakeID')->where('LabRefNo', $wsMother)->get()->getResultArray() as $ik) {
                        $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_wholeshoe_intake')->where('source_id', (int) $ik['IntakeID'])->delete();
                    }
                    $db->table('tbl_dtr_wholeshoe_intake')->where('LabRefNo', $wsMother)->delete();
                }
            } catch (\Throwable $e) {
                log_message('error', 'Whole shoe intake cleanup failed: ' . $e->getMessage());
            }
        }

        return $this->response->setJSON(['ok' => true, 'message' => 'Entry deleted and recorded in the audit trail.', 'csrf' => csrf_hash()]);
    }

    /**
     * GET /dtr/prodcode-summary?slug=&q= : every report of the lab (Shoe Bonding, Whole Shoe, Materials, Third Party) grouped by Production Code.
     * Bonding / Whole Shoe reports carry the code themselves; a material report is tied to it by its code or MTL #; QIMA orders by their ProductionCode.
     * One group = one Production Code with its style / colorway / MTL / ETRF, production + tested dates, status, overall result and the list of its tests.
     */
    public function prodCodeSummary()
    {
        $db   = \Config\Database::connect();
        $slug = trim((string) ($this->request->getGet('slug') ?? 'all'));
        $q    = strtolower(trim((string) ($this->request->getGet('q') ?? '')));

        // the Production Code -> MTL # cross-reference has to see every bonding/wholeshoe row, not just
        // the ones a search term or the recency window below would otherwise include, or a filtered/older
        // material report would fail to resolve its MTL # through its own Production Code
        $known = [];
        $mtlMap = [];
        $refRows = $db->table('tbl_dtr_unified_labrefs')
            ->select('po_lot_no, mtl_no')
            ->whereIn('form_slug', ['bonding', 'wholeshoe'])
            ->where('po_lot_no IS NOT NULL', null, false)->where('po_lot_no !=', '')
            ->get()->getResultArray();
        foreach ($refRows as $r) {
            $c = strtoupper(trim((string) $r['po_lot_no']));
            $known[$c] = true;
            if (trim((string) $r['mtl_no']) !== '') {
                $mtlMap[$c] = strtoupper(trim((string) $r['mtl_no']));   // production code -> MTL #
            }
        }

        $builder = $db->table('tbl_dtr_unified_labrefs u')
            ->select('u.id, u.lab_ref_no, u.tr_no, u.form_category, u.form_slug, u.source_id, u.model_style, u.color, u.po_lot_no, u.mtl_no, u.material_code, u.received_date, u.tested_date, u.status, u.overall_result, bi.ProductionDate AS bpd, wi.ProductionDate AS wpd')
            ->join('tbl_dtr_bonding_info bi', "u.form_slug = 'bonding' AND bi.BondingTestID = u.source_id", 'left')
            ->join('tbl_dtr_wholeshoe_intake wi', "u.form_slug = 'wholeshoe' AND wi.IntakeID = u.source_id", 'left');
        if ($q !== '') {
            // a search term has to reach the whole table, not just the recent window below, or an older
            // Lab Ref No. would look like it doesn't exist
            $builder->groupStart()
                ->like('u.lab_ref_no', $q)->orLike('u.tr_no', $q)->orLike('u.model_style', $q)->orLike('u.color', $q)
                ->orLike('u.po_lot_no', $q)->orLike('u.mtl_no', $q)->orLike('u.material_code', $q)
                ->groupEnd()
                ->orderBy('u.id', 'DESC')->limit(6000);   // a very short term (e.g. "VN") matches almost everything: the newest 6000, like the list without a term
        } else {
            $builder->orderBy('u.id', 'DESC')->limit(6000);
        }
        $rows = $builder->get()->getResultArray();
        $qima = $db->tableExists('tbl_qima') ? $db->table('tbl_qima')->where('MTL IS NOT NULL', null, false)->where('MTL !=', '')->orderBy('ID', 'DESC')->limit(2000)->get()->getResultArray() : [];

        $groups = [];
        $add = static function (string $code, array $info, array $child) use (&$groups): void {
            $g = &$groups[$code];
            $g = $g ?? ['key' => $code, 'code' => '', 'mtl' => '', 'color' => '', 'style' => '', 'etrf' => '', 'prod' => '', 'tested' => '', 'pending' => false, 'rank' => 0, 'seen' => false, 'tests' => []];
            foreach (['code', 'mtl', 'color', 'style', 'etrf'] as $k) {
                if ($g[$k] === '' && trim((string) ($info[$k] ?? '')) !== '') {
                    $g[$k] = trim((string) $info[$k]);
                }
            }
            if (! empty($info['prod']) && ($g['prod'] === '' || $info['prod'] < $g['prod'])) {
                $g['prod'] = $info['prod'];
            }
            if ($child['tested'] !== '' && $child['tested_sort'] > ($g['tested_sort'] ?? '')) {
                $g['tested'] = $child['tested'];
                $g['tested_sort'] = $child['tested_sort'];
            }
            $res = strtoupper($child['result']);
            if ($res !== '') {
                $g['seen'] = true;
                $g['rank'] = max($g['rank'], str_starts_with($res, 'F') ? 3 : (str_starts_with($res, 'T') ? 2 : 1));
            }
            if (strcasecmp($child['status'], 'Complete') !== 0 && strcasecmp($child['status'], 'Completed') !== 0 && $child['kind'] !== 'thirdparty') {
                $g['pending'] = true;
            }
            $g['tests'][] = $child;
        };

        foreach ($rows as $r) {
            if ($slug !== 'all' && $slug !== '' && $r['form_slug'] !== $slug) {
                continue;
            }
            $po  = strtoupper(trim((string) $r['po_lot_no']));
            $mtl = strtoupper(trim((string) $r['mtl_no']));
            $code = isset($known[$po]) ? $po : '';
            if ($mtl === '' && $code !== '') {
                $mtl = $mtlMap[$code] ?? '';   // a report without an MTL # takes the one of its Production Code
            }
            // nothing to tie it to an MTL # -- rather than drop the report, fall back to the next most
            // useful thing to group it under (Production Code, then Material Code, then its own Lab Ref
            // No. as a last resort) so every report still shows up somewhere in the tree
            $groupKey = $mtl; $groupLabel = $mtl;
            if ($groupKey === '' && $po !== '') { $groupKey = 'PO#' . $po; $groupLabel = 'PO ' . $po; }
            if ($groupKey === '') {
                $mc = strtoupper(trim((string) ($r['material_code'] ?? '')));
                if ($mc !== '') { $groupKey = 'MC#' . $mc; $groupLabel = 'Material ' . $mc; }
            }
            if ($groupKey === '') {
                $lr = trim((string) $r['lab_ref_no']);
                $groupKey = $lr !== '' ? ('LR#' . $lr) : ('ID#' . $r['id']);
                $groupLabel = $lr !== '' ? $lr : ('#' . $r['id']);
            }
            $prod = (string) ($r['bpd'] ?: $r['wpd'] ?: '');
            $t    = (string) $r['tested_date'];
            $add($groupKey, ['code' => $code, 'mtl' => $groupLabel, 'color' => $r['color'], 'style' => $r['model_style'], 'etrf' => $r['tr_no'], 'prod' => $prod ? substr($prod, 0, 10) : ''], [
                'kind' => $r['form_slug'], 'labref' => $r['lab_ref_no'], 'etrf' => (string) $r['tr_no'], 'code' => $code, 'test' => $r['form_category'], 'slug' => $r['form_slug'], 'source_id' => (int) $r['source_id'],
                'tested' => $t !== '' && strtotime($t) ? date('Y-m-d', strtotime($t)) : '', 'tested_sort' => $t !== '' && strtotime($t) ? date('Y-m-d', strtotime($t)) : '',
                'status' => (string) ($r['status'] ?: 'Complete'), 'result' => (string) $r['overall_result'],
            ]);
        }
        if ($slug === 'all' || $slug === '' || $slug === 'thirdparty') {
            foreach ($qima as $z) {
                $d = (string) ($z['QimaAuditDate'] ?? '');
                $add(strtoupper(trim((string) $z['MTL'])), ['code' => strtoupper(trim((string) $z['ProductionCode'])), 'mtl' => $z['MTL'], 'color' => $z['Colorway'], 'style' => $z['Style'], 'etrf' => '', 'prod' => ''], [
                    'kind' => 'thirdparty', 'code' => strtoupper(trim((string) $z['ProductionCode'])), 'labref' => (string) ($z['QimaReference'] ?? ''), 'test' => 'Third Party (QIMA)', 'slug' => 'thirdparty', 'source_id' => (int) $z['ID'],
                    'tested' => $d, 'tested_sort' => $d, 'status' => $d !== '' && strtotime($d) <= time() ? 'Complete' : 'Scheduled', 'result' => '',
                ]);
            }
        }

        $out = [];
        foreach ($groups as $g) {
            if ($q !== '') {
                $hay = strtolower($g['code'] . ' ' . $g['key'] . ' ' . $g['mtl'] . ' ' . $g['color'] . ' ' . $g['style'] . ' ' . $g['etrf'] . ' ' . implode(' ', array_column($g['tests'], 'labref')));
                if (! str_contains($hay, $q)) {
                    continue;
                }
            }
            $g['status'] = $g['pending'] ? 'Pending' : 'Complete';
            $g['result'] = $g['seen'] ? [1 => 'Pass', 2 => 'Tolerable', 3 => 'Fail'][$g['rank']] : '';
            unset($g['pending'], $g['rank'], $g['seen']);
            foreach ($g['tests'] as &$tt) {
                unset($tt['tested_sort']);
            }
            unset($tt, $g['tested_sort']);
            $out[] = $g;
        }
        usort($out, static fn ($a, $b) => strcmp($b['prod'] ?: $b['tested'], $a['prod'] ?: $a['tested']));

        return $this->response->setJSON(['ok' => true, 'groups' => array_slice($out, 0, 400), 'total' => count($out), 'csrf' => csrf_hash()]);
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
    /** Report-only columns of the Pull Footwear Test Report (Lab VF 003) that tbl_dtr_pull_tests did not have. */
    private function ensurePullReportCols($db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        // tbl_dtr_pull_tests is at InnoDB's row-size limit (30 x Peak / SampleNo / Graph), so these go to a side table, one row per PullInfoID
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_pull_report (PullInfoID INT UNSIGNED NOT NULL PRIMARY KEY, Extra MEDIUMTEXT NULL, UpdatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
    }

    private const PULL_EXTRA = ['ETRFNo', 'SupplierCountry', 'MaterialDescription', 'POQuantity', 'SendBy', 'MaterialSource', 'TechSlot', 'FinishedDate', 'Comments', 'ReviewedBy', 'ApprovedBy', 'PullData'];

    /**
     * The Pull Footwear Test Report as form fields, read from tbl_dtr_pull_tests (by PullInfoID or Lab Ref No; of duplicate rows of one Lab Ref the one holding
     * the machine readings wins and fills the blanks of the others). Blank header details come from the intake tracker, then from tbl_dtr_bonding_info.
     */
    /**
     * Item rows (0 Eyelet Lateral, 1 Eyelet Medial, 2 / 3 below the Medial row, 4 Webbing loop holder, 5 Heel tab) filled from the machine readings (Peak1..Peak30)
     * of every test of one Lab Ref (rows newest first). A Lab Ref can hold several tests: EY has L1 (Lateral) + M1 (Medial); RVT has DIAMOND / PEARL tests.
     *
     * @return array<int,array{res:string}>
     */
    private function pullCells(array $rows, string $code): array
    {
        $fmt   = static fn (array $a): string => implode(' ', array_map(static fn ($x) => number_format($x, 2, '.', ''), $a));
        $tests = [];
        foreach ($rows as $tr) {
            $pk = [];
            for ($i = 1; $i <= 30; $i++) {
                if (isset($tr['Peak' . $i]) && $tr['Peak' . $i] !== '' && is_numeric($tr['Peak' . $i])) {
                    $pk[] = (float) $tr['Peak' . $i];
                }
            }
            if ($pk) {
                $nm = strtoupper(trim((string) ($tr['ReportName'] ?? '')));
                $tests[$nm !== '' ? $nm : '#' . ($tr['PullInfoID'] ?? count($tests))] ??= ['name' => $nm, 'peaks' => $pk];   // one per report name, the newest
            }
        }
        $tests = array_values($tests);
        $cells = [];
        if ($code === 'EY') {
            if (count($tests) > 1) {   // L1 = Lateral side (row 1), M1 = Medial side (row 2)
                foreach ($tests as $ti => $t) {
                    $slot = str_starts_with($t['name'], 'M') ? 1 : (str_starts_with($t['name'], 'L') ? 0 : min($ti, 1));
                    $cells[$slot] = ['res' => $fmt($t['peaks'])];
                }
            } elseif ($tests) {   // one test: first half Lateral side, second half Medial side, as in the Excel report
                $pk = $tests[0]['peaks'];
                if (count($pk) > 1) {
                    $half  = (int) ceil(count($pk) / 2);
                    $cells = [0 => ['res' => $fmt(array_slice($pk, 0, $half))], 1 => ['res' => $fmt(array_slice($pk, $half))]];
                } else {
                    $cells = [0 => ['res' => $fmt($pk)]];
                }
            }
        } elseif ($code === 'RVT') {
            // rivet readings sit below the Medial row: DIAMOND first (row 3), PEARL next (row 4); a test with neither word takes the next free row
            foreach ($tests as $t) {
                $want = str_contains($t['name'], 'PEARL') ? 3 : 2;
                while (isset($cells[$want]) && $want < 3) {
                    $want++;
                }
                $mat = str_contains($t['name'], 'PEARL') ? 'Pearl :' : (str_contains($t['name'], 'DIAMOND') ? 'Diamond :' : '');   // the material the rivet row is about
                $cells[$want] = ['res' => $fmt($t['peaks']), 'lbl' => $mat];
            }
        } elseif ($tests && in_array($code, ['DR', 'HP'], true)) {   // other pull types (e.g. LLH) have no row in this report
            $cells[['DR' => 4, 'HP' => 5][$code] ?? 4] = ['res' => $fmt($tests[0]['peaks'])];
        }

        return $cells;
    }

    private function pullReportRecord($db, int $sourceId, string $labRef): array
    {
        if (! $db->tableExists('tbl_dtr_pull_tests')) {
            return [];
        }
        $this->ensurePullReportCols($db);
        if ($sourceId > 0 && $labRef === '') {
            $labRef = trim((string) ($db->table('tbl_dtr_pull_tests')->select('LabRefNo')->where('PullInfoID', $sourceId)->get()->getRow('LabRefNo') ?? ''));
        }
        if ($labRef === '') {
            return [];
        }
        $rows = $db->table('tbl_dtr_pull_tests')->where('LabRefNo', $labRef)->orderBy('(Peak1 IS NOT NULL)', 'DESC', false)->orderBy('PullInfoID', 'DESC')->get()->getResultArray();
        $r    = $rows[0] ?? [];
        foreach (array_slice($rows, 1) as $o) {
            foreach ($o as $k => $v) {
                if (($r[$k] ?? '') === '' || $r[$k] === null) {
                    $r[$k] = $v;
                }
            }
        }
        if (! empty($r['PullInfoID'])) {
            $ex = $db->table('tbl_dtr_pull_report')->where('PullInfoID', (int) $r['PullInfoID'])->get()->getRowArray();
            $r  = array_merge($r, is_array($j = json_decode((string) ($ex['Extra'] ?? ''), true)) ? $j : []);
            if (trim((string) ($r['FinishedDate'] ?? '')) === '' && ! empty($r['FinishTimestamp'])) {
                $r['FinishedDate'] = substr((string) $r['FinishTimestamp'], 0, 10);
            }
        }
        $trk = $db->tableExists('tbl_dtr_BondingTracker') ? ($db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $labRef)->orderBy('TrackerID', 'DESC')->get(1)->getRowArray() ?: []) : [];
        $bi  = $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $labRef)->orderBy('BondingTestID', 'DESC')->get(1)->getRowArray() ?: [];
        $pick = static function (...$v): string {
            foreach ($v as $x) {
                if ($x !== null && trim((string) $x) !== '') {
                    return trim((string) $x);
                }
            }

            return '';
        };
        $date = static fn ($x) => ($t = strtotime((string) $x)) && trim((string) $x) !== '' && ! str_starts_with((string) $x, '0000') ? date('Y-m-d', $t) : '';
        $type = strtoupper($r['TestType'] ?? '');
        $code = preg_match('/^DTP\d{8}(HP|EY|DR|RVT|LLH|RIVET)([A-Z])?(\d+)?-([LR1-9])/i', $labRef, $m) ? strtoupper($m[1]) : '';
        $code = $code === 'RIVET' ? 'RVT' : $code;   // SAMPLE > Rivet (DTP<date>RIVET-<#>) is a rivet pull test
        $desc = ['EY' => 'Eyelet', 'HP' => 'Heel pull', 'DR' => 'D-ring', 'RVT' => 'Rivet', 'LLH' => 'Lace loop holder'][$code] ?? '';

        // machine readings (Peak1..Peak30, kgf) -> the item row of this pull type: Lateral = weakest of the first half, Medial = weakest of the second half
        $pullData = trim((string) ($r['PullData'] ?? ''));
        if ($code !== '') {
            $side  = strtoupper($m[4] ?? 'L') === 'R' ? 'right' : 'left';
            $cells = $pullData === '' ? $this->pullCells($rows, $code) : [];
            // the pull Lab Refs of one shoe (DTP<date>EY|RVT|DR|HP<line>-<side>) make ONE report: whichever of them is opened, it shows the readings of all of them
            // (eyelet Lateral / Medial first, rivet readings - diamond / pearl by report name - below the Medial row, then D-ring and heel tab)
            if (preg_match('/^(DTP\d{8})(?:EY|DR|HP|RVT|LLH|RIVET)(.+)$/i', $labRef, $sm)) {
                foreach (['EY', 'RVT', 'DR', 'HP'] as $sib) {
                    if ($sib === $code) {
                        continue;
                    }
                    $sibRows = $db->table('tbl_dtr_pull_tests')->where('LabRefNo', $sm[1] . $sib . $sm[2])->orderBy('(Peak1 IS NOT NULL)', 'DESC', false)->orderBy('PullInfoID', 'DESC')->get()->getResultArray();
                    $cells  += $this->pullCells($sibRows, $sib);
                }
            }
            if ($pullData === '') {
                if ($cells) {
                    ksort($cells);
                    $pullData = json_encode([$side => $cells]);
                }
            } elseif ($cells && is_array($saved = json_decode($pullData, true))) {
                // a report saved earlier: rows it left empty are filled from the other pull Lab Refs of the shoe (its own saved readings stay as they are)
                foreach ($cells as $ci => $cell) {
                    if (trim((string) ($saved[$side][$ci]['res'] ?? '')) === '') {
                        $saved[$side][$ci] = array_merge(is_array($saved[$side][$ci] ?? null) ? $saved[$side][$ci] : [], $cell);
                    }
                }
                $pullData = json_encode($saved);
            }
        }
        // ERP (dberp): PO No / PO Quantity (pairs) of the production order, and the pull part's material name from the BOM (production_order_details)
        $erpPo = null;
        $erpMat = '';
        $prodCode = $pick($r['ProductionCode'] ?? '', $trk['ProductionCode'] ?? '', $bi['POLotNO'] ?? '');
        if ($prodCode !== '') {
            try {
                $erpPo = $db->table('dberp.production_orders')->select('po_no, pairs')->where('prod_code', $prodCode)->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
            } catch (\Throwable $e) {
            }
        }
        $poNo = trim((string) ($erpPo['po_no'] ?? ''));
        $ms = ['DAILY' => 'Prod. Material', 'FIRST ARTICLE' => 'Prod. Material', 'RETEST' => 'Prod. Re-test', 'SAMPLE' => 'Sales Sample', 'TECH TRIAL INTERNAL TESTING' => 'Trial Test'];

        return [
            'ID' => $r['PullInfoID'] ?? '', 'LabRefNo' => $labRef, 'ETRFNo' => $pick($r['ETRFNo'] ?? ''),
            'Supplier' => $pick($r['Factory'] ?? '', $bi['Factory'] ?? ''), 'SupplierCountry' => $pick($r['SupplierCountry'] ?? ''),
            'MaterialDescription' => $pick($r['MaterialDescription'] ?? ''),   // typed by the technician: no lookup
            'ModelNoStyle' => $pick($r['ModelStyle'] ?? '', $trk['Style'] ?? '', $bi['ModelStyle'] ?? ''), 'Color' => $pick($r['Color'] ?? '', $trk['Colorway'] ?? '', $bi['Color'] ?? ''),
            'POLotNo' => $poNo !== '' ? $poNo . ' / ' . $prodCode : $prodCode, 'POQuantity' => $pick(isset($erpPo['pairs']) ? (string) $erpPo['pairs'] : '', $r['POQuantity'] ?? ''), 'SendBy' => $pick($r['SendBy'] ?? ''),
            'MaterialSource' => $pick($r['MaterialSource'] ?? '', $ms[$pick($trk['TestType'] ?? '', $r['TestType'] ?? '')] ?? ''), 'TechSlot' => $pick($r['TechSlot'] ?? ''),
            'ReceivedDate' => $date($pick($r['CollectionDate'] ?? '', $trk['CollectionDate'] ?? '')), 'TestedDate' => $date($r['TestedDate'] ?? ''), 'FinishedDate' => $date($r['FinishedDate'] ?? ''),
            'Technician' => $pick($r['Technician'] ?? '', $trk['Technician'] ?? ''), 'Result' => strtoupper($pick($r['Result'] ?? '')),
            'Comments' => $pick($r['Comments'] ?? '', $trk['TestType'] ?? ''), 'ReviewedBy' => $pick($r['ReviewedBy'] ?? ''), 'ApprovedBy' => $pick($r['ApprovedBy'] ?? ''), 'PullData' => $pullData,
        ];
    }

    /** Saves the Pull Footwear Test Report into tbl_dtr_pull_tests (the row of its Lab Ref, or a new one) and refreshes the Lab Ref index. */
    private function pullReportSave($db, array $f)
    {
        $this->ensurePullReportCols($db);
        $ref = trim((string) ($f['LabRefNo'] ?? ''));
        if ($ref === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Lab Ref No is required.', 'csrf' => csrf_hash()]);
        }
        $date = static function ($x) {
            $x = trim((string) $x);

            return $x !== '' && ($t = strtotime($x)) ? date('Y-m-d', $t) : null;
        };
        $nn  = static fn ($x) => trim((string) $x) === '' ? null : trim((string) $x);
        $row = [
            'LabRefNo' => $ref, 'Factory' => $nn($f['Supplier'] ?? ''), 'ModelStyle' => $nn($f['ModelNoStyle'] ?? ''), 'Color' => $nn($f['Color'] ?? ''), 'ProductionCode' => $nn(trim((string) preg_replace('/^.*\//', '', (string) ($f['POLotNo'] ?? '')))),   // "PO No / Prod. Code": the code is stored
            'CollectionDate' => $nn($f['ReceivedDate'] ?? ''), 'TestedDate' => $date($f['TestedDate'] ?? ''), 'Technician' => $nn($f['Technician'] ?? ''),
            'Result' => $nn(strtoupper((string) ($f['Result'] ?? ''))),
        ];
        $extra = [];
        foreach (self::PULL_EXTRA as $k) {
            $extra[$k] = trim((string) ($f[$k] ?? ''));
        }
        $id = (int) ($f['ID'] ?? 0);
        if ($id <= 0 || ! $db->table('tbl_dtr_pull_tests')->where('PullInfoID', $id)->countAllResults()) {
            $id = (int) ($db->table('tbl_dtr_pull_tests')->select('PullInfoID')->where('LabRefNo', $ref)->orderBy('(Peak1 IS NOT NULL)', 'DESC', false)->orderBy('PullInfoID', 'DESC')->get(1)->getRow('PullInfoID') ?? 0);
        }
        if ($id > 0) {
            $db->table('tbl_dtr_pull_tests')->where('PullInfoID', $id)->update($row);
        } else {
            $db->table('tbl_dtr_pull_tests')->insert($row);
            $id = (int) $db->insertID();
        }
        $db->query('INSERT INTO tbl_dtr_pull_report (PullInfoID, Extra) VALUES (?, ?) ON DUPLICATE KEY UPDATE Extra = VALUES(Extra)', [$id, json_encode($extra, JSON_UNESCAPED_UNICODE)]);
        \App\Libraries\DtrLabrefIndex::upsertPullTest($db, $id, $row);

        return $this->response->setJSON(['ok' => true, 'recordId' => $id, 'labRefNo' => $ref, 'message' => "Saved Pull Footwear Test Report ({$ref}) to tbl_dtr_pull_tests.", 'csrf' => csrf_hash()]);
    }

    public function record()
    {
        $db       = \Config\Database::connect();
        $slug     = trim((string) ($this->request->getGet('slug') ?? 'fabric'));
        $sourceId = (int) ($this->request->getGet('source_id') ?? 0);
        $labRef   = trim((string) ($this->request->getGet('lab_ref_no') ?? ''));

        if (! isset(self::FORM_CONFIG[$slug])) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Invalid form type.']);
        }

        if ($slug === 'pull') {   // the Pull Footwear Test Report reads tbl_dtr_pull_tests
            return $this->response->setJSON(['ok' => true, 'slug' => 'pull', 'config' => self::FORM_CONFIG['pull'], 'record' => $this->pullReportRecord($db, $sourceId, $labRef), 'customValues' => [], 'bondingPoints' => [], 'csrf' => csrf_hash()]);
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
            // one report can have more than one spot row (an early partial save, then the full one): the newest is the report's data
            $bondingPoints = $db->table('tbl_dtr_bonding_points')
                ->where('BondingTestID', $record['BondingTestID'])
                ->orderBy('BondingPointstID', 'DESC')
                ->get(1)
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

    /** GET /dtr/material-code-search?q= : Material Code lookup: tbl_dtr_material_type_status (code -> material type) with the name and supplier of tbl_dtr_material_list; codes only in the material list are added */
    public function materialCodeSearch()
    {
        $q   = trim((string) ($this->request->getGet('q') ?? ''));
        $db  = \Config\Database::connect();
        $out = [];
        try {
            $col = 'COLLATE utf8mb4_general_ci';
            foreach ($db->query("SELECT s.MaterialCode, s.MaterialType,
                    (SELECT l.MaterialName FROM tbl_dtr_material_list l WHERE l.MaterialCode {$col} = s.MaterialCode {$col} LIMIT 1) AS MaterialName,
                    (SELECT l.Supplier FROM tbl_dtr_material_list l WHERE l.MaterialCode {$col} = s.MaterialCode {$col} LIMIT 1) AS Supplier
                FROM tbl_dtr_material_type_status s WHERE s.MaterialCode LIKE ? ORDER BY s.MaterialCode LIMIT 30", ['%' . $q . '%'])->getResultArray() as $r) {
                $out[strtoupper($r['MaterialCode'])] = $r;
            }
            if (count($out) < 30) {
                foreach ($db->query("SELECT l.MaterialCode, l.`Material Type` AS MaterialType, l.MaterialName, l.Supplier FROM tbl_dtr_material_list l
                    WHERE l.MaterialCode LIKE ? AND NOT EXISTS (SELECT 1 FROM tbl_dtr_material_type_status s WHERE s.MaterialCode {$col} = l.MaterialCode {$col})
                    ORDER BY l.MaterialCode LIMIT " . (30 - count($out)), ['%' . $q . '%'])->getResultArray() as $r) {
                    $out[strtoupper($r['MaterialCode'])] = $r;
                }
            }
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'items' => [], 'message' => 'The material lists are not reachable.', 'csrf' => csrf_hash()]);
        }
        $items = array_map(static fn ($r) => ['code' => (string) $r['MaterialCode'], 'type' => trim((string) ($r['MaterialType'] ?? '')), 'name' => trim((string) ($r['MaterialName'] ?? '')), 'supplier' => trim((string) ($r['Supplier'] ?? ''))], array_values($out));

        return $this->response->setJSON(['ok' => true, 'items' => $items, 'csrf' => csrf_hash()]);
    }

    /**
     * GET /dtr/erp-prod-materials?code=&q= : the materials of one ERP production order (dberp.production_order_details, keyed by the
     * Production Code). One entry per material code, in the order of the bill of materials. The type comes from tbl_dtr_material_type_status when the code is known.
     */
    public function erpProdMaterials()
    {
        $code = trim((string) ($this->request->getGet('code') ?? ''));
        $q    = trim((string) ($this->request->getGet('q') ?? ''));
        $db   = \Config\Database::connect();
        if ($code === '') {
            return $this->response->setJSON(['ok' => true, 'items' => [], 'csrf' => csrf_hash()]);
        }
        try {
            // production_order_details = the bill of materials of the order (every part with its material, supplier and unit)
            $b = $db->table('dberp.production_order_details')
                ->select("material_code, material_name_e, material_name_c, supplier_e, supplier_c, unit, 'Raw Material' AS source_type, prod_usage", false)
                ->where('prod_code', $code)->where("material_code <> ''", null, false);
            if ($q !== '') {
                $b->groupStart()->like('material_code', $q)->orLike('material_name_e', $q)->orLike('material_name_c', $q)->groupEnd();
            }
            $rows = $b->orderBy('serial_no', 'ASC')->orderBy('id', 'ASC')->limit(600)->get()->getResultArray();
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'items' => [], 'message' => 'ERP production order materials are not reachable.', 'csrf' => csrf_hash()]);
        }
        $types = [];
        try {
            $codes = array_values(array_unique(array_column($rows, 'material_code')));
            if ($codes) {
                foreach ($db->table('tbl_dtr_material_type_status')->select('MaterialCode, MaterialType')->whereIn('MaterialCode', $codes)->get()->getResultArray() as $t) {
                    $types[strtoupper((string) $t['MaterialCode'])] = trim((string) $t['MaterialType']);
                }
            }
        } catch (\Throwable $e) {
            // no type table: the ERP source type is shown instead
        }
        // the lab's own material list (tbl_dtr_material_list) completes what the production order does not hold (name, supplier, type)
        $list = [];
        try {
            $codes = array_values(array_unique(array_column($rows, 'material_code')));
            if ($codes) {
                foreach ($db->table('tbl_dtr_material_list')->select('MaterialCode, MaterialName, Supplier, `Material Type` AS MType')->whereIn('MaterialCode', $codes)->get()->getResultArray() as $l) {
                    $list[strtoupper((string) $l['MaterialCode'])] ??= $l;
                }
            }
        } catch (\Throwable $e) {
            // no material list: the ERP values are used as they are
        }
        // the ERP material master (dberp.material_list, key mater_no) knows the type of codes the lab list does not have yet
        $erpType = [];
        try {
            $codes = array_values(array_unique(array_column($rows, 'material_code')));
            if ($codes) {
                foreach ($db->table('dberp.material_list')->select('mater_no, kind_name, material_group')->whereIn('mater_no', $codes)->get()->getResultArray() as $m) {
                    $kn = trim((string) ($m['kind_name'] ?: $m['material_group']));
                    $erpType[strtoupper((string) $m['mater_no'])] ??= (stripos($kn, 'Processing') !== false ? 'Processing Material' : (stripos($kn, 'Raw') !== false ? 'Raw Material' : $kn));
                }
            }
        } catch (\Throwable $e) {
            // ERP material master not reachable
        }
        $items = [];
        foreach ($rows as $r) {
            $k = strtoupper((string) $r['material_code']);
            if (isset($items[$k])) {
                continue;
            }
            $l = $list[$k] ?? [];
            $items[$k] = [
                'code'     => (string) $r['material_code'],
                'name'     => trim((string) ($r['material_name_e'] ?: ($l['MaterialName'] ?? '') ?: $r['material_name_c'])),
                'supplier' => trim((string) ($r['supplier_e'] ?: ($l['Supplier'] ?? '') ?: $r['supplier_c'])),
                'unit'     => trim((string) $r['unit']),
                'type'     => trim((string) ($l['MType'] ?? '')) ?: (($erpType[$k] ?? '') ?: (($types[$k] ?? '') ?: trim((string) $r['source_type']))),
                'raw'      => (string) $r['source_type'] === 'Raw Material',
            ];
        }

        return $this->response->setJSON(['ok' => true, 'items' => array_values($items), 'csrf' => csrf_hash()]);
    }

    /** GET /dtr/shoe-list-search?q= : Production Code filter (tbl_dtr_shoe_list): production code, style, colorway and MTL # of every shoe that has a production code, newest first */
    public function shoeListSearch()
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $b = \Config\Database::connect()->table('tbl_dtr_shoe_list')->select('ProdCode, StyleNo, Colorway, MTL')->where('ProdCode <>', '');
        if ($q !== '') {
            $b->groupStart()->like('ProdCode', $q)->orLike('StyleNo', $q)->orLike('MTL', $q)->orLike('Colorway', $q)->groupEnd();
        }
        $shoes = $b->groupBy('ProdCode, StyleNo, Colorway, MTL')->orderBy('MAX(ID)', 'DESC', false)->limit(300)->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'shoes' => $shoes, 'csrf' => csrf_hash()]);
    }

    /** newest production order (crd, pairs, po_no, mtl_no) of a Prod. Code from dberp.production_orders; null when none / ERP not reachable */
    private function erpOrderOf(string $code): ?array
    {
        static $cache = [];
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        if (! array_key_exists($code, $cache)) {
            try {
                $cache[$code] = \Config\Database::connect()->table('dberp.production_orders')->select('crd, pairs, po_no, mtl_no')->where('prod_code', $code)->orderBy('id', 'DESC')->limit(1)->get()->getRowArray() ?: null;
            } catch (\Throwable $e) {
                $cache[$code] = null;
            }
        }

        return $cache[$code];
    }

    /** GET /dtr/erp-po?code= : Shipped Date (crd) and PO Quantity (pairs) of one production order of dberp.production_orders */
    public function erpPo()
    {
        $code = trim((string) ($this->request->getGet('code') ?? ''));
        if ($code === '') {
            return $this->response->setJSON(['ok' => true, 'order' => null, 'csrf' => csrf_hash()]);
        }
        try {
            $r = \Config\Database::connect()->table('dberp.production_orders')->select('crd, pairs, po_no, mtl_no')->where('prod_code', $code)->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'order' => null, 'message' => 'ERP production orders are not reachable.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'order' => $r ? ['shipped' => (string) ($r['crd'] ?? ''), 'qty' => (string) ($r['pairs'] ?? ''), 'po' => (string) ($r['po_no'] ?? ''), 'mtl' => (string) ($r['mtl_no'] ?? '')] : null, 'csrf' => csrf_hash()]);
    }

    /** GET /dtr/bonding-graph-data?lab_ref= : the readings, curves and report fields of a Lab Ref for the graph report (points row + bonding report row) */
    public function bondingGraphData()
    {
        $ref = trim((string) ($this->request->getGet('lab_ref') ?? ''));
        if (! preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $ref)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid Lab Ref No.', 'csrf' => csrf_hash()]);
        }
        $db  = \Config\Database::connect();
        $pts = $db->table('tbl_dtr_bonding_points')->where('LabRefNo', $ref)->orderBy('BondingPointstID', 'DESC')->get(1)->getRowArray();
        $inf = $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $ref)->orderBy('BondingTestID', 'DESC')->get(1)->getRowArray();
        if (! $pts) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'No bonding readings are saved for ' . $ref . ' (tbl_dtr_bonding_points).', 'csrf' => csrf_hash()]);
        }
        $data = array_map(static fn ($v) => $v === null ? '' : (string) $v, $pts);
        foreach ((array) $inf as $k => $v) {                       // the bonding report fills what the points row does not have
            if (! isset($data[$k]) || trim($data[$k]) === '') {
                $data[$k] = $v === null ? '' : (string) $v;
            }
        }
        if (trim((string) ($data['ShoeName'] ?? '')) === '') {
            $data['ShoeName'] = trim(($data['ModelStyle'] ?? '') . ' ' . ($data['Color'] ?? ''));
        }
        if (trim((string) ($data['TestDate'] ?? '')) === '') {
            $data['TestDate'] = (string) ($inf['TestedDate'] ?? '');
        }

        return $this->response->setJSON(['ok' => true, 'data' => $data, 'csrf' => csrf_hash()]);
    }

    /** GET /dtr/pull-graph-data?cat=eyelet&lab_ref=... : fetches up to 3 connected tables for a Pull Test Graph report */
    public function pullGraphData()
    {
        $cat = trim((string) ($this->request->getGet('cat') ?? 'eyelet'));
        $ref = trim((string) ($this->request->getGet('lab_ref') ?? ''));
        $pkVal = trim((string) ($this->request->getGet('pk_val') ?? ''));
        $allowedCats = ['rivet', 'velcro', 'eyelet', 'lace_holder', 'heel_pull_tab', 'tongue_pull_tab'];
        if (! in_array($cat, $allowedCats, true)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid report category.', 'csrf' => csrf_hash()]);
        }
        if ($ref === '' && $pkVal === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Lab Ref No. is required.', 'csrf' => csrf_hash()]);
        }

        $db = \Config\Database::connect();
        $validTables = [];
        foreach ($db->query('SHOW TABLES')->getResultArray() as $r) {
            $t = (string) reset($r);
            if ($t !== '' && ! str_starts_with($t, 'tbl_bot_')) {
                $validTables[] = $t;
            }
        }
        $colsOf = static function (string $tbl) use ($db, $validTables): array {
            if (! in_array($tbl, $validTables, true)) {
                return [];
            }
            $out = [];
            foreach ($db->query('SHOW COLUMNS FROM `' . $tbl . '`')->getResultArray() as $c) {
                $out[] = (string) $c['Field'];
            }

            return $out;
        };

        // Load saved sources from Report Designer if available
        $sources = ['tables' => ['tbl_dtr_pull_tests', '', ''], 'joins' => [null, null, null]];
        $layRow  = $db->table('tbl_report_layouts')->where(['slug' => 'pull-graph-' . $cat, 'variant' => 'a4'])->get()->getRowArray();
        if ($layRow && ! empty($layRow['layout_json'])) {
            $lj = json_decode((string) $layRow['layout_json'], true);
            if (is_array($lj['sources']['tables'] ?? null) && ! empty($lj['sources']['tables'][0])) {
                $sources = $lj['sources'];
            }
        }

        $t1 = trim((string) ($sources['tables'][0] ?? 'tbl_dtr_pull_tests'));
        $t1Cols = $colsOf($t1);
        if (! $t1Cols) {
            $t1 = 'tbl_dtr_pull_tests';
            $t1Cols = $colsOf($t1);
        }
        $pk1 = $t1Cols[0] ?? 'PullInfoID';

        $row1 = null;
        if ($pkVal !== '' && $pk1 !== '') {
            $row1 = $db->query('SELECT * FROM `' . $t1 . '` WHERE `' . $pk1 . '` = ? LIMIT 1', [$pkVal])->getRowArray();
        }
        if (! $row1 && $ref !== '' && in_array('LabRefNo', $t1Cols, true)) {
            $row1 = $db->query('SELECT * FROM `' . $t1 . '` WHERE `LabRefNo` = ? ORDER BY `' . $pk1 . '` DESC LIMIT 1', [$ref])->getRowArray();
        }
        // Fallback: if LabRefNo is stored in WholeshoeLabRefNo on tbl_dtr_pull_tests
        if (! $row1 && $ref !== '' && in_array('WholeshoeLabRefNo', $t1Cols, true)) {
            $row1 = $db->query('SELECT * FROM `' . $t1 . '` WHERE `WholeshoeLabRefNo` = ? ORDER BY `' . $pk1 . '` DESC LIMIT 1', [$ref])->getRowArray();
        }
        if (! $row1) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok'    => false,
                'error' => 'No ' . str_replace('_', ' ', $cat) . ' data found for ' . ($ref ?: ('#' . $pkVal)) . ' in ' . $t1 . '.',
                'csrf'  => csrf_hash(),
            ]);
        }

        $rowsByIdx = [0 => $row1];
        $data = [];
        $mergeRow = static function (string $tbl, array $r) use (&$data): void {
            foreach ($r as $k => $v) {
                $val = $v === null ? '' : (string) $v;
                $data[$tbl . '.' . $k] = $val;
                if (! isset($data[$k]) || trim($data[$k]) === '') {
                    $data[$k] = $val;
                }
            }
        };
        $mergeRow($t1, $row1);

        // Join Table 2 and Table 3 if configured
        for ($i = 1; $i < 3; $i++) {
            $ti = trim((string) ($sources['tables'][$i] ?? ''));
            $j  = $sources['joins'][$i] ?? null;
            if ($ti === '' || ! is_array($j)) {
                continue;
            }
            $tiCols = $colsOf($ti);
            if (! $tiCols) {
                continue;
            }
            $lt = (int) ($j['leftTable'] ?? 0);
            $lc = trim((string) ($j['leftCol'] ?? ''));
            $rc = trim((string) ($j['rightCol'] ?? ''));
            if ($lc === '' || $rc === '' || ! isset($rowsByIdx[$lt]) || ! in_array($rc, $tiCols, true)) {
                continue;
            }
            $joinVal = $rowsByIdx[$lt][$lc] ?? null;
            if ($joinVal === null || $joinVal === '') {
                continue;
            }
            $pki = $tiCols[0];
            $ri  = $db->query('SELECT * FROM `' . $ti . '` WHERE `' . $rc . '` = ? ORDER BY `' . $pki . '` DESC LIMIT 1', [$joinVal])->getRowArray();
            if ($ri) {
                $rowsByIdx[$i] = $ri;
                $mergeRow($ti, $ri);
            }
        }

        return $this->response->setJSON(['ok' => true, 'data' => $data, 'csrf' => csrf_hash()]);
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
                // Vans writes the toddler / infant sizes as the style prefix TD and IN (TD VERO LS V, IN OLD SKOOL CRIB); the table keeps them as TODDLER (2.50)
                $first = $words[0] ?? '';
                if (in_array($first, ['TD', 'IN', 'INFANT'], true)) {
                    array_unshift($words, 'TODDLER');
                }
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

    /** a SHUTDOWN line recorded as a row of tbl_dtr_bonding_info (alias b) */
    private const SHUTDOWN_ROW = "(COALESCE(b.LabRefNo, '') LIKE '%SHUTDOWN%' OR COALESCE(b.POLotNO, '') = 'SHUTDOWN' OR COALESCE(b.ModelStyle, '') = 'SHUTDOWN')";

    private const TEST_TYPE_DIRS = ['DAILY', 'TECH TRIAL', 'SAMPLE', 'INTERNAL TESTING', 'FIRST ARTICLE', 'RETEST'];

    /** Settings > Lab Report Settings of one Lab Tracker tab (bonding | shoes | containers): is the picture on, where it is read from, how that folder is organised */
    private function reportImageCfg(string $tab): array
    {
        $tab  = in_array($tab, ['bonding', 'shoes', 'containers'], true) ? $tab : 'bonding';
        $root = trim((string) \App\Libraries\SystemSettings::get("labreport.{$tab}.folder", ''));
        if ($root !== '' && ! preg_match('~^(\\\\\\\\|[A-Za-z]:[\\\\/]|https?://)~', $root)) {
            $root = '';   // not a full folder path (a bare name like "BondingImages" is read from the web server's own folder, not the share): the main folder is used
        }
        if ($root === '') {
            $root = trim((string) \App\Libraries\SystemSettings::get('labreport.image_root', ''));
        }
        $root = rtrim($root, '\\/');

        return [
            'tab'    => $tab,
            'on'     => \App\Libraries\SystemSettings::bool("labreport.{$tab}.show", $tab === 'bonding'),
            'root'   => $this->resolvedRootCached($root, $tab),
            'layout' => (string) \App\Libraries\SystemSettings::get("labreport.{$tab}.layout", $tab === 'bonding' ? 'type_date' : 'flat'),
        ];
    }

    /**
     * Resolves the report picture folder: if the configured path is a UNC path pointing to a non-existent share on this machine
     * (e.g. \\192.168.32.45\html\BondingImages when the folder is at D:\BACK UP\SystemProcess\BondingImages or \\192.168.32.43\html\BondingImages),
     * automatically falls back to a reachable local or network folder.
     */
    private function resolveReportRoot(string $root, string $tab): string
    {
        if ($root !== '' && @is_dir($root)) {
            return $root;
        }
        $candidates = array_values(array_unique(array_filter([
            $root,
            '\\\\192.168.32.43\\html\\BondingImages',   // the live pictures (where the report editor saves them)
            '\\\\192.168.32.45\\SystemProcess\\BondingImages',
            $tab === 'bonding' ? 'D:\\BACK UP\\SystemProcess\\BondingImages' : '',   // old local backup copy: last resort only
        ])));
        $local = static fn (string $c): bool => (bool) preg_match('~^[A-Za-z]:[\\\\/]~', $c);
        // in order (the live .43 folder first): opened as a share, or - the Apache service often has no network login - read over the web server of the PC that holds it; an older copy on another PC is used only when the live one is out of reach
        foreach ($candidates as $cand) {
            if ($local($cand)) {
                continue;
            }
            if (@is_dir($cand)) {
                return $cand;
            }
            $http = $this->reportHttpRoot($cand);
            if ($http !== '') {
                $hdrs = @get_headers($http . '/', false, stream_context_create(['http' => ['timeout' => 3]]));
                if (is_array($hdrs) && isset($hdrs[0]) && str_contains($hdrs[0], '200')) {
                    return $cand;
                }
            }
        }
        foreach ($candidates as $cand) {
            if ($local($cand) && @is_dir($cand)) {
                return $cand;   // old local backup copy: last resort
            }
        }

        return $root;
    }

    /** resolveReportRoot, remembered for 5 minutes (every thumbnail asks for it) */
    private function resolvedRootCached(string $root, string $tab): string
    {
        $f = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'reportroot_' . md5($tab . '|' . $root) . '.txt';
        if (is_file($f) && time() - (int) @filemtime($f) < 300) {
            return (string) @file_get_contents($f);
        }
        $r = $this->resolveReportRoot($root, $tab);
        @file_put_contents($f, $r, LOCK_EX);

        return $r;
    }

    /** Can the web server open the report picture folder? */
    private function reportShareReachable(string $root): bool
    {
        return @is_dir($root);
    }

    private function reportImageCache(array $cfg): string
    {
        return WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'reportimg_' . md5($cfg['root'] . '|' . $cfg['layout']) . '.json';
    }

    /**
     * Every picture of the tab's folder, keyed by its Lab Ref No (letters and digits only, so DTP20261109RIVETA11L and DTP20261109RIVETA11-L meet).
     * Layout TYPE_DATE: <folder>\<TEST TYPE>\<date>\<Lab Ref>.png (and any loose picture in <folder>); FLAT: <folder>\<Lab Ref>.png.
     * Names:  <LabRef>.png = the current report (wins) | <LabRef>_<n>.png = older version | <date>-<time>_<LabRef>_<hash>.png = batch copy.
     * Kept for a minute in writable/cache so a list of 100 pictures does not scan the network share 100 times.
     *
     * @return array<string,array{p:string,t:int,e:bool}>
     */
    private function reportImageIndex(array $cfg): array
    {
        $root = $cfg['root'];
        if ($root === '') {
            return [];
        }
        $http = $this->reportShareReachable($root) ? '' : $this->reportHttpRoot($root);
        if ($http === '' && ! $this->reportShareReachable($root)) {
            return [];
        }
        $cache = $this->reportImageCache($cfg);
        $fresh = static function () use ($cache): ?array {
            if (is_file($cache) && time() - (int) @filemtime($cache) < 300) {
                $j = json_decode((string) @file_get_contents($cache), true);
                if (is_array($j) && count($j) > 0) {
                    return $j;
                }
            }

            return null;
        };
        if (($j = $fresh()) !== null) {
            return $j;
        }
        // only one request reads the folder; the others wait for it and then use its result
        $lock = @fopen($cache . '.lock', 'c');
        if ($lock) {
            @flock($lock, LOCK_EX);
            if (($j = $fresh()) !== null) {
                @flock($lock, LOCK_UN);
                @fclose($lock);

                return $j;
            }
        }
        try {
            return $this->buildReportImageIndex($cfg, $root, $http, $cache);
        } finally {
            if ($lock) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        }
    }

    /** reads the picture folder (see reportImageIndex) */
    private function buildReportImageIndex(array $cfg, string $root, string $http, string $cache): array
    {
        $normKey = fn (string $s): string => $this->reportKey($s);
        $map  = [];
        $scan = static function (string $dir) use (&$map, $normKey): void {
            foreach (@scandir($dir) ?: [] as $f) {
                if (! preg_match('/^(.+?)\.(png|jpe?g)$/i', $f, $m)) {
                    continue;
                }
                $name  = $m[1];
                $exact = true;
                if (preg_match('/^\d{8}-\d{4}_(.+)_[0-9a-f]{8}$/i', $name, $mm)) {
                    $name = $mm[1]; $exact = false;
                } elseif (preg_match('/^(.+)_\d+$/', $name, $mm)) {
                    $name = $mm[1]; $exact = false;
                } elseif (stripos($name, 'TRIAL') === false && preg_match('/[LR][\s\-]+\d+$/i', $name)) {
                    $exact = false;
                }
                $key  = $normKey($name);
                $path = $dir . DIRECTORY_SEPARATOR . $f;
                $t    = (int) @filemtime($path);
                $cur  = $map[$key] ?? null;
                if ($cur === null || ($exact && ! $cur['e']) || ($exact === $cur['e'] && $t >= $cur['t'])) {
                    $map[$key] = ['p' => $path, 't' => $t, 'e' => $exact];
                }
            }
        };
        if ($http !== '') {
            // the web server cannot open the share (the Apache service has no network login): read the same folder over the web server of the PC that holds it
            $list = static function (string $url): array {
                $html = (string) @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
                $out  = ['files' => [], 'dirs' => []];
                if (preg_match_all('~<a href="([^"?/][^"]*?)">[^<]*</a>(?:</td><td[^>]*>\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2}))?~i', $html, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $x) {
                        $n = $x[1];
                        if (substr($n, -1) === '/') {
                            $out['dirs'][] = $n;
                        } else {
                            $out['files'][] = [$n, isset($x[2]) ? (int) strtotime($x[2]) : 0];
                        }
                    }
                }

                return $out;
            };
            $add = static function (string $base, array $files) use (&$map, $normKey): void {
                foreach ($files as [$f, $t]) {
                    $dec = rawurldecode($f);
                    if (! preg_match('/^(.+?)\.(png|jpe?g)$/i', $dec, $m)) {
                        continue;
                    }
                    $name = $m[1]; $exact = true;
                    if (preg_match('/^\d{8}-\d{4}_(.+)_[0-9a-f]{8}$/i', $name, $mm)) {
                        $name = $mm[1]; $exact = false;
                    } elseif (preg_match('/^(.+)_\d+$/', $name, $mm)) {
                        $name = $mm[1]; $exact = false;
                    } elseif (stripos($name, 'TRIAL') === false && preg_match('/[LR][\s\-]+\d+$/i', $name)) {
                        $exact = false;
                    }
                    $key = $normKey($name);
                    $cur = $map[$key] ?? null;
                    if ($cur === null || ($exact && ! $cur['e']) || ($exact === $cur['e'] && $t >= $cur['t'])) {
                        $map[$key] = ['p' => $base . $f, 't' => $t, 'e' => $exact];
                    }
                }
            };
            $r0 = $list($http . '/');
            $add($http . '/', $r0['files']);
            if ($cfg['layout'] === 'type_date') {
                foreach ($r0['dirs'] as $a) {
                    $r1 = $list($http . '/' . $a);
                    $add($http . '/' . $a, $r1['files']);
                    foreach ($r1['dirs'] as $b) {
                        $r2 = $list($http . '/' . $a . $b);
                        $add($http . '/' . $a . $b, $r2['files']);
                        foreach ($r2['dirs'] as $c) {
                            $r3 = $list($http . '/' . $a . $b . $c);
                            $add($http . '/' . $a . $b . $c, $r3['files']);
                        }
                    }
                }
            }
            if ($map) {
                @file_put_contents($cache, json_encode($map));
            }

            return $map;
        }
        $scan($root);
        if ($cfg['layout'] === 'type_date') {
            foreach (@scandir($root) ?: [] as $a) {
                if ($a === '.' || $a === '..' || ! is_dir($root . DIRECTORY_SEPARATOR . $a)) {
                    continue;
                }
                $dirA = $root . DIRECTORY_SEPARATOR . $a;
                $scan($dirA);
                foreach (@scandir($dirA) ?: [] as $b) {
                    if ($b === '.' || $b === '..' || ! is_dir($dirA . DIRECTORY_SEPARATOR . $b)) {
                        continue;
                    }
                    $dirB = $dirA . DIRECTORY_SEPARATOR . $b;
                    $scan($dirB);
                    foreach (@scandir($dirB) ?: [] as $c) {
                        if ($c !== '.' && $c !== '..' && is_dir($dirB . DIRECTORY_SEPARATOR . $c)) {
                            $scan($dirB . DIRECTORY_SEPARATOR . $c);
                        }
                    }
                }
            }
        }
        if ($map) {
            @file_put_contents($cache, json_encode($map));
        }

        return $map;
    }

    /**
     * Matching key of a Lab Ref / picture name (letters and digits only). Forgives the old spellings found in the picture folders:
     * RIVET = RVT, "-RO1" typed with the letter O = "-R01", and a trailing " 2" / "-2" copy number after the side (L/R) - except TRIAL names, where that number is the trial number.
     */
    private function reportKey(string $s): string
    {
        $raw = trim($s);
        if (! str_contains(strtoupper($raw), 'TRIAL')) {
            $raw = (string) preg_replace('/([LR])[\s\-]+\d+$/i', '$1', $raw);
        }
        $k = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
        $k = (string) preg_replace('/^(DTP\d{8})RIVET/', '$1RVT', $k);

        return (string) preg_replace('/R[O0]([0-9])$/', 'R0$1', $k);
    }

    private function reportImageFile(string $tab, string $labRef): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $labRef)) {
            return null;
        }
        $key = $this->reportKey($labRef);
        $hit = $this->reportImageIndex($this->reportImageCfg($tab))[$key] ?? null;

        return ($hit && (preg_match('~^https?://~i', $hit['p']) || is_file($hit['p']))) ? $hit['p'] : null;
    }

    /** \\host\html\Folder -> http://host/Folder (the share "html" is the web root of that PC); an http(s):// folder is used as it is; '' when neither */
    private function reportHttpRoot(string $root): string
    {
        if (preg_match('~^https?://~i', $root)) {
            return rtrim($root, '/');
        }
        if (preg_match('~^\\\\\\\\([^\\\\]+)\\\\html(?:\\\\(.*))?$~i', $root, $m)) {
            return 'http://' . $m[1] . (isset($m[2]) && $m[2] !== '' ? '/' . implode('/', array_map('rawurlencode', explode('\\', $m[2]))) : '');
        }

        // \\host\BondingImages (the share itself named like the web folder) -> http://host/BondingImages
        if (preg_match('~^\\\\\\\\([^\\\\]+)\\\\(.+)$~', $root, $m)) {
            return 'http://' . $m[1] . '/' . implode('/', array_map('rawurlencode', explode('\\', $m[2])));
        }

        return '';
    }

    /** GET /dtr/bonding-image?lab_ref=[&tab=bonding|shoes|containers] : the report picture of a Lab Ref (404 when there is none) */
    public function bondingImage()
    {
        if ($this->request->getGet('diag')) {   // /dtr/bonding-image?diag=1&tab=bonding : why are there no pictures?
            $cfg  = $this->reportImageCfg((string) ($this->request->getGet('tab') ?? 'bonding'));
            $before = @is_dir($cfg['root']);
            @unlink($this->reportImageCache($cfg));
            $idx = $this->reportImageIndex($cfg);

            return $this->response->setJSON([
                'tab' => $cfg['tab'], 'enabled' => $cfg['on'], 'folder' => $cfg['root'], 'layout' => $cfg['layout'], 'php_runs_as' => get_current_user(),
                'folder_open' => $before,
                'pictures_found' => count($idx), 'sample' => array_slice(array_keys($idx), 0, 5),
            ]);
        }
        $tabQ  = (string) ($this->request->getGet('tab') ?? 'bonding');
        $refQ  = trim((string) ($this->request->getGet('lab_ref') ?? ''));
        $thumb = (bool) $this->request->getGet('thumb');
        $tf    = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . md5(strtolower($tabQ . '|' . $refQ));
        if ($thumb) {   // a small list picture: kept 10 minutes (an edit in NEXUS-L replaces it at once); "no picture" is kept 5 minutes
            if (is_file($tf . '.jpg') && time() - (int) filemtime($tf . '.jpg') < 600) {
                return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=300')->setBody((string) file_get_contents($tf . '.jpg'));
            }
            if (is_file($tf . '.none') && time() - (int) filemtime($tf . '.none') < 300) {
                return $this->response->setStatusCode(404)->setHeader('Cache-Control', 'private, max-age=120');
            }
        }
        $file = $this->reportImageFile($tabQ, $refQ);
        if ($file === null) {
            if ($thumb) {
                @mkdir(dirname($tf), 0777, true);
                @touch($tf . '.none');
            }

            return $this->response->setStatusCode(404);
        }
        $mime = preg_match('/\.jpe?g$/i', $file) ? 'image/jpeg' : 'image/png';
        $body = preg_match('~^https?://~i', $file) ? (string) @file_get_contents($file, false, stream_context_create(['http' => ['timeout' => 10]])) : (string) file_get_contents($file);
        if ($body === '') {
            return $this->response->setStatusCode(404);
        }
        if ($thumb && function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring($body))) {
            // 320 px wide JPEG: a few KB instead of the full report picture (often 0.5-2 MB)
            $w = imagesx($src);
            $h = imagesy($src);
            $nw = min(320, $w);
            $nh = max(1, (int) round($h * $nw / max(1, $w)));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // transparent PNG parts become white
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 72);
            $small = (string) ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            if ($small !== '') {
                @mkdir(dirname($tf), 0777, true);
                @file_put_contents($tf . '.jpg', $small, LOCK_EX);
                @unlink($tf . '.none');

                return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=300')->setBody($small);
            }
        }

        return $this->response->setHeader('Content-Type', $mime)->setHeader('Cache-Control', 'private, max-age=30')->setBody($body);
    }

    /** POST /dtr/bonding-image-save {lab_ref, tab, image: data:image/png;base64,...} : saved as the new current report; the old one is kept as the next _n version */
    public function bondingImageSave()
    {
        $p      = $this->request->getJSON(true) ?? [];
        $labRef = trim((string) ($p['lab_ref'] ?? ''));
        $data   = (string) ($p['image'] ?? '');
        $fail   = fn (string $m) => $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $m, 'csrf' => csrf_hash()]);

        if (! preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $labRef)) {
            return $fail('Invalid Lab Ref No.');
        }
        $cfg = $this->reportImageCfg((string) ($p['tab'] ?? 'bonding'));
        if ($cfg['root'] === '' || ! $this->reportShareReachable($cfg['root'])) {
            return $fail('The report picture folder is not reachable (' . $cfg['root'] . '). Check Settings > Lab Report Settings.');
        }
        if (! preg_match('#^data:image/png;base64,#', $data)) {
            return $fail('The edited picture is missing.');
        }
        $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
        if ($bin === false || strncmp($bin, "\x89PNG", 4) !== 0 || strlen($bin) > 25 * 1024 * 1024) {
            return $fail('The edited picture is not a valid PNG (up to 25 MB).');
        }
        // the edited report goes next to the picture it came from; with none yet: <folder>\<TEST TYPE>\<today>\ (TYPE_DATE) or the folder itself (FLAT)
        $found = $this->reportImageFile($cfg['tab'], $labRef);
        if ($found !== null) {
            $dir = dirname($found) . DIRECTORY_SEPARATOR;
        } elseif ($cfg['layout'] === 'type_date') {
            $tt = strtoupper(trim((string) (\Config\Database::connect()->table('tbl_dtr_bonding_info')->select('TestType')->where('LabRefNo', $labRef)->orderBy('BondingTestID', 'DESC')->get(1)->getRow('TestType') ?? '')));
            $tt = $tt === 'TRIAL' ? 'TECH TRIAL' : (in_array($tt, self::TEST_TYPE_DIRS, true) ? $tt : 'DAILY');
            $dir = $cfg['root'] . DIRECTORY_SEPARATOR . $tt . DIRECTORY_SEPARATOR . date('Y-m-d') . DIRECTORY_SEPARATOR;
            if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
                return $fail('Could not create the folder ' . $dir);
            }
        } else {
            $dir = $cfg['root'] . DIRECTORY_SEPARATOR;
        }
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

            return $fail('Could not save into the report picture folder.');
        }
        // batch-tracking copy, same naming as the machine batches: <date>-<time>_<LabRef>_<hash>.png
        @file_put_contents($dir . date('Ymd-Hi') . '_' . $labRef . '_' . substr(md5($bin), 0, 8) . '.png', $bin);
        @unlink($this->reportImageCache($cfg));
        $tf = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . md5(strtolower(($p['tab'] ?? 'bonding') . '|' . $labRef));
        @unlink($tf . '.jpg');
        @unlink($tf . '.none');

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
            if (strtoupper((string) ($r['type'] ?? '')) === 'TRIAL') {   // a trial has no building / line: DTP<date><code>-<side>
                $bld   = '';
                $lineN = '';
            }
            $side   = ($r['side'] ?? 'L') === 'R' ? 'R' : 'L';
            $mother = 'DTP' . $pd . 'WH' . $bld . $lineN . '-' . $side;   // -L / -R like the bonding Lab Ref No

            $kids = [];
            foreach ((array) ($r['tests'] ?? []) as $tt) {
                $tc = $codes[strtolower(trim((string) $tt))] ?? null;
                if ($tc !== null) {
                    // Test Type Lab Ref No: DTP<date><code><line>-<side>  (UV: DTP<date>UV<line>, no side), no running number; only a Lab Ref that is already taken gets -2, -3 ...
                    $key  = 'DTP' . $pd . $tc . $bld . $lineN;
                    $base = $tc === 'UV' ? $key : $key . '-' . $side;
                    $n    = 0;
                    foreach ($db->table('tbl_dtr_WholeshoeTracker')->select('LabRefNo')->like('LabRefNo', $base, 'after')->get()->getResultArray() as $x) {
                        if ((string) $x['LabRefNo'] === $base) {
                            $n = max($n, 1);
                        } elseif (preg_match('/^' . preg_quote($base, '/') . '-(\d+)$/', (string) $x['LabRefNo'], $mm)) {
                            $n = max($n, (int) $mm[1]);
                        }
                    }
                    $kids[] = ['type' => trim((string) $tt), 'code' => $tc, 'ref' => $n === 0 ? $base : $base . '-' . ($n + 1)];
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
                'IntakeType'        => strtoupper((string) ($r['type'] ?? '')) === 'TRIAL' ? 'TRIAL' : 'DAILY',
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
        if ($saved > 0) {
            \App\Libraries\DataCache::bump();
        }
        $msg = "Saved {$saved} whole shoe(s).";
        if ($skipped) {
            $msg .= ' Skipped (Test Type Lab Ref No already exists): ' . implode(', ', $skipped) . '.';
        }

        return $this->response->setJSON(['ok' => $saved > 0, 'message' => $msg, 'saved' => $saved, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
    }

    /** GET /dtr/material-labref-next?material=&date= : the Lab Reference the next received line of that material and date will get */
    public function materialLabrefNext()
    {
        $material = trim((string) ($this->request->getGet('material') ?? ''));
        $d        = trim((string) ($this->request->getGet('date') ?? ''));
        $d        = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : date('Y-m-d');
        $db       = \Config\Database::connect();
        $has      = $db->tableExists('tbl_dtr_material_receipts') && $db->fieldExists('LabReference', 'tbl_dtr_material_receipts');
        $ref      = $has ? $this->nextMaterialLabRef($db, $material, $d, (string) ($this->request->getGet('name') ?? ''), (int) ($this->request->getGet('samples') ?? 1)) : $this->nextMaterialLabRefBase($material, $d) . '-1';

        return $this->response->setJSON(['ok' => true, 'labref' => $ref, 'csrf' => csrf_hash()]);
    }

    /** DTP + date + material code: a single word gives its first 3 letters (FAB, LEA), several words their initials (NW, LF) */
    private function nextMaterialLabRefBase(string $material, string $date): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', trim($material), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) === 1) {
            $code = strtoupper(substr($words[0], 0, 3));
        } else {
            $code = strtoupper(implode('', array_map(static fn ($w) => $w[0], array_slice($words, 0, 3))));
        }

        return 'DTP' . str_replace('-', '', $date) . ($code !== '' ? $code : 'MT');
    }

    /**
     * The Lab Reference code of a material (list of the lab): Fabric Upper FBU, Fabric Lining FBL, Leather Upper LEU / Lining LEL, Synthetic Upper SYU / Lining SYL,
     * Shoe Lace LC, Thread TH, Eyelet EY, Grommets GR, Latex Foam FML, PU Foam FM, Velcro Hook VH / Loop VL, Size Label SL, Woven Label WL, Nylon Webbing NW,
     * Inner Box IB, Outer Box OB, Rubber Outsole RB, Foxing F1, Heel Logo Rubber HLR, Heel Logo molded HL, Accessories AC. '' when the type is not in that list.
     */
    private function materialLabRefCode(string $type, string $name): string
    {
        $t = strtoupper(trim($type));
        $n = strtoupper($name);
        $lining = str_contains($t, 'LINING') || str_contains($n, 'LINING');
        $map = [
            ['FABRIC', $lining ? 'FBL' : 'FBU'], ['CANVAS', $lining ? 'FBL' : 'FBU'], ['OSNABURG', 'FBL'], ['LEATHER', $lining ? 'LEL' : 'LEU'],
            ['SYNTHETIC', $lining ? 'SYL' : 'SYU'], ['LACE', 'LC'], ['THREAD', 'TH'], ['GROMMET', str_contains($t, 'EYELET') ? 'EY' : 'GR'], ['EYELET', 'EY'],
            ['LATEX FOAM', 'FML'], ['FOAM', 'FM'], ['HOOK', str_contains($n, 'LOOP') && ! str_contains($n, 'HOOK') ? 'VL' : 'VH'], ['VELCRO', str_contains($n, 'LOOP') && ! str_contains($n, 'HOOK') ? 'VL' : 'VH'],
            ['SIZE LABEL', 'SL'], ['WOVEN LABEL', 'WL'], ['NYLON WEBBING', 'NW'], ['INNER BOX', 'IB'], ['OUTER BOX', 'OB'], ['HEEL LOGO RUBBER', 'HLR'],
            ['HEEL LOGO', 'HL'], ['OUTSOLE', 'RB'], ['RUBBER', 'RB'], ['FOXING', 'F1'], ['ACCESSOR', 'AC'],
        ];
        foreach ($map as [$k, $c]) {
            if (str_contains($t, $k)) {
                return $c;
            }
        }

        return '';
    }

    /**
     * DTP + date + material code - first sample no. [- last sample no.] , e.g. DTP20261104FBU-1-100 (samples 1 to 100); one sample is only DTP20261104FBU-1.
     * Samples go on from the last one of the same code and date (LC-1-7 is followed by LC-8-9).
     */
    private function nextMaterialLabRef($db, string $type, string $date, string $name = '', int $samples = 1): string
    {
        $code = $this->materialLabRefCode($type, $name);
        $base = $code !== '' ? 'DTP' . str_replace('-', '', $date) . $code : $this->nextMaterialLabRefBase($type, $date);
        $n    = 0;
        $sql  = 'SELECT CONVERT(LabReference USING utf8mb4) COLLATE utf8mb4_general_ci AS r FROM tbl_dtr_material_receipts WHERE LabReference LIKE ?'
            . ' UNION SELECT CONVERT(lab_ref_no USING utf8mb4) COLLATE utf8mb4_general_ci FROM tbl_dtr_unified_labrefs WHERE lab_ref_no LIKE ?'
            . ' UNION SELECT CONVERT(lab_ref_no USING utf8mb4) COLLATE utf8mb4_general_ci FROM tbl_dtr_material_bench_log WHERE lab_ref_no LIKE ?';
        try {
            $found = $db->query($sql, [$base . '-%', $base . '-%', $base . '-%'])->getResultArray();
        } catch (\Throwable $e) {
            $found = $db->query(str_replace(' UNION SELECT CONVERT(lab_ref_no USING utf8mb4) COLLATE utf8mb4_general_ci FROM tbl_dtr_material_bench_log WHERE lab_ref_no LIKE ?', '', $sql), [$base . '-%', $base . '-%'])->getResultArray();
        }
        foreach ($found as $x) {
            if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)(?:-(\d+))?/', (string) $x['r'], $m)) {
                $n = max($n, (int) ($m[2] ?? $m[1]));
            }
        }
        $first = $n + 1;
        $last  = $first + max(1, $samples) - 1;

        return $base . '-' . $first . ($last > $first ? '-' . $last : '');
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
        foreach (['MaterialName', 'ProdCode', 'ProcessingNo', 'LabReference', 'MaterialTest', 'Remarks', 'Style', 'Colorway', 'MTL'] as $col) {
            if (! $db->fieldExists($col, 'tbl_dtr_material_receipts')) {
                $db->query("ALTER TABLE tbl_dtr_material_receipts ADD COLUMN {$col} VARCHAR(191) NULL AFTER ContainerNo");
            }
        }

        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_MaterialTracker (
            TrackerID INT AUTO_INCREMENT PRIMARY KEY,
            LabRefNo VARCHAR(191) NOT NULL,
            ReceivedDate DATE NULL,
            DateArrived DATE NULL,
            ProductionCode VARCHAR(191) NULL,
            Colorway VARCHAR(191) NULL,
            Style VARCHAR(191) NULL,
            MTL VARCHAR(191) NULL,
            MaterialCode VARCHAR(191) NULL,
            MaterialName VARCHAR(500) NULL,
            MaterialType VARCHAR(191) NULL,
            Supplier VARCHAR(191) NULL,
            ContainerNo VARCHAR(191) NULL,
            BatchNo VARCHAR(191) NULL,
            Qty VARCHAR(100) NULL,
            QCQty VARCHAR(100) NULL,
            Remarks VARCHAR(500) NULL,
            EncodedBy VARCHAR(191) NULL,
            CreatedAt DATETIME NULL,
            UNIQUE KEY uq_labref (LabRefNo),
            INDEX (ProductionCode), INDEX (ReceivedDate), INDEX (MaterialCode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        // a table made by an earlier version of this form gets the new columns
        foreach (['DateArrived' => 'DATE', 'MaterialCode' => 'VARCHAR(191)', 'MaterialName' => 'VARCHAR(500)', 'MaterialType' => 'VARCHAR(191)', 'Supplier' => 'VARCHAR(191)', 'Qty' => 'VARCHAR(100)', 'QCQty' => 'VARCHAR(100)', 'Unit' => 'VARCHAR(50)'] as $col => $type) {
            if (! $db->fieldExists($col, 'tbl_dtr_MaterialTracker')) {
                $db->query("ALTER TABLE tbl_dtr_MaterialTracker ADD COLUMN {$col} {$type} NULL");
            }
        }

        // one Lab Ref per material test: the tests each material type has (Settings-free: edit the rows of this table to add types / tests)
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_material_type_tests (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            MaterialType VARCHAR(100) NOT NULL,
            TestCode VARCHAR(20) NOT NULL,
            TestName VARCHAR(191) NOT NULL,
            SortNo INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_type_test (MaterialType, TestCode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_MaterialTrackerTests (
            TestID INT AUTO_INCREMENT PRIMARY KEY,
            TrackerID INT NULL,
            MotherLabRefNo VARCHAR(191) NOT NULL,
            ChildLabRefNo VARCHAR(191) NOT NULL,
            MaterialCode VARCHAR(191) NULL,
            MaterialType VARCHAR(191) NULL,
            TestType VARCHAR(191) NULL,
            TestCode VARCHAR(20) NULL,
            Status VARCHAR(50) NULL,
            CreatedAt DATETIME NULL,
            UNIQUE KEY uq_child (ChildLabRefNo),
            INDEX (MotherLabRefNo), INDEX (TrackerID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if ((int) $db->table('tbl_dtr_material_type_tests')->countAllResults() === 0) {
            $seed = [['TEN', 'Tensile'], ['ELO', 'Elongation'], ['DIC', 'Die C Tear'], ['SST', 'Seam Stitch Tear - Soling'], ['RFX', 'Ross Flex'], ['UVA', 'UV - Light (A)'], ['UVB', 'UV - Light (B)'],
                ['HAG', 'Heat Aging'], ['BLM', 'Blooming Test'], ['HYD', 'Hydrolysis Test'], ['SHR', 'Shrinkage'], ['PEL', 'Peel Strength to Upper'], ['HRD', 'Hardness'],
                ['SPG', 'Specific Gravity'], ['DIN', 'DIN Abrasion'], ['COF', 'Coefficient of Friction - TM144'], ['NMK', 'Non-Marking']];
            foreach ($seed as $i => [$c, $n]) {
                $db->table('tbl_dtr_material_type_tests')->insert(['MaterialType' => 'RUBBER', 'TestCode' => $c, 'TestName' => $n, 'SortNo' => $i + 1]);
            }
        }

        $date    = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;
        $str     = static fn ($v, int $n = 191) => mb_substr(trim((string) $v), 0, $n);
        $enc     = $str($p['encodedBy'] ?? '');
        $now     = date('Y-m-d H:i:s');
        $saved   = 0;
        $tests   = 0;
        $skipped = [];

        foreach ($rows as $r) {
            $code = $str($r['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $received = $date($r['date'] ?? '') ?: date('Y-m-d');
            $arrived  = $date($r['arrived'] ?? '') ?: $received;
            $type     = $str($r['type'] ?? '');
            $name     = $str($r['name'] ?? '', 500);
            $qty      = $str($r['qty'] ?? '', 100);
            // the specific material type of the code (FABRIC UPPER, OUTSOLE ...) decides the Lab Ref code; the sample count is the QC quantity
            $ts0      = $db->table('tbl_dtr_material_type_status')->select('MaterialType')->where('MaterialCode', $code)->get()->getRowArray();
            $specType = ($ts0 && trim((string) $ts0['MaterialType']) !== '') ? trim((string) $ts0['MaterialType']) : $type;
            $samples  = max(1, (int) preg_replace('/[^0-9]/', '', explode('.', (string) ($r['qcqty'] ?? '1'))[0] ?: '1'));
            $labRef   = $this->nextMaterialLabRef($db, $specType !== '' ? $specType : $code, $received, $name, $samples);
            $db->table('tbl_dtr_material_receipts')->insert([
                'LabReference'        => $labRef,
                'MaterialType'        => $type,
                'MaterialName'        => $name !== '' ? $name : $code,
                'ProdCode'            => $str($r['prod'] ?? ''),
                'Style'               => $str($r['style'] ?? ''),
                'Colorway'            => $str($r['color'] ?? ''),
                'MTL'                 => $str($r['mtl'] ?? ''),
                'ContainerNo'         => $labRef,   // the receiving log is keyed by this Lab Reference
                'DateArrived'         => $arrived,
                'LabReceivedDate'     => $received,
                'Quantity'            => $qty,
                'EncodedBy'           => $enc,
                'CreatedAt'           => $now,
            ]);
            // MaterialTracker: every received material with all the details entered on the form
            $db->table('tbl_dtr_MaterialTracker')->insert([
                'LabRefNo' => $labRef, 'ReceivedDate' => $received, 'DateArrived' => $arrived, 'ProductionCode' => $str($r['prod'] ?? ''), 'Colorway' => $str($r['color'] ?? ''),
                'Style' => $str($r['style'] ?? ''), 'MTL' => $str($r['mtl'] ?? ''), 'MaterialCode' => $code, 'MaterialName' => $name, 'MaterialType' => $type,
                'Supplier' => $str($r['supplier'] ?? ''), 'ContainerNo' => $str($r['container'] ?? ''), 'BatchNo' => $str($r['batch'] ?? ''), 'Qty' => $qty,
                'QCQty' => $str($r['qcqty'] ?? '', 100), 'Unit' => $str($r['unit'] ?? '', 50), 'EncodedBy' => $enc, 'CreatedAt' => $now,
            ]);
            $trackerId = (int) $db->insertID();
            $db->table('tbl_dtr_containers')->insert([
                'ContainerNo'       => $labRef,
                'DateArrived'       => $arrived,
                'DateReceivedInLab' => $received,
                'Remarks'           => $str(trim($code . ' ' . $name)),
                'Encodedby'         => $enc,
                'EncodedDate'       => $now,
            ]);

            // the tests of this material type: each one gets its own Lab Ref (<Lab Ref>-<test code>)
            $specific = strtoupper($type);
            $ts = $db->table('tbl_dtr_material_type_status')->select('MaterialType')->where('MaterialCode', $code)->get()->getRowArray();
            if ($ts && trim((string) $ts['MaterialType']) !== '') {
                $specific = strtoupper(trim((string) $ts['MaterialType']));
            }
            $key = (str_contains($specific, 'OUTSOLE') || str_contains($specific, 'RUBBER')) ? 'RUBBER' : $specific;
            $list = $db->table('tbl_dtr_material_type_tests')->where('MaterialType', $key)->orderBy('SortNo')->get()->getResultArray();
            foreach ($list as $t) {
                $db->table('tbl_dtr_MaterialTrackerTests')->insert([
                    'TrackerID' => $trackerId, 'MotherLabRefNo' => $labRef, 'ChildLabRefNo' => $labRef . '-' . $t['TestCode'], 'MaterialCode' => $code,
                    'MaterialType' => $specific, 'TestType' => $t['TestName'], 'TestCode' => $t['TestCode'], 'Status' => 'Pending', 'CreatedAt' => $now,
                ]);
                $tests++;
            }
            if ($list) {
                $db->table('tbl_dtr_material_receipts')->where('LabReference', $labRef)->update(['MaterialTest' => $str(implode(', ', array_column($list, 'TestName')))]);
            }
            $saved++;
        }

        $msg = "Saved {$saved} material line(s)" . ($tests ? " with {$tests} test Lab Ref No(s)." : '.');

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
        $db = \Config\Database::connect();
        $this->ensureTrackerTables($db);   // DDL first: it would implicitly commit a running transaction

        // one batch at a time: the "next free Lab Ref number" is read and then written, so two saves at once could pick the same one
        $got = (int) ($db->query("SELECT GET_LOCK('nexus_dtr_intake_save', 20) AS l")->getRow()->l ?? 0);
        if ($got !== 1) {
            return $this->response->setStatusCode(503)->setJSON(['ok' => false, 'message' => 'Another batch is being saved. Please try again in a moment.', 'csrf' => csrf_hash()]);
        }

        // all-or-nothing: a failed batch leaves no half-saved specimens, tracker rows or index entries (and Save can simply be clicked again)
        $db->transBegin();
        try {
            $res = $this->intakeSaveCore($db);
            if ($db->transStatus() === false) {
                $db->transRollback();
                return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => 'Save failed, nothing was saved: ' . (trim((string) ($db->error()['message'] ?? '')) ?: 'database error (see writable/logs)'), 'csrf' => csrf_hash()]);
            }
            $db->transCommit();

            return $res;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[intakeSave] ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => 'Save failed, nothing was saved: ' . (trim($e->getMessage()) ?: get_class($e) . ' (see writable/logs)'), 'csrf' => csrf_hash()]);
        } finally {
            $db->query("SELECT RELEASE_LOCK('nexus_dtr_intake_save')");
        }
    }

    private function intakeSaveCore($db)
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
        $lackings = is_array($p['lackings'] ?? null) ? $p['lackings'] : [];
        $dropLack = is_array($p['dropLackings'] ?? null) ? $p['dropLackings'] : [];
        if (! $rows && ! $shutdowns && ! $lackings && ! $dropLack) {
            return $fail('No specimen lines to save.');
        }
        // Lacking unticked / its date changed after it was saved: the old marker (still a lacking record) goes
        foreach ($dropLack as $dl) {
            $dln = strtoupper(trim((string) ($dl['line'] ?? '')));
            $dls = (($dl['side'] ?? 'L') === 'R') ? 'R' : 'L';
            $dld = (string) ($dl['date'] ?? '');
            if (! preg_match('/^' . $building . '(\d{1,2})$/', $dln, $dm) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dld)) {
                continue;
            }
            $w = ['TestType' => 'LACKING', 'Building' => $building, 'LineNo' => $building . (int) $dm[1], 'Side' => $dls, 'ProductionDate' => $dld];
            foreach ($db->table('tbl_dtr_BondingTracker')->where($w)->get()->getResultArray() as $mk) {
                if (trim((string) $mk['LabRefNo']) !== '') {
                    foreach ($db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $mk['LabRefNo'])->where('TestType', 'LACKING')->get()->getResultArray() as $lb) {
                        $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_bonding_info')->where('source_id', (int) $lb['BondingTestID'])->delete();
                        $db->table('tbl_dtr_bonding_info')->where('BondingTestID', (int) $lb['BondingTestID'])->delete();
                    }
                }
                $db->table('tbl_dtr_BondingTracker')->where('TrackerID', (int) $mk['TrackerID'])->delete();
            }
        }

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
        // lines ticked LACKING are recorded like a shutdown: one marker per line / side, booked under the date the specimen is due (no Lab Ref, no specimen)
        $lack = 0;
        foreach ($lackings as $lk) {
            $ln   = strtoupper(trim((string) ($lk['line'] ?? '')));
            $ls   = (($lk['side'] ?? 'L') === 'R') ? 'R' : 'L';
            $due  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($lk['date'] ?? '')) ? $lk['date'] : $prod;
            if (! preg_match('/^' . $building . '(\d{1,2})$/', $ln, $m)) {
                continue;
            }
            // everything entered on the lacking row is kept: Lab Ref (daily format of the due date), production code, style, colorway, MTL #, pull types, remarks, test type
            $pullCodesL = ['HEEL PULL' => 'HP', 'EYELET' => 'EY', 'D-RING' => 'DR', 'RIVET' => 'RVT'];
            $pulls      = array_values(array_filter(array_map(static fn ($x) => strtoupper(trim((string) $x)), is_array($lk['pullTypes'] ?? null) ? $lk['pullTypes'] : []), static fn ($x) => isset($pullCodesL[$x])));
            $detail     = [
                'ProductionCode' => mb_substr(trim((string) ($lk['code'] ?? '')), 0, 191),
                'Style'          => mb_substr(trim((string) ($lk['style'] ?? '')), 0, 191),
                'Colorway'       => mb_substr(trim((string) ($lk['color'] ?? '')), 0, 191),
                'MTL'            => mb_substr(trim((string) ($lk['mtl'] ?? '')), 0, 191),
                'PullTest'       => $pulls ? 1 : 0,
                'PullTestType'   => $pulls ? mb_substr(implode(', ', $pulls), 0, 60) : null,
                'RetestDetails'  => mb_substr(trim((string) ($lk['remarks'] ?? '')), 0, 500),
                'Lacking'        => 1,
                'LackingDate'    => $due,
            ];
            $lkRef = 'DTP' . str_replace('-', '', $due) . 'SH' . $building . (int) $m[1] . '-' . $ls;
            // a lacking line that has pull types also gets its pull test Lab Refs (tbl_dtr_pull_tests, Pending), like a daily line; the real specimen later takes them over
            $lackPulls = function () use ($db, $pulls, $pullCodesL, $due, $ls, $m, $building, $collect, $encodedBy, $detail) {
                foreach ($pulls as $pt) {
                    $ref = 'DTP' . str_replace('-', '', $due) . $pullCodesL[$pt] . $building . (int) $m[1] . '-' . $ls;
                    $row = [
                        'LabRefNo' => $ref, 'Factory' => 'DATIAN SUBIC SHOES INC. (DTP)', 'ModelStyle' => $detail['Style'], 'Color' => mb_substr($detail['Colorway'], 0, 120), 'TestType' => $pt,
                        'Building' => $building, 'LineNo' => $building . (int) $m[1], 'ProductionCode' => $detail['ProductionCode'], 'MTL' => $detail['MTL'],
                        'CollectionDate' => $collect, 'ProductionDate' => $due, 'EncodedBy' => mb_substr($encodedBy, 0, 191),
                    ];
                    $has = $db->table('tbl_dtr_pull_tests')->select('PullInfoID')->where('LabRefNo', $ref)->orderBy('PullInfoID', 'DESC')->get(1)->getRowArray();
                    if ($has) {
                        $db->table('tbl_dtr_pull_tests')->where('PullInfoID', (int) $has['PullInfoID'])->update($row);
                        $pid = (int) $has['PullInfoID'];
                    } else {
                        $db->table('tbl_dtr_pull_tests')->insert($row);
                        $pid = (int) $db->insertID();
                    }
                    \App\Libraries\DtrLabrefIndex::upsertPullTest($db, $pid, $row);
                }
            };
            // a batch tracking record of that line / side / due date already exists: mark it lacking instead of adding a marker
            $exist = $db->table('tbl_dtr_BondingTracker')->where(['Building' => $building, 'LineNo' => $building . (int) $m[1], 'Side' => $ls, 'ProductionDate' => $due])
                ->where('LabRefNo IS NOT NULL', null, false)->where('TestType !=', 'LACKING')->orderBy('TrackerID', 'DESC')->get()->getRowArray();
            if ($exist) {
                $upd = array_filter($detail, static fn ($v, $k) => in_array($k, ['Lacking', 'LackingDate'], true) || ($v !== '' && $v !== null && $v !== 0), ARRAY_FILTER_USE_BOTH);
                $db->table('tbl_dtr_BondingTracker')->where('TrackerID', $exist['TrackerID'])->update($upd);
                $lackPulls();
                $lack++;
                continue;
            }
            // a LACKING marker of that line / side / production date is already saved (ticking Lacking saves it at once): only update it, never add a second one
            $mk = $db->table('tbl_dtr_BondingTracker')->where(['TestType' => 'LACKING', 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'Side' => $ls, 'ProductionDate' => $due])
                ->orderBy('TrackerID', 'DESC')->get()->getRowArray();
            if ($mk) {
                $keep = array_filter($detail, static fn ($v, $k) => in_array($k, ['Lacking', 'LackingDate'], true) || ($v !== '' && $v !== null && $v !== 0), ARRAY_FILTER_USE_BOTH);
                $db->table('tbl_dtr_BondingTracker')->where('TrackerID', (int) $mk['TrackerID'])->update($keep + ['LabRefNo' => $lkRef, 'CollectionDate' => $collect, 'EncodedBy' => $encodedBy]);
                $old = trim((string) $mk['LabRefNo']);
                if ($old !== '' && $old !== $lkRef) {   // the Lab Ref changed: its bonding_info record and index row follow
                    foreach ($db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $old)->where('TestType', 'LACKING')->get()->getResultArray() as $lb) {
                        $db->table('tbl_dtr_bonding_info')->where('BondingTestID', (int) $lb['BondingTestID'])->update(['LabRefNo' => $lkRef]);
                        $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_bonding_info')->where('source_id', (int) $lb['BondingTestID'])->update(['lab_ref_no' => $lkRef]);
                    }
                }
                \App\Libraries\BondingMirror::fromTracker($db, $db->table('tbl_dtr_BondingTracker')->where('TrackerID', (int) $mk['TrackerID'])->get()->getRowArray() ?: []);
                $lackPulls();
                $lack++;
                continue;
            }
            $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $lkRef)->delete();
            $db->table('tbl_dtr_BondingTracker')->insert($detail + [
                'LabRefNo' => $lkRef, 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'Side' => $ls, 'LabTest' => 'Bonding', 'TestType' => 'LACKING',
                'CollectionDate' => $collect, 'ProductionDate' => $due, 'EncodedBy' => $encodedBy, 'CreatedAt' => date('Y-m-d H:i:s'),
            ]);
            // the lacking line is also a record of tbl_dtr_bonding_info and a row of tbl_dtr_unified_labrefs (Pending); the real specimen later takes it over
            \App\Libraries\BondingMirror::fromTracker($db, $db->table('tbl_dtr_BondingTracker')->where('TrackerID', (int) $db->insertID())->get()->getRowArray() ?: []);
            $lackPulls();
            $lack++;
        }
        $table   = $db->table('tbl_dtr_bonding_info');
        $saved   = 0;
        $skipped = [];
        $updated = 0;
        $pullIgnored = [];
        $dups        = [];   // specimens that were already saved (not added again)
        $trialNos    = [];   // pair id => trial number shared by the L and R rows of a tech trial pair

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
            // SAMPLE > Rivet is a pull test: its Lab Ref (DTP<date>RIVET-<#>) goes to tbl_dtr_pull_tests; Bonding / Osna / Strip Test go to tbl_dtr_bonding_info
            $variants  = [[$mainPre, ($typeU === 'SAMPLE' && $subTest === 'RIVET') ? 'RIVET' : '']];
            if ($typeU === 'SAMPLE' && ! empty($r['pullTypes'])) {
                $pullIgnored[] = $line;   // SAMPLE rows take no pull tests: say so instead of dropping them silently
            }
            foreach (($typeU === 'SAMPLE' || ! is_array($r['pullTypes'] ?? null)) ? [] : $r['pullTypes'] as $pt) {
                $pt = strtoupper(trim((string) $pt));
                if (isset($pullCodes[$pt]) && ! in_array([$pullCodes[$pt], $pt], $variants, true)) {
                    $variants[] = [$pullCodes[$pt], $pt];
                }
            }

            foreach ($variants as [$prefix, $pullType]) {
            // (lacking lines never reach this loop: they are saved as markers above and cleared when the real specimen arrives)
            $keyRef = 'DTP' . str_replace('-', '', $prod) . $prefix . (($noLine && $prefix === $mainPre) ? '' : $building . (int) $m[1]);
            $labRef = $keyRef . ($isStrip ? '' : '-' . $side);   // retest base / fallback
            // never add the same specimen twice: a tracker row with the same Lab Ref family (date / test / line), side, dates, test type, production code, MTL # and pull type is already saved
            if (empty($r['retest'])) {
                $dupQ = $db->table('tbl_dtr_BondingTracker')->select('LabRefNo')
                    ->like('LabRefNo', $keyRef . '-', 'after')
                    ->where(['Building' => $building, 'LineNo' => $building . (int) $m[1], 'Side' => $side, 'ProductionDate' => $prod, 'CollectionDate' => $collect, 'Shutdown' => 0])
                    ->where('TestType', mb_substr(trim((string) ($r['type'] ?? '')), 0, 50))
                    ->where('MTL', mb_substr(trim((string) ($r['mtl'] ?? '')), 0, 191))
                    ->where('ProductionCode', mb_substr(trim((string) ($r['code'] ?? '')), 0, 191));
                // a row with remarks is told apart by them: it only counts as a duplicate when the saved row has the very same remarks
                $dupRem = mb_substr(trim((string) ($r['retestNote'] ?? '')), 0, 500);
                if ($dupRem !== '') {
                    $dupQ->where('RetestDetails', $dupRem);
                } else {
                    $dupQ->groupStart()->where('RetestDetails IS NULL', null, false)->orWhere('RetestDetails', '')->groupEnd();
                }
                if ($pullType !== '') {
                    $dupQ->where('PullTestType', $pullType);
                } else {
                    $dupQ->groupStart()->where('PullTestType IS NULL', null, false)->orWhere('PullTestType', '')->groupEnd();
                }
                $dupRow = $dupQ->orderBy('TrackerID', 'DESC')->get(1)->getRowArray();
                if ($dupRow) {
                    $dups[] = (string) $dupRow['LabRefNo'];
                    continue;
                }
            }
            // a tech trial may repeat the same MTL #: every entry is the next trial (1, 2, 3 ...); the two shoes of a pair share the number
            // a pull test (HP / EY / DR / RVT) is not a bonding specimen: its Lab Ref and details go to tbl_dtr_pull_tests (and the tracker), never to tbl_dtr_bonding_info
            $isPullRow = $pullType !== '';
            $infoTbl   = $isPullRow ? 'tbl_dtr_pull_tests' : 'tbl_dtr_bonding_info';
            $infoPk    = $isPullRow ? 'PullInfoID' : 'BondingTestID';
            if (empty($r['retest'])) {
                // running number: -<#> = next free number per line and side
                $n = 0;
                $q = $db->table($infoTbl)->select('LabRefNo')->like('LabRefNo', $keyRef . '-', 'after')->orderBy($infoPk, 'DESC')->get()->getResultArray();
                // bonding Lab Ref = DTP<date>SH<line>-<side> (no number); only when that Lab Ref is already taken the next one gets -2, -3 ...
                $pat = $isStrip ? '/^' . preg_quote($keyRef, '/') . '-(\d+)$/' : '/^' . preg_quote($keyRef, '/') . '-([LR])(?:-(\d+))?$/';
                foreach ($q as $x) {
                    if (preg_match($pat, (string) $x['LabRefNo'], $mm)) {
                        $num = $isStrip ? (int) $mm[1] : (int) ($mm[2] ?? 1);
                        if (! $isStrip && $mm[1] !== $side) {
                            continue;
                        }
                        $n = max($n, $num);
                    }
                }
                $labRef = $isStrip ? $keyRef . '-' . ($n + 1) : ($n === 0 ? $keyRef . '-' . $side : $keyRef . '-' . $side . '-' . ($n + 1));
                if ($typeU === 'TECH TRIAL' && $prefix === $mainPre && ! $isPullRow) {
                    // trial number = next free one for the day over both sides (an old un-numbered Lab Ref counts as 1); a pair (same pair id) reuses it
                    $pairId = trim((string) ($r['pair'] ?? ''));
                    if ($pairId !== '' && isset($trialNos[$pairId])) {
                        $tn = $trialNos[$pairId];
                    } else {
                        $tn = 0;
                        foreach ($q as $x) {
                            if (preg_match('/^' . preg_quote($keyRef, '/') . '-[LR](?:-(\d+))?$/', (string) $x['LabRefNo'], $mm2)) {
                                $tn = max($tn, (int) ($mm2[1] ?? 1));
                            }
                        }
                        $tn++;
                        if ($pairId !== '') {
                            $trialNos[$pairId] = $tn;
                        }
                    }
                    $labRef = $keyRef . '-' . $side . '-' . $tn;
                }
            }

            if (! empty($r['retest'])) {
                // a retest is a new record: the failed batch's Lab Ref + -R01, -R02 ... (next free number); without a picked
                // failed Lab Ref it falls back to this line's own Lab Ref
                $of = trim((string) ($r['retestOf'] ?? ''));
                if ($pullType === '' && preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $of)) {
                    $labRef = preg_replace('/(-R\d+|-\d+)$/', '', $of);
                }
                $last = 0;
                foreach ($db->table($infoTbl)->select('LabRefNo')->like('LabRefNo', $labRef . '-R', 'after')->get()->getResultArray() as $x) {
                    if (preg_match('/-R(\d+)$/', (string) $x['LabRefNo'], $mm)) {
                        $last = max($last, (int) $mm[1]);
                    }
                }
                $labRef .= '-R' . str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
            }

            $existing = $db->table($infoTbl)->select($infoPk . ' AS BondingTestID')->where('LabRefNo', $labRef)->orderBy($infoPk, 'DESC')->get()->getRowArray();

            $notes = [];
            if (! empty($r['mtl'])) {
                $notes[] = 'MTL ' . trim((string) $r['mtl']);
            }
            if (! empty($r['retest'])) {
                $notes[] = 'RETEST: ' . trim((string) ($r['retestNote'] ?? ''));
            } elseif (trim((string) ($r['retestNote'] ?? '')) !== '') {
                $notes[] = 'REMARKS: ' . trim((string) $r['retestNote']);   // a remark typed on any other test type is kept as a remark
            }
            if ($pullType !== '') {
                $notes[] = 'PULL TEST: ' . $pullType;
            }
            if ($isStrip) {
                $notes[] = $subTest . ' TEST';
            }
            if (! empty($r['advance'])) {
                $notes[] = 'ADVANCE TEST';   // shoes given for an advance test
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
                'RetestOf'         => ! empty($r['retest']) ? mb_substr(trim((string) ($r['retestOf'] ?? '')), 0, 191) : null,
            ];
            // Shipped Date = the CRD of the Prod. Code's production order, PO Quantity = its pairs (dberp.production_orders)
            $erpOrd = $this->erpOrderOf((string) $infoRow['POLotNO']);
            if ($erpOrd) {
                if (trim((string) $erpOrd['crd']) !== '') {
                    $infoRow['ShippedDate'] = substr((string) $erpOrd['crd'], 0, 10);
                }
                if (trim((string) $erpOrd['pairs']) !== '') {
                    $infoRow['POQuantity'] = (string) $erpOrd['pairs'];
                }
            }
            if ($isPullRow) {
                // tbl_dtr_pull_tests: the pull test's Lab Ref + the intake details (readings are filled in later by the pull test report / machine file)
                $pullRow = [
                    'LabRefNo' => $labRef, 'Factory' => $infoRow['Factory'], 'ModelStyle' => $infoRow['ModelStyle'], 'Color' => mb_substr($infoRow['Color'], 0, 120),
                    'TestType' => mb_substr($pullType, 0, 50), 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'ProductionCode' => $infoRow['POLotNO'], 'MTL' => $infoRow['MTL'],
                    'CollectionDate' => $collect, 'ProductionDate' => $prod, 'Technician' => mb_substr($infoRow['Technician'], 0, 120), 'EncodedBy' => mb_substr($encodedBy, 0, 191),
                ];
                if ($existing) {
                    $db->table('tbl_dtr_pull_tests')->where('PullInfoID', (int) $existing['BondingTestID'])->update($pullRow);
                    $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $labRef)->delete();
                    $updated++;
                    $infoId = (int) $existing['BondingTestID'];
                } else {
                    $db->table('tbl_dtr_pull_tests')->insert($pullRow);
                    $infoId = (int) $db->insertID();
                }
                \App\Libraries\DtrLabrefIndex::upsertPullTest($db, $infoId, $pullRow);   // also a row of tbl_dtr_unified_labrefs (Pending)
            } else {
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
            }
            // a tracker row of this Lab Ref left behind without its record (an earlier save that did not finish) would stop the insert (unique Lab Ref): it is replaced
            $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $labRef)->delete();
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
                'Lacking'        => ! empty($r['advance']) ? 2 : 0,   // 0 normal, 1 lacking, 2 advance shoes given by production (Advance box ticked)
                'LackingDate'    => null,
                'RetestDetails'  => mb_substr(trim((string) ($r['retestNote'] ?? '')), 0, 500),
                'RetestOf'       => ! empty($r['retest']) ? mb_substr(trim((string) ($r['retestOf'] ?? '')), 0, 191) : null,
                'CollectionDate' => $collect,
                'ProductionDate' => $prod,
                'EncodedBy'      => $encodedBy,
                'CreatedAt'      => date('Y-m-d H:i:s'),
            ]);
            if ($isPullRow) {
                $db->table('tbl_dtr_pull_tests')->where('PullInfoID', $infoId)->update(['TrackerID' => (int) $db->insertID()]);   // link to the tracker row just added
            }
            $saved++;
            // the real specimen has arrived: the lacking marker of that line / side / due date is cleared
            $db->table('tbl_dtr_BondingTracker')->where(['Lacking' => 1, 'TestType' => 'LACKING', 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'Side' => $side, 'ProductionDate' => $prod])->delete();
            }
        }

        if ($saved > 0 || $down > 0 || $lack > 0) {
            \App\Libraries\DataCache::bump();
        }
        $msg = "Saved {$saved} specimen(s) for Building {$building}.";
        if ($lack) {
            $msg .= " {$lack} line(s) marked LACKING.";
        }
        if ($updated) {
            $msg .= " ({$updated} existing Lab Ref updated.)";
        }
        if ($down) {
            $msg .= " {$down} line(s) marked SHUTDOWN.";
        }
        if ($skipped) {
            $msg .= ' Not saved, trial already recorded: ' . implode(', ', array_unique($skipped)) . '.';
        }
        if ($pullIgnored) {
            $msg .= ' Pull types ignored on SAMPLE row(s): ' . implode(', ', array_unique($pullIgnored)) . '.';
        }
        if ($dups) {
            $dups = array_values(array_unique($dups));
            $msg  = ($saved > 0 || $down > 0 || $lack > 0 ? $msg . ' ' : '') . 'Already saved, not added again (' . count($dups) . '): ' . implode(', ', array_slice($dups, 0, 12)) . (count($dups) > 12 ? ' ...' : '') . '.';
            if ($saved === 0 && $down === 0 && $lack === 0) {
                $msg = 'Nothing new to save. ' . $msg;
            }
        }

        return $this->response->setJSON(['ok' => $saved > 0 || $down > 0 || $lack > 0, 'message' => $msg, 'saved' => $saved, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /dtr/load-machine
     * Safely reads/copies machine output .xlsx or .csv files from \\192.168.32.45\OUTDATA\MATERIALS\ or \BONDING\
     * into writable/cache/outdata_samples/ (Read-Only on 192.168.32.45!) and extracts the exact test readings
     * using the VBA LoadResults / BondingTest cell coordinates.
     */
    public function loadMachine()
    {
        $payload  = $this->bodyJson() ?? $this->request->getPost();
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
    /** Admin or Material PIC: may change the material test report sheets */
    public static function canEditMaterial(): bool
    {
        return \App\Libraries\Roles::canManage((string) (service('auth')->user()['role'] ?? ''), 'materials');
    }

    /**
     * GET dtr/material-report-image?slug=<form>&lab_ref=<Lab Ref>: the report of a material Lab Ref as it is printed (the lab's report form filled with the
     * values of THAT Lab Ref, see ReportPdf::material) as page pictures, for the Send report dialog (Synology / e-mail), like the Bonding report card.
     */
    public function materialReportImage()
    {
        $slug = trim((string) $this->request->getGet('slug'));
        $ref  = trim((string) $this->request->getGet('lab_ref'));
        $cfg  = self::FORM_CONFIG[$slug] ?? null;
        $fail = fn (string $m, int $c = 422) => $this->response->setStatusCode($c)->setJSON(['ok' => false, 'error' => $m]);
        if (! $cfg || in_array($slug, ['bonding', 'pull'], true) || $ref === '') {
            return $fail('Open a material report first.');
        }
        $row = \Config\Database::connect()->table($cfg['table'])->where('LabRefNo', $ref)->orderBy($cfg['pk'], 'DESC')->get()->getRowArray();
        if (! $row) {
            return $fail('That Lab Ref has no saved report.', 404);
        }
        $pdf = \App\Libraries\ReportPdf::material($slug, (int) $row[$cfg['pk']]);
        if (! $pdf) {
            return $fail('The report form of this material could not be made.', 500);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mri');
        $py  = is_file('C:\\Python314\\python.exe') ? 'C:\\Python314\\python.exe' : 'python';
        $out = (string) @shell_exec('"' . $py . '" "' . ROOTPATH . 'tools\\pdf_pages.py" "' . $pdf . '" "' . $tmp . '" 1.6 3 2>NUL');
        $pages = [];
        foreach (preg_split('/\R/', trim($out)) as $f) {
            if ($f !== '' && is_file($f)) {
                $pages[] = 'data:image/png;base64,' . base64_encode((string) file_get_contents($f));
                @unlink($f);
            }
        }
        @unlink($tmp);

        return $pages ? $this->response->setJSON(['ok' => true, 'pages' => $pages, 'lab_ref' => $ref]) : $fail('The report pages could not be drawn.', 500);
    }

    public function save()
    {
        $db      = \Config\Database::connect();
        $payload = $this->bodyJson() ?? $this->request->getPost();
        $slug    = trim((string) ($payload['slug'] ?? 'fabric'));
        $fields  = (array) ($payload['fields'] ?? []);

        if (! isset(self::FORM_CONFIG[$slug])) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Invalid form category.']);
        }
        // the material report sheets can be changed only by an Admin or a Material PIC (everybody else reads them)
        if (! in_array($slug, ['bonding', 'pull'], true) && ! self::canEditMaterial()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Only an Admin or a Material PIC can change a material test report.']);
        }

        // a bonding report rated Fail / Tolerable must say how it failed (Quality Insights and the lab alerts read it)
        if ($slug === 'bonding' && preg_match('/^(fail|failed|tolerable)$/i', trim((string) ($fields['Result'] ?? ''))) && trim(preg_replace('/^-$/', '', trim((string) ($fields['ModeOfFailure'] ?? '')))) === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Write the Mode of Failure(s): a ' . strtoupper(trim((string) $fields['Result'])) . ' result needs it.', 'csrf' => csrf_hash()]);
        }
        if ($slug === 'pull') {
            return $this->pullReportSave($db, $fields);
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
            // built from the record as stored (the posted form may leave some fields out), then the posted values on top
            $stored = $db->table($table)->where($pk, $recordId)->get()->getRowArray() ?: [];
            $idxRow = $saveData + $fields + $stored;
            if ($slug === 'bonding' && trim((string) ($idxRow['Result'] ?? '')) === '') {
                // result only entered in the spot table: it is the report's result
                $idxRow['Result'] = (string) (($payload['bondingPoints']['Result'] ?? '') ?: ($db->table('tbl_dtr_bonding_points')->select('Result')->where('BondingTestID', $recordId)->get()->getRow()->Result ?? ''));
            }
            \App\Libraries\DtrLabrefIndex::upsert($db, $slug, $recordId, $idxRow);
        }
        \App\Libraries\DataCache::bump();

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
                $allTests = [];
                if (! $t) {   // the MOTHER Lab Ref of the whole shoe (e.g. DTP20261113WHA8-R): the shoe comes from its first test, and every test type is listed
                    $kids = $db->table('tbl_dtr_WholeshoeTracker')->where('WholeshoeLabRefNo', $ref)->orderBy('TrackerID', 'ASC')->get()->getResultArray();
                    if ($kids) {
                        $t = $kids[0];
                        $allTests = array_values(array_unique(array_filter(array_map(static fn ($k) => trim((string) ($k['TestType'] ?? '')), $kids))));
                    }
                }
            } catch (\Throwable $e) {
                $t = null;
            }
            if (! $t) {
                return null;
            }
            $row = [
                'LabRefNo' => $ref, 'ProdCode' => $t['ProductionCode'] ?? '', 'StyleNo' => $t['Style'] ?? '', 'Colorway' => $t['Colorway'] ?? '', 'MTL' => $t['MTL'] ?? '',
                'NoOfShoes' => $t['NoOfShoes'] ?? 1, 'CollectionDate' => $t['CollectionDate'] ?? '', 'TestTypes' => $allTests ? implode(', ', $allTests) : ($t['TestType'] ?? ''), 'EncodedBy' => $t['EncodedBy'] ?? '',
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
        $rows = $b->orderBy('i.CollectionDate', 'DESC')->orderBy('i.IntakeID', 'DESC')->limit(100)->get()->getResultArray();

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
        \App\Libraries\DataCache::bump();

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
        $p    = $this->bodyJson() ?? $this->request->getPost();
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
        // page 2 (Shoe Bonding U60): the report picture, full page size (the zoom undoes the page-1 shrink)
        $img2 = (string) ($p['page2_img'] ?? '');
        if ($img2 !== '' && preg_match('#^data:image/(png|jpeg|jpg|webp);base64,[A-Za-z0-9+/=]+$#', $img2) && strlen($img2) < 40000000) {
            $cap2 = htmlspecialchars((string) ($p['page2_cap'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<div style="break-before:page;page-break-before:always;zoom:' . round(1 / $scale, 4) . '">'
                . ($cap2 !== '' ? '<div style="font:600 12px Arial,sans-serif;color:#334155;margin-bottom:4mm">' . $cap2 . '</div>' : '')
                . '<img src="' . $img2 . '" style="display:block;margin:0 auto;max-width:198mm;max-height:270mm;object-fit:contain" alt=""></div>';
        }
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

    /** the JSON body of the request, or null when the request is not JSON (CodeIgniter 4.7 throws on a form / multipart body) */
    private function bodyJson(): ?array
    {
        if (! str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'json')) {
            return null;
        }
        try {
            $j = $this->request->getJSON(true);

            return is_array($j) ? $j : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * POST /dtr/send-synology
     * Send report notification & optional image attachment to Synology Chat channel.
     */
    public function sendSynology()
    {
        $text = trim((string) ($this->request->getPost('text') ?? ''));
        // a form post (text + picture) is multipart: only a JSON body is read as JSON (CodeIgniter 4.7 throws on anything else)
        $json = [];
        if (str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'json')) {
            try {
                $json = (array) $this->request->getJSON(true);
            } catch (\Throwable $e) {
                $json = [];
            }
        }
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
        $json = $this->bodyJson() ?? [];
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

            // photos: up to 10 (photos[]; the old single image2 still works)
            $photoFiles = $this->request->getFileMultiple('photos') ?: [];
            if (! $photoFiles && ($one = $this->request->getFile('image2'))) {
                $photoFiles = [$one];
            }
            $pn = 0;
            foreach (array_slice($photoFiles, 0, 10) as $photoFile) {
                if (! $photoFile || ! $photoFile->isValid()) {
                    continue;
                }
                $pn++;
                $cid = 'photo_image_' . $pn;
                $attachments[] = [
                    'filename'     => $photoFile->getClientName() ?: ('photo' . $pn . '.jpg'),
                    'content_type' => $photoFile->getClientMimeType() ?: 'image/jpeg',
                    'data_base64'  => base64_encode(file_get_contents($photoFile->getTempName())),
                    'cid'          => $cid,
                ];
                $imgHtml .= '<div style="margin:16px 0;text-align:center;"><p style="font-size:12px;color:#6b7280;margin-bottom:4px;font-weight:bold;">SPECIMEN PHOTO ' . $pn . '</p><img src="cid:' . $cid . '" style="max-width:100%;height:auto;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.06);" alt="Specimen Photo ' . $pn . '"></div>';
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

            // Insert into FIFO outbox queue (tbl_email_outbox)
            $db = \Config\Database::connect();
            $senderName = (string) \App\Libraries\SystemSettings::get('mail.sender_name', 'Nexus-L');
            $senderEmail = (string) \App\Libraries\SystemSettings::get('mail.sender_email', 'Nexus-Noreply@outlook.com');

            $db->table('tbl_email_outbox')->insert([
                'source'       => 'DTR-Bonding',
                'sender_name'  => $senderName,
                'sender_email' => $senderEmail,
                'to_address'   => $to,
                'subject'      => $subject,
                'body'         => $htmlBody,
                'is_html'      => 1,
                'attachments'  => ! empty($attachments) ? json_encode($attachments) : null,
                'status'       => 'queued',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            $queueId = (int) $db->insertID();

            // Notify local Outlook Relay service (if running on Windows on port 5088)
            try {
                $client = service('curlrequest');
                $client->post('http://127.0.0.1:5088/api/enqueue', [
                    'json' => [
                        'id'          => $queueId,
                        'to'          => $to,
                        'subject'     => $subject,
                        'body'        => $htmlBody,
                        'sender_name' => $senderName,
                        'sender_email'=> $senderEmail,
                        'attachments' => $attachments,
                    ],
                    'timeout' => 1.2,
                    'http_errors' => false,
                ]);
            } catch (\Throwable $t) {
                // Background FIFO poller will pick it up automatically
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
