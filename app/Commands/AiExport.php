<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark ai:export [path]` : writes the approved answers of the assistant to a JSON-lines file that the AI service
 * (ai-service/) indexes. Nothing leaves this computer. Run it after answers are added or changed (the nightly job does).
 */
class AiExport extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'ai:export';
    protected $description = 'Exports the approved assistant answers for the AI service.';
    protected $usage       = 'ai:export [path]';

    public function run(array $params)
    {
        $path = $params[0] ?? (getenv('AI_KNOWLEDGE_FILE') ?: 'C:\\NexusAI\\knowledge.jsonl');
        $dir  = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            CLI::error("Cannot create {$dir}");

            return;
        }
        $rows = \Config\Database::connect()->table('tbl_bot_knowledge')->where('status', 'approved')->orderBy('id')->get()->getResultArray();
        $tmp  = $path . '.tmp';
        $fh   = fopen($tmp, 'wb');
        foreach ($rows as $r) {
            fwrite($fh, json_encode([
                'id' => (int) $r['id'], 'title' => $r['title'], 'keywords' => (string) $r['keywords'], 'body' => (string) $r['body'],
                'steps' => json_decode((string) $r['steps_json'], true) ?: [], 'page_match' => $r['page_match'], 'role_min' => $r['role_min'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        fclose($fh);
        rename($tmp, $path);
        CLI::write(count($rows) . " approved answers written to {$path}");
    }
}
