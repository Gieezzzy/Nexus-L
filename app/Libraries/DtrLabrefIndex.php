<?php

namespace App\Libraries;

use App\Controllers\DtrController;

/**
 * tbl_dtr_unified_labrefs: one row per test report (any material form or bonding) that the DTR tracker, the InterLink
 * Lab Reference picker and "Generate Report" read. Built from the report's own row, by the web save and by
 * `php spark dtr:sync-access`.
 */
class DtrLabrefIndex
{
    /** Pass / Tolerable / Fail from the report's result (Access writes FAILED, the web FAIL). */
    public static function overall(string $slug, array $row): string
    {
        $raw = strtoupper(trim((string) ($slug === 'bonding' ? ($row['Result'] ?? '') : ($row['Remarks'] ?? $row['Result'] ?? ''))));
        if ($slug === 'bonding' && $raw === '') {
            return '';   // collected but not tested yet: no result, so not shown as a Pass
        }

        if ($raw === '' && strtoupper(trim((string) ($row['Status'] ?? ''))) === 'PENDING') {
            return '';   // collected, not tested yet: no result
        }

        return match ($raw) {
            'FAIL', 'FAILED' => 'Fail',
            'TOLERABLE'      => 'Tolerable',
            default          => 'Pass',
        };
    }

    private static function date($v): string
    {
        $t = $v !== null && $v !== '' ? strtotime((string) $v) : false;

        return $t ? date('d M Y', $t) : '';
    }

    /** Values of the index row for one report. */
    public static function build(string $slug, int $recordId, array $row): array
    {
        $cfg = DtrController::FORM_CONFIG[$slug];
        $g   = static function (array $keys) use ($row): string {
            foreach ($keys as $k) {
                if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                    return trim((string) $row[$k]);
                }
            }

            return '';
        };
        $matType = $g(['MaterialType', 'AgeCategory']);

        return [
            'lab_ref_no'            => $g(['LabRefNo']),
            'tr_no'                 => $g(['TR', 'ETRFNo']),
            'form_category'         => $cfg['label'],
            'form_slug'             => $slug,
            'interlink_sample_type' => $cfg['sample_type'],
            'source_table'          => $cfg['table'],
            'source_id'             => $recordId,
            'material_type'         => $matType,
            'material_code'         => $g(['MaterialCode', 'AdhesiveSupplier']),
            'material_description'  => $g(['Material Description', 'MaterialDescription']) ?: $matType,
            'supplier'              => $g(['Supplier/Factory', 'Supplier', 'Factory']),
            'model_style'           => $g(['Model No/Style', 'ModelNoStyle', 'ModelStyle']),
            'color'                 => $g(['Color']),
            'po_lot_no'             => $g(['PO/Lot No', 'PO/LotNo', 'POLotNo', 'POLotNO']),
            'mtl_no'                => $g(['MTL']),
            'received_date'         => self::date($g(['Received Date', 'ReceivedDate', 'RecievedDate'])),
            'tested_date'           => self::date($g(['Tested Date', 'TestedDate'])),
            'finished_date'         => self::date($g(['Finished Date', 'FinishedDate'])),
            'technician'            => $g(['Technician']),
            'status'                => $g(['Status']) ?: ($slug === 'bonding' && self::overall($slug, $row) === '' ? 'Pending' : 'Complete'),
            'overall_result'        => self::overall($slug, $row),
            'thickness_ave'         => $g(['TAve', 'Hardness_Ave']),
            'weight_ave'            => $g(['WAve', 'SG_Ave']),
        ];
    }

    /** Adds or refreshes the index row of one report; skipped when the report has no Lab Ref. */
    public static function upsert($db, string $slug, int $recordId, array $row): void
    {
        $data = self::build($slug, $recordId, $row);
        if ($data['lab_ref_no'] === '') {
            return;
        }
        $t   = $db->table('tbl_dtr_unified_labrefs');
        $has = $t->where('source_table', $data['source_table'])->where('source_id', $recordId)->get()->getRowArray();
        if (! $has) {
            // no row for this record id: reuse the row already indexed under the same Lab Ref No (never a second copy of one Lab Ref)
            $has = $db->table('tbl_dtr_unified_labrefs')->where('source_table', $data['source_table'])->where('lab_ref_no', $data['lab_ref_no'])->orderBy('id', 'DESC')->get(1)->getRowArray();
        }
        $has ? $db->table('tbl_dtr_unified_labrefs')->where('id', $has['id'])->update($data) : $db->table('tbl_dtr_unified_labrefs')->insert($data);
    }

    /** Pull test (HP / EY / DR / RVT): its row of tbl_dtr_pull_tests as an index row, form_slug 'pulltest' (Pending until a result is recorded). */
    public static function upsertPullTest($db, int $pullInfoId, array $r): void
    {
        $ref = trim((string) ($r['LabRefNo'] ?? ''));
        if ($ref === '') {
            return;
        }
        $res  = strtoupper(trim((string) ($r['Result'] ?? '')));
        $data = [
            'lab_ref_no' => $ref, 'tr_no' => '', 'form_category' => 'Pull Test', 'form_slug' => 'pulltest', 'interlink_sample_type' => '',
            'source_table' => 'tbl_dtr_pull_tests', 'source_id' => $pullInfoId, 'material_type' => (string) ($r['TestType'] ?? ''), 'material_code' => '',
            'material_description' => (string) ($r['TestType'] ?? ''), 'supplier' => '', 'model_style' => (string) ($r['ModelStyle'] ?? ''), 'color' => (string) ($r['Color'] ?? ''),
            'po_lot_no' => (string) ($r['ProductionCode'] ?? ''), 'mtl_no' => (string) ($r['MTL'] ?? ''), 'received_date' => self::date($r['CollectionDate'] ?? ''),
            'tested_date' => self::date($r['TestedDate'] ?? ''), 'finished_date' => '', 'technician' => (string) ($r['Technician'] ?? ''),
            'status' => $res === '' ? 'Pending' : 'Complete',
            'overall_result' => $res === '' ? '' : (str_starts_with($res, 'FAIL') ? 'Fail' : (str_starts_with($res, 'TOL') ? 'Tolerable' : 'Pass')),
        ];
        $has = $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_pull_tests')->where('source_id', $pullInfoId)->get()->getRowArray();
        $has ? $db->table('tbl_dtr_unified_labrefs')->where('id', $has['id'])->update($data) : $db->table('tbl_dtr_unified_labrefs')->insert($data);
    }

    /**
     * Whole Shoe: the lab reference of a whole shoe collection (tbl_dtr_wholeshoe_intake, with the style / colorway / MTL # of its production
     * code) as an index row, form_slug 'wholeshoe'. Its status and result come from the tests of that shoe (tbl_dtr_wholeshoetracker).
     * Adds the row, or refreshes it when it exists.
     */
    public static function upsertWholeShoe($db, int $intakeId): void
    {
        if (! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return;
        }
        $r = $db->table('tbl_dtr_wholeshoe_intake i')->select('i.*, s.StyleNo, s.Colorway, s.MTL')->join('tbl_dtr_shoe_list s', 's.ProdCode = i.ProdCode', 'left')->where('i.IntakeID', $intakeId)->get()->getRowArray();
        $lab = trim((string) ($r['LabRefNo'] ?? ''));
        if (! $r || $lab === '') {
            return;
        }
        $kids = $db->tableExists('tbl_dtr_wholeshoetracker')
            ? $db->query('SELECT Status, FinalResult FROM tbl_dtr_wholeshoetracker WHERE WholeshoeLabRefNo = ? OR WholeshoeLabRefNo LIKE ?', [$lab, $db->escapeLikeString($lab) . '-%'])->getResultArray() : [];
        $done = $kids !== [] && count(array_filter($kids, static fn ($k) => stripos((string) $k['Status'], 'COMPLETE') === 0)) === count($kids);
        $fail = array_filter($kids, static fn ($k) => stripos((string) $k['FinalResult'], 'F') === 0) !== [];
        $style = trim((string) $r['StyleNo']);
        $date  = static fn ($v) => $v ? date('d M Y', strtotime((string) $v)) : '';
        $data  = [
            'lab_ref_no' => $lab, 'tr_no' => '', 'form_category' => 'Whole Shoe', 'form_slug' => 'wholeshoe', 'interlink_sample_type' => 'Finished Product',
            'source_table' => 'tbl_dtr_wholeshoe_intake', 'source_id' => $intakeId,
            'material_type' => (string) $r['TestTypes'], 'material_code' => '', 'material_description' => trim($style . ' ' . (string) $r['Colorway']),
            'supplier' => '', 'model_style' => $style, 'color' => (string) $r['Colorway'], 'po_lot_no' => (string) $r['ProdCode'], 'mtl_no' => (string) $r['MTL'],
            'received_date' => $date($r['CollectionDate']), 'tested_date' => '', 'finished_date' => '', 'technician' => (string) ($r['EncodedBy'] ?? ''),
            'status' => $done ? 'Complete' : 'Pending', 'overall_result' => $fail ? 'Fail' : ($done ? 'Pass' : ''),
        ];
        $has = $db->table('tbl_dtr_unified_labrefs')->where('source_table', 'tbl_dtr_wholeshoe_intake')->where('source_id', $intakeId)->get()->getRowArray();
        $has ? $db->table('tbl_dtr_unified_labrefs')->where('id', $has['id'])->update($data) : $db->table('tbl_dtr_unified_labrefs')->insert($data);
    }

    /** Whole shoe collections that have no index row yet (the others are refreshed). Returns how many were added. */
    public static function addMissingWholeShoe($db, bool $dry = false): int
    {
        if (! $db->tableExists('tbl_dtr_wholeshoe_intake')) {
            return 0;
        }
        $n = 0;
        foreach ($db->query("SELECT i.IntakeID, (SELECT COUNT(*) FROM tbl_dtr_unified_labrefs u WHERE u.source_table = 'tbl_dtr_wholeshoe_intake' AND u.source_id = i.IntakeID) AS has
                FROM tbl_dtr_wholeshoe_intake i WHERE i.LabRefNo IS NOT NULL AND i.LabRefNo <> ''")->getResultArray() as $r) {
            $n += (int) $r['has'] === 0 ? 1 : 0;
            $dry || self::upsertWholeShoe($db, (int) $r['IntakeID']);
        }

        return $n;
    }

    /** BondingTestID => Result of tbl_dtr_bonding_points, read once (the table has no index on BondingTestID). */
    public static function pointsResults($db): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach ($db->query("SELECT BondingTestID, Result FROM tbl_dtr_bonding_points WHERE Result IS NOT NULL AND Result <> ''")->getResultArray() as $p) {
                $map[(int) $p['BondingTestID']] = (string) $p['Result'];
            }
        }

        return $map;
    }

    /** Index rows for reports that have none yet (records that arrived from Access). Returns how many were added. */
    public static function addMissing($db, bool $dry = false): int
    {
        $n = 0;
        foreach (DtrController::FORM_CONFIG as $slug => $cfg) {
            if (! $db->tableExists($cfg['table'])) {
                continue;
            }
            $pk   = $cfg['pk'];
            $rows = $db->query('SELECT r.* FROM `' . $cfg['table'] . '` r WHERE r.`LabRefNo` IS NOT NULL AND r.`LabRefNo` <> \'\' AND NOT EXISTS '
                . '(SELECT 1 FROM tbl_dtr_unified_labrefs u WHERE u.source_table = ? AND u.source_id = r.`' . $pk . '`)', [$cfg['table']])->getResultArray();
            foreach ($rows as $r) {
                if ($slug === 'bonding' && trim((string) ($r['Result'] ?? '')) === '') {
                    $r['Result'] = self::pointsResults($db)[(int) $r[$pk]] ?? '';
                }
                $dry || self::upsert($db, $slug, (int) $r[$pk], $r);
                $n++;
            }
        }

        return $n + self::addMissingWholeShoe($db, $dry);
    }
}
