<?php

namespace App\Libraries;

/**
 * Auto-learning from what users ask. It only ever PROPOSES: every suggestion waits in tbl_bot_suggestions until an
 * administrator applies (or rejects) it in the Brain console, so a wrong or private answer cannot go live by itself.
 *
 *  gaps        a question with no answer, a low-confidence answer, or an answer the user marked thumbs-down
 *  alias       the user rephrased, then marked the new answer helpful: the first wording is proposed as extra keywords
 *  draft       similar questions were answered by an administrator in a live chat: that answer is proposed as an entry
 *  gap         a group of similar unanswered questions that nobody has answered yet (the administrator writes the answer)
 *
 * Questions are redacted (Brain::redact) before they are stored; no user name is kept in a suggestion.
 */
class BrainLearn
{
    /** Two questions are "the same question" at this word overlap (0-1). */
    private const SAME = 0.55;
    /** A rephrase counts only within this many seconds of the failed question. */
    private const REPHRASE_WITHIN = 900;

    private static function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    public static function ready(): bool
    {
        $db = self::db();

        return $db->tableExists('tbl_bot_suggestions') && $db->fieldExists('reason', 'tbl_bot_unanswered');
    }

    /** @param list<string> $a @param list<string> $b */
    private static function overlap(array $a, array $b): float
    {
        if (! $a || ! $b) {
            return 0.0;
        }
        $i = count(array_intersect($a, $b));

        return $i / max(1, count(array_unique(array_merge($a, $b))));
    }

    /** Records a gap that the plain "no answer" path of the assistant does not see (low confidence, thumbs-down). */
    public static function noteGap(string $text, string $page, string $reason, ?int $kbId = null): void
    {
        if (! self::ready()) {
            return;
        }
        $norm = implode(' ', array_slice(Brain::tokens($text), 0, 12));
        if ($norm === '') {
            return;
        }
        $db  = self::db();
        $now = date('Y-m-d H:i:s');
        $row = $db->table('tbl_bot_unanswered')->where('norm', mb_substr($norm, 0, 200))->get()->getRowArray();
        if ($row) {
            $db->table('tbl_bot_unanswered')->where('id', $row['id'])->set('times', 'times + 1', false)
                ->update(['last_seen' => $now, 'status' => $row['status'] === 'answered' ? 'open' : $row['status'], 'reason' => $reason === 'down' ? 'down' : $row['reason'], 'kb_id' => $kbId ?: $row['kb_id']]);
        } else {
            $db->table('tbl_bot_unanswered')->insert(['norm' => mb_substr($norm, 0, 200), 'sample' => mb_substr(Brain::redact($text), 0, 400), 'page' => mb_substr($page, 0, 160),
                'times' => 1, 'status' => 'open', 'first_seen' => $now, 'last_seen' => $now, 'reason' => $reason, 'kb_id' => $kbId]);
        }
    }

    /**
     * A thumbs-up on a bot answer. If the user asked something just before that the assistant could not answer (or answered with
     * low confidence), the earlier wording is proposed as extra keywords of the answer that finally helped.
     */
    public static function noteHelpful(int $chatId): void
    {
        if (! self::ready()) {
            return;
        }
        $db   = self::db();
        $chat = $db->table('tbl_bot_chats')->where(['id' => $chatId, 'sender' => 'bot'])->get()->getRowArray();
        if (! $chat || ! $chat['knowledge_id']) {
            return;
        }
        $rows = $db->table('tbl_bot_chats')->where('thread_id', $chat['thread_id'])->where('id <=', $chat['id'])->orderBy('id', 'DESC')->limit(8)->get()->getResultArray();
        $rows = array_reverse($rows);
        $last = count($rows) - 1;
        // the question that produced this answer is the user message just before it; look further back for a failed one
        $failedQ = null;
        for ($i = $last - 2; $i >= 1; $i--) {
            if ($rows[$i]['sender'] !== 'bot') {
                continue;
            }
            $weak = ! $rows[$i]['knowledge_id'] || (int) $rows[$i]['confidence'] < 45;
            if ($weak && $rows[$i - 1]['sender'] === 'user' && abs(strtotime($rows[$last]['created_at']) - strtotime($rows[$i - 1]['created_at'])) <= self::REPHRASE_WITHIN) {
                $failedQ = $rows[$i - 1]['message'];
            }
            break;
        }
        if ($failedQ === null) {
            return;
        }
        $kb = $db->table('tbl_bot_knowledge')->where(['id' => (int) $chat['knowledge_id'], 'status' => 'approved'])->get()->getRowArray();
        if (! $kb) {
            return;
        }
        $have = array_merge(Brain::tokens($kb['title'] . ' ' . $kb['keywords']));
        $new  = array_values(array_diff(array_slice(Brain::tokens(Brain::redact($failedQ)), 0, 10), $have));
        if (count($new) < 1) {
            return;
        }
        self::upsert('alias', 'alias:' . $kb['id'] . ':' . md5(implode(' ', $new)), (int) $kb['id'], $kb['title'], implode(', ', $new), null,
            ['question' => mb_substr(Brain::redact($failedQ), 0, 300), 'helped' => $kb['title']]);
    }

    /** @param array<string,mixed> $evidence */
    private static function upsert(string $kind, string $sig, ?int $kbId, string $title, string $keywords, ?string $body, array $evidence, int $times = 1): void
    {
        $db  = self::db();
        $sig = mb_substr($sig, 0, 40);
        $row = $db->table('tbl_bot_suggestions')->where('sig', $sig)->get()->getRowArray();
        if ($row) {
            if ($row['status'] === 'open') {
                $db->table('tbl_bot_suggestions')->where('id', $row['id'])->update(['times' => $kind === 'alias' ? (int) $row['times'] + 1 : $times, 'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
                    'body' => $body ?? $row['body'], 'keywords' => $keywords]);
            }

            return;
        }
        $db->table('tbl_bot_suggestions')->insert(['kind' => $kind, 'sig' => $sig, 'kb_id' => $kbId, 'title' => mb_substr($title, 0, 200), 'keywords' => mb_substr($keywords, 0, 600), 'body' => $body,
            'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE), 'times' => $times, 'status' => 'open', 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** Groups the open gaps and builds draft / gap suggestions. @return array{groups:int,drafts:int,gaps:int} */
    public static function refresh(): array
    {
        $out = ['groups' => 0, 'drafts' => 0, 'gaps' => 0];
        if (! self::ready()) {
            return $out;
        }
        $db   = self::db();
        $open = $db->table('tbl_bot_unanswered')->where('status', 'open')->orderBy('times', 'DESC')->limit(400)->get()->getResultArray();
        // 1. group the near-identical questions
        $groups = [];
        foreach ($open as $r) {
            $tok = Brain::tokens($r['sample'] ?: $r['norm']);
            $hit = null;
            foreach ($groups as $gi => $g) {
                if (self::overlap($tok, $g['tok']) >= self::SAME) {
                    $hit = $gi;
                    break;
                }
            }
            if ($hit === null) {
                $groups[] = ['tok' => $tok, 'rows' => [$r], 'times' => (int) $r['times'], 'down' => $r['reason'] === 'down'];
            } else {
                $groups[$hit]['rows'][] = $r;
                $groups[$hit]['times'] += (int) $r['times'];
                $groups[$hit]['down'] = $groups[$hit]['down'] || $r['reason'] === 'down';
                $groups[$hit]['tok'] = array_values(array_unique(array_merge($groups[$hit]['tok'], $tok)));
            }
        }
        $out['groups'] = count($groups);
        // 2. admin answers from live chats: first user message -> the administrator's replies
        $answers = self::adminAnswers();

        foreach ($groups as $g) {
            // a single question asked once is not worth a suggestion yet
            if ($g['times'] < 2 && ! $g['down']) {
                continue;
            }
            $main   = $g['rows'][0];
            $ids    = array_map(static fn ($r) => (int) $r['id'], $g['rows']);
            $qs     = array_values(array_unique(array_map(static fn ($r) => mb_substr((string) $r['sample'], 0, 200), $g['rows'])));
            $sig    = 'g:' . substr(md5(implode(' ', array_slice($g['tok'], 0, 6))), 0, 30);
            $best   = null;
            $bestSc = 0.0;
            foreach ($answers as $a) {
                $sc = self::overlap($g['tok'], $a['tok']);
                if ($sc > $bestSc) {
                    $bestSc = $sc;
                    $best   = $a;
                }
            }
            $kw = implode(', ', array_slice($g['tok'], 0, 10));
            if ($best && $bestSc >= 0.35) {
                self::upsert('draft', $sig, null, mb_substr((string) $main['sample'], 0, 190), $kw, $best['body'], ['questions' => array_slice($qs, 0, 6), 'unanswered_ids' => $ids, 'from' => 'an administrator\'s reply in a live chat', 'match' => round($bestSc, 2)], $g['times']);
                $out['drafts']++;
            } else {
                self::upsert('gap', $sig, null, mb_substr((string) $main['sample'], 0, 190), $kw, null, ['questions' => array_slice($qs, 0, 6), 'unanswered_ids' => $ids, 'thumbs_down' => $g['down'], 'answered_with' => $main['kb_id']], $g['times']);
                $out['gaps']++;
            }
        }

        return $out;
    }

    /** @return list<array{tok:list<string>,body:string}> */
    private static function adminAnswers(): array
    {
        $db   = self::db();
        $rows = $db->query("SELECT c.thread_id, c.sender, c.message FROM tbl_bot_chats c
            JOIN (SELECT DISTINCT thread_id FROM tbl_bot_chats WHERE sender = 'admin' ORDER BY thread_id DESC LIMIT 150) t ON t.thread_id = c.thread_id
            WHERE c.sender IN ('user', 'admin') ORDER BY c.thread_id, c.id")->getResultArray();
        $by = [];
        foreach ($rows as $r) {
            $t = (int) $r['thread_id'];
            $by[$t] ??= ['q' => '', 'a' => []];
            if ($r['sender'] === 'user' && $by[$t]['q'] === '') {
                $by[$t]['q'] = Brain::redact($r['message']);
            } elseif ($r['sender'] === 'admin') {
                $by[$t]['a'][] = trim($r['message']);
            }
        }
        $out = [];
        foreach ($by as $t) {
            if ($t['q'] !== '' && $t['a']) {
                $out[] = ['tok' => Brain::tokens($t['q']), 'body' => mb_substr(implode("\n\n", $t['a']), 0, 3000)];
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    public static function open(): array
    {
        if (! self::ready()) {
            return [];
        }
        $rows = self::db()->table('tbl_bot_suggestions')->where('status', 'open')->orderBy('times', 'DESC')->orderBy('id', 'DESC')->limit(100)->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['evidence'] = json_decode((string) $r['evidence'], true) ?: [];
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    public static function stats(): array
    {
        if (! self::ready()) {
            return ['ready' => false];
        }
        $db    = self::db();
        $since = date('Y-m-d H:i:s', time() - 86400 * 30);

        return [
            'ready'    => true,
            'open'     => $db->table('tbl_bot_suggestions')->where('status', 'open')->countAllResults(),
            'applied'  => $db->table('tbl_bot_suggestions')->where('status', 'applied')->countAllResults(),
            'rejected' => $db->table('tbl_bot_suggestions')->where('status', 'rejected')->countAllResults(),
            'gaps30'   => $db->table('tbl_bot_unanswered')->where('last_seen >=', $since)->countAllResults(),
            'down30'   => $db->table('tbl_bot_chats')->where(['sender' => 'bot', 'feedback' => -1])->where('created_at >=', $since)->countAllResults(),
            'up30'     => $db->table('tbl_bot_chats')->where(['sender' => 'bot', 'feedback' => 1])->where('created_at >=', $since)->countAllResults(),
        ];
    }

    /**
     * Applies or rejects one suggestion. An alias adds words to an existing answer; a draft / gap becomes a new approved answer
     * with the (possibly edited) title, words and text.
     *
     * @param array<string,mixed> $edit title, keywords, body, steps
     * @return array{ok:bool,error?:string,knowledge_id?:int}
     */
    public static function decide(int $id, string $action, array $edit, string $by): array
    {
        $db  = self::db();
        $s   = $db->table('tbl_bot_suggestions')->where(['id' => $id, 'status' => 'open'])->get()->getRowArray();
        if (! $s) {
            return ['ok' => false, 'error' => 'That suggestion was already decided.'];
        }
        $now = date('Y-m-d H:i:s');
        if ($action === 'reject') {
            $db->table('tbl_bot_suggestions')->where('id', $id)->update(['status' => 'rejected', 'decided_at' => $now, 'decided_by' => mb_substr($by, 0, 80)]);

            return ['ok' => true];
        }
        $kid = null;
        if ($s['kind'] === 'alias') {
            $kb = $db->table('tbl_bot_knowledge')->where('id', (int) $s['kb_id'])->get()->getRowArray();
            if (! $kb) {
                return ['ok' => false, 'error' => 'The answer this was meant for no longer exists.'];
            }
            $words = array_filter(array_map('trim', explode(',', (string) ($edit['keywords'] ?? $s['keywords']))), 'strlen');
            $kw    = trim((string) $kb['keywords']);
            foreach ($words as $w) {
                if (! str_contains(mb_strtolower($kw), mb_strtolower($w))) {
                    $kw .= ($kw === '' ? '' : ', ') . strip_tags($w);
                }
            }
            $db->table('tbl_bot_knowledge')->where('id', $kb['id'])->update(['keywords' => mb_substr($kw, 0, 600), 'updated_at' => $now]);
            $kid = (int) $kb['id'];
        } else {
            $title = trim(strip_tags((string) ($edit['title'] ?? $s['title'])));
            $body  = trim(strip_tags((string) ($edit['body'] ?? $s['body'])));
            if ($title === '' || $body === '') {
                return ['ok' => false, 'error' => 'A title and an answer are required.'];
            }
            $steps = array_values(array_filter(array_map(static fn ($l) => mb_substr(trim(strip_tags((string) $l)), 0, 400), preg_split('/\r?\n/', (string) ($edit['steps'] ?? '')) ?: []), 'strlen'));
            $db->table('tbl_bot_knowledge')->insert(['kind' => 'learned', 'title' => mb_substr($title, 0, 200), 'keywords' => mb_substr(trim(strip_tags((string) ($edit['keywords'] ?? $s['keywords']))), 0, 600),
                'body' => mb_substr($body, 0, 4000), 'steps_json' => json_encode($steps, JSON_UNESCAPED_UNICODE), 'actions_json' => '[]', 'role_min' => 'user', 'status' => 'approved', 'source' => 'auto',
                'created_at' => $now, 'updated_at' => $now]);
            $kid = (int) $db->insertID();
            $ev  = json_decode((string) $s['evidence'], true) ?: [];
            if (! empty($ev['unanswered_ids'])) {
                $db->table('tbl_bot_unanswered')->whereIn('id', array_map('intval', $ev['unanswered_ids']))->update(['status' => 'answered']);
            }
        }
        $db->table('tbl_bot_suggestions')->where('id', $id)->update(['status' => 'applied', 'kb_id' => $kid, 'decided_at' => $now, 'decided_by' => mb_substr($by, 0, 80)]);

        return ['ok' => true, 'knowledge_id' => $kid];
    }
}
