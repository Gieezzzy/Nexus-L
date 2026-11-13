<?php

namespace App\Commands;

use App\Libraries\SystemSettings;
use App\Libraries\VfTestRequestService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VfSync extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'vf:sync';
    protected $description = 'Reads the Outlook-synced e-mails and updates VF Test Requests (requests, transit, lab received, certificates). Run by a scheduled task; the interval is Settings > VF Test Request.';
    protected $usage       = 'vf:sync [--force]';

    public function run(array $params)
    {
        $minutes = (int) SystemSettings::get('vf.auto_sync_minutes', '10');
        if ($minutes <= 0 && ! isset($params['force']) && CLI::getOption('force') === null) {
            CLI::write('VF auto sync is off (Settings > VF Test Request).', 'yellow');

            return;
        }
        $last = (int) strtotime((string) SystemSettings::get('vf.last_auto_sync', ''));
        if (CLI::getOption('force') === null && $last > 0 && (time() - $last) < max(1, $minutes) * 60 - 20) {
            CLI::write('Not due yet.', 'yellow');

            return;
        }
        SystemSettings::set('vf.last_auto_sync', date('Y-m-d H:i:s'), 'vf:sync');
        try {
            $res = (new VfTestRequestService())->syncFromOutlook();
        } catch (\Throwable $e) {
            log_message('error', 'vf:sync failed: ' . $e->getMessage());
            CLI::error('vf:sync failed: ' . $e->getMessage());

            return;
        }
        $line = json_encode($res, JSON_UNESCAPED_UNICODE);
        CLI::write($line, ! empty($res['ok']) ? 'green' : 'red');
        if (! empty($res['ok']) && (($res['items_added'] ?? 0) + ($res['transit_updates'] ?? 0) + ($res['lab_received'] ?? 0) + ($res['certificates_saved'] ?? 0)) > 0) {
            log_message('info', 'vf:sync ' . $line);
        }
    }
}
