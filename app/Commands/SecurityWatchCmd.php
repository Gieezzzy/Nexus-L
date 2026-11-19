<?php

namespace App\Commands;

use App\Libraries\SecurityWatch;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark security:watch [minutes]   reads the Apache access log and the sign-in log, stores new findings and prints the report (nothing is blocked) */
class SecurityWatchCmd extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'security:watch';
    protected $description = 'Finds attack patterns in the web server and sign-in logs and reports them (read only).';
    protected $usage       = 'security:watch [minutes]';

    public function run(array $params)
    {
        $min = max(5, min(1440, (int) ($params[0] ?? 60)));
        $r   = SecurityWatch::report($min);
        $new = SecurityWatch::record($r['findings']);
        CLI::write($r['text']);
        foreach ($r['steps'] as $s) {
            CLI::write(' - ' . $s);
        }
        CLI::write("New findings stored: {$new}");
    }
}
