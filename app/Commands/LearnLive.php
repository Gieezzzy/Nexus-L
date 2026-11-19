<?php

namespace App\Commands;

use App\Libraries\ConversationLearner;
use App\Libraries\Mastery;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark brain:learn-live [quiz-size]   the autonomous learning step on its own: read new conversations, self-quiz, print what was learned (the night trainer does the same every few rounds) */
class LearnLive extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:learn-live';
    protected $description = 'Learns from the conversations since the last run and quizzes itself; never writes new knowledge.';

    public function run(array $params)
    {
        $l = ConversationLearner::ingest();
        $q = Mastery::quiz(max(20, min(1000, (int) ($params[0] ?? 200))));
        CLI::write("Conversations: {$l['chats']} lines read, {$l['helpful']} helpful pairs, {$l['owner_fix']} owner corrections, {$l['prefs']} style preferences.");
        CLI::write("Self-quiz: {$q['right']} of {$q['asked']} right ({$q['accuracy']}%).");
        foreach (array_slice($q['missed'], 0, 10) as $m) {
            CLI::write('  missed: "' . $m['asked'] . '" -> answered "' . mb_substr($m['got'], 0, 50) . '" (wanted "' . mb_substr($m['title'], 0, 50) . '")');
        }
    }
}
