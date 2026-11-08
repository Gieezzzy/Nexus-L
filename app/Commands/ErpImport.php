<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark erp:import "C:\path\2026 Daily Tracker.xlsx": fills Application Nos., ERP status and InterLink flag of the lab refs from the daily tracker workbook */
class ErpImport extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'erp:import';
    protected $description = 'Import bonding / pull test TR numbers and ERP status from the daily tracker workbook.';
    protected $usage       = 'erp:import <workbook.xlsx>';

    public function run(array $params)
    {
        $path = (string) ($params[0] ?? '');
        if ($path === '' || ! is_file($path)) {
            CLI::error('Give the path of the workbook.');

            return;
        }
        date_default_timezone_set('Asia/Manila');
        foreach (\App\Libraries\ErpMonitor::importWorkbook($path) as $k => $v) {
            CLI::write(str_pad($k, 10) . $v);
        }
    }
}
