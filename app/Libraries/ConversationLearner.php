<?php

namespace App\Libraries;

/**
 * Learning from real conversations, on its own and within safe limits. Called by the night trainer every few rounds (and by `php spark brain:learn-live`).
 *
 * What it learns by itself (small, reversible, never new facts):
 *  - helpful pairs: a question whose answer was marked Helpful becomes one more way to ask for that answer (an extra training phrase);
 *    the owner's thumb counts three times;
 *  - owner corrections: when the owner answers "no, I meant ..." the earlier question is linked to the topic he named (when that topic is found with high
 *    confidence) and the wrong answer is pushed down one learning step;
 *  - style preferences: a user who asks for "shorter" or "simpler" answers more than once gets them that way (tbl_bot_user_prefs).
 * What it never does by itself: write a new knowledge entry or change a fact. Those stay proposals that an administrator approves (BrainLearn).
 * Everything it learns is written to tbl_bot_learning_log, which is the "what did you learn" report.
 */
class ConversationLearner
{
    public static function ensureTables(): void
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_bot_live_phrases (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kb_id INT UNSIGNED NOT NULL, phrase VARCHAR(160) NOT NULL, phrase_hash CHAR(32) NOT NULL, source VARCHAR(16) NOT NULL,
            weight TINYINT NOT NULL DEFAULT 1, user_id INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, UNIQUE KEY uq_kb_phrase (kb_id, phrase_hash), KEY idx_kb (kb_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_bot_user_prefs (
            user_id INT NOT NULL PRIMARY KEY, brief INT NOT NULL DEFAULT 0, simple INT NOT NULL DEFAULT 0, steps INT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_bot_learning_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, created_at DATETIME NOT NULL, kind VARCHAR(24) NOT NULL, n INT NOT NULL DEFAULT 0, detail VARCHAR(400) NOT NULL DEFAULT "", KEY idx_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private static function log(string $kind, int $n, string $detail = ''): void
    {
        if ($n > 0 || $kind === 'ingest') {
            \Config\Database::connect()->table('tbl_bot_learning_log')->insert(['created_at' => date('Y-m-d H:i:s'), 'kind' => $kind, 'n' => $n, 'detail' => mb_substr($detail, 0, 400)]);
        }
    }

    /** the phrase to remember: redacted, lower case, 2..14 words */
    private static function phrase(string $msg): string
    {
        $p = mb_strtolower(trim(Brain::redact($msg)));
        $p = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N}\s\-\.\/]/u', ' ', $p)));

        return (count(explode(' ', $p)) >= 2 && count(explode(' ', $p)) <= 14 && mb_strlen($p) <= 150) ? $p : '';
    }

    private static function addPhrase(int $kb, string $phrase, string $source, int $weight, int $userId): bool
    {
        if ($kb <= 0 || $phrase === '') {
            return false;
        }
        $db = \Config\Database::connect();
        $h  = md5($phrase);
        if ($db->query('SELECT 1 FROM tbl_bot_live_phrases WHERE kb_id = ? AND phrase_hash = ?', [$kb, $h])->getRowArray()) {
            return false;
        }
        $db->table('tbl_bot_live_phrases')->insert(['kb_id' => $kb, 'phrase' => $phrase, 'phrase_hash' => $h, 'source' => $source, 'weight' => max(1, min(5, $weight)), 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    private static function ownerIds(): array
    {
        static $ids = null;

        return $ids ??= array_map('intval', array_column(\Config\Database::connect()->query('SELECT UserID FROM tbl_users WHERE SynologyID = ?', [Persona::OWNER_SYNOLOGY_ID])->getResultArray(), 'UserID'));
    }

    /**
     * Reads the conversations since the last run. @return array{helpful:int,owner_fix:int,prefs:int,chats:int}
     */
    public static function ingest(int $maxChats = 4000): array
    {
        self::ensureTables();
        $db    = \Config\Database::connect();
        $last  = (int) SystemSettings::get('brain.live.last_chat_id', '0');
        $rows  = $db->query("SELECT c.id, c.thread_id, c.sender, c.message, c.knowledge_id, c.feedback, t.user_id FROM tbl_bot_chats c JOIN tbl_bot_threads t ON t.id = c.thread_id
            WHERE c.id > ? ORDER BY c.id LIMIT ?", [$last, $maxChats])->getResultArray();
        $out    = ['helpful' => 0, 'owner_fix' => 0, 'prefs' => 0, 'chats' => count($rows)];
        $owners = self::ownerIds();
        $byThread = [];
        $max    = $last;
        foreach ($rows as $r) {
            $max = max($max, (int) $r['id']);
            $uid = (int) $r['user_id'];
            if ($r['sender'] === 'user') {
                // style preferences from what the user asks for
                $m = mb_strtolower((string) $r['message']);
                $set = [];
                if (preg_match('/\b(shorter|briefly|in short|too long|summary only|make it short|just the answer)\b/u', $m)) {
                    $set[] = 'brief';
                }
                if (preg_match('/\b(simpler|simple words|in simple|eli5|too technical|do not understand|don\'?t understand)\b/u', $m)) {
                    $set[] = 'simple';
                }
                if (preg_match('/\b(step by step|steps please|walk me through)\b/u', $m)) {
                    $set[] = 'steps';
                }
                foreach ($set as $col) {
                    $db->query("INSERT INTO tbl_bot_user_prefs (user_id, {$col}, updated_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE {$col} = {$col} + 1, updated_at = NOW()", [$uid]);
                    $out['prefs']++;
                }
                // an owner correction: "no, I meant X" / "I was asking about X"
                if (in_array($uid, $owners, true) && preg_match('/^(?:no|nope|not that|wrong|that\'?s not it|actually)?[\s,.\-]*(?:i meant|i mean|what i meant is|i was asking (?:about|for)|i am asking (?:about|for))\s+(.{4,120})$/iu', trim((string) $r['message']), $mm)) {
                    $prev = $byThread[$r['thread_id']]['q'] ?? null;
                    $bot  = $byThread[$r['thread_id']]['kb'] ?? null;
                    if ($prev) {
                        $hits = Brain::search($mm[1], ['role' => 'Admin'], 2);
                        if ($hits && Brain::confidence($hits, $mm[1]) >= 70) {
                            $kb = (int) $hits[0]['row']['id'];
                            if (self::addPhrase($kb, self::phrase($prev), 'owner_fix', 3, $uid)) {
                                $out['owner_fix']++;
                                if ($bot && (int) $bot !== $kb) {
                                    BrainLearn::train(Brain::tokens($prev), (int) $bot, 0);   // the answer he did not want moves down one step
                                }
                                self::log('owner_fix', 1, mb_substr($prev, 0, 120) . ' -> ' . $hits[0]['row']['title']);
                            }
                        }
                    }
                }
                $byThread[$r['thread_id']]['q'] = (string) $r['message'];
            } elseif ($r['sender'] === 'bot') {
                $byThread[$r['thread_id']]['kb'] = $r['knowledge_id'];
                if ((int) $r['feedback'] === 1 && (int) $r['knowledge_id'] > 0 && ! empty($byThread[$r['thread_id']]['q'])) {
                    if (self::addPhrase((int) $r['knowledge_id'], self::phrase((string) $byThread[$r['thread_id']]['q']), 'helpful', in_array($uid, $owners, true) ? 3 : 1, $uid)) {
                        $out['helpful']++;
                    }
                }
            }
        }
        // thumbs given after the watermark to older answers are caught by the pairs above only when both lines are new: the rest is already trained by feedback()
        if ($max > $last) {
            SystemSettings::set('brain.live.last_chat_id', (string) $max, 'brain');
        }
        self::log('helpful', $out['helpful'], 'questions marked Helpful became extra training phrases');
        self::log('prefs', $out['prefs'], 'style preferences noticed (shorter / simpler / steps)');
        self::log('ingest', $out['chats'], 'conversation lines read');

        return $out;
    }

    /** the style the user likes: brief when asked for it twice or more */
    public static function prefs(int $userId): array
    {
        static $c = [];
        if (! isset($c[$userId])) {
            try {
                $r = \Config\Database::connect()->query('SELECT brief, simple, steps FROM tbl_bot_user_prefs WHERE user_id = ?', [$userId])->getRowArray() ?: [];
            } catch (\Throwable $e) {
                $r = [];
            }
            $c[$userId] = ['brief' => (int) ($r['brief'] ?? 0) >= 2, 'simple' => (int) ($r['simple'] ?? 0) >= 2, 'steps' => (int) ($r['steps'] ?? 0) >= 2];
        }

        return $c[$userId];
    }

    /** extra training phrases of every entry (the trainer adds them to the entry's own phrases; the owner's count several times) */
    public static function phrasesByEntry(): array
    {
        try {
            $out = [];
            foreach (\Config\Database::connect()->query('SELECT kb_id, phrase, weight FROM tbl_bot_live_phrases ORDER BY id DESC LIMIT 20000')->getResultArray() as $r) {
                for ($i = 0; $i < (int) $r['weight']; $i++) {
                    $out[(int) $r['kb_id']][] = $r['phrase'];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** "what did you learn" for the owner */
    public static function journal(int $hours = 24): array
    {
        self::ensureTables();
        $db   = \Config\Database::connect();
        $rows = $db->query('SELECT kind, SUM(n) n FROM tbl_bot_learning_log WHERE created_at >= ? AND kind <> "ingest" GROUP BY kind', [date('Y-m-d H:i:s', time() - $hours * 3600)])->getResultArray();
        $ing  = (int) ($db->query('SELECT SUM(n) n FROM tbl_bot_learning_log WHERE created_at >= ? AND kind = "ingest"', [date('Y-m-d H:i:s', time() - $hours * 3600)])->getRow()->n ?? 0);
        $phr  = (int) $db->query('SELECT COUNT(*) c FROM tbl_bot_live_phrases')->getRow()->c;

        return ['by_kind' => array_column($rows, 'n', 'kind'), 'chats_read' => $ing, 'live_phrases' => $phr, 'hours' => $hours];
    }
}
