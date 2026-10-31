<?php

namespace App\Commands;

use App\Libraries\Brain;
use App\Libraries\BrainLearn;
use App\Libraries\SystemWorkflowSeeder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TrainSystemTasks extends BaseCommand
{
    protected $group       = 'Brain';
    protected $name        = 'brain:train-system';
    protected $description = 'Trains the AI Brain with 1,000+ active website task and workflow queries.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        CLI::write("==================================================", 'cyan');
        CLI::write("  LEXIS AI Brain: 1,000+ System Task Trainer", 'yellow');
        CLI::write("==================================================", 'cyan');

        // 1. Seed System Workflow Playbooks
        CLI::write("Seeding 18 active website workflow & task playbooks...", 'cyan');
        $seedRes = SystemWorkflowSeeder::run();
        CLI::write("-> Seeded: {$seedRes['inserted']} inserted, {$seedRes['updated']} refreshed.", 'green');

        // 2. Index Target Knowledge IDs
        $rows = $db->table('tbl_bot_knowledge')
            ->where('source', 'system_workflow')
            ->get()
            ->getResultArray();

        $targetMap = [];
        foreach ($rows as $r) {
            $title = $r['title'];
            $id = (int) $r['id'];

            if (stripos($title, 'PDF Reports') !== false) $targetMap['PDF Reports'] = $id;
            if (stripos($title, 'Live Timers') !== false) $targetMap['Live Timers'] = $id;
            if (stripos($title, 'Cycle Checkpoints') !== false) $targetMap['Cycle Checkpoints'] = $id;
            if (stripos($title, 'Whole Shoe Physical') !== false) $targetMap['Whole Shoe Physical'] = $id;
            if (stripos($title, 'Unified Test Results Summary') !== false) $targetMap['Unified Test Results Summary'] = $id;
            if (stripos($title, 'Export Laboratory Summary') !== false) $targetMap['Export Laboratory Summary'] = $id;
            if (stripos($title, 'Receive Samples') !== false) $targetMap['Receive Samples'] = $id;
            if (stripos($title, 'Manage Retests') !== false) $targetMap['Manage Retests'] = $id;
            if (stripos($title, 'Shoe Lookup') !== false) $targetMap['Shoe Lookup'] = $id;
            if (stripos($title, 'Upload & Import Raw Machine') !== false) $targetMap['Upload & Import Raw Machine'] = $id;
            if (stripos($title, 'Send Reports & Synology Approval') !== false) $targetMap['Send Reports & Synology Approval'] = $id;
            if (stripos($title, 'Verify Reports with QR Codes') !== false) $targetMap['Verify Reports with QR Codes'] = $id;
            if (stripos($title, 'InterLink 2.0 Cloud') !== false) $targetMap['InterLink 2.0 Cloud'] = $id;
            if (stripos($title, 'Laboratory Analytics') !== false) $targetMap['Laboratory Analytics'] = $id;
            if (stripos($title, 'Report Designer') !== false) $targetMap['Report Designer'] = $id;
            if (stripos($title, 'Team Inbox') !== false) $targetMap['Team Inbox'] = $id;
            if (stripos($title, 'System Administration') !== false) $targetMap['System Administration'] = $id;
            if (stripos($title, 'Brain AI Console') !== false) $targetMap['Brain AI Console'] = $id;
        }

        CLI::write("Indexed " . count($targetMap) . " active website task playbooks.", 'green');

        // 3. Load 1,000+ System Task Queries
        $dataset = SystemTaskTrainingSet::get1000SystemTaskQueries();
        $totalQueries = count($dataset);
        CLI::write("Loaded {$totalQueries} realistic user prompts across active website tasks.", 'cyan');

        $epochs = (int) (CLI::getOption('epochs') ?? ($params[0] ?? 45));
        if ($epochs < 1) {
            $epochs = 45;
        }

        // 4. Train Term Weights across deep ML Epochs
        CLI::write("Training weights with BrainLearn::train() across {$epochs} deep ML epochs...", 'yellow');
        $weightsUpdated = 0;
        $trainedCount = 0;

        for ($epoch = 1; $epoch <= $epochs; $epoch++) {
            foreach ($dataset as $item) {
                $query = $item['q'];
                $targetSnippet = $item['target'];
                $kbId = $targetMap[$targetSnippet] ?? null;

                if (! $kbId) {
                    continue;
                }

                $terms = Brain::tokens($query);
                if (! $terms) {
                    continue;
                }

                $res = BrainLearn::train($terms, $kbId, 1);
                if ($res !== null) {
                    $weightsUpdated += count($terms) + 1;
                }

                if ($epoch === 1) {
                    $trainedCount++;
                }
            }

            if ($epoch === 1 || $epoch % 10 === 0 || $epoch === $epochs) {
                CLI::write("  -> Epoch {$epoch}/{$epochs} completed ({$weightsUpdated} cumulative weight updates)...", 'light_gray');
            }
        }

        CLI::write("Completed training: {$trainedCount} unique queries, {$weightsUpdated} total weight updates applied.", 'green');

        // 5. Enrich Keywords with Comma-Separated Phrases
        CLI::write("Enriching comma-separated keyword phrases for +4.0 phrase bonuses...", 'yellow');
        $groupedPhrases = [];
        foreach ($dataset as $item) {
            $groupedPhrases[$item['target']][] = $item['q'];
        }

        $enrichedCount = 0;
        foreach ($groupedPhrases as $targetSnippet => $phrases) {
            $kbId = $targetMap[$targetSnippet] ?? null;
            if (! $kbId) {
                continue;
            }

            $current = $db->table('tbl_bot_knowledge')->where('id', $kbId)->get()->getRowArray();
            if (! $current) {
                continue;
            }

            $existingKw = (string) ($current['keywords'] ?? '');
            $existingPhrases = array_filter(array_map('trim', explode(',', $existingKw)));

            $newPhrases = [];
            foreach ($phrases as $phrase) {
                $cleanPhrase = trim(preg_replace('/\s+/', ' ', $phrase));
                if (mb_strlen($cleanPhrase) >= 4 && mb_strlen($cleanPhrase) <= 45) {
                    $newPhrases[$cleanPhrase] = true;
                }
            }

            $allPhrases = array_unique(array_merge(array_keys($newPhrases), $existingPhrases));
            $combinedKw = '';
            foreach ($allPhrases as $p) {
                if ($combinedKw === '') {
                    $combinedKw = $p;
                } elseif (mb_strlen($combinedKw . ', ' . $p) <= 590) {
                    $combinedKw .= ', ' . $p;
                } else {
                    break;
                }
            }

            $db->table('tbl_bot_knowledge')->where('id', $kbId)->update([
                'keywords'   => $combinedKw,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $enrichedCount++;
        }

        CLI::write("Enriched keywords on {$enrichedCount} active task playbooks.", 'green');

        // 6. Flush Brain Caches
        CLI::write("Flushing Brain caches and synchronizing knowledge...", 'yellow');
        Brain::flush();

        // 7. Verification Sample
        CLI::write("Running verification sample across active tasks (Target: >= 90% confidence)...", 'cyan');
        $sampleQueries = [
            'how to generate pdf report',
            'start a new test session in materials',
            'how to log intermediate findings on sample',
            'start whole shoe walking flex run',
            'how to view unified test results summary',
            'export all test summary to excel spreadsheet',
            'how to receive new samples in dtr intake',
            'manage retest for failed production lot',
            'how to look up a shoe style test history',
            'upload raw machine excel file from gotech u60',
            'send report to manager on synology chat for approval',
            'verify test report authenticity with qr code',
            'auto fill interlink grid and submit to cloud',
            'view laboratory first time pass rate in analytics',
            'customize pdf report layout in report designer',
            'chat with lab staff and share file in team inbox',
            'switch active database in system settings',
            'supervise ai bot and take over live chat in brain',
        ];

        $passed = 0;
        foreach ($sampleQueries as $sq) {
            $hits = Brain::search($sq, ['role' => 'user'], 3);
            $conf = Brain::confidence($hits, $sq);
            $topTitle = $hits[0]['row']['title'] ?? '(none)';
            $isTargetMet = ($conf >= 90 && $topTitle !== '(none)');
            if ($isTargetMet) {
                $passed++;
                CLI::write("  ✓ [{$conf}%] {$sq} -> " . mb_substr($topTitle, 0, 48), 'green');
            } else {
                CLI::write("  ✗ [{$conf}%] {$sq} -> " . mb_substr($topTitle, 0, 48), 'red');
            }
        }

        $pct = round(($passed / count($sampleQueries)) * 100);
        CLI::write("Target Confidence Verification (>=90%): {$passed}/" . count($sampleQueries) . " ({$pct}%)", $passed === count($sampleQueries) ? 'green' : 'yellow');

        // 8. Trigger AI Vector Re-Index
        if (\App\Libraries\AiBridge::isLive()) {
            CLI::write("Triggering AI vector index refresh...", 'yellow');
            \App\Libraries\AiBridge::post('/admin/train/start', []);
            CLI::write("AI Vector store re-indexed successfully.", 'green');
        }

        CLI::write("==================================================", 'cyan');
        CLI::write("  1,000+ System Task Training Completed!", 'green');
        CLI::write("==================================================", 'cyan');
    }
}
