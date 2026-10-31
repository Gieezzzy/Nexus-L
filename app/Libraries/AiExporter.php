<?php

namespace App\Libraries;

/**
 * Writes what the AI service learns from: the approved answers (knowledge.jsonl) and the real questions people typed (questions.jsonl, for
 * the review task). Used by `php spark ai:export` and by the Train AI tab. Nothing leaves this computer; names are never exported and the
 * text is redacted.
 */
class AiExporter
{
    public static function dir(): string
    {
        return rtrim((string) (getenv('AI_HOME') ?: 'C:\\NexusAI'), '\\/');
    }

    /** @return array{answers:int,questions:int,dir:string} */
    public static function run(?string $knowledgePath = null): array
    {
        $dir  = self::dir();
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            throw new \RuntimeException("Cannot create {$dir}");
        }
        $path = $knowledgePath ?: $dir . DIRECTORY_SEPARATOR . 'knowledge.jsonl';
        $db   = \Config\Database::connect();
        $rows = $db->table('tbl_bot_knowledge')->where('status', 'approved')->orderBy('id')->get()->getResultArray();
        $fh   = fopen($path . '.tmp', 'wb');
        foreach ($rows as $r) {
            fwrite($fh, json_encode([
                'id' => (int) $r['id'], 'title' => $r['title'], 'keywords' => (string) $r['keywords'], 'body' => (string) $r['body'],
                'steps' => json_decode((string) $r['steps_json'], true) ?: [], 'page_match' => $r['page_match'], 'role_min' => $r['role_min'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        fclose($fh);
        rename($path . '.tmp', $path);

        $qpath = $dir . DIRECTORY_SEPARATOR . 'questions.jsonl';
        $seen  = [];
        $fh    = fopen($qpath . '.tmp', 'wb');
        $src   = array_merge(
            array_map(static fn ($r) => ['chat', (string) $r['message']], $db->table('tbl_bot_chats')->select('message')->where('sender', 'user')->orderBy('id', 'DESC')->limit(3000)->get()->getResultArray()),
            $db->tableExists('tbl_bot_unanswered') ? array_map(static fn ($r) => ['unanswered', (string) $r['sample']], $db->table('tbl_bot_unanswered')->select('sample')->limit(1000)->get()->getResultArray()) : []
        );
        $n = 0;
        foreach ($src as [$source, $q]) {
            $q = trim(Brain::redact($q));
            $k = mb_strtolower($q);
            if (mb_strlen($q) < 6 || mb_strlen($q) > 300 || isset($seen[$k]) || preg_match('/^(hi|hello|thanks|thank you|ok|yes|no)\b/i', $q)) {
                continue;
            }
            $seen[$k] = true;
            fwrite($fh, json_encode(['q' => $q, 'source' => $source], JSON_UNESCAPED_UNICODE) . "\n");
            $n++;
        }
        fclose($fh);
        rename($qpath . '.tmp', $qpath);

        return ['answers' => count($rows), 'questions' => $n, 'dir' => $dir];
    }
}
