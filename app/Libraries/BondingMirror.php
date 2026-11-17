<?php

namespace App\Libraries;

/**
 * Keeps what the Specimen Intake saves in tbl_dtr_BondingTracker present in the other bonding tables:
 *   tbl_dtr_bonding_info      the test record (same details: style, colorway, production code, MTL #, dates, side, technician, test type, retest ...)
 *   tbl_dtr_unified_labrefs   the Lab Ref index (through DtrLabrefIndex)
 * tbl_dtr_bonding_spot_points holds the measured spot values of a test (Value per PointName), which only exist once a test is run: the intake has nothing to put there.
 */
class BondingMirror
{
    /** tracker column => bonding_info column, copied as they are when both exist */
    private const SAME = ['MachineNumber', 'RetestOf', 'RetestStatus', 'StartTimestamp', 'FinishTimestamp', 'TestDuration', 'ShoeID', 'AppSheetTestID', 'SourceSystem'];

    /**
     * The bonding_info record of one tracker row (created when it is missing) and its unified Lab Ref row. Shutdown markers have no Lab Ref and are skipped.
     * Returns the BondingTestID, 0 when nothing was needed.
     */
    public static function fromTracker($db, array $t): int
    {
        $ref = trim((string) ($t['LabRefNo'] ?? ''));
        if ($ref === '' || ! empty($t['Shutdown'])) {
            return 0;
        }
        $info = $db->table('tbl_dtr_bonding_info')->select('BondingTestID')->where('LabRefNo', $ref)->orderBy('BondingTestID', 'DESC')->get()->getRowArray();
        if (! $info) {
            $isLack = strtoupper((string) ($t['TestType'] ?? '')) === 'LACKING' || (int) ($t['Lacking'] ?? 0) === 1;
            $notes  = [];
            if (trim((string) ($t['MTL'] ?? '')) !== '') {
                $notes[] = 'MTL ' . trim((string) $t['MTL']);
            }
            if ($isLack) {
                $notes[] = 'LACKING (due ' . substr((string) ($t['LackingDate'] ?? $t['ProductionDate'] ?? ''), 0, 10) . ')';
            }
            if (trim((string) ($t['RetestDetails'] ?? '')) !== '') {
                $notes[] = (trim((string) ($t['RetestOf'] ?? '')) !== '' ? 'RETEST: ' : 'REMARKS: ') . trim((string) $t['RetestDetails']);
            }
            if (trim((string) ($t['PullTestType'] ?? '')) !== '') {
                $notes[] = 'PULL TEST: ' . trim((string) $t['PullTestType']);
            }
            if (trim((string) ($t['EncodedBy'] ?? '')) !== '') {
                $notes[] = 'Encoded by ' . trim((string) $t['EncodedBy']);
            }
            $row = [
                'LabRefNo'         => $ref,
                'Factory'          => 'DATIAN SUBIC SHOES INC. (DTP)',
                'ModelStyle'       => mb_substr(trim((string) ($t['Style'] ?? '')), 0, 191),
                'Color'            => trim((string) ($t['Colorway'] ?? '')),
                'ProductionDate'   => substr((string) ($t['ProductionDate'] ?? ''), 0, 10),
                'POLotNO'          => mb_substr(trim((string) ($t['ProductionCode'] ?? '')), 0, 191),
                'MaterialSource'   => 'Prod. Material',
                'ReceivedDate'     => substr((string) ($t['CollectionDate'] ?? ''), 0, 10),
                'Side'             => strtoupper((string) ($t['Side'] ?? 'L')) === 'R' ? 'Right' : 'Left',
                'Technician'       => mb_substr(trim((string) ($t['Technician'] ?? '')), 0, 191),
                'TestType'         => mb_substr(trim((string) ($t['TestType'] ?? '')), 0, 50),
                'MTL'              => mb_substr(trim((string) ($t['MTL'] ?? '')), 0, 191),
                'EncodedBy'        => mb_substr(trim((string) ($t['EncodedBy'] ?? '')), 0, 191),
                'Remarks'          => mb_substr(trim((string) ($t['TestType'] ?? '')), 0, 191),
                'LabNotesComments' => implode('; ', $notes),
            ];
            foreach (self::SAME as $col) {
                if (isset($t[$col]) && trim((string) $t[$col]) !== '' && $db->fieldExists($col, 'tbl_dtr_bonding_info')) {
                    $row[$col] = $t[$col];
                }
            }
            $db->table('tbl_dtr_bonding_info')->insert($row);
            $id = (int) $db->insertID();
            DtrLabrefIndex::upsert($db, 'bonding', $id, $row + ['Status' => 'Pending', 'Result' => '', 'TestedDate' => '', 'FinishedDate' => '']);

            return $id;
        }
        $id = (int) $info['BondingTestID'];
        // the record exists: make sure it is indexed too
        if (! $db->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->countAllResults()) {
            $full = $db->table('tbl_dtr_bonding_info')->where('BondingTestID', $id)->get()->getRowArray();
            if ($full) {
                DtrLabrefIndex::upsert($db, 'bonding', $id, $full + ['Status' => trim((string) ($full['Result'] ?? '')) !== '' ? 'Complete' : 'Pending', 'TestedDate' => (string) ($full['TestedDate'] ?? ''), 'FinishedDate' => (string) ($full['FinishedDate'] ?? '')]);
            }
        }

        return 0;
    }

    /** Every tracker row with a Lab Ref that has no bonding_info record, and every bonding_info record without a unified Lab Ref row. Returns counts. */
    public static function backfill($db): array
    {
        $added = $indexed = 0;
        $col   = 'COLLATE utf8mb4_general_ci';
        foreach ($db->query("SELECT t.* FROM tbl_dtr_BondingTracker t WHERE t.LabRefNo IS NOT NULL AND t.LabRefNo <> '' AND t.Shutdown = 0
                AND NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo {$col} = t.LabRefNo {$col})")->getResultArray() as $t) {
            if (self::fromTracker($db, $t) > 0) {
                $added++;
            }
        }
        foreach ($db->query("SELECT b.* FROM tbl_dtr_bonding_info b WHERE b.LabRefNo IS NOT NULL AND b.LabRefNo <> ''
                AND NOT EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs u WHERE u.lab_ref_no {$col} = b.LabRefNo {$col})")->getResultArray() as $b) {
            DtrLabrefIndex::upsert($db, 'bonding', (int) $b['BondingTestID'], $b + ['Status' => trim((string) ($b['Result'] ?? '')) !== '' ? 'Complete' : 'Pending']);
            $indexed++;
        }

        return ['info_added' => $added, 'unified_added' => $indexed];
    }
}
