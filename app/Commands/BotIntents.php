<?php

namespace App\Commands;

use App\Libraries\BotApprovals;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark bot:intents path.tsv
 *
 * Checks the approval conversation of the help bot without sending anything. One case per line, tab separated:
 *   kind <TAB> user message <TAB> last bot message (may be empty; use \n for a line break) <TAB> expected
 *
 *   kind     list     BotApprovals::listIntent answers (expected: part of the reply text, or "none" when it must not answer)
 *            resolve  which report a resend request means (expected: LAB:<ref>, "ASK" or "NONE")
 *            howto    is it a question about the procedure, which must never send (expected: yes / no)
 *            email    BotApprovals::emailIntent answers (expected: yes / no)
 *
 * Prints every miss and the accuracy per kind. The list cases read the approval table; nothing is written.
 */
class BotIntents extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'bot:intents';
    protected $description = 'Tests the approval / resend / e-mail understanding of the help bot (sends nothing).';

    public function run(array $params)
    {
        $file = $params[0] ?? '';
        if (! is_file($file)) {
            CLI::error('Give a test file: php spark bot:intents path.tsv');
            return;
        }
        $db   = \Config\Database::connect();
        $stat = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$kind, $text, $last, $want] = array_pad(explode("\t", $line, 4), 4, '');
            $last = str_replace('\n', "\n", $last);
            $got  = '';
            switch ($kind) {
                case 'list':
                    $r   = BotApprovals::listIntent($text, 1, $db);
                    $got = $r === null ? 'none' : (string) $r['text'];
                    $ok  = $want === 'none' ? $r === null : ($r !== null && stripos($got, $want) !== false);
                    break;
                case 'resolve':
                    $r   = BotApprovals::resolveTarget($text, $last);
                    $got = $r['ambiguous'] ? 'ASK' : ($r['lab'] ? 'LAB:' . $r['lab'] : ($r['app'] ? 'APP:' . $r['app'] : 'NONE'));
                    $ok  = $got === $want;
                    break;
                case 'howto':
                    $got = BotApprovals::isHowTo(mb_strtolower($text)) ? 'yes' : 'no';
                    $ok  = $got === $want;
                    break;
                case 'email':
                    $got = BotApprovals::emailIntent($text) !== null ? 'yes' : 'no';
                    $ok  = $got === $want;
                    break;
                default:
                    continue 2;
            }
            $stat[$kind][0] = ($stat[$kind][0] ?? 0) + ($ok ? 1 : 0);
            $stat[$kind][1] = ($stat[$kind][1] ?? 0) + 1;
            if (! $ok) {
                CLI::write("MISS {$kind}: {$text}  (want {$want}, got " . mb_substr(str_replace("\n", ' | ', $got), 0, 100) . ')', 'red');
            }
        }
        $all = [0, 0];
        foreach ($stat as $k => [$ok, $n]) {
            CLI::write(sprintf('%-8s %4d / %-4d = %5.1f%%', $k, $ok, $n, $ok * 100 / max(1, $n)));
            $all[0] += $ok;
            $all[1] += $n;
        }
        CLI::write(sprintf('TOTAL    %4d / %-4d = %5.1f%%', $all[0], $all[1], $all[0] * 100 / max(1, $all[1])));
    }
}
