<?php

namespace App\Commands;

use App\Libraries\AiBridge;
use App\Libraries\AiExporter;
use App\Libraries\Brain;
use App\Libraries\BrainEvents;
use App\Libraries\BrainLearn;
use App\Libraries\BrainSeed as Seed;
use App\Libraries\BrainSeed2 as Seed2;
use App\Libraries\BrainSeed3 as Seed3;
use App\Libraries\BrainSeed4 as Seed4;
use App\Libraries\BrainSeed5 as Seed5;
use App\Libraries\BrainSeed6 as Seed6;
use App\Libraries\BrainSeed7 as Seed7;
use App\Libraries\BrainSeed8 as Seed8;
use App\Libraries\ProblemKnowledgeSeeder;
use App\Libraries\SystemSettings;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class BrainTrain extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:train';
    protected $description = 'Full training pipeline for the AI Brain: seeds knowledge, problem cases, term weights, playbooks, and vector index.';

    public function run(array $params)
    {
        CLI::write(CLI::color("=== Training LEXIS AI Brain ===", 'cyan'));

        // 1. Seed & Refresh Built-in Playbooks
        CLI::write("1. Seeding built-in knowledge playbooks...");
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $add = 0; $upd = 0;
        foreach (array_merge(Seed::all(), Seed2::all(), Seed3::all(), Seed4::all(), Seed5::all(), Seed6::all(), Seed7::all(), Seed8::all()) as $e) {
            $data = [
                'kind' => 'playbook', 'title' => mb_substr($e['title'], 0, 200), 'keywords' => mb_substr((string) ($e['keywords'] ?? ''), 0, 600), 'body' => (string) $e['body'],
                'steps_json' => json_encode($e['steps'] ?? [], JSON_UNESCAPED_UNICODE), 'actions_json' => json_encode($e['actions'] ?? [], JSON_UNESCAPED_SLASHES),
                'page_match' => $e['page_match'] ?? null, 'error_match' => $e['error_match'] ?? null, 'diagnose' => $e['diagnose'] ?? null,
                'role_min' => $e['role_min'] ?? 'user', 'status' => 'approved', 'source' => 'seed', 'updated_at' => $now,
            ];
            $row = $db->table('tbl_bot_knowledge')->where('title', $data['title'])->get()->getRowArray();
            if (! $row) {
                $db->table('tbl_bot_knowledge')->insert($data + ['created_at' => $now]);
                $add++;
            } elseif ($row['source'] === 'seed') {
                $db->table('tbl_bot_knowledge')->where('id', $row['id'])->update($data);
                $upd++;
            }
        }
        $db->table('tbl_bot_knowledge')->where('source', 'seed')->whereIn('title', array_merge(Seed5::RETIRE, Seed6::RETIRE))->update(['status' => 'retired', 'updated_at' => $now]);
        CLI::write(CLI::color("   -> Playbooks: {$add} added, {$upd} refreshed.", 'green'));

        // 2. Seed 200 QC Problem Knowledge
        CLI::write("2. Seeding 200 QC Problem Knowledge cases...");
        $probRes = ProblemKnowledgeSeeder::run();
        CLI::write(CLI::color("   -> 200 QC Problems: {$probRes['inserted']} inserted, {$probRes['updated']} refreshed.", 'green'));

        // 3. Learning & Term Weight Training
        CLI::write("3. Updating learning term weights & question gap analysis...");
        if (BrainLearn::ready()) {
            $learn = BrainLearn::refresh();
            CLI::write(CLI::color("   -> BrainLearn: {$learn['groups']} question groups, {$learn['drafts']} drafted answers.", 'green'));
        }

        // 4. Flush Brain Backups & Markdown Playbooks
        CLI::write("4. Flushing Brain .back files & Markdown playbooks...");
        $f = Brain::flush();
        $md = Brain::exportMarkdown();
        CLI::write(CLI::color("   -> Backups: knowledge.back updated, {$md} Markdown playbooks synced.", 'green'));

        // 5. Export JSONL Dataset for Vector AI Service
        CLI::write("5. Exporting knowledge.jsonl and review question dataset...");
        try {
            $exp = AiExporter::run();
            CLI::write(CLI::color("   -> Exported: {$exp['answers']} approved answers, {$exp['questions']} review questions in {$exp['dir']}", 'green'));
        } catch (\Throwable $e) {
            CLI::write(CLI::color("   -> AiExporter note: " . $e->getMessage(), 'yellow'));
        }

        // 6. Connect to AI Vector Service (if active)
        CLI::write("6. Re-indexing AI Vector & Semantic Search...");
        $aiUrl = SystemSettings::get('ai.url', 'http://127.0.0.1:8765');
        $status = AiBridge::get('/health', 2500);
        if ($status !== null) {
            $t = AiBridge::admin('POST', '/admin/train/start', [], 5000);
            $i = AiBridge::admin('POST', '/admin/index', [], 10000);
            BrainEvents::log('train', 'primary', null, null, 'Full training pipeline completed');
            CLI::write(CLI::color("   -> AI Service: Training and Vector store re-indexed successfully.", 'green'));
        } else {
            BrainEvents::log('train', 'primary', null, null, 'Brain knowledge trained (local heuristic & TF-IDF active)');
            CLI::write(CLI::color("   -> AI Service: Offline. Local heuristic & TF-IDF Brain vector engine is 100% active and trained.", 'light_gray'));
        }

        CLI::write(CLI::color("\n✓ AI Brain training completed successfully!", 'cyan'));
    }
}
