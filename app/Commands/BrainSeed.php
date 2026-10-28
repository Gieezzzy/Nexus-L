<?php

namespace App\Commands;

use App\Libraries\Brain;
use App\Libraries\BrainSeed as Seed;
use App\Libraries\BrainSeed2 as Seed2;
use App\Libraries\BrainSeed3 as Seed3;
use App\Libraries\BrainSeed4 as Seed4;
use App\Libraries\BrainSeed5 as Seed5;
use App\Libraries\BrainSeed6 as Seed6;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark brain:seed  - loads / refreshes the built-in knowledge (matched by title; learned answers are never touched). */
class BrainSeed extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:seed';
    protected $description = 'Loads the built-in help-bot knowledge into the Brain.';

    public function run(array $params)
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $add = 0;
        $upd = 0;
        foreach (array_merge(Seed::all(), Seed2::all(), Seed3::all(), Seed4::all(), Seed5::all(), Seed6::all()) as $e) {
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
        // answers about the old shared server-side InterLink session are retired
        $db->table('tbl_bot_knowledge')->where('source', 'seed')->whereIn('title', array_merge(Seed5::RETIRE, Seed6::RETIRE))->update(['status' => 'retired', 'updated_at' => $now]);
        CLI::write("Knowledge: {$add} added, {$upd} refreshed.");
        $f = Brain::flush();
        CLI::write('Exported: ' . json_encode($f) . ', playbooks: ' . Brain::exportMarkdown());
    }
}
