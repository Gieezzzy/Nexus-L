<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;

/**
 * API of the "NEXUS-L Brain Trainer" Windows app (C#). The app reads the knowledge groups, starts / stops a training run
 * (the same `brain:train-night` command that runs overnight, with the options chosen in the app) and follows its progress.
 * No sign-in: the token in Settings > AI (ai.trainer_token) is the key, sent as X-Trainer-Token.
 */
class BrainTrainerController extends BaseController
{
    private const GROUPS = [
        'offtopic'  => 'Questions that are not about the system (greetings, general, refusals)',
        'how'       => 'System how-tos (workflows of NEXUS-L)',
        'method'    => 'Test methods (VF methods, illustrated methods)',
        'standards' => 'Standards and documents (standards database, graph / Obsidian notes, lab documents)',
        'problem'   => 'Problems and solutions (QC lab troubleshooting)',
        'other'     => 'Other approved knowledge (statuses, follow-ups, imports)',
    ];

    private function denied()
    {
        $want = (string) SystemSettings::get('ai.trainer_token', '');
        $sent = (string) $this->request->getHeaderLine('X-Trainer-Token');
        if ($want === '' || ! hash_equals($want, $sent)) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => $want === '' ? 'No Brain Trainer token is set in NEXUS-L (Settings > AI).' : 'Wrong token']);
        }

        return null;
    }

    /** the command-line PHP: under Apache PHP_BINARY is httpd.exe, which must never be run as a command */
    private function php(): string
    {
        if (strcasecmp(basename(PHP_BINARY), 'php.exe') === 0) {
            return PHP_BINARY;
        }
        foreach ([dirname(PHP_BINARY, 2) . '\php\php.exe', PHP_BINDIR . '\php.exe', 'C:\xampp\php\php.exe'] as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return 'php';
    }

    private function dir(): string
    {
        $d = WRITEPATH . 'brain-train/';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    /** the training commands now running (this one or the overnight one) */
    private function running(): array
    {
        $out = (string) shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"name=\'php.exe\'\" | Where-Object { $_.CommandLine -match \'brain:train-night\' } | ForEach-Object { $_.ProcessId }"');
        $ids = array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($out)))));

        return $ids;
    }

    /** GET brain-trainer/info: the knowledge groups with their size, the weights, the measured speed, whether something is training */
    public function info()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $db  = \Config\Database::connect();
        $off = [];
        foreach (\App\Libraries\BrainSeed12::all() as $e) {
            $off[mb_substr($e['title'], 0, 200)] = true;
        }
        $count = array_fill_keys(array_keys(self::GROUPS), 0);
        foreach ($db->query("SELECT title, keywords, kind, source FROM tbl_bot_knowledge WHERE status = 'approved' AND keywords <> ''")->getResultArray() as $r) {
            $ph = array_filter(array_map('trim', explode(',', $r['keywords'])), static fn ($p) => mb_strlen($p) >= 4 && mb_strlen($p) <= 70);
            if (! $ph) {
                continue;
            }
            $g = isset($off[$r['title']]) ? 'offtopic' : (in_array($r['source'], ['system_workflow', 'workflow_seed'], true) ? 'how'
                : (in_array($r['kind'], ['vf_method', 'vf_illustrated'], true) ? 'method' : ($r['source'] === 'standards_db' || $r['source'] === 'graph_obsidian' ? 'standards'
                : ($r['kind'] === 'problem' ? 'problem' : 'other'))));
            $count[$g]++;
        }
        $groups = [];
        foreach (self::GROUPS as $k => $label) {
            $groups[] = ['key' => $k, 'label' => $label, 'entries' => $count[$k]];
        }
        // speed measured in the overnight / earlier runs: seconds per (question x epoch)
        $qe = [];
        $sc = [];
        $log = WRITEPATH . 'logs/night-train.log';
        if (is_file($log)) {
            $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -80);
            foreach ($lines as $l) {
                if (preg_match('/Round \d+: (\d+) questions x (\d+) epochs.*\((\d+) s\)/', $l, $m)) {
                    $qe[] = $m[1] * $m[2];
                    $sc[] = (int) $m[3];
                }
            }
        }

        return $this->response->setJSON([
            'ok'          => true,
            'server_time' => date('c'),
            'groups'      => $groups,
            'entries'     => array_sum($count),
            'weights'     => (int) $db->query('SELECT COUNT(*) c FROM tbl_bot_weights')->getRow()->c,
            'sec_per_qe'  => $qe ? round(array_sum($sc) / array_sum($qe), 8) : null,
            'running_pids' => $this->running(),
        ]);
    }

    /** GET brain-trainer/status: progress of the run (status.json written by the command) */
    public function status()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $f  = $this->dir() . 'status.json';
        $st = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        $pids = $this->running();
        if (($st['state'] ?? '') === 'running' && ! in_array((int) ($st['pid'] ?? 0), $pids, true)) {
            $st['state'] = 'stopped';   // the process is gone without a final message
        }

        $legacy = $pids && (! $st || ! in_array((int) ($st['pid'] ?? 0), $pids, true));   // a run started on the command line (not from this app)

        return $this->response->setJSON(['ok' => true, 'running' => (bool) $pids, 'running_pids' => $pids, 'status' => $st ?: null, 'legacy' => (bool) $legacy,
            'interrupted' => $this->interrupted($pids), 'server_time' => date('c')]);
    }

    /** GET brain-trainer/bench: runs the benchmark (takes some seconds) */
    public function bench()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $out = (string) shell_exec('cd /d ' . escapeshellarg(ROOTPATH) . ' && ' . escapeshellarg($this->php()) . ' spark brain:bench 2>&1');
        $ok  = preg_match('/OVERALL BENCHMARK[^|]*\|\s*([\d.]+)%/', $out, $m);

        return $this->response->setJSON(['ok' => (bool) $ok, 'benchmark' => $ok ? (float) $m[1] : null]);
    }

    /** POST brain-trainer/start {groups[], shares{}, size, epochs, pause, rounds | until, eta, wmax, gate, reindex_every} */
    public function start()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        if ($this->running()) {
            return $this->response->setStatusCode(409)->setJSON(['ok' => false, 'error' => 'A training run is already going (PID ' . implode(', ', $this->running()) . '). Stop it first.']);
        }
        $p      = $this->request->getJSON(true) ?? [];
        $groups = array_values(array_intersect(array_keys(self::GROUPS), (array) ($p['groups'] ?? [])));
        if (! $groups) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Choose at least one knowledge group.']);
        }
        $cfg = [
            'groups'        => $groups,
            'shares'        => is_array($p['shares'] ?? null) ? $p['shares'] : [],
            'size'          => max(100, min(100000, (int) ($p['size'] ?? 3500))),
            'epochs'        => max(1, min(500, (int) ($p['epochs'] ?? 45))),
            'pause'         => max(0, min(3600, (int) ($p['pause'] ?? 60))),
            'eta'           => (float) ($p['eta'] ?? 0.5),
            'wmax'          => (float) ($p['wmax'] ?? 2.0),
            'gate'          => ! array_key_exists('gate', $p) || (bool) $p['gate'],
            'reindex_every' => max(1, (int) ($p['reindex_every'] ?? 8)),
        ];
        if (! empty($p['until'])) {
            try {
                $u = new \DateTime((string) $p['until']);
            } catch (\Throwable $e) {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Bad finish time.']);
            }
            $cfg['until'] = $u->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
        } else {
            $cfg['rounds'] = max(1, min(5000, (int) ($p['rounds'] ?? 10)));
        }
        $file = $this->dir() . 'job-' . date('Ymd-His') . '.json';
        file_put_contents($file, json_encode($cfg, JSON_PRETTY_PRINT));
        @unlink($this->dir() . 'stop');
        @unlink($this->dir() . 'status.json');
        @unlink($this->dir() . 'status.json.last');
        copy($file, $this->dir() . 'job.json');                 // what `brain:train-night --resume` continues
        $bat = $this->dir() . 'run.bat';
        file_put_contents($bat, "@echo off
cd /d \"" . rtrim(ROOTPATH, '\/') . "\"
\"" . $this->php() . "\" spark brain:train-night --config \"" . $file . "\" > \"" . $this->dir() . "run.out\" 2>&1
");
        pclose(popen('start /B "" cmd /c "' . $bat . '"', 'r'));

        return $this->response->setJSON(['ok' => true, 'config' => $cfg]);
    }

    /** the run was cut off (PC restarted, process killed) and can be continued from the rounds already done */
    private function interrupted(array $pids): bool
    {
        $last = json_decode((string) @file_get_contents($this->dir() . 'status.json.last'), true) ?: [];

        return is_file($this->dir() . 'job.json') && in_array($last['state'] ?? '', ['starting', 'running'], true) && ! in_array((int) ($last['pid'] ?? 0), $pids, true) && ! $pids;
    }

    /** POST brain-trainer/resume: carries on an interrupted run (also done by `php spark brain:train-night --resume` when the PC starts) */
    public function resume()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        if ($this->running()) {
            return $this->response->setJSON(['ok' => true, 'message' => 'Already running.']);
        }
        if (! $this->interrupted([])) {
            return $this->response->setStatusCode(409)->setJSON(['ok' => false, 'error' => 'There is no interrupted run to continue.']);
        }
        @unlink($this->dir() . 'stop');
        $bat = $this->dir() . 'resume.bat';
        file_put_contents($bat, "@echo off

cd /d \"" . rtrim(ROOTPATH, '\/') . "\"

\"" . $this->php() . "\" spark brain:train-night --resume >> \"" . $this->dir() . "run.out\" 2>&1

");
        pclose(popen('start /B "" cmd /c "' . $bat . '"', 'r'));

        return $this->response->setJSON(['ok' => true, 'message' => 'Resumed.']);
    }

    /** POST brain-trainer/stop {force?}: asks the run to finish its round and end; force (or a run not started from the app) ends the process now */
    public function stop()
    {
        if ($r = $this->denied()) {
            return $r;
        }
        $p     = $this->request->getJSON(true) ?? [];
        $pids  = $this->running();
        $st    = is_file($this->dir() . 'status.json') ? (json_decode((string) file_get_contents($this->dir() . 'status.json'), true) ?: []) : [];
        $own   = $pids && in_array((int) ($st['pid'] ?? 0), $pids, true);
        $force = ! empty($p['force']) || ($pids && ! $own);
        file_put_contents($this->dir() . 'stop', date('c'));
        if (! $force || ! $pids) {
            return $this->response->setJSON(['ok' => true, 'forced' => false, 'message' => 'Stopping after the current round.']);
        }
        foreach ($pids as $pid) {
            shell_exec('taskkill /PID ' . (int) $pid . ' /T /F 2>&1');
        }
        $st['state'] = 'stopped';
        $st['stopped_by_user'] = true;
        $st['updated'] = date('c');
        file_put_contents($this->dir() . 'status.json', json_encode($st));
        file_put_contents($this->dir() . 'status.json.last', json_encode($st));
        @unlink($this->dir() . 'stop');
        // a run ended in the middle of a round may have left the weights half written: check, and go back to the copy made before the round
        $note = '';
        $out  = (string) shell_exec('cd /d ' . escapeshellarg(ROOTPATH) . ' && ' . escapeshellarg($this->php()) . ' spark brain:bench 2>&1');
        if (! preg_match('/OVERALL BENCHMARK[^|]*\|\s*([\d.]+)%/', $out, $m) || (float) $m[1] < 100.0) {
            $db = \Config\Database::connect();
            if ($db->query("SHOW TABLES LIKE 'tbl_bot_weights_bak_night'")->getNumRows()) {
                $db->query('DELETE FROM tbl_bot_weights');
                $db->query('INSERT INTO tbl_bot_weights SELECT * FROM tbl_bot_weights_bak_night');
                $note = ' The round that was cut off was taken back.';
            }
        }

        return $this->response->setJSON(['ok' => true, 'forced' => true, 'message' => 'Training stopped now.' . $note]);
    }
}
