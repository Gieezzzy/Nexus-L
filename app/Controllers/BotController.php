<?php

namespace App\Controllers;

use App\Libraries\BotSessions;
use App\Libraries\Brain;

/**
 * BotController - the NEXUS-L help bot (local, system-only).
 *  - signed-in users: ask, feedback, escalate to an administrator, read / send chat messages, activity events
 *  - administrators (/brain): threads to answer, knowledge to curate, questions the bot could not answer, repeated
 *    problems, health checks, export / restore of the Brain folders
 */
class BotController extends BaseController
{
    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    private function isAdmin(): bool
    {
        return strcasecmp((string) ($this->auth->user()['role'] ?? ''), 'admin') === 0;
    }

    private function json(array $d, int $code = 200)
    {
        $d['csrf'] = csrf_hash();

        return $this->response->setStatusCode($code)->setJSON($d);
    }

    private function input(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
    }

    // ============================================================================ activity events

    /** POST bot/events {events:[{type,page,target,detail,status}]} */
    public function events()
    {
        $u    = $this->auth->user();
        $p    = $this->input();
        $db   = $this->db();
        $now  = date('Y-m-d H:i:s');
        $ok   = ['view', 'click', 'save', 'error', 'http_error', 'stuck', 'bot'];
        $n    = 0;
        $fwd  = [];
        foreach (array_slice((array) ($p['events'] ?? []), 0, 40) as $e) {
            $type = (string) ($e['type'] ?? '');
            if (! in_array($type, $ok, true)) {
                continue;
            }
            $page   = mb_substr(preg_replace('#[?\#].*$#', '', (string) ($e['page'] ?? '')), 0, 160);
            $target = Brain::redact((string) ($e['target'] ?? ''));
            $detail = Brain::redact((string) ($e['detail'] ?? ''));
            $sig    = in_array($type, ['error', 'http_error', 'stuck'], true) ? Brain::signature($type, $page, $detail !== '' ? $detail : $target) : null;
            $db->table('tbl_bot_events')->insert([
                'user_id' => (int) ($u['id'] ?? 0), 'username' => (string) ($u['username'] ?? ''), 'role' => (string) ($u['role'] ?? ''),
                'event_type' => $type, 'page' => $page, 'target' => mb_substr($target, 0, 160), 'detail' => mb_substr($detail, 0, 500),
                'status' => isset($e['status']) ? (int) $e['status'] : null, 'signature' => $sig, 'created_at' => $now,
            ]);
            $n++;
            $fwd[] = ['event_type' => $type, 'page' => $page, 'target' => $target, 'detail' => $detail];
            if ($sig !== null) {
                $exists = $db->table('tbl_bot_issues')->where('signature', $sig)->get()->getRowArray();
                if ($exists) {
                    $db->table('tbl_bot_issues')->where('id', $exists['id'])->set('times', 'times + 1', false)->update(['last_seen' => $now]);
                } else {
                    $db->table('tbl_bot_issues')->insert(['signature' => $sig, 'summary' => mb_substr(($type === 'http_error' ? 'HTTP ' . (int) ($e['status'] ?? 0) . ' ' : '') . ($detail !== '' ? $detail : $target), 0, 300), 'page' => $page, 'times' => 1, 'users' => 1, 'status' => 'open', 'first_seen' => $now, 'last_seen' => $now]);
                }
            }
        }

        \App\Libraries\AiBridge::events((int) ($u['id'] ?? 0), (string) ($u['role'] ?? ''), $fwd);

        return $this->json(['ok' => true, 'stored' => $n]);
    }

    /** GET bot/history: the signed-in user's own recent activity and chats. */
    public function history()
    {
        $u  = $this->auth->user();
        $db = $this->db();

        return $this->json([
            'ok'      => true,
            'events'  => $db->table('tbl_bot_events')->select('event_type, page, target, detail, created_at')->where('user_id', (int) $u['id'])->orderBy('id', 'DESC')->limit(40)->get()->getResultArray(),
            'threads' => $db->table('tbl_bot_threads')->select('id, topic, status, updated_at')->where('user_id', (int) $u['id'])->orderBy('id', 'DESC')->limit(15)->get()->getResultArray(),
        ]);
    }

    // ================================================================================== user chat

    private function threadFor(int $userId, string $username, string $page, ?int $threadId, string $firstMessage): array
    {
        $db = $this->db();
        if ($threadId) {
            $t = $db->table('tbl_bot_threads')->where(['id' => $threadId, 'user_id' => $userId])->get()->getRowArray();
            if ($t && $t['status'] !== 'resolved') {
                return $t;
            }
        }
        $now = date('Y-m-d H:i:s');
        $db->table('tbl_bot_threads')->insert(['user_id' => $userId, 'username' => $username, 'status' => 'bot', 'topic' => mb_substr($firstMessage, 0, 190), 'page' => mb_substr($page, 0, 160), 'created_at' => $now, 'updated_at' => $now]);

        return $db->table('tbl_bot_threads')->where('id', (int) $db->insertID())->get()->getRowArray();
    }

    private function say(int $threadId, string $sender, string $name, string $message, ?int $kbId = null, ?int $conf = null): int
    {
        $db = $this->db();
        $db->table('tbl_bot_chats')->insert(['thread_id' => $threadId, 'sender' => $sender, 'sender_name' => $name, 'message' => $message, 'knowledge_id' => $kbId, 'confidence' => $conf, 'created_at' => date('Y-m-d H:i:s')]);
        $id = (int) $db->insertID();   // read it before the next query resets it
        $db->table('tbl_bot_threads')->where('id', $threadId)->update(['updated_at' => date('Y-m-d H:i:s')]);

        return $id;
    }

    private function recentErrors(int $userId): array
    {
        $rows = $this->db()->table('tbl_bot_events')->select('event_type, status, detail, target')->where('user_id', $userId)
            ->whereIn('event_type', ['error', 'http_error'])->where('created_at >=', date('Y-m-d H:i:s', time() - 900))->orderBy('id', 'DESC')->limit(6)->get()->getResultArray();

        return array_map(static fn ($r) => trim(($r['status'] ? 'HTTP ' . $r['status'] . ' ' : '') . ($r['detail'] ?: $r['target'])), $rows);
    }

    /** POST bot/ask {message, thread_id?, page?} */
    public function ask()
    {
        $u    = $this->auth->user();
        $p    = $this->input();
        $text = trim((string) ($p['message'] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) {
            return $this->json(['ok' => false, 'error' => 'Type your question first.'], 422);
        }
        $text   = Brain::redact($text);
        $page   = (string) ($p['page'] ?? '');
        $uid    = (int) $u['id'];
        BotSessions::expire();
        $thread = $this->threadFor($uid, (string) ($u['username'] ?? ''), $page, isset($p['thread_id']) ? (int) $p['thread_id'] : null, $text);
        $sess   = BotSessions::active((int) $thread['id']);

        // in a live session with an administrator: the message goes to them, the bot stays quiet
        if ($sess) {
            $userChatId = BotSessions::line((int) $thread['id'], (int) $sess['id'], 'user', (string) ($u['name'] ?? $u['username'] ?? 'User'), $text);
            BotSessions::fromUser($sess, $text);

            return $this->json(['ok' => true, 'thread_id' => (int) $thread['id'], 'user_chat_id' => $userChatId, 'escalated' => true, 'session' => BotSessions::public(BotSessions::find((int) $sess['id'])), 'reply' => null]);
        }
        $userChatId = $this->say((int) $thread['id'], 'user', (string) ($u['name'] ?? $u['username'] ?? 'User'), $text);

        $prev = $this->db()->table('tbl_bot_chats')->select('message')->where(['thread_id' => (int) $thread['id'], 'sender' => 'user'])->where('id <', $userChatId)->orderBy('id', 'DESC')->get(1)->getRowArray();
        $prevUserMsg = (string) ($prev['message'] ?? '');

        $intentReply = $this->labRefIntent($text) ?? $this->approvalReminderIntent($text, $uid, (array) $u) ?? $this->dbSearchIntent($text) ?? $this->computeIntent($text, $prevUserMsg);
        if ($intentReply !== null) {
            $reply = $intentReply;
        } else {
            $reply = $this->compose($text, ['page' => $page, 'role' => (string) ($u['role'] ?? ''), 'errors' => $this->recentErrors($uid)], $page);
            if (\App\Libraries\AiBridge::isLive()) {
                $aiResp = \App\Libraries\AiBridge::post('/ask', [
                    'session_id' => \App\Libraries\AiBridge::session($uid),
                    'user_id' => (string) $uid,
                    'message' => $text,
                    'prev_question' => $prevUserMsg,
                    'ui' => ['page' => $page, 'role' => strtolower((string) ($u['role'] ?? 'user')) === 'admin' ? 'admin' : 'user'],
                ], 4500);

                $ansText = trim((string) ($aiResp['text'] ?? $aiResp['answer'] ?? ''));
                if ($aiResp && $ansText !== '') {
                    $conf = isset($aiResp['confidence']) ? (float) $aiResp['confidence'] : 0.0;
                    $confPct = (int) round($conf * 100);
                    $route = (string) ($aiResp['route'] ?? 'agent');

                    // Zero-hallucination guard: if confidence is below threshold or route is abstain, enforce safe message
                    $threshold = \App\Libraries\AiBridge::threshold();
                    $finalText = ($route === 'abstain' || $conf < $threshold)
                        ? \App\Libraries\AiBridge::fallbackMessage()
                        : $ansText;

                    $related = [];
                    $rawRelated = is_array($aiResp['related'] ?? null) && ! empty($aiResp['related']) ? $aiResp['related'] : ($reply['related'] ?? []);
                    foreach ($rawRelated as $rel) {
                        $t = is_array($rel) ? (string) ($rel['title'] ?? $rel['question'] ?? '') : (string) $rel;
                        if ($t !== '') {
                            $relId = is_array($rel) ? (int) ($rel['id'] ?? $rel['kb_id'] ?? 0) : 0;
                            $related[] = ['id' => $relId, 'kb_id' => $relId, 'title' => $t];
                        }
                    }

                    $reply = [
                        'text' => $finalText,
                        'steps' => is_array($aiResp['steps'] ?? null) ? $aiResp['steps'] : ($reply['steps'] ?? []),
                        'actions' => is_array($aiResp['actions'] ?? null) ? $aiResp['actions'] : ($reply['actions'] ?? []),
                        'confidence' => $confPct,
                        'related' => $related,
                        'diagnosis' => null,
                        'knowledge_id' => $aiResp['kb_id'] ?? null,
                        'suggest_admin' => ! empty($aiResp['suggest_admin']) || ! empty($aiResp['escalate']) || $route === 'abstain',
                        'sources' => $aiResp['citations'] ?? [],
                        'trace_id' => $aiResp['trace_id'] ?? null,
                    ];
                }
            }
        }
        $botName = (string) \App\Libraries\SystemSettings::get('ai.name', 'LEXIS AI');
        $cid   = $this->say((int) $thread['id'], 'bot', $botName, $reply['text'], $reply['knowledge_id'], $reply['confidence']);
        $reply['chat_id'] = $cid;
        if (\App\Libraries\AiBridge::enabled()) {
            // shadow mode: the AI service answers on the side for comparison; the user only ever sees the answer above
            $prev = $db0 = $this->db()->table('tbl_bot_chats')->select('message')->where(['thread_id' => (int) $thread['id'], 'sender' => 'user'])->where('id <', $userChatId)->orderBy('id', 'DESC')->get(1)->getRowArray();
            \App\Libraries\AiBridge::shadowAsk($uid, $text, $page, (string) ($u['role'] ?? ''), $reply['knowledge_id'] ? (int) $reply['knowledge_id'] : null, isset($reply['confidence']) ? (int) $reply['confidence'] : null, (string) ($prev['message'] ?? ''));
        }
        unset($reply['knowledge_id']);

        return $this->json(['ok' => true, 'thread_id' => (int) $thread['id'], 'user_chat_id' => $userChatId, 'escalated' => false, 'reply' => $reply]);
    }

    /**
     * "open / show / preview / print / pdf / check the machine file for DTP20261012FAA8-L": finds the report and returns
     * buttons that open it (nothing is saved or sent by the assistant; the user presses the buttons).
     */
    private function labRefIntent(string $text): ?array
    {
        if (! preg_match('/\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b/i', $text, $m)) {
            return null;
        }
        if (! preg_match('/\b(open|show|find|view|edit|preview|print|pdf|download|machine|file|reading|load|check|where)\b/i', $text)) {
            return null;
        }
        $ref  = strtoupper($m[1]);
        $db   = $this->db();
        $rows = $db->table('tbl_dtr_unified_labrefs')->select('lab_ref_no, form_slug, form_category, source_id, overall_result, model_style, material_description')
            ->like('lab_ref_no', $ref, 'after')->orderBy('id', 'DESC')->limit(3)->get()->getResultArray();
        $wantMachine = (bool) preg_match('/\b(machine|file|reading|load|excel)\b/i', $text);
        $wantPdf     = (bool) preg_match('/\b(preview|print|pdf|download)\b/i', $text);

        if (! $rows) {
            $text = 'I could not find ' . $ref . ' in NEXUS-L. Check the number (date part and letters, and the -L / -R ending). If the report was never saved, create it from QC Lab Reports > Material Tests or Shoe Bonding and load the machine file.';
            $out  = ['text' => $text, 'steps' => [], 'actions' => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]]];
        } else {
            $r    = $rows[0];
            $slug = (string) $r['form_slug'];
            $tab  = $slug === 'bonding' ? 'bonding' : 'material';
            $base = 'dtr?tab=' . $tab . '&open=' . rawurlencode($slug . ':' . $r['source_id']);
            $text = $r['lab_ref_no'] . ' found: ' . $r['form_category'] . ($r['overall_result'] ? ', result ' . $r['overall_result'] : '') . ($r['model_style'] ? ', ' . $r['model_style'] : '') . '.';
            $acts = [['type' => 'open', 'label' => 'Open ' . $r['lab_ref_no'], 'url' => site_url($base)]];
            if ($wantPdf) {
                $acts[] = ['type' => 'open', 'label' => 'Open the print / PDF preview', 'url' => site_url($base . '&preview=1')];
                $text .= ' The preview opens with the Download PDF and Print buttons.';
            }
            if ($wantMachine) {
                $text .= ' ' . $this->machineCheck($slug, $r['lab_ref_no']);
            }
            foreach (array_slice($rows, 1) as $o) {
                $acts[] = ['type' => 'open', 'label' => 'Open ' . $o['lab_ref_no'], 'url' => site_url('dtr?tab=' . ($o['form_slug'] === 'bonding' ? 'bonding' : 'material') . '&open=' . rawurlencode($o['form_slug'] . ':' . $o['source_id']))];
            }
            $out = ['text' => $text, 'steps' => [], 'actions' => $acts];
        }

        return $out + ['title' => 'Lab Reference ' . $ref, 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false];
    }


    /**
     * Read-only live database search & statistics query over tbl_dtr_unified_labrefs.
     * Answers queries like:
     *  - "what is the total failed for bonding", "how many fail and pass do we have", "bonding test summary"
     *  - "how many reports failed this month", "total passed tests"
     *  - "find failed reports for Vans", "search reports for supplier X"
     */
    /**
     * Resends Synology Chat approval reminders to designated managers / signatories for pending reports.
     * Triggered by phrases like:
     *  - "resend notification for approver", "remind approver", "remind manager for approval"
     *  - "send notification for pending approval", "notify signatories"
     */
    private function approvalReminderIntent(string $text, int $uid, array $u): ?array
    {
        $low = mb_strtolower(trim($text));

        // Intent detection regex
        $isReminder = (bool) (
            (preg_match('/\b(resend|send|remind|notify|alert|ping|forward)\b/i', $low) &&
             preg_match('/\b(approv|signator|manager|supervisor)/i', $low)) ||
            preg_match('/\b(approval\s*reminder|remind\s*approver|resend\s*approval|notify\s*approver)\b/i', $low)
        );

        if (! $isReminder) {
            return null;
        }

        $db = $this->db();
        if (! $db->tableExists('tbl_dtr_report_approvals') || ! $db->tableExists('tbl_dtr_signatories')) {
            return null;
        }

        // 1. Check if a specific Lab Reference or Application No. was mentioned
        $targetLab = null;
        if (preg_match('/\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b/i', $text, $m)) {
            $targetLab = strtoupper($m[1]);
        }
        $targetApp = null;
        if (preg_match('/\b(APP[-_0-9A-Z]{3,})\b/i', $text, $am)) {
            $targetApp = strtoupper($am[1]);
        }

        // 2. Query for pending approval
        $apv = null;
        if ($targetLab) {
            $apv = $db->table('tbl_dtr_report_approvals')->where(['lab_ref_no' => $targetLab, 'status' => 'pending'])->orderBy('id', 'DESC')->get(1)->getRowArray();
        } elseif ($targetApp) {
            $apv = $db->table('tbl_dtr_report_approvals')->where(['application_no' => $targetApp, 'status' => 'pending'])->orderBy('id', 'DESC')->get(1)->getRowArray();
        } else {
            // Prefer pending approval requested by current user, else latest pending
            if ($uid > 0) {
                $apv = $db->table('tbl_dtr_report_approvals')
                    ->where(['status' => 'pending', 'requested_by' => $uid])
                    ->orderBy('id', 'DESC')
                    ->get(1)->getRowArray();
            }
            if (! $apv) {
                $apv = $db->table('tbl_dtr_report_approvals')->where('status', 'pending')->orderBy('id', 'DESC')->get(1)->getRowArray();
            }
        }

        if (! $apv) {
            return [
                'text'          => "There are currently no reports with 'Pending' status waiting for approval. If you just generated or edited a report, open it and click 'Request approval (QR)' first.",
                'title'         => 'No Pending Approvals Found',
                'steps'         => [
                    'Open the report in QC Lab Reports (Material Tests or Shoe Bonding).',
                    'Click "Generate Report" or "Request approval (QR)".',
                    'Once submitted, the managers and signatories are queued for sign-off.',
                ],
                'actions'       => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'Open InterLink Upload Form', 'url' => site_url('interlink')],
                ],
                'confidence'    => 100,
                'related'       => [
                    ['title' => 'How does QR report approval work?'],
                    ['title' => 'Who are the registered signatories?'],
                ],
                'diagnosis'     => null,
                'knowledge_id'  => null,
                'suggest_admin' => false,
            ];
        }

        // 3. Ensure token is valid and active (refresh if expired)
        $now = date('Y-m-d H:i:s');
        $linkMinutes = max(1, (int) \App\Libraries\SystemSettings::get('approval.link_minutes', 60));
        $isExpired   = empty($apv['expires_at']) || strtotime((string) $apv['expires_at']) <= time();

        $token = (string) $apv['token'];
        if ($isExpired || strlen($token) !== 40) {
            $token   = bin2hex(random_bytes(20));
            $expires = date('Y-m-d H:i:s', time() + $linkMinutes * 60);
            $db->table('tbl_dtr_report_approvals')->where('id', $apv['id'])->update([
                'token'      => $token,
                'expires_at' => $expires,
                'updated_at' => $now,
            ]);
            $apv['token']      = $token;
            $apv['expires_at'] = $expires;
        }

        $minutesLeft = max(1, (int) round((strtotime((string) $apv['expires_at']) - time()) / 60));
        $approvalUrl = site_url('approve/' . $token);

        // 4. Find active registered signatories and their SynologyIDs
        $signRows = $db->table('tbl_dtr_signatories s')
            ->select('s.full_name, s.title, s.user_id, u.SynologyID, u.name as user_name, u.role')
            ->join('tbl_users u', 'u.UserID = s.user_id', 'left')
            ->where('s.is_active', 1)
            ->get()->getResultArray();

        // If a required name is printed on the report, filter to matching signatory
        if (! empty($apv['required_name'])) {
            $filtered = array_values(array_filter($signRows, static fn ($r) => \App\Libraries\ReportPdf::nameMatches((string) $apv['required_name'], (string) $r['full_name'])));
            if (! empty($filtered)) {
                $signRows = $filtered;
            }
        }

        if (empty($signRows)) {
            return [
                'text'          => "Report {$apv['lab_ref_no']} is pending, but no active signatories are currently registered in Report Designer → Signatories.",
                'title'         => 'No Active Signatories Registered',
                'steps'         => [
                    'Ask an administrator to open Report Designer > Signatories.',
                    'Register the Manager or Supervisor account with their printed name and title.',
                    'Ensure the user account has a SynologyID set in tbl_users.',
                ],
                'actions'       => [
                    ['type' => 'open', 'label' => 'Open Approval Link', 'url' => $approvalUrl],
                ],
                'confidence'    => 100,
                'related'       => [],
                'diagnosis'     => null,
                'knowledge_id'  => null,
                'suggest_admin' => true,
            ];
        }

        // 5. Send Synology message to each signatory
        $synologyChat = new \App\Libraries\SynologyChat();
        $notified     = [];
        $noSynologyId = [];

        $msgText = "NEXUS-L Approval Reminder:\nReport {$apv['lab_ref_no']} (Application: " . ($apv['application_no'] ?: 'Regular') . ") requested by " . ($apv['requested_name'] ?: 'Lab Staff') . " is waiting for your approval before submission to InterLink.\nSingle-use sign-off link (expires in {$minutesLeft} min):\n{$approvalUrl}";

        foreach ($signRows as $sr) {
            $sId         = (int) ($sr['SynologyID'] ?? 0);
            $displayName = $sr['full_name'] . ($sr['title'] ? " ({$sr['title']})" : '');
            if ($sId > 0) {
                try {
                    $ok = $synologyChat->send($sId, $msgText);
                    if ($ok) {
                        $notified[] = "• {$displayName} - Synology ID: {$sId}";
                    } else {
                        $notified[] = "• {$displayName} - Failed to send: " . ($synologyChat->lastError() ?: 'Network error');
                    }
                } catch (\Throwable $e) {
                    $notified[] = "• {$displayName} - Error: " . $e->getMessage();
                }
            } else {
                $noSynologyId[] = "• {$displayName} (No SynologyID configured in user profile)";
            }
        }

        // Format bot response
        $replyText = "🔔 **Approval Notification Sent to Signatories!**\n\n";
        $replyText .= "**Pending Report Details:**\n";
        $replyText .= "• **Lab Reference:** {$apv['lab_ref_no']}\n";
        if (! empty($apv['application_no'])) {
            $replyText .= "• **Application No:** {$apv['application_no']}\n";
        }
        $replyText .= "• **Requested By:** " . ($apv['requested_name'] ?: 'User') . "\n";
        $replyText .= "• **Link Expiration:** In {$minutesLeft} minutes\n\n";

        if (! empty($notified)) {
            $replyText .= "**Signatories Notified via Synology Chat:**\n" . implode("\n", $notified) . "\n\n";
        }
        if (! empty($noSynologyId)) {
            $replyText .= "**Pending Signatories (No Synology ID linked):**\n" . implode("\n", $noSynologyId) . "\n\n";
        }
        $replyText .= "The managers can tap the secure single-use link on Synology Chat to view the signed sheet and sign off.";

        $actions = [
            ['type' => 'open', 'label' => 'Open Approval Sheet', 'url' => $approvalUrl],
            ['type' => 'open', 'label' => 'View ' . $apv['lab_ref_no'], 'url' => site_url('dtr?open=' . rawurlencode($apv['lab_ref_no']))],
        ];

        return [
            'text'          => $replyText,
            'title'         => "Approval Notification Sent · {$apv['lab_ref_no']}",
            'steps'         => [],
            'actions'       => $actions,
            'confidence'    => 100,
            'related'       => [
                ['title' => 'What is the status of pending approvals?'],
                ['title' => 'How does QR report approval work?'],
            ],
            'diagnosis'     => null,
            'knowledge_id'  => null,
            'suggest_admin' => false,
        ];
    }

    private function dbSearchIntent(string $text): ?array
    {
        try {
            $low = mb_strtolower(trim($text));

            // 1. Check if the question is asking for counts / statistics / totals / pass-fail
            $isStats = (bool) (
                preg_match('/\b(how\s*many|total|count|number\s*of|summary|statistics?|rate|percentage)\b/i', $low) &&
                preg_match('/\b(fail|failed|pass|passed|tolerable|test|tests|report|reports|bonding|material|results?)\b/i', $low)
            ) || preg_match('/\b(fail\s*(and|&|\/)\s*pass|pass\s*(and|&|\/)\s*fail)\b/i', $low);

            // 2. Check if the question is asking to search / find / list reports
            $isSearch = ! $isStats && (bool) preg_match('/\b(search|find|lookup|list|show)\b.*?\b(failed|passed|bonding|reports?|tests?)\b/i', $low);

            if (! $isStats && ! $isSearch) {
                return null;
            }

            $db = $this->db();
            if (! $db->tableExists('tbl_dtr_unified_labrefs')) {
                return null;
            }

            // Detect category
            $slug = null;
            $categoryName = 'All Reports (Overall Lab)';
            if (preg_match('/\b(bonding|whole\s*shoe|shoe)\b/i', $low)) {
                $slug = 'bonding';
                $categoryName = 'Whole Shoe Bonding';
            } elseif (preg_match('/\b(fabric)\b/i', $low)) {
                $slug = 'fabric';
                $categoryName = 'Fabric Material';
            } elseif (preg_match('/\b(leather)\b/i', $low)) {
                $slug = 'leather';
                $categoryName = 'Leather Material';
            } elseif (preg_match('/\b(rubber)\b/i', $low)) {
                $slug = 'rubber';
                $categoryName = 'Rubber Outsole';
            } elseif (preg_match('/\b(wst|webbing|shoelace|thread)\b/i', $low)) {
                $slug = 'wst';
                $categoryName = 'Webbing / Shoelace / Thread';
            } elseif (preg_match('/\b(fthl|foxing|heel\s*logo)\b/i', $low)) {
                $slug = 'fthl';
                $categoryName = 'Foxing Tape & Heel Logo';
            } elseif (preg_match('/\b(hardware|eyelet|zipper)\b/i', $low)) {
                $slug = 'hardware';
                $categoryName = 'Hardware';
            } elseif (preg_match('/\b(label)\b/i', $low)) {
                $slug = 'label';
                $categoryName = 'Label';
            } elseif (preg_match('/\b(sockliner|foam|sklfm)\b/i', $low)) {
                $slug = 'sklfm';
                $categoryName = 'Sockliner & Foam';
            } elseif (preg_match('/\b(box|carton)\b/i', $low)) {
                $slug = 'box';
                $categoryName = 'Carton Box';
            } elseif (preg_match('/\b(insole)\b/i', $low)) {
                $slug = 'insole';
                $categoryName = 'Insole Board';
            }

            // Detect focus
            $focusFailed = (bool) preg_match('/\b(fail|failed|failure)\b/i', $low) && ! preg_match('/\b(pass|passed)\b/i', $low);
            $focusPassed = (bool) preg_match('/\b(pass|passed)\b/i', $low) && ! preg_match('/\b(fail|failed)\b/i', $low);

            // Detect time filter
            $timeFilterLabel = '';
            $monthPattern = null;
            if (preg_match('/\b(this\s*month|current\s*month)\b/i', $low)) {
                $monthPattern = date('M Y');
                $timeFilterLabel = ' (This Month: ' . date('F Y') . ')';
            } elseif (preg_match('/\b(today)\b/i', $low)) {
                $monthPattern = date('d M Y');
                $timeFilterLabel = ' (Today: ' . date('d M Y') . ')';
            } elseif (preg_match('/\b(this\s*year)\b/i', $low)) {
                $monthPattern = date('Y');
                $timeFilterLabel = ' (This Year: ' . date('Y') . ')';
            }

            // Handle Search query
            if ($isSearch) {
                $kw = trim((string) preg_replace('/\b(search|find|lookup|list|show|reports?|tests?|for|in|qc|lab)\b/i', '', $low));
                $qb = $db->table('tbl_dtr_unified_labrefs')
                    ->select('lab_ref_no, form_slug, form_category, source_id, overall_result, model_style, supplier, tested_date');
                if ($slug) {
                    $qb->where('form_slug', $slug);
                }
                if ($focusFailed) {
                    $qb->where("LOWER(overall_result) IN ('fail', 'failed')", null, false);
                } elseif ($focusPassed) {
                    $qb->where("LOWER(overall_result) IN ('pass', 'passed')", null, false);
                }
                if ($kw !== '') {
                    $qb->groupStart()
                        ->like('lab_ref_no', $kw)
                        ->orLike('model_style', $kw)
                        ->orLike('supplier', $kw)
                        ->orLike('material_description', $kw)
                        ->groupEnd();
                }
                $matches = $qb->orderBy('id', 'DESC')->limit(5)->get()->getResultArray();
                if (! $matches) {
                    return [
                        'text'          => "No reports matching your search were found in the database. You can search by Lab Reference number or check the Lab Tracker.",
                        'title'         => 'Database Search: No Results',
                        'steps'         => [],
                        'actions'       => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                        'confidence'    => 100,
                        'related'       => [
                            ['title' => 'What is the total failed for bonding?'],
                            ['title' => 'How many fail and pass do we have?'],
                        ],
                        'diagnosis'     => null,
                        'knowledge_id'  => null,
                        'suggest_admin' => false,
                    ];
                }
                $msg = "Live Database Search: {$categoryName}\nFound " . count($matches) . " matching reports:\n";
                $acts = [];
                foreach ($matches as $m) {
                    $resTag = $m['overall_result'] ? " [{$m['overall_result']}]" : '';
                    $style = $m['model_style'] ? " - {$m['model_style']}" : '';
                    $msg .= "\n• {$m['lab_ref_no']}{$resTag}{$style}";
                    $tab = $m['form_slug'] === 'bonding' ? 'bonding' : 'material';
                    $acts[] = [
                        'type'  => 'open',
                        'label' => 'Open ' . $m['lab_ref_no'],
                        'url'   => site_url("dtr?tab={$tab}&open=" . rawurlencode($m['form_slug'] . ':' . $m['source_id'])),
                    ];
                }

                return [
                    'text'          => $msg,
                    'title'         => 'QC Lab Database Search',
                    'steps'         => [],
                    'actions'       => $acts,
                    'confidence'    => 100,
                    'related'       => [
                        ['title' => 'What is the total failed for bonding?'],
                        ['title' => 'How many fail and pass do we have?'],
                    ],
                    'diagnosis'     => null,
                    'knowledge_id'  => null,
                    'suggest_admin' => false,
                ];
            }

            // Handle Stats / Count query
            $b = $db->table('tbl_dtr_unified_labrefs')
                ->select("
                    COUNT(*) as total,
                    SUM(CASE WHEN LOWER(overall_result) IN ('pass', 'passed') THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN LOWER(overall_result) IN ('fail', 'failed') THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN LOWER(overall_result) = 'tolerable' THEN 1 ELSE 0 END) as tolerable
                ", false);

            if ($slug) {
                $b->where('form_slug', $slug);
            }
            if ($monthPattern) {
                $b->like('tested_date', $monthPattern);
            }

            $stats = $b->get()->getRowArray() ?: [];
            $total     = (int) ($stats['total'] ?? 0);
            $passed    = (int) ($stats['passed'] ?? 0);
            $failed    = (int) ($stats['failed'] ?? 0);
            $tolerable = (int) ($stats['tolerable'] ?? 0);

            $passPct = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
            $failPct = $total > 0 ? round(($failed / $total) * 100, 1) : 0;
            $tolPct  = $total > 0 ? round(($tolerable / $total) * 100, 1) : 0;

            $msg = "Live Database Statistics: {$categoryName}{$timeFilterLabel}\n";
            $msg .= "• Total Reports: " . number_format($total) . "\n";
            $msg .= "• Passed: " . number_format($passed) . " ({$passPct}%)\n";
            $msg .= "• Failed: " . number_format($failed) . " ({$failPct}%)\n";
            if ($tolerable > 0) {
                $msg .= "• Tolerable: " . number_format($tolerable) . " ({$tolPct}%)\n";
            }

            // List up to 3 recent failed reports
            $actions = [];
            if ($failed > 0) {
                $fb = $db->table('tbl_dtr_unified_labrefs')
                    ->select('lab_ref_no, form_slug, source_id, model_style, tested_date')
                    ->where("LOWER(overall_result) IN ('fail', 'failed')", null, false);
                if ($slug) {
                    $fb->where('form_slug', $slug);
                }
                if ($monthPattern) {
                    $fb->like('tested_date', $monthPattern);
                }
                $recentFailed = $fb->orderBy('id', 'DESC')->limit(3)->get()->getResultArray();
                if ($recentFailed) {
                    $msg .= "\nRecent Failed Reports:";
                    foreach ($recentFailed as $rf) {
                        $model = $rf['model_style'] ? " ({$rf['model_style']})" : '';
                        $msg .= "\n• {$rf['lab_ref_no']}{$model}";
                        $actions[] = [
                            'type'  => 'open',
                            'label' => 'Open ' . $rf['lab_ref_no'],
                            'url'   => site_url('dtr?tab=' . ($rf['form_slug'] === 'bonding' ? 'bonding' : 'material') . '&open=' . rawurlencode($rf['form_slug'] . ':' . $rf['source_id'])),
                        ];
                    }
                }
            }

            // Add direct navigation buttons
            if ($slug === 'bonding') {
                $actions[] = ['type' => 'open', 'label' => 'Open Whole Shoe Bonding', 'url' => site_url('dtr?tab=bonding')];
            } else {
                $actions[] = ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')];
            }

            $related = [
                ['title' => 'What is the total failed for bonding?'],
                ['title' => 'How many fail and pass do we have?'],
                ['title' => 'How many reports failed this month?'],
            ];

            return [
                'text'          => $msg,
                'title'         => "QC Lab Live Query · {$categoryName}",
                'steps'         => [],
                'actions'       => $actions,
                'confidence'    => 100,
                'related'       => $related,
                'diagnosis'     => null,
                'knowledge_id'  => null,
                'suggest_admin' => false,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'dbSearchIntent error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Interactive test computation & pass/fail rating engine.
     * Computes values, evaluates against standards, and prompts for follow-up data if inputs are incomplete.
     */
    private function computeIntent(string $text, string $prev = ''): ?array
    {
        $low = mb_strtolower(trim($text));
        $comb = mb_strtolower(trim($prev . ' ' . $text));

        $hasComputeWord = (bool) preg_match('/\b(comput|calculat|verdict|pass\s*(or|\/)\s*fail|is\s*(this|it)\s*(pass|fail)|evaluate|work\s*out|average\s*of|how\s*much\s*is|formula|rating\s*of)\b/i', $low);
        $hasTestWord = (bool) preg_match('/\b(bonding|u60|utf|fto|fito|tensile|elongation|tear|trouser|stitch|die\s*c|sg|specific\s*gravity|compression\s*set|abrasion|din|ross\s*flex|shrinkage|thickness|specimen|hardness)\b/i', $comb);
        $hasNumbers = (bool) preg_match('/\d+(?:\.\d+)?/', $low);

        if (! $hasComputeWord && ! ($hasTestWord && $hasNumbers && preg_match('/\b(std|standard|readings?|results?|values?|pass|fail|specimen)\b/i', $low))) {
            return null;
        }

        // 1. Whole Shoe Bonding (U60)
        if (preg_match('/\b(bonding|u60|utf|fto|fito|spot)\b/i', $comb)) {
            return $this->computeBonding($low, $comb);
        }

        // 2. Specific Gravity
        if (preg_match('/\b(specific\s*gravity|sg)\b/i', $comb)) {
            return $this->computeSpecificGravity($low, $comb);
        }

        // 3. Compression Set
        if (preg_match('/\b(compression\s*set|cs)\b/i', $comb)) {
            return $this->computeCompressionSet($low, $comb);
        }

        // 4. Shrinkage
        if (preg_match('/\b(shrinkage)\b/i', $comb)) {
            return $this->computeShrinkage($low, $comb);
        }

        // 5. Directional (Tensile, Tear, etc.) or Generic Averaging & Standards
        return $this->computeDirectionalOrGeneric($low, $comb);
    }

    private function computeBonding(string $low, string $comb): array
    {
        preg_match_all('/\b\d+(?:\.\d+)?\b/', $low, $m);
        $nums = array_map('floatval', $m[0] ?? []);

        // Detect STD / Category
        $std = null;
        $category = null;
        if (preg_match('/\b(adult|3(?:\.0)?)\b/i', $comb)) {
            $std = 3.0;
            $category = 'Adult (STD 3.0 kg/cm)';
        } elseif (preg_match('/\b(child|children|kids?|toddler|2\.5)\b/i', $comb)) {
            $std = 2.5;
            $category = 'Children / Kids (STD 2.5 kg/cm)';
        } elseif (preg_match('/\b(fito|center|1(?:\.0)?)\b/i', $comb)) {
            $std = 1.0;
            $category = 'Center FiTO (STD 1.0 kg/cm)';
        } elseif (preg_match('/\b(?:std|standard)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $sm)) {
            $std = (float) $sm[1];
            $category = "Custom STD ({$std} kg/cm)";
        }

        // Filter out the standard number if accidentally included in readings
        if ($std !== null && ($key = array_search($std, $nums, true)) !== false && count($nums) > 1) {
            unset($nums[$key]);
            $nums = array_values($nums);
        }

        // Incomplete: No readings provided
        if (empty($nums)) {
            return [
                'text'       => "To calculate the **Whole Shoe Bonding (U60)** rating, please provide:\n\n"
                    . "1. **Spot readings** (e.g. `2.8, 3.2, 2.5`)\n"
                    . "2. **Shoe Age Category or Standard**:\n"
                    . "   • **Adult**: Standard 3.0 kg/cm (Tolerable $\ge$ 2.4)\n"
                    . "   • **Children / Kids**: Standard 2.5 kg/cm (Tolerable $\ge$ 2.0)\n"
                    . "   • **Center FiTO**: Standard 1.0 kg/cm (Tolerable $\ge$ 0.8)\n\n"
                    . "Please enter your readings and category!",
                'title'      => 'Whole Shoe Bonding Calculation',
                'steps'      => ['Enter your spot readings and shoe category.'],
                'actions'    => [['type' => 'open', 'label' => 'Open Whole Shoe Bonding', 'url' => site_url('dtr?tab=bonding')]],
                'confidence' => 100,
                'related'    => [['title' => 'How are specimen averages and pass fail worked out?']],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        // Incomplete: Readings provided but category/standard missing
        if ($std === null) {
            $min = min($nums);
            $max = max($nums);
            $ave = round(array_sum($nums) / count($nums), 2);
            $readingsStr = implode(', ', $nums);

            $adultRating = ($min >= 3.0) ? 'PASS' : (($min >= 2.4) ? 'TOLERABLE' : 'FAILED');
            $childRating = ($min >= 2.5) ? 'PASS' : (($min >= 2.0) ? 'TOLERABLE' : 'FAILED');

            return [
                'text'       => "📊 **Bonding Readings Calculated:**\n"
                    . "• **Readings:** {$readingsStr}\n"
                    . "• **Min:** {$min} kg/cm | **Max:** {$max} kg/cm | **Average:** {$ave} kg/cm\n\n"
                    . "⚠️ **Which age category is this shoe?**\n"
                    . "• If **Adult (STD 3.0)**: Result is **{$adultRating}** (Tolerable limit: $\ge$ 2.4)\n"
                    . "• If **Children / Kids (STD 2.5)**: Result is **{$childRating}** (Tolerable limit: $\ge$ 2.0)\n\n"
                    . "Reply with **Adult** or **Children** to confirm the official verdict.",
                'title'      => 'Bonding Evaluation: Specify Category',
                'steps'      => ['Select Adult or Children category.'],
                'actions'    => [['type' => 'open', 'label' => 'Open Whole Shoe Bonding', 'url' => site_url('dtr?tab=bonding')]],
                'confidence' => 100,
                'related'    => [['title' => 'How are specimen averages and pass fail worked out?']],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        // Complete data: Evaluate exact Access/VB.NET parity formula
        $min = min($nums);
        $max = max($nums);
        $ave = round(array_sum($nums) / count($nums), 2);
        $tol = round($std * 0.8, 2);

        $worst = 'PASS';
        foreach ($nums as $v) {
            if ($v < $tol) {
                $worst = 'FAILED';
                break;
            }
            if ($v < $std) {
                $worst = 'TOLERABLE';
            }
        }

        $badge = $worst === 'PASS' ? '✅ PASS' : ($worst === 'TOLERABLE' ? '⚠️ TOLERABLE' : '❌ FAILED');
        $readingsStr = implode(', ', $nums);

        $out = "📊 **Whole Shoe Bonding Verdict:** **{$badge}**\n\n"
            . "• **Category:** {$category}\n"
            . "• **Standard (STD):** $\ge$ {$std} kg/cm\n"
            . "• **Tolerable Range (80% STD):** {$tol} – " . ($std - 0.01) . " kg/cm\n"
            . "• **Failure Threshold (< 80% STD):** < {$tol} kg/cm\n"
            . "• **Specimen Readings:** {$readingsStr}\n"
            . "• **Lowest Spot (Min):** {$min} kg/cm\n"
            . "• **Average:** {$ave} kg/cm\n\n"
            . "**Official Rule:** PASS $\ge$ STD, TOLERABLE $\ge$ 80% STD, FAILED < 80% STD.";

        return [
            'text'       => $out,
            'title'      => "Bonding Evaluation · {$badge}",
            'steps'      => [],
            'actions'    => [['type' => 'open', 'label' => 'Open Whole Shoe Bonding', 'url' => site_url('dtr?tab=bonding')]],
            'confidence' => 100,
            'related'    => [['title' => 'How are specimen averages and pass fail worked out?']],
            'diagnosis'  => null,
            'knowledge_id' => null,
            'suggest_admin' => false,
        ];
    }

    private function computeSpecificGravity(string $low, string $comb): array
    {
        preg_match_all('/\b\d+(?:\.\d+)?\b/', $low, $m);
        $nums = array_map('floatval', $m[0] ?? []);

        // Check Hydrostatic (W1 and W2) or Sheet (RawW and Thickness)
        $w1 = null; $w2 = null;
        if (preg_match('/\bw1\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $m1)) $w1 = (float) $m1[1];
        if (preg_match('/\bw2\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $m2)) $w2 = (float) $m2[1];

        if ($w1 !== null && $w2 !== null && ($w1 - $w2) > 0) {
            $sg = round($w1 / ($w1 - $w2), 3);
            $std = null;
            if (preg_match('/\b(?:std|standard)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $sm)) $std = (float) $sm[1];
            $rateStr = '';
            if ($std !== null) {
                $ok = abs($sg - $std) <= 0.02;
                $rateStr = "\n• **Standard:** {$std} (Tolerance $\pm 0.02$)\n• **Verdict:** " . ($ok ? '✅ PASS' : '❌ FAIL');
            }

            return [
                'text'       => "📊 **Specific Gravity (Hydrostatic):**\n\n"
                    . "• **Weight in Air ($W_1$):** {$w1} g\n"
                    . "• **Weight in Water ($W_2$):** {$w2} g\n"
                    . "• **Calculated SG:** **{$sg}** (Formula: $\\frac{W_1}{W_1 - W_2}$){$rateStr}",
                'title'      => "Specific Gravity · {$sg}",
                'steps'      => [],
                'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                'confidence' => 100,
                'related'    => [],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        // Sheet formula: RawW / (100 * (T / 10))
        if (count($nums) >= 2) {
            $rawW = $nums[0];
            $t = $nums[1];
            $denom = 100 * ($t / 10);
            if ($denom > 0) {
                $sg = round($rawW / $denom, 3);

                return [
                    'text'       => "📊 **Specific Gravity (Sheet Specimen):**\n\n"
                        . "• **Raw Weight:** {$rawW} g\n"
                        . "• **Thickness:** {$t} mm\n"
                        . "• **Calculated SG:** **{$sg}** (Formula: $\\frac{\\text{RawWeight}}{100 \\times (T / 10)}$)\n\n"
                        . "Tolerance against standard is $\pm 0.02$.",
                    'title'      => "Specific Gravity · {$sg}",
                    'steps'      => [],
                    'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                    'confidence' => 100,
                    'related'    => [],
                    'diagnosis'  => null,
                    'knowledge_id' => null,
                    'suggest_admin' => false,
                ];
            }
        }

        return [
            'text'       => "To calculate **Specific Gravity (SG)**, please provide either:\n\n"
                . "• **Method 1 (Hydrostatic):** `W1=...` (weight in air) and `W2=...` (weight in water)\n"
                . "• **Method 2 (Sheet):** Raw Weight (g) and Thickness (mm)\n\n"
                . "You can also specify your standard (e.g. `STD=1.15`). What are your values?",
            'title'      => 'Specific Gravity Calculator',
            'steps'      => ['Provide W1 and W2 or Raw Weight and Thickness.'],
            'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
            'confidence' => 100,
            'related'    => [],
            'diagnosis'  => null,
            'knowledge_id' => null,
            'suggest_admin' => false,
        ];
    }

    private function computeCompressionSet(string $low, string $comb): array
    {
        preg_match_all('/\b\d+(?:\.\d+)?\b/', $low, $m);
        $nums = array_map('floatval', $m[0] ?? []);

        $t0 = null; $t1 = null; $ts = null;
        if (preg_match('/\b(?:t0|before|original)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $m0)) $t0 = (float) $m0[1];
        if (preg_match('/\b(?:t1|after|final)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $m1)) $t1 = (float) $m1[1];
        if (preg_match('/\b(?:ts|spacer)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $ms)) $ts = (float) $ms[1];

        if ($t0 === null && count($nums) >= 3) {
            $t0 = $nums[0]; $t1 = $nums[1]; $ts = $nums[2];
        }

        if ($t0 !== null && $t1 !== null && $ts !== null && ($t0 - $ts) != 0) {
            $cs = round((($t0 - $t1) / ($t0 - $ts)) * 100, 1);
            $std = null;
            if (preg_match('/\b(?:std|standard)\s*[:=]?\s*(\d+(?:\.\d+)?)/i', $comb, $sm)) $std = (float) $sm[1];
            $rateStr = '';
            if ($std !== null) {
                $ok = $cs <= $std;
                $rateStr = "\n• **Standard (Max):** $\le$ {$std}%\n• **Verdict:** " . ($ok ? '✅ PASS' : '❌ FAIL');
            }

            return [
                'text'       => "📊 **Compression Set Calculation:**\n\n"
                    . "• **Original Thickness ($t_0$):** {$t0} mm\n"
                    . "• **After Recovery ($t_1$):** {$t1} mm\n"
                    . "• **Spacer Thickness ($t_s$):** {$ts} mm\n"
                    . "• **Calculated Compression Set:** **{$cs}%**\n"
                    . "• **Formula:** $\\frac{t_0 - t_1}{t_0 - t_s} \\times 100\\%${$rateStr}",
                'title'      => "Compression Set · {$cs}%",
                'steps'      => [],
                'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                'confidence' => 100,
                'related'    => [],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        return [
            'text'       => "To calculate **Compression Set (CS)**, I need 3 measurements:\n\n"
                . "1. **Original Thickness ($t_0$)** (e.g. `10.0 mm`)\n"
                . "2. **Final Recovered Thickness ($t_1$)** (e.g. `9.2 mm`)\n"
                . "3. **Spacer Thickness ($t_s$)** (e.g. `6.0 mm`)\n\n"
                . "Formula: $\\frac{t_0 - t_1}{t_0 - t_s} \\times 100\\%$.\n"
                . "What are your values?",
            'title'      => 'Compression Set Calculator',
            'steps'      => ['Provide original, after, and spacer thickness.'],
            'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
            'confidence' => 100,
            'related'    => [],
            'diagnosis'  => null,
            'knowledge_id' => null,
            'suggest_admin' => false,
        ];
    }

    private function computeShrinkage(string $low, string $comb): array
    {
        preg_match_all('/\b\d+(?:\.\d+)?\b/', $low, $m);
        $nums = array_map('floatval', $m[0] ?? []);

        if (count($nums) >= 2 && $nums[0] > 0) {
            $before = $nums[0];
            $after = $nums[1];
            $sh = round((($before - $after) / $before) * 100, 2);

            return [
                'text'       => "📊 **Shrinkage Calculation:**\n\n"
                    . "• **Original Dimension:** {$before} mm\n"
                    . "• **After Test Dimension:** {$after} mm\n"
                    . "• **Shrinkage:** **{$sh}%**\n"
                    . "• **Formula:** $\\frac{\\text{Before} - \\text{After}}{\\text{Before}} \\times 100\\%$",
                'title'      => "Shrinkage · {$sh}%",
                'steps'      => [],
                'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                'confidence' => 100,
                'related'    => [],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        return [
            'text'       => "To calculate **Shrinkage %**, please provide:\n"
                . "1. **Before test dimension** (e.g. `100 mm`)\n"
                . "2. **After test dimension** (e.g. `98.5 mm`)\n\n"
                . "Formula: $\\frac{\\text{Before} - \\text{After}}{\\text{Before}} \\times 100\\%$.",
            'title'      => 'Shrinkage Calculator',
            'steps'      => ['Provide before and after test dimensions.'],
            'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
            'confidence' => 100,
            'related'    => [],
            'diagnosis'  => null,
            'knowledge_id' => null,
            'suggest_admin' => false,
        ];
    }

    private function computeDirectionalOrGeneric(string $low, string $comb): array
    {
        preg_match_all('/\b\d+(?:\.\d+)?\b/', $low, $m);
        $nums = array_map('floatval', $m[0] ?? []);

        // Detect Standard if specified
        $std = null;
        if (preg_match('/\b(?:std|standard|req|requirement)\s*[:=]?\s*(?:>=|<=)?\s*(\d+(?:\.\d+)?)/i', $comb, $sm)) {
            $std = (float) $sm[1];
            // Remove STD from numbers list if present
            if (($key = array_search($std, $nums, true)) !== false && count($nums) > 1) {
                unset($nums[$key]);
                $nums = array_values($nums);
            }
        }

        // Detect Test Name
        $testName = 'Material Test';
        if (preg_match('/\b(tensile)\b/i', $comb)) $testName = 'Tensile Strength';
        elseif (preg_match('/\b(elongation)\b/i', $comb)) $testName = 'Elongation';
        elseif (preg_match('/\b(trouser\s*tear)\b/i', $comb)) $testName = 'Trouser Tear';
        elseif (preg_match('/\b(stitch\s*tear)\b/i', $comb)) $testName = 'Stitch Tear';
        elseif (preg_match('/\b(die\s*c)\b/i', $comb)) $testName = 'Die C Tear';
        elseif (preg_match('/\b(mullen\s*burst)\b/i', $comb)) $testName = 'Mullen Burst';
        elseif (preg_match('/\b(thickness)\b/i', $comb)) $testName = 'Thickness';
        elseif (preg_match('/\b(hardness)\b/i', $comb)) $testName = 'Hardness';

        if (empty($nums)) {
            return [
                'text'       => "To calculate the average and evaluate **{$testName}**, please provide:\n\n"
                    . "1. **Specimen readings** (e.g. `14.2, 15.1, 14.8`)\n"
                    . "2. **Standard requirement** (e.g. `STD >= 12.0`)\n\n"
                    . "What are your specimen values and standard?",
                'title'      => "{$testName} Calculation",
                'steps'      => ['Enter your specimen readings and standard.'],
                'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                'confidence' => 100,
                'related'    => [['title' => 'How are specimen averages and pass fail worked out?']],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        $min = min($nums);
        $max = max($nums);
        $ave = round(array_sum($nums) / count($nums), 2);
        $readingsStr = implode(', ', $nums);

        if ($std === null) {
            return [
                'text'       => "📊 **{$testName} Specimens Calculated:**\n\n"
                    . "• **Readings:** {$readingsStr}\n"
                    . "• **Average:** **{$ave}** (Min: {$min}, Max: {$max})\n\n"
                    . "⚠️ **What is the Standard (STD) specification?**\n"
                    . "Please enter your required standard (e.g. `STD 14.0`) so I can determine whether this is a **PASS** or **FAIL**.",
                'title'      => "{$testName} · Average: {$ave}",
                'steps'      => ['Specify the Standard requirement to determine Pass/Fail.'],
                'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
                'confidence' => 100,
                'related'    => [['title' => 'How are specimen averages and pass fail worked out?']],
                'diagnosis'  => null,
                'knowledge_id' => null,
                'suggest_admin' => false,
            ];
        }

        $pass = ($ave >= $std);
        $badge = $pass ? '✅ PASS' : '❌ FAIL';

        return [
            'text'       => "📊 **{$testName} Verdict:** **{$badge}**\n\n"
                . "• **Specimens:** {$readingsStr}\n"
                . "• **Average:** **{$ave}** (Min: {$min}, Max: {$max})\n"
                . "• **Standard Specification:** $\ge$ {$std}\n"
                . "• **Verdict:** **{$badge}** ({$ave} " . ($pass ? '$\ge$' : '<') . " {$std})\n\n"
                . "**Formula:** Average = $\\frac{\\sum \\text{Specimens}}{N}$. Evaluated against standard priority rules.",
            'title'      => "{$testName} · {$badge}",
            'steps'      => [],
            'actions'    => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]],
            'confidence' => 100,
            'related'    => [['title' => 'How are specimen averages and pass fail worked out?']],
            'diagnosis'  => null,
            'knowledge_id' => null,
            'suggest_admin' => false,
        ];
    }

    /** Read-only look at the machine file of a report: found or not, and how many readings it holds. */
    private function machineCheck(string $slug, string $labRef): string
    {
        try {
            $maps = $this->db()->table('tbl_machine_maps')->where(['slug' => $slug, 'enabled' => 1])->orderBy('sort_order')->get()->getResultArray();
            if (! $maps) {
                return 'No machine-file reading rule is set up for this report type yet; an administrator can add one in the Report Designer.';
            }
            $reader = new \App\Libraries\MachineFileReader();
            $found  = [];
            $count  = 0;
            foreach ($maps as $m) {
                $file = $reader->findFile((string) $m['folder'], $labRef);
                if ($file) {
                    $got     = $reader->apply($reader->grid($file, (string) $m['sheet']), (array) json_decode((string) $m['rules_json'], true), $labRef);
                    $found[] = basename($file);
                    $count  += count($got);
                }
            }

            return $found ? 'Machine file found (' . implode(', ', array_unique($found)) . ') with ' . $count . ' readings. Open the report and press "Load Machine Results" to bring them in.' : 'No machine file for this Lab Ref No. was found yet. The test may not be finished or saved: try again in a minute, then check the number.';
        } catch (\Throwable $e) {
            return 'I could not check the machine file right now.';
        }
    }

    /** Builds the answer for one question. @return array<string,mixed> */
    private function compose(string $text, array $ctx, string $page): array
    {
        $low = mb_strtolower($text);
        if (preg_match('/^(hi|hello|hey|good (morning|afternoon|evening)|yo)\b/', $low) && mb_strlen($low) < 30) {
            return ['text' => 'Hello! I am LEXIS AI (Laboratory Execution Intelligence System). Ask me how to do something in this system, calculate test results, or check report status and I will help you.', 'steps' => [], 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false];
        }
        if (preg_match('/^(thanks|thank you|ty|ok thanks|great|got it)\b/', $low) && mb_strlen($low) < 40) {
            return ['text' => 'You are welcome! Ask me anything else about NEXUS-L.', 'steps' => [], 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false];
        }

        if (preg_match('/\b(poem|joke|weather|recipe|song|lyrics|story|horoscope|football|basketball|bitcoin|stock market|movie|capital of|who won|translate this|news today|riddle)\b/i', $low) && ! preg_match('/\b(nexus|report|lab|interlink|bonding|machine|pdf|sync|designer|inbox|dtr|sample|test)\b/i', $low)) {
            return ['text' => 'I can only help with NEXUS-L: how to use its pages, fix problems, and find your reports. What would you like to do in the system?', 'steps' => [], 'actions' => [], 'confidence' => 0, 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false];
        }

        // a bare "I need help" has no searchable words: answer it directly
        if (preg_match('/^(i\s+)?(really\s+)?(need\s+)?(some\s+)?(help|assistance|support)(\s+me|\s+please)?[.!?]*$/i', trim($low)) || preg_match('/^(can|could)\s+(you|someone)\s+(please\s+)?help(\s+me)?[.!?]*$/i', trim($low))) {
            $row = $this->db()->table('tbl_bot_knowledge')->where(['title' => 'I need help', 'status' => 'approved'])->get()->getRowArray();
            if ($row) {
                return ['text' => (string) $row['body'], 'title' => (string) $row['title'], 'steps' => [], 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => (int) $row['id'], 'suggest_admin' => false];
            }
        }

        $hits = Brain::search($text, $ctx, 5);
        $conf = Brain::confidence($hits, $text);
        $db   = $this->db();

        if (! $hits || $conf < 30) {
            $norm = implode(' ', array_slice(Brain::tokens($text), 0, 12));
            if ($norm !== '') {
                $now = date('Y-m-d H:i:s');
                $row = $db->table('tbl_bot_unanswered')->where('norm', mb_substr($norm, 0, 200))->get()->getRowArray();
                if ($row) {
                    $db->table('tbl_bot_unanswered')->where('id', $row['id'])->set('times', 'times + 1', false)->update(['last_seen' => $now, 'status' => $row['status'] === 'answered' ? 'open' : $row['status']]);
                } else {
                    $db->table('tbl_bot_unanswered')->insert(['norm' => mb_substr($norm, 0, 200), 'sample' => mb_substr($text, 0, 400), 'page' => mb_substr($page, 0, 160), 'times' => 1, 'status' => 'open', 'first_seen' => $now, 'last_seen' => $now]);
                }
            }

            return [
                'text'  => 'I do not have a verified answer for that yet. I have noted the question so it can be added. You can rephrase it, or send it to an administrator who will help you directly.',
                'steps' => [], 'actions' => [], 'confidence' => $conf, 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => true,
                'related' => array_map(static fn ($h) => ['id' => (int) $h['row']['id'], 'title' => $h['row']['title']], array_slice($hits, 0, 3)),
            ];
        }

        $best = $hits[0]['row'];
        if ($conf < 45) {
            \App\Libraries\BrainLearn::noteGap($text, $page, 'low', (int) $best['id']);
        }
        $db->table('tbl_bot_knowledge')->where('id', $best['id'])->set('hits', 'hits + 1', false)->update();
        $diag = null;
        if (! empty($best['diagnose'])) {
            $diag = Brain::diagnose((string) $best['diagnose']);
        }
        $actions = [];
        foreach ((array) json_decode((string) ($best['actions_json'] ?? ''), true) as $a) {
            if (! is_array($a) || empty($a['label'])) {
                continue;
            }
            if (($a['type'] ?? '') === 'open' && ! empty($a['url'])) {
                $a['url'] = preg_match('#^https?://#i', (string) $a['url']) ? null : site_url(ltrim((string) $a['url'], '/'));
                if ($a['url'] === null) {
                    continue;
                }
            }
            if (($a['role'] ?? '') === 'admin' && ! $this->isAdmin()) {
                continue;
            }
            $actions[] = $a;
        }

        return [
            'text'  => ($conf < 45 ? "I am not fully sure this is what you mean, but this may help:

" : '') . (string) $best['body'],
            'title' => (string) $best['title'],
            'steps' => array_values(array_filter((array) json_decode((string) ($best['steps_json'] ?? ''), true), 'is_string')),
            'actions' => $actions, 'confidence' => $conf, 'diagnosis' => $diag, 'knowledge_id' => (int) $best['id'], 'suggest_admin' => $conf < 45,
            'related' => array_map(static fn ($h) => ['id' => (int) $h['row']['id'], 'title' => $h['row']['title']], array_slice($hits, 1, 3)),
        ];
    }

    /** GET bot/topic?id= : show one related topic */
    public function topic()
    {
        $id  = (int) $this->request->getGet('id');
        $row = $this->db()->table('tbl_bot_knowledge')->where(['id' => $id, 'status' => 'approved'])->get()->getRowArray();
        if (! $row || ($row['role_min'] === 'admin' && ! $this->isAdmin())) {
            return $this->json(['ok' => false, 'error' => 'Not found.'], 404);
        }
        $this->db()->table('tbl_bot_knowledge')->where('id', $id)->set('hits', 'hits + 1', false)->update();

        return $this->json(['ok' => true, 'reply' => ['text' => $row['body'], 'title' => $row['title'], 'steps' => array_values(array_filter((array) json_decode((string) $row['steps_json'], true), 'is_string')), 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => ! empty($row['diagnose']) ? Brain::diagnose((string) $row['diagnose']) : null]]);
    }

    /** GET bot/suggest?page= : starter questions for this page */
    public function suggest()
    {
        $seg  = explode('/', trim((string) $this->request->getGet('page'), '/'))[0] ?? '';
        $q    = $this->db()->table('tbl_bot_knowledge')->select('title')->where('status', 'approved')->where('role_min', 'user');
        if ($seg !== '') {
            $q->like('page_match', $seg);
        }
        $rows = $q->orderBy('hits', 'DESC')->limit(4)->get()->getResultArray();
        if (count($rows) < 3) {
            $rows = array_merge($rows, $this->db()->table('tbl_bot_knowledge')->select('title')->where(['status' => 'approved', 'role_min' => 'user'])->orderBy('hits', 'DESC')->limit(4)->get()->getResultArray());
        }

        return $this->json(['ok' => true, 'items' => array_values(array_unique(array_column($rows, 'title')))]);
    }

    /** POST bot/feedback {chat_id, value} */
    public function feedback()
    {
        $p   = $this->input();
        $val = ((int) ($p['value'] ?? 0)) > 0 ? 1 : -1;
        $db  = $this->db();
        $c   = $db->table('tbl_bot_chats c')->select('c.id, c.knowledge_id, c.feedback')->join('tbl_bot_threads t', 't.id = c.thread_id')->where(['c.id' => (int) ($p['chat_id'] ?? 0), 't.user_id' => (int) $this->auth->user()['id']])->get()->getRowArray();
        if (! $c) {
            return $this->json(['ok' => false], 404);
        }
        if ($c['knowledge_id'] && (int) $c['feedback'] !== $val) {
            $delta = $val - (int) $c['feedback'];
            $db->table('tbl_bot_knowledge')->where('id', $c['knowledge_id'])->set('helpful', 'helpful + ' . (int) $delta, false)->update();
        }
        $db->table('tbl_bot_chats')->where('id', $c['id'])->update(['feedback' => $val]);
        if (\App\Libraries\AiBridge::enabled()) {
            $qq = $db->query("SELECT message FROM tbl_bot_chats WHERE thread_id = (SELECT thread_id FROM tbl_bot_chats WHERE id = ?) AND sender = 'user' AND id < ? ORDER BY id DESC LIMIT 1", [$c['id'], $c['id']])->getRowArray();
            \App\Libraries\AiBridge::feedback((int) $this->auth->user()['id'], $c['knowledge_id'] ? (int) $c['knowledge_id'] : null, $val, (string) ($qq['message'] ?? ''));
        }
        // learn from it: a thumbs-down is a gap; a thumbs-up after a rephrase teaches the earlier wording
        if ((int) $c['feedback'] !== $val) {
            try {
                if ($c['knowledge_id']) {
                    // one learning step: the words of this question now point a little more (or less) to this answer
                    \App\Libraries\BrainLearn::train(\App\Libraries\BrainLearn::questionTerms((int) $c['id']), (int) $c['knowledge_id'], $val > 0 ? 1 : 0);
                }
                if ($val < 0) {
                    $q = $db->query("SELECT message FROM tbl_bot_chats WHERE thread_id = (SELECT thread_id FROM tbl_bot_chats WHERE id = ?) AND sender = 'user' AND id < ? ORDER BY id DESC LIMIT 1", [$c['id'], $c['id']])->getRowArray();
                    if ($q) {
                        \App\Libraries\BrainLearn::noteGap((string) $q['message'], '', 'down', $c['knowledge_id'] ? (int) $c['knowledge_id'] : null);
                    }
                } else {
                    \App\Libraries\BrainLearn::noteHelpful((int) $c['id']);
                }
            } catch (\Throwable $e) {
                log_message('error', 'BrainLearn: ' . $e->getMessage());
            }
        }

        return $this->json(['ok' => true]);
    }

    /** POST bot/escalate {thread_id?, page?} : opens a live session with an administrator (starts a chat if needed) */
    public function escalate()
    {
        $p    = $this->input();
        $u    = $this->auth->user();
        $page = (string) ($p['page'] ?? '');
        BotSessions::expire();
        $t    = $this->threadFor((int) $u['id'], (string) ($u['username'] ?? ''), $page, (int) ($p['thread_id'] ?? 0) ?: null, 'Talk to an administrator');
        $s    = BotSessions::open($t, $u, $page);

        return $this->json(['ok' => true, 'thread_id' => (int) $t['id'], 'session' => BotSessions::public($s)]);
    }

    /** GET bot/thread?thread_id=&after= : messages of the user's own thread (also the poll for new ones) */
    public function thread()
    {
        $u  = $this->auth->user();
        $db = $this->db();
        $id = (int) $this->request->getGet('thread_id');
        BotSessions::expire();
        if ($id === 0) {
            $t = $db->table('tbl_bot_threads')->where('user_id', (int) $u['id'])->whereIn('status', ['bot', 'escalated'])->orderBy('id', 'DESC')->get(1)->getRowArray();
        } else {
            $t = $db->table('tbl_bot_threads')->where(['id' => $id, 'user_id' => (int) $u['id']])->get()->getRowArray();
        }
        if (! $t) {
            return $this->json(['ok' => true, 'thread' => null, 'messages' => []]);
        }
        $after = (int) $this->request->getGet('after');
        $msgs  = $db->table('tbl_bot_chats')->select('id, sender, sender_name, message, feedback, created_at')->where('thread_id', $t['id'])->where('id >', $after)->orderBy('id')->limit(200)->get()->getResultArray();
        if ((int) $t['user_unread'] > 0) {
            $db->table('tbl_bot_threads')->where('id', $t['id'])->update(['user_unread' => 0]);
        }

        $sess = $db->table('tbl_bot_sessions')->where('thread_id', $t['id'])->orderBy('id', 'DESC')->get(1)->getRowArray();

        return $this->json(['ok' => true, 'thread' => ['id' => (int) $t['id'], 'status' => $t['status'], 'admin_user' => $t['admin_user']], 'session' => BotSessions::public($sess ?: null), 'messages' => $msgs]);
    }

    /** GET bot/badge : unread counts for the widget (user) and the sidebar (admin) */
    public function badge()
    {
        $u  = $this->auth->user();
        $db = $this->db();
        $out = ['ok' => true, 'user_unread' => (int) ($db->table('tbl_bot_threads')->selectSum('user_unread', 's')->where('user_id', (int) $u['id'])->get()->getRow()->s ?? 0)];
        $out['user_live'] = $db->table('tbl_bot_sessions')->where(['user_id' => (int) $u['id'], 'status' => 'open'])->where('last_activity_at >=', date('Y-m-d H:i:s', time() - BotSessions::IDLE))->countAllResults() > 0;
        if ($this->isAdmin()) {
            BotSessions::expire();
            $out['admin_unread'] = (int) $db->table('tbl_bot_sessions')->where('status', 'open')->groupStart()->where('admin_unread >', 0)->orWhere('admin_user IS NULL', null, false)->groupEnd()->countAllResults();
        }

        return $this->json($out);
    }

    // ================================================================================ admin console

    public function console(): string
    {
        return $this->render('brain/index', ['title' => 'Brain', 'withCss' => 'brain.css', 'withJs' => ['brain.js']]);
    }

    /** GET brain/overview */
    public function overview()
    {
        $db = $this->db();
        $since = date('Y-m-d H:i:s', time() - 86400 * 7);

        return $this->json([
            'ok' => true,
            'knowledge' => ['approved' => $db->table('tbl_bot_knowledge')->where('status', 'approved')->countAllResults(), 'draft' => $db->table('tbl_bot_knowledge')->where('status', 'draft')->countAllResults()],
            'threads' => ['open' => $db->table('tbl_bot_threads')->where('status', 'escalated')->countAllResults(), 'total' => $db->table('tbl_bot_threads')->countAllResults()],
            'unanswered' => $db->table('tbl_bot_unanswered')->where('status', 'open')->countAllResults(),
            'issues' => $db->table('tbl_bot_issues')->where('status', 'open')->countAllResults(),
            'events7d' => $db->table('tbl_bot_events')->where('created_at >=', $since)->countAllResults(),
            'errors7d' => $db->table('tbl_bot_events')->whereIn('event_type', ['error', 'http_error'])->where('created_at >=', $since)->countAllResults(),
            'topPages' => $db->query("SELECT page, COUNT(*) c FROM tbl_bot_events WHERE event_type = 'view' AND created_at >= ? GROUP BY page ORDER BY c DESC LIMIT 6", [$since])->getResultArray(),
            'answerRate' => $this->answerRate(),
        ]);
    }

    private function answerRate(): ?int
    {
        $db  = $this->db();
        $tot = $db->table('tbl_bot_chats')->where('sender', 'bot')->where('created_at >=', date('Y-m-d H:i:s', time() - 86400 * 30))->countAllResults();
        if ($tot === 0) {
            return null;
        }
        $good = $db->table('tbl_bot_chats')->where('sender', 'bot')->where('knowledge_id IS NOT NULL', null, false)->where('created_at >=', date('Y-m-d H:i:s', time() - 86400 * 30))->countAllResults();

        return (int) round($good * 100 / $tot);
    }

    /** GET brain/threads?status= */
    public function threads()
    {
        $st = (string) $this->request->getGet('status');
        $q  = $this->db()->table('tbl_bot_threads')->orderBy('admin_unread', 'DESC')->orderBy('updated_at', 'DESC')->limit(100);
        if (in_array($st, ['bot', 'escalated', 'resolved'], true)) {
            $q->where('status', $st);
        }

        return $this->json(['ok' => true, 'rows' => $q->get()->getResultArray()]);
    }

    /** GET brain/thread/(:num) */
    public function threadMessages(int $id)
    {
        $db = $this->db();
        $t  = $db->table('tbl_bot_threads')->where('id', $id)->get()->getRowArray();
        if (! $t) {
            return $this->json(['ok' => false], 404);
        }
        $db->table('tbl_bot_threads')->where('id', $id)->update(['admin_unread' => 0]);

        return $this->json(['ok' => true, 'thread' => $t, 'session' => BotSessions::public(BotSessions::active($id)), 'messages' => $db->table('tbl_bot_chats')->where('thread_id', $id)->orderBy('id')->limit(300)->get()->getResultArray(),
            'events' => $db->table('tbl_bot_events')->select('event_type, page, target, detail, status, created_at')->where('user_id', (int) $t['user_id'])->orderBy('id', 'DESC')->limit(25)->get()->getResultArray()]);
    }

    /** POST brain/reply {session_id | thread_id, message} : only into an open session */
    public function reply()
    {
        $p   = $this->input();
        $msg = Brain::redact(trim((string) ($p['message'] ?? '')));
        if ($msg === '') {
            return $this->json(['ok' => false, 'error' => 'Write a message.'], 422);
        }
        BotSessions::expire();
        $s = ! empty($p['session_id']) ? BotSessions::find((int) $p['session_id']) : BotSessions::active((int) ($p['thread_id'] ?? 0));
        if (! $s || $s['status'] !== 'open') {
            return $this->json(['ok' => false, 'error' => 'This chat is closed. Nobody can write into it any more.'], 409);
        }
        $id = BotSessions::fromAdmin($s, $this->auth->user(), $msg);
        if (\App\Libraries\AiBridge::enabled()) {
            $q = $this->db()->table('tbl_bot_chats')->select('message')->where(['thread_id' => (int) $s['thread_id'], 'sender' => 'user'])->orderBy('id', 'DESC')->get(1)->getRowArray();
            \App\Libraries\AiBridge::adminReply((int) $s['user_id'], (int) ($this->auth->user()['id'] ?? 0), (string) ($q['message'] ?? ''), $msg);
        }

        return $this->json(['ok' => true, 'chat_id' => $id, 'session' => BotSessions::public(BotSessions::find((int) $s['id']))]);
    }

    /** GET brain/live : open sessions for the attention card on the admins' pages */
    public function live()
    {
        return $this->json(['ok' => true, 'idle' => BotSessions::IDLE, 'sessions' => array_map([BotSessions::class, 'public'], BotSessions::openList())]);
    }

    /** GET brain/session/(:num)?after= : one session's chat (also the poll for new lines) */
    public function session(int $id)
    {
        BotSessions::expire();
        $s = BotSessions::find($id);
        if (! $s) {
            return $this->json(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        $db    = $this->db();
        $after = (int) $this->request->getGet('after');
        $msgs  = $db->table('tbl_bot_chats')->select('id, sender, sender_name, message, created_at')->where('session_id', $id)->where('id >', $after)->orderBy('id')->limit(300)->get()->getResultArray();
        if ($after === 0) {
            // what the user asked the bot just before the session, for context
            $before = $db->table('tbl_bot_chats')->select('id, sender, sender_name, message, created_at')->where('thread_id', $s['thread_id'])->where('session_id IS NULL', null, false)
                ->where('created_at <=', $s['started_at'])->orderBy('id', 'DESC')->limit(6)->get()->getResultArray();
            $msgs = array_merge(array_map(static fn ($m) => $m + ['earlier' => 1], array_reverse($before)), $msgs);
        }
        if ((int) $s['admin_unread'] > 0 && $s['status'] === 'open') {
            $db->table('tbl_bot_sessions')->where('id', $id)->update(['admin_unread' => 0]);
            $db->table('tbl_bot_threads')->where('id', $s['thread_id'])->update(['admin_unread' => 0]);
        }

        return $this->json(['ok' => true, 'session' => BotSessions::public(BotSessions::find($id)), 'messages' => $msgs]);
    }

    /** POST brain/session/close {session_id} : the administrator ends the chat; nobody can write into it after this */
    public function sessionClose()
    {
        $s = BotSessions::find((int) ($this->input()['session_id'] ?? 0));
        if (! $s) {
            return $this->json(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        BotSessions::close($s, (string) ($this->auth->user()['username'] ?? 'admin'));

        return $this->json(['ok' => true, 'session' => BotSessions::public(BotSessions::find((int) $s['id']))]);
    }

    /** POST brain/resolve {thread_id, learn?:{title,keywords,body,steps[]}} : closes the chat and (optionally) teaches the bot */
    public function resolve()
    {
        $p  = $this->input();
        $id = (int) ($p['thread_id'] ?? 0);
        $db = $this->db();
        if ($s = BotSessions::active($id)) {
            BotSessions::close($s, (string) ($this->auth->user()['username'] ?? 'admin'));
        }
        $db->table('tbl_bot_threads')->where('id', $id)->update(['status' => 'resolved', 'admin_unread' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->say($id, 'bot', 'NEXUS-L Assistant', 'This conversation was marked as resolved. Start a new question any time.');
        $kid = null;
        if (! empty($p['learn']) && is_array($p['learn'])) {
            $kid = $this->saveKnowledgeRow($p['learn'] + ['source' => 'learned', 'status' => 'approved']);
        }

        return $this->json(['ok' => true, 'knowledge_id' => $kid]);
    }

    /** GET brain/learn-draft?thread_id= : proposes an entry from the chat (first user message + admin's replies) */
    public function learnDraft()
    {
        $id   = (int) $this->request->getGet('thread_id');
        $msgs = $this->db()->table('tbl_bot_chats')->where('thread_id', $id)->orderBy('id')->get()->getResultArray();
        $q    = '';
        $a    = [];
        foreach ($msgs as $m) {
            if ($m['sender'] === 'user' && $q === '') {
                $q = $m['message'];
            }
            if ($m['sender'] === 'admin') {
                $a[] = $m['message'];
            }
        }

        return $this->json(['ok' => true, 'draft' => ['title' => mb_substr($q, 0, 190), 'keywords' => implode(', ', array_slice(Brain::tokens($q), 0, 10)), 'body' => implode("\n\n", $a), 'steps' => []]]);
    }

    // ------------------------------------------------------------------------------------------ knowledge

    private function saveKnowledgeRow(array $k): ?int
    {
        $title = trim(strip_tags((string) ($k['title'] ?? '')));
        $body  = trim(strip_tags((string) ($k['body'] ?? '')));
        if ($title === '' || $body === '') {
            return null;
        }
        $steps = $k['steps'] ?? [];
        if (is_string($steps)) {
            $steps = preg_split('/\r?\n/', trim($steps)) ?: [];
        }
        $steps   = array_values(array_filter(array_map(static fn ($s) => mb_substr(trim(strip_tags((string) $s)), 0, 400), (array) $steps), 'strlen'));
        $actions = [];
        foreach ((array) ($k['actions'] ?? []) as $a) {
            if (is_array($a) && ! empty($a['label']) && ($a['type'] ?? '') === 'open' && ! empty($a['url']) && ! preg_match('#^[a-z]+://#i', (string) $a['url'])) {
                $actions[] = ['type' => 'open', 'label' => mb_substr(strip_tags((string) $a['label']), 0, 60), 'url' => mb_substr((string) $a['url'], 0, 200)] + (($a['role'] ?? '') === 'admin' ? ['role' => 'admin'] : []);
            }
        }
        $now  = date('Y-m-d H:i:s');
        $data = [
            'kind' => in_array($k['kind'] ?? '', ['playbook', 'qa', 'system', 'learned'], true) ? $k['kind'] : 'playbook',
            'title' => mb_substr($title, 0, 200), 'keywords' => mb_substr(trim(strip_tags((string) ($k['keywords'] ?? ''))), 0, 600),
            'body' => mb_substr($body, 0, 4000), 'steps_json' => json_encode($steps, JSON_UNESCAPED_UNICODE), 'actions_json' => json_encode($actions, JSON_UNESCAPED_SLASHES),
            'page_match' => mb_substr(preg_replace('/[^a-z0-9_\/-]/i', '', (string) ($k['page_match'] ?? '')), 0, 80) ?: null,
            'error_match' => mb_substr(trim(strip_tags((string) ($k['error_match'] ?? ''))), 0, 120) ?: null,
            'diagnose' => in_array($k['diagnose'] ?? '', ['machine_share', 'interlink_session', 'pdf_engine', 'worker', 'disk', 'brain'], true) ? $k['diagnose'] : null,
            'role_min' => ($k['role_min'] ?? 'user') === 'admin' ? 'admin' : 'user',
            'status' => in_array($k['status'] ?? '', ['draft', 'approved', 'retired'], true) ? $k['status'] : 'approved',
            'source' => mb_substr((string) ($k['source'] ?? 'admin'), 0, 24), 'updated_at' => $now,
        ];
        $db = $this->db();
        if (! empty($k['id'])) {
            $db->table('tbl_bot_knowledge')->where('id', (int) $k['id'])->update($data);

            return (int) $k['id'];
        }
        $data['created_at'] = $now;
        $db->table('tbl_bot_knowledge')->insert($data);

        return (int) $db->insertID();
    }

    /** GET brain/knowledge?q=&status= */
    public function knowledge()
    {
        $q  = trim((string) $this->request->getGet('q'));
        $st = (string) $this->request->getGet('status');
        $b  = $this->db()->table('tbl_bot_knowledge')->orderBy('updated_at', 'DESC')->limit(200);
        if ($q !== '') {
            $b->groupStart()->like('title', $q)->orLike('keywords', $q)->orLike('body', $q)->groupEnd();
        }
        if (in_array($st, ['draft', 'approved', 'retired'], true)) {
            $b->where('status', $st);
        }

        return $this->json(['ok' => true, 'rows' => $b->get()->getResultArray()]);
    }

    /** POST brain/knowledge/save */
    public function knowledgeSave()
    {
        $id = $this->saveKnowledgeRow($this->input() + ['source' => 'admin']);

        return $id ? $this->json(['ok' => true, 'id' => $id]) : $this->json(['ok' => false, 'error' => 'A title and an answer are required.'], 422);
    }

    /** POST brain/knowledge/status {id, status} */
    public function knowledgeStatus()
    {
        $p = $this->input();
        $this->db()->table('tbl_bot_knowledge')->where('id', (int) ($p['id'] ?? 0))->update(['status' => in_array($p['status'] ?? '', ['draft', 'approved', 'retired'], true) ? $p['status'] : 'retired', 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->json(['ok' => true]);
    }

    /**
     * POST brain/import {format: jsonl|csv|text, content}
     * jsonl: one object per line {title, keywords, body, steps[], page_match, role_min}; csv: title,keywords,body;
     * text: blocks separated by a blank line, first line = question, rest = answer.
     */
    public function import()
    {
        $p   = $this->input();
        $fmt = (string) ($p['format'] ?? 'text');
        $raw = (string) ($p['content'] ?? '');
        if ($raw === '' || strlen($raw) > 3000000) {
            return $this->json(['ok' => false, 'error' => 'Paste the content to import (max 3 MB).'], 422);
        }
        $rows = [];
        if ($fmt === 'jsonl') {
            foreach (preg_split('/\r?\n/', $raw) as $line) {
                $j = json_decode(trim($line), true);
                if (is_array($j)) {
                    $rows[] = $j;
                }
            }
        } elseif ($fmt === 'csv') {
            $fh = fopen('php://temp', 'r+');
            fwrite($fh, $raw);
            rewind($fh);
            while (($r = fgetcsv($fh)) !== false) {
                if (count($r) >= 3 && strtolower((string) $r[0]) !== 'title') {
                    $rows[] = ['title' => $r[0], 'keywords' => $r[1], 'body' => $r[2]];
                }
            }
            fclose($fh);
        } else {
            foreach (preg_split('/\r?\n\s*\r?\n/', trim($raw)) as $block) {
                $lines = preg_split('/\r?\n/', trim($block));
                $title = array_shift($lines);
                if ($title && $lines) {
                    $rows[] = ['title' => rtrim($title, '?') . '?', 'keywords' => implode(', ', array_slice(Brain::tokens($title), 0, 10)), 'body' => implode("\n", $lines)];
                }
            }
        }
        $added = 0;
        $db    = $this->db();
        foreach (array_slice($rows, 0, 5000) as $r) {
            if (empty($r['title']) || $db->table('tbl_bot_knowledge')->where('title', mb_substr(trim((string) $r['title']), 0, 200))->countAllResults() > 0) {
                continue;     // no duplicates
            }
            if ($this->saveKnowledgeRow($r + ['source' => 'import'])) {
                $added++;
            }
        }

        return $this->json(['ok' => true, 'added' => $added, 'seen' => count($rows)]);
    }

    // ---------------------------------------------------------------------- unanswered + issues + health

    /** GET brain/unanswered */
    public function unanswered()
    {
        return $this->json(['ok' => true, 'rows' => $this->db()->table('tbl_bot_unanswered')->where('status', 'open')->orderBy('times', 'DESC')->limit(100)->get()->getResultArray()]);
    }

    /** POST brain/unanswered/answer {id, title, keywords, body, steps} or {id, ignore:true} */
    public function unansweredAnswer()
    {
        $p  = $this->input();
        $db = $this->db();
        if (! empty($p['ignore'])) {
            $db->table('tbl_bot_unanswered')->where('id', (int) ($p['id'] ?? 0))->update(['status' => 'ignored']);

            return $this->json(['ok' => true]);
        }
        $kid = $this->saveKnowledgeRow($p + ['source' => 'learned', 'status' => 'approved']);
        if (! $kid) {
            return $this->json(['ok' => false, 'error' => 'A title and an answer are required.'], 422);
        }
        $db->table('tbl_bot_unanswered')->where('id', (int) ($p['id'] ?? 0))->update(['status' => 'answered']);

        return $this->json(['ok' => true, 'id' => $kid]);
    }

    /** GET brain/learn?refresh=1 : the suggestions the Brain built from what users asked, waiting for an administrator */
    public function learn()
    {
        $new = $this->request->getGet('refresh') ? \App\Libraries\BrainLearn::refresh() : null;

        return $this->json(['ok' => true, 'rows' => \App\Libraries\BrainLearn::open(), 'stats' => \App\Libraries\BrainLearn::stats(), 'refreshed' => $new]);
    }

    /** POST brain/learn/decide {id, action: apply|reject, title?, keywords?, body?, steps?} */
    public function learnDecide()
    {
        $p = $this->input();
        $r = \App\Libraries\BrainLearn::decide((int) ($p['id'] ?? 0), ($p['action'] ?? '') === 'reject' ? 'reject' : 'apply', $p, (string) ($this->auth->user()['username'] ?? ''));

        return $this->json($r, $r['ok'] ? 200 : 422);
    }

    /** GET brain/issues */
    public function issues()
    {
        return $this->json(['ok' => true, 'rows' => $this->db()->table('tbl_bot_issues')->where('status', 'open')->orderBy('times', 'DESC')->limit(100)->get()->getResultArray()]);
    }

    /** POST brain/issues/resolve {id, ignore?, learn?:{...}} */
    public function issuesResolve()
    {
        $p  = $this->input();
        $db = $this->db();
        $kid = null;
        if (! empty($p['learn']) && is_array($p['learn'])) {
            $kid = $this->saveKnowledgeRow($p['learn'] + ['source' => 'learned', 'status' => 'approved']);
        }
        $db->table('tbl_bot_issues')->where('id', (int) ($p['id'] ?? 0))->update(['status' => ! empty($p['ignore']) ? 'ignored' : 'resolved', 'knowledge_id' => $kid]);

        return $this->json(['ok' => true, 'knowledge_id' => $kid]);
    }

    /** GET brain/health */
    public function health()
    {
        $db   = $this->db();
        $dirs = Brain::dirStatus();
        $checks = [];
        foreach (['machine_share' => 'Machine files location', 'pdf_engine' => 'PDF engine', 'interlink_session' => 'InterLink session', 'worker' => 'Background worker', 'disk' => 'Disk space (D:)', 'brain' => 'Brain folders'] as $k => $label) {
            $d = Brain::diagnose($k);
            $checks[] = ['key' => $k, 'label' => $label, 'ok' => $d['ok'], 'text' => $d['text']];
        }
        $unflushed = (int) $db->table('tbl_bot_events')->where('flushed', 0)->countAllResults() + (int) $db->table('tbl_bot_chats')->where('flushed', 0)->countAllResults();
        $files = [];
        foreach ($dirs as $d => $st) {
            $files[$d] = $st['ok'] && is_file($d . DIRECTORY_SEPARATOR . 'knowledge.back') ? date('Y-m-d H:i', (int) filemtime($d . DIRECTORY_SEPARATOR . 'knowledge.back')) : null;
        }
        $env = [
            'php' => PHP_VERSION, 'environment' => ENVIRONMENT, 'deflate' => function_exists('apache_get_modules') ? in_array('mod_deflate', apache_get_modules(), true) : null,
            'memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time'),
            'session_handler' => config('Session')->driver ?? '',
        ];

        return $this->json(['ok' => true, 'checks' => $checks, 'dirs' => $dirs, 'unflushed' => $unflushed, 'knowledgeBackModified' => $files, 'env' => $env]);
    }

    /** POST brain/flush : writes the .back files and the Markdown playbooks now */
    public function flushNow()
    {
        $r = Brain::flush();
        $r['markdown'] = Brain::exportMarkdown();

        return $this->json(['ok' => true, 'result' => $r]);
    }

    /** POST brain/restore : rebuilds knowledge from knowledge.back (after a disaster) */
    public function restoreNow()
    {
        return $this->json(['ok' => true, 'restored' => Brain::restore()]);
    }
}
