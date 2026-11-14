<?php

namespace App\Commands;

use App\Libraries\WebPush;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark push:test ["Title"] ["Text"] : sends a browser push to every device that allowed notifications, and lists them */
class PushTest extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'push:test';
    protected $description = 'Send a test browser notification to every subscribed device.';

    public function run(array $params)
    {
        WebPush::ensureTables();
        $rows = \Config\Database::connect()->query('SELECT user_id, role, LEFT(user_agent, 60) ua, last_ok_at, last_error FROM tbl_push_subscriptions')->getResultArray();
        CLI::write(count($rows) . ' subscribed device(s).');
        foreach ($rows as $r) {
            CLI::write(' - user ' . $r['user_id'] . ' (' . $r['role'] . ') ' . $r['ua'] . ' last ok: ' . ($r['last_ok_at'] ?: '-') . ($r['last_error'] ? ' ERROR: ' . $r['last_error'] : ''));
        }
        $n = WebPush::notify($params[0] ?? 'NEXUS-L test', $params[1] ?? 'Push works. You can close NEXUS-L and still get lab alerts.', 'dashboard', ['tag' => 'nxl-test']);
        CLI::write("Delivered to {$n} device(s).", $n > 0 ? 'green' : 'yellow');
    }
}
