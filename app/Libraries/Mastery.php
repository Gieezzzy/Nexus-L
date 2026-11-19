<?php

namespace App\Libraries;

/**
 * Self-quiz with spaced repetition (the idea of the learning-skill repos: mastery checks, review what you got wrong more often).
 * The Brain asks itself questions in the way people really ask (spoken style, typos), checks whether its top answer is the right entry, and remembers
 * the entries it missed. The trainer then gives those entries extra practice until they are answered right again. Read-only for the knowledge itself.
 */
class Mastery
{
    public static function ensureTable(): void
    {
        \Config\Database::connect()->query('CREATE TABLE IF NOT EXISTS tbl_bot_mastery (
            kb_id INT UNSIGNED NOT NULL PRIMARY KEY, tests INT NOT NULL DEFAULT 0, misses INT NOT NULL DEFAULT 0, last_phrase VARCHAR(200) NOT NULL DEFAULT "", last_ok DATETIME NULL, last_miss DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private static function typo(string $q): string
    {
        $w = explode(' ', $q);
        $c = array_keys(array_filter($w, static fn ($x) => mb_strlen($x) >= 6 && ctype_alpha($x)));
        if (! $c) {
            return $q;
        }
        $i = $c[mt_rand(0, count($c) - 1)];
        $x = $w[$i];
        $p = mt_rand(1, mb_strlen($x) - 3);
        $w[$i] = mb_substr($x, 0, $p) . mb_substr($x, $p + 1, 1) . mb_substr($x, $p, 1) . mb_substr($x, $p + 2);   // two neighbouring letters swapped

        return implode(' ', $w);
    }

    /** @return array{asked:int,right:int,accuracy:float,missed:list<array{kb:int,title:string,asked:string,got:string}>} */
    public static function quiz(int $n = 200, int $seed = 0): array
    {
        self::ensureTable();
        mt_srand($seed ?: (int) (microtime(true) * 1000) % 100000);
        $db   = \Config\Database::connect();
        $weak = array_column($db->query('SELECT kb_id FROM tbl_bot_mastery WHERE misses > 0 AND (last_ok IS NULL OR last_miss > last_ok) LIMIT 100')->getResultArray(), 'kb_id');
        $pool = $db->query("SELECT id, title, keywords FROM tbl_bot_knowledge WHERE status = 'approved' AND keywords <> '' AND source IN ('seed','workflow_seed','system_workflow') ORDER BY RAND() LIMIT " . (int) max($n, 50))->getResultArray();
        if ($weak) {   // review what was missed first
            $in   = implode(',', array_map('intval', $weak));
            $pool = array_merge($db->query("SELECT id, title, keywords FROM tbl_bot_knowledge WHERE id IN ({$in})")->getResultArray(), $pool);
        }
        $pool   = array_slice($pool, 0, $n);
        $right  = 0;
        $asked  = 0;
        $missed = [];
        foreach ($pool as $e) {
            $ph = array_values(array_filter(array_map('trim', explode(',', (string) $e['keywords'])), static fn ($p) => mb_strlen($p) >= 8 && mb_strlen($p) <= 70));
            if (! $ph) {
                continue;
            }
            $q = BrainGroups::spoken($ph[mt_rand(0, count($ph) - 1)]);
            if (mt_rand(1, 100) <= 35) {
                $q = self::typo($q);
            }
            $hits = Brain::search(Persona::forSearch($q), ['role' => 'Admin'], 1);
            $asked++;
            $ok = $hits && (int) $hits[0]['row']['id'] === (int) $e['id'];
            $right += $ok ? 1 : 0;
            $db->query('INSERT INTO tbl_bot_mastery (kb_id, tests, misses, last_phrase, last_ok, last_miss) VALUES (?, 1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE tests = tests + 1, misses = misses + VALUES(misses), last_phrase = VALUES(last_phrase),
                last_ok = COALESCE(VALUES(last_ok), last_ok), last_miss = COALESCE(VALUES(last_miss), last_miss)', [(int) $e['id'], $ok ? 0 : 1, mb_substr($q, 0, 200), $ok ? date('Y-m-d H:i:s') : null, $ok ? null : date('Y-m-d H:i:s')]);
            if (! $ok) {
                $missed[] = ['kb' => (int) $e['id'], 'title' => (string) $e['title'], 'asked' => $q, 'got' => $hits ? (string) $hits[0]['row']['title'] : '(no answer)'];
            }
        }

        return ['asked' => $asked, 'right' => $right, 'accuracy' => $asked ? round($right * 100 / $asked, 1) : 0.0, 'missed' => $missed];
    }

    /** entries still missed (the trainer practises them more) */
    public static function weakIds(): array
    {
        try {
            self::ensureTable();

            return array_map('intval', array_column(\Config\Database::connect()->query('SELECT kb_id FROM tbl_bot_mastery WHERE misses > 0 AND (last_ok IS NULL OR last_miss > last_ok) LIMIT 400')->getResultArray(), 'kb_id'));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
