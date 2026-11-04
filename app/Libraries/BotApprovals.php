<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * The approval conversation of the help bot, in plain words:
 *
 *   "what are my for approval reports"   -> a numbered list of the reports waiting for a signature (read only)
 *   "resend the verification for this report" / "resend #2" / "remind them about the first one"
 *                                          -> which report is meant, taken from the list the bot showed last
 *   "can you email this report"          -> what NEXUS-L can do instead (it does not send e-mail itself)
 *
 * Nothing here sends anything. BotController::approvalReminderIntent does the sending (Synology Chat), after this class has said
 * WHICH report is meant, or that it cannot tell and must ask. Questions about HOW to resend ("how do I send this report to the
 * manager") are never treated as a request to resend.
 */
class BotApprovals
{
    /** a Lab Reference as written in the lab: DTP20261019F1-3, DTPOFR20260325FBL-3, DTP20261027FAA7-R */
    public const LABREF = '/\b(DTP[A-Z]*\d{8}[A-Z0-9]*(?:-[A-Z0-9]{1,4})?)\b/i';

    private const ORDINALS = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'una' => 1, 'pangalawa' => 2, 'ikalawa' => 2,
        'pangatlo' => 3, 'ikatlo' => 3, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];

    /** a question about the procedure, not a request to do it */
    public static function isHowTo(string $low): bool
    {
        return (bool) preg_match('/^\s*(how|paano|pano|where|saan|what|ano|why|bakit|when|kailan|who|sino|can i|can we|pwede|puwede|is it|are there|do i|do we|should i|steps?\b|explain)\b/i', $low)
            || (bool) preg_match('/\b(how (do|to|can|should)|steps? (to|for)|procedure|instructions?|paano)\b/i', $low);
    }

    /** @return list<string> the Lab References in a text, in order, without repeats, upper case */
    public static function refsIn(string $text): array
    {
        preg_match_all(self::LABREF, $text, $m);
        return array_values(array_unique(array_map('strtoupper', $m[1] ?? [])));
    }

    /**
     * Which report a resend request is about.
     *
     * @return array{lab:?string,app:?string,deictic:bool,ambiguous:bool,candidates:list<string>}
     *   lab       the Lab Reference to use (null: none named and none to point back to)
     *   deictic   the request points back ("this report", "that one", "the first")
     *   ambiguous it points back, but several reports were listed and no number was given: the bot has to ask
     */
    public static function resolveTarget(string $text, string $lastBotMsg): array
    {
        $out  = ['lab' => null, 'app' => null, 'deictic' => false, 'ambiguous' => false, 'candidates' => []];
        $refs = self::refsIn($text);
        if ($refs !== []) {
            $out['lab'] = $refs[0];
            return $out;
        }
        if (preg_match('/\b(APP[-_]?\d{4,}|A\d{9})\b/', $text, $m)) {
            $out['app'] = strtoupper($m[1]);
            return $out;
        }
        $low  = mb_strtolower($text);
        $list = self::refsIn($lastBotMsg);
        $idx  = null;
        if (preg_match('/(?:#|no\.?\s*|number\s*|item\s*|report\s*)(\d{1,2})\b/', $low, $m)) {
            $idx = (int) $m[1];
        } elseif (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)\b/', $low, $m)) {
            $idx = (int) $m[1];
        } elseif (preg_match('/\b(first|second|third|fourth|fifth|una|pangalawa|ikalawa|pangatlo|ikatlo)\b/', $low, $m)) {
            $idx = self::ORDINALS[$m[1]];
        } elseif (preg_match('/\b(last|latest|huli)\b/', $low) && $list !== []) {
            $idx = count($list);
        }
        $deictic = (bool) preg_match('/\b(this|that|these|those|the same|same one|it|above|the one|ito|iyan|iyon|yan|yun|yung)\b/', $low) || $idx !== null;
        $out['deictic'] = $deictic;
        if (! $deictic) {
            return $out;
        }
        $out['candidates'] = $list;
        if ($idx !== null && $idx >= 1 && $idx <= count($list)) {
            $out['lab'] = $list[$idx - 1];
        } elseif ($idx === null && count($list) === 1) {
            $out['lab'] = $list[0];
        } else {
            $out['ambiguous'] = true;                         // none listed, or several and no number
        }
        return $out;
    }

    // ----------------------------------------------------------------------------------------------- the list

    private static function wantsList(string $low): ?string
    {
        if (self::isHowTo($low) && ! preg_match('/^\s*(what|which|who|ano|sino|ilan|how many)\b.*\b(approv|sign|pending|waiting|unsigned)/i', $low)) {
            return null;
        }
        if (preg_match('/\b(resend|re-?send|remind|follow[\s-]?up|send again|notify|chase|nudge)\b/i', $low)) {
            return null;                                        // an action, handled by the reminder
        }
        $noun = '(approv\w*|signature|signed|sign[\s-]?off|unsigned|verif\w*|pending|awaiting|waiting)';
        if (! preg_match('/' . $noun . '/i', $low)) {
            return null;
        }
        if (! preg_match('/\b(reports?|lab ?refs?|samples?|requests?|items?|approvals?|signatures?|list|show|pending|waiting|awaiting|unsigned|not (yet )?(signed|approved)|who|sino|ano|which|ilan|how many)\b/i', $low)) {
            return null;
        }
        if (preg_match('/\b(not (yet )?(signed|approved)|unsigned|nobody signed|who (has|have) not|hasn\'?t signed|haven\'?t signed|sino.*hindi.*(pa )?(pumirma|signed))\b/i', $low)) {
            return 'pending';
        }
        if (preg_match('/\b(rejected|declined|denied)\b/i', $low)) {
            return 'rejected';
        }
        if (preg_match('/\bexpired\b/i', $low)) {
            return 'expired';
        }
        if (preg_match('/\b(for approval|pending|waiting|awaiting|need(s|ing)? (my |an? )?(approval|signature|sign)|to be (approved|signed)|for (signature|signing|sign[\s-]?off)|for verification|unverified|naghihintay|hinihintay)\b/i', $low)) {
            return 'pending';
        }
        if (preg_match('/\b(approved|signed|already approved|already signed|nasign|naaprubahan)\b/i', $low)) {
            return 'approved';
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null a chat reply (text, title, steps, actions ...) or null when the text is not about the approval list
     */
    public static function listIntent(string $text, int $uid, BaseConnection $db): ?array
    {
        $low  = mb_strtolower(trim($text));
        $what = self::wantsList($low);
        if ($what === null || ! $db->tableExists('tbl_dtr_report_approvals')) {
            return null;
        }
        $mine  = (bool) preg_match('/\b(my|mine|i (have )?(sent|requested|submitted|made)|ko|akin|aking)\b/i', $low) && $uid > 0;
        $count = (bool) preg_match('/\b(how many|ilan|count|number of|total)\b/i', $low);
        $b     = $db->table('tbl_dtr_report_approvals a')
            ->select('a.id, a.lab_ref_no, a.application_no, a.form_slug, a.status, a.requested_name, a.requested_at, a.expires_at, a.decided_name, a.decided_at, a.required_name, a.remarks, u.model_style, u.material_description, u.supplier')
            ->join('tbl_dtr_unified_labrefs u', 'u.lab_ref_no COLLATE utf8mb4_general_ci = a.lab_ref_no COLLATE utf8mb4_general_ci', 'left', false);
        if ($what === 'pending') {
            $b->where('a.status', 'pending');
        } elseif ($what === 'expired') {
            $b->where('a.status', 'pending')->where('a.expires_at <', date('Y-m-d H:i:s'));
        } elseif ($what === 'rejected') {
            $b->whereIn('a.status', ['rejected', 'declined', 'denied']);
        } else {
            $b->where('a.status', 'approved');
        }
        if ($mine) {
            $b->where('a.requested_by', $uid);
        }
        $total = (clone $b)->countAllResults(false);
        $rows  = $b->orderBy('a.id', 'DESC')->limit(10)->get()->getResultArray();
        $label = ['pending' => 'waiting for approval', 'expired' => 'waiting for approval with an expired link', 'rejected' => 'rejected', 'approved' => 'approved'][$what];
        $scope = $mine ? 'you requested' : 'in NEXUS-L';
        if ($rows === []) {
            $none = $what === 'pending'
                ? "No report {$scope} is waiting for approval right now. Open a finished report and press \"Request approval (QR)\" to send it to the signatories."
                : "No report {$scope} is {$label}.";
            return self::reply($none, ucfirst($label), [], [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]]);
        }
        $now   = time();
        $lines = [];
        foreach ($rows as $i => $r) {
            $what1 = trim((string) ($r['model_style'] ?: $r['material_description'] ?: $r['form_slug']));
            $by    = $r['status'] === 'approved'
                ? 'approved by ' . ($r['decided_name'] ?: '-') . ' on ' . date('d M Y H:i', strtotime((string) $r['decided_at']))
                : 'requested by ' . ($r['requested_name'] ?: '-') . ' on ' . date('d M Y H:i', strtotime((string) $r['requested_at']))
                    . ((string) $r['required_name'] !== '' ? ', waiting for ' . $r['required_name'] : ', waiting for a signatory')
                    . (strtotime((string) $r['expires_at']) <= $now ? ' (link expired: resend it)' : ' (link valid until ' . date('H:i', strtotime((string) $r['expires_at'])) . ')');
            $lines[] = ($i + 1) . '. ' . $r['lab_ref_no'] . ($what1 !== '' ? ' - ' . mb_substr($what1, 0, 50) : '') . ' - ' . $by;
        }
        $head = $count
            ? "{$total} report" . ($total === 1 ? '' : 's') . " {$scope} " . ($total === 1 ? 'is' : 'are') . " {$label}."
            : ($total > count($rows) ? "Showing the latest " . count($rows) . " of {$total} reports {$scope} {$label}:" : ucfirst("{$total} report" . ($total === 1 ? '' : 's') . " {$scope} {$label}:"));
        $tail = $what === 'approved' ? '' : "\n\nTo remind the signatories, say \"resend approval for #1\" (or for a Lab Reference).";
        $act  = [];
        foreach (array_slice($rows, 0, 3) as $r) {
            $act[] = ['type' => 'open', 'label' => 'Open ' . $r['lab_ref_no'], 'url' => site_url('dtr?open=' . rawurlencode((string) $r['lab_ref_no']))];
        }
        return self::reply($head . ($count ? '' : "\n\n" . implode("\n", $lines)) . ($count ? "\n\n" . implode("\n", $lines) : '') . $tail, ucfirst($label), [], $act);
    }

    // ----------------------------------------------------------------------------------------------- e-mail

    /** @return array<string,mixed>|null */
    public static function emailIntent(string $text): ?array
    {
        $low = mb_strtolower($text);
        if (! preg_match('/\b(e-?mail|emailing|gmail|outlook|mail (it|this|the|a))\b/i', $low)) {
            return null;
        }
        if (! preg_match('/\b(send|forward|share|attach|follow[\s-]?up|notify|deliver|padala|ipadala)\b|\bcan (i|you|we)\b/i', $low)) {
            return null;
        }
        if (! preg_match('/\b(report|pdf|result|approval|verification|sheet|lab ref|DTP\d)/i', $text)) {
            return null;
        }
        return self::reply(
            "NEXUS-L does not send e-mail itself. For a report you can:\n"
            . "1. Download the PDF and attach it to your own e-mail.\n"
            . "2. Share it with a colleague through the Inbox, which accepts files and report links.\n"
            . "3. For approval: say \"resend approval for this report\" and the signatories get a Synology Chat message with the sign link.\n"
            . "4. For the customer: send the report through InterLink (the PIC sends it with their own account).",
            'E-mail a report',
            ['Open the report and press the PDF button.', 'Attach the file in your e-mail program.'],
            [['type' => 'open', 'label' => 'Open Inbox', 'url' => site_url('inbox')], ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
        );
    }

    /** the reply when a resend request says "this report" and the bot cannot tell which */
    public static function askWhich(array $candidates): array
    {
        if ($candidates === []) {
            return self::reply("Which report? Give me its Lab Reference (for example DTP20261019F1-3), or ask \"what are my for approval reports\" first and then say \"resend #1\".", 'Which report?', [],
                [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]]);
        }
        $lines = [];
        foreach (array_slice($candidates, 0, 10) as $i => $c) {
            $lines[] = ($i + 1) . '. ' . $c;
        }
        return self::reply("Which one? You listed " . count($candidates) . " reports:\n" . implode("\n", $lines) . "\n\nSay the number, for example \"resend #2\".", 'Which report?', []);
    }

    /** @return array<string,mixed> */
    private static function reply(string $text, string $title, array $steps = [], array $actions = []): array
    {
        return ['text' => $text, 'title' => $title, 'steps' => $steps, 'actions' => $actions, 'confidence' => 100, 'related' => [],
            'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false];
    }
}
