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
 *    Folders: E:\html\Brain (main) and C:\Brain (mirror). `restore()` rebuilds the knowledge table from knowledge.back.
 *
 * Search
 *  - FULLTEXT candidates from the database, re-ranked here with weighted keyword / title / body matches, synonyms,
 *    the page the user is on and the errors the user just hit.
 */
class Brain
{
    public const DIRS = ['E:\\html\\Brain', 'C:\\Brain'];

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

    /** @var array<string,mixed>|null per-process copy of the tokenised knowledge base */
    private static ?array $corpus = null;

    /**
     * The approved knowledge base with every entry already split into words, plus how many entries each word appears in.
     * Cached on disk and rebuilt when an entry is added, edited or removed.
     *
     * @return array{rows:array<int,array>,tok:array<int,array{kw:array,ti:array,bo:array}>,df:array<string,int>,n:int}
     */
    private static function corpus(): array
    {
        $db  = \Config\Database::connect();
        $sig = $db->query('SELECT COUNT(*) c, COALESCE(MAX(updated_at),\'\') u, COALESCE(SUM(helpful),0) h FROM tbl_bot_knowledge WHERE status = \'approved\'')->getRowArray();
        $sig = md5(json_encode($sig) . filesize(__FILE__));
        if (self::$corpus !== null && self::$corpus['sig'] === $sig) {
            return self::$corpus;
        }
        $file = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'brain_corpus.ser';
        if (is_file($file)) {
            $c = @unserialize((string) @file_get_contents($file), ['allowed_classes' => false]);
            if (is_array($c) && ($c['sig'] ?? '') === $sig) {
                return self::$corpus = $c;
            }
        }
        $rows = $tok = $df = [];
        foreach ($db->table('tbl_bot_knowledge')->where('status', 'approved')->get()->getResultArray() as $r) {
            $id        = (int) $r['id'];
            $rows[$id] = $r;
            $kp = [];
            foreach (array_filter(array_map('trim', explode(',', (string) $r['keywords']))) as $phrase) {
                $pt = self::tokens($phrase);
                if ($pt) {
                    $kp[] = $pt;
                }
            }
            $tok[$id]  = ['kw' => self::tokens((string) $r['keywords']), 'ti' => self::tokens((string) $r['title']), 'bo' => self::tokens((string) $r['body']), 'kp' => $kp];
            foreach (array_unique(array_merge($tok[$id]['kw'], $tok[$id]['ti'], $tok[$id]['bo'])) as $w) {
                $df[$w] = ($df[$w] ?? 0) + 1;
            }
        }
        $c = ['sig' => $sig, 'rows' => $rows, 'tok' => $tok, 'df' => $df, 'n' => count($rows)];
        @file_put_contents($file, serialize($c), LOCK_EX);

        return self::$corpus = $c;
    }

    /**
     * Finds the knowledge entries that answer a question by looking at it ONE WORD AT A TIME.
     * Every word is looked up on its own across the whole knowledge base (exact, same stem/prefix, or a one-or-two-letter
     * typo), weighted by how rare it is (a word only a few entries use says far more than "report"), then each entry is
     * ranked by how much of the question's weight it explains, with keywords > title > body.
     *
     * @param array{page?:string,role?:string,errors?:list<string>} $ctx
     *
     * @return list<array{row:array,score:float}> best first
     */
    public static function search(string $question, array $ctx = [], int $limit = 5): array
    {
        $q       = self::tokens($question);
        $isAdmin = strcasecmp((string) ($ctx['role'] ?? ''), 'admin') === 0;
        if (! $q) {
            return [];
        }
        $C  = self::corpus();
        $N  = max(1, $C['n']);
        $df = $C['df'];

        // 1. each question word -> the knowledge-base words it can stand for, with how sure that match is
        $vocab  = array_keys($df);
        $expand = [];
        $idf    = [];
        foreach ($q as $t) {
            $m = [$t => 1.0];
            $L = strlen($t);
            if ($L >= 4) {
                foreach ($vocab as $v) {
                    if ($v === $t) {
                        continue;
                    }
                    $lv = strlen($v);
                    if ($lv >= 4 && (str_starts_with($v, $t) || str_starts_with($t, $v)) && abs($lv - $L) <= 4) {
                        $m[$v] = max($m[$v] ?? 0, 0.75);
                    } elseif ($L >= 5 && abs($lv - $L) <= 2 && levenshtein($t, $v) <= ($L >= 8 ? 2 : 1)) {
                        $m[$v] = max($m[$v] ?? 0, 0.65);
                    }
                }
            }
            $expand[$t] = $m;
            $best       = 0;
            foreach ($m as $v => $f) {
                $best = max($best, $df[$v] ?? 0);
            }
            $idf[$t] = $best > 0 ? log(1 + $N / (1 + $best)) : 0.0;     // 0 = no entry uses this word at all
        }
        $known = array_filter($idf);
        if (! $known) {
            return [];
        }
        $mean = array_sum($known) / count($known);
        $w    = [];
        foreach ($q as $t) {
            $w[$t] = $idf[$t] > 0 ? $idf[$t] / $mean : 0.4;              // a word nobody uses still counts a little against an entry
        }
        $wTotal = array_sum($w);

        // 2. every entry that contains at least one of the words, scored by what it explains
        $page = strtolower((string) ($ctx['page'] ?? ''));
        $ql   = mb_strtolower($question);
        $FW   = ['kw' => 3.0, 'ti' => 2.5, 'bo' => 0.8];
        $scored = [];
        // what earlier thumbs-up / thumbs-down taught: weight[answer][word]; '#' is the answer's own bias (BrainLearn::train)
        $LW = [];
        try {
            $db = \Config\Database::connect();
            if (self::learnedTable($db)) {
                foreach ($db->table('tbl_bot_weights')->select('kb_id, term, w, updates')->whereIn('term', array_merge(['#'], $q))->get()->getResultArray() as $lw) {
                    $LW[(int) $lw['kb_id']][$lw['term']] = (float) $lw['w'];
                    if ($lw['term'] === '#') {
                        $LW[(int) $lw['kb_id']]['votes'] = (int) $lw['updates'];
                    }
                }
            }
        } catch (\Throwable $e) {
            $LW = [];
        }
        foreach ($C['rows'] as $id => $r) {
            if (($r['role_min'] ?? 'user') === 'admin' && ! $isAdmin) {
                continue;
            }
            $T       = $C['tok'][$id];
            $score   = 0.0;
            $covered = 0.0;
            foreach ($q as $t) {
                $top = 0.0;
                foreach ($FW as $f => $fw) {
                    foreach ($expand[$t] as $v => $factor) {
                        if ($factor * $fw > $top && in_array($v, $T[$f], true)) {
                            $top = $factor * $fw;
                            if ($factor === 1.0) {
                                break;
                            }
                        }
                    }
                }
                if ($top > 0) {
                    $score   += $w[$t] * $top;
                    $covered += $w[$t] * min(1.0, $top / 0.8);
                }
            }
            if ($score <= 0) {
                continue;
            }
            // words that sit next to each other in the question and in the title / keywords: a phrase, not just two words
            for ($i = 0; $i + 1 < count($q); $i++) {
                $pair = $q[$i] . ' ' . $q[$i + 1];
                if (str_contains(implode(' ', $T['ti']), $pair) || str_contains(implode(' ', $T['kw']), $pair)) {
                    $score += 1.5 * min($w[$q[$i]], $w[$q[$i + 1]]);
                }
            }
            foreach (array_filter(array_map('trim', explode(',', mb_strtolower((string) $r['keywords'])))) as $phrase) {
                if (strlen($phrase) > 5 && str_contains($ql, $phrase)) {
                    $score += 4.0;
                }
            }
            // a keyword phrase of 2+ words whose every word is in the question (in any order, typos allowed) is a strong sign
            $wm = $wTotal / count($q);
            foreach ($T['kp'] as $P) {
                if (count($P) < 2) {
                    continue;
                }
                $all = true;
                foreach ($P as $p) {
                    $found = false;
                    foreach ($expand as $m) {
                        if (isset($m[$p])) {
                            $found = true;
                            break;
                        }
                    }
                    if (! $found) {
                        $all = false;
                        break;
                    }
                }
                if ($all) {
                    $score += 1.2 * count($P) * $wm;
                }
            }
            // how much of the TITLE the question accounts for: "What is Ross Flex?" beats an overview that merely lists it
            if ($T['ti']) {
                $tc = 0;
                foreach ($T['ti'] as $p) {
                    foreach ($expand as $m) {
                        if (isset($m[$p])) {
                            $tc++;
                            break;
                        }
                    }
                }
                $score *= 1 + 0.5 * ($tc / count($T['ti']));
            }
            $score *= 0.5 + 1.0 * ($covered / max(0.0001, $wTotal));
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
            if (isset($LW[$id])) {
                $z = $LW[$id]['#'] ?? 0.0;
                foreach ($q as $t) {
                    $z += $LW[$id][$t] ?? 0.0;
                }
                $score *= 0.7 + 0.6 / (1 + exp(-$z));      // 1.0 when nothing is learned, 0.7 .. 1.3 when it is
            }
            $scored[] = ['row' => $r, 'score' => round($score, 2)];
        }
        // an answer the users taught for THESE words (two or more votes) is a candidate even when it shares none of them,
        // at a modest score: four tenths of a perfect hit at most, so one wording that was marked helpful never beats a real match
        $have = [];
        foreach ($scored as $si => $s) {
            $have[(int) $s['row']['id']] = $si;
        }
        foreach ($LW as $id => $m) {
            if (! isset($C['rows'][$id]) || ($m['votes'] ?? 0) < 2) {
                continue;
            }
            if (($C['rows'][$id]['role_min'] ?? 'user') === 'admin' && ! $isAdmin) {
                continue;
            }
            $z = 0.0;
            foreach ($q as $t) {
                $z += $m[$t] ?? 0.0;
            }
            if ($z <= 0) {
                continue;
            }
            $ls = round((1 / (1 + exp(-($z + ($m['#'] ?? 0.0)))) - 0.5) * 2 * 0.4 * (3.4 * count($q) + 2), 2);
            if (isset($have[$id])) {
                $scored[$have[$id]]['score'] = max($scored[$have[$id]]['score'], $ls);
            } else {
                $scored[] = ['row' => $C['rows'][$id], 'score' => $ls];
            }
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    private static function learnedTable(\CodeIgniter\Database\BaseConnection $db): bool
    {
        static $ok = null;

        return $ok ??= $db->tableExists('tbl_bot_weights');
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
                $free = @disk_free_space(substr(ROOTPATH, 0, 3));

                return ['ok' => $free !== false && $free > 5e9, 'text' => $free === false ? 'Could not read the disk.' : 'Free space on ' . substr(ROOTPATH, 0, 2) . ' is ' . round($free / 1073741824, 1) . ' GB.'];

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
