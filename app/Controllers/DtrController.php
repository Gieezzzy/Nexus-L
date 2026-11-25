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
    private array $dtrSvc = [];

    /** A DTR helper service (TrackerSchema, EditSync, PullReport, ReportImages, MaterialLabRef, BondingFile, WholeShoeReport, Pdf). */
    private function dtr(string $name): object
    {
        $cls = '\\App\\Services\\Dtr\\Dtr' . $name;

        return $this->dtrSvc[$name] ??= new $cls();
    }

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

    public function index(): string
    {
        $db        = \Config\Database::connect();
        $this->dtr('TrackerSchema')->ensureTrackerTables($db);
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
            $this->dtr('EditSync')->runDueEditSyncs();
        } catch (\Throwable $e) {
            // the list still loads
        }
        // IQC Container Receiving Log: one row per material of a container (a container with no material yet shows alone);
        // joined with tbl_dtr_material_receipts & tbl_dtr_unified_labrefs to display test details
        $rc = $db->tableExists('tbl_dtr_material_receipts');
        // a container saved by Material Receive is keyed by its mother Lab Ref: its details are read from tbl_dtr_MaterialTracker (the one table of the entry)
        $mt  = $rc && $db->tableExists('tbl_dtr_MaterialTracker') && $db->fieldExists('MotherLabRefNo', 'tbl_dtr_MaterialTracker');
        $trk = static fn (string $expr): string => "(SELECT {$expr} FROM tbl_dtr_MaterialTracker t WHERE t.MotherLabRefNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci)";
        $containers = $db->query('SELECT c.ContainerID, c.ContainerNo, c.DateArrived, c.DateReceivedInLab, c.Remarks AS ContainerRemarks, c.Encodedby,
                m.MaterialID, m.MaterialCode, m.MaterialName, m.MaterialProdCode, m.LabReference, m.IQCERP, m.TestingStatus, m.FinalStatus, m.Remarks AS MaterialRemarks'
            . ($mt ? ', COALESCE(' . $trk('MIN(t.MotherLabRefNo)') . ', (SELECT r.LabReference FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptLabRef,
                COALESCE(' . $trk("GROUP_CONCAT(DISTINCT t.TestType SEPARATOR ', ')") . ', (SELECT r.MaterialTest FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptType,
                COALESCE(' . $trk('MAX(t.Style)') . ', (SELECT r.Style FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptStyle,
                COALESCE(' . $trk('MAX(t.Colorway)') . ', (SELECT r.Colorway FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptColor,
                COALESCE(' . $trk('MAX(t.MTL)') . ', (SELECT r.MTL FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptMTL,
                COALESCE(' . $trk('MAX(t.Qty)') . ', (SELECT r.Quantity FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS Quantity,
                COALESCE(' . $trk('MAX(t.MaterialName)') . ', (SELECT r.MaterialName FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptName,
                COALESCE(' . $trk('MAX(t.ProductionCode)') . ', (SELECT r.ProdCode FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1)) AS ReceiptProd'
            : ($rc ? ', (SELECT r.LabReference FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptLabRef,
                (SELECT r.MaterialTest FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptType,
                (SELECT r.Style FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptStyle,
                (SELECT r.Colorway FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptColor,
                (SELECT r.MTL FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptMTL,
                (SELECT r.Quantity FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS Quantity,
                (SELECT r.MaterialName FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptName,
                (SELECT r.ProdCode FROM tbl_dtr_material_receipts r WHERE r.ContainerNo COLLATE utf8mb4_general_ci = c.ContainerNo COLLATE utf8mb4_general_ci ORDER BY r.ReceiptID DESC LIMIT 1) AS ReceiptProd' : ", NULL AS ReceiptLabRef, NULL AS ReceiptType, NULL AS ReceiptStyle, NULL AS ReceiptColor, NULL AS ReceiptMTL, NULL AS Quantity, NULL AS ReceiptName, NULL AS ReceiptProd"))
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
        // the Lab Tracker opens on ALL dates (newest first); a date range is only used when the calendar icon is used. Set to true to open on Today again.
        $openOnToday    = false;
        $isTodayDefault = ($openOnToday && $activeTab === 'labtracker' && ! $lRange && $this->request->getGet('all') !== '1' && ! in_array($subTab, ['erp', 'cfa', 'schedule', 'failed'], true));
        if ($isTodayDefault) {
            $lFrom  = date('Y-m-d');   // the default is Today (its Daily shoes are the day before's, the other types are yesterday's and today's)
            $lTo    = $lFrom;
            $lRange = true;
            if ($lk === '') {
                $lk = $subTab === 'shoes' ? 'shoes' : 'bonding';
            }
        }
        $lRes  = in_array((string) $this->request->getGet('res'), ['pass', 'tol', 'fail', 'pending', 'fin', 'ong', 'carry'], true) ? (string) $this->request->getGet('res') : '';
        // KPI cards Finished (has a result) / On Going (no result yet) / Pending (carried from the last working day): the Bonding list keeps them for the row filter below
        // (the carried rows and pull tests are decided after the query), the other lists filter the result column
        $lPost = ($lk === 'bonding' && in_array($lRes, ['fin', 'ong', 'carry'], true)) ? $lRes : '';
        if ($lPost !== '') {
            $lRes = '';
        }
        // ?lt= : a Bonding list summary button (daily | trial | sample | retest | first | advance | lacking | shutdown) lists only that kind of row
        $lType = in_array((string) $this->request->getGet('lt'), ['daily', 'trial', 'sample', 'retest', 'first', 'advance', 'lacking', 'shutdown'], true) ? (string) $this->request->getGet('lt') : '';
        $bondingW = $lk === 'bonding' ? $lqW : [];
        // the AppSheet spelling of a whole shoe Lab Ref (DTP20261028WSA8-R) is found too: NEXUS-L calls the parent DTP20261028WHA8-R
        $shoesW   = $lk === 'shoes' ? array_map(static fn ($w) => preg_replace('/^(DTP\d{8})WS([AB]\d+)/i', '$1WH$2', $w), $lqW) : [];
        $matW     = $lk === 'materials' ? $lqW : [];
        $resSql   = static fn (string $col, string $res): string => match ($res) {
            'pass'    => "UPPER({$col}) LIKE 'PASS%'",
            'tol'     => "UPPER({$col}) LIKE 'TOL%'",
            'fail'    => "(UPPER({$col}) LIKE 'FAIL%' OR UPPER({$col}) = 'REJECTED')",
            'pending', 'ong', 'carry' => "COALESCE({$col}, '') = ''",
            'fin'     => "(UPPER({$col}) LIKE 'PASS%' OR UPPER({$col}) LIKE 'TOL%' OR UPPER({$col}) LIKE 'FAIL%' OR UPPER({$col}) = 'REJECTED')",
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
        $lqMax = ($bondingW || ($lk === 'bonding' && ($lRange || $lRes !== '' || $lPost !== '' || $lsKey !== '' || $lType !== ''))) ? max(8000, $bondingPage * $lPer * 2) : max(60, $bondingPage * $lPer * 2);
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
        // a First Article / Retest counts on the day it was TESTED (a Saturday collection is tested on Monday); until it has a tested date it stays on its collection date
        // a Daily shoe whose Lab Ref carries its own production day and that was TESTED that same day (e.g. DTP20261125SHA7-L, produced and tested on 11-25) counts on that day, not the next one
        // (from 2026-11-25 on: the shoes of earlier days were counted the next day and keep their days)
        $sameDay  = "(LEFT(NULLIF(b.TestedDate, ''), 10) >= '2026-11-25' AND LEFT(NULLIF(b.TestedDate, ''), 10) = {$dailyBase} AND CONCAT(SUBSTR(b.LabRefNo, 4, 4), '-', SUBSTR(b.LabRefNo, 8, 2), '-', SUBSTR(b.LabRefNo, 10, 2)) = {$dailyBase})";
        $effE     = "(CASE WHEN {$dailyCond} THEN (CASE WHEN {$sameDay} THEN {$dailyBase} ELSE DATE_ADD({$dailyBase}, INTERVAL (CASE WHEN DAYOFWEEK({$dailyBase}) = 7 THEN 2 ELSE 1 END) DAY) END) WHEN ({$ttE} LIKE '%FIRST%' OR {$ttE} LIKE '%RETEST%') THEN COALESCE(NULLIF(LEFT(b.TestedDate, 10), ''), {$collE}) ELSE {$collE} END)";   // no collection on a Sunday: Saturday's Daily shoes count on Monday
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
            // a line loaded LATE (production date earlier than the collection date) stays with its production batch, like its pull test
            $base = "(CASE WHEN {$px}ProductionDate IS NOT NULL AND {$px}ProductionDate < {$px}CollectionDate THEN {$px}ProductionDate ELSE {$px}CollectionDate END)";
            $coll = "DATE_ADD({$base}, INTERVAL (CASE WHEN DAYOFWEEK({$base}) = 7 THEN 2 ELSE 1 END) DAY)";

            return "({$coll} >= {$f} AND {$coll} <= {$t})";
        };
        $rangeP   = '1=1';
        if ($lk === 'bonding' && $lRange) {
            $nonPull0 = "b.LabRefNo NOT REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)'";
            // PENDING: a test scheduled on the working day before the range that did not get its result is carried to this range (the next working day), and counts in its Total
            $pendFrom = $dShift($lFrom, (int) date('N', strtotime($lFrom)) === 1 ? -3 : -1);
            $rangeP = "(({$effE} >= " . $db->escape($lFrom) . " AND {$effE} <= " . $db->escape($lTo) . ")"
                . " OR ({$effE} >= " . $db->escape($pendFrom) . " AND {$effE} < " . $db->escape($lFrom) . " AND COALESCE(b.Result, '') = '' AND COALESCE(t.Lacking, 0) = 0 AND {$nonPull0}))";
        }
        $pendFlag = ($lk === 'bonding' && $lRange) ? "({$effE} < " . $db->escape($lFrom) . ")" : '0';
        $nonPull = "b.LabRefNo NOT REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)'";
        // the type chips count what belongs to the day itself: a test carried over from the last working day is listed, but is not counted again here
        $bondingTypeRow = (clone $bondingBaseQ)->where($rangeP, null, false)->select("
            SUM(CASE WHEN ({$dailyCond}) AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_daily,
            SUM(CASE WHEN (COALESCE(t.Lacking, 0) = 1 AND {$nonPull}) AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_lack,
            SUM(CASE WHEN (COALESCE(t.Lacking, 0) = 2 AND {$nonPull}) AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_adv,
            SUM(CASE WHEN ({$ttE} LIKE '%TRIAL%') AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_trial,
            SUM(CASE WHEN ({$ttE} LIKE '%SAMPLE%' OR {$ttE} LIKE '%RIVET%') AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_sample,
            SUM(CASE WHEN ({$ttE} LIKE '%RETEST%') AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_retest,
            SUM(CASE WHEN ({$ttE} LIKE '%FIRST%') AND COALESCE({$pendFlag}, 0) = 0 THEN 1 ELSE 0 END) AS c_first
        ", false)->get()->getRowArray() ?: [];
        $bondingListBase = (clone $bondingBaseQ)->where($rangeP, null, false);   // counts and rows both go by the collection date
        $bSel = "b.BondingTestID, b.LabRefNo, COALESCE(NULLIF(b.ModelStyle, ''), t.Style) AS ModelStyle, COALESCE(NULLIF(b.Color, ''), t.Colorway) AS Color, b.Side, b.Factory, COALESCE({$pdExpr}, b.ProductionDate) AS ProductionDate, b.TestedDate, b.Technician, b.Result, b.LabNotesComments, COALESCE(NULLIF(b.POLotNO, ''), t.ProductionCode) AS POLotNO, COALESCE(NULLIF(b.MTL, ''), t.MTL) AS MTL, b.ReceivedDate, b.Remarks, COALESCE(NULLIF(b.TestType COLLATE utf8mb4_general_ci, ''), t.TestType COLLATE utf8mb4_general_ci) AS TestType, COALESCE(NULLIF(b.MachineNumber, ''), t.MachineNumber) AS MachineNumber, b.FailPart, b.Cause, b.TestDuration, b.RetestOf, b.SourceSystem, t.`LineNo` AS TrackerLine, t.Building AS TrackerBuilding, t.PullTestType, t.Lacking, COALESCE(t.TrackerID, 0) AS TrackerID, {$pendFlag} AS PendCarry";
        $bondingShoesQ = (clone $bondingListBase)->select($bSel, false);
        $bondingDbTotal = (int) (clone $bondingListBase)->countAllResults(false);
        $bLineExpr = "COALESCE(NULLIF(TRIM(t.`LineNo`), ''), CASE WHEN b.LabRefNo REGEXP '^DTP[0-9]{8}[A-Za-z]{2}[ABab][0-9]+' THEN SUBSTRING_INDEX(SUBSTRING(b.LabRefNo, 14), '-', 1) ELSE '' END)";
        $bBldgVal  = "UPPER(LEFT(COALESCE(NULLIF(TRIM(t.Building), ''), TRIM({$bLineExpr}), ''), 1)) COLLATE utf8mb4_general_ci";
        $bBldgSql  = "CASE WHEN {$bBldgVal} = 'A' THEN 0 WHEN {$bBldgVal} = 'B' THEN 1 ELSE 2 END";
        $bNumSql   = "CASE WHEN CAST(SUBSTRING(TRIM({$bLineExpr}), 2) AS UNSIGNED) > 0 THEN CAST(SUBSTRING(TRIM({$bLineExpr}), 2) AS UNSIGNED) ELSE 9999 END";
        $bondingShoes = $bondingShoesQ
            ->orderBy("({$bdtExpr} REGEXP '^20[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])\$') DESC, {$bdtExpr} DESC, {$bBldgSql} ASC, {$bNumSql} ASC, b.LabRefNo ASC, COALESCE(t.TrackerID, 0) DESC, b.BondingTestID DESC", '', false)
            ->limit($lqMax)->get()->getResultArray();
        // lines marked LACKING have no specimen yet but are part of the collection: they are listed too (shutdown marks are not)
        $lackTotal = 0;
        if ($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) {
            // a Lacking marker, or a batch row of the line that was marked Lacking (its own bonding record is still the LACKING placeholder, so the Daily list skips it and this list shows it)
            $lackQ = $db->table('tbl_dtr_BondingTracker')->where('Shutdown', 0)->groupStart()->where('TestType', 'LACKING')
                ->orGroupStart()->where('Lacking', 1)->where('LabRefNo IS NOT NULL', null, false)->where("NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info rb WHERE rb.LabRefNo = tbl_dtr_BondingTracker.LabRefNo COLLATE utf8mb4_unicode_ci AND COALESCE(rb.TestType, '') <> 'LACKING')", null, false)->groupEnd()->groupEnd();
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
                // (a RIVET sample taken today is tested the next working day, so it follows the same rule)
                $rvQ->where("({$rvEff} >= " . $db->escape($lFrom) . " AND {$rvEff} <= " . $db->escape($lTo) . ")", null, false);
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
                // RIVET samples are tested the next working day (Saturday -> Monday)
                $rvEffC = "DATE_ADD({$rvD}, INTERVAL (CASE WHEN DAYOFWEEK({$rvD}) = 7 THEN 2 ELSE 1 END) DAY)";
                $rvCntQ->where($rvEffC . ' >= ' . $db->escape($lFrom), null, false)->where($rvEffC . ' <= ' . $db->escape($lTo), null, false);
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

        // a carried-over (Pending) retest whose shoe was already retested under another Lab Ref (same line + side, e.g. SHA9-L-R01 and SHA9-L-R02) takes that result:
        // it is tested, so it counts as finished instead of Pending
        if ($lk === 'bonding') {
            foreach ($bondingShoes as &$cr) {
                if (empty($cr['PendCarry']) || trim((string) ($cr['Result'] ?? '')) !== '' || ! preg_match('/^DTP\d{8}([A-Z]{2}[A-Z]\d+-[LR])(?:-R\d+)?$/i', (string) ($cr['LabRefNo'] ?? ''), $cm)) {
                    continue;
                }
                // only a LATER retest (higher R number) of the SAME shoe (same production date in the Lab Ref) can stand in: an older retest's result, or another day's shoe, never counts
                preg_match('/^DTP(\d{8})/i', (string) $cr['LabRefNo'], $dm);
                $curN = preg_match('/-R(\d+)$/i', (string) $cr['LabRefNo'], $nm) && str_contains(strtoupper((string) $cr['LabRefNo']), strtoupper($cm[1]) . '-R') ? (int) $nm[1] : 0;
                $sib  = null;
                foreach ($db->query("SELECT LabRefNo, Result, TestedDate, Technician FROM tbl_dtr_bonding_info WHERE LabRefNo REGEXP ? AND LabRefNo <> ? AND COALESCE(Result, '') <> '' ORDER BY TestedDate DESC, BondingTestID DESC", ['^DTP' . $dm[1] . preg_quote(strtoupper($cm[1]), '/') . '-R[0-9]+$', (string) $cr['LabRefNo']])->getResultArray() as $cand) {
                    if (preg_match('/-R(\d+)$/i', (string) $cand['LabRefNo'], $cn) && (int) $cn[1] > $curN) {
                        $sib = $cand;
                        break;
                    }
                }
                if ($sib) {
                    $cr['Result']     = $sib['Result'];
                    $cr['TestedDate'] = $sib['TestedDate'];
                    $cr['Technician'] = $cr['Technician'] ?: $sib['Technician'];
                    if (array_key_exists('RowResult', $cr) && trim((string) $cr['RowResult']) === '') {
                        $cr['RowResult'] = $sib['Result'];
                    }
                    $cr['PendCarry'] = 0;
                }
            }
            unset($cr);
        }

        // Passed / Tolerable / Failed cards = the Result column of the rows listed for the date (a row with its pull tests shows the worst of them: one FAILED report makes the row FAILED, the same for TOLERABLE)
        $bondingResCounts = ['pass' => 0, 'tol' => 0, 'fail' => 0, 'pending' => 0];
        if ($lk === 'bonding') {
            foreach ($bondingShoes as $rr) {
                if (! empty($rr['IsLacking']) || ! empty($rr['IsShutdown'])) {
                    continue;
                }
                $rv = strtoupper(trim((string) (($rr['RowResult'] ?? '') !== '' ? $rr['RowResult'] : ($rr['Result'] ?? ''))));
                if ($rv === '' && ! empty($rr['PendCarry'])) {
                    $bondingResCounts['pending']++;
                }
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

        // KPI card Finished / On Going / Pending: the rows with / without a result, the carried ones apart (lacking and shutdown lines are not reports)
        if ($lk === 'bonding' && $lPost !== '') {
            $bondingShoes = array_values(array_filter($bondingShoes, static function (array $r) use ($lPost): bool {
                if (! empty($r['IsLacking']) || ! empty($r['IsShutdown'])) {
                    return false;
                }
                $rv  = strtoupper(trim((string) (($r['RowResult'] ?? '') !== '' ? $r['RowResult'] : ($r['Result'] ?? ''))));
                $has = $rv === 'ERROR' || $rv === 'REJECTED' || str_starts_with($rv, 'FAIL') || str_starts_with($rv, 'TOL') || str_starts_with($rv, 'PASS');

                return match ($lPost) {
                    'fin'   => $has,
                    'ong'   => ! $has && empty($r['PendCarry']),
                    'carry' => ! $has && ! empty($r['PendCarry']),
                };
            }));
        }

        $bondingFilteredTotal = ($bondingW || ($lk === 'bonding' && ($lRange || $lRes !== '' || $lPost !== '' || $lType !== '')))
            ? count($bondingShoes)
            : max(count($bondingShoes), ($bondingDbTotal + $lackTotal - $droppedPulls));
        $bondingShoes = array_slice($bondingShoes, ($bondingPage - 1) * $lPer, $lPer);

        $shoeTotal  = $db->table('tbl_dtr_shoe_list')->countAllResults();
        $this->dtr('WholeShoeReport')->wsReportTable();   // makes sure tbl_dtr_wholeshoe_reports exists (the list reads the Technician saved in a report)
        // Whole Shoe Collection = every recorded test type (one row each) from tbl_dtr_WholeshoeTracker
        $shoesPage = $lk === 'shoes' ? $lp : 1;
        // Whole Shoe Collection = one row per MOTHER Lab Ref (tbl_dtr_WholeshoeTracker.WholeshoeLabRefNo, e.g. DTP20261113WHA4-R); its test type Lab Refs (FX, UV, WG, HY, HA ...) are the child rows that open under it
        $wsKey = "COALESCE(NULLIF(TRIM(WholeshoeLabRefNo), ''), LabRefNo)";
        // the two sides of one mother Lab Ref (DTP<date>WHA4-L / -R) stay together: a filter (date, result, search) that matches one side keeps the other side in the list too
        $wsBase   = "REGEXP_REPLACE({$wsKey}, '-[LR]\$', '')";
        $wsFilter = function ($q) use (&$wsFilter0, $db, $wsBase, $lk, $lRange, $lRes, $shoesW) {
            if ($lk === 'shoes' && ($lRange || $lRes !== '' || $shoesW)) {
                $sub = $wsFilter0($db->table('tbl_dtr_WholeshoeTracker'))->select("DISTINCT {$wsBase}", false)->getCompiledSelect();
                $q->where("{$wsBase} IN ({$sub})", null, false);
            }

            return $q;
        };
        $wsFilter0 = function ($q) use ($db, $lk, $lRange, $lFrom, $lTo, $lRes, $resSql, $shoesW) {
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
            $keysQ->orderBy("({$agg} IS NULL OR {$agg} = '')", 'ASC', false)->orderBy($agg, $lsDir, false)->orderBy("MIN({$wsBase})", 'ASC', false)->orderBy("MIN(RIGHT({$wsKey}, 1))", 'ASC', false)->orderBy('mt', 'DESC', false);
        } else {
            $wsDate    = "LEFT(COALESCE(NULLIF(ProductionDate, ''), NULLIF(CollectionDate, ''), ''), 10)";
            $wsBldgVal = "UPPER(LEFT(COALESCE(NULLIF(TRIM(Building), ''), TRIM(`LineNo`), ''), 1)) COLLATE utf8mb4_general_ci";
            $wsBldg    = "CASE WHEN {$wsBldgVal} = 'A' THEN 0 WHEN {$wsBldgVal} = 'B' THEN 1 ELSE 2 END";
            $wsNum     = "CASE WHEN TRIM(`LineNo`) REGEXP '^[0-9]+\$' AND CAST(TRIM(`LineNo`) AS UNSIGNED) > 0 THEN CAST(TRIM(`LineNo`) AS UNSIGNED) WHEN CAST(SUBSTRING(TRIM(`LineNo`), 2) AS UNSIGNED) > 0 THEN CAST(SUBSTRING(TRIM(`LineNo`), 2) AS UNSIGNED) ELSE 9999 END";
            $keysQ->orderBy("MAX({$wsDate}) DESC, MIN({$wsBldg}) ASC, MIN({$wsNum}) ASC, MIN({$wsBase}) ASC, MIN(RIGHT({$wsKey}, 1)) ASC, MAX(TrackerID) DESC", '', false);
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
            'labImg'           => ['bonding' => $this->dtr('ReportImages')->reportImageCfg('bonding')['on'], 'shoes' => $this->dtr('ReportImages')->reportImageCfg('shoes')['on'], 'containers' => $this->dtr('ReportImages')->reportImageCfg('containers')['on']],
            'shutTotal'        => $shutTotal,
            'shutToday'        => $shutToday,
            'totalTol'         => $totalTol,
            'recentReports'    => $recentReports,
            'editLocked'       => $this->dtr('EditSync')->lockedEdits($db),
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
            // what was received in the Lab Tracker (Material Receive) belongs to this collection too: listed first, with its tests (page 1, shown whatever the date range, so every test is visible)
            $received = ($page === 1 && $res === '') ? \App\Controllers\MaterialsController::collectionRows($q) : [];
            $total   += count($received);
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
                ORDER BY COALESCE(NULLIF(m.finished_at, ''), NULLIF(m.started_at, ''), NULLIF(m.iqc_received_date, ''), NULLIF(m.created_at, ''), '1970-01-01') DESC, m.id DESC
                LIMIT $limit OFFSET $offset
            ", $binds)->getResultArray();
            $rows = array_merge($received, $rows);
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
     * POST /dtr/export-table {title, range, headers[], rows[][]}: the Lab Tracker table the user sees (all its pages, already filtered by the
     * page) as an .xlsx file. The server only lays the cells out; it reads nothing from the database.
     */
    public function exportTable()
    {
        $p       = $this->request->getJSON(true) ?? [];
        $headers = array_values(array_map(static fn ($h) => mb_substr(trim((string) $h), 0, 120), (array) ($p['headers'] ?? [])));
        $rows    = array_slice((array) ($p['rows'] ?? []), 0, 50000);
        if (! $headers || count($headers) > 60) {
            return $this->response->setStatusCode(422)->setBody('Nothing to export.');
        }
        $title = mb_substr(trim((string) ($p['title'] ?? 'Lab Tracker')), 0, 80) ?: 'Lab Tracker';
        $range = mb_substr(trim((string) ($p['range'] ?? '')), 0, 60);

        $x      = new \App\Libraries\XlsxBuilder(mb_substr($title, 0, 31));
        $navy   = '0F2A47';
        $sTitle = $x->style(['bold' => true, 'size' => 14, 'color' => 'FFFFFF', 'fill' => $navy, 'h' => 'left']);
        $sSub   = $x->style(['size' => 9, 'color' => '64748B']);
        $sHead  = $x->style(['bold' => true, 'size' => 10, 'color' => 'FFFFFF', 'fill' => '1E3A5F', 'border' => true, 'h' => 'left', 'wrap' => true]);
        $sCell  = $x->style(['size' => 10, 'border' => true, 'h' => 'left', 'wrap' => true]);

        $nc = count($headers);
        $x->merge(1, 1, 1, $nc, $title, $sTitle);
        $x->height(1, 24);
        $x->set(2, 1, 'Date range: ' . ($range !== '' ? $range : 'All dates') . '   |   Rows: ' . number_format(count($rows)) . '   |   Exported: ' . date('Y-m-d H:i') . ' by ' . (string) (session()->get('name') ?? session()->get('username') ?? ''), $sSub);
        $width = [];
        foreach ($headers as $c => $h) {
            $x->set(4, $c + 1, $h, $sHead);
            $width[$c] = mb_strlen($h) + 4;
        }
        $x->height(4, 28);
        $r = 5;
        foreach ($rows as $row) {
            $row = array_values((array) $row);
            for ($c = 0; $c < $nc; $c++) {
                $v = mb_substr(trim((string) ($row[$c] ?? '')), 0, 4000);
                $x->set($r, $c + 1, $v, $sCell);
                $longest = 0;
                foreach (explode("\n", $v) as $ln) {
                    $longest = max($longest, mb_strlen($ln));
                }
                $width[$c] = max($width[$c], min(60, $longest + 2));
            }
            $r++;
        }
        foreach ($width as $c => $w) {
            $x->width($c + 1, (float) min(60, max(10, $w)));
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $title) . '_' . ($range !== '' ? preg_replace('/[^0-9A-Za-z]+/', '_', $range) : 'All_dates') . '.xlsx';
        \App\Libraries\ActivityLog::record('dtr', 'export-table', $title, count($rows) . ' row(s), ' . ($range !== '' ? $range : 'All dates'));

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->setBody($x->build());
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
        if (($p['kind'] ?? '') === 'lacking') {          // a Lacking marker of the collection (no bonding report yet): the row is the tracker row, its report row only when one exists
            $t0 = $db->table('tbl_dtr_BondingTracker')->where('TrackerID', $id)->get()->getRowArray();
            if (! $t0) {
                return $fail('That bonding entry no longer exists.', 404);
            }
            $info = $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $t0['LabRefNo'])->orderBy('BondingTestID', 'DESC')->get(1)->getRowArray() ?: [
                'LabRefNo' => $t0['LabRefNo'], 'ModelStyle' => $t0['Style'] ?? '', 'Color' => $t0['Colorway'] ?? '', 'POLotNO' => $t0['ProductionCode'] ?? '', 'MTL' => $t0['MTL'] ?? '',
                'ProductionDate' => $t0['ProductionDate'] ?? '', 'ReceivedDate' => $t0['CollectionDate'] ?? '', 'Technician' => $t0['Technician'] ?? '', 'TestType' => $t0['TestType'] ?? '',
            ];
        } else {
            $info = $db->table('tbl_dtr_bonding_info')->where('BondingTestID', $id)->get()->getRowArray();
        }
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
        // the production date of the bonding row's tracker record is the one the Lab Tracker pairs the pull tests by (production yesterday, tested today: the Lab Ref carries the test day)
        $prod = substr($pick($trk['ProductionDate'] ?? '', $info['ProductionDate'] ?? ''), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $prod)) {
            $prod = substr($dateRaw, 0, 4) . '-' . substr($dateRaw, 4, 2) . '-' . substr($dateRaw, 6, 2);
        }
        $collect = substr($pick($trk['CollectionDate'] ?? '', $info['ReceivedDate'] ?? '', $prod), 0, 10);
        $code    = mb_substr($pick($info['POLotNO'] ?? '', $trk['ProductionCode'] ?? ''), 0, 191);
        $style   = mb_substr($pick($info['ModelStyle'] ?? '', $trk['Style'] ?? ''), 0, 191);
        $color   = $pick($info['Color'] ?? '', $trk['Colorway'] ?? '');
        $mtl     = mb_substr($pick($info['MTL'] ?? '', $trk['MTL'] ?? ''), 0, 191);
        $tech    = mb_substr($pick($info['Technician'] ?? '', $trk['Technician'] ?? ''), 0, 191);
        $ttype   = mb_substr($pick($trk['TestType'] ?? '', $info['TestType'] ?? ''), 0, 50);
        if (strtoupper($ttype) === 'LACKING') {
            $ttype = 'DAILY';           // the pull row belongs under the Lacking row but is not a Lacking row itself (else it is listed as a second row)
        }
        $user    = service('auth')->user();
        $by      = mb_substr((string) ($user['name'] ?? $user['username'] ?? ''), 0, 100);
        $factory = 'DATIAN SUBIC SHOES INC. (DTP)';
        $made    = [];
        $skipped = [];
        // a bonding row can carry at most 4 pull tests (Heel Pull, Eyelet, D-Ring, Rivet): the ones it already has count
        $maxPulls = 4;
        $existing = $db->table('tbl_dtr_BondingTracker')
            ->where(['Building' => $building, 'LineNo' => $building . $lineNo, 'Side' => $side, 'ProductionDate' => $prod, 'PullTest' => 1, 'Shutdown' => 0])
            ->where('ProductionCode', $code)->countAllResults();
        if ($existing >= $maxPulls) {
            return $fail($ref . ' already has ' . $existing . ' pull tests: 4 is the maximum.');
        }
        foreach ($types as $pt) {
            if ($existing + count($made) >= $maxPulls) {
                $skipped[] = $pt . ' (maximum of ' . $maxPulls . ' pull tests per row)';
                continue;
            }
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
            \App\Libraries\DataCache::bump('bonding');
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
        if (in_array($kind . ':' . $id, $this->dtr('EditSync')->lockedEdits($db), true)) {
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
        // Shoe side (L / R) of a bonding row can always be corrected: it is written to the bonding record and to the tracker record(s) of this Lab Ref (the Lab Ref text is not renamed)
        $sideMsg  = '';
        $newSide  = strtoupper(substr(trim((string) ($p['side'] ?? '')), 0, 1));
        if ($kind === 'bonding' && in_array($newSide, ['L', 'R'], true)) {
            $cur  = trim((string) ($row['Side'] ?? ''));
            $word = preg_match('/^(left|right)$/i', $cur) || $cur === '';
            $ref0 = trim((string) ($row['LabRefNo'] ?? ''));
            // the Lab Ref carries the side (DTP<date>SH<building><line>-<L|R>[-n]): it is renamed everywhere it is stored, together with the pull tests of the same shoe
            $map = [];   // old Lab Ref => new Lab Ref
            if (preg_match('/^(DTP(\d{8})(?:SH|FA)([AB]\d+))-([LR])((?:-\d+)?)$/i', $ref0, $pm) && strtoupper($pm[4]) !== $newSide) {
                $oldSide = strtoupper($pm[4]);
                $newRef  = $pm[1] . '-' . $newSide . $pm[5];
                if ($db->table('tbl_dtr_bonding_info')->where('LabRefNo', $newRef)->countAllResults() > 0
                    || ($db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $ref0)->countAllResults() > 0 && $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $newRef)->countAllResults() > 0)) {
                    return $fail($newRef . ' already exists as another record. Nothing was changed.');
                }
                $map[$ref0] = $newRef;
                $code = trim((string) ($row['POLotNO'] ?? ''));
                $pq   = $db->table('tbl_dtr_pull_tests')->select('LabRefNo, ProductionCode')
                    ->where("LabRefNo REGEXP '^DTP" . $pm[2] . '(HP|EY|DR|RVT|LLH)' . $pm[3] . '-' . $oldSide . "(-[0-9]+)?$'", null, false)->get()->getResultArray();
                foreach ($pq as $pr) {
                    if ($code !== '' && trim((string) $pr['ProductionCode']) !== '' && strcasecmp(trim((string) $pr['ProductionCode']), $code) !== 0) {
                        continue;   // a pull test of another production code is another shoe
                    }
                    $np = preg_replace('/-' . $oldSide . '((?:-\d+)?)$/', '-' . $newSide . '$1', (string) $pr['LabRefNo']);
                    if ($db->table('tbl_dtr_pull_tests')->where('LabRefNo', $np)->countAllResults() > 0) {
                        return $fail($np . ' (pull test) already exists as another record. Nothing was changed.');
                    }
                    $map[(string) $pr['LabRefNo']] = $np;
                }
            }
            $targets = [   // table => Lab Ref columns that hold a bonding / pull Lab Ref (history logs and the ERP / InterLink copies are left as they were)
                'tbl_dtr_bonding_info' => ['LabRefNo', 'RetestOf'], 'tbl_dtr_BondingTracker' => ['LabRefNo', 'RetestOf'], 'tbl_dtr_bonding_points' => ['LabRefNo'], 'tbl_dtr_bonding_spot_points' => ['LabRefNo'],
                'tbl_dtr_pull_results' => ['LabRefNo'], 'tbl_dtr_pull_tests' => ['LabRefNo'], 'tbl_dtr_test_pauses' => ['LabRefNo'], 'tbl_dtr_unified_labrefs' => ['lab_ref_no'],
                'tbl_photo_links' => ['lab_ref'], 'tbl_dtr_report_approvals' => ['lab_ref_no'], 'tbl_dtr_capa' => ['lab_ref_no', 'retest_lab_ref'],
            ];
            $touched = [];
            $db->transStart();
            foreach ($map as $o => $n) {
                foreach ($targets as $tbl => $colsT) {
                    if (! $db->tableExists($tbl)) {
                        continue;
                    }
                    $have = $db->getFieldNames($tbl);
                    foreach ($colsT as $c) {
                        if (in_array($c, $have, true)) {
                            $db->table($tbl)->where($c, $o)->update([$c => $n]);
                            if ($db->affectedRows() > 0) {
                                $touched[$tbl] = true;
                            }
                        }
                    }
                }
            }
            $db->table($table)->where($key, $id)->update(['Side' => $word ? ($newSide === 'L' ? 'Left' : 'Right') : $newSide]);
            $refNow = $map[$ref0] ?? $ref0;
            if ($refNow !== '') {
                $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $refNow)->update(['Side' => $newSide]);
            }
            foreach (array_slice($map, 1, null, true) as $np) {   // the pull tests of the shoe carry the new side too
                $db->table('tbl_dtr_BondingTracker')->where('LabRefNo', $np)->update(['Side' => $newSide]);
                if (in_array('Side', $db->getFieldNames('tbl_dtr_pull_tests'), true)) {
                    $db->table('tbl_dtr_pull_tests')->where('LabRefNo', $np)->update(['Side' => $newSide]);
                }
            }
            $db->transComplete();
            if (! $db->transStatus()) {
                return $fail('The side could not be saved. Nothing was changed.', 500);
            }
            $row['LabRefNo'] = $refNow;
            \App\Libraries\DataCache::bump('bonding');
            \App\Libraries\ActivityLog::record('dtr', 'edit-side', $ref0, 'Shoe side set to ' . $newSide . ' (was ' . ($cur !== '' ? $cur : 'blank') . ')' . ($map ? '; Lab Ref renamed: ' . json_encode($map) . ' in ' . implode(', ', array_keys($touched)) : ''));
            $sideMsg = 'Shoe side set to ' . ($newSide === 'L' ? 'Left' : 'Right') . '.' . ($map ? ' Lab Ref ' . $ref0 . ' is now ' . $refNow . (count($map) > 1 ? ' (and ' . (count($map) - 1) . ' pull test Lab Ref(s))' : '') . ', updated in ' . count($touched) . ' related table(s).' : '');
        }
        if (! $set) {
            if ($sideMsg !== '') {
                return $this->response->setJSON(['ok' => true, 'message' => $sideMsg, 'saved' => [], 'csrf' => csrf_hash()]);
            }

            return $fail('Nothing new to save. Only empty columns can be filled.');
        }
        $db->table($table)->where($key, $id)->update($set);

        if ($kind === 'bonding') {
            $this->dtr('EditSync')->balanceBonding($db, (string) ($row['LabRefNo'] ?? ''));   // tracker record created / completed right away
        }
        if ($kind === 'wholeshoe') {
            $this->dtr('EditSync')->balanceWholeShoe($db, $id);
        }
        // the edit is carried over to the other tables that hold this Lab Ref 3 minutes later (runDueEditSyncs)
        $this->dtr('EditSync')->ensureEditSyncTable($db);
        $db->table('tbl_dtr_edit_sync')->insert([
            'Kind' => $kind, 'EntryID' => $id, 'LabRefNo' => (string) ($row['LabRefNo'] ?? $row['LabReference'] ?? ''),
            'Fields' => json_encode($set), 'EditedBy' => (string) (service('auth')->user()['name'] ?? ''),
            'CreatedAt' => date('Y-m-d H:i:s'), 'DueAt' => date('Y-m-d H:i:s', time() + 180),
        ]);

        return $this->response->setJSON(['ok' => true, 'message' => trim('Entry updated. It syncs to the other tables of this Lab Ref in 3 minutes. ' . $sideMsg), 'saved' => $set, 'csrf' => csrf_hash()]);
    }

    /** POST /dtr/sync-edits: runs the edit syncs that are due (polled by the Lab Tracker page). */
    public function syncEdits()
    {
        try {
            $n = $this->dtr('EditSync')->runDueEditSyncs();
        } catch (\Throwable $e) {
            return $this->response->setJSON(['ok' => false, 'message' => $e->getMessage(), 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'synced' => $n, 'locked' => $this->dtr('EditSync')->lockedEdits(\Config\Database::connect()), 'csrf' => csrf_hash()]);
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
            if (\App\Libraries\ReportPdf::signedLocked($ref)) {
                $db->transComplete();

                return $fail('This report is approved and signed. Only an Admin can delete it.', 403);
            }
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
                $this->dtr('EditSync')->purgeLabRef($db, $ref, [['tbl_dtr_bonding_spot_points', 'LabRefNo'], ['tbl_dtr_bonding_points', 'LabRefNo'], ['tbl_dtr_edit_sync', 'LabRefNo'], ['tbl_dtr_report_approvals', 'lab_ref_no'],
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
            if (\App\Libraries\ReportPdf::signedLocked($ref)) {
                $db->transComplete();

                return $fail('This report is approved and signed. Only an Admin can delete it.', 403);
            }
            $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', $id)->delete();
            if (trim($ref) !== '') {
                $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->delete();
                $this->dtr('EditSync')->purgeLabRef($db, $ref, [['tbl_dtr_wholeshoe_reports', 'LabRefNo'], ['tbl_dtr_wst_results', 'LabRefNo'], ['tbl_dtr_edit_sync', 'LabRefNo'], ['tbl_dtr_report_approvals', 'lab_ref_no'],
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

    private const PULL_EXTRA = ['ETRFNo', 'SupplierCountry', 'MaterialDescription', 'POQuantity', 'SendBy', 'MaterialSource', 'TechSlot', 'FinishedDate', 'Comments', 'ReviewedBy', 'ApprovedBy', 'PullData'];

    /** Saves the Pull Footwear Test Report into tbl_dtr_pull_tests (the row of its Lab Ref, or a new one) and refreshes the Lab Ref index. */
    private function pullReportSave($db, array $f)
    {
        $this->dtr('PullReport')->ensurePullReportCols($db);
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
            return $this->response->setJSON(['ok' => true, 'slug' => 'pull', 'config' => self::FORM_CONFIG['pull'], 'record' => $this->dtr('PullReport')->pullReportRecord($db, $sourceId, $labRef), 'customValues' => [], 'bondingPoints' => [], 'csrf' => csrf_hash()]);
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
        // the eTRF No on the report: when the record has none, the one InterLink gave this Lab Reference is shown (it may sit on the other record of the Lab Ref, on the Lab Tracker row or on the eTRF job)
        if ($record && ! empty($record['LabRefNo'])) {
            $col = array_key_exists('ETRFNo', $record) ? 'ETRFNo' : (array_key_exists('TR', $record) ? 'TR' : '');
            if ($col !== '' && ! preg_match('/^A\d{6,}/i', trim((string) ($record[$col] ?? '')))) {
                $app = \App\Libraries\EtrfAppNos::forLabRef((string) $record['LabRefNo'], $slug);
                if ($app !== '' && trim((string) ($record[$col] ?? '')) === '') {
                    $record[$col] = $app;
                }
            }
        }

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
                ->select("material_code, material_name_e, material_name_c, supplier_e, supplier_c, unit, part_name_e, 'Raw Material' AS source_type, prod_usage", false)
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
                // Material Type = the part of the production order (production_order_details.part_name_e: Outsole, Vamp, Lace ...)
                'type'     => trim(preg_replace('/\s+/', ' ', (string) ($r['part_name_e'] ?? ''))) ?: (trim((string) ($l['MType'] ?? '')) ?: (($erpType[$k] ?? '') ?: (($types[$k] ?? '') ?: trim((string) $r['source_type'])))),
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

    /** GET /dtr/bonding-image?lab_ref=[&tab=bonding|shoes|containers] : the report picture of a Lab Ref (404 when there is none) */
    public function bondingImage()
    {
        if ($this->request->getGet('diag')) {   // /dtr/bonding-image?diag=1&tab=bonding : why are there no pictures?
            $cfg  = $this->dtr('ReportImages')->reportImageCfg((string) ($this->request->getGet('tab') ?? 'bonding'));
            $before = @is_dir($cfg['root']);
            @unlink($this->dtr('ReportImages')->reportImageCache($cfg));
            $idx = $this->dtr('ReportImages')->reportImageIndex($cfg);

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
                return $this->response->setStatusCode(204)->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=120');   // "no picture": 204, not 404, so the console stays clean and the browser keeps the answer for a while
            }
        }
        $file = $this->dtr('ReportImages')->reportImageFile($tabQ, $refQ);
        if ($file === null) {
            if ($thumb) {
                @mkdir(dirname($tf), 0777, true);
                @touch($tf . '.none');

                return $this->response->setStatusCode(204)->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=120');
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
        $cfg = $this->dtr('ReportImages')->reportImageCfg((string) ($p['tab'] ?? 'bonding'));
        if ($cfg['root'] === '' || ! $this->dtr('ReportImages')->reportShareReachable($cfg['root'])) {
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
        $found = $this->dtr('ReportImages')->reportImageFile($cfg['tab'], $labRef);
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

                return $this->response->setStatusCode(204)->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=120');
        if (@file_put_contents($current, $bin) === false) {
            if ($moved !== '') {
                @rename($dir . $moved, $current);   // put the old report back
            }

            return $fail('Could not save into the report picture folder.');
        }
        // batch-tracking copy, same naming as the machine batches: <date>-<time>_<LabRef>_<hash>.png
        @file_put_contents($dir . date('Ymd-Hi') . '_' . $labRef . '_' . substr(md5($bin), 0, 8) . '.png', $bin);
        @unlink($this->dtr('ReportImages')->reportImageCache($cfg));
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
        $this->dtr('TrackerSchema')->ensureTrackerTables($db);
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

            // Whole Shoe Lab Ref No (mother, kept internally) = DTP + production date + WS + building + line.
            // Test Type Lab Ref No (one per ticked test) = the same with WS replaced by the test code, e.g. DTP20261103AGA4. Duplicates are checked on the Test Type Lab Ref No.
            $bld    = strtoupper(mb_substr((string) ($p['building'] ?? 'A'), 0, 1)) === 'B' ? 'B' : 'A';
            $pd     = preg_replace('/\D/', '', (string) (($p['productionDate'] ?? '') ?: ($p['collectionDate'] ?? '') ?: date('Y-m-d')));
            $lineN  = (int) preg_replace('/\D/', '', (string) ($r['line'] ?? '')) ?: ($saved + 1);
            if (strtoupper((string) ($r['type'] ?? '')) === 'TRIAL') {   // a trial has no building / line: DTP<date><code>-<side>
                $bld   = '';
                $lineN = '';
            }
            $side   = ($r['side'] ?? 'L') === 'R' ? 'R' : 'L';
            $mother = 'DTP' . $pd . 'WS' . $bld . $lineN;   // the mother Lab Ref has no side (both sides of a line share it); the side goes into the Lab Ref No of each test (-L / -R)
            // another shoe (different Production Code) on the same line and date: the mother Lab Ref gets a count, DTP<date>WS<line>-1, -2 ... (the same shoe, i.e. its other side, shares the mother of the first one)
            $mBase = $mother;
            $mHave = [];
            foreach ($db->table('tbl_dtr_WholeshoeTracker')->select('WholeshoeLabRefNo, ProductionCode')->groupStart()->where('WholeshoeLabRefNo', $mBase)->orLike('WholeshoeLabRefNo', $mBase . '-', 'after')->groupEnd()->get()->getResultArray() as $x) {
                if (preg_match('/^' . preg_quote($mBase, '/') . '(?:-(\d+))?$/i', trim((string) $x['WholeshoeLabRefNo']))) {
                    $mHave[trim((string) $x['WholeshoeLabRefNo'])][strtoupper(trim((string) $x['ProductionCode']))] = true;
                }
            }
            if ($mHave) {
                $found = null;
                foreach ($mHave as $mRef => $codesOf) {
                    if (isset($codesOf[strtoupper($code)])) {
                        $found = $mRef;
                        break;
                    }
                }
                if ($found !== null) {
                    $mother = $found;
                } else {
                    if (isset($mHave[$mBase])) {   // the first shoe of the line becomes -1
                        foreach (['tbl_dtr_WholeshoeTracker' => 'WholeshoeLabRefNo', 'tbl_dtr_wholeshoe_intake' => 'LabRefNo'] as $tb => $cl) {
                            $db->table($tb)->where($cl, $mBase)->update([$cl => $mBase . '-1']);
                        }
                        $db->table('tbl_dtr_unified_labrefs')->where('form_slug', 'wholeshoe')->where('lab_ref_no', $mBase)->update(['lab_ref_no' => $mBase . '-1']);
                        $mHave[$mBase . '-1'] = $mHave[$mBase];
                        unset($mHave[$mBase]);
                    }
                    $maxN = 0;
                    foreach (array_keys($mHave) as $mRef) {
                        if (preg_match('/-(\d+)$/', $mRef, $mm)) {
                            $maxN = max($maxN, (int) $mm[1]);
                        }
                    }
                    $mother = $mBase . '-' . ($maxN + 1);
                }
            }

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
            $todo = $kids ?: [['type' => null, 'code' => null, 'ref' => $mother . '-' . $side]];   // no test ticked: the row's own Lab Ref keeps the side (it must be unique per side)
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
            \App\Libraries\DataCache::bump('wholeshoe');
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
        $ref      = $has ? $this->dtr('MaterialLabRef')->nextMaterialLabRef($db, $material, $d, (string) ($this->request->getGet('name') ?? ''), (int) ($this->request->getGet('samples') ?? 1)) : $this->dtr('MaterialLabRef')->nextMaterialLabRefBase($material, $d) . '-1';

        return $this->response->setJSON(['ok' => true, 'labref' => $ref, 'csrf' => csrf_hash()]);
    }

    /**
     * POST /dtr/material-receive-save
     * Material Receive batch: each line is added to the IQC Container Receiving Log (tbl_dtr_containers; an existing
     * Container No. is skipped) and the full receiving details are kept in tbl_dtr_material_receipts.
     */
    /** Test selection of Material Receive: test name => the code its Lab Ref ends with (<Lab Ref>-<code>) */
    public const MATERIAL_TESTS = [
        'Hardness' => 'HRD', 'Weight' => 'WGT', 'Thickness' => 'THK', 'Specific Gravity' => 'SPG', 'Yarn Count' => 'YRN', 'Tensile' => 'TEN', 'Bursting' => 'BST',
        'Peel and Shear Strength' => 'PSS', 'Pull Strength' => 'PUL', 'Die-C Tear' => 'DIC', 'Split Tear' => 'SPT', 'Trouser Tear' => 'TRT', 'Stitch Tear' => 'STT',
        'Baumann Tear' => 'BAU', 'Compression' => 'CMP', 'Knot Slip' => 'KNS', 'Lace Tip' => 'LCT', 'Edge Wise' => 'EDG', 'DIN' => 'DIN', 'Martindale' => 'MAR',
        'Taber' => 'TAB', 'Lace Abrasion' => 'LAB', 'Bally' => 'BAL', 'Ross Flex' => 'RFX', 'Colorfastness To Water' => 'CFW', 'Colorfastness To Crocking' => 'CFC',
        'UV Light' => 'UVL', 'Heat Aging' => 'HAG', 'PVC' => 'PVC', 'Corrosion' => 'COR', 'Shrinkage' => 'SHR', 'Perspiration' => 'PER', 'Blooming' => 'BLM',
        'Hydrolysis' => 'HYD', 'Non Marking Test' => 'NMK', 'Dip Test' => 'DIP', 'Glow in the dark' => 'GLO', 'Footwear Adhesion' => 'FWA',
        'Method of Peel Strength of Outsole To Upper and to Midsole' => 'PSO', 'Finish Adhesion' => 'FIA', 'Method for Laminar Peel Strength' => 'LPS',
        'Ink Adhesion' => 'INA', 'Cardboard Adhesion' => 'CBA', 'Whole Shoe Flex' => 'WSF', 'Washing' => 'WSH',
    ];

    public function materialReceiveSave()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $rows = is_array($p['rows'] ?? null) ? $p['rows'] : [];
        if (! $rows) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'No material lines to save.', 'csrf' => csrf_hash()]);
        }

        $db = \Config\Database::connect();
        // ONE table holds the whole entry: tbl_dtr_MaterialTracker, one row per child Lab Ref (LabRefNo) with its mother (MotherLabRefNo) and its test
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
            INDEX (LabRefNo), INDEX (ProductionCode), INDEX (ReceivedDate), INDEX (MaterialCode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        // a table made by an earlier version of this form gets the new columns
        foreach (['DateArrived' => 'DATE', 'MaterialCode' => 'VARCHAR(191)', 'MaterialName' => 'VARCHAR(500)', 'MaterialType' => 'VARCHAR(191)', 'Supplier' => 'VARCHAR(191)', 'Qty' => 'VARCHAR(100)', 'QCQty' => 'VARCHAR(100)', 'Unit' => 'VARCHAR(50)', 'MotherLabRefNo' => 'VARCHAR(191)', 'TestType' => 'VARCHAR(191)', 'TestCode' => 'VARCHAR(20)', 'NoOfSamples' => 'INT DEFAULT 3', 'Result' => 'VARCHAR(50)', 'Status' => 'VARCHAR(50)'] as $col => $type) {
            if (! $db->fieldExists($col, 'tbl_dtr_MaterialTracker')) {
                $db->query("ALTER TABLE tbl_dtr_MaterialTracker ADD COLUMN {$col} {$type} NULL");
            }
        }
        // Result and Status are the last two columns of the table
        $names = $db->getFieldNames('tbl_dtr_MaterialTracker');
        if (array_slice($names, -2) !== ['Result', 'Status']) {
            $others = array_values(array_diff($names, ['Result', 'Status']));
            $db->query('ALTER TABLE tbl_dtr_MaterialTracker MODIFY Result VARCHAR(50) NULL AFTER `' . end($others) . '`, MODIFY Status VARCHAR(50) NULL AFTER Result');
        }
        // the same child Lab Ref (sample no.) is used by every test of the line, so a row is unique by Lab Ref + test, not by Lab Ref alone
        if ($db->query("SHOW INDEX FROM tbl_dtr_MaterialTracker WHERE Key_name = 'uq_labref'")->getNumRows() > 0) {
            $db->query('ALTER TABLE tbl_dtr_MaterialTracker DROP INDEX uq_labref, ADD INDEX idx_labref (LabRefNo)');
        }
        if ($db->query("SHOW INDEX FROM tbl_dtr_MaterialTracker WHERE Key_name = 'uq_labref_test'")->getNumRows() === 0) {
            $db->query("UPDATE tbl_dtr_MaterialTracker SET TestCode = '' WHERE TestCode IS NULL");
            $db->query('ALTER TABLE tbl_dtr_MaterialTracker ADD UNIQUE KEY uq_labref_test (LabRefNo, TestCode)');
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
            // MOTHER Lab Ref of the line = DTP + received date + MT + container no. (DTP20261118MT<container>); a repeat of the same one gets -2, -3 ...
            $cont     = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($r['container'] ?? ''));
            $mBase    = 'DTP' . str_replace('-', '', $received) . 'MT' . strtoupper($cont);
            $labRef   = $mBase;
            for ($k = 2; $db->table('tbl_dtr_MaterialTracker')->groupStart()->where('MotherLabRefNo', $labRef)->orWhere('LabRefNo', $labRef)->groupEnd()->countAllResults() > 0; $k++) {
                $labRef = $mBase . '-' . $k;
            }
            // CHILD Lab Ref base = DTP + received date + the material suffix (LC, FBU, IB ...), numbered one by one: DTP20261118LC-1, -2 ...
            $cCode    = $this->dtr('MaterialLabRef')->materialLabRefCode($specType !== '' ? $specType : $type, $name);
            $cBase    = $cCode !== '' ? 'DTP' . str_replace('-', '', $received) . $cCode : $this->dtr('MaterialLabRef')->nextMaterialLabRefBase($specType !== '' ? $specType : ($type !== '' ? $type : $code), $received);
            // the entry details shared by every row of this line; each child Lab Ref below is saved as its own row of tbl_dtr_MaterialTracker
            $entry = [
                'MotherLabRefNo' => $labRef, 'ReceivedDate' => $received, 'DateArrived' => $arrived, 'ProductionCode' => $str($r['prod'] ?? ''), 'Colorway' => $str($r['color'] ?? ''),
                'Style' => $str($r['style'] ?? ''), 'MTL' => $str($r['mtl'] ?? ''), 'MaterialCode' => $code, 'MaterialName' => $name, 'MaterialType' => $type,
                'Supplier' => $str($r['supplier'] ?? ''), 'ContainerNo' => $str($r['container'] ?? ''), 'BatchNo' => $str($r['batch'] ?? ''), 'Qty' => $qty,
                'QCQty' => $str($r['qcqty'] ?? '', 100), 'Unit' => $str($r['unit'] ?? '', 50), 'NoOfSamples' => 3, 'EncodedBy' => $enc, 'CreatedAt' => $now,
            ];   // every Lab Ref has 3 samples
            $db->table('tbl_dtr_containers')->insert([
                'ContainerNo'       => $labRef,
                'DateArrived'       => $arrived,
                'DateReceivedInLab' => $received,
                'Remarks'           => $str(trim($code . ' ' . $name)),
                'Encodedby'         => $enc,
                'EncodedDate'       => $now,
            ]);

            // the tests picked on the line; none picked = the default tests of the material type, one sample each
            $picked = [];
            foreach ((array) ($r['tests'] ?? []) as $tn) {
                $tn = trim((string) $tn);
                if ($tn !== '') {
                    $picked[$tn] = self::MATERIAL_TESTS[$tn] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $tn), 0, 3));
                }
            }
            $perTest = $samples;
            if (! $picked) {
                $perTest = 1;
                \App\Libraries\MaterialCatalog::ensureTables();
                $res = \App\Libraries\MaterialCatalog::resolve($specType) ?? \App\Libraries\MaterialCatalog::resolve($type);
                foreach ($res['tests'] ?? [] as $tc) {
                    $picked[(string) (\App\Libraries\MaterialCatalog::test($tc)['name'] ?? $tc)] = (string) $tc;
                }
                if (! $picked) {
                    $picked[$type !== '' ? $type : $code] = 'GEN';
                }
            }
            // child Lab Refs: one row per sample per test; every test counts from the next free sample no. of the suffix and date (Hardness LC-1..8, Weight LC-1..8, next line LC-9...)
            $first = $this->dtr('MaterialLabRef')->lastMaterialChildNo($db, $cBase) + 1;
            foreach ($picked as $tName => $tCode) {
                for ($i = 0; $i < $perTest; $i++) {
                    try {
                        if ($db->table('tbl_dtr_MaterialTracker')->insert($entry + ['LabRefNo' => $cBase . '-' . ($first + $i), 'TestType' => $tName, 'TestCode' => $tCode, 'Status' => 'Pending'])) {
                            $tests++;
                        }
                    } catch (\Throwable $e) {
                        $skipped[] = $cBase . '-' . ($first + $i) . ' ' . $tCode;   // already there (Lab Ref + test is unique)
                    }
                }
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

        // one scan for the notes that mention a retest (a correlated NOT EXISTS ... LIKE '%..%' per failed row read millions of rows), then the filter in PHP
        $retested = [];
        foreach ($db->query("SELECT LabNotesComments FROM tbl_dtr_bonding_info WHERE LabNotesComments LIKE '%Retest of %'")->getResultArray() as $n) {
            if (preg_match_all('/Retest of\s+([A-Za-z0-9._-]+)/i', (string) $n['LabNotesComments'], $mm)) {
                foreach ($mm[1] as $ref) {
                    $retested[strtoupper($ref)] = true;
                }
            }
        }

        $builder = $db->table('tbl_dtr_bonding_info')
            ->select("LabRefNo, ModelStyle, Color, POLotNO, MTL, Result, ModeOfFailure, TestedDate, COALESCE(NULLIF(PossibleCause, ''), NULLIF(Remarks, ''), NULLIF(LabNotesComments, '')) AS Analysis", false)
            ->like('Result', 'FAIL', 'after');

        if ($q !== '') {
            $builder->groupStart()->like('LabRefNo', $q)->orLike('ModelStyle', $q)->orLike('Color', $q)->groupEnd();
        }

        $rows = [];
        foreach ($builder->orderBy('BondingTestID', 'DESC')->limit(600)->get()->getResultArray() as $r) {
            if (! isset($retested[strtoupper((string) $r['LabRefNo'])])) {
                $rows[] = $r;
                if (count($rows) >= 60) {
                    break;
                }
            }
        }

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
        $this->dtr('TrackerSchema')->ensureTrackerTables($db);   // DDL first: it would implicitly commit a running transaction

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
            // a line ticked Lacking stays a LACKING row, also when its Prod. Code is typed: that only fills in the details of the lacking row
            // (it is never turned into a daily record or added a second time; the real specimen takes it over when it is collected)
            $lackColl = $due < $collect ? $due : $collect;   // a shoe lacking from an earlier production day is tested on the day after THAT day (today for yesterday's production), not a day later
            if ($detail['Style'] === '' && $detail['ProductionCode'] !== '') {          // the style of the production code (shoe master) when the form left it empty
                $st = $db->table('tbl_dtr_shoe_list')->select('StyleNo')->where('ProdCode', $detail['ProductionCode'])->where('StyleNo <>', '')->get(1)->getRowArray();
                $detail['Style'] = (string) ($st['StyleNo'] ?? '');
            }
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
                $upd = array_filter($detail, static fn ($v, $k) => in_array($k, ['Lacking', 'LackingDate'], true) || ($k !== 'TestType' && $v !== '' && $v !== null && $v !== 0), ARRAY_FILTER_USE_BOTH);
                $upd['Lacking']     = 1;   // an already saved record ticked Lacking stays marked lacking
                $upd['LackingDate'] = $due;
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
                $db->table('tbl_dtr_BondingTracker')->where('TrackerID', (int) $mk['TrackerID'])->update($keep + ['LabRefNo' => $lkRef, 'CollectionDate' => $lackColl, 'EncodedBy' => $encodedBy]);
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
                'CollectionDate' => $lackColl, 'ProductionDate' => $due, 'EncodedBy' => $encodedBy, 'CreatedAt' => date('Y-m-d H:i:s'),
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
            // Lab Ref families: DAILY SH<line> | TRIAL TRIALSH | SAMPLE SAMPLESH<line> | INTERNAL TESTING ITSH | FIRST ARTICLE FA<line> | RETEST SH<line>-R01
            $typeU   = strtoupper(trim((string) ($r['type'] ?? '')));
            $noLine  = in_array($typeU, ['TECH TRIAL', 'INTERNAL TESTING', 'SAMPLE'], true);   // these belong to no line
            $mainPre = ['TECH TRIAL' => 'TRIALSH', 'SAMPLE' => 'SAMPLESH', 'INTERNAL TESTING' => 'ITSH', 'FIRST ARTICLE' => 'FA'][$typeU] ?? 'SH';
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
                    // the side of a retest can be changed: the new Lab Ref ends with the chosen side
                    if (preg_match('/-[LR]$/', $labRef)) {
                        $labRef = preg_replace('/-[LR]$/', '-' . $side, $labRef);
                    }
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
            // (the marker of the same line / side, booked under this production date OR under the same production code, is converted: the specimen is saved as a normal
            //  DAILY record under its own Lab Ref, so the leftover lacking record and its Pending pull records of the marker's Lab Ref go too)
            $mkCode = mb_substr(trim((string) ($r['code'] ?? '')), 0, 191);
            $mkQ    = $db->table('tbl_dtr_BondingTracker')->where(['Lacking' => 1, 'TestType' => 'LACKING', 'Building' => $building, 'LineNo' => $building . (int) $m[1], 'Side' => $side])
                ->groupStart()->where('ProductionDate', $prod);
            if ($mkCode !== '') {
                $mkQ->orWhere('ProductionCode', $mkCode);
            }
            foreach ($mkQ->groupEnd()->get()->getResultArray() as $mk) {
                $mkRef = trim((string) $mk['LabRefNo']);
                if ($mkRef !== '' && strcasecmp($mkRef, $labRef) !== 0 && ! $isPullRow) {
                    foreach ($db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $mkRef)->where('TestType', 'LACKING')->groupStart()->where('Result IS NULL', null, false)->orWhere('Result', '')->groupEnd()->get()->getResultArray() as $lb) {
                        $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_bonding_info')->where('source_id', (int) $lb['BondingTestID'])->delete();
                        $db->table('tbl_dtr_bonding_info')->where('BondingTestID', (int) $lb['BondingTestID'])->delete();
                    }
                    $mkDay = substr($mkRef, 3, 8);
                    if (ctype_digit($mkDay) && $mkDay !== str_replace('-', '', $prod)) {   // another day than this batch: its Pending pull records would stay behind
                        foreach (['HP', 'EY', 'DR', 'RVT'] as $pc) {
                            $pr0 = 'DTP' . $mkDay . $pc . $building . (int) $m[1] . '-' . $side;
                            foreach ($db->table('tbl_dtr_pull_tests')->select('PullInfoID')->where('LabRefNo', $pr0)->groupStart()->where('Result IS NULL', null, false)->orWhere('Result', '')->groupEnd()->get()->getResultArray() as $pl) {
                                $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $pr0)->delete();
                                $db->table('tbl_dtr_pull_tests')->where('PullInfoID', (int) $pl['PullInfoID'])->delete();
                            }
                        }
                    }
                }
                $db->table('tbl_dtr_BondingTracker')->where('TrackerID', (int) $mk['TrackerID'])->delete();
            }
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
            $file = $this->dtr('BondingFile')->findAndCacheOutdataFile('BONDING', $labRefNo, $baseRef);
            if ($file) {
                $grid = $this->dtr('BondingFile')->parseSpreadsheetToGrid($file);
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
                $file = $this->dtr('BondingFile')->findAndCacheOutdataFile($spec['folder'], $labRefNo, $baseRef);
                if (! $file) {
                    continue;
                }
                $grid = $this->dtr('BondingFile')->parseSpreadsheetToGrid($file);
                $extracted = $this->dtr('BondingFile')->extractByLoadResultsRule($grid, $labRefNo, $spec['labRow'], $spec['resRow'], 3, count($spec['fields']), $spec['fields']);
                if (! empty($extracted)) {
                    $loadedFields = array_merge($loadedFields, $extracted);
                    $loadedFiles[] = basename($file) . ' (' . basename($spec['folder']) . ')';
                }
            }
        }

        if ($slug === 'bonding' && $loadedFields) {
            $loadedFields = $this->dtr('BondingFile')->deriveBondingFields($loadedFields, $labRefNo);
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

        $pdf = $this->dtr('Pdf')->htmlToPdfBytes($html, 'body{font-family:Arial,sans-serif;font-size:11px;color:#000;padding:10px 14px}h1{text-align:center;font-size:22px;font-weight:400;margin:18px 0 26px}.eq{margin:0 0 12px}'
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

    /** GET /dtr/wholeshoe-labrefs?q= : whole shoe Lab Refs (newest first) for the Whole Shoe report picker. */
    public function wholeShoeLabRefs()
    {
        $db = \Config\Database::connect();
        // every section of the form, so the blank sheet can be shown before a Lab Ref is chosen
        $blank = [];
        foreach ($this->dtr('WholeShoeReport')->wsSections() as $id => $s) {
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
        $out = $ref !== null ? $this->dtr('WholeShoeReport')->wsLoad($ref) : null;
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
        if ($ref === null || ! $this->dtr('WholeShoeReport')->wsLoad($ref)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Choose a whole shoe Lab Ref No first.', 'csrf' => csrf_hash()]);
        }
        $d    = is_array($p['data'] ?? null) ? $p['data'] : [];
        $clean = [];
        foreach (['etrfNo', 'supplier', 'country', 'matDesc', 'sendBy', 'style', 'color', 'po', 'qty', 'receivedDate', 'testedDate', 'finishedDate', 'technician', 'reviewedBy', 'approvedBy', 'comments'] as $k) {
            $clean[$k] = mb_substr(trim((string) ($d[$k] ?? '')), 0, 500);
        }
        $clean['results']     = [];
        $clean['sectionMeta'] = [];
        foreach ($this->dtr('WholeShoeReport')->wsSections() as $id => $s) {
            foreach (array_keys($s[3]) as $k) {
                $v = trim((string) ($d['results'][$id][$k] ?? ''));
                if ($v !== '') {
                    $clean['results'][$id][$k] = mb_substr($v, 0, 500);
                }
            }
            $sm = is_array($d['sectionMeta'][$id] ?? null) ? $d['sectionMeta'][$id] : [];
            $resVal = strtoupper(trim((string) ($sm['result'] ?? '')));
            if (! in_array($resVal, ['PASS', 'TOLERABLE', 'FAILED'], true)) {
                $resVal = '';
            }
            $cDate = trim((string) ($sm['completedDate'] ?? ''));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $cDate)) {
                $cDate = '';
            }
            if ($resVal !== '' || $cDate !== '') {
                $clean['sectionMeta'][$id] = ['result' => $resVal, 'completedDate' => $cDate];
            }
        }
        $side = strtoupper(trim((string) ($d['side'] ?? '')));
        if (! in_array($side, ['L', 'R'], true)) {
            $side = '';
        }
        $baseRef    = preg_replace('/-[LR]$/i', '', $ref);
        $updatedRef = $side !== '' ? ($baseRef . '-' . $side) : '';
        $clean['side']          = $side;
        $clean['updatedLabRef'] = $updatedRef;

        $db   = $this->dtr('WholeShoeReport')->wsReportTable();
        $user = (string) (service('auth')->user()['name'] ?? '');
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $db->query(
            'INSERT INTO tbl_dtr_wholeshoe_reports (LabRefNo, UpdatedWholeshoeLabRef, ReportData, UpdatedBy, UpdatedAt) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE UpdatedWholeshoeLabRef = VALUES(UpdatedWholeshoeLabRef), ReportData = VALUES(ReportData), UpdatedBy = VALUES(UpdatedBy), UpdatedAt = VALUES(UpdatedAt)',
            [$ref, $updatedRef !== '' ? $updatedRef : null, $json, $user, date('Y-m-d H:i:s')]
        );

        // Also sync saved header, UpdatedWholeshoeLabRef, & per-section Result / Complete Date back to matching tbl_dtr_WholeshoeTracker rows
        try {
            $trackerRows = $db->table('tbl_dtr_WholeshoeTracker')
                ->select('TrackerID, TestType, Findings')
                ->groupStart()->where('LabRefNo', $ref)->orWhere('WholeshoeLabRefNo', $ref)->groupEnd()
                ->get()->getResultArray();
            if ($trackerRows) {
                $typeMap = ['UV' => 'E03', 'UV Light' => 'E03', 'Washing' => 'G03', 'Dip Test' => 'E26', 'Heat Aging' => 'E04', 'Hydrolysis' => 'TM361'];
                foreach ($trackerRows as $tr) {
                    $upd = [
                        'UpdatedWholeshoeLabRef' => $updatedRef !== '' ? $updatedRef : null,
                    ];
                    if ($side !== '') {
                        $upd['Side'] = $side;
                    }
                    if ($clean['style'] !== '') {
                        $upd['Style'] = mb_substr($clean['style'], 0, 191);
                    }
                    if ($clean['color'] !== '') {
                        $upd['Colorway'] = mb_substr($clean['color'], 0, 191);
                    }
                    if ($clean['po'] !== '') {
                        $upd['ProductionCode'] = mb_substr($clean['po'], 0, 191);
                    }
                    $tt = (string) ($tr['TestType'] ?? '');
                    if ($tt === 'Flexing') {
                        $f = (string) ($tr['Findings'] ?? '');
                        $sid = preg_match('/\b50\s*[°º]/u', $f) ? 'G05' : (preg_match('/\b30\s*[°º]/u', $f) ? 'G04' : 'G02');
                    } else {
                        $sid = $typeMap[$tt] ?? '';
                    }
                    if ($sid !== '' && isset($clean['sectionMeta'][$sid])) {
                        $sm = $clean['sectionMeta'][$sid];
                        if (($sm['result'] ?? '') !== '') {
                            $upd['FinalResult'] = $sm['result'];
                        }
                        if (($sm['completedDate'] ?? '') !== '') {
                            $upd['TimeCompleted'] = $sm['completedDate'];
                        }
                    }
                    if ($sid !== '' && ! empty($clean['results'][$sid])) {
                        $parts = array_values(array_filter(array_map('trim', $clean['results'][$sid]), static fn ($x) => $x !== ''));
                        if ($parts) {
                            $upd['Findings'] = mb_substr(implode('; ', $parts), 0, 1000);
                        }
                    }
                    if ($upd) {
                        $db->table('tbl_dtr_WholeshoeTracker')->where('TrackerID', (int) $tr['TrackerID'])->update($upd);
                    }
                }
            }
        } catch (\Throwable $e) {
            // tbl_dtr_wholeshoe_reports already saved; tracker sync is best-effort
        }
        // Also sync ETRF/TR No. & edited header fields into tbl_dtr_unified_labrefs for the mother Whole Shoe Lab Ref and ALL its child test Lab Refs (Hydrolysis, Heat Aging, UV, Flexing, Washing, Dip Test)
        try {
            $baseRef = preg_replace('/-[LR]$/i', '', $ref);
            $allRefs = array_filter([$ref, $baseRef, $baseRef . '-L', $baseRef . '-R']);
            $childRows = $db->table('tbl_dtr_WholeshoeTracker')
                ->select('LabRefNo, WholeshoeLabRefNo')
                ->groupStart()
                    ->where('LabRefNo', $ref)
                    ->orWhere('WholeshoeLabRefNo', $ref)
                    ->orWhere('WholeshoeLabRefNo', $baseRef)
                    ->orLike('WholeshoeLabRefNo', $baseRef . '-', 'after')
                ->groupEnd()
                ->get()->getResultArray();
            foreach ($childRows as $cr) {
                if (! empty($cr['LabRefNo'])) {
                    $allRefs[] = trim((string) $cr['LabRefNo']);
                }
                if (! empty($cr['WholeshoeLabRefNo'])) {
                    $allRefs[] = trim((string) $cr['WholeshoeLabRefNo']);
                }
            }
            $allRefs = array_values(array_unique(array_filter($allRefs)));
            $uUpd = [];
            if ($clean['etrfNo'] !== '') {
                $uUpd['tr_no'] = mb_substr($clean['etrfNo'], 0, 120);
            }
            if ($clean['style'] !== '') {
                $uUpd['model_style'] = mb_substr($clean['style'], 0, 191);
            }
            if ($clean['color'] !== '') {
                $uUpd['color'] = mb_substr($clean['color'], 0, 191);
            }
            if ($clean['po'] !== '') {
                $uUpd['po_lot_no'] = mb_substr($clean['po'], 0, 191);
            }
            if ($clean['receivedDate'] !== '') {
                $uUpd['received_date'] = $clean['receivedDate'];
            }
            if ($clean['testedDate'] !== '') {
                $uUpd['tested_date'] = $clean['testedDate'];
            }
            if ($clean['finishedDate'] !== '') {
                $uUpd['finished_date'] = $clean['finishedDate'];
            }
            if ($clean['technician'] !== '') {
                $uUpd['technician'] = mb_substr($clean['technician'], 0, 120);
            }
            if ($uUpd && $allRefs) {
                $db->table('tbl_dtr_unified_labrefs')->whereIn('lab_ref_no', $allRefs)->update($uUpd);
            }
            // the TR typed in the report also goes to the TR column of every test of the whole shoe (Flexing, Aging, Hydrolysis, Washing, UV) on the whole shoe line,
            // the same way InterLink does it, so ERP Finish Monitoring (Whole Shoe) shows it without typing it again
            $trNo = strtoupper(trim((string) $clean['etrfNo']));
            if (preg_match('/^A\d{6,}(-REV\d+)?$/', $trNo)) {
                foreach ($childRows as $cr) {
                    if (trim((string) ($cr['LabRefNo'] ?? '')) !== '') {
                        \App\Libraries\EtrfAppNos::recordWholeShoeTest(trim((string) $cr['LabRefNo']), $trNo);
                    }
                }
            }
        } catch (\Throwable $e) {
            // best-effort sync to unified_labrefs
        }
        // the Side (L / R) changes the Lab Ref of the whole shoe itself: every table that holds the old Lab Ref gets the new one (DTP20261113WSA10-1 -> DTP20261113WSA10-1-L)
        $renamed = false;
        if ($updatedRef !== '' && $updatedRef !== $ref) {
            $renamed = $this->wsRenameLabRef($db, $ref, $updatedRef, $side);
        }
        \App\Libraries\DataCache::bump('wholeshoe');

        $savedLabel = $updatedRef !== '' ? $updatedRef : $ref;

        return $this->response->setJSON([
            'ok' => true, 'message' => "Saved Whole Shoe report {$savedLabel}.",
            'updatedLabRef' => $updatedRef, 'side' => $side, 'labRef' => $renamed ? $updatedRef : $ref, 'renamed' => $renamed, 'csrf' => csrf_hash(),
        ]);
    }

    /**
     * Renames the Lab Ref of a whole shoe in every table that holds it (intake lines, Lab Ref list, tracker rows of its tests, saved report).
     * Skipped when the new Lab Ref already exists on another record (it would merge two shoes).
     */
    private function wsRenameLabRef(\CodeIgniter\Database\BaseConnection $db, string $old, string $new, string $side): bool
    {
        try {
            if ($db->table('tbl_dtr_wholeshoe_intake')->where('LabRefNo', $new)->countAllResults() > 0
                || $db->table('tbl_dtr_wholeshoe_reports')->where('LabRefNo', $new)->countAllResults() > 0) {
                return false;
            }
            $db->transStart();
            $ids = array_column($db->table('tbl_dtr_wholeshoe_intake')->select('IntakeID')->where('LabRefNo', $old)->get()->getResultArray(), 'IntakeID');
            if ($ids) {
                $db->table('tbl_dtr_wholeshoe_intake')->whereIn('IntakeID', $ids)->update(['LabRefNo' => $new, 'Side' => $side]);
                $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $old)->where('source_table', 'tbl_dtr_wholeshoe_intake')->whereIn('source_id', $ids)->update(['lab_ref_no' => $new]);
            }
            $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $old)->where('form_slug', 'wholeshoe')->update(['lab_ref_no' => $new]);
            $db->table('tbl_dtr_WholeshoeTracker')->where('WholeshoeLabRefNo', $old)->update(['WholeshoeLabRefNo' => $new, 'UpdatedWholeshoeLabRef' => $new, 'Side' => $side]);
            $db->table('tbl_dtr_wholeshoe_reports')->where('LabRefNo', $old)->update(['LabRefNo' => $new]);
            $db->transComplete();

            return $db->transStatus() !== false;
        } catch (\Throwable $e) {
            log_message('error', 'wsRenameLabRef ' . $old . ' -> ' . $new . ': ' . $e->getMessage());

            return false;
        }
    }

    /**
        // a whole shoe can have several intake lines (L / R collections): every test type recorded under this parent counts
        try {
            foreach ($db->table('tbl_dtr_WholeshoeTracker')->select('TestType')->where('WholeshoeLabRefNo', $ref)->get()->getResultArray() as $k) {
                $tt = trim((string) $k['TestType']);
                if ($tt !== '' && ! in_array($tt, $tests, true) && ! in_array(strtolower($tt) === 'uv light' ? 'UV' : ($tt === 'UV' ? 'UV Light' : $tt), $tests, true)) {
                    $tests[] = $tt;
                }
            }
        } catch (\Throwable $e) {
            // the intake line alone
        }
     * GET /dtr/wholeshoe-report-pdf?lab_ref=&inline=1
     * "Whole Footwear Test Results" (Form # Lab VF 002) filled from the whole shoe intake and the saved results; only the test
     * sections ticked at intake are printed (all when none were).
     */
    public function wholeShoeReportPdf()
    {
        $ref = \App\Libraries\MachineFileReader::safeRef((string) $this->request->getGet('lab_ref'));
        $rep = $ref !== null ? $this->dtr('WholeShoeReport')->wsLoad($ref) : null;
        if (! $rep) {
            return $this->response->setStatusCode(404)->setBody('No whole shoe intake found for ' . (string) $ref . '.');
        }
        $info = $rep['info'];
        $d    = $rep['data'];
        $e    = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $all  = $this->dtr('WholeShoeReport')->wsSections();

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
                $res .= '<div class="rl"><b>' . $e($label) . ' :</b> ' . $e($val !== '' ? $val : '-') . '</div>';
            }
            $savedRes = trim((string) ($d['sectionMeta'][$s['id']]['result'] ?? ''));
            $rtVal    = $savedRes !== '' ? $savedRes : (isset($pb[$s['id']]) ? trim((string) $pb[$s['id']]['result']) : '');
            $rt       = $rtVal !== '' ? ' ' . $e($rtVal) : '';
            $body .= '<div class="st">' . $all[$s['id']][1] . '</div><table class="sc"><tr><td class="tc"><b>Test conditions测试条件:</b></td><td class="tr"><b>Result结果 :' . $rt . '</b></td></tr>'
                . '<tr><td class="cd">' . $all[$s['id']][2] . '</td><td class="rs">' . $res . '</td></tr></table>';
        }

        $displayRef = ($info['updatedLabRef'] ?? '') !== '' ? $info['updatedLabRef'] : $ref;
        $logo = base_url('assets/images/vf-logo.webp');
        $sat  = base_url('assets/images/satra-accredited.png');
        $html = '<div class="hd"><img src="' . $logo . '" class="vf"><div class="tt"><h1>Whole Footwear Test Results</h1><h2>整鞋测试报告</h2></div><img src="' . $sat . '" class="sa"></div>'
            . '<table class="ref"><tr><td class="frm">Form # : Lab VF 002</td><td class="rf"><div>ETRF. No : <b>' . $e(($d['etrfNo'] ?? '') !== '' ? $d['etrfNo'] : ($info['etrfNo'] ?? '')) . '</b></div><div>Lab Ref. No : <b>' . $e($displayRef) . '</b></div></td></tr></table>'
            . '<table class="info"><tr><td>Supplier/Factory(厂商) :</td><td><b>' . $e(($d['supplier'] ?? '') !== '' ? $d['supplier'] : 'DATIAN SUBIC SHOES INC.') . '</b></td><td>O Prod.Material (量产材料)</td><td>O Prod.Re-test量产重测</td></tr>'
            . '<tr><td>Supplier\'s country(国家):</td><td><b>' . $e(($d['country'] ?? '') !== '' ? $d['country'] : 'Philippines') . '</b></td><td>O New/Development 开发段新材料</td><td>O Trial Test 试测测试</td></tr>'
            . '<tr><td>Material Description(材料):</td><td>' . $e($d['matDesc'] ?? '') . '</td><td colspan="2">o Tech 1 &nbsp;&nbsp; Tech 2 &nbsp;&nbsp; Tech 3 &nbsp;&nbsp; Tech 4</td></tr>'
            . '<tr><td>Model No./Style(型体):</td><td>' . $e(($d['style'] ?? '') !== '' ? $d['style'] : $info['style']) . '</td><td colspan="2">O Sales Sample (销售样品)</td></tr>'
            . '<tr><td>Color (颜色):</td><td>' . $e(($d['color'] ?? '') !== '' ? $d['color'] : $info['color']) . '</td><td>Received Date(收到日) : ' . $e(($d['receivedDate'] ?? '') !== '' ? $d['receivedDate'] : $info['received']) . '</td><td></td></tr>'
            . '<tr><td>P.O/Lot No(编号):</td><td>' . $e(($d['po'] ?? '') !== '' ? $d['po'] : $info['prodCode']) . '</td><td>Tested Date(测试时间) : ' . $e($d['testedDate'] ?? '') . '</td><td></td></tr>'
            . '<tr><td>Quantity(数量):</td><td>' . $e(($d['qty'] ?? '') !== '' ? $d['qty'] : (string) (int) $info['qty']) . '</td><td>Finished Date(完成时间) : ' . $e($d['finishedDate'] ?? '') . '</td><td></td></tr>'
            . '<tr><td>Send by (提供方):</td><td>' . $e($d['sendBy'] ?? '') . '</td><td>Technician(技术人员) : ' . $e($d['technician'] ?? '') . '</td><td></td></tr></table>'
            . $body
            . '<table class="ft"><tr><td>Comments备注 : ' . $e($d['comments'] ?? '') . '</td><td>Reviewed by 检查: <b>' . $e($d['reviewedBy'] ?? '') . '</b><br><br>Approved by核准: <b>' . $e($d['approvedBy'] ?? '') . '</b></td></tr></table>';

        $css = 'body{font-family:Arial,sans-serif;font-size:10px;color:#000;margin:0}.hd{display:flex;align-items:center;justify-content:space-between}.hd .vf{height:62px}.hd .sa{height:58px}.tt{text-align:center}.tt h1{font-size:22px;margin:0}.tt h2{font-size:19px;margin:2px 0 0}'
            . 'table{border-collapse:collapse;width:100%}.ref{margin-top:6px}.ref td{border:1px solid #000;padding:3px 5px}.frm{width:60%}.info td{border:1px solid #000;padding:5px 5px;font-size:10.5px}'
            . '.st{background:#ddd9c4;border:1px solid #000;border-top:0;text-align:center;font-weight:700;font-size:12px;padding:6px 3px}.sc td{border:1px solid #000;vertical-align:top;padding:4px 5px}.tc{width:26%;text-align:center}.cd{text-align:center;font-size:9.5px;height:60px}.rs{padding:4px 5px}.rl{padding:4px 0}'
            . '.ft{margin-top:8px}.ft td{border:1px solid #000;padding:10px 6px;height:44px;vertical-align:top}';

        $pdf = $this->dtr('Pdf')->htmlToPdfBytes($html, $css);
        if ($pdf === null) {
            return $this->response->setStatusCode(500)->setBody('The PDF could not be created on the server (Chrome or Edge is required).');
        }
        $disp = $this->request->getGet('inline') ? 'inline' : 'attachment';

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disp . '; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $ref) . '_Whole-Footwear-Test-Results.pdf"')
            ->setBody($pdf);
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
            . ' --user-data-dir="' . $dir . 'p_' . $id . '" --print-to-pdf="' . $pdfFile . '" "file:///' . str_replace('\\', '/', $htmlFile) . '" 2>&1';
        $cliOut = (string) @shell_exec($cmd);
        @shell_exec('rmdir /s /q "' . str_replace('/', '\\', $dir . 'p_' . $id) . '" 2>nul');
        // a blank page is about 1 KB: keep that sheet and Chrome's answer to see why (the newest one only)
        if (! is_file($pdfFile) || filesize($pdfFile) < 3000) {
            @copy($htmlFile, $dir . 'last_failed.html');
            @file_put_contents($dir . 'last_failed.txt', $cmd . "\n\n" . $cliOut . "\n\nPDF: " . (is_file($pdfFile) ? filesize($pdfFile) : 'none') . ' bytes, html ' . strlen($html));
        }
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

        return $this->response->setStatusCode(500)->setBody('The Access PDF could not be created for report #' . (int) $id . ' (no report form is available for it here). Please close this tab and use "Print Report / Save as PDF" instead.');
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
