<?php

namespace App\Commands;

use App\Libraries\BrainLearn as Learn;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark brain:learn` : groups the questions the assistant could not answer well and builds suggestions for the Brain console
 * (Learning tab). It only proposes; an administrator applies or rejects each one. Safe to run every night.
 */
class BrainLearn extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:learn';
    protected $description = 'Builds learning suggestions from unanswered / low-confidence / thumbs-down questions.';

    public function run(array $params)
    {
        if (! Learn::ready()) {
            CLI::error('Run `php spark migrate` first (tbl_bot_suggestions is missing).');

            return;
        }
        $r = Learn::refresh();
        CLI::write("Groups of questions: {$r['groups']}, answers found in chats: {$r['drafts']}, still need an answer: {$r['gaps']}.");
        CLI::write('Waiting in the Brain console: ' . Learn::stats()['open']);
    }
}
