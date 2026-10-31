<?php

namespace App\Commands;

use App\Libraries\AiExporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark ai:export [path]` : writes the approved answers of the assistant (and the real questions for the review task) to JSON-lines
 * files that the AI service (ai-service/) reads. Nothing leaves this computer. Run it after answers are added or changed.
 */
class AiExport extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'ai:export';
    protected $description = 'Exports the approved assistant answers and real questions for the AI service.';
    protected $usage       = 'ai:export [path]';

    public function run(array $params)
    {
        try {
            $r = AiExporter::run($params[0] ?? null);
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());

            return;
        }
        CLI::write("{$r['answers']} approved answers and {$r['questions']} real questions written to {$r['dir']}");
    }
}
