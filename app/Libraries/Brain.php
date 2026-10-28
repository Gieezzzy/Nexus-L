<?php

namespace App\Libraries;

/**
 * Brain - the memory and search engine of the NEXUS-L help bot. Everything runs locally: no cloud AI, nothing leaves
 * the server.
 *
 * Storage
 *  - Primary store is the database (fast, safe while a disk stalls).
 *  - `flush()` exports new rows as .back files (JSON lines, one object per line) to every Brain folder:
 *      events-YYYY-MM-DD.back   what users did and the errors they hit
 *      chats-YYYY-MM-DD.back    bot answers and the chats with administrators
 *      knowledge.back           every version of every knowledge entry (the bot's learned memory)
 *    Folders: D:\html\Brain (main) and C:\Brain (mirror). `restore()` rebuilds the knowledge table from knowledge.back.
 *
 * Search
 *  - FULLTEXT candidates from the database, re-ranked here with weighted keyword / title / body matches, synonyms,
 *    the page the user is on and the errors the user just hit.
 */
class Brain
{
    public const DIRS = ['D:\\html\\Brain', 'C:\\Brain'];

    private const STOP = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'to', 'of', 'in', 'on', 'at', 'for', 'and', 'or', 'it', 'this', 'that', 'my', 'me', 'i', 'we', 'you', 'your', 'do', 'does', 'did', 'can', 'could', 'should', 'would', 'how', 'what', 'why', 'when', 'please', 'pls', 'help', 'with', 'there', 'about', 'mean', 'means', 'meaning', 'explain', 'tell', 'say', 'im', 'ive', 'its', 'as', 'by', 'from', 'have', 'has', 'had', 'get', 'got', 'need', 'want', 'like', 'any', 'some', 'but', 'so', 'if', 'then', 'just', 'now', 'still'];

    /** word => canonical word, so different wordings of the same thing meet */
    private const SYN = [
        'not' => 'neg', 'cant' => 'neg', 'cannot' => 'neg', 'dont' => 'neg', 'doesnt' => 'neg', 'wont' => 'neg', 'didnt' => 'neg', 'isnt' => 'neg', 'unable' => 'neg', 'never' => 'neg', 'without' => 'neg',
        'pdf' => 'pdf', 'download' => 'pdf', 'export' => 'pdf', 'print' => 'print', 'printing' => 'print', 'printout' => 'print', 'preview' => 'print',
        'blank' => 'blank', 'empty' => 'blank', 'nothing' => 'blank', 'white' => 'blank',
        'error' => 'error', 'errors' => 'error', 'working' => 'error', 'workin' => 'error', 'fail' => 'error', 'failed' => 'error', 'failure' => 'error', 'broken' => 'error', 'problem' => 'error', 'issue' => 'error', 'bug' => 'error', 'crash' => 'error', 'wrong' => 'error',
        'login' => 'login', 'signin' => 'login', 'sign' => 'login', 'logon' => 'login', 'password' => 'password', 'passcode' => 'password', 'forgot' => 'forgot', 'forgotten' => 'forgot', 'reset' => 'forgot', 'recover' => 'forgot',
        'logout' => 'logout', 'signout' => 'logout', 'expired' => 'expire', 'expire' => 'expire', 'timeout' => 'expire', 'session' => 'session',
        'machine' => 'machine', 'xlsx' => 'machine', 'excel' => 'machine', 'csv' => 'machine', 'readings' => 'machine', 'u60' => 'machine', 'outdata' => 'machine', 'spreadsheet' => 'machine',
        'load' => 'load', 'import' => 'load', 'fetch' => 'load', 'pull' => 'load',
        'save' => 'save', 'store' => 'save', 'submit' => 'save', 'keep' => 'save',
        'new' => 'new', 'create' => 'new', 'add' => 'new', 'make' => 'new',
        'edit' => 'edit', 'change' => 'edit', 'modify' => 'edit', 'update' => 'edit',
        'delete' => 'delete', 'remove' => 'delete', 'erase' => 'delete',
        'bonding' => 'bonding', 'shoe' => 'bonding', 'spot' => 'bonding', 'point' => 'point', 'points' => 'point', 'dot' => 'point', 'dots' => 'point',
        'logo' => 'logo', 'satra' => 'logo', 'vf' => 'logo',
        'interlink' => 'interlink', 'intertek' => 'interlink', 'portal' => 'interlink', 'sync' => 'sync', 'synchronize' => 'sync', 'queue' => 'queue', 'queued' => 'queue',
        'designer' => 'designer', 'layout' => 'designer', 'rearrange' => 'designer', 'move' => 'move', 'drag' => 'move',
        'admin' => 'admin', 'administrator' => 'admin', 'supervisor' => 'admin', 'access' => 'permission', 'permission' => 'permission', 'authorized' => 'permission', 'unauthorized' => 'permission', 'denied' => 'permission', 'forbidden' => 'permission',
        'slow' => 'slow', 'lag' => 'slow', 'laggy' => 'slow', 'loading' => 'slow', 'hang' => 'slow', 'hanging' => 'slow', 'frozen' => 'slow', 'stuck' => 'slow', 'freeze' => 'slow',
        'inbox' => 'inbox', 'chat' => 'inbox', 'message' => 'inbox', 'messages' => 'inbox',
        'report' => 'report', 'reports' => 'report', 'record' => 'report', 'records' => 'report',
        'labref' => 'labref', 'lab' => 'lab', 'reference' => 'labref', 'ref' => 'labref',
        'material' => 'material', 'fabric' => 'material', 'leather' => 'material', 'rubber' => 'material', 'tensile' => 'material', 'tear' => 'material',
        'calculator' => 'calc', 'calculators' => 'calc', 'calculate' => 'calc', 'calculation' => 'calc', 'average' => 'calc', 'weight' => 'calc', 'shrinkage' => 'calc',
        'standard' => 'standard', 'standards' => 'standard', 'spec' => 'standard', 'rating' => 'rating', 'pass' => 'pass', 'tolerable' => 'pass',
        'phone' => 'mobile', 'mobile' => 'mobile', 'tablet' => 'mobile', 'sidebar' => 'sidebar', 'menu' => 'sidebar', 'collapse' => 'sidebar',
        'register' => 'register', 'signup' => 'register', 'account' => 'account', 'profile' => 'account',
        'notfound' => 'notfound', '404' => 'notfound', '500' => 'server', '401' => 'login', '403' => 'permission', 'server' => 'server',
    ];

    // ----------------------------------------------------------------------------------------------- folders

    /** @return list<string> */
    public static function dirs(): array
    {
        $dirs = self::DIRS;
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('tbl_app_settings')) {
                $row = $db->table('tbl_app_settings')->where('skey', 'brain_dirs')->get()->getRowArray();
                $j   = json_decode((string) ($row['svalue'] ?? ''), true);
                if (is_array($j) && $j) {
                    $dirs = array_values(array_filter(array_map('strval', $j)));
                }
            }
        } catch (\Throwable $e) {
            // defaults
        }

        return $dirs;
    }

    /** @return array<string,array{ok:bool,writable:bool,free_gb:?float}> */
    public static function dirStatus(): array
    {
        $out = [];
        foreach (self::dirs() as $d) {
            if (! is_dir($d)) {
                @mkdir($d, 0775, true);
            }
            $ok = is_dir($d);
            $out[$d] = [
                'ok'       => $ok,
                'writable' => $ok && is_writable($d),
                'free_gb'  => $ok ? round((float) @disk_free_space($d) / 1073741824, 1) : null,
            ];
        }

        return $out;
    }

    private static function append(string $file, array $lines): void
    {
        if (! $lines) {
            return;
        }
        $payload = '';
        foreach ($lines as $l) {
            $payload .= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
        foreach (self::dirs() as $d) {
            if (! is_dir($d)) {
                @mkdir($d, 0775, true);
            }
            if (is_dir($d)) {
                @file_put_contents($d . DIRECTORY_SEPARATOR . $file, $payload, FILE_APPEND | LOCK_EX);
            }
        }
    }

    /** Exports new events / chats / knowledge versions as .back files (called every minute by the worker). */
    public static function flush(int $limit = 2000): array
    {
        $db  = \Config\Database::connect();
        $out = ['events' => 0, 'chats' => 0, 'knowledge' => 0];

        $rows = $db->table('tbl_bot_events')->where('flushed', 0)->orderBy('id')->limit($limit)->get()->getResultArray();
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[substr((string) $r['created_at'], 0, 10)][] = ['type' => 'event'] + $r;
        }
        foreach ($byDay as $day => $lines) {
            self::append("events-{$day}.back", $lines);
        }
        if ($rows) {
            $db->table('tbl_bot_events')->whereIn('id', array_column($rows, 'id'))->update(['flushed' => 1]);
            $out['events'] = count($rows);
        }

        $rows = $db->table('tbl_bot_chats')->where('flushed', 0)->orderBy('id')->limit($limit)->get()->getResultArray();
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[substr((string) $r['created_at'], 0, 10)][] = ['type' => 'chat'] + $r;
        }
        foreach ($byDay as $day => $lines) {
            self::append("chats-{$day}.back", $lines);
        }
        if ($rows) {
            $db->table('tbl_bot_chats')->whereIn('id', array_column($rows, 'id'))->update(['flushed' => 1]);
            $out['chats'] = count($rows);
        }

        $rows = $db->query('SELECT * FROM tbl_bot_knowledge WHERE flushed_at IS NULL OR flushed_at < updated_at ORDER BY id LIMIT ' . (int) $limit)->getResultArray();
        if ($rows) {
            $now = date('Y-m-d H:i:s');
            self::append('knowledge.back', array_map(static fn ($r) => ['type' => 'knowledge', 'saved_at' => $now] + $r, $rows));
            $db->table('tbl_bot_knowledge')->whereIn('id', array_column($rows, 'id'))->update(['flushed_at' => $now]);
            $out['knowledge'] = count($rows);
        }

        return $out;
    }

    /** Rebuilds the knowledge table from knowledge.back (latest version of every entry wins). Returns rows restored. */
    public static function restore(): int
    {
        $db   = \Config\Database::connect();
        $best = [];
        foreach (self::dirs() as $d) {
            $f = $d . DIRECTORY_SEPARATOR . 'knowledge.back';
            if (! is_file($f)) {
                continue;
            }
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $r = json_decode($line, true);
                if (is_array($r) && isset($r['id'])) {
                    $id = (int) $r['id'];
                    if (! isset($best[$id]) || strcmp((string) ($r['updated_at'] ?? ''), (string) ($best[$id]['updated_at'] ?? '')) >= 0) {
                        $best[$id] = $r;
                    }
                }
            }
        }
        $n = 0;
        foreach ($best as $id => $r) {
            unset($r['type'], $r['saved_at']);
            $exists = $db->table('tbl_bot_knowledge')->where('id', $id)->countAllResults() > 0;
            $exists ? $db->table('tbl_bot_knowledge')->where('id', $id)->update($r) : $db->table('tbl_bot_knowledge')->insert($r);
            $n++;
        }

        return $n;
    }

    /** One readable Markdown file per approved playbook, so the Brain folder opens nicely in Obsidian. */
    public static function exportMarkdown(): int
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('tbl_bot_knowledge')->where('status', 'approved')->orderBy('kind')->orderBy('title')->get()->getResultArray();
        $n    = 0;
        foreach (self::dirs() as $d) {
            $dir = $d . DIRECTORY_SEPARATOR . 'playbooks';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (! is_dir($dir)) {
                continue;
            }
            $n = 0;
            foreach ($rows as $r) {
                $steps = json_decode((string) ($r['steps_json'] ?? ''), true) ?: [];
                $md    = "---\nid: {$r['id']}\nkind: {$r['kind']}\nrole: {$r['role_min']}\nkeywords: {$r['keywords']}\n---\n# {$r['title']}\n\n{$r['body']}\n";
                if ($steps) {
                    $md .= "\n## Steps\n";
                    foreach ($steps as $i => $s) {
                        $md .= ($i + 1) . '. ' . $s . "\n";
                    }
                }
                $name = preg_replace('/[^A-Za-z0-9 _-]+/', '', (string) $r['title']);
                @file_put_contents($dir . DIRECTORY_SEPARATOR . sprintf('%04d - %s.md', $r['id'], mb_substr(trim($name), 0, 70)), $md);
                $n++;
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------------------------------------ search

    /** @return list<string> canonical tokens */
    public static function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $out  = [];
        foreach (preg_split('/\s+/', trim((string) $text)) ?: [] as $w) {
            if ($w === '' || in_array($w, self::STOP, true)) {
                continue;
            }
            if (isset(self::SYN[$w])) {
                $out[] = self::SYN[$w];
                continue;
            }
            // light stemming
            foreach (['ing', 'ed', 'es', 's'] as $suf) {
                if (strlen($w) > strlen($suf) + 3 && str_ends_with($w, $suf)) {
                    $w = substr($w, 0, -strlen($suf));
                    break;
                }
            }
            $out[] = self::SYN[$w] ?? $w;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array{page?:string,role?:string,errors?:list<string>} $ctx
     *
     * @return list<array{row:array,score:float}> best first
     */
    public static function search(string $question, array $ctx = [], int $limit = 5): array
    {
        $db     = \Config\Database::connect();
        $q      = self::tokens($question);
        $isAdmin = strcasecmp((string) ($ctx['role'] ?? ''), 'admin') === 0;
        if (! $q) {
            return [];
        }

        // 1. candidates: FULLTEXT first, LIKE as the safety net, plus entries bound to this page / these errors
        $ids = [];
        try {
            $boolean = implode(' ', array_map(static fn ($t) => '' . preg_replace('/[^\p{L}\p{N}]/u', '', $t) . '*', $q));
            $rows    = $db->query('SELECT id FROM tbl_bot_knowledge WHERE status = \'approved\' AND MATCH(title, keywords, body) AGAINST (? IN BOOLEAN MODE) LIMIT 80', [$boolean])->getResultArray();
            $ids     = array_column($rows, 'id');
        } catch (\Throwable $e) {
            // no fulltext index
        }
        $builder = $db->table('tbl_bot_knowledge')->where('status', 'approved');
        $cand    = [];
        if ($ids) {
            $cand = $builder->whereIn('id', $ids)->get()->getResultArray();
        }
        if (count($cand) < 8) {
            $b = $db->table('tbl_bot_knowledge')->where('status', 'approved')->groupStart();
            foreach (array_slice($q, 0, 6) as $t) {
                $b->orLike('keywords', $t)->orLike('title', $t);
            }
            $more = $b->groupEnd()->limit(120)->get()->getResultArray();
            $seen = array_column($cand, 'id');
            foreach ($more as $m) {
                if (! in_array($m['id'], $seen, true)) {
                    $cand[] = $m;
                }
            }
        }
        $page = strtolower((string) ($ctx['page'] ?? ''));
        if ($page !== '') {
            $more = $db->table('tbl_bot_knowledge')->where('status', 'approved')->like('page_match', explode('/', trim($page, '/'))[0] ?? '')->limit(40)->get()->getResultArray();
            $seen = array_column($cand, 'id');
            foreach ($more as $m) {
                if (! in_array($m['id'], $seen, true)) {
                    $cand[] = $m;
                }
            }
        }

        // 2. score
        $scored = [];
        foreach ($cand as $r) {
            if (($r['role_min'] ?? 'user') === 'admin' && ! $isAdmin) {
                continue;
            }
            $kw    = self::tokens((string) $r['keywords']);
            $ti    = self::tokens((string) $r['title']);
            $bo    = self::tokens((string) $r['body']);
            $score = 0.0;
            foreach ($q as $t) {
                if (in_array($t, $kw, true)) {
                    $score += 3.0;
                }
                if (in_array($t, $ti, true)) {
                    $score += 2.5;
                }
                if (in_array($t, $bo, true)) {
                    $score += 0.8;
                }
            }
            // phrase bonus: the whole question inside keywords/title
            $ql = mb_strtolower($question);
            foreach (array_filter(array_map('trim', explode(',', mb_strtolower((string) $r['keywords'])))) as $phrase) {
                if (strlen($phrase) > 5 && str_contains($ql, $phrase)) {
                    $score += 4.0;
                }
            }
            if ($score <= 0) {
                continue;
            }
            // coverage: share of the question that this entry explains
            $hit   = count(array_intersect($q, array_merge($kw, $ti, $bo)));
            $score *= 0.6 + 0.8 * ($hit / max(1, count($q)));
            // context: page and recent errors
            if ($page !== '' && ! empty($r['page_match']) && str_contains($page, strtolower((string) $r['page_match']))) {
                $score *= 1.25;
            }
            foreach ((array) ($ctx['errors'] ?? []) as $e) {
                if (! empty($r['error_match']) && stripos((string) $e, (string) $r['error_match']) !== false) {
                    $score *= 1.6;
                    break;
                }
            }
            $score *= 1 + min(0.3, max(-0.3, ((int) $r['helpful']) / 40));
            $scored[] = ['row' => $r, 'score' => round($score, 2)];
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /** @return int 0..100 confidence of the best hit against a perfect hit for this question */
    public static function confidence(array $hits, string $question): int
    {
        if (! $hits) {
            return 0;
        }
        $n    = max(1, count(self::tokens($question)));
        $best = $hits[0]['score'];
        $c    = (int) round(min(100, ($best / (3.4 * $n + 2)) * 100));
        if (isset($hits[1]) && $hits[1]['score'] > $best * 0.9) {
            $c = (int) round($c * 0.9);   // two equally good answers: less sure
        }

        return $c;
    }

    // ------------------------------------------------------------------------------------------- diagnostics

    /** Checks the bot can run for the user ("solve the problem"). @return array{ok:bool,text:string} */
    public static function diagnose(string $key): array
    {
        $db = \Config\Database::connect();
        switch ($key) {
            case 'machine_share':
                $root = MachineFileReader::root();
                $ok   = @is_dir($root);

                return ['ok' => $ok, 'text' => $ok ? 'The machine-file location is reachable (' . $root . ').' : 'The machine-file location is NOT reachable right now. An administrator must check the PC / share that holds the machine files.'];

            case 'interlink_session':
                return ['ok' => true, 'text' => 'InterLink is used with each person\'s own account in their own browser. NEXUS-L stores no InterLink login, so there is nothing to connect on the server. If InterLink asks you to sign in, sign in again with your own account.'];

            case 'pdf_engine':
                $found = false;
                foreach (['C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'] as $c) {
                    $found = $found || is_file($c);
                }

                return ['ok' => $found, 'text' => $found ? 'The PDF engine (Chrome / Edge) is installed on the server.' : 'Chrome or Edge is missing on the server, so "Download PDF" cannot work. Use "Print Report / Save as PDF" instead and tell an administrator.'];

            case 'worker':
                $f   = Brain::dirs()[0] . DIRECTORY_SEPARATOR . 'knowledge.back';
                $age = is_file($f) ? time() - (int) filemtime($f) : null;
                $ev  = (int) $db->table('tbl_bot_events')->where('flushed', 0)->countAllResults();

                return ['ok' => $ev < 200, 'text' => $ev < 200 ? 'The background task that saves the assistant\'s memory is working (' . $ev . ' item(s) waiting).' : 'Many items are waiting to be saved to the Brain files (' . $ev . '). An administrator should check the scheduled task "NEXUS-L InterLink Worker".'];

            case 'disk':
                $free = @disk_free_space('D:\\');

                return ['ok' => $free !== false && $free > 5e9, 'text' => $free === false ? 'Could not read the disk.' : 'Free space on D: is ' . round($free / 1073741824, 1) . ' GB.'];

            case 'brain':
                $st = self::dirStatus();
                $bad = array_filter($st, static fn ($s) => ! $s['writable']);

                return ['ok' => ! $bad, 'text' => $bad ? 'A Brain folder is not writable: ' . implode(', ', array_keys($bad)) : 'Both Brain folders are writable.'];
        }

        return ['ok' => true, 'text' => ''];
    }

    // ---------------------------------------------------------------------------------------------- helpers

    public static function signature(string $type, string $page, string $detail): string
    {
        $d = preg_replace('/\d+/', '#', mb_strtolower($detail));
        $d = preg_replace('/[a-f0-9]{16,}/', '#', (string) $d);

        return md5($type . '|' . $page . '|' . mb_substr((string) $d, 0, 120));
    }

    /** Removes anything that looks like a secret or personal value from free text before it is stored. */
    public static function redact(string $s): string
    {
        $s = preg_replace('/(password|passwd|pwd|token|cookie|secret|authorization)\s*[=:]\s*\S+/i', '$1=[hidden]', $s);
        $s = preg_replace('/[A-Za-z0-9+\/_-]{40,}/', '[hidden]', (string) $s);
        $s = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[email]', (string) $s);

        return mb_substr(trim((string) $s), 0, 500);
    }
}
