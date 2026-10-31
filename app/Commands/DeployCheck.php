<?php

namespace App\Commands;

use App\Libraries\Brain;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark deploy:check - one screen that says whether this server is ready for users. */
class DeployCheck extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'deploy:check';
    protected $description = 'Checks PHP, database, folders, tools, scheduled task, backups and speed settings before / after going live.';

    private int $bad = 0;
    private int $warn = 0;

    private function line(string $state, string $label, string $detail = ''): void
    {
        $color = $state === 'OK' ? 'green' : ($state === 'WARN' ? 'yellow' : 'red');
        if ($state === 'FAIL') {
            $this->bad++;
        } elseif ($state === 'WARN') {
            $this->warn++;
        }
        CLI::write(sprintf('%-5s %-34s %s', $state, $label, $detail), $color);
    }

    public function run(array $params)
    {
        CLI::write('NEXUS-L deployment check - ' . date('Y-m-d H:i:s'), 'white');

        // PHP
        $this->line(version_compare(PHP_VERSION, '8.1', '>=') ? 'OK' : 'FAIL', 'PHP version', PHP_VERSION);
        foreach (['mysqli', 'curl', 'mbstring', 'zip', 'intl', 'json', 'openssl', 'fileinfo'] as $ext) {
            $this->line(extension_loaded($ext) ? 'OK' : ($ext === 'intl' ? 'WARN' : 'FAIL'), 'PHP extension ' . $ext);
        }
        $this->line(ENVIRONMENT === 'production' ? 'OK' : 'FAIL', 'Mode is production', ENVIRONMENT . ' (development shows a debug toolbar with database details)');

        // database + migrations
        try {
            $db = \Config\Database::connect();
            $db->query('SELECT 1');
            $this->line('OK', 'Database connection', $db->getDatabase() ? 'connected' : '');
            $need = ['tbl_machine_maps', 'tbl_app_settings', 'tbl_interlink_session', 'tbl_bot_knowledge', 'tbl_report_layouts'];
            $miss = array_filter($need, static fn ($t) => ! $db->tableExists($t));
            $this->line($miss ? 'FAIL' : 'OK', 'Tables are migrated', $miss ? 'missing: ' . implode(', ', $miss) . ' - run: php spark migrate' : 'all present');
            $kb = $db->tableExists('tbl_bot_knowledge') ? (int) $db->table('tbl_bot_knowledge')->where('status', 'approved')->countAllResults() : 0;
            $this->line($kb >= 50 ? 'OK' : 'WARN', 'Assistant knowledge', $kb . ' approved entries' . ($kb < 50 ? ' - run: php spark brain:seed' : ''));
        } catch (\Throwable $e) {
            $this->line('FAIL', 'Database connection', $e->getMessage());
        }

        // folders
        foreach ([WRITEPATH . 'cache', WRITEPATH . 'session', WRITEPATH . 'logs'] as $d) {
            $this->line(is_dir($d) && is_writable($d) ? 'OK' : 'FAIL', 'Writable ' . basename($d), $d);
        }
        foreach (Brain::dirStatus() as $d => $st) {
            $this->line($st['writable'] ? 'OK' : 'FAIL', 'Brain folder', $d . ($st['free_gb'] !== null ? ' (' . $st['free_gb'] . ' GB free)' : ''));
            if ($st['free_gb'] !== null && $st['free_gb'] < 5) {
                $this->line('WARN', 'Low disk space', $d . ' has less than 5 GB free');
            }
        }

        // tools
        $chrome = null;
        foreach (['C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe'] as $c) {
            $chrome = $chrome ?? (is_file($c) ? $c : null);
        }
        $this->line($chrome ? 'OK' : 'FAIL', 'Chrome / Edge (PDF + InterLink sign-in)', $chrome ?? 'not found');
        $node = trim((string) @shell_exec('node -v 2>&1'));
        $this->line(str_starts_with($node, 'v') ? 'OK' : 'WARN', 'Node.js (InterLink sign-in helper)', $node ?: 'not found');
        $this->line(is_dir(ROOTPATH . 'tools/interlink-login/node_modules') ? 'OK' : 'WARN', 'Sign-in helper installed', 'tools/interlink-login/node_modules');

        // machine files + scheduled task + speed
        $root = \App\Libraries\MachineFileReader::root();
        $this->line(@is_dir($root) ? 'OK' : 'FAIL', 'Machine files location', $root);
        $task = (string) @shell_exec('schtasks /query /tn "NEXUS-L InterLink Worker" 2>&1');
        $this->line(stripos($task, 'NEXUS-L InterLink Worker') !== false && stripos($task, 'ERROR') === false ? 'OK' : 'WARN', 'Scheduled task "NEXUS-L InterLink Worker"', 'runs the background sync + Brain export every minute (tools\\interlink-worker.ps1, elevated)');
        $httpd = 'C:\\xampp\\apache\\conf\\httpd.conf';
        if (is_file($httpd)) {
            $conf = (string) file_get_contents($httpd);
            $this->line(preg_match('/^\s*LoadModule\s+deflate_module/m', $conf) ? 'OK' : 'WARN', 'Apache compression (mod_deflate)', 'uncomment the LoadModule deflate_module line in httpd.conf and restart Apache');
            $this->line(preg_match('/^\s*LoadModule\s+headers_module/m', $conf) ? 'OK' : 'WARN', 'Apache headers (cache control)');
            $this->line(preg_match('/^\s*LoadModule\s+rewrite_module/m', $conf) ? 'OK' : 'FAIL', 'Apache rewrite (pretty URLs)');
        }
        $this->line((int) ini_get('memory_limit') >= 256 || ini_get('memory_limit') === '-1' ? 'OK' : 'WARN', 'PHP memory_limit', (string) ini_get('memory_limit'));
        $this->line(is_dir(ROOTPATH . 'public/assets/min/js') ? 'OK' : 'WARN', 'Minified scripts built', 'node tools/build/build.js');

        // backups
        $bk = glob('C:\\Users\\*\\NEXUS-L-backup-*', GLOB_ONLYDIR) ?: [];
        $this->line($bk ? 'OK' : 'WARN', 'Project backup on another drive', $bk ? $bk[0] : 'none found - copy the project to C: or a network share regularly');
        $d = @disk_free_space(substr(ROOTPATH, 0, 3));
        $this->line($d !== false && $d > 20e9 ? 'OK' : 'WARN', 'Free space on ' . substr(ROOTPATH, 0, 2), $d === false ? 'unknown' : round($d / 1073741824) . ' GB');

        CLI::newLine();
        CLI::write($this->bad === 0 ? 'READY: no blocking problems' . ($this->warn ? " ({$this->warn} warnings to review)" : '.') : "NOT READY: {$this->bad} blocking problem(s), {$this->warn} warning(s).", $this->bad === 0 ? 'green' : 'red');
    }
}
