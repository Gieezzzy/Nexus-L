<?php

namespace App\Commands;

use App\Libraries\Brain;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark brain:ask "question" [--admin]      one question
 * php spark brain:ask --file=path.tsv [--admin] test file: question<TAB>expected title (part). Prints the accuracy.
 */
class BrainAsk extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:ask';
    protected $description = 'Tests the help bot search from the command line.';

    public function run(array $params)
    {
        $role = 'admin';
        $file = $params[0] ?? null;
        if ($file && is_file($file)) {
            $ok = 0;
            $n  = 0;
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                [$q, $want] = array_pad(explode("\t", $line, 2), 2, '');
                $hits = Brain::search($q, ['role' => $role], 3);
                $conf = Brain::confidence($hits, $q);
                $top  = $hits[0]['row']['title'] ?? '(none)';
                $good = $want !== '' ? stripos($top, $want) !== false : $conf < 22;
                $n++;
                $ok += $good ? 1 : 0;
                CLI::write(($good ? 'OK   ' : 'MISS ') . sprintf('%3d%% ', $conf) . $q . '  ->  ' . mb_substr($top, 0, 60), $good ? 'green' : 'red');
            }
            CLI::write("Accuracy: {$ok}/{$n} = " . round($ok * 100 / max(1, $n)) . '%');

            return;
        }
        $q    = implode(' ', $params);
        $hits = Brain::search($q, ['role' => $role], 5);
        CLI::write('confidence ' . Brain::confidence($hits, $q) . '%');
        foreach ($hits as $h) {
            CLI::write(sprintf('%6.2f  %s', $h['score'], $h['row']['title']));
        }
    }
}
