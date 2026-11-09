<?php

namespace App\Commands;

use App\Libraries\Brain;
use App\Libraries\BrainLearn;
use App\Libraries\BrainSeed10;
use App\Libraries\BrainSeed11;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Trains the AI Brain on the newest functions and views (BrainSeed10 + BrainSeed11: InterLink eTRF page, Order Summary, pick lists,
 * service address, accounts, installer, Report Submission grid, collapsed sidebar, AI service auto start).
 * Every keyword phrase of those playbooks is turned into question variants (different openings and endings), and each variant is
 * learned over several epochs. Run `php spark brain:seed` first so the playbooks exist, then `php spark brain:train` to re-index.
 */
class TrainNewFeatures extends BaseCommand
{
    protected $group       = 'Brain';
    protected $name        = 'brain:train-new';
    protected $description = 'Trains the AI Brain with 1,000+ question variants on the newest functions and views (eTRF page, Order Summary, pick lists, service, layout).';
    protected $usage       = 'brain:train-new [epochs]';

    public function run(array $params)
    {
        $epochs = max(1, min(30, (int) ($params[0] ?? 10)));
        $db     = \Config\Database::connect();
        CLI::write('=== Training on the newest functions and views ===', 'cyan');

        $prefixes = ['', 'how do i ', 'where is the ', 'what is ', 'how to ', 'help with ', 'i need ', 'why is ', 'can i ', 'tell me about ', 'explain ', 'nexus '];
        $suffixes = ['', ' in nexus-l', ' please', ' help'];

        $dataset = [];
        $missing = 0;
        foreach (array_merge(BrainSeed10::all(), BrainSeed11::all()) as $e) {
            $row = $db->table('tbl_bot_knowledge')->where('title', mb_substr($e['title'], 0, 200))->where('status', 'approved')->get()->getRowArray();
            if (! $row) {
                $missing++;
                continue;
            }
            $phrases = array_values(array_filter(array_map('trim', explode(',', (string) ($e['keywords'] ?? '')))));
            $phrases[] = mb_strtolower(rtrim($e['title'], '?'));
            foreach ($phrases as $i => $ph) {
                for ($j = 0; $j < 6; $j++) {                            // six different openings / endings per phrase, spread evenly
                    $n = ($i * 7 + $j * 5 + (int) $row['id']) % (count($prefixes) * count($suffixes));
                    $dataset[] = ['q' => trim($prefixes[intdiv($n, count($suffixes))] . $ph . $suffixes[$n % count($suffixes)]), 'id' => (int) $row['id']];
                }
            }
        }
        if ($missing) {
            CLI::write("   {$missing} playbook(s) are not in the knowledge base yet: run 'php spark brain:seed' first.", 'yellow');
        }
        $unique = [];
        foreach ($dataset as $d) {
            $unique[mb_strtolower($d['q'])] = $d;
        }
        $dataset = array_values($unique);
        CLI::write('Prepared ' . count($dataset) . ' unique question variants over ' . count(array_unique(array_column($dataset, 'id'))) . ' playbooks.', 'green');

        $updates = 0;
        for ($epoch = 1; $epoch <= $epochs; $epoch++) {
            foreach ($dataset as $d) {
                $terms = Brain::tokens($d['q']);
                if (! $terms) {
                    continue;
                }
                if (BrainLearn::train($terms, $d['id'], 1) !== null) {
                    $updates += count($terms) + 1;
                }
            }
            CLI::write("   epoch {$epoch}/{$epochs} done", 'light_gray');
        }
        CLI::write('Completed: ' . count($dataset) . " questions x {$epochs} epochs, {$updates} weight updates.", 'green');
    }
}
