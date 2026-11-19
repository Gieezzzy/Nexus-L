<?php

namespace App\Libraries;

/**
 * The knowledge groups of the Brain Trainer, in one place (the night trainer and the Brain Trainer app both use it).
 * Besides the original six there are two for the owner's latest training:
 *  - comms: how the assistant talks (owner and users, understanding what is needed, reasoning, checking itself, voice, security conversation);
 *  - video: what it learned from the training videos (machine learning, transformers, optimisers, JWT, API gateways, AI agent repos).
 */
class BrainGroups
{
    public const LABELS = [
        'offtopic'  => 'Questions that are not about the system (greetings, general, refusals)',
        'how'       => 'System how-tos (workflows of NEXUS-L)',
        'method'    => 'Test methods (VF methods, illustrated methods)',
        'standards' => 'Standards and documents (standards database, graph / Obsidian notes, lab documents)',
        'problem'   => 'Problems and solutions (QC lab troubleshooting)',
        'comms'     => 'Communication: talking with the owner and users, understanding needs, reasoning, voice, security conversation',
        'video'     => 'Learned from the training videos (machine learning, transformers, optimisers, JWT, API gateway)',
        'other'     => 'Other approved knowledge (statuses, follow-ups, imports)',
    ];

    /** default share of a round (percent) for the app and the trainer */
    public const SHARE = ['offtopic' => 14, 'how' => 14, 'method' => 14, 'standards' => 18, 'problem' => 8, 'comms' => 12, 'video' => 8, 'other' => 12];

    /** titles (as stored) of the seed entries that belong to the comms group */
    private const COMMS_TITLES = [
        'How sure are you, and why did you give me this answer?', 'Explain step by step, in simple words, or with an example', 'Why did the assistant ask which topic I meant?',
        'How do you reason about a problem?', 'How do you check your own answer before replying?', 'How do you talk to the owner and to users?', 'Can you talk to me and listen to my voice?',
        'Someone is attacking my network: what do you do?', 'What are the common web attacks and how do I recognise them?',
    ];

    /** @return array{off:array<string,bool>,comms:array<string,bool>,video:array<string,bool>} */
    public static function titles(): array
    {
        static $t = null;
        if ($t !== null) {
            return $t;
        }
        $t = ['off' => [], 'comms' => array_fill_keys(self::COMMS_TITLES, true), 'video' => []];
        foreach (BrainSeed12::all() as $e) {
            $t['off'][mb_substr($e['title'], 0, 200)] = true;
        }
        foreach (BrainSeed14::all() as $e) {
            $k = mb_substr($e['title'], 0, 200);
            if (! isset($t['comms'][$e['title']])) {
                $t['video'][$k] = true;
            }
        }

        return $t;
    }

    /** the group of one approved knowledge row (title, kind, source) */
    public static function of(array $r): string
    {
        $t = self::titles();
        $title = (string) $r['title'];
        if (isset($t['comms'][$title]) || isset($t['comms'][mb_substr($title, 0, 200)])) {
            return 'comms';
        }
        if (isset($t['video'][mb_substr($title, 0, 200)])) {
            return 'video';
        }

        return isset($t['off'][mb_substr($title, 0, 200)]) ? 'offtopic' : (in_array($r['source'], ['system_workflow', 'workflow_seed'], true) ? 'how'
            : (in_array($r['kind'], ['vf_method', 'vf_illustrated'], true) ? 'method' : ($r['source'] === 'standards_db' || $r['source'] === 'graph_obsidian' ? 'standards'
            : ($r['kind'] === 'problem' ? 'problem' : 'other'))));
    }

    /**
     * How people really talk to the assistant, owner and users, typed or by voice: filler words, no punctuation, Taglish, a name up front.
     * Used by the trainer to word the comms and video questions the way they will arrive.
     */
    public static function spoken(string $q): string
    {
        $pre = ['', 'gie here, ', 'it is gie, ', 'hey lexus, ', 'okay lexus ', 'um ', 'uh ', 'so ', 'lexus, ', 'boss asks: ', 'quick one, ', 'can you tell me ', 'i want to know ', 'i do not understand ', 'help me understand ',
            'pwede ba ', 'paki-explain ', 'ano ba ang ', 'paano ', 'sige, ', 'okay so ', 'one more thing, ', 'listen, '];
        $suf = ['', ' please', ' po', ' ah', ' you know', ' right now', ' in simple words', ' step by step', ' with an example', ' and why', ' for me', ' thanks', ' lexus', ' ba', ' na'];
        $s   = trim($pre[mt_rand(0, count($pre) - 1)] . $q . $suf[mt_rand(0, count($suf) - 1)]);
        if (mt_rand(1, 100) <= 40) {   // a transcript of speech has no capitals or punctuation
            $s = mb_strtolower(preg_replace('/[?.!,;:]+/', '', $s));
        }

        return $s;
    }
}
