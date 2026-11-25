<?php

namespace App\Services\Dtr;

/** Creates / upgrades the Lab Tracker tables on first use. (moved out of DtrController; bodies unchanged). */
class DtrTrackerSchema
{
    private static bool $trackerTablesReady = false;

    /** Lab Tracker intake tables: one row per specimen line saved from the Specimen Intake / Whole Shoe Intake popups. */
    public function ensureTrackerTables($db): void
    {
        if (self::$trackerTablesReady) {
            return;
        }
        self::$trackerTablesReady = true;
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
        // a LACKING line that already has its entry (Prod. Code typed) is a Daily record: it is read like one (not in Lacking any more)
        $db->query("UPDATE tbl_dtr_bonding_info b JOIN tbl_dtr_BondingTracker t ON t.LabRefNo = b.LabRefNo COLLATE utf8mb4_general_ci
            SET b.TestType = 'DAILY', b.Remarks = 'DAILY', b.LabNotesComments = REPLACE(b.LabNotesComments, 'LACKING', 'DAILY')
            WHERE UPPER(t.TestType) = 'LACKING' AND t.Shutdown = 0 AND TRIM(COALESCE(t.ProductionCode, '')) <> '' AND UPPER(b.TestType) = 'LACKING'");
        if ($db->query("UPDATE tbl_dtr_BondingTracker SET TestType = 'DAILY', Lacking = 0, LackingDate = NULL
            WHERE UPPER(TestType) = 'LACKING' AND Shutdown = 0 AND TRIM(COALESCE(ProductionCode, '')) <> ''") && $db->affectedRows() > 0) {
            \App\Libraries\DataCache::bump();
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
        // IGNORE: a ref whose -L / -R twin already exists (DTP20261116UVA3 next to DTP20261116UVA3-L) is left as it is instead of stopping every save with a duplicate-key error
        $db->query("UPDATE IGNORE tbl_dtr_WholeshoeTracker SET
                LabRefNo = CONCAT(LabRefNo, '-', Side)
            WHERE Side IN ('L', 'R') AND LabRefNo NOT LIKE '%-L' AND LabRefNo NOT LIKE '%-R' AND LabRefNo NOT REGEXP '-[LR]-[0-9]+\$'");
        // the MOTHER Lab Ref has no side (DTP20261118WHA4, not ...WHA4-L): the side is in the Lab Ref No of each test. Only mothers that differ from the row's own Lab Ref are touched
        $db->query("UPDATE tbl_dtr_WholeshoeTracker SET WholeshoeLabRefNo = REGEXP_REPLACE(WholeshoeLabRefNo, '-[LR]\$', '') WHERE WholeshoeLabRefNo REGEXP '^DTP[0-9]{8}W[HS][AB]?[0-9]*-[LR]\$' AND WholeshoeLabRefNo <> LabRefNo");
        if ($db->tableExists('tbl_dtr_wholeshoe_intake') && $db->tableExists('tbl_dtr_unified_labrefs')) {
            $db->query("UPDATE tbl_dtr_unified_labrefs u JOIN tbl_dtr_wholeshoe_intake i ON u.source_table = 'tbl_dtr_wholeshoe_intake' AND u.source_id = i.IntakeID
                SET u.lab_ref_no = REGEXP_REPLACE(u.lab_ref_no, '-[LR]\$', '') WHERE u.lab_ref_no REGEXP '^DTP[0-9]{8}W[HS][AB]?[0-9]*-[LR]\$' AND NOT EXISTS (SELECT 1 FROM tbl_dtr_WholeshoeTracker w WHERE w.LabRefNo = u.lab_ref_no)");
        }
        if ($db->tableExists('tbl_dtr_wholeshoe_intake')) {
            $db->query("UPDATE tbl_dtr_wholeshoe_intake i SET i.LabRefNo = REGEXP_REPLACE(i.LabRefNo, '-[LR]\$', '') WHERE i.LabRefNo REGEXP '^DTP[0-9]{8}W[HS][AB]?[0-9]*-[LR]\$' AND NOT EXISTS (SELECT 1 FROM tbl_dtr_WholeshoeTracker w WHERE w.LabRefNo = i.LabRefNo)");
        }
        // a Lab Ref No saved with a space in front (" DTP20261114SHA4-R") is no longer found by the tracker / pull test / index joins: spaces are trimmed (IGNORE: a trimmed twin that already exists is left alone)
        foreach ([['tbl_dtr_bonding_info', 'LabRefNo'], ['tbl_dtr_BondingTracker', 'LabRefNo'], ['tbl_dtr_pull_tests', 'LabRefNo'], ['tbl_dtr_unified_labrefs', 'lab_ref_no']] as [$tb, $cl]) {
            try {
                $db->query("UPDATE IGNORE {$tb} SET {$cl} = TRIM({$cl}) WHERE {$cl} IS NOT NULL AND {$cl} <> TRIM({$cl})");
            } catch (\Throwable $e) { /* table not there yet */ }
        }
        if ($db->tableExists('tbl_dtr_wholeshoe_intake') && in_array('side', array_map('strtolower', $db->getFieldNames('tbl_dtr_wholeshoe_intake')), true)) {
        }
        if (in_array('testtypes', $have, true)) {
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
}
