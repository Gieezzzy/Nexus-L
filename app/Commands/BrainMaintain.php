<?php

namespace App\Commands;

use App\Libraries\Brain;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark brain:maintain [--markdown] [--purge]
 * Exports new events / chats / knowledge to the .back files (both Brain folders). Run every minute by the worker task.
 * --purge removes activity events older than 90 days from the database (the .back files keep them).
 */
class BrainMaintain extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:maintain';
    protected $description = 'Exports the Brain to its .back files and keeps the tables tidy.';

    public function run(array $params)
    {
        $db  = \Config\Database::connect();
        $out = Brain::flush();
        if (CLI::getOption('markdown')) {
            $out['markdown'] = Brain::exportMarkdown();
        }
        if (CLI::getOption('purge')) {
            $cut = date('Y-m-d H:i:s', time() - 86400 * 90);
            $db->table('tbl_bot_events')->where(['flushed' => 1])->where('created_at <', $cut)->delete();
            $out['purged'] = $db->affectedRows();
        }
        // how many different people hit each repeated problem
        $db->query("UPDATE tbl_bot_issues i SET users = (SELECT COUNT(DISTINCT e.user_id) FROM tbl_bot_events e WHERE e.signature = i.signature AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) WHERE i.status = 'open'");
        CLI::write(date('Y-m-d H:i:s') . ' ' . json_encode($out));
    }
}
