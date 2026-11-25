<?php

namespace App\Services\Analytics;

/** Daily / weekly workload view (Lab Tracker based). */
class DailyWorkloadAnalytics extends AnalyticsBase
{
    /** the controller's request (period / year / week filters are read from it) */
    public $request;

    /** Daily Works Monitoring: year of materials received vs encoded (+ by type) and the day-by-day lab workload with encoding progress. */
    public function dailyWorks($db): array
    {
        $ok     = static fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
        $period = strtolower((string) ($this->request->getGet('period') ?: 'daily'));
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            $period = 'daily';
        }
        $year = (int) ($this->request->getGet('year') ?: date('Y'));
        $year = ($year >= 2020 && $year <= 2100) ? $year : (int) date('Y');
        $asof = $ok($this->request->getGet('asof')) ?: date('Y-m-d');   // the default day is today: the same day the Lab Tracker lists (Daily shoes of the day before + today's Tech Trial / Retest / Sample)
        $reqWeek  = (string) ($this->request->getGet('week') ?: '');
        $reqMonth = (int) ($this->request->getGet('month') ?: 0);
        // custom date range from the calendar picker (same as Daily Bonding); one day = plain daily, several days = custom window
        $cFrom = $ok($this->request->getGet('from'));
        $cTo   = $ok($this->request->getGet('to'));
        if ($cFrom !== '' && $cTo !== '') {
            if ($cFrom > $cTo) {
                [$cFrom, $cTo] = [$cTo, $cFrom];
            }
            if ($cFrom === $cTo) {
                $period = 'daily';
                $asof   = $cFrom;
            } else {
                $period = 'custom';
                if ((strtotime($cTo) - strtotime($cFrom)) / 86400 > 92) {
                    $cFrom = date('Y-m-d', strtotime($cTo . ' -92 days'));   // keep the day list readable
                }
            }
        }

        if ($period === 'custom') {
            $winFrom = $cFrom;
            $winTo   = $cTo;
            $asof    = $cTo;
            $year    = (int) date('Y', strtotime($cTo));
            $week    = date('o-\WW', strtotime($cTo));
            $month   = (int) date('n', strtotime($cTo));
            $encFrom = $winFrom;
            $encTo   = $winTo;
            $periodTitle = date('M j', strtotime($winFrom)) . ' – ' . date('M j, Y', strtotime($winTo));
            $windowTag   = date('M j', strtotime($winFrom)) . '–' . date('M j', strtotime($winTo));
            $windowShort = 'Range';
            $windowLabel = 'Custom (' . $periodTitle . ')';
            $buckets = [];
            for ($ts = strtotime($winTo); $ts >= strtotime($winFrom); $ts = strtotime('-1 day', $ts)) {
                $ds = date('Y-m-d', $ts);
                $buckets[] = ['date' => $ds, 'from' => $ds, 'to' => $ds, 'label' => date('M j, Y', $ts), 'short' => date('D m/d', $ts)];
            }
        } elseif ($period === 'weekly') {
            if (preg_match('/^(\d{4})-W(0[1-9]|[1-4]\d|5[0-3])$/', $reqWeek, $wm)) {
                $wy = (int) $wm[1];
                $ww = (int) $wm[2];
                $year = ($wy >= 2020 && $wy <= 2100) ? $wy : $year;
            } else {
                $baseTs = (int) date('Y', strtotime($asof)) === $year ? strtotime($asof) : strtotime("{$year}-01-04");
                $wy = $year;
                $ww = (int) date('W', $baseTs);
            }
            $mon = (new \DateTimeImmutable())->setISODate($wy, $ww, 1);
            $sun = $mon->modify('+6 days');
            $winFrom = $mon->format('Y-m-d');
            $winTo   = $sun->format('Y-m-d');
            $asof    = $winTo;
            $week    = $mon->format('o-\WW');
            $month   = (int) $mon->format('n');
            $encFrom = $winFrom;
            $encTo   = $winTo;
            $periodTitle = 'Week ' . $mon->format('W') . ' (' . $mon->format('M j') . ' – ' . $sun->format('M j, Y') . ')';
            $windowTag   = 'Weekly';
            $windowShort = 'Weekly';
            $windowLabel = 'Weekly (' . $mon->format('M j') . ' – ' . $sun->format('M j, Y') . ')';
            $buckets = [];
            for ($k = 6; $k >= 0; $k--) {
                $dt = $mon->modify("+{$k} days");
                $ds = $dt->format('Y-m-d');
                $buckets[] = ['date' => $ds, 'from' => $ds, 'to' => $ds, 'label' => $dt->format('M j, Y'), 'short' => $dt->format('D m/d')];
            }
        } elseif ($period === 'monthly') {
            $month = ($reqMonth >= 1 && $reqMonth <= 12) ? $reqMonth : (int) date('n', strtotime($asof));
            $m1    = sprintf('%04d-%02d-01', $year, $month);
            $mEnd  = date('Y-m-t', strtotime($m1));
            $winFrom = $m1;
            $winTo   = $mEnd;
            $asof    = $mEnd;
            $week    = date('o-\WW', strtotime($m1));
            $encFrom = $m1;
            $encTo   = $mEnd;
            $periodTitle = date('F Y', strtotime($m1));
            $windowTag   = date('M', strtotime($m1));
            $windowShort = date('M Y', strtotime($m1));
            $windowLabel = 'Monthly (' . date('F Y', strtotime($m1)) . ')';
            $buckets = [];
            $cur = new \DateTimeImmutable($m1);
            $end = new \DateTimeImmutable($mEnd);
            while ($cur <= $end) {
                $dow    = (int) $cur->format('N'); // 1=Mon .. 7=Sun
                $sliceEnd = $cur->modify('+' . (7 - $dow) . ' days');
                if ($sliceEnd > $end) {
                    $sliceEnd = $end;
                }
                $bFrom = $cur->format('Y-m-d');
                $bTo   = $sliceEnd->format('Y-m-d');
                $bShort = $bFrom === $bTo ? $cur->format('M j') : ($cur->format('M j') . '–' . $sliceEnd->format('j'));
                $buckets[] = ['date' => $bTo, 'from' => $bFrom, 'to' => $bTo, 'label' => $bShort . ', ' . $year, 'short' => $bShort];
                $cur = $sliceEnd->modify('+1 day');
            }
            $buckets = array_reverse($buckets); // newest slice first to match daily order
        } else {
            $winFrom = $asof;
            $winTo   = $asof;
            $week    = date('o-\WW', strtotime($asof));
            $month   = (int) date('n', strtotime($asof));
            $encFrom = $asof;
            $encTo   = $asof;
            $periodTitle = date('M j, Y', strtotime($asof));
            $windowTag   = date('M j', strtotime($asof));
            $windowShort = 'Daily';
            $windowLabel = 'Daily (' . date('M j, Y', strtotime($asof)) . ')';
            $buckets = [
                ['date' => $asof, 'from' => $asof, 'to' => $asof, 'label' => date('M j, Y', strtotime($asof)), 'short' => date('M j', strtotime($asof))],
            ];
        }

        // ---- A. materials received per month, encoded in INTERLINK, per material type ----
        $types  = ['Fabric', 'Leather', 'Synthetic', 'Accessories', 'Rubber'];
        $months = \App\Libraries\DataCache::remember('analytics_months_' . $year, 600, function () use ($db, $year, $types) {   // two scans of the materials tables: kept 10 minutes
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = ['recv' => 0, 'enc' => 0, 'types' => array_fill_keys($types, 0)];
        }
        // 1) All material test reports in tbl_dtr_unified_labrefs (covers Jan..Dec across all material categories)
        $uMo = "CAST(COALESCE(
            CASE WHEN lab_ref_no REGEXP '^DTP{$year}[0-1][0-9]' THEN SUBSTR(lab_ref_no, 8, 2) END,
            MONTH(STR_TO_DATE(received_date, '%d %b %Y')),
            MONTH(STR_TO_DATE(tested_date, '%d %b %Y')),
            CASE WHEN received_date REGEXP '^{$year}-[0-9]{2}' THEN SUBSTR(received_date, 6, 2) END,
            CASE WHEN tested_date REGEXP '^{$year}-[0-9]{2}' THEN SUBSTR(tested_date, 6, 2) END
        ) AS UNSIGNED)";
        $uTy = "CASE
            WHEN UPPER(COALESCE(material_type,'')) LIKE '%SYNTHETIC%' OR UPPER(COALESCE(material_type,'')) LIKE '%SUEDE%' THEN 'Synthetic'
            WHEN form_slug = 'leather' OR UPPER(COALESCE(material_type,'')) LIKE '%LEATHER%' THEN 'Leather'
            WHEN form_slug = 'fabric' OR UPPER(COALESCE(material_type,'')) REGEXP 'FABRIC|LINING|MESH|CANVAS|TEXTILE' THEN 'Fabric'
            WHEN form_slug IN ('rubber', 'fthl') OR UPPER(COALESCE(material_type,'')) REGEXP 'RUBBER|OUTSOLE|FOXING|SOLE|HEEL LOGO' THEN 'Rubber'
            ELSE 'Accessories'
        END";
        $uEnc = "(COALESCE(overall_result,'') <> '' OR COALESCE(interlink_flag,'') <> '' OR COALESCE(status,'') IN ('Completed','Done','Pass','Passed','Fail','Failed'))";
        if ($db->tableExists('tbl_dtr_unified_labrefs')) {
            $uRows = $db->query("SELECT {$uMo} mo, {$uTy} tp, COUNT(*) n, SUM({$uEnc}) e
                FROM tbl_dtr_unified_labrefs
                WHERE form_slug NOT IN ('bonding', 'wholeshoe', 'pulltest')
                  AND (lab_ref_no LIKE 'DTP{$year}%' OR received_date LIKE '%{$year}%' OR tested_date LIKE '%{$year}%')
                GROUP BY mo, tp")->getResultArray();
            foreach ($uRows as $r) {
                $mo = (int) $r['mo'];
                if ($mo < 1 || $mo > 12) {
                    continue;
                }
                $months[$mo]['recv'] += (int) $r['n'];
                $months[$mo]['enc']  += (int) $r['e'];
                $months[$mo]['types'][$r['tp']] = ($months[$mo]['types'][$r['tp']] ?? 0) + (int) $r['n'];
            }
        }
        // 2) Supplement with any months in tbl_dtr_materials_master that have additional IQC intake records not yet in unified_labrefs
        $md  = "COALESCE(NULLIF(LEFT(m.MaterialDateReceivedInLab,10),'0000-00-00'), NULLIF(LEFT(c.DateReceivedInLab,10),'0000-00-00'), NULLIF(LEFT(m.MaterialDateArrived,10),'0000-00-00'), NULLIF(LEFT(c.DateArrived,10),'0000-00-00'))";
        $ty  = "CASE WHEN UPPER(m.MaterialType) LIKE '%LEATHER%' THEN 'Leather' WHEN UPPER(m.MaterialType) REGEXP 'RUBBER|OUTSOLE|FOXING|SOLE' THEN 'Rubber' WHEN UPPER(m.MaterialType) LIKE '%SYNTHETIC%' OR UPPER(m.MaterialType) LIKE '%SUEDE%' THEN 'Synthetic' WHEN UPPER(m.MaterialType) REGEXP 'FABRIC|LINING|MESH|CANVAS|TEXTILE' THEN 'Fabric' ELSE 'Accessories' END";
        $enc = "(UPPER(COALESCE(m.IQCERP,''))='OK' OR UPPER(COALESCE(m.FinalStatus,'')) IN ('PASS','TOLERABLE','FAILED') OR EXISTS (SELECT 1 FROM tbl_interlink_reports r WHERE r.synced_to_interlink=1 AND r.material_code<>'' AND r.material_code = m.MaterialCode COLLATE utf8mb4_general_ci))";
        $rows = $db->query("SELECT CAST(SUBSTR({$md},6,2) AS UNSIGNED) mo, {$ty} tp, COUNT(*) n, SUM({$enc}) e
            FROM tbl_dtr_materials_master m LEFT JOIN tbl_dtr_containers c ON c.ContainerID = m.ContainerID
            WHERE {$md} BETWEEN '{$year}-01-01' AND '{$year}-12-31'
              AND (m.LabReference IS NULL OR m.LabReference = '' OR NOT EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs u WHERE u.lab_ref_no = m.LabReference))
            GROUP BY mo, tp")->getResultArray();
        foreach ($rows as $r) {
            $mo = (int) $r['mo'];
            if ($mo < 1 || $mo > 12 || $months[$mo]['recv'] > 0) {
                continue;
            }
            $months[$mo]['recv'] += (int) $r['n'];
            $months[$mo]['enc']  += (int) $r['e'];
            $months[$mo]['types'][$r['tp']] = ($months[$mo]['types'][$r['tp']] ?? 0) + (int) $r['n'];
        }

        return $months;
        });

        // ---- B. day-by-day bonding workload (specimens collected that day) ----
        $isPull = static fn (string $lab): bool => (bool) preg_match('/^DTP\d{8}(EY|DR|RVT|HP|LLH)/i', $lab);
        $classify = static function (array $r): array {
            $t = strtoupper((string) $r['TestType']); $lab = strtoupper((string) $r['LabRefNo']);
            if (trim((string) $r['RetestOf']) !== '' || str_contains($t, 'RETEST') || preg_match('/-R\d+$/', $lab)) {
                return ['retest', 'Daily Bonding'];
            }
            if (str_contains($t, 'SAMPLE')) {
                if (preg_match('/STRIP|OSNA/', $lab)) { return ['sample', 'Rubber Strip Test']; }
                if (str_contains($t, 'RIVET') || str_contains($lab, 'RIVET')) { return ['sample', 'Rivet']; }

                return ['sample', 'Bonding Shoe'];
            }
            if (str_contains($t, 'RIVET')) { return ['sample', 'Rivet']; }
            if (str_contains($t, 'FIRST')) { return ['first', 'Bonding Shoe']; }
            if (str_contains($t, 'TRIAL')) { return ['trial', 'Bonding Shoe']; }
            if (! str_contains($t, 'DAILY')) { return ['', '']; }   // only what the Lab Tracker counts as Daily

            return ['daily', 'Daily Bonding Shoe'];
        };
        $sections = [
            'daily'  => ['DAILY BONDING TEST', ['Daily Bonding Shoe', 'Daily Bonding Shoe (Advance)', 'Daily Bonding Shoe (Lacking)']],
            'retest' => ['BONDING FAILED RE-TEST', ['Daily Bonding']],
            'first'  => ['PRODUCTION FIRST ARTICLE', ['Bonding Shoe', 'Whole Shoe']],
            'sample' => ['SAMPLE', ['Bonding Shoe', 'Rubber Strip Test', 'Rivet']],
            'trial'  => ['TECH TRIAL', ['Bonding Shoe']],
        ];
        $dayExpr = "LEFT(COALESCE(NULLIF(ReceivedDate,''),NULLIF(TestedDate,''),NULLIF(ProductionDate,'')),10)";
        $baseW   = 'NOT (' . self::SHUTDOWN_ROW . ') AND ' . self::BOND_DEDUP;
        // the tracker table is tbl_dtr_bondingtracker on some servers: tableExists() is case sensitive, so both spellings are tried (the queries themselves are not)
        $hasTrk  = ($db->tableExists('tbl_dtr_BondingTracker') || $db->tableExists('tbl_dtr_bondingtracker')) && $db->fieldExists('Shutdown', 'tbl_dtr_BondingTracker');
        $hasWs   = $db->tableExists('tbl_dtr_WholeshoeTracker');
        $days    = [];
        $eWinFrom = $db->escape($winFrom);
        $eWinTo   = $db->escape($winTo);
        // Same rules as the Lab Tracker chips: Test Type from the intake tracker, COLLECTION date (Daily counts the next working day, Saturday -> Monday),
        // Advance / Lacking / Shutdown count on the day they were loaded (CreatedAt) or on the day their Daily batch counts
        $eCollFrom = $db->escape(date('Y-m-d', strtotime($winFrom . ' -3 day')));
        $bondRows = $db->query("SELECT b.LabRefNo, b.Result, b.RetestOf, b.TestedDate, b.ProductionDate, COALESCE(" . ($hasTrk ? "NULLIF(LEFT(t.ProductionDate, 10), ''), " : '') . "LEFT(b.ProductionDate, 10)) AS pd2,
                UPPER(COALESCE(" . ($hasTrk ? "NULLIF(t.TestType COLLATE utf8mb4_general_ci, ''), " : '') . "NULLIF(b.TestType COLLATE utf8mb4_general_ci, ''), '')) AS TestType,
                COALESCE(" . ($hasTrk ? "NULLIF(LEFT(t.CollectionDate, 10), ''), " : '') . "NULLIF(LEFT(b.ReceivedDate, 10), ''), NULLIF(LEFT(b.TestedDate, 10), ''), LEFT(b.ProductionDate, 10)) AS cd
            FROM (SELECT * FROM tbl_dtr_bonding_info WHERE {$baseW} AND COALESCE(TestType, '') <> 'LACKING') b"
            . ($hasTrk ? " LEFT JOIN (SELECT LabRefNo, MAX(TrackerID) AS tid FROM tbl_dtr_BondingTracker WHERE LabRefNo IS NOT NULL GROUP BY LabRefNo) tm ON tm.LabRefNo = b.LabRefNo COLLATE utf8mb4_general_ci
                LEFT JOIN tbl_dtr_BondingTracker t ON t.TrackerID = tm.tid" : '') . "
            HAVING cd BETWEEN {$eCollFrom} AND {$eWinTo}")->getResultArray();
        $effOf = static function (string $tt, string $lab, string $cd, string $pd = '', string $td = ''): string {
            $daily = str_contains($tt, 'DAILY') && ! preg_match('/^DTP\d{8}(HP|EY|DR|RVT|LLH)/i', $lab);
            // a First Article / Retest counts on the day it was TESTED (same rule as the Lab Tracker); until it has a tested date it stays on its collection date
            if (! $daily && (str_contains($tt, 'FIRST') || str_contains($tt, 'RETEST')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $td)) {
                return $td;
            }
            if (! $daily || $cd === '') {
                return $cd;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd) && $pd < $cd) {
                $cd = $pd;   // collected late: it stays with its production batch (same rule as the Lab Tracker)
            }

            return date('Y-m-d', strtotime($cd . ((int) date('N', strtotime($cd)) === 6 ? ' +2 day' : ' +1 day')));
        };
        $resByRef = [];
        foreach ($bondRows as $r) {
            if (trim((string) $r['Result']) !== '') { $resByRef[strtoupper((string) $r['LabRefNo'])] = true; }
        }
        // a shoe whose pull test (EY / RVT / DR / HP / LLH of the same date, line and side) already has a result counts as finished too, like the Lab Tracker row result
        $pullDone = [];
        if ($db->tableExists('tbl_dtr_pull_tests')) {
            foreach ($db->query("SELECT LabRefNo FROM tbl_dtr_pull_tests WHERE Result IS NOT NULL AND Result <> '' AND LabRefNo REGEXP '^DTP[0-9]{8}(HP|EY|DR|RVT|LLH)[A-Z][0-9]+-[LR]' AND LEFT(LabRefNo, 11) >= " . $db->escape('DTP' . date('Ym', strtotime($winFrom . ' -3 day')) . '00'))->getResultArray() as $pr) {
                if (preg_match('/^(DTP\d{8})(?:HP|EY|DR|RVT|LLH)([A-Z]\d+)-([LR])/i', (string) $pr['LabRefNo'], $pm)) {
                    $pullDone[strtoupper($pm[1] . '|' . $pm[2] . '|' . $pm[3])] = true;
                }
            }
        }
        $trkMarks = $hasTrk ? $db->query("SELECT LabRefNo, Lacking, Shutdown, UPPER(TestType) tt, COALESCE(PullTestType, '') pt, LEFT(CreatedAt, 10) cr, LEFT(CollectionDate, 10) cd
            FROM tbl_dtr_BondingTracker WHERE (Lacking IN (1, 2) OR Shutdown = 1 OR UPPER(TestType) = 'SHUTDOWN')
              AND (LEFT(CreatedAt, 10) BETWEEN {$eWinFrom} AND {$eWinTo} OR LEFT(CollectionDate, 10) BETWEEN {$eCollFrom} AND {$eWinTo})")->getResultArray() : [];
        $wsRows   = $hasWs ? $db->query("SELECT LEFT(CollectionDate,10) d, Status, FinalResult, ProductionDate, TimeCompleted FROM tbl_dtr_WholeshoeTracker WHERE LEFT(CollectionDate,10) BETWEEN {$eWinFrom} AND {$eWinTo}")->getResultArray() : [];
        $shutInfoRows = $db->query('SELECT ' . $dayExpr . ' d, COUNT(*) c FROM tbl_dtr_bonding_info WHERE ' . self::SHUTDOWN_ROW . " AND {$dayExpr} BETWEEN {$eWinFrom} AND {$eWinTo} GROUP BY d")->getResultArray();

        foreach ($buckets as $bk) {
            $bFrom = $bk['from'];
            $bTo   = $bk['to'];
            $cells = [];
            foreach ($sections as $sk => [$title, $labels]) {
                foreach ($labels as $lb) {
                    $cells[$sk][$lb] = ['q' => 0, 'f' => 0, 'nf' => 0, 'prod' => '', 'test' => ''];   // nf = finished but NOT pass (failed / tolerable / rejected)
                }
            }
            foreach ($bondRows as $r) {
                $rd = $effOf((string) $r['TestType'], (string) $r['LabRefNo'], (string) $r['cd'], (string) $r['pd2'], substr(trim((string) $r['TestedDate']), 0, 10));
                if ($rd < $bFrom || $rd > $bTo || $isPull((string) $r['LabRefNo'])) {
                    continue;
                }
                [$sk, $lb] = $classify($r);
                if ($sk === '') {
                    continue;
                }
                $c = &$cells[$sk][$lb];
                $c['q']++;
                $pd = substr((string) $r['ProductionDate'], 0, 10);
                if ($pd > $c['prod']) { $c['prod'] = $pd; }
                $pullKey = preg_match('/^(DTP\d{8})SH([A-Z]\d+)-([LR])/i', (string) $r['LabRefNo'], $bm) ? strtoupper($bm[1] . '|' . $bm[2] . '|' . $bm[3]) : '';
                $resU = strtoupper(trim((string) $r['Result']));
                if (str_starts_with($resU, 'FAIL') || $resU === 'REJECTED') {   // failed only, like the Failed Reports of the Lab Tracker (a tolerable result is not a failure)
                    $c['nf']++;
                }
                if (trim((string) $r['Result']) !== '' || ($pullKey !== '' && isset($pullDone[$pullKey]))) {
                    $c['f']++;
                    $td = substr((string) $r['TestedDate'], 0, 10);
                    if ($td > $c['test']) { $c['test'] = $td; }
                }
                unset($c);
            }
            foreach ($wsRows as $r) {
                $rd = (string) $r['d'];
                if ($rd < $bFrom || $rd > $bTo) {
                    continue;
                }
                $c = &$cells['first']['Whole Shoe'];
                $c['q']++;
                $pd = substr((string) $r['ProductionDate'], 0, 10);
                if ($pd > $c['prod']) { $c['prod'] = $pd; }
                if (trim((string) $r['FinalResult']) !== '' || in_array(strtoupper((string) $r['Status']), ['COMPLETED', 'DONE', 'FINISHED', 'PASSED', 'FAILED'], true)) {
                    $c['f']++;
                    $td = substr((string) $r['TimeCompleted'], 0, 10) ?: $rd;
                    if ($td > $c['test']) { $c['test'] = $td; }
                }
                unset($c);
            }
            $shut = 0;
            foreach ($shutInfoRows as $sr) {
                $rd = (string) $sr['d'];
                if ($rd >= $bFrom && $rd <= $bTo) {
                    $shut += (int) $sr['c'];
                }
            }
            foreach ($trkMarks as $mk) {
                $cr  = (string) $mk['cr'];
                $sh  = $mk['cd'] !== '' ? date('Y-m-d', strtotime($mk['cd'] . ((int) date('N', strtotime($mk['cd'])) === 6 ? ' +2 day' : ' +1 day'))) : '';
                if (! (($cr >= $bFrom && $cr <= $bTo) || ($sh >= $bFrom && $sh <= $bTo))) {
                    continue;
                }
                $noPull = trim((string) $mk['pt']) === '';
                if ((int) $mk['Lacking'] === 1 && ! ($sh >= $bFrom && $sh <= $bTo)) {
                    continue;   // a LACKING line belongs to the testing day it is due (next working day), not the day it was marked
                }
                if ((int) $mk['Lacking'] === 2 && ! ($cr >= $bFrom && $cr <= $bTo) && isset($resByRef[strtoupper((string) $mk['LabRefNo'])])) {
                    continue;   // an Advance shoe already tested is in the Daily record of its day, no longer an Advance of a later day (same rule as the Lab Tracker)
                }
                if ((int) $mk['Shutdown'] === 1 || $mk['tt'] === 'SHUTDOWN') {
                    $shut++;
                } elseif ((int) $mk['Lacking'] === 2 && $noPull) {
                    $c = &$cells['daily']['Daily Bonding Shoe (Advance)'];
                    $c['q']++;
                    if (isset($resByRef[strtoupper((string) $mk['LabRefNo'])])) { $c['f']++; }
                    unset($c);
                } elseif ((int) $mk['Lacking'] === 1 && ($mk['tt'] === 'LACKING' || $noPull)) {
                    $cells['daily']['Daily Bonding Shoe (Lacking)']['q']++;
                }
            }
            $tot = ['q' => 0, 'f' => 0];
            foreach ($cells as $sec) { foreach ($sec as $c) { $tot['q'] += $c['q']; $tot['f'] += $c['f']; } }
            $days[] = ['date' => $bk['date'], 'from' => $bFrom, 'to' => $bTo, 'label' => $bk['label'], 'short' => $bk['short'], 'cells' => $cells, 'total' => $tot, 'shut' => $shut];
        }

        // ---- C. test reports encoded in the selected period from tbl_dtr_unified_labrefs: ETRF / INTERLINK / ERP ----
        $eEncFrom = $db->escape($encFrom);
        $eEncTo   = $db->escape($encTo);
        $cat = [
            'Bonding'    => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
            'Pull Test'  => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
            'Re-Test'    => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
            'Whole Shoe' => ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
        ];
        $materials = ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0];
        if ($db->tableExists('tbl_dtr_unified_labrefs')) {
            \App\Libraries\ErpMonitor::ensureColumns();
            $uDateExpr = "COALESCE(
                CASE WHEN lab_ref_no REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(lab_ref_no, 4, 4), '-', SUBSTR(lab_ref_no, 8, 2), '-', SUBSTR(lab_ref_no, 10, 2)) END,
                CASE WHEN received_date REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(received_date, 10) END,
                CASE WHEN received_date REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(received_date, 7, 4), '-', SUBSTR(received_date, 1, 2), '-', SUBSTR(received_date, 4, 2)) END,
                DATE_FORMAT(STR_TO_DATE(received_date, '%d %b %Y'), '%Y-%m-%d'),
                CASE WHEN tested_date REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(tested_date, 10) END,
                CASE WHEN tested_date REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(tested_date, 7, 4), '-', SUBSTR(tested_date, 1, 2), '-', SUBSTR(tested_date, 4, 2)) END,
                DATE_FORMAT(STR_TO_DATE(tested_date, '%d %b %Y'), '%Y-%m-%d'),
                ''
            )";
            $uEncRows = $db->query("SELECT lab_ref_no, tr_no, form_slug, form_category, status, overall_result,
                etrf_mat_app, etrf_mat_hist, etrf_prod_app, etrf_prod_hist, etrf_pull_app, etrf_pull_hist,
                etrf_bonding_app, etrf_bonding_hist, etrf_whole_app, etrf_whole_hist, etrf_adult_app, etrf_adult_hist,
                erp_status, interlink_flag
                FROM tbl_dtr_unified_labrefs
                WHERE {$uDateExpr} BETWEEN {$eEncFrom} AND {$eEncTo}")->getResultArray();
            $extraPullEtrf = 0;
            $extraPullIl   = 0;
            $extraPullErp  = 0;
            foreach ($uEncRows as $r) {
                $slug = strtolower(trim((string) $r['form_slug']));
                $lab  = trim((string) $r['lab_ref_no']);
                $fcat = strtoupper((string) $r['form_category']);
                $st   = strtoupper(trim((string) $r['status']));
                if ($slug === 'pulltest' || ($slug === 'bonding' && $isPull($lab))) {
                    $k = 'Pull Test';
                } elseif ($slug === 'bonding' && (preg_match('/-R\d+$/i', $lab) || str_contains($fcat, 'RETEST') || str_contains($st, 'RETEST'))) {
                    $k = 'Re-Test';
                } elseif ($slug === 'bonding') {
                    $k = 'Bonding';
                } elseif (in_array($slug, ['wholeshoe', 'wst'], true)) {
                    $k = 'Whole Shoe';
                } else {
                    $k = 'Materials';
                }
                $trNo = trim((string) $r['tr_no']);
                $isEtrf = match ($k) {
                    'Pull Test'  => $trNo !== '' || trim((string) $r['etrf_pull_app']) !== '' || trim((string) $r['etrf_pull_hist']) !== '',
                    'Bonding', 'Re-Test' => $trNo !== '' || trim((string) $r['etrf_bonding_app']) !== '' || trim((string) $r['etrf_bonding_hist']) !== '' || trim((string) $r['etrf_prod_app']) !== '' || trim((string) $r['etrf_adult_app']) !== '',
                    'Whole Shoe' => $trNo !== '' || trim((string) $r['etrf_whole_app']) !== '' || trim((string) $r['etrf_whole_hist']) !== '' || trim((string) $r['etrf_prod_app']) !== '' || trim((string) $r['etrf_adult_app']) !== '',
                    default      => $trNo !== '' || trim((string) $r['etrf_mat_app']) !== '' || trim((string) $r['etrf_mat_hist']) !== '',
                };
                $hasRes = trim((string) $r['overall_result']) !== '' || in_array($st, ['COMPLETE', 'COMPLETED', 'DONE', 'PASS', 'PASSED', 'FAIL', 'FAILED'], true);
                $isIl   = trim((string) $r['interlink_flag']) !== '' || (($k === 'Materials' || $k === 'Whole Shoe') && $isEtrf && $hasRes);
                $isErp  = in_array(strtoupper(trim((string) $r['erp_status'])), ['OK', 'SUBMITTED', 'DONE', '1', 'COMPLETED'], true);
                if ($k === 'Materials') {
                    $materials['q']++;
                    if ($isEtrf) { $materials['etrf']++; }
                    if ($isIl) { $materials['il']++; }
                    if ($isErp) { $materials['erp']++; }
                } else {
                    $cat[$k]['q']++;
                    if ($isEtrf) { $cat[$k]['etrf']++; }
                    if ($isIl) { $cat[$k]['il']++; }
                    if ($isErp) { $cat[$k]['erp']++; }
                }
                // Bonding rows that also carry a Pull Test TR in etrf_pull_app/etrf_pull_hist
                if ($k === 'Bonding' && (trim((string) $r['etrf_pull_app']) !== '' || trim((string) $r['etrf_pull_hist']) !== '')) {
                    $extraPullEtrf++;
                    if ($isIl) { $extraPullIl++; }
                    if ($isErp) { $extraPullErp++; }
                }
            }
            if ($extraPullEtrf > 0) {
                if ($cat['Pull Test']['q'] < $cat['Pull Test']['etrf'] + $extraPullEtrf) {
                    $cat['Pull Test']['q'] = $cat['Pull Test']['etrf'] + $extraPullEtrf;
                }
                $cat['Pull Test']['etrf'] = min($cat['Pull Test']['q'], $cat['Pull Test']['etrf'] + $extraPullEtrf);
                $cat['Pull Test']['il']   = min($cat['Pull Test']['q'], $cat['Pull Test']['il'] + $extraPullIl);
                $cat['Pull Test']['erp']  = min($cat['Pull Test']['q'], $cat['Pull Test']['erp'] + $extraPullErp);
            }
        }

        return ['dw' => ['year' => $year, 'asof' => $asof, 'period' => $period, 'week' => $week, 'month' => $month, 'winFrom' => $winFrom, 'winTo' => $winTo, 'encFrom' => $encFrom, 'encTo' => $encTo, 'windowTag' => $windowTag, 'windowShort' => $windowShort, 'windowLabel' => $windowLabel, 'types' => $types, 'months' => $months, 'days' => $days, 'sections' => $sections, 'cat' => $cat, 'materials' => $materials, 'monthName' => $periodTitle],
            'kpis' => [], 'chartTitle' => '', 'months' => [], 'lists' => []];
    }
}
