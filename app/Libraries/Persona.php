<?php

namespace App\Libraries;

/**
 * How the assistant talks to the person in front of it. Three jobs:
 *  - who: the owner (Synology ID 426, account aud-001) is "Gie"; everybody else is addressed by the first word of the full name;
 *  - understand: intent, sentiment (with negation), urgency and entities of a message (ideas of nlp.js / AXIOM written in plain PHP);
 *  - lead: the short human opening of a reply ("Understood, Gie. I treat this as urgent."), used only when it helps (urgent, upset, status or security checks).
 * Behaviour only: nothing here reads or reveals a password, key or private data.
 */
class Persona
{
    public const OWNER_SYNOLOGY_ID = 426;
    public const OWNER_NAME        = 'Gie';

    /** @return array{name:string,first:string,owner:bool} */
    public static function who(?array $user): array
    {
        static $cache = [];
        $uid = (int) ($user['id'] ?? 0);
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }
        $full  = trim((string) ($user['name'] ?? ''));
        $first = $full !== '' ? preg_split('/\s+/', $full)[0] : ($uid > 0 ? (string) ($user['username'] ?? 'there') : 'there');
        $owner = false;
        if ($uid > 0) {
            try {
                $r     = \Config\Database::connect()->query('SELECT SynologyID FROM tbl_users WHERE UserID = ? LIMIT 1', [$uid])->getRowArray();
                $owner = $r && (int) $r['SynologyID'] === self::OWNER_SYNOLOGY_ID;
            } catch (\Throwable $e) {
                // no lookup: treat as a normal user
            }
        }

        return $cache[$uid] = ['name' => $owner ? self::OWNER_NAME : $first, 'first' => $first, 'owner' => $owner];
    }

    /**
     * @return array{intent:string,sentiment:string,urgent:bool,entities:array<string,list<string>>,needs:list<string>}
     */
    public static function understand(string $text): array
    {
        $t   = mb_strtolower(trim($text));
        $ent = [];
        if (preg_match_all('/\bDTP\d{8}[A-Z0-9]+(?:-[A-Z0-9]+)*\b/i', $text, $m)) {
            $ent['lab_ref'] = array_values(array_unique(array_map('strtoupper', $m[0])));
        }
        if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text, $m)) {
            $ent['ip'] = array_values(array_unique($m[0]));
        }
        if (preg_match_all('/\b(today|yesterday|tomorrow|this week|last week|this month|last hour|last \d+ (?:minutes?|hours?|days?))\b/i', $text, $m)) {
            $ent['when'] = array_values(array_unique(array_map('strtolower', $m[1])));
        }

        // sentiment: negative / positive words, flipped by a negation just before them (nlp.js idea)
        $neg   = '/\b(wrong|useless|stupid|bad|terrible|annoying|slow|stuck|broken|critical|down|error|not working|doesn\'?t work|cannot|can\'?t|angry|frustrat\w+|problem|issue|worst|hate|again and again|still not)\b/u';
        $pos   = '/\b(thanks|thank you|great|good|nice|perfect|awesome|helpful|love|excellent|fixed|solved|cool)\b/u';
        $score = 0;
        foreach ([[$neg, -1], [$pos, 1]] as [$re, $sign]) {
            if (preg_match_all($re, $t, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[0] as [$w, $off]) {
                    $before = mb_substr($t, max(0, $off - 14), min(14, $off));
                    $flip   = (bool) preg_match('/\b(not|no|never|isn\'?t|wasn\'?t|don\'?t|didn\'?t|without)\s+(?:\w+\s+)?$/u', $before);
                    $score += $flip ? -$sign : $sign;
                }
            }
        }
        $sentiment = $score < 0 ? 'negative' : ($score > 0 ? 'positive' : 'neutral');
        $urgent    = (bool) preg_match('/\b(urgent|asap|right now|immediately|emergency|critical|as soon as possible|quickly|hurry|now na|agad)\b/u', $t) || (bool) preg_match('/!{2,}/', $text);

        $core = $t;
        for ($i = 0; $i < 4; $i++) {      // "um hey lexus gie here please how do i ..."
            $next = preg_replace('/^(um+|uh+|ah+|so|okay|ok|sige|hey|hi|hello|lexus|lexis|gie here|it is gie|boss asks|quick one|please|pls|asap|urgent|quickly|listen|one more thing|can you tell me|i want to know|help me understand)\b[,:]?\s*/u', '', $core);
            if ($next === $core) {
                break;
            }
            $core = $next;
        }
        $core   = $core === '' ? $t : $core;
        $intent = 'question';
        $needs  = [];
        if (preg_match('/^(hi|hello|hey|good (morning|afternoon|evening)|yo|kumusta)\b/u', $t) && mb_strlen($t) < 40) {
            $intent = 'greeting';
            $sentiment = 'neutral';
        } elseif ((preg_match('/^(thanks|thank you|ty|salamat|ok thanks|got it|great)\b/u', $t) || preg_match('/\b(thanks|thank you|salamat)\s*[.!]*$/u', $t)) && mb_strlen($t) < 50 && ! str_contains($t, '?')) {
            $intent = 'thanks';
        } elseif (! (preg_match('/^(what (is|are|does)|what .{0,25}look like|how (does|do|are|to)|explain|define|why (do|does|is|are)|tell me about)\b/u', $t) && ! preg_match('/\b(my |our |the )?(network|server|system)\b.*\b(now|today|last|any)\b|\b(any|is anyone|right now|currently|detected)\b/u', $t)) && preg_match('/\b(attack\w*|intrusion|intruder\w*|hack(?:ed|er|ing)?|breach\w*|malware|brute.?force|port scan\w*|ddos|exploit\w*|vulnerab\w+|security (?:status|report|check|watch|alert|log|logs|monitor\w*)|suspicious|firewall|threats?|who is (?:trying|scanning)|(?:someone|somebody|anyone) (?:is |was |has |had )?(?:scan\w*|prob\w*|trying)|break(?:ing)? in(?:to)?|monitor(?:ing)? (?:my |the )?(?:network|server))\b/u', $t)) {
            $intent = 'security';
            $needs[] = 'security status';
        } elseif (preg_match('/\b(wrong|useless|stupid|not working|doesn\'?t work|does not work|nothing works|broken|stuck|still not|again and again|you keep|do not understand|don\'?t understand|bad bot)\b/u', $t)) {
            $intent = 'complaint';
            $sentiment = 'negative';
            $needs[] = 'fix the problem';
        } elseif (preg_match('/^(how|where|paano|pano|saan)\b/u', $core) && ! preg_match('/^how (many|much)\b/u', $core)) {
            $intent = 'howto';
        } elseif (preg_match('/\b(status|progress|update|how many|how much|count|list|show me|any new|what happened|what is happening)\b/u', $core)) {
            $intent = 'status';
            $needs[] = 'current information';
        } elseif (preg_match('/^(please |pls |can you |could you |kindly )?(send|make|create|generate|add|remove|delete|open|run|start|stop|check|monitor|report|train|learn|update|fix|move|transfer|change|set|download|install|block)\b/u', $core)) {
            $intent = 'task';
            $needs[] = 'do something';
        } elseif (preg_match('/^(how|where|when|what|why|which|who|is|are|can|do|does|ano|bakit|pwede)\b/u', $core) || str_contains($t, '?')) {
            $intent = 'question';
        }
        if (preg_match('/\b(why|explain|reason|because|how come)\b/u', $t)) {
            $needs[] = 'the reason';
        }
        if (preg_match('/\b(example|sample|show me how)\b/u', $t)) {
            $needs[] = 'an example';
        }
        if (preg_match('/\b(step by step|steps|walk me through|procedure)\b/u', $t)) {
            $needs[] = 'steps';
        }
        if (preg_match('/\b(simple|simply|eli5|don\'?t understand|do not understand|confus\w+)\b/u', $t)) {
            $needs[] = 'a simpler explanation';
        }

        return ['intent' => $intent, 'sentiment' => $sentiment, 'urgent' => $urgent, 'entities' => $ent, 'needs' => array_values(array_unique($needs))];
    }

    /** The human opening of a reply (empty when none is needed). */
    public static function lead(array $u, array $who): string
    {
        $n = $who['name'];
        if ($u['intent'] === 'complaint' || ($u['sentiment'] === 'negative' && $u['urgent'])) {
            return "I am sorry about that, {$n}. Let me look at it properly. ";
        }
        if ($u['urgent']) {
            return "Understood, {$n}. I am treating this as urgent. ";
        }
        if ($u['intent'] === 'security') {
            return "Checking the security logs now, {$n}. ";
        }
        if ($u['intent'] === 'task') {
            return "On it, {$n}. ";
        }
        if (in_array('a simpler explanation', $u['needs'], true)) {
            return "No problem, {$n}, I will put it simply. ";
        }

        return '';
    }

    public static function greeting(array $who): string
    {
        $h = (int) date('G');
        $p = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');

        return $who['owner']
            ? "{$p}, {$who['name']}! I am LEXUS AI, ready when you are. Tell me what you need: how to do something in the system, a test or report status, or a check of the security logs."
            : "{$p}, {$who['name']}! I am LEXUS AI (Laboratory Execution Intelligence System). Ask me how to do something in this system, calculate test results, or check report status and I will help you.";
    }
}
