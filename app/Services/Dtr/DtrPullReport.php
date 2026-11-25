<?php

namespace App\Services\Dtr;

/** Pull Footwear Test Report (Lab VF 003): columns, cells and record loading. (moved out of DtrController; bodies unchanged). */
class DtrPullReport
{
    /**
     * GET /dtr/record
     * Fetches the complete record from its native table (e.g. tbl_dtr_fabric_results or tbl_dtr_bonding_info + tbl_dtr_bonding_points).
     */
    /** Report-only columns of the Pull Footwear Test Report (Lab VF 003) that tbl_dtr_pull_tests did not have. */
    public function ensurePullReportCols($db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        // tbl_dtr_pull_tests is at InnoDB's row-size limit (30 x Peak / SampleNo / Graph), so these go to a side table, one row per PullInfoID
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_pull_report (PullInfoID INT UNSIGNED NOT NULL PRIMARY KEY, Extra MEDIUMTEXT NULL, UpdatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
    }

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
    public function pullCells(array $rows, string $code): array
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

    public function pullReportRecord($db, int $sourceId, string $labRef): array
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
            // eTRF No: the one typed on the report, else the one InterLink gave this Lab Ref (tbl_dtr_unified_labrefs.tr_no / etrf_pull_app, the eTRF jobs), as the bonding report does
            'ID' => $r['PullInfoID'] ?? '', 'LabRefNo' => $labRef, 'ETRFNo' => $pick($r['ETRFNo'] ?? '', \App\Libraries\EtrfAppNos::forLabRef($labRef, 'pulltest')),
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
}
