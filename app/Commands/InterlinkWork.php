<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark interlink:work
 * One background cycle of the InterLink sync center: session check, pull of what changed in InterLink, queue run.
 * Meant to be started every minute by Windows Task Scheduler (see tools/interlink-worker.ps1), so syncing keeps
 * working with every NEXUS-L / InterLink page closed. A lock file keeps two cycles from overlapping.
 */
class InterlinkWork extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'interlink:work';
    protected $description = 'Runs one InterLink sync-center cycle (session check, live pull, queued Lab References).';

    public function run(array $params)
    {
        $lock = fopen(WRITEPATH . 'cache/interlink_worker.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            CLI::write('Another cycle is still running.', 'yellow');

            return;
        }

        try {
            $result = (new \App\Controllers\InterlinkController())->runWorkerCycle();
            CLI::write(date('Y-m-d H:i:s') . ' ' . json_encode($result));
            try {
                CLI::write('brain ' . json_encode(\App\Libraries\Brain::flush()));   // export new activity / chats / knowledge to the .back files
            } catch (\Throwable $e) {
                CLI::error('brain: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
