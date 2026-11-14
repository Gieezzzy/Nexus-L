<?php

namespace App\Commands;

use App\Libraries\ShareSync;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark share:worker          keeps running: every 2 seconds it does the waiting Lab Docs changes on the .130 share
 * php spark share:worker --once   does what is waiting now and stops
 *
 * Must run as a Windows user who can open \\192.168.32.130\qc lab files (the web server cannot). It is started at logon by
 * "NEXUS-L Share Worker.vbs" in the Startup folder of that user (tools/share-worker/install.ps1 puts it there).
 * Only one copy runs at a time (a lock file).
 */
class ShareWorker extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'share:worker';
    protected $description = 'Applies the Lab Docs file changes to the .130 share (rename, move, copy, delete, upload).';

    public function run(array $params)
    {
        $once = in_array('--once', $params, true) || CLI::getOption('once') !== null;
        $lock = fopen(WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'share_worker.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            CLI::write('Another share worker is already running.');

            return;
        }
        CLI::write('Share worker started (' . (is_dir(ShareSync::ROOT) ? 'share reachable' : 'share NOT reachable yet') . ').');
        $rounds = 0;
        do {
            ShareSync::beat();
            try {
                $n = ShareSync::work(50);
                if ($n > 0) {
                    CLI::write(date('H:i:s') . " {$n} change(s) done on the share.");
                }
            } catch (\Throwable $e) {
                CLI::error($e->getMessage());
                \Config\Database::connect()->reconnect();   // a dropped database connection must not stop the worker
            }
            if ($once) {
                break;
            }
            sleep(2);
            if (++$rounds % 1800 === 0) {                   // about every hour: free memory, keep the connection fresh
                gc_collect_cycles();
            }
        } while (true);
        flock($lock, LOCK_UN);
    }
}
