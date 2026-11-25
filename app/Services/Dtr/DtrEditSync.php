<?php

namespace App\Services\Dtr;

/** Locked-edit sync and the Bonding / Whole Shoe balancing rules. (moved out of DtrController; bodies unchanged). */
class DtrEditSync
{
    /**
     * Keeps tbl_dtr_bonding_info and tbl_dtr_BondingTracker balanced for one Lab Ref No: a Lab Ref with no tracker record gets one (built from the
     * bonding record), otherwise the empty columns of each side are filled from the other. Existing data is never overwritten.
     */
    public function balanceBonding($db, string $ref): void
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
    public function balanceWholeShoe($db, int $trackerId): void
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
    public function lockedEdits($db): array
    {
        $this->ensureEditSyncTable($db);
        $out = [];
        foreach ($db->query('SELECT DISTINCT Kind, EntryID FROM tbl_dtr_edit_sync WHERE DoneAt IS NOT NULL')->getResultArray() as $r) {
            $out[] = $r['Kind'] . ':' . (int) $r['EntryID'];
        }

        return $out;
    }

    public function ensureEditSyncTable($db): void
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
    public function fillEmpty($db, string $table, array $where, array $data): void
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
    public function runDueEditSyncs(): int
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

    /** Removes the rows of one Lab Ref from every related table that holds data for it (tables / columns that do not exist are skipped). */
    public function purgeLabRef($db, string $ref, array $targets): void
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
}
