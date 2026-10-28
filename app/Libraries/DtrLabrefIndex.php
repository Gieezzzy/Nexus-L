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
            'status'                => $g(['Status']) ?: 'Complete',
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
        $has ? $db->table('tbl_dtr_unified_labrefs')->where('id', $has['id'])->update($data) : $db->table('tbl_dtr_unified_labrefs')->insert($data);
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
                    $r['Result'] = $db->table('tbl_dtr_bonding_points')->select('Result')->where('BondingTestID', $r[$pk])->get()->getRow()->Result ?? '';
                }
                $dry || self::upsert($db, $slug, (int) $r[$pk], $r);
                $n++;
            }
        }

        return $n;
    }
}
