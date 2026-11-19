<?php

namespace App\Commands;

use App\Libraries\Persona;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark brain:comms-bench   measures how well the assistant understands HOW people talk to it: the intent of a message (question, how-to, status, task,
 * security, complaint, greeting, thanks), its sentiment and urgency, typed or spoken (no punctuation, filler words, Taglish). Prints the accuracy per kind and every miss.
 */
class CommsBench extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:comms-bench';
    protected $description = 'Measures how well the assistant understands intent, sentiment and urgency of messages (typed or spoken).';

    /** [message, intent, sentiment, urgent] */
    public const CASES = [
        ['hello lexus', 'greeting', 'neutral', false], ['good morning', 'greeting', 'neutral', false], ['hi there', 'greeting', 'neutral', false], ['kumusta lexus', 'greeting', 'neutral', false],
        ['thanks a lot', 'thanks', 'positive', false], ['salamat lexus', 'thanks', 'neutral', false], ['thank you so much', 'thanks', 'positive', false],
        ['how do i add a pull test', 'howto', 'neutral', false], ['how to remove the status of a whole shoe test', 'howto', 'neutral', false], ['where is the lab reference status', 'howto', 'neutral', false],
        ['um how do i send a report for approval', 'howto', 'neutral', false], ['paano mag add ng pull test', 'howto', 'neutral', false],
        ['what is the standard for crocking of leather upper', 'question', 'neutral', false], ['what does tolerable mean', 'question', 'neutral', false], ['why is the bonding result tolerable', 'question', 'neutral', false],
        ['is the report approved', 'question', 'neutral', false], ['can i edit a completed test', 'question', 'neutral', false], ['which tests does fabric need', 'question', 'neutral', false],
        ['show me the failed materials', 'status', 'neutral', false], ['how many reports are pending', 'status', 'neutral', false], ['any new lab references today', 'status', 'neutral', false],
        ['list the whole shoe tests that are running', 'status', 'neutral', false], ['what is the status of dtp20261118shb13-l', 'status', 'neutral', false], ['update on my report', 'status', 'neutral', false],
        ['send the report to interlink', 'task', 'neutral', false], ['please open the material testing page', 'task', 'neutral', false], ['can you check the bonding results', 'task', 'neutral', false],
        ['create a new lab reference', 'task', 'neutral', false], ['run the benchmark', 'task', 'neutral', false], ['please train the brain', 'task', 'neutral', false], ['could you move the lacking row to today', 'task', 'neutral', false],
        ['is anyone attacking my network', 'security', 'neutral', false], ['any attack in the last hour', 'security', 'neutral', false], ['check the security logs and report back', 'security', 'neutral', false],
        ['monitor my network for attacks', 'security', 'neutral', false], ['are we being hacked right now', 'security', 'neutral', true], ['any suspicious activity today', 'security', 'neutral', false],
        ['who is trying to break in', 'security', 'neutral', false], ['show me the security status', 'security', 'neutral', false], ['did someone scan the server', 'security', 'neutral', false],
        ['is there a brute force attempt', 'security', 'neutral', false], ['what attacks look like in the log', 'question', 'neutral', false], ['what is sql injection', 'question', 'neutral', false],
        ['explain brute force attacks', 'question', 'neutral', false],
        ['this is useless it is wrong again', 'complaint', 'negative', false], ['you keep giving me the wrong answer', 'complaint', 'negative', false], ['the report is stuck again and nothing works', 'complaint', 'negative', false],
        ['that is not working', 'complaint', 'negative', false], ['bad bot you do not understand', 'complaint', 'negative', false],
        ['urgent where is the report', 'howto', 'neutral', true], ['i need the result right now', 'question', 'neutral', true], ['asap please check the failed test', 'task', 'neutral', true],
        ['this is critical the system is down!!', 'question', 'negative', true], ['quickly how do i send it', 'howto', 'neutral', true],
        ['it is not bad', 'question', 'positive', false], ['that is not good', 'question', 'negative', false], ['great that works now', 'thanks', 'positive', false],
        ['explain simply how attention works', 'question', 'neutral', false], ['give me an example of a tolerable result', 'question', 'neutral', false],
    ];


    /** written after the rules were tuned and never used to tune them: the honest measure of how well the understanding generalises */
    public const HOLDOUT = [
        ['hey lexus good afternoon', 'greeting', 'neutral', false], ['thank you lexus that was helpful', 'thanks', 'positive', false], ['how can i reset a whole shoe test', 'howto', 'neutral', false],
        ['where do i find the failed materials', 'howto', 'neutral', false], ['what is a tolerable result', 'question', 'neutral', false], ['is the pull test required for every row', 'question', 'neutral', false],
        ['how many materials are completed', 'status', 'neutral', false], ['show me the pending reports', 'status', 'neutral', false], ['give me an update on dtp20261118eyb13-l', 'status', 'neutral', false],
        ['please generate the weekly report', 'task', 'neutral', false], ['can you block that address', 'task', 'neutral', false], ['start the training now', 'task', 'neutral', true],
        ['are there any intruders on the server', 'security', 'neutral', false], ['did a hacker try to get in last night', 'security', 'neutral', false], ['check if someone is probing my website',  'security', 'neutral', false],
        ['you are useless this keeps failing again and again', 'complaint', 'negative', false], ['the page is broken and nothing loads', 'complaint', 'negative', false], ['i do not understand your answer', 'complaint', 'negative', false],
        ['um uh how do i add a pull test please', 'howto', 'neutral', false], ['okay so what does fail mean', 'question', 'neutral', false], ['i need this immediately where is the report', 'howto', 'neutral', true],
        ['why do we divide by the square root in attention', 'question', 'neutral', false], ['explain dropout in simple words', 'question', 'neutral', false], ['that is very good thanks', 'thanks', 'positive', false],
    ];

    /** overall accuracy (percent) of the understanding on a set of cases: intent + sentiment + urgency per message */
    public static function accuracy(array $set): float
    {
        $tot = 0;
        $ok  = 0;
        foreach ($set as [$m, $intent, $sent, $urg]) {
            $u = Persona::understand($m);
            foreach (['intent' => $intent, 'sentiment' => $sent, 'urgent' => $urg] as $k => $want) {
                $tot++;
                $ok += $u[$k] === $want ? 1 : 0;
            }
        }

        return round($ok * 100 / max(1, $tot), 1);
    }

    public function run(array $params)
    {
        $set = in_array('holdout', $params, true) ? self::HOLDOUT : self::CASES;
        $by = [];
        $ok = 0;
        $miss = [];
        foreach ($set as [$m, $intent, $sent, $urg]) {
            $u = Persona::understand($m);
            foreach (['intent' => $intent, 'sentiment' => $sent, 'urgent' => $urg] as $k => $want) {
                $by[$k][0] = ($by[$k][0] ?? 0) + 1;
                if ($u[$k] === $want) {
                    $by[$k][1] = ($by[$k][1] ?? 0) + 1;
                } else {
                    $miss[] = sprintf('%-52s %-9s want %-9s got %s', $m, $k, var_export($want, true), var_export($u[$k], true));
                }
            }
        }
        $tot = 0; $good = 0;
        foreach ($by as $k => [$n, $g]) {
            $g   = $g ?? 0;
            $tot += $n; $good += $g;
            CLI::write(sprintf('%-10s %3d / %3d  %5.1f%%', $k, $g, $n, $g * 100 / $n));
        }
        foreach ($miss as $x) {
            CLI::write('  MISS ' . $x);
        }
        CLI::write(sprintf('OVERALL COMMS' . (in_array('holdout', $params, true) ? ' (hold-out)' : '') . ' | %.1f%%', $good * 100 / max(1, $tot)));
    }
}
