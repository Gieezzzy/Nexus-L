<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;

/**
 * Settings page: Admin only (the routes are in the auth:admin group, and every method checks the role again).
 *  - InterLink service: the address (IP + port) and shared key of the C# background service, with a test
 *  - Database: which database NEXUS-L uses (written to .env, where the code reads it), a test, and a transfer to another database
 *  - values a new InterLink report starts with, approval link time, Synology Chat
 */
class SettingsController extends BaseController
{
    private function isAdmin(): bool
    {
        return strcasecmp((string) ($this->auth->user()['role'] ?? ''), 'admin') === 0;
    }

    private function deny()
    {
        return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Only an Admin can use the settings.', 'csrf' => csrf_hash()]);
    }

    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function me(): string
    {
        return (string) ($this->auth->user()['username'] ?? '');
    }

    public function index()
    {
        if (! $this->isAdmin()) {
            return redirect()->to(site_url('dashboard'))->with('error', 'You are not authorized to access that page.');
        }
        try {
            SystemSettings::ensureTable();
        } catch (\Throwable $e) {
            // shown by the page: the settings table could not be made
        }

        return $this->render('settings/index', [
            'title'      => 'Settings',
            'withCss'    => 'settings.css',
            'withJs'     => ['settings.js'],
            'rows'       => SystemSettings::forPage(),
            'categories' => SystemSettings::categories(),
            'db'         => $this->currentDb(),
            'envPath'    => $this->envPath(),
            'envWritable' => is_writable($this->envPath()),
        ]);
    }

    /** POST settings/save {values: {key: value}}: a password left empty keeps what is saved */
    public function save()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p    = $this->request->getJSON(true) ?? [];
        $vals = is_array($p['values'] ?? null) ? $p['values'] : [];
        $defs = SystemSettings::definitions();
        $n    = 0;
        foreach ($vals as $key => $val) {
            $def = $defs[$key] ?? null;
            if (! $def) {
                continue;
            }
            $val = is_bool($val) ? ($val ? '1' : '0') : trim((string) $val);
            if ($def['type'] === 'password' && $val === '') {
                continue;                       // not retyped: unchanged
            }
            if ($def['type'] === 'number' && ($val === '' || ! is_numeric($val))) {
                return $this->json(['ok' => false, 'error' => "{$def['label']} must be a number."], 422);
            }
            if ($def['type'] === 'url' && $val !== '' && ! preg_match('#^https?://#i', $val)) {
                return $this->json(['ok' => false, 'error' => "{$def['label']} must start with http:// or https://."], 422);
            }
            if ($key === 'interlink.service.key' && $val !== '' && strlen($val) < 16) {
                return $this->json(['ok' => false, 'error' => 'The shared key must be at least 16 characters.'], 422);
            }
            SystemSettings::set($key, $val, $this->me());
            $n++;
        }

        return $this->json(['ok' => true, 'saved' => $n]);
    }

    /** POST settings/service/test {url?, key?}: is the service reachable, is the key right, is it signed in to InterLink? */
    public function testService()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p   = $this->request->getJSON(true) ?? [];
        $url = rtrim(trim((string) ($p['url'] ?? '')) ?: (string) SystemSettings::get('interlink.service.url', ''), '/');
        $key = trim((string) ($p['key'] ?? '')) ?: (string) SystemSettings::get('interlink.service.key', '');
        if ($url === '') {
            return $this->json(['ok' => false, 'error' => 'Enter the service address first (IP and port).'], 422);
        }
        $out = ['ok' => true, 'reachable' => false, 'keyOk' => false, 'signedIn' => false, 'waiting' => 0, 'version' => ''];
        try {
            $h = json_decode((string) service('curlrequest')->get($url . '/health', ['timeout' => 4, 'http_errors' => false])->getBody(), true) ?: [];
            $out['reachable'] = ! empty($h['ok']);
            $out['signedIn']  = ! empty($h['signedIn']);
            $out['waiting']   = (int) ($h['waiting'] ?? 0);
            $out['version']   = (string) ($h['version'] ?? '');
            if ($out['reachable']) {
                $q = service('curlrequest')->get($url . '/queue', ['timeout' => 4, 'http_errors' => false, 'headers' => ['X-Service-Key' => $key]]);
                $out['keyOk'] = $q->getStatusCode() === 200;
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }

        return $this->json($out);
    }

    // ------------------------------------------------------------------ database -----------------------------------------------------

    private function envPath(): string
    {
        return ROOTPATH . '.env';
    }

    /** the connection NEXUS-L is using now (the password is never sent to the browser) */
    private function currentDb(): array
    {
        $c = config(\Config\Database::class)->default;
        $info = ['hostname' => (string) $c['hostname'], 'port' => (int) ($c['port'] ?? 3306), 'database' => (string) $c['database'], 'username' => (string) $c['username'], 'connected' => false];
        try {
            $db = \Config\Database::connect();
            $info['connected'] = (bool) $db->query('SELECT 1');
            $info['version']   = (string) $db->getVersion();
            $info['tables']    = count($db->listTables());
            $row = $db->query("SELECT ROUND(SUM(data_length+index_length)/1048576,1) AS mb FROM information_schema.tables WHERE table_schema = ?", [$c['database']])->getRowArray();
            $info['sizeMb'] = (float) ($row['mb'] ?? 0);
        } catch (\Throwable $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    /** @return array{0: ?\mysqli, 1: string} a connection to the given server, or the reason there is none */
    private function connect(array $p, bool $createDb = false): array
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $host = trim((string) ($p['hostname'] ?? ''));
        $port = (int) ($p['port'] ?? 3306) ?: 3306;
        $user = trim((string) ($p['username'] ?? ''));
        $pass = (string) ($p['password'] ?? '');
        $name = trim((string) ($p['database'] ?? ''));
        if ($host === '' || $user === '' || $name === '') {
            return [null, 'Server, database and user are required.'];
        }
        if (! preg_match('/^[A-Za-z0-9_$.\-]+$/', $name)) {
            return [null, 'The database name may only hold letters, numbers and _ - . $'];
        }
        $m = mysqli_init();
        $m->options(MYSQLI_OPT_CONNECT_TIMEOUT, 6);
        if (! @$m->real_connect($host, $user, $pass, null, $port)) {
            return [null, 'Could not connect to ' . $host . ':' . $port . ' - ' . $m->connect_error];
        }
        if (! $m->select_db($name)) {
            if ($createDb) {
                if (! $m->query('CREATE DATABASE IF NOT EXISTS `' . $m->real_escape_string($name) . '` CHARACTER SET utf8mb4') || ! $m->select_db($name)) {
                    return [null, 'The database "' . $name . '" does not exist and could not be created: ' . $m->error];
                }
            } else {
                return [null, 'The server answered, but the database "' . $name . '" was not found.'];
            }
        }
        $m->set_charset('utf8mb4');

        return [$m, ''];
    }

    /** POST settings/db/test {hostname, port, database, username, password} */
    public function dbTest()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        [$m, $err] = $this->connect($this->request->getJSON(true) ?? []);
        if (! $m) {
            return $this->json(['ok' => false, 'error' => $err], 422);
        }
        $tables = (int) ($m->query('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = DATABASE()')->fetch_assoc()['c'] ?? 0);
        $hasApp = (bool) $m->query("SHOW TABLES LIKE 'tbl_users'")->num_rows;
        $ver    = $m->server_info;
        $m->close();

        return $this->json(['ok' => true, 'tables' => $tables, 'hasSchema' => $hasApp, 'version' => $ver,
            'note' => $hasApp ? 'This database already holds NEXUS-L tables.' : ($tables > 0 ? 'The database has tables, but not NEXUS-L\'s.' : 'The database is empty: use "Transfer" first to copy everything into it.')]);
    }

    /** writes database.default.* to .env (a copy of the old file is kept in writable/env-backups) */
    private function writeEnv(array $conn): ?string
    {
        $path = $this->envPath();
        if (! is_file($path) || ! is_writable($path)) {
            return 'The .env file is not writable: ' . $path;
        }
        foreach ($conn as $v) {
            if (preg_match("/['\"\r\n]/", (string) $v)) {
                return 'Quotes and line breaks are not allowed in the connection values.';
            }
        }
        $dir = WRITEPATH . 'env-backups/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @copy($path, $dir . '.env-' . date('Ymd-His'));
        $lines = preg_split('/\R/', (string) file_get_contents($path));
        $map   = ['hostname' => 'hostname', 'database' => 'database', 'username' => 'username', 'password' => 'password', 'port' => 'port'];
        $done  = [];
        foreach ($lines as $i => $line) {
            foreach ($map as $k => $field) {
                if (preg_match('/^\s*database\.default\.' . $field . '\s*=/', $line)) {
                    $lines[$i] = "database.default.{$field} = '" . $conn[$k] . "'";
                    $done[$k]  = true;
                }
            }
        }
        foreach ($map as $k => $field) {
            if (empty($done[$k])) {
                $lines[] = "database.default.{$field} = '" . $conn[$k] . "'";
            }
        }
        file_put_contents($path, implode("\n", $lines));

        return null;
    }

    /** POST settings/db/switch {hostname, port, database, username, password, confirm: "SWITCH"}: NEXUS-L uses this database from the next request on */
    public function dbSwitch()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p = $this->request->getJSON(true) ?? [];
        if (($p['confirm'] ?? '') !== 'SWITCH') {
            return $this->json(['ok' => false, 'error' => 'Type SWITCH to confirm.'], 422);
        }
        [$m, $err] = $this->connect($p);
        if (! $m) {
            return $this->json(['ok' => false, 'error' => $err], 422);
        }
        if (! $m->query("SHOW TABLES LIKE 'tbl_users'")->num_rows) {
            return $this->json(['ok' => false, 'error' => 'That database has no NEXUS-L tables (tbl_users is missing). Transfer the data into it first.'], 422);
        }
        $m->close();
        $e = $this->writeEnv(['hostname' => trim((string) $p['hostname']), 'port' => (string) ((int) ($p['port'] ?? 3306) ?: 3306), 'database' => trim((string) $p['database']), 'username' => trim((string) $p['username']), 'password' => (string) ($p['password'] ?? '')]);
        if ($e) {
            return $this->json(['ok' => false, 'error' => $e], 500);
        }
        log_message('notice', 'Settings: the database was switched to ' . $p['hostname'] . '/' . $p['database'] . ' by ' . $this->me());

        return $this->json(['ok' => true, 'message' => 'Saved. NEXUS-L uses the new database from the next page on. The old connection is kept in writable/env-backups.']);
    }

    // ------------------------------------------------------------------ transfer to another database ----------------------------------

    private function transferDir(): string
    {
        $d = WRITEPATH . 'transfer/';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    /** POST settings/transfer/start {target: {...}, create_db, overwrite}: copies every table and its rows, in the background */
    public function transferStart()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p      = $this->request->getJSON(true) ?? [];
        $target = is_array($p['target'] ?? null) ? $p['target'] : [];
        [$m, $err] = $this->connect($target, ! empty($p['create_db']));
        if (! $m) {
            return $this->json(['ok' => false, 'error' => $err], 422);
        }
        $m->close();
        $src = config(\Config\Database::class)->default;
        if (strcasecmp((string) $src['hostname'], (string) $target['hostname']) === 0 && (int) ($src['port'] ?? 3306) === ((int) ($target['port'] ?? 3306) ?: 3306) && strcasecmp((string) $src['database'], (string) $target['database']) === 0) {
            return $this->json(['ok' => false, 'error' => 'The target is the database NEXUS-L is using now. Choose another database.'], 422);
        }
        $id  = bin2hex(random_bytes(8));
        $job = ['id' => $id, 'status' => 'queued', 'started_by' => $this->me(), 'created_at' => date('Y-m-d H:i:s'), 'overwrite' => ! empty($p['overwrite']),
            'target' => ['hostname' => trim((string) $target['hostname']), 'port' => (int) ($target['port'] ?? 3306) ?: 3306, 'database' => trim((string) $target['database']), 'username' => trim((string) $target['username']), 'password' => (string) ($target['password'] ?? '')],
            'tables_total' => 0, 'tables_done' => 0, 'rows_copied' => 0, 'current' => '', 'errors' => [], 'log' => []];
        file_put_contents($this->transferDir() . $id . '.json', json_encode($job));

        $php = is_file(PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe') ? PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe' : 'php';
        $cmd = 'start /B "" "' . $php . '" "' . ROOTPATH . 'spark" settings:transfer ' . $id . ' > "' . $this->transferDir() . $id . '.out" 2>&1';
        pclose(popen('cmd /c ' . $cmd, 'r'));

        return $this->json(['ok' => true, 'id' => $id]);
    }

    /** GET settings/transfer/status?id= */
    public function transferStatus()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $id   = preg_replace('/[^a-f0-9]/', '', (string) $this->request->getGet('id'));
        $file = $this->transferDir() . $id . '.json';
        if ($id === '' || ! is_file($file)) {
            return $this->json(['ok' => false, 'error' => 'Unknown transfer.'], 404);
        }
        $job = json_decode((string) file_get_contents($file), true) ?: [];
        unset($job['target']['password']);

        return $this->json(['ok' => true, 'job' => $job]);
    }
}
