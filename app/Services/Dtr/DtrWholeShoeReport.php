<?php

namespace App\Services\Dtr;

/** Whole Shoe report sections, table and pulled data. (moved out of DtrController; bodies unchanged). */
class DtrWholeShoeReport
{
    /** The test sections of the form: id => [test ticked at intake, title, conditions, fields key => label]. */
    public function wsSections(): array
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

    public function wsReportTable(): \CodeIgniter\Database\BaseConnection
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_wholeshoe_reports (
            ReportID INT AUTO_INCREMENT PRIMARY KEY,
            LabRefNo VARCHAR(191) NOT NULL,
            UpdatedWholeshoeLabRef VARCHAR(191) NULL,
            ReportData LONGTEXT NULL,
            UpdatedBy VARCHAR(191) NULL,
            UpdatedAt DATETIME NULL,
            UNIQUE KEY uq_labref (LabRefNo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if ($db->query("SHOW COLUMNS FROM tbl_dtr_wholeshoe_reports LIKE 'UpdatedWholeshoeLabRef'")->getNumRows() < 1) {
            $db->query('ALTER TABLE tbl_dtr_wholeshoe_reports ADD COLUMN UpdatedWholeshoeLabRef VARCHAR(191) NULL AFTER LabRefNo');
        }
        if ($db->query("SHOW TABLES LIKE 'tbl_dtr_WholeshoeTracker'")->getNumRows() > 0
            && $db->query("SHOW COLUMNS FROM tbl_dtr_WholeshoeTracker LIKE 'UpdatedWholeshoeLabRef'")->getNumRows() < 1) {
            $db->query('ALTER TABLE tbl_dtr_WholeshoeTracker ADD COLUMN UpdatedWholeshoeLabRef VARCHAR(191) NULL AFTER WholeshoeLabRefNo');
        }

        return $db;
    }

    /** Whole shoe intake line of a Lab Ref (joined with its shoe) + the saved report data. */
    public function wsLoad(string $ref): ?array
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
        $pulled = $this->wsPulled($db, $ref);
        // every section of the form stays (like the paper form); only the sections of this Lab Ref's tests get the pulled data
        $sections = [];
        foreach ($this->wsSections() as $id => $s) {
            $sections[] = ['id' => $id, 'title' => strip_tags(str_replace('&nbsp;', ' ', $s[1])), 'cond' => $s[2], 'fields' => $s[3]];
        }

        // Read existing ETRF / TR No from tbl_dtr_unified_labrefs (mother Lab Ref or any child Whole Shoe test Lab Ref)
        $etrfNo = '';
        try {
            $refSet = [$ref];
            $baseRef = preg_replace('/-[LR]$/i', '', $ref);
            if ($baseRef !== '' && $baseRef !== $ref) {
                $refSet[] = $baseRef;
            }
            foreach ($pulled as $p) {
                if (! empty($p['labRef'])) {
                    $refSet[] = (string) $p['labRef'];
                }
            }
            $uRow = $db->table('tbl_dtr_unified_labrefs')
                ->select('tr_no')
                ->whereIn('lab_ref_no', array_values(array_unique($refSet)))
                ->where("tr_no IS NOT NULL AND TRIM(tr_no) <> ''", null, false)
                ->orderBy('id', 'DESC')->get(1)->getRowArray();
            if ($uRow) {
                $etrfNo = trim((string) ($uRow['tr_no'] ?? ''));
            }
        } catch (\Throwable $e) {
            // optional lookup
        }

        $savedData = $saved ? (json_decode((string) $saved['ReportData'], true) ?: []) : [];
        $updatedRef = trim((string) ($saved['UpdatedWholeshoeLabRef'] ?? ($savedData['updatedLabRef'] ?? '')));
        $savedSide  = strtoupper(trim((string) ($savedData['side'] ?? '')));
        if (! in_array($savedSide, ['L', 'R'], true) && preg_match('/-([LR])$/i', $updatedRef, $sm)) {
            $savedSide = strtoupper($sm[1]);
        }

        return [
            'info' => [
                'labRef' => $ref, 'updatedLabRef' => $updatedRef, 'side' => $savedSide,
                'etrfNo' => $etrfNo, 'prodCode' => $row['ProdCode'], 'style' => $row['StyleNo'] ?? '', 'color' => $row['Colorway'] ?? '',
                'qty' => (int) ($row['NoOfShoes'] ?? 1), 'received' => substr((string) ($row['CollectionDate'] ?? ''), 0, 10),
                'tests' => $tests, 'encodedBy' => $row['EncodedBy'] ?? '',
            ],
            'sections' => $sections,
            'data'     => $savedData,
            'pulled'   => $pulled,
        ];
    }

    /**
     * The result of every test of a whole shoe Lab Ref, pulled from tbl_dtr_WholeshoeTracker (one row per test type):
     * section of the form it belongs to, Result / Status, dates and the Findings text split over the section's fields.
     *
     * @return list<array<string, mixed>>
     */
    public function wsPulled(\CodeIgniter\Database\BaseConnection $db, string $ref): array
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
        $typeS = ['UV' => 'E03', 'UV Light' => 'E03', 'Washing' => 'G03', 'Dip Test' => 'E26', 'Heat Aging' => 'E04', 'Hydrolysis' => 'TM361'];
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
}
