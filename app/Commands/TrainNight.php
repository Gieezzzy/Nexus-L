<?php

namespace App\Commands;

use App\Libraries\Brain;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Overnight training of the AI Brain: rounds of freshly worded questions (different openings and endings, filler words, and the typing
 * mistakes people really make) over EVERY approved knowledge entry - system how-tos, test methods, standards, lab documents, Lab Reference
 * and approval statuses, follow-ups and the answers to questions that are not about the system - each round learned over 45 epochs.
 * Safety: before every round the weights are copied; after the round the benchmark (brain:bench) must still be 100%, otherwise the round
 * is taken back and the next one is smaller; three taken-back rounds in a row stop the run. The index of the AI service is rebuilt every
 * few rounds and at the end. It runs until the given time (default 05:00, Asia/Manila) and writes writable/logs/night-train.log.
 * The learning step is that of BrainLearn::train() (same step and limit), done in memory; the bias weight of an entry is never changed.
 */
class TrainNight extends BaseCommand
{
    protected $group       = 'Brain';
    protected $name        = 'brain:train-night';
    protected $description = 'Overnight training rounds (45 epochs each) over all entries until a given time, with a benchmark gate after every round.';
    protected $usage       = 'brain:train-night [until HH:MM] [epochs] [pause seconds] [--config file.json]';

    private float $eta  = 0.5;
    private float $wmax = 2.0;

    private $db;
    private string $log = '';
    /** options of a run started from the Brain Trainer app (see BrainTrainerController): groups, shares, size, epochs, pause, rounds, until, gate, reindex_every */
    private array $cfg = [];
    private array $status = [];
    private string $statusFile = '';

    public function run(array $params)
    {
        $tz     = new \DateTimeZone('Asia/Manila');
        $cf     = CLI::getOption('config');
        $resume = CLI::getOption('resume') !== null && CLI::getOption('resume') !== false;   // carry on an interrupted run (PC restarted, process killed)
        if ($resume) {
            $cf   = WRITEPATH . 'brain-train/job.json';
            $last = json_decode((string) @file_get_contents(WRITEPATH . 'brain-train/status.json.last'), true) ?: [];
            if (! is_file($cf) || ! in_array($last['state'] ?? '', ['starting', 'running'], true)) {
                CLI::write('Nothing to resume.');

                return 0;
            }
            $alive = $this->alive((int) ($last['pid'] ?? 0));
            if ($alive !== '') {
                CLI::write('The training is still running (PID ' . $alive . ').');

                return 0;
            }
            $lock = WRITEPATH . 'brain-train/resume.lock';   // two starters (the app and the start-up task) must not both resume
            if (is_file($lock) && filemtime($lock) > time() - 120) {
                CLI::write('Another resume is already starting.');

                return 0;
            }
            @file_put_contents($lock, date('c'));
            for ($i = 0; $i < 40; $i++) {                      // after a restart the database may still be starting
                try {
                    \Config\Database::connect()->query('SELECT 1');
                    break;
                } catch (\Throwable $e) {
                    sleep(5);
                }
            }
        }
        if ($cf && is_file($cf)) {
            $this->cfg = json_decode((string) file_get_contents($cf), true) ?: [];
        }
        $c      = $this->cfg;
        if (! empty($c['until'])) {
            $until = new \DateTime($c['until'], $tz);
        } elseif (! empty($c['rounds'])) {
            $until = new \DateTime('+10 years', $tz);   // limited by the number of rounds instead
        } else {
            $until = new \DateTime('today ' . ($params[0] ?? '05:00'), $tz);
            if ($until <= new \DateTime('now', $tz)) {
                $until->modify('+1 day');
            }
        }
        $maxRounds = max(0, (int) ($c['rounds'] ?? 0));
        $epochs = max(1, min(500, (int) ($c['epochs'] ?? $params[1] ?? 45)));
        $pause  = max(0, (int) ($c['pause'] ?? $params[2] ?? 240));
        $this->eta  = max(0.01, min(2.0, (float) ($c['eta'] ?? 0.5)));
        $this->wmax = max(0.5, min(10.0, (float) ($c['wmax'] ?? 2.0)));
        $gate       = ! array_key_exists('gate', $c) || (bool) $c['gate'];
        $reEvery    = max(1, (int) ($c['reindex_every'] ?? 8));
        $this->statusFile = WRITEPATH . 'brain-train/status.json';
        @mkdir(dirname($this->statusFile), 0775, true);
        @unlink(WRITEPATH . 'brain-train/stop');
        $this->status = ['state' => 'starting', 'pid' => getmypid(), 'started' => date('c'), 'until' => $maxRounds ? null : $until->format('c'), 'max_rounds' => $maxRounds,
            'epochs' => $epochs, 'pause' => $pause, 'size' => (int) ($c['size'] ?? 3500), 'groups' => $c['groups'] ?? 'all', 'round' => 0, 'good' => 0, 'taken_back' => 0, 'benchmark' => null,
            'last_round_seconds' => null, 'avg_round_seconds' => null, 'log' => []];
        if ($resume) {   // go on from the rounds already done
            $prev = json_decode((string) @file_get_contents($this->statusFile . '.last'), true) ?: [];
            foreach (['started', 'round', 'good', 'taken_back', 'avg_round_seconds', 'last_round_seconds'] as $k) {
                if (isset($prev[$k])) {
                    $this->status[$k] = $prev[$k];
                }
            }
            $this->status['resumed'] = (int) ($prev['resumed'] ?? 0) + 1;
            $this->status['log']     = array_slice($prev['log'] ?? [], -40);
        }
        $this->db  = \Config\Database::connect();
        $this->log = WRITEPATH . 'logs/night-train.log';
        @mkdir(dirname($this->log), 0775, true);
        $this->say($resume ? '=== Training RESUMED after an interruption: carrying on from round ' . ((int) $this->status['round'] + 1) . ' ===' : "=== Night training started; runs until " . $until->format('Y-m-d H:i') . " (Asia/Manila), {$epochs} epochs per round ===");

        $pool = $this->pool();
        $this->say('Entries in the pool: ' . count($pool['all']) . ' (' . implode(', ', array_map(static fn ($k, $v) => "$k " . count($v), array_keys($pool['groups']), $pool['groups'])) . ')');
        $size   = max(100, min(100000, (int) ($c['size'] ?? 3500)));
        $round  = $resume ? (int) $this->status['round'] : 0;
        $secs   = [];
        $bad    = 0;
        $good   = $resume ? (int) $this->status['good'] : 0;
        $base   = $this->bench();
        if ($resume && ($base === null || $base < 100.0)) {   // the run broke off in the middle of a round: go back to the last accepted state
            $bak = $this->db->query("SHOW TABLES LIKE 'tbl_bot_weights_bak_night'")->getNumRows();
            if ($bak) {
                $this->db->query('DELETE FROM tbl_bot_weights');
                $this->db->query('INSERT INTO tbl_bot_weights SELECT * FROM tbl_bot_weights_bak_night');
                $this->say('The interrupted round was taken back (weights restored from the copy made before it).');
                $base = $this->bench();
            }
        }
        $this->say('Benchmark before the first round: ' . ($base === null ? 'unreadable' : $base . '%'));
        $this->status['benchmark'] = $base;
        $this->status['state']     = 'running';
        $this->save();
        if ($gate && ($base === null || $base < 100.0)) {
            $this->say('The benchmark is not at 100% to begin with: stopping, nothing was changed.');
            $this->status['state'] = 'failed';
            $this->save();

            return 1;
        }

        while (new \DateTime('now', $tz) < $until && (! $maxRounds || $round < $maxRounds)) {
            if (is_file(WRITEPATH . 'brain-train/stop')) {
                $this->say('Stopped from the Brain Trainer app.');
                $this->status['stopped_by_user'] = true;
                break;
            }
            $round++;
            $this->db->query('DROP TABLE IF EXISTS tbl_bot_weights_bak_night');
            $this->db->query('CREATE TABLE tbl_bot_weights_bak_night AS SELECT * FROM tbl_bot_weights');
            $t0   = microtime(true);
            $info = $this->round($pool, $round, $size, $epochs);
            $res  = $this->bench();
            $this->status['benchmark'] = $res;
            if ($gate && ($res === null || $res < 100.0)) {
                $this->db->query('DELETE FROM tbl_bot_weights');
                $this->db->query('INSERT INTO tbl_bot_weights SELECT * FROM tbl_bot_weights_bak_night');
                $bad++;
                $this->status['taken_back']++;
                $size = max(500, (int) ($size / 2));
                $this->say("Round {$round}: benchmark " . ($res ?? 'unreadable') . '% - round taken back; next rounds use ' . $size . ' questions.');
                if ($bad >= 3) {
                    $this->say('Three rounds in a row were taken back: stopping so nothing is made worse.');
                    $this->status['gate_stop'] = true;
                    break;
                }
            } else {
                $bad = 0;
                $good++;
                $took   = microtime(true) - $t0;
                $secs[] = $took;
                $this->status['good'] = $good;
                $this->status['last_round_seconds'] = round($took, 1);
                $this->status['avg_round_seconds']  = round(array_sum($secs) / count($secs), 1);
                $this->say("Round {$round}: {$info} - benchmark " . ($res === null ? '?' : $res) . '% (' . round($took) . ' s)');
                if ($good % $reEvery === 0) {
                    $this->reindex();
                }
            }
            $this->status['round'] = $round;
            $this->save();
            $left = $until->getTimestamp() - time();
            if ($left <= 0 || ($maxRounds && $round >= $maxRounds)) {
                break;
            }
            // pause in short steps so Stop is quick
            for ($w = (int) min($pause, $left); $w > 0; $w -= 2) {
                if (is_file(WRITEPATH . 'brain-train/stop')) {
                    break;
                }
                sleep(min(2, $w));
            }
        }

        $this->reindex();
        $final = $this->bench();
        $rows  = $this->db->query('SELECT COUNT(*) c FROM tbl_bot_weights')->getRow()->c;
        $this->say("=== Night training finished: {$good} good round(s), final benchmark " . ($final ?? '?') . "%, {$rows} weight rows ===");

        $this->status['state']     = ! empty($this->status['stopped_by_user']) ? 'stopped' : (! empty($this->status['gate_stop']) ? 'failed' : 'finished');
        $this->status['finished']  = date('c');
        $this->status['benchmark'] = $final;
        $this->save();
        @unlink(WRITEPATH . 'brain-train/stop');

        return 0;
    }

    /** the PID when a brain:train-night process with that id is running, else '' (a PID of an earlier boot may be reused by something else) */
    private function alive(int $pid): string
    {
        if ($pid <= 0) {
            return '';
        }
        $ps  = "Get-CimInstance Win32_Process -Filter 'ProcessId={$pid}' | Where-Object { \$_.CommandLine -match 'brain:train-night' } | ForEach-Object { \$_.ProcessId }";
        $out = (string) shell_exec('powershell -NoProfile -Command ' . escapeshellarg($ps));

        return trim($out);
    }

    private function say(string $m): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $m;
        CLI::write($line);
        @file_put_contents($this->log, $line . "\n", FILE_APPEND);
        if ($this->statusFile !== '') {
            $this->status['log'][] = $line;
            $this->status['log']   = array_slice($this->status['log'], -60);
            $this->save();
        }
    }

    private function save(): void
    {
        $this->status['updated'] = date('c');
        $json = json_encode($this->status, JSON_UNESCAPED_UNICODE);
        @file_put_contents($this->statusFile, $json);
        @file_put_contents($this->statusFile . '.last', $json);
    }

    /** the overall benchmark accuracy, or null when it cannot be read */
    private function bench(): ?float
    {
        $out = (string) shell_exec('cd /d ' . escapeshellarg(ROOTPATH) . ' && ' . escapeshellarg(PHP_BINARY) . ' spark brain:bench 2>&1');

        return preg_match('/OVERALL BENCHMARK[^|]*\|\s*([\d.]+)%/', $out, $m) ? (float) $m[1] : null;
    }

    private function reindex(): void
    {
        shell_exec('cd /d ' . escapeshellarg(ROOTPATH) . ' && ' . escapeshellarg(PHP_BINARY) . ' spark brain:train 2>&1');
        $this->say('Index of the AI service rebuilt.');
    }

    /** every approved entry with its keyword phrases, in groups that are sampled with different weights */
    private function pool(): array
    {
        $groups = ['offtopic' => [], 'how' => [], 'method' => [], 'standards' => [], 'problem' => [], 'other' => []];
        $all    = [];
        $off    = [];
        foreach (\App\Libraries\BrainSeed12::all() as $e) {
            $off[mb_substr($e['title'], 0, 200)] = true;
        }
        foreach ($this->db->query("SELECT id, title, keywords, kind, source FROM tbl_bot_knowledge WHERE status = 'approved' AND keywords <> ''")->getResultArray() as $r) {
            $ph = array_values(array_filter(array_map('trim', explode(',', $r['keywords'])), static fn ($p) => mb_strlen($p) >= 4 && mb_strlen($p) <= 70));
            if (! $ph) {
                continue;
            }
            $ph[] = mb_strtolower(rtrim($r['title'], '?'));
            $e    = ['id' => (int) $r['id'], 'p' => $ph];
            $g    = isset($off[$r['title']]) ? 'offtopic' : (in_array($r['source'], ['system_workflow', 'workflow_seed'], true) ? 'how'
                : (in_array($r['kind'], ['vf_method', 'vf_illustrated'], true) ? 'method' : ($r['source'] === 'standards_db' || $r['source'] === 'graph_obsidian' ? 'standards'
                : ($r['kind'] === 'problem' ? 'problem' : 'other'))));
            $groups[$g][] = $e;
            $all[]        = $e;
        }

        $want = $this->cfg['groups'] ?? null;
        if (is_array($want) && $want) {
            foreach ($groups as $g => $entries) {
                if (! in_array($g, $want, true)) {
                    $groups[$g] = [];
                }
            }
            $all = array_merge(...array_values($groups));
        }

        return ['groups' => $groups, 'all' => $all];
    }

    /** one typing mistake in one longer word: swapped neighbours, a missing letter or a doubled letter */
    private function typo(string $q): string
    {
        $w = explode(' ', $q);
        $c = array_keys(array_filter($w, static fn ($x) => mb_strlen($x) >= 5 && ctype_alpha($x)));
        if (! $c) {
            return $q;
        }
        $i = $c[mt_rand(0, count($c) - 1)];
        $x = $w[$i];
        $p = mt_rand(1, mb_strlen($x) - 2);
        switch (mt_rand(0, 2)) {
            case 0:
                $x = mb_substr($x, 0, $p) . mb_substr($x, $p + 1, 1) . mb_substr($x, $p, 1) . mb_substr($x, $p + 2);
                break;
            case 1:
                $x = mb_substr($x, 0, $p) . mb_substr($x, $p + 1);
                break;
            default:
                $x = mb_substr($x, 0, $p + 1) . mb_substr($x, $p, 1) . mb_substr($x, $p + 1);
        }
        $w[$i] = $x;

        return implode(' ', $w);
    }

    /** builds one round of $size questions, learns them for $epochs epochs and writes the weights */
    private function round(array $pool, int $round, int $size, int $epochs): string
    {
        mt_srand(7000 + $round * 131);
        $share = ['offtopic' => 0.18, 'how' => 0.16, 'method' => 0.16, 'standards' => 0.22, 'problem' => 0.10, 'other' => 0.18];
        if (! empty($this->cfg['shares']) && is_array($this->cfg['shares'])) {   // the app can set the weight of each group
            foreach ($share as $g => $v) {
                $share[$g] = max(0.0, (float) ($this->cfg['shares'][$g] ?? $v));
            }
        }
        $sum = 0.0;
        foreach ($pool['groups'] as $g => $entries) {
            $sum += $entries ? $share[$g] : 0.0;
        }
        $sum = $sum > 0 ? $sum : 1.0;
        foreach ($share as $g => $v) {
            $share[$g] = $v / $sum;
        }
        $pre   = ['', 'how do i ', 'where can i find ', 'please help me ', 'i want to ', 'can you show me ', 'how to ', 'where is ', 'i need ', 'what is ', 'hi, ', 'can i ', 'show me ', 'tell me ', 'pls ',
            'quick question: ', 'hey lexus, ', 'is there ', 'what about ', 'urgent: ', 'my boss asked about ', 'explain ', 'i forgot ', 'any idea about '];
        $suf   = ['', ' in nexus', ' please', ' pls', ' po', ' thanks', ' for today', ' step by step', ' now', ' again', ' on my phone', ' for the lab', ' quickly', ' ?', ' help'];
        $qs    = [];
        foreach ($pool['groups'] as $g => $entries) {
            if (! $entries) {
                continue;
            }
            $n = (int) round($size * $share[$g]);
            for ($i = 0; $i < $n; $i++) {
                $e = $entries[mt_rand(0, count($entries) - 1)];
                $p = $e['p'][mt_rand(0, count($e['p']) - 1)];
                $q = trim($pre[mt_rand(0, count($pre) - 1)] . $p . $suf[mt_rand(0, count($suf) - 1)]);
                if (mt_rand(1, 100) <= 35) {
                    $q = $this->typo($q);
                }
                $qs[] = [$e['id'], $q];
            }
        }
        $sets = [];
        foreach ($qs as [$id, $q]) {
            $t = array_slice(array_values(array_unique(array_filter(Brain::tokens($q), static fn ($x) => $x !== '' && $x !== '#'))), 0, 12);
            if ($t) {
                $sets[] = [$id, $t];
            }
        }
        $ids = array_values(array_unique(array_column($sets, 0)));
        $w   = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            foreach ($this->db->table('tbl_bot_weights')->whereIn('kb_id', $chunk)->where('term !=', '#')->get()->getResultArray() as $r) {
                $w[(int) $r['kb_id']][$r['term']] = (float) $r['w'];
            }
        }
        $touched = [];
        for ($ep = 1; $ep <= $epochs; $ep++) {
            foreach ($sets as [$kb, $keys]) {
                $z = 0.0;
                foreach ($keys as $k) {
                    $z += $w[$kb][$k] ?? 0.0;
                }
                $e = 1 / (1 + exp(-$z)) - 1;
                foreach ($keys as $k) {
                    $w[$kb][$k] = max(-$this->wmax, min(self::WMAX, ($w[$kb][$k] ?? 0.0) - $this->eta * $e));
                    $touched[$kb][$k] = ($touched[$kb][$k] ?? 0) + 1;
                }
            }
        }
        $rows = [];
        foreach ($touched as $kb => $terms) {
            foreach ($terms as $t => $n) {
                $rows[] = [$kb, $t, $w[$kb][$t], $n];
            }
        }
        $now = date('Y-m-d H:i:s');
        foreach (array_chunk($rows, 500) as $chunk) {
            $ph  = implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, ?)'));
            $arg = [];
            foreach ($chunk as [$kb, $t, $wt, $n]) {
                array_push($arg, $kb, $t, $wt, $n, $now);
            }
            $this->db->query("INSERT INTO tbl_bot_weights (kb_id, term, w, updates, updated_at) VALUES {$ph} ON DUPLICATE KEY UPDATE w = VALUES(w), updates = updates + VALUES(updates), updated_at = VALUES(updated_at)", $arg);
        }

        return count($sets) . " questions x {$epochs} epochs, " . count($rows) . ' weights, ' . count($ids) . ' entries';
    }
}
