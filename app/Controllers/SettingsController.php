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
        return \App\Libraries\Roles::isAdmin((string) ($this->auth->user()['role'] ?? ''));
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
        if (! $this->isAdmin()) {                         // everybody else has their own settings under the Account Profile
            return redirect()->to(site_url('account?tab=preferences'));
        }
        try {
            SystemSettings::ensureTable();
        } catch (\Throwable $e) {
            // shown by the page: the settings table could not be made
        }

        return $this->render('settings/index', [
            'title'      => 'Settings',
            'withCss'    => 'settings.css',
            'withJs'     => ['settings.js', 'settings-users.js', 'settings-ui.js'],
            'rows'       => SystemSettings::forPage(),
            'categories' => SystemSettings::categories(),
            'db'         => $this->currentDb(),
            'envPath'    => $this->envPath(),
            'envWritable' => is_writable($this->envPath()),
        ]);
    }


    // ------------------------------------------------------------------ users and roles (Admin)

    private function userRow(array $u): array
    {
        return [
            'id' => (int) $u['UserID'], 'username' => $u['Username'], 'name' => $u['FullName'], 'email' => (string) $u['Email'], 'department' => (string) $u['Department'],
            'job_title' => (string) ($u['JobTitle'] ?? ''), 'synology_id' => $u['SynologyID'] !== null ? (int) $u['SynologyID'] : null,
            'roles' => \App\Libraries\Roles::parse((string) $u['Role']), 'legacy' => \App\Libraries\Roles::isLegacy((string) $u['Role']), 'raw_role' => (string) $u['Role'],
            'active' => (int) $u['IsActive'] === 1, 'locked' => ! empty($u['LockedUntil']) && strtotime((string) $u['LockedUntil']) > time(), 'last_login' => $u['LastLoginAt'] ?? null,
        ];
    }

    /** GET settings/users */
    public function users()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $rows = (new \App\Models\UserModel())->orderBy('FullName')->findAll();

        return $this->json(['ok' => true, 'users' => array_map(fn ($u) => $this->userRow($u), $rows), 'roles' => \App\Libraries\Roles::ROLES]);
    }

    private function tempPassword(): string
    {
        $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; $b = 'abcdefghijkmnopqrstuvwxyz'; $c = '23456789'; $d = '!@#$%&*?';
        $pick = static fn (string $set, int $n) => implode('', array_map(static fn () => $set[random_int(0, strlen($set) - 1)], range(1, $n)));
        $pw = $pick($a, 3) . $pick($b, 5) . $pick($c, 3) . $pick($d, 2);

        return str_shuffle($pw);
    }

    /** the Role value from a list of role names (only the seven roles; none = the old plain "user") */
    private function roleValue(array $list): string
    {
        $out = [];
        foreach ($list as $r) {
            $n = \App\Libraries\Roles::normalize((string) $r);
            if ($n !== null && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out ? implode(';', $out) : 'user';
    }

    private function activeAdmins(?int $except = null): int
    {
        $n = 0;
        foreach ((new \App\Models\UserModel())->where('IsActive', 1)->findAll() as $u) {
            if (\App\Libraries\Roles::isAdmin((string) $u['Role']) && (int) $u['UserID'] !== (int) $except) {
                $n++;
            }
        }

        return $n;
    }

    /** POST settings/users/save {id, name, email, department, job_title, synology_id, roles[], active} */
    public function usersSave()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p     = $this->request->getJSON(true) ?? [];
        $model = new \App\Models\UserModel();
        $id    = (int) ($p['id'] ?? 0);
        $u     = $model->find($id);
        if (! $u) {
            return $this->json(['ok' => false, 'error' => 'That user does not exist.'], 404);
        }
        $role   = $this->roleValue((array) ($p['roles'] ?? []));
        $active = ! empty($p['active']);
        $name   = trim((string) ($p['name'] ?? $u['FullName']));
        $email  = trim((string) ($p['email'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            return $this->json(['ok' => false, 'error' => 'The full name must be 3 to 120 characters.'], 422);
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'error' => 'That e-mail address is not valid.'], 422);
        }
        if ($email !== '' && $model->where('Email', $email)->where('UserID !=', $id)->countAllResults() > 0) {
            return $this->json(['ok' => false, 'error' => 'Another account already uses that e-mail address.'], 422);
        }
        // the system must always keep an active Admin, and nobody takes the Admin role or the access away from themselves by mistake
        $stillAdmin = $active && \App\Libraries\Roles::isAdmin($role);
        if (! $stillAdmin && \App\Libraries\Roles::isAdmin((string) $u['Role']) && (int) $u['IsActive'] === 1 && $this->activeAdmins($id) < 1) {
            return $this->json(['ok' => false, 'error' => 'This is the last active Admin: keep the Admin role and the account active, or make another Admin first.'], 422);
        }
        if ($id === (int) $this->auth->id() && (! $active || ! \App\Libraries\Roles::isAdmin($role))) {
            return $this->json(['ok' => false, 'error' => 'You cannot take the Admin role from yourself or disable your own account.'], 422);
        }
        $syn = $p['synology_id'] ?? null;
        $model->update($id, [
            'FullName' => $name, 'Email' => $email ?: null, 'Department' => trim((string) ($p['department'] ?? '')) ?: null, 'JobTitle' => trim((string) ($p['job_title'] ?? '')) ?: null,
            'SynologyID' => ($syn !== null && $syn !== '' && ctype_digit((string) $syn)) ? (int) $syn : null, 'Role' => $role, 'IsActive' => $active ? 1 : 0,
        ]);

        return $this->json(['ok' => true, 'message' => $name . ' saved (' . ($role === 'user' ? 'no role' : str_replace(';', ', ', $role)) . ', ' . ($active ? 'active' : 'disabled') . ').', 'user' => $this->userRow($model->find($id))]);
    }

    /** POST settings/users/create {username, name, email, department, job_title, roles[]}: the temporary password is shown once */
    public function usersCreate()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p     = $this->request->getJSON(true) ?? [];
        $model = new \App\Models\UserModel();
        $uname = trim((string) ($p['username'] ?? ''));
        $name  = trim((string) ($p['name'] ?? ''));
        $email = trim((string) ($p['email'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9_.-]{4,50}$/', $uname)) {
            return $this->json(['ok' => false, 'error' => 'The username needs 4 to 50 letters, numbers, dots, dashes or underscores.'], 422);
        }
        if ($model->where('Username', $uname)->countAllResults() > 0) {
            return $this->json(['ok' => false, 'error' => 'That username is already taken.'], 422);
        }
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            return $this->json(['ok' => false, 'error' => 'The full name must be 3 to 120 characters.'], 422);
        }
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || $model->where('Email', $email)->countAllResults() > 0)) {
            return $this->json(['ok' => false, 'error' => 'The e-mail address is not valid or already used.'], 422);
        }
        $pw = $this->tempPassword();
        $id = $model->insert([
            'Username' => $uname, 'FullName' => $name, 'Email' => $email ?: null, 'Department' => trim((string) ($p['department'] ?? '')) ?: null, 'JobTitle' => trim((string) ($p['job_title'] ?? '')) ?: null,
            'Password' => $pw, 'Role' => $this->roleValue((array) ($p['roles'] ?? [])), 'IsActive' => 1, 'FailedAttempts' => 0,
        ], true);

        return $this->json(['ok' => true, 'message' => "Account {$uname} created. Give the person the temporary password shown below; it is not shown again, and they should change it under Account Profile.", 'temp_password' => $pw, 'user' => $this->userRow($model->find($id))]);
    }

    /** POST settings/users/reset {id}: a new temporary password (shown once), the account unlocked */
    public function usersReset()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $model = new \App\Models\UserModel();
        $id    = (int) (($this->request->getJSON(true) ?? [])['id'] ?? 0);
        $u     = $model->find($id);
        if (! $u) {
            return $this->json(['ok' => false, 'error' => 'That user does not exist.'], 404);
        }
        $pw = $this->tempPassword();
        $model->update($id, ['Password' => $pw, 'FailedAttempts' => 0, 'LockedUntil' => null]);

        return $this->json(['ok' => true, 'message' => 'New temporary password for ' . $u['Username'] . '. It is shown once; the person signs in with it and changes it under Account Profile. Their open sessions were signed out.', 'temp_password' => $pw]);
    }

    /** GET settings/roles/matrix */
    public function matrix()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }

        return $this->json(['ok' => true, 'matrix' => \App\Libraries\Roles::matrix(), 'defaults' => \App\Libraries\Roles::defaults(), 'modules' => \App\Libraries\Roles::MODULES, 'admin_only' => \App\Libraries\Roles::ADMIN_ONLY]);
    }

    /** POST settings/roles/matrix {matrix: {Role: {module: 0|1|2}}} or {reset: true} */
    public function matrixSave()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p = $this->request->getJSON(true) ?? [];
        if (! empty($p['reset'])) {
            SystemSettings::set('roles.matrix', '', $this->me());

            return $this->json(['ok' => true, 'message' => 'The access matrix is back to the defaults.', 'matrix' => \App\Libraries\Roles::defaults()]);
        }
        $clean = [];
        foreach ((array) ($p['matrix'] ?? []) as $role => $mods) {
            if (! isset(\App\Libraries\Roles::defaults()[$role]) || ! is_array($mods)) {
                continue;
            }
            foreach ($mods as $mod => $lvl) {
                if (isset(\App\Libraries\Roles::MODULES[$mod]) && ! in_array($mod, \App\Libraries\Roles::ADMIN_ONLY, true) && in_array((int) $lvl, [0, 1, 2], true)) {
                    $clean[$role][$mod] = (int) $lvl;
                }
            }
        }
        SystemSettings::set('roles.matrix', json_encode($clean), $this->me());

        return $this->json(['ok' => true, 'message' => 'The access matrix was saved. It applies to everybody within a minute.', 'matrix' => \App\Libraries\Roles::matrix()]);
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
        $url = rtrim(trim((string) ($p['url'] ?? '')) ?: SystemSettings::serviceUrl(), '/');
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

    /** GET settings/notifier-token: the saved Notifier app token (Admin only). POST: makes a new one and saves it. */
    public function notifierToken()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        if ($this->request->getMethod() === 'post') {
            $t = bin2hex(random_bytes(12));
            SystemSettings::set('notify.token', $t, $this->me());

            return $this->json(['ok' => true, 'token' => $t]);
        }

        return $this->json(['ok' => true, 'token' => (string) SystemSettings::get('notify.token', '')]);
    }

    /** GET settings/mail/status: is the Outlook Relay alive, the queue counts and the latest e-mails (Admin only) */
    public function mailStatus()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        \App\Libraries\MailOutbox::ensureTable();
        $db     = \Config\Database::connect();
        $counts = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($db->query('SELECT status, COUNT(*) c FROM tbl_email_outbox GROUP BY status')->getResultArray() as $r) {
            $counts[$r['status']] = (int) $r['c'];
        }
        $rows = $db->query('SELECT id, source, to_address, subject, status, attempts, last_error, created_at, sent_at, next_attempt_at FROM tbl_email_outbox ORDER BY id DESC LIMIT 25')->getResultArray();
        $info = json_decode((string) SystemSettings::get('mail.relay_info', ''), true) ?: [];

        return $this->json([
            'ok'     => true,
            'relay'  => ['online' => \App\Libraries\MailOutbox::relayOnline(), 'seen_at' => (string) SystemSettings::get('mail.relay_seen_at', ''), 'info' => $info, 'has_token' => (string) SystemSettings::get('mail.relay_token', '') !== ''],
            'counts' => $counts,
            'rows'   => $rows,
        ]);
    }

    /** POST settings/mail/retry {id}: a failed (or waiting) e-mail goes to the front of the queue again */
    public function mailRetry()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $id = (int) (($this->request->getJSON(true) ?: [])['id'] ?? 0);
        $db = \Config\Database::connect();
        $db->query("UPDATE tbl_email_outbox SET status = 'queued', attempts = 0, next_attempt_at = NULL, last_error = NULL WHERE id = ? AND status IN ('failed','queued')", [$id]);

        return $this->json(['ok' => $db->affectedRows() > 0]);
    }

    /** POST settings/mail/test {to}: queues a short test e-mail so the whole chain can be checked */
    public function mailTest()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $to = trim((string) (($this->request->getJSON(true) ?: [])['to'] ?? ''));
        [$id, $err] = \App\Libraries\MailOutbox::enqueue($to, 'NEXUS-L test e-mail', '<p>This is a test e-mail from <b>NEXUS-L</b> (Settings &gt; E-mail), sent at ' . date('Y-m-d H:i:s') . '.</p><p>If you can read it, the Outlook Relay chain works.</p>', ['source' => 'NEXUS-L test']);

        return $this->json($id ? ['ok' => true, 'id' => $id] : ['ok' => false, 'message' => $err], $id ? 200 : 422);
    }

    /** GET settings/relay-token: the saved Outlook Relay app token (Admin only). POST: makes a new one and saves it. */
    public function relayToken()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        if ($this->request->getMethod() === 'post') {
            $t = bin2hex(random_bytes(12));
            SystemSettings::set('mail.relay_token', $t, $this->me());

            return $this->json(['ok' => true, 'token' => $t]);
        }

        return $this->json(['ok' => true, 'token' => (string) SystemSettings::get('mail.relay_token', '')]);
    }

    /** GET settings/trainer-token: the saved Brain Trainer app token (Admin only). POST: makes a new one and saves it. */
    public function trainerToken()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        if ($this->request->getMethod() === 'post') {
            $t = bin2hex(random_bytes(12));
            SystemSettings::set('ai.trainer_token', $t, $this->me());

            return $this->json(['ok' => true, 'token' => $t]);
        }

        return $this->json(['ok' => true, 'token' => (string) SystemSettings::get('ai.trainer_token', '')]);
    }

    /** POST settings/ai/test {url?, key?}: is the local Python AI service reachable? */
    public function testAi()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $p   = $this->request->getJSON(true) ?? [];
        $url = rtrim(trim((string) ($p['url'] ?? '')) ?: (string) SystemSettings::get('ai.url', 'http://127.0.0.1:8765'), '/');
        $key = trim((string) ($p['key'] ?? '')) ?: (string) SystemSettings::get('ai.key', '');
        if ($url === '') {
            return $this->json(['ok' => false, 'error' => 'Enter the AI service address first (e.g. http://127.0.0.1:8765).'], 422);
        }
        $out   = ['ok' => true, 'reachable' => false, 'status' => '', 'latency_ms' => 0, 'service' => ''];
        $start = microtime(true);
        try {
            $headers = ['Accept' => 'application/json'];
            if ($key !== '') {
                $headers['X-AI-Key'] = $key;
            }
            $res = service('curlrequest')->get($url . '/health', [
                'timeout'     => 4,
                'http_errors' => false,
                'headers'     => $headers,
            ]);
            $latency = (int) round((microtime(true) - $start) * 1000);
            $out['latency_ms'] = $latency;
            $code = $res->getStatusCode();
            if ($code === 200) {
                $body = json_decode((string) $res->getBody(), true) ?: [];
                $out['reachable']   = true;
                $out['status']      = ! empty($body['ok']) ? 'healthy' : 'degraded';
                $out['service']     = (string) ($body['service'] ?? 'nexus-ai-service');
                $out['docs']        = (int) ($body['docs'] ?? 0);
                $out['corrections'] = (int) ($body['corrections'] ?? 0);
                $out['threshold']   = $body['threshold'] ?? null;
                $out['llm_mode']    = (string) ($body['mode'] ?? 'extractive');
                $out['rasa']        = ! empty($body['rasa']);
            } else {
                $out['error'] = 'HTTP ' . $code . ': ' . substr(strip_tags((string) $res->getBody()), 0, 150);
            }
        } catch (\Throwable $e) {
            $out['latency_ms'] = (int) round((microtime(true) - $start) * 1000);
            $out['error']      = $e->getMessage();
        }

        return $this->json($out);
    }

    /** POST settings/ai/seed: seeds 200 problem questions into tbl_bot_knowledge, exports knowledge.jsonl and reindexes vector store */
    public function seedAiKnowledge()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        try {
            $res = \App\Libraries\ProblemKnowledgeSeeder::run();

            return $this->json(['ok' => true, 'result' => $res]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST settings/git/sync: stages, commits, and pushes all codebase changes to origin/main */
    public function gitSync()
    {
        if (! $this->isAdmin()) {
            return $this->deny();
        }
        $out = [];
        $code = 0;
        @exec('cmd /c "E:\\html\\NEXUS-L\\tools\\auto-push.cmd" 2>&1', $out, $code);

        return $this->json([
            'ok'     => ($code === 0),
            'code'   => $code,
            'output' => implode("\n", $out),
        ]);
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
