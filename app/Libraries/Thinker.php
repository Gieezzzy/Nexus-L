<?php

namespace App\Libraries;

/**
 * Deep thinking for the chat: before the assistant looks anything up it tries to WORK THE QUESTION OUT with the Reasoner, from real data, and shows how.
 * Pipeline: understand (Persona) -> pick the tool the question needs -> read the facts -> apply explicit rules -> verify (do the records agree?) -> conclude with
 * the steps and a confidence. When no tool fits, it returns null and the normal knowledge answer is used.
 */
class Thinker
{
    /** @return array<string,mixed>|null a reply in the shape of BotController::compose, or null */
    public static function think(string $text, array $und, array $who, bool $isAdmin): ?array
    {
        $base = ['actions' => [], 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'understood' => $und];

        // 1) "what did you learn?" (owner / admin)
        if ($isAdmin && preg_match('/\b(what (did|have) you learn(ed|t)?|learning report|how is your learning|what are you learning|how much did you learn|your learning progress)\b/i', $text)) {
            $j = ConversationLearner::journal(24);
            $m = Mastery::quiz(60);
            $by = $j['by_kind'];
            $steps = [
                'Conversation lines read in the last 24 hours: ' . $j['chats_read'] . '.',
                'New ways to ask for an answer learned from Helpful ratings: ' . (int) ($by['helpful'] ?? 0) . '; from your corrections: ' . (int) ($by['owner_fix'] ?? 0) . '; style preferences noticed: ' . (int) ($by['prefs'] ?? 0) . '.',
                'All extra phrases learned from conversations so far: ' . $j['live_phrases'] . '.',
                'Self-quiz just now (spoken-style questions with typos): ' . $m['right'] . ' of ' . $m['asked'] . ' right (' . $m['accuracy'] . '%).',
            ];
            if ($m['missed']) {
                $steps[] = 'I missed, for example: "' . $m['missed'][0]['asked'] . '" (I answered "' . mb_substr($m['missed'][0]['got'], 0, 60) . '"). The trainer practises these entries more until they are right.';
            }

            return $base + ['text' => $who['name'] . ', this is what I learned by myself. I never write new facts on my own: new knowledge stays a proposal until an administrator approves it.', 'title' => 'What I learned', 'steps' => $steps, 'confidence' => 100,
                'reasoning' => ['matched' => 'Learning journal', 'confidence' => 100, 'basis' => 'read from the learning log and a live self-quiz']];
        }

        // 2) a Lab Reference: work out its state from every record
        $ref = $und['entities']['lab_ref'][0] ?? null;
        if ($ref && ! preg_match('/^(how (do|can|to)|where (do|can))\b/i', trim($text)) && preg_match('/\b(why|status|state|pending|stuck|complete|completed|result|where is|what happened|what is happening|done|approved|ready|check|explain|progress|lacking|fail|failed|pass|passed)\b/i', $text)) {
            $r = Reasoner::explainLabRef($ref);
            $steps = $r['trace'];
            if ($r['next'] !== '') {
                $steps[] = 'Next step: ' . $r['next'];
            }

            return $base + ['text' => $r['summary'], 'title' => 'How I worked it out', 'steps' => $steps, 'confidence' => $r['confidence'],
                'reasoning' => ['matched' => 'Reasoner: Lab Reference ' . $ref, 'confidence' => $r['confidence'], 'basis' => 'derived from the records by explicit rules (each step is shown)', 'found' => $r['found']]];
        }

        // 3) a measured value against a standard
        $c = Reasoner::parseClassify($text);
        if ($c) {
            $r = Reasoner::classify($c['value'], $c['std'], $c['dir']);

            return $base + ['text' => 'With value ' . $c['value'] . ' and standard ' . $c['std'] . ' the result is ' . $r['result'] . '. I used the bonding rule of this system (Pass at the standard, Tolerable down to 20% below it); tell me if this test uses another tolerance.',
                'title' => 'How I worked it out', 'steps' => $r['trace'], 'confidence' => 90,
                'reasoning' => ['matched' => 'Reasoner: classification', 'confidence' => 90, 'basis' => 'calculated, not looked up; assumes the 20% tolerance rule']];
        }

        return null;
    }
}
