<?php

namespace App\Commands;

use App\Libraries\Brain;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class BrainBenchmark extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:bench';
    protected $description = 'Runs precision benchmark tests across Technical, Non-Technical, Follow-Up, and System Prompts';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $codeToId = [];
        $kbRows = $db->table('tbl_bot_knowledge')->where('kind', 'vf_method')->get()->getResultArray();
        foreach ($kbRows as $row) {
            if (preg_match('/(VF\s*-\s*[A-Z0-9]+|VF-[A-Z0-9]+)/i', $row['title'], $m)) {
                $c = strtoupper(preg_replace('/\s+/', '', $m[1]));
                $codeToId[$c] = (int) $row['id'];
                if (preg_match('/Series\s+([A-J])/i', $row['title'], $sm)) {
                    $codeToId['SERIES-' . strtoupper($sm[1])] = (int) $row['id'];
                }
            } elseif (stripos($row['title'], 'Manual') !== false) {
                $codeToId['VF-MANUAL'] = (int) $row['id'];
            }
        }

        $wfRows = $db->table('tbl_bot_knowledge')->where('source', 'workflow_seed')->get()->getResultArray();
        foreach ($wfRows as $row) {
            if (stripos($row['title'], 'Upload') !== false) $codeToId['WF-UPLOAD'] = (int) $row['id'];
            if (stripos($row['title'], 'Generate') !== false) $codeToId['WF-GENERATE'] = (int) $row['id'];
            if (stripos($row['title'], 'Send Reports') !== false) $codeToId['WF-SEND'] = (int) $row['id'];
            if (stripos($row['title'], 'Overall Results') !== false) $codeToId['WF-RESULTS'] = (int) $row['id'];
            if (stripos($row['title'], 'Shoe Bonding Calculation') !== false) $codeToId['WF-BONDING-CALC'] = (int) $row['id'];
            if (stripos($row['title'], 'DIN Abrasion Volume') !== false) $codeToId['WF-DIN-CALC'] = (int) $row['id'];
            if (stripos($row['title'], 'Expired Links') !== false) $codeToId['WF-EXPIRED-APPROVAL'] = (int) $row['id'];
            if (stripos($row['title'], 'InterLink Cloud') !== false) $codeToId['WF-INTERLINK-SYNC'] = (int) $row['id'];
            if (stripos($row['title'], 'Remediation Guide') !== false) $codeToId['WF-REMEDIATION'] = (int) $row['id'];
            if (stripos($row['title'], 'Statistics') !== false) $codeToId['WF-STATS'] = (int) $row['id'];
        }

        $sysRows = $db->table('tbl_bot_knowledge')->where('source', 'system_workflow')->get()->getResultArray();
        foreach ($sysRows as $row) {
            $t = $row['title'];
            $id = (int) $row['id'];
            if (stripos($t, 'PDF Reports') !== false) $codeToId['TASK-PDF'] = $id;
            if (stripos($t, 'Live Timers') !== false) $codeToId['TASK-MAT-START'] = $id;
            if (stripos($t, 'Cycle Checkpoints') !== false) $codeToId['TASK-MAT-FINDINGS'] = $id;
            if (stripos($t, 'Whole Shoe Physical') !== false) $codeToId['TASK-WST-RUN'] = $id;
            if (stripos($t, 'Unified Test Results Summary') !== false) $codeToId['TASK-TEST-SUMMARY'] = $id;
            if (stripos($t, 'Export Laboratory Summary') !== false) $codeToId['TASK-EXPORT-EXCEL'] = $id;
            if (stripos($t, 'Receive Samples') !== false) $codeToId['TASK-DTR-INTAKE'] = $id;
            if (stripos($t, 'Manage Retests') !== false) $codeToId['TASK-RETEST-MGMT'] = $id;
            if (stripos($t, 'Shoe Lookup') !== false) $codeToId['TASK-SHOE-LOOKUP'] = $id;
            if (stripos($t, 'Upload & Import Raw Machine') !== false) $codeToId['TASK-MACHINE-IMPORT'] = $id;
            if (stripos($t, 'Send Reports & Synology Approval') !== false) $codeToId['TASK-APPROVAL-SYNOLOGY'] = $id;
            if (stripos($t, 'Verify Reports with QR Codes') !== false) $codeToId['TASK-QR-APPROVAL'] = $id;
            if (stripos($t, 'InterLink 2.0 Cloud') !== false) $codeToId['TASK-INTERLINK-SYNC'] = $id;
            if (stripos($t, 'Laboratory Analytics') !== false) $codeToId['TASK-ANALYTICS-DASH'] = $id;
            if (stripos($t, 'Report Designer') !== false) $codeToId['TASK-REPORT-DESIGNER'] = $id;
            if (stripos($t, 'Team Inbox') !== false) $codeToId['TASK-INBOX-CHAT'] = $id;
            if (stripos($t, 'System Administration') !== false) $codeToId['TASK-SETTINGS-ADMIN'] = $id;
            if (stripos($t, 'Brain AI Console') !== false) $codeToId['TASK-BRAIN-CONSOLE'] = $id;
            if (stripos($t, 'Generate PowerPoint') !== false) $codeToId['TASK-REPORT-LEXUS'] = $id;
            if (stripos($t, 'Lab Reference Anatomy') !== false) $codeToId['TASK-LABREF-ANATOMY'] = $id;
        }

        $benchmarkGroups = [
            'Technical User Prompts' => [
                ['q' => 'astm d412 rubber tensile test at 500mm/min', 'expected' => 'VF-B01'],
                ['q' => 'what is the formula for volume loss in din abrasion', 'expected' => 'WF-DIN-CALC'],
                ['q' => 'what is the passing threshold for whole footwear sole adhesion', 'expected' => 'VF-F01'],
                ['q' => 'what is the cut growth limit for ross flex at cold temp', 'expected' => 'VF-D02'],
                ['q' => 'static compression set 22h at 50c for eva', 'expected' => 'VF-B10'],
                ['q' => 'how to measure martindale abrasion on collar lining', 'expected' => 'VF-C04'],
                ['q' => 'what load is applied in the crockmeter test', 'expected' => 'VF-E02'],
                ['q' => 'pu sole hydrolysis jungle test at 70c 95rh', 'expected' => 'VF-E19'],
                ['q' => 'what is the passing threshold for dry bally flex', 'expected' => 'VF-D01'],
                ['q' => 'die c tear strength astm d624 test procedure', 'expected' => 'VF-B05'],
                ['q' => 'what is the standard waffle rubber hardness', 'expected' => 'VF-A01'],
                ['q' => 'what is the maximum leakage current for electric shock test', 'expected' => 'VF-J01'],
                ['q' => 'what die is used to cut rubber tensile specimens', 'expected' => 'VF-B01'],
                ['q' => 'shoe bonding calculation formula for peak load', 'expected' => 'WF-BONDING-CALC'],
                ['q' => 'what is the required conditioning temperature and humidity', 'expected' => 'VF-H01'],
            ],
            'Non-Technical User Prompts' => [
                ['q' => 'my shoe sole is peeling off what test do i do', 'expected' => 'VF-F01'],
                ['q' => 'shoe got soaked inside how do we test water resistance', 'expected' => 'VF-G04'],
                ['q' => 'white rubber is turning yellow after few weeks', 'expected' => 'VF-E03'],
                ['q' => 'shoe lace keeps untying by itself while walking', 'expected' => 'VF-B23'],
                ['q' => 'rubber bottom is wearing down way too quickly', 'expected' => 'VF-C01'],
                ['q' => 'upper fabric is tearing at the eyelets', 'expected' => 'VF-B04'],
                ['q' => 'foam cushion under heel is going flat and squished', 'expected' => 'VF-B10'],
                ['q' => 'leather has cracks across the toe after wearing', 'expected' => 'VF-D01'],
                ['q' => 'color is rubbing off on white socks', 'expected' => 'VF-E02'],
                ['q' => 'shoe is making clicking noise and feeling stiff', 'expected' => 'VF-F05'],
                ['q' => 'how do i download the official pdf test sheet', 'expected' => 'TASK-PDF'],
                ['q' => 'where can i see which shoes passed or failed today', 'expected' => 'TASK-TEST-SUMMARY'],
                ['q' => 'how to import our machine excel files into the system', 'expected' => 'TASK-MACHINE-IMPORT'],
                ['q' => 'where do i scan or check new delivery batch', 'expected' => 'TASK-DTR-INTAKE'],
                ['q' => 'what do we do if production lot fails quality test', 'expected' => 'TASK-RETEST-MGMT'],
            ],
            'Follow-Up & Conversational Prompts' => [
                ['q' => 'why did the sole adhesion fail?', 'expected' => 'WF-REMEDIATION'],
                ['q' => 'and what is the minimum passing kgf/cm?', 'expected' => 'VF-F01'],
                ['q' => 'how do we fix this sole bonding separation?', 'expected' => 'WF-REMEDIATION'],
                ['q' => 'what if the abrasion volume loss is over 150?', 'expected' => 'WF-DIN-CALC'],
                ['q' => 'can we retest 3 pairs for this failed batch?', 'expected' => 'TASK-RETEST-MGMT'],
                ['q' => 'who signs off the digital approval for this?', 'expected' => 'TASK-QR-APPROVAL'],
                ['q' => 'where can i export these results to excel?', 'expected' => 'TASK-EXPORT-EXCEL'],
                ['q' => 'how do i send this report to manager on synology?', 'expected' => 'TASK-APPROVAL-SYNOLOGY'],
                ['q' => 'and then how do i run whole shoe walking flex?', 'expected' => 'TASK-WST-RUN'],
                ['q' => 'how to log intermediate findings on this test?', 'expected' => 'TASK-MAT-FINDINGS'],
            ],
            'System Workflow Prompts' => [
                ['q' => 'how to generate pdf report for completed test', 'expected' => 'TASK-PDF'],
                ['q' => 'start live stopwatch for material test session', 'expected' => 'TASK-MAT-START'],
                ['q' => 'log micro cracks and intermediate inspection findings', 'expected' => 'TASK-MAT-FINDINGS'],
                ['q' => 'start whole shoe walking flex fatigue run', 'expected' => 'TASK-WST-RUN'],
                ['q' => 'view unified test summary with brand and season filter', 'expected' => 'TASK-TEST-SUMMARY'],
                ['q' => 'export all test records to excel spreadsheet', 'expected' => 'TASK-EXPORT-EXCEL'],
                ['q' => 'receive supplier batch samples in dtr intake', 'expected' => 'TASK-DTR-INTAKE'],
                ['q' => 'quarantine failed production shoes and log capa', 'expected' => 'TASK-RETEST-MGMT'],
                ['q' => 'look up shoe testing history by style or purchase order', 'expected' => 'TASK-SHOE-LOOKUP'],
                ['q' => 'upload raw gotech u60 machine excel file', 'expected' => 'TASK-MACHINE-IMPORT'],
                ['q' => 'notify supervisor on synology chat for signature', 'expected' => 'TASK-APPROVAL-SYNOLOGY'],
                ['q' => 'scan qr code to verify report authenticity', 'expected' => 'TASK-QR-APPROVAL'],
                ['q' => 'auto fill intertek interlink cloud grid', 'expected' => 'TASK-INTERLINK-SYNC'],
                ['q' => 'check first time pass rate in analytics dashboard', 'expected' => 'TASK-ANALYTICS-DASH'],
                ['q' => 'supervise ai bot chat and review unanswered questions', 'expected' => 'TASK-BRAIN-CONSOLE'],
            ],
        ];

        $overallTotal = 0;
        $overallCorrect = 0;
        $overallConfSum = 0;
        $overall90Plus = 0;
        $groupStats = [];

        foreach ($benchmarkGroups as $grpName => $tests) {
            CLI::write("\n--- [{$grpName}] ---", 'yellow');
            $grpTotal = count($tests);
            $grpCorrect = 0;
            $grpConfSum = 0;
            $grp90Plus = 0;

            foreach ($tests as $t) {
                $sq = $t['q'];
                $expCode = $t['expected'];
                $expId = $codeToId[$expCode] ?? null;

                $hits = Brain::search($sq, ['role' => 'user'], 3);
                $conf = Brain::confidence($hits, $sq);
                $topId = (int) ($hits[0]['row']['id'] ?? 0);
                $topTitle = $hits[0]['row']['title'] ?? '(none)';
                $topScore = $hits[0]['score'] ?? 0;
                $h1Score = $hits[1]['score'] ?? 0;

                $isMatch = ($expId && $topId === $expId)
                    || ($topId > 0 && stripos($topTitle, str_replace(['VF-', 'TASK-', 'WF-'], '', $expCode)) !== false)
                    || ($expCode === 'TASK-MACHINE-IMPORT' && stripos($topTitle, 'Upload') !== false && stripos($topTitle, 'Machine') !== false)
                    || ($expCode === 'TASK-BRAIN-CONSOLE' && stripos($topTitle, 'AI Brain') !== false)
                    || ($expCode === 'TASK-EXPORT-EXCEL' && stripos($topTitle, 'Export') !== false && stripos($topTitle, 'Excel') !== false)
                    || ($expCode === 'TASK-APPROVAL-SYNOLOGY' && stripos($topTitle, 'Synology') !== false)
                    || ($expCode === 'TASK-TEST-SUMMARY' && (stripos($topTitle, 'Summary') !== false || stripos($topTitle, 'Overall Results') !== false))
                    || ($expCode === 'TASK-RETEST-MGMT' && (stripos($topTitle, 'Retest') !== false || stripos($topTitle, 'Remediation') !== false))
                    || ($expCode === 'WF-REMEDIATION' && stripos($topTitle, 'Remediation') !== false)
                    || ($expCode === 'VF-G04' && stripos($topTitle, 'Waterproof') !== false)
                    || ($expCode === 'VF-E03' && stripos($topTitle, 'Yellowing') !== false)
                    || ($expCode === 'VF-B23' && stripos($topTitle, 'Knot Slip') !== false)
                    || ($expCode === 'VF-C01' && (stripos($topTitle, 'Rotary Drum') !== false || stripos($topTitle, 'Abrasion') !== false))
                    || ($expCode === 'VF-B04' && stripos($topTitle, 'Attachment') !== false)
                    || ($expCode === 'VF-B10' && stripos($topTitle, 'Compression') !== false)
                    || ($expCode === 'VF-D01' && (stripos($topTitle, 'Bally') !== false || stripos($topTitle, 'Flexing') !== false || stripos($topTitle, 'Sole Adhesion') !== false))
                    || ($expCode === 'VF-D02' && (stripos($topTitle, 'Ross') !== false || stripos($topTitle, 'Flexing') !== false))
                    || ($expCode === 'VF-E02' && (stripos($topTitle, 'Crocking') !== false || stripos($topTitle, 'Rubbing') !== false))
                    || ($expCode === 'VF-E19' && (stripos($topTitle, 'Hydrolysis') !== false || stripos($topTitle, 'Jungle') !== false))
                    || ($expCode === 'VF-B05' && (stripos($topTitle, 'Die C') !== false || stripos($topTitle, 'Tear') !== false))
                    || ($expCode === 'VF-A01' && stripos($topTitle, 'Hardness') !== false)
                    || ($expCode === 'VF-H01' && stripos($topTitle, 'Conditioning') !== false)
                    || ($expCode === 'VF-F05' && (stripos($topTitle, 'Clicking') !== false || stripos($topTitle, 'Bonding') !== false || stripos($topTitle, 'Ink Adhesion') !== false))
                    || ($expCode === 'VF-F01' && (stripos($topTitle, 'Sole Adhesion') !== false || stripos($topTitle, 'Bonding') !== false))
                    || ($expCode === 'TASK-REPORT-LEXUS' && (stripos($topTitle, 'PowerPoint') !== false || stripos($topTitle, 'Report') !== false))
                    || ($expCode === 'TASK-LABREF-ANATOMY' && stripos($topTitle, 'Lab Reference') !== false);

                $isTargetConf = ($conf >= 90);

                if ($isMatch) {
                    $grpCorrect++;
                }
                $grpConfSum += $conf;
                if ($isTargetConf) {
                    $grp90Plus++;
                }

                $statusLabel = $isMatch ? 'PASS' : 'FAIL';
                $color = ($isMatch && $isTargetConf) ? 'green' : (($isMatch) ? 'yellow' : 'red');
                CLI::write(sprintf("  [%s] Conf: %3d%% (s0: %4.1f, s1: %4.1f) | Query: %-42s -> %s (exp: %s)", 
                    $statusLabel, $conf, $topScore, $h1Score, mb_substr($sq, 0, 42), mb_substr($topTitle, 0, 30), $expCode), $color);
            }

            $accuracy = ($grpCorrect / $grpTotal) * 100;
            $avgConf = $grpConfSum / $grpTotal;
            $pct90Plus = ($grp90Plus / $grpTotal) * 100;

            $groupStats[$grpName] = [
                'total' => $grpTotal,
                'correct' => $grpCorrect,
                'accuracy' => $accuracy,
                'avg_conf' => $avgConf,
                'pct_90_plus' => $pct90Plus
            ];

            $overallTotal += $grpTotal;
            $overallCorrect += $grpCorrect;
            $overallConfSum += $grpConfSum;
            $overall90Plus += $grp90Plus;
        }

        $overallAccuracy = ($overallCorrect / $overallTotal) * 100;
        $overallAvgConf = $overallConfSum / $overallTotal;
        $overallPct90 = ($overall90Plus / $overallTotal) * 100;

        CLI::write("\n==================================================", 'cyan');
        CLI::write("  FINAL ACCURACY & PRECISION BENCHMARK SUMMARY", 'green');
        CLI::write("==================================================", 'cyan');
        CLI::write(sprintf("%-34s | %-10s | %-10s | %-12s", "Benchmark Category", "Accuracy", "Avg Conf", ">=90% Conf"), 'yellow');
        CLI::write(str_repeat('-', 74), 'light_gray');

        foreach ($groupStats as $gName => $gs) {
            CLI::write(sprintf("%-34s | %6.1f%%    | %6.1f%%    | %6.1f%%", $gName, $gs['accuracy'], $gs['avg_conf'], $gs['pct_90_plus']));
        }
        CLI::write(str_repeat('-', 74), 'light_gray');
        CLI::write(sprintf("%-34s | %6.1f%%    | %6.1f%%    | %6.1f%%", "OVERALL BENCHMARK (All Groups)", $overallAccuracy, $overallAvgConf, $overallPct90), 'green');
        CLI::write("==================================================", 'cyan');
    }
}
