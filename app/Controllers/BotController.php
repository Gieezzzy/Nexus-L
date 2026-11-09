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















        // Master switch check: if the AI Brain is turned OFF by an admin







        if (\App\Libraries\SystemSettings::get('ai.enabled', '1') === '0') {







            $botName = (string) \App\Libraries\SystemSettings::get('ai.name', 'LEXUS AI');







            $reply = [







                'title' => 'AI Brain Is Currently Turned OFF',







                'text'  => "The {$botName} Brain assistant is currently turned OFF by system administrators for maintenance. Laboratory test forms and navigation are still fully operational.",







                'steps' => [







                    'Admins can re-enable the AI Brain at any time using the header toggle button or Settings.',







                    'For immediate help, contact your lab supervisor or click "Talk to an administrator".',







                ],







                'suggest_admin' => true,







            ];







            $botChatId = $this->say((int) $thread['id'], 'bot', $botName, $reply['text']);







            $reply['chat_id'] = $botChatId;















            return $this->json(['ok' => true, 'thread_id' => (int) $thread['id'], 'user_chat_id' => $userChatId, 'escalated' => false, 'reply' => $reply]);







        }















        // Multi-turn conversational memory: load recent turns across bot and user
        $threadHistory = $this->db()->table('tbl_bot_chats')
            ->select('sender, message, knowledge_id')
            ->where('thread_id', (int) $thread['id'])
            ->where('id <', $userChatId)
            ->orderBy('id', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();
        $threadHistory = array_reverse($threadHistory);

        $lastBotMsg = '';
        $prevUserMsg = '';
        $threadContextStr = '';
        foreach ($threadHistory as $chat) {
            $msg = trim((string) ($chat['message'] ?? ''));
            if ($msg === '') continue;
            if ($chat['sender'] === 'bot') {
                $lastBotMsg = $msg;
            } elseif ($chat['sender'] === 'user') {
                $prevUserMsg = $msg;
            }
            $threadContextStr .= ' ' . $msg;
        }

        $normText = $this->normalizePromptTypos($text);















        $intentReply = \App\Libraries\BotApprovals::listIntent($normText, $uid, $this->db()) ?? \App\Libraries\BotApprovals::emailIntent($normText) ?? $this->aiSkillsIntent($normText) ??
            $this->lexusMultiReportIntent($normText, $uid) ?? $this->artifactIntent($normText, $uid, (int) $thread['id'], (array) $u) ?? $this->vfMethodIntent($normText, $threadContextStr) ?? $this->labRefIntent($normText) ?? $this->approvalReminderIntent($normText, $uid, (array) $u, $lastBotMsg) ?? $this->dbSearchIntent($normText) ?? $this->computeIntent($normText, $prevUserMsg, $lastBotMsg, $threadContextStr);







        if ($intentReply !== null) {







            $reply = $intentReply;







        } else {







            // a short follow-up ("why?", "and then?", "what about X") is about the previous question







            $askText = $text;







            if ($prevUserMsg !== '' && mb_strlen($text) < 40 && str_word_count($text) <= 5 && preg_match('/^(why|why not|how|and|and then|then|what about|what next|more|explain|details?|how come|really|ok and|so)\b/i', trim($text))) {







                $askText = $prevUserMsg . ' ' . $text;







            }







            // TWO BRAINS. The primary brain is the AI service (reranked retrieval, calibrated confidence, fact check). The classic PHP Brain is the







            // secondary: it answers when the primary is down, unsure, or abstains. If both are unsure the user gets help options, never silence.







            $brain  = 'secondary';







            $reply  = null;







            $aiSeen = false;







            if (\App\Libraries\AiBridge::isLive()) {







                $aiResp = \App\Libraries\AiBridge::post('/ask', [







                    'session_id' => \App\Libraries\AiBridge::session($uid),







                    'user_id' => (string) $uid,







                    'message' => $text,







                    'prev_question' => $prevUserMsg,







                    // the last turns of this chat, whichever brain answered them: a follow-up keeps its topic







                    'history' => array_map(static fn ($c) => ['sender' => (string) $c['sender'], 'text' => mb_substr((string) $c['message'], 0, 600), 'kb_id' => $c['knowledge_id'] !== null ? (int) $c['knowledge_id'] : null], $threadHistory),







                    'ui' => ['page' => $page, 'role' => strtolower((string) ($u['role'] ?? 'user')) === 'admin' ? 'admin' : 'user'],







                ], 9000);                                                   // the AI brain answers in 1-5 s (document search and re-reading); waiting less hands a right answer to the fallback







                $ansText = trim((string) ($aiResp['text'] ?? $aiResp['answer'] ?? ''));







                if ($aiResp && $ansText !== '') {







                    $aiSeen  = true;







                    $conf    = isset($aiResp['confidence']) ? (float) $aiResp['confidence'] : 0.0;







                    $route   = (string) ($aiResp['route'] ?? 'agent');







                    // the AI brain's own calibrated threshold decides (it already abstained below it); the Settings value is only the floor when it sent none







                    $trusted = ! in_array($route, ['abstain', 'human'], true) && $conf >= (isset($aiResp['threshold']) ? (float) $aiResp['threshold'] : \App\Libraries\AiBridge::threshold());







                    if ($trusted || ! empty($aiResp['escalate'])) {







                        $related = [];







                        foreach ((is_array($aiResp['related'] ?? null) ? $aiResp['related'] : []) as $rel) {







                            $t = is_array($rel) ? (string) ($rel['title'] ?? $rel['question'] ?? '') : (string) $rel;







                            if ($t !== '') {







                                $relId = is_array($rel) ? (int) ($rel['id'] ?? $rel['kb_id'] ?? 0) : 0;







                                $related[] = ['id' => $relId, 'kb_id' => $relId, 'title' => $t];







                            }







                        }







                        $reply = [







                            'text' => $ansText,







                            'steps' => is_array($aiResp['steps'] ?? null) ? $aiResp['steps'] : [],







                            'actions' => is_array($aiResp['actions'] ?? null) ? $aiResp['actions'] : [],







                            'confidence' => (int) round($conf * 100),







                            'related' => $related,







                            'diagnosis' => null,







                            'knowledge_id' => $aiResp['kb_id'] ?? null,







                            'suggest_admin' => ! empty($aiResp['suggest_admin']) || ! empty($aiResp['escalate']),







                            'sources' => $aiResp['citations'] ?? [],







                            'trace_id' => $aiResp['trace_id'] ?? null,







                        ];







                        $brain = 'primary';







                    }







                }







            }







            if ($reply === null) {







                $reply = $this->compose($askText, ['page' => $page, 'role' => (string) ($u['role'] ?? ''), 'errors' => $this->recentErrors($uid)], $page);







                if (! empty($reply['suggest_admin']) && (int) ($reply['confidence'] ?? 0) < 45 && empty($reply['knowledge_id'])) {







                    $brain = 'none';







                    if ($aiSeen) {                                  // both brains are unsure: the safe holding message, plus the help options







                        $reply['text'] = \App\Libraries\AiBridge::fallbackMessage();







                    }







                }







            }







            $reply['brain'] = $brain;







        }







        if (! isset($reply['brain'])) {







            $reply['brain'] = 'rule';                               // a built-in capability (chart, file, report lookup, calculator)







        }







        $botName = (string) \App\Libraries\SystemSettings::get('ai.name', 'LEXUS AI');







        $cid   = $this->say((int) $thread['id'], 'bot', $botName, $reply['text'], $reply['knowledge_id'], $reply['confidence']);







        $reply['chat_id'] = $cid;







        \App\Libraries\BrainEvents::log('ask', (string) ($reply['brain'] ?? ''), ! empty($reply['knowledge_id']) ? (int) $reply['knowledge_id'] : null, isset($reply['confidence']) ? (int) $reply['confidence'] : null, $text);







        if (\App\Libraries\AiBridge::mode() === 'shadow') {







            // shadow mode: the AI service answers on the side for comparison; the user only ever sees the answer above







            $prev = $db0 = $this->db()->table('tbl_bot_chats')->select('message')->where(['thread_id' => (int) $thread['id'], 'sender' => 'user'])->where('id <', $userChatId)->orderBy('id', 'DESC')->get(1)->getRowArray();







            \App\Libraries\AiBridge::shadowAsk($uid, $text, $page, (string) ($u['role'] ?? ''), $reply['knowledge_id'] ? (int) $reply['knowledge_id'] : null, isset($reply['confidence']) ? (int) $reply['confidence'] : null, (string) ($prev['message'] ?? ''));







        }







        unset($reply['knowledge_id']);















        return $this->json(['ok' => true, 'thread_id' => (int) $thread['id'], 'user_chat_id' => $userChatId, 'escalated' => false, 'reply' => $reply]);







    }















    







    /**







     * VF Test Methods Intent: answers queries about VF test standards, methods, procedures, and parameters.







     * Matches codes like VF-B01, VF-A01, VF-F01, or natural queries like "show tensile test method", "martindale abrasion",







     * "vans testing manual", "illustrated VF-B05", "list of test methods".







     */







    private function vfMethodIntent(string $text, string $threadContext = ''): ?array







    {







        $low = mb_strtolower(trim($text));

        // Early return for troubleshooting & failure remediation queries (route to WF-REMEDIATION)
        if (preg_match('/\b(?:why\s+did\s+.*fail|how\s+(?:do|can)\s+we\s+fix|how\s+to\s+fix|remediat|troubleshoot\s+fail)\b/i', $low)) {
            return null;
        }















        // 1. Check for specific code like VF-B01, VF B01, VF-A04, etc.







        $code = null;

// Check for general test method listing queries
        if (preg_match('/\b(?:list\s+(?:of\s+)?(?:the\s+)?test\s+methods?|what\s+are\s+(?:the\s+)?(?:list\s+of\s+)?(?:vf\s+)?test\s+methods?|what\s+test\s+methods?|all\s+(?:vf\s+)?test\s+methods?|show\s+(?:all\s+)?test\s+methods?|how\s+many\s+test\s+methods?)\b/i', $low)) {
            $code = 'VF-CATALOG';
        }
        






        if (preg_match('/\bVF\s*[-–]?\s*([A-J]\d{1,3}[A-Za-z]?)\b/i', $text, $m)) {
            $code = 'VF-' . strtoupper(preg_replace('/\s+/', '', $m[1]));
        } elseif (preg_match('/\bVF\s*[-–]?\s*(\d{1,4}[A-Za-z]?)\b/i', $text, $m)) {
            $num = (int) $m[1];
            $code = $this->resolveNumericVfCode($num, $low . ' ' . mb_strtolower($threadContext));
        }

        // 2. Check for manual / parameters / guidelines keywords







        if (! $code) {







            if (preg_match('/\b(vans.*(?:manual|testing manual|performance testing)|footwear performance testing manual)\b/i', $text)) {







                $code = 'VF-MANUAL';







            } elseif (preg_match('/\b(universal machine|utm parameters|utm test parameters)\b/i', $text)) {







                $code = 'VF-PARAM-UTM';







            } elseif (preg_match('/\b(testing parameters|test parameters|parameters.*temp)\b/i', $text)) {







                $code = 'VF-PARAM-TEMP';







            } elseif (preg_match('/\b(guidelines for phy|phy prop guidelines|guidelines for physical|guidelines.*testing)\b/i', $text)) {







                $code = 'VF-GUIDELINES';







            }







        }















        // 3. Check for test name keywords if accompanied by "test", "method", "standard", "how to", "vf", "illustrated", "pdf"







        $isTestQuery = (bool) preg_match('/\b(test|method|standard|vf|illustrated|procedure|apparatus|spec|specification|pdf|how to|guide|measure|check)\b/i', $text);







        if (! $code && $isTestQuery) {







            $keywordMap = [







                'hardness' => 'VF-A01',







                'weight' => 'VF-A02',







                'thickness' => 'VF-A03',







                'specific gravity' => 'VF-A04',







                'density' => 'VF-A04',







                'softness' => 'VF-A06',







                'yarn count' => 'VF-A07',







                'non-volatile' => 'VF-A08',







                'solids content' => 'VF-H04',







                'tensile' => 'VF-B01',







                'elongation' => 'VF-B01',







                'bursting' => 'VF-B02',







                'peel and shear' => 'VF-B03',







                'hardware attachment' => 'VF-B04',







                'lace holder' => 'VF-B04',







                'die c' => 'VF-B05',







                'die-c' => 'VF-B05',







                'split tear' => 'VF-B06',







                'trouser tear' => 'VF-B07',







                'stitch tear' => 'VF-B08',







                'baumann tear' => 'VF-B09',







                'compression set' => 'VF-B10',







                'crush strength' => 'VF-B12',







                'james slip' => 'VF-B15',







                'friction coefficient' => 'VF-B25',







                'slip resistance' => 'VF-B15',







                'rebound' => 'VF-B16',







                'resiliency' => 'VF-B16',







                'zipper' => 'VF-B17',







                'knot slip' => 'VF-B23',







                'lace tip' => 'VF-B24',







                'aglet' => 'VF-B24',







                'edgewise crush' => 'VF-B27',







                'toecap' => 'VF-B28',







                'din abrasion' => 'VF-C01',







                'rotary drum abrasion' => 'VF-C01',







                'martindale' => 'VF-C04',







                'taber' => 'VF-C05',







                'lace abrasion' => 'VF-C09',







                'bally flex' => 'VF-D01',







                'ross flex' => 'VF-D02',







                'bata belt' => 'VF-D03',







                'stretch and flex' => 'VF-D06',







                'color fastness to water' => 'VF-E01',







                'water fastness' => 'VF-E01',







                'crocking' => 'VF-E02',







                'rub fastness' => 'VF-E02',







                'uv discoloration' => 'VF-E03',







                'yellowing' => 'VF-E03',







                'perspiration' => 'VF-E16',







                'blooming' => 'VF-E17',







                'water swelling' => 'VF-E18',







                'hydrolysis' => 'VF-E19',







                'jungle test' => 'VF-E19',







                'ph extract' => 'VF-E20',







                'non-marking' => 'VF-E21',







                'vapor permeability' => 'VF-E25',







                'breathability' => 'VF-E25',







                'dip test' => 'VF-E26',







                'spray test' => 'VF-E27',







                'spray rating' => 'VF-E27',







                'glow in the dark' => 'VF-E28',







                'photoluminescence' => 'VF-E28',







                'footwear adhesion' => 'VF-F01',







                'sole adhesion' => 'VF-F01',







                'peel strength' => 'VF-F02',







                'finish adhesion' => 'VF-F03',







                'laminar peel' => 'VF-F04',







                'ink adhesion' => 'VF-F05',







                'cross-hatch' => 'VF-F05',







                'cross hatch' => 'VF-F05',







                'strap pulling' => 'VF-F06',







                'heel attachment' => 'VF-F09',







                'top lift' => 'VF-F10',







                'cardboard adhesion' => 'VF-F11',







                'assembled footwear' => 'VF-G01',







                'whole shoe flex' => 'VF-G02',







                'wash test' => 'VF-G03',







                'whole shoe wash' => 'VF-G03',







                'flex waterproof' => 'VF-G04',







                'china gb' => 'VF-G05',







                'water absorption' => 'VF-G06',







                'static waterproof' => 'VF-G07',







                'hydrostatic' => 'VF-G08',







                'centrifuge' => 'VF-G09',







                'conditioning' => 'VF-H01',







                'leather sampling' => 'VF-H02',







                'midsole sampling' => 'VF-H03',







                'oil content' => 'VF-I01',







                'fiber identification' => 'VF-I02',







                'oil resistant' => 'VF-I05',







                'electric shock' => 'VF-J01',







                'thermal conductivity' => 'VF-J02',







            ];















            foreach ($keywordMap as $kw => $c) {







                if (strpos($low, $kw) !== false) {







                    $code = $c;







                    break;







                }







            }







        }















        // 4. Check for listing / catalog query







        $isCatalogQuery = (bool) preg_match('/\b(?:what\s+(?:are\s+)?(?:the\s+)?(?:list\s+of\s+)?(?:vf\s+)?test\s+methods?|what\s+test\s+methods?|list\s+(?:of\s+)?(?:the\s+)?(?:vf\s+)?test\s+methods?|all\s+(?:vf\s+)?test\s+methods?|vf\s+catalog|vf\s+test\s+methods|vf\s+standards|show\s+(?:all\s+)?(?:vf\s+)?test\s+methods?|how\s+many\s+test\s+methods?)\b/i', $low . ' ' . $text);
        if ($code === 'VF-CATALOG' || $code === 'VF-INDEX') {
            $code = null;
            $isCatalogQuery = true;
        }















        if (! $code && ! $isCatalogQuery) {







            return null;







        }















        $db = $this->db();















        // Handle Catalog Query - Full Structured VF Standards Overview
        if ($isCatalogQuery && ! $code) {
            $indexRow = $db->table('tbl_vf_test_methods')->where('code', 'VF-INDEX')->orLike('file_name', 'List of Test')->get()->getRowArray();
            $manualRow = $db->table('tbl_vf_test_methods')->like('file_name', 'Manual')->get()->getRowArray();

            $replyText = "### \xf0\x9f\x93\x9a Complete VF Test Methods & Laboratory Standards Library\n\n"
                       . "The NEXUS-L Laboratory Information System contains **100 distinct official VF test methods** across **162 standard documents and illustrated pictorial guides** from the **DTP-Lab-101 VF Test Methods Collection (Updated 2025)**:\n\n"
                       . "| Series | Category & Domain | Scope & Key Test Methods |\n"
                       . "| :---: | :--- | :--- |\n"
                       . "| **Series A** | **Physical & Dimensional Properties** | **VF-A01** (Hardness), **VF-A02** (Weight), **VF-A03** (Thickness), **VF-A04** (Specific Gravity / Density) |\n"
                       . "| **Series B** | **Strength & Mechanical Properties** | **VF-B01** (Tensile & Elongation), **VF-B05** (Die C Tear), **VF-B07** (Trouser Tear), **VF-B10** (Compression Set), **VF-B23** (Knot Slip) |\n"
                       . "| **Series C** | **Abrasion Resistance** | **VF-C01** (DIN Rotary Drum Abrasion), **VF-C02** (Akron Abrasion), **VF-C03** (Taber Abrasion), **VF-C04** (Martindale Abrasion) |\n"
                       . "| **Series D** | **Flexing & Fatigue Resistance** | **VF-D01** (Bally Flexing), **VF-D02** (Ross Flexing Cut Growth), **VF-D03** (Demattia Flex), **VF-D04** (Belt Flex) |\n"
                       . "| **Series E** | **Chemical, Aging & Environmental Fastness** | **VF-E02** (Color Fastness to Crocking/Rubbing), **VF-E03** (UV Light Yellowing), **VF-E19** (PU Hydrolysis Jungle Test) |\n"
                       . "| **Series F** | **Adhesion & Bond Strength** | **VF-F01** (Whole Footwear Sole Adhesion), **VF-F02** (Upper Bonding Strength), **VF-F05** (Ink Adhesion on Substrates) |\n"
                       . "| **Series G** | **Assembled Footwear Performance** | **VF-G01** (Whole Shoe Walking Flex Fatigue), **VF-G04** (Dynamic Water Penetration), **VF-G05** (Heel Impact Resistance) |\n"
                       . "| **Series H** | **Sampling & Material Conditioning** | **VF-H01** (Standard Lab Conditioning at 23\xc2\xb12\xc2\xb0C, 50\xc2\xb15% RH for 24h), **VF-H02** (Specimen Sampling & Preparation) |\n"
                       . "| **Series I** | **Chemical Analysis & Identification** | **VF-I01** (FTIR Spectrometry), **VF-I02** (Ash Content Determination), **VF-I04** (Solvent Extractables) |\n"
                       . "| **Series J** | **Electrical & Thermal Safety** | **VF-J01** (Electric Shock Resistance / Dielectric Breakdown), **VF-J02** (Conductive & Antistatic Footwear) |\n"
                       . "| **Master** | **Master Manuals & Equipment Parameters** | **VANS Footwear Performance Testing Manual 2026**, **Universal Testing Machine (UTM) Speed & Jaw Parameters** |\n\n"
                       . "> [!TIP]\n"
                       . "> **Quick Search:** Ask for any specific test method (e.g. `show VF-A01`, `VF-B01 tensile`, `formula for hardness`, `VF-F01 sole adhesion criteria`) to view its official parameters, specimen setup, acceptance criteria, and PDF standards.\n";

            $actions = [
                ['type' => 'open', 'label' => 'Open Official VF Test Method Index PDF', 'url' => site_url($indexRow ? $indexRow['url_path'] : 'uploads/vf_test_methods/0_List of Test Methods.pdf'), 'target' => '_blank'],
                ['type' => 'open', 'label' => 'Open VANS Performance Manual 2026', 'url' => site_url($manualRow ? $manualRow['url_path'] : 'uploads/vf_test_methods/VANS  Footwear Performance Testing Manual 2026.pdf'), 'target' => '_blank'],
                ['type' => 'open', 'label' => 'Open Lab Test Tracker', 'url' => site_url('dtr?tab=tracker')],
                ['type' => 'open', 'label' => 'Create Material Test (DTR)', 'url' => site_url('dtr?tab=material')],
            ];

            return [
                'text'          => $replyText,
                'steps'         => [
                    '100 distinct VF test methods across Series A through J',
                    '162 official standard operating procedures & illustrated guides',
                    'Full VANS Footwear Performance Testing Manual 2026 available',
                ],
                'actions'       => $actions,
                'suggestions'   => [
                    'VF-A01: Hardness Test',
                    'VF-B01: Tensile Strength',
                    'VF-C01: DIN Rotary Abrasion',
                    'VF-D02: Ross Flexing Cut Growth',
                    'VF-F01: Sole Adhesion Criteria',
                    'VF-H01: Material Conditioning',
                ],
            ];
        }















        // Handle Specific Method Query







        $records = $db->table('tbl_vf_test_methods')->where('code', $code)->orderBy('is_illustrated', 'ASC')->get()->getResultArray();







        if (! $records) {







            // Try LIKE







            $records = $db->table('tbl_vf_test_methods')->like('code', $code)->orLike('title', str_replace('VF-', '', $code))->orderBy('is_illustrated', 'ASC')->get()->getResultArray();







        }















        if (! $records) {







            return null;







        }















        $official = null;







        $illustrated = [];







        $files = [];















        foreach ($records as $r) {







            $files[] = [







                'name'  => $r['file_name'],







                'label' => ($r['is_illustrated'] ? '🎨 Illustrated: ' : '📄 Official: ') . $r['title'],







                'url'   => site_url($r['url_path']),







                'size'  => (int) $r['file_size'],







            ];















            if ($r['is_illustrated'] == 0 && $official === null) {







                $official = $r;







            } else {







                $illustrated[] = $r;







            }







        }















        if ($official === null && !empty($illustrated)) {







            $official = $illustrated[0];







        }















        $kbRow = $db->table('tbl_bot_knowledge')->like('title', $code)->get()->getRowArray();







        $titleStr = $official['code'] . ' - ' . $official['title'];







        $catStr = $official['category'];







        $revStr = $official['revision_date'] ? ' (Revision: ' . $official['revision_date'] . ')' : '';















        $textOut = "### 📄 {$titleStr}\n\n"







                 . "**Category:** {$catStr}{$revStr}  \n"







                 . "**Description:**  \n" . $official['description'] . "\n\n";















        if ($kbRow && !empty($kbRow['body'])) {







            // Extract standard operating procedure if present in knowledge







            if (preg_match('/Standard Operating Procedure:\*\*\s*\n*(.*?)(?=\n\nClick|\z)/s', $kbRow['body'], $pm)) {







                $textOut .= "**Procedure & Parameters:**  \n" . trim($pm[1]) . "\n\n";







            }







        }















        $textOut .= "You can view or download the complete official PDF method and any illustrated apparatus guides below:";















        $actions = [];







        if ($official && !empty($official['url_path'])) {







            $actions[] = [







                'type'   => 'open',







                'label'  => 'Open Official PDF (' . $official['code'] . ')',







                'url'    => site_url($official['url_path']),







                'target' => '_blank',







            ];







        }















        foreach ($illustrated as $ill) {







            $actions[] = [







                'type'   => 'open',







                'label'  => 'Illustrated: ' . mb_substr($ill['title'], 0, 32),







                'url'    => site_url($ill['url_path']),







                'target' => '_blank',







            ];







        }















        // Add DTR Test Navigation







        $seriesChar = substr($official['code'], 3, 1);







        if (in_array($seriesChar, ['A', 'B', 'C', 'D', 'E', 'H', 'I'], true)) {







            $actions[] = ['type' => 'open', 'label' => 'Create Material Test in DTR', 'url' => site_url('dtr?tab=material')];







        } elseif (in_array($seriesChar, ['F', 'G'], true)) {







            $actions[] = ['type' => 'open', 'label' => 'Create Shoe Bonding Test in DTR', 'url' => site_url('dtr?tab=bonding')];







        }















        $steps = [







            "Method: {$titleStr}",







            "Category: {$catStr}",







            "Conditioning: Store specimens at 23±2°C and 50±5% RH for at least 24 hours (VF-H01).",







            "Verification: Check calibration of UTM, durometer, or flex apparatus before test run.",







        ];















        return [







            'text'          => $textOut,







            'steps'         => $steps,







            'actions'       => $actions,







            'files'         => $files,







            'title'         => $titleStr,







            'confidence'    => 100,







            'related'       => [







                ['id' => 0, 'title' => "VF Test Methods Series {$seriesChar}"],







                ['id' => 0, 'title' => 'VANS Footwear Performance Testing Manual 2026'],







            ],







            'diagnosis'     => null,







            'knowledge_id'  => $kbRow ? (int) $kbRow['id'] : null,







            'suggest_admin' => false,







        ];







    }















    /**







     * "open / show / preview / print / pdf / check the machine file for DTP20261012FAA8-L": finds the report and returns







     * buttons that open it (nothing is saved or sent by the assistant; the user presses the buttons).







     */







        private function resolveNumericVfCode(int $num, string $context): ?string
    {
        if ($num === 1) {
            if (str_contains($context, 'din') || str_contains($context, 'abrasion')) return 'VF-C01';
            if (str_contains($context, 'flex') || str_contains($context, 'bally')) return 'VF-D01';
            if (str_contains($context, 'adhesion') || str_contains($context, 'bottom')) return 'VF-F01';
            if (str_contains($context, 'water') || str_contains($context, 'fastness')) return 'VF-E01';
            if (str_contains($context, 'tensile')) return 'VF-B01';
            return 'VF-A01';
        }
        if ($num === 2) {
            if (str_contains($context, 'burst')) return 'VF-B02';
            if (str_contains($context, 'flex') || str_contains($context, 'ross')) return 'VF-D02';
            if (str_contains($context, 'crock') || str_contains($context, 'rub') || str_contains($context, 'fastness')) return 'VF-E02';
            if (str_contains($context, 'whole shoe')) return 'VF-G02';
            if (str_contains($context, 'peel')) return 'VF-F02';
            if (str_contains($context, 'weight')) return 'VF-A02';
            return 'VF-E02';
        }
        if ($num === 3) {
            if (str_contains($context, 'uv') || str_contains($context, 'yellow')) return 'VF-E03';
            if (str_contains($context, 'peel') || str_contains($context, 'fastener')) return 'VF-B03';
            if (str_contains($context, 'bata') || str_contains($context, 'belt')) return 'VF-D03';
            if (str_contains($context, 'wash')) return 'VF-G03';
            return 'VF-A03';
        }
        if ($num === 4) {
            if (str_contains($context, 'hardware') || str_contains($context, 'lace')) return 'VF-B04';
            if (str_contains($context, 'heat') || str_contains($context, 'aging')) return 'VF-E04';
            if (str_contains($context, 'martindale')) return 'VF-C04';
            if (str_contains($context, 'waterproof')) return 'VF-G04';
            return 'VF-A04';
        }
        if ($num === 5) {
            if (str_contains($context, 'tear') || str_contains($context, 'die')) return 'VF-B05';
            if (str_contains($context, 'taber')) return 'VF-C05';
            if (str_contains($context, 'ink')) return 'VF-F05';
            return 'VF-B05';
        }
        if ($num === 11 || $num === 1011) {
            if (str_contains($context, 'cardboard')) return 'VF-F11';
            if (str_contains($context, 'maser') || str_contains($context, 'water')) return 'VF-E11';
            return 'VF-E11';
        }
        $formatted = sprintf('%02d', $num);
        $db = $this->db();
        $row = $db->table('tbl_vf_test_methods')->like('code', $formatted)->orderBy('is_illustrated', 'ASC')->get()->getFirstRow('array');
        return $row ? (string) $row['code'] : null;
    }

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







    private function approvalReminderIntent(string $text, int $uid, array $u, string $lastBotMsg = ''): ?array







    {







        $low = mb_strtolower(trim($text));















        // Intent detection regex







        $isReminder = (bool) (
            (preg_match('/\b(re[\s-]?send|send|remind|notify|alert|ping|forward|follow[\s-]?up|chase|nudge|paalala|ipaalala|ulitin)\b/i', $low) &&
             preg_match('/\b(approv|signator|manager|supervisor|verif|sign(ature|[\s-]?off)?\b|qr\b)/i', $low)) ||
            preg_match('/\b(approval\s*reminder|remind\s*approver|re[\s-]?send\s*approval|notify\s*approver)\b/i', $low)
        );
        // "how do I send this report to the manager" asks for the steps; it must never send anything
        if ($isReminder && \App\Libraries\BotApprovals::isHowTo($low)) {
            $isReminder = false;
        }
        if (! $isReminder) {







            return null;







        }















        $db = $this->db();







        if (! $db->tableExists('tbl_dtr_report_approvals') || ! $db->tableExists('tbl_dtr_signatories')) {







            return null;







        }















        // 1. Check if a specific Lab Reference or Application No. was mentioned







        $resolved = \App\Libraries\BotApprovals::resolveTarget($text, $lastBotMsg);
        if ($resolved['ambiguous']) {
            return \App\Libraries\BotApprovals::askWhich($resolved['candidates']);       // "this report", but no single report to point back to
        }
        $targetLab = $resolved['lab'];
        $targetApp = $resolved['app'];















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







                'text'          => "Report {$apv['lab_ref_no']} is pending, but no active signatories are currently registered in Report Designer â†’ Signatories.",







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







        $replyText = "ðŸ”” **Approval Notification Sent to Signatories!**\n\n";







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















    // ------------------------------------------------------------------------------------------------ charts, files and images















    private function artReply(string $text, string $title, array $extra = []): array







    {







        return array_merge(['text' => $text, 'title' => $title, 'steps' => [], 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false], $extra);







    }















    /** The Lab Reference the user means: in the sentence, else the last one in this conversation, else the last one they asked about. */







    private function labRefInContext(string $text, int $threadId, int $uid): ?string







    {







        if (preg_match('/\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b/i', $text, $m)) {







            return strtoupper($m[1]);







        }







        foreach ($this->db()->table('tbl_bot_chats')->select('message')->where(['thread_id' => $threadId, 'sender' => 'user'])->orderBy('id', 'DESC')->limit(12)->get()->getResultArray() as $r) {







            if (preg_match('/\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b/i', (string) $r['message'], $m)) {







                return strtoupper($m[1]);







            }







        }







        $c = cache('botctx_' . $uid);















        return is_array($c) && ! empty($c['labref']) ? (string) $c['labref'] : null;







    }















    private function fileEntry(?array $f, string $label): ?array







    {







        return $f ? ['label' => $label, 'name' => $f['name'], 'url' => $f['url'], 'size' => $f['size']] : null;







    }















    /**







     * Charts ("pie chart of pass and fail by category", "trend of failed bonding reports per month"), pictures of them (the page draws







     * and downloads PNG) and files ("export failed fabric reports this month to Excel", "export this report as PDF").







     * Follow-ups work: "as a pie", "same for leather", "export that to excel".







     */







    

    /**

     * Lexus Multi-Format Report Intent (PowerPoint, Excel, PDF)

     * Handles:

     *   - Specific Lab Reference reports (e.g., DTP20261027FAA7 in PPT, Excel, or PDF)

     *   - Lab Reference anatomy explanation (Facility DTP, Date 20261027, Stage FA = First Article, Line A7)

     *   - Monthly & Span Date summary reports (Pass, Tolerable, Failed breakdown in PPT, Excel, PDF)

     */

        /**
     * Answers queries regarding the core AI Skills and Architecture:
     * 1. Mem0 (The Memory Layer for AI - mem0ai/mem0)
     * 2. LangGraph (Advanced Multi-Turn State Management - langchain-ai/langgraph)
     * 3. LocalAI (Self-Hosted Local LLM API - mudler/LocalAI)
     */
    private function aiSkillsIntent(string $text): ?array
    {
        $low = mb_strtolower(trim($text));

        // 1. Specific skill: Mem0
        if (preg_match('/\b(mem0|mem0ai|memory\s*layer\s*for\s*ai|long\s*term\s*memory\s*for\s*(?:the\s*)?(?:bot|ai))\b/i', $low)) {
            $replyText = "### \xf0\x9f\xa7\xa0 Mem0 \xe2\x80\x94 The Memory Layer for AI\n\n"
                . "**Mem0** is the leading open-source project designed specifically to give AI models long-term memory. It automatically tracks user preferences, extracts facts from conversations over time, and stores them efficiently.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Long-term memory:** Prevents context loss between different chat sessions.\n"
                . "\xe2\x80\xa2 **Cross-session continuity:** Remembers previous instructions, standards, and preferences across days and weeks.\n"
                . "\xe2\x80\xa2 **User profiles & personalization:** Tracks user habits (e.g. preferred report formats, common test methods, language preferences).\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works\n"
                . "1. **Fact Extraction:** Watches the conversation stream and extracts persistent facts (e.g., *\"User frequently tests waffle rubber under VF-A01\"* or *\"User prefers Excel export over PowerPoint\"*).\n"
                . "2. **Local Vector Storage:** Stores structured memories in local Qdrant vector storage (`C:\\NexusAI\\mem0_qdrant`) and SQLite history (`C:\\NexusAI\\mem0_history.db`).\n"
                . "3. **Adaptive Injection:** On subsequent turns, Mem0 automatically queries relevant historical memories and injects them back into the active prompt context.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [mem0ai/mem0](https://github.com/mem0ai/mem0)\n\n"
                . "> [!TIP]\n> Mem0 is integrated directly into the NEXUS-L LangGraph microservice (`ai-service/app/memory.py`).";

            return [
                'text' => $replyText,
                'steps' => [
                    'Watches conversation stream for salient user facts & preferences',
                    'Embeds and stores facts in local Qdrant vector database',
                    'Automatically recalls relevant facts on future query turns',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View Mem0 on GitHub', 'url' => 'https://github.com/mem0ai/mem0', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Open AI Brain Console', 'url' => site_url('brain')],
                ],
                'suggestions' => [
                    'What is LangGraph in our AI?',
                    'Explain Hindsight biomimetic memory',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 2. Specific skill: LangGraph
        if (preg_match('/\b(langgraph|langchain\s*langgraph|cyclical\s*graph|multi\s*turn\s*state\s*management|stateful\s*multi\s*actor)\b/i', $low)) {
            $replyText = "### \xf0\x9f\x94\x84 LangGraph \xe2\x80\x94 Advanced Multi-Turn State Management\n\n"
                . "**LangGraph** (developed by the LangChain team) is the premier library for building stateful, multi-actor applications and cyclical graph workflows with LLMs. Unlike standard linear chains, LangGraph enables cyclical graphs essential for continuous chat states, error self-correction, and tool loops.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Continuous Chat State:** Maintains rich conversation memory across complex multi-turn dialogs.\n"
                . "\xe2\x80\xa2 **Conversational Routing:** Intelligently routes queries between test method retrieval, report generation, math computations, and approval workflows.\n"
                . "\xe2\x80\xa2 **Self-Correction & Tool Looping:** Automatically evaluates output validity; if an error or low confidence is detected, it loops back through correction nodes.\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works\n"
                . "LangGraph acts as the assistant's central **brain architecture**:\n"
                . "1. **State Definition:** An `AgentState` object holds message history, extracted numbers, active lab references, and confidence scores.\n"
                . "2. **Node Execution:** Input passes through specialized nodes: `memory_node` (Mem0 recall) \xe2\x86\x92 `nlu_node` (intent classification) \xe2\x86\x92 `retrieve_node` (vector & keyword search) \xe2\x86\x92 `eval_node` (accuracy validation).\n"
                . "3. **Cyclical Feedback:** If the evaluator flags an anomaly, the graph branches back to refine the response before delivering the answer to the user.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [langchain-ai/langgraph](https://github.com/langchain-ai/langgraph)\n\n"
                . "> [!TIP]\n> The NEXUS-L microservice executes a custom StateGraph with live Phoenix OpenTelemetry tracing on port `6006`.";

            return [
                'text' => $replyText,
                'steps' => [
                    'StateGraph manages the state machine across all dialog turns',
                    'Executes memory recall, semantic retrieval, and reranking nodes',
                    'Loops back for automated self-correction upon confidence thresholds',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View LangGraph on GitHub', 'url' => 'https://github.com/langchain-ai/langgraph', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Inspect Traces in Phoenix (Port 6006)', 'url' => 'http://127.0.0.1:6006', 'target' => '_blank'],
                ],
                'suggestions' => [
                    'What is Mem0 in our AI?',
                    'Explain Cognee knowledge graph',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 3. Specific skill: LocalAI
        if (preg_match('/\b(localai|mudler\s*localai|self\s*hosted\s*(?:local\s*)?llm|offline\s*(?:local\s*)?(?:llm|model|api))\b/i', $low)) {
            $replyText = "### \xf0\x9f\x92\xbb LocalAI \xe2\x80\x94 Self-Hosted Local LLM REST API\n\n"
                . "**LocalAI** is the leading free, open-source drop-in replacement REST API compatible with OpenAI specifications for local and offline CPU/GPU inference. It enables running open-source models completely on-premises without cloud dependencies or external data leaks.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Offline & On-Premises Privacy:** Operates 100% locally on your server hardware\xe2\x80\x94no laboratory data ever leaves the internal network.\n"
                . "\xe2\x80\xa2 **Zero API Fees:** Eliminates recurring cloud API billing and quota limits.\n"
                . "\xe2\x80\xa2 **Standard API Compatibility:** Drop-in compatibility with OpenAI REST format (`/v1/chat/completions`, `/v1/embeddings`), enabling standard client libraries to interact with local models.\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works\n"
                . "1. **Containerized Model Serving:** Wraps quantized open-weight models (Llama 3, Mistral, Phi-3, Qwen) inside a lightweight local runtime (using `llama.cpp` or ONNX).\n"
                . "2. **Standard REST Endpoints:** Exposes fast endpoints on `127.0.0.1` for text generation, embeddings, and tool/function calling.\n"
                . "3. **Zero Configuration Switching:** Applications call standard REST endpoints just like cloud APIs, while all computations execute on local CPU/GPU cores.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [mudler/LocalAI](https://github.com/mudler/LocalAI)\n\n"
                . "> [!NOTE]\n> NEXUS-L supports local CPU/GPU inference via LocalAI and Ollama without any cloud API keys.";

            return [
                'text' => $replyText,
                'steps' => [
                    'Runs quantized GGUF/ONNX models directly on local hardware',
                    'Serves an OpenAI-compatible REST API on localhost',
                    'Provides 100% data confidentiality for QC test methods and reports',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View LocalAI on GitHub', 'url' => 'https://github.com/mudler/LocalAI', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Open AI Assistant Settings', 'url' => site_url('settings')],
                ],
                'suggestions' => [
                    'What is SimpleMem compression?',
                    'What is OpenViking context VFS?',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 4. Specific skill: Hindsight
        if (preg_match('/\b(hindsight|vectorize[- ]?io\s*hindsight|biomimetic\s*memory|retain\s*recall\s*reflect|ai\s*amnesia|memory\s*tiers)\b/i', $low)) {
            $replyText = "### \xf0\x9f\xa7\xa0 Hindsight \xe2\x80\x94 Biomimetic Agent Memory\n\n"
                . "**Hindsight** is a dedicated agent memory architecture designed to run entirely locally using your own data infrastructure. It bypasses typical fragile RAG pipelines by creating biological-style multi-tiered memory models.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Continuous Memory Consolidation:** Prevents \"AI amnesia\" by constantly digesting past conversations into long-term mental models.\n"
                . "\xe2\x80\xa2 **Cross-Session Reasoning:** Synthesizes conclusions across multiple testing shifts and dates instead of treating each session as an isolated conversation.\n"
                . "\xe2\x80\xa2 **100% Local Execution:** Runs completely on-premises using your local data infrastructure without external cloud API dependencies.\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works (The 3-Part Lifecycle)\n"
                . "1. **Retain (Observation Extraction):** Watches user chats and test runs, extracting discrete verified facts and observations.\n"
                . "2. **Recall (Hybrid Retrieval):** Merges dense semantic embeddings with sparse keyword matching to retrieve relevant context accurately.\n"
                . "3. **Reflect (Background Consolidation):** Background worker tasks cross-reference statements across test records, consolidating findings and pruning redundant noise.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [vectorize-io/hindsight](https://github.com/vectorize-io/hindsight)";

            return [
                'text' => $replyText,
                'steps' => [
                    'Retain: Extracts discrete observations from conversation turns',
                    'Recall: Merges dense semantic embeddings with sparse keyword matching',
                    'Reflect: Consolidates observations into long-term mental models in the background',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View Hindsight on GitHub', 'url' => 'https://github.com/vectorize-io/hindsight', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Open AI Brain Console', 'url' => site_url('brain')],
                ],
                'suggestions' => [
                    'What is Cognee knowledge graph?',
                    'What is OpenViking context VFS?',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 5. Specific skill: Cognee
        if (preg_match('/\b(cognee|topoteretes\s*cognee|graph[- ]?based\s*(?:cognitive\s*)?memory|local\s*knowledge\s*graph|entity\s*relationship\s*tracking)\b/i', $low)) {
            $replyText = "### \xf0\x9f\x95\xb8\xef\xb8\x8f Cognee \xe2\x80\x94 Graph-Based Cognitive Memory Platform\n\n"
                . "**Cognee** is an open-source cognitive platform designed to turn raw conversations, test results, and laboratory documents into an interconnected **local knowledge graph**.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Relationship & Connection Tracking:** Understands how entities connect (e.g. *Supplier X* \xe2\x86\x92 supplies *Rubber Compound Y* \xe2\x86\x92 tested under *VF-A01* \xe2\x86\x92 historical failure in *Week 42*).\n"
                . "\xe2\x80\xa2 **High Semantic Accuracy:** Eliminates vector hallucinations by hopping along verified graph edges rather than relying purely on text similarity.\n"
                . "\xe2\x80\xa2 **Zero Cloud Costs:** Operates completely on your local machine using small, local CPU-optimized models.\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works\n"
                . "1. **Entity & Relation Extraction:** Extracts key entities (test methods, suppliers, chemical compounds, technicians) and their relationships from chat logs and test sheets.\n"
                . "2. **Graph Construction:** Builds local graph nodes and edges stored in local graph/relational databases.\n"
                . "3. **Multi-Hop Traversal:** Instead of simple string searches, queries traverse connected graph nodes to discover non-obvious correlations and failure root causes.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [topoteretes/cognee](https://github.com/topoteretes/cognee)";

            return [
                'text' => $replyText,
                'steps' => [
                    'Ingests unstructured laboratory chats, spreadsheets, and test sheets',
                    'Extracts entities and relationships using local CPU-optimized models',
                    'Builds a traversable knowledge graph for multi-hop relational queries',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View Cognee on GitHub', 'url' => 'https://github.com/topoteretes/cognee', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Open AI Brain Universe', 'url' => site_url('brain#universe')],
                ],
                'suggestions' => [
                    'What is Hindsight biomimetic memory?',
                    'What is OpenViking context VFS?',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 6. Specific skill: OpenViking
        if (preg_match('/\b(openviking|volcengine\s*openviking|transparent\s*context|context\s*virtual\s*filesystem|vfs\s*memory|virtual\s*filesystem\s*for\s*ai)\b/i', $low)) {
            $replyText = "### \xf0\x9f\x97\x82\xef\xb8\x8f OpenViking \xe2\x80\x94 Transparent Context Virtual Filesystem\n\n"
                . "**OpenViking** (developed by Volcengine) is an open-source context database that maps an agent's memory directly into a **transparent virtual filesystem (VFS)** layer.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Eliminates the \"Black Box\":** Overcomes the opacity of traditional vector stores where administrators cannot inspect or edit what the bot has stored.\n"
                . "\xe2\x80\xa2 **Direct Auditability & Correction:** Quality managers can directly inspect, modify, or delete erroneous memories with standard filesystem operations.\n"
                . "\xe2\x80\xa2 **Granular Access Control:** File-permission style access control across departments (e.g., technicians vs lab supervisors).\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works\n"
                . "1. **Filesystem Mapping:** User preferences, test histories, and machine logs are organized into directories and files (e.g., `/users/staff_01/preferences.json`, `/labrefs/DTP2026/readings.txt`).\n"
                . "2. **CLI Inspection:** Administrators can use terminal-style operations (`ls`, `tree`, `read`, `write`, `grep`) to audit memory contents.\n"
                . "3. **Precise Overrides:** Correcting an error is as simple as updating a text file inside the virtual filesystem directory.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [volcengine/OpenViking](https://github.com/volcengine/OpenViking)";

            return [
                'text' => $replyText,
                'steps' => [
                    'Maps agent memory hierarchy into structured virtual folders and files',
                    'Enables standard terminal operations (ls, tree, grep) to inspect memory state',
                    'Allows administrators to directly edit, sanitize, and audit stored knowledge',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View OpenViking on GitHub', 'url' => 'https://github.com/volcengine/OpenViking', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Open Knowledge Management', 'url' => site_url('brain#knowledge')],
                ],
                'suggestions' => [
                    'What is SimpleMem compression?',
                    'What is Cognee knowledge graph?',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 7. Specific skill: SimpleMem
        if (preg_match('/\b(simplemem|aiming[- ]?lab\s*simplemem|lossless\s*token[- ]?saving|context\s*(?:window\s*)?compression|token[- ]?saving\s*compression)\b/i', $low)) {
            $replyText = "### \xe2\x9a\xa1 SimpleMem \xe2\x80\x94 Lossless Token-Saving Context Compression\n\n"
                . "**SimpleMem** (developed by Aiming Lab) is a high-efficiency context compression framework designed to drastically reduce token consumption in multi-turn local conversations.\n\n"
                . "#### \xf0\x9f\x8e\xaf What it Solves\n"
                . "\xe2\x80\xa2 **Local Inference Latency:** Prevents CPU/GPU slowdowns caused by feeding hundreds of conversational turns into local models.\n"
                . "\xe2\x80\xa2 **Context Window Overflow:** Extends the effective conversation length up to 10\xc3\x97 without exceeding the model's context limits.\n"
                . "\xe2\x80\xa2 **Hardware Efficiency:** Enables local execution on constrained server hardware with minimal RAM/VRAM footprint.\n\n"
                . "#### \xe2\x9a\x99\xef\xb8\x8f How it Works\n"
                . "1. **Semantic Distillation:** Analyzes multi-turn dialogue history and strips conversational filler, pleasantries, and duplicated exchanges.\n"
                . "2. **Timestamped Semantic Blocks:** Condenses key questions, numerical values, and decisions into compact, timestamped structured facts.\n"
                . "3. **Micro-Payload Injection:** Passes only the compressed distillation to the LLM prompt, keeping prompt token payloads exceptionally small while preserving full conversational fidelity.\n\n"
                . "\xe2\x80\xa2 **GitHub Repository:** [aiming-lab/SimpleMem](https://github.com/aiming-lab/SimpleMem)";

            return [
                'text' => $replyText,
                'steps' => [
                    'Monitors multi-turn conversation length and token budget',
                    'Strips conversational fluff and compresses turns into timestamped blocks',
                    'Maintains 10x longer continuous dialogue with near-zero latency penalty',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View SimpleMem on GitHub', 'url' => 'https://github.com/aiming-lab/SimpleMem', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'Open AI Assistant Settings', 'url' => site_url('settings')],
                ],
                'suggestions' => [
                    'What is Hindsight biomimetic memory?',
                    'What is Mem0 in our AI?',
                    'Overview of all AI skills',
                ],
            ];
        }

        // 8. Combined skills overview
        if (preg_match('/\b(?:skills?\s*(?:of|in|for)\s*(?:our|the)?\s*ai|ai\s*skills?|core\s*ai\s*skills?|ai\s*brain\s*(?:skills?|stack|architecture)|what\s*are\s*the\s*ai\s*skills)\b/i', $low)) {
            $replyText = "### \xf0\x9f\x9b\xa1\xef\xb8\x8f Core Skills & Architecture of our AI (LEXUS AI Brain)\n\n"
                . "The LEXUS AI Brain in NEXUS-L is powered by seven specialized open-source technologies for continuous memory, state orchestration, knowledge graphs, and local offline inference:\n\n"
                . "| Skill / Technology | Architecture Role | What It Solves | GitHub Repository |\n"
                . "| :--- | :--- | :--- | :--- |\n"
                . "| **1. Mem0** | **The Memory Layer for AI** | Long-term memory, cross-session continuity, and user preference tracking across chat sessions | [mem0ai/mem0](https://github.com/mem0ai/mem0) |\n"
                . "| **2. LangGraph** | **Multi-Turn State Management** | Cyclical graph workflows, conversation routing, self-correction, and tool looping | [langchain-ai/langgraph](https://github.com/langchain-ai/langgraph) |\n"
                . "| **3. LocalAI** | **Self-Hosted Local LLM API** | Running open-source models (Llama 3, Mistral, Phi-3) 100% offline on your own machine without cloud API fees | [mudler/LocalAI](https://github.com/mudler/LocalAI) |\n"
                . "| **4. Hindsight** | **Biomimetic Agent Memory** | Continuous memory consolidation (Retain-Recall-Reflect) to prevent AI amnesia and build mental models | [vectorize-io/hindsight](https://github.com/vectorize-io/hindsight) |\n"
                . "| **5. Cognee** | **Knowledge Graph Platform** | Extracts entities & relationships into a local graph for multi-hop semantic accuracy without cloud DBs | [topoteretes/cognee](https://github.com/topoteretes/cognee) |\n"
                . "| **6. OpenViking** | **Context Virtual Filesystem** | Transparent VFS memory layer allowing direct inspection & correction via terminal commands (ls, grep) | [volcengine/OpenViking](https://github.com/volcengine/OpenViking) |\n"
                . "| **7. SimpleMem** | **Lossless Token Compression** | Compresses multi-turn logs into compact timestamped semantic blocks, saving tokens & speeding up inference | [aiming-lab/SimpleMem](https://github.com/aiming-lab/SimpleMem) |\n\n"
                . "> [!IMPORTANT]\n"
                . "> **Privacy & Isolation:** All components run strictly on the local laboratory host (`127.0.0.1`). No proprietary test data, test results, or laboratory reports ever leave the internal network.";

            return [
                'text' => $replyText,
                'steps' => [
                    '1. Mem0 & Hindsight: Long-term biomimetic memory and continuous consolidation',
                    '2. LangGraph: Cyclical dialog state orchestration and self-correction',
                    '3. Cognee & OpenViking: Knowledge graph relationships and transparent virtual filesystem auditing',
                    '4. SimpleMem & LocalAI: Lossless token compression and on-premises offline model inference',
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View Mem0', 'url' => 'https://github.com/mem0ai/mem0', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View LangGraph', 'url' => 'https://github.com/langchain-ai/langgraph', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View LocalAI', 'url' => 'https://github.com/mudler/LocalAI', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View Hindsight', 'url' => 'https://github.com/vectorize-io/hindsight', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View Cognee', 'url' => 'https://github.com/topoteretes/cognee', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View OpenViking', 'url' => 'https://github.com/volcengine/OpenViking', 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View SimpleMem', 'url' => 'https://github.com/aiming-lab/SimpleMem', 'target' => '_blank'],
                ],
                'suggestions' => [
                    'Tell me about Hindsight biomimetic memory',
                    'How does Cognee build knowledge graphs?',
                    'Explain OpenViking virtual filesystem',
                    'How does SimpleMem compress tokens?',
                ],
            ];
        }

        return null;
    }

    private function lexusMultiReportIntent(string $text, int $uid): ?array

    {

        $low = mb_strtolower(trim($text));



        // 1. Check for Lab Reference pattern

        $hasLabRef = preg_match('/\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b/i', $text, $m);

        $labRef = $hasLabRef ? strtoupper($m[1]) : null;



        // Check format requested

        $wantPpt = (bool) preg_match('/\b(powerpoint|power\s*point|ppt|pptx|presentation|slides?|deck)\b/i', $low);

        $wantExcel = (bool) preg_match('/\b(excel|xlsx|xls|spreadsheet|workbook)\b/i', $low);

        $wantPdf = (bool) preg_match('/\b(pdf|document|sheet)\b/i', $low);

        $wantReport = (bool) preg_match('/\b(report|generate|export|create|download|make|build|summary)\b/i', $low);

        $wantExplain = (bool) preg_match('/\b(explain|mean|meaning|breakdown|anatomy|what is|what does)\b/i', $low);



        // Case A: User asks to explain or breakdown a Lab Reference

        if ($labRef && ($wantExplain || preg_match('/\b(what does .* mean|break down|parts|details)\b/i', $low))) {

            $parsed = \App\Libraries\LabReferenceParser::parse($labRef);

            $msg = "### Lab Reference Anatomy Breakdown: `{$labRef}`



"

                 . "In NEXUS-L, this standardized laboratory reference is structured as follows:



"

                 . "• **Facility (`{$parsed['facility_code']}`):** {$parsed['facility_name']}

"

                 . "• **Test Date (`{$parsed['date_raw']}`):** {$parsed['date_formatted']}

"

                 . "• **Production Stage (`{$parsed['stage_code']}`):** **{$parsed['stage_name']}** (Initial production qualification batch)

"

                 . "• **Production Line (`{$parsed['line_code']}`):** **{$parsed['line_name']}** (Assembly Conveyor Line {$parsed['line_code']})

"

                 . "• **Foot Specimen:** {$parsed['foot_side']}



"

                 . "Would you like me to generate an official report for this reference?";



            return [

                'title'      => "Lab Reference Analysis · {$labRef}",

                'text'       => $msg,

                'steps'      => [

                    "Stage: {$parsed['stage_name']} ({$parsed['stage_code']})",

                    "Assembly Line: {$parsed['line_name']} ({$parsed['line_code']})",

                    "Test Date: {$parsed['date_formatted']}",

                ],

                'actions'    => [

                    ['type' => 'open', 'label' => 'Open in Lab Tracker', 'url' => site_url('dtr?open=' . rawurlencode($labRef))],

                    ['type' => 'open', 'label' => 'Generate PDF Report', 'url' => site_url('dtr?open=' . rawurlencode($labRef) . '&preview=1')],

                ],

                'confidence' => 100,

                'related'    => [

                    ['title' => "Generate PowerPoint report for {$labRef}"],

                    ['title' => "Export {$labRef} to Excel"],

                    ['title' => "Export {$labRef} to PDF"],

                ],

                'diagnosis'  => null,

                'knowledge_id' => null,

                'suggest_admin' => false,

            ];

        }



        // Case B: User asks for single Lab Reference report in PPT, Excel, or PDF

        if ($labRef && ($wantReport || $wantPpt || $wantExcel || $wantPdf)) {

            $format = $wantPpt ? 'pptx' : ($wantExcel ? 'xlsx' : 'pdf');

            $rep = \App\Libraries\LexusReportEngine::generateSingleReport($labRef, $format, $uid);

            if ($rep) {

                $meta = $rep['meta'];

                $textReply = "### Laboratory Process Report Generated: `{$labRef}`



"

                           . "Here is the complete process report for **{$labRef}** ({$rep['style']}):



"

                           . "• **Facility:** {$meta['facility_name']} (`{$meta['facility_code']}`)

"

                           . "• **Test Date:** {$meta['date_formatted']}

"

                           . "• **Production Stage:** **{$meta['stage_name']}** (`{$meta['stage_code']}`)

"

                           . "• **Assembly Line:** **{$meta['line_name']}** (`{$meta['line_code']}`)

"

                           . "• **Overall Result:** **{$rep['result']}**



"

                           . "Your **{$rep['file_label']}** has been compiled and is ready for download:";



                $actions = [

                    ['type' => 'open', 'label' => "Download {$rep['file_label']}", 'url' => $rep['url']],

                    ['type' => 'open', 'label' => 'View in Lab Tracker', 'url' => site_url('dtr?open=' . rawurlencode($labRef))],

                ];



                return [

                    'title'      => "Process Report · {$labRef}",

                    'text'       => $textReply,

                    'steps'      => [

                        "Click 'Download {$rep['file_label']}' to review findings.",

                        "Inspect technical readings (Toe, Ball, Waist, Heel peel strength in kgf/cm).",

                        "Verify sign-off certificate and QR token.",

                    ],

                    'actions'    => $actions,

                    'files'      => [

                        ['label' => $rep['file_label'], 'name' => $rep['filename'], 'url' => $rep['url'], 'size' => $rep['size']],

                    ],

                    'confidence' => 100,

                    'related'    => [

                        ['title' => "Export {$labRef} to PowerPoint (PPT)"],

                        ['title' => "Export {$labRef} to Excel (XLSX)"],

                        ['title' => "Export {$labRef} to PDF"],

                    ],

                    'diagnosis'  => null,

                    'knowledge_id' => null,

                    'suggest_admin' => false,

                ];

            }

        }



        // Case C: Date Range / Monthly Report in PPT, Excel, or PDF

        if (($wantReport || $wantPpt || $wantExcel || $wantPdf) && (preg_match('/\b(month|monthly|october|november|december|january|span|range|between|from\s+\d|pass.*fail|tolerable)\b/i', $low))) {

            $format = $wantPpt ? 'pptx' : ($wantExcel ? 'xlsx' : 'pdf');

            $filters = \App\Libraries\BotArtifacts::filters($text);

            $rep = \App\Libraries\LexusReportEngine::generatePeriodReport($filters, $format, $uid);

            if ($rep) {

                $textReply = "### Quality Audit & Performance Report Generated



"

                           . "Here is the consolidated laboratory audit report for **{$rep['period']}**:



"

                           . "• **Total Tests Monitored:** {$rep['total']}

"

                           . "• **Total Passed:** **{$rep['pass_count']}** ({$rep['pass_rate']}%)

"

                           . "• **Tolerable Quality Exceptions:** **{$rep['tol_count']}**

"

                           . "• **Total Failed:** **{$rep['fail_count']}**

"

                           . "• **Line & Stage Breakdown:** Includes First Article (`FA`), Pre-Production (`PP`), and Production (`PR`) lots across Assembly Lines.



"

                           . "Your **{$rep['file_label']}** is ready for download:";



                $actions = [

                    ['type' => 'open', 'label' => "Download {$rep['file_label']}", 'url' => $rep['url']],

                    ['type' => 'open', 'label' => 'View Unified Test Summary', 'url' => site_url('test-summary')],

                ];



                return [

                    'title'      => "Quality Audit Report · {$rep['period']}",

                    'text'       => $textReply,

                    'steps'      => [

                        "Click 'Download {$rep['file_label']}' to review presentation slides / spreadsheet.",

                        "Inspect pass rate trends and defect Pareto across testing categories.",

                        "Track First Article (FA) approval rates before mass production rollout.",

                    ],

                    'actions'    => $actions,

                    'files'      => [

                        ['label' => $rep['file_label'], 'name' => $rep['filename'], 'url' => $rep['url'], 'size' => $rep['size']],

                    ],

                    'confidence' => 100,

                    'related'    => [

                        ['title' => "Monthly PowerPoint presentation for this month"],

                        ['title' => "Export monthly summary to Excel"],

                        ['title' => "Export monthly audit to PDF"],

                    ],

                    'diagnosis'  => null,

                    'knowledge_id' => null,

                    'suggest_admin' => false,

                ];

            }

        }



        return null;

    }



private function artifactIntent(string $text, int $uid, int $threadId, array $u): ?array







    {







        try {







            $low = mb_strtolower(trim($text));







            $ctx = cache('botctx_' . $uid);







            $ctx = is_array($ctx) ? $ctx : [];







            $db  = $this->db();







            if (! $db->tableExists('tbl_dtr_unified_labrefs')) {







                return null;







            }







            $chart  = \App\Libraries\BotArtifacts::chartRequest($text);







            $export = \App\Libraries\BotArtifacts::exportRequest($text);















            // follow-ups on the last chart







            if (! $chart && ! empty($ctx['chart']) && $low !== '') {







                $newF = \App\Libraries\BotArtifacts::filters($text);







                $kind = preg_match('/\b(pie|donut|doughnut)\b/', $low) ? 'pie' : (preg_match('/\b(line|trend)\b/', $low) ? 'line' : (preg_match('/\b(bar|column)\b/', $low) ? 'bar' : null));







                $changes = $kind !== null || $newF['slug'] !== null || $newF['result'] !== null || $newF['from'] !== null;







                if (! $export && $changes && ! preg_match('/\bby\s+[a-z]+/', $low) && mb_strlen($low) < 60 && preg_match('/^(and|now|also|same|what about|how about|show|make|change|as|in|for|only|just|but|instead|try|use|turn)\b/', $low)) {







                    $req = $ctx['chart']['req'];







                    $f   = $ctx['chart']['f'];







                    $req['kind'] = $kind ?? $req['kind'];







                    foreach (['slug', 'result'] as $k) {







                        if ($newF[$k] !== null) {







                            $f[$k] = $newF[$k];







                        }







                    }







                    if ($newF['from'] !== null) {







                        $f['from'] = $newF['from'];







                        $f['to'] = $newF['to'];







                        $f['period'] = $newF['period'];







                    }







                    $req['title'] = ($f['result'] ? $f['result'] . ' reports' : 'Reports') . ' by ' . ['category' => 'category', 'result' => 'result', 'month' => 'month', 'supplier' => 'supplier', 'model' => 'model / style', 'material' => 'material type', 'technician' => 'technician', 'status' => 'status'][$req['dim']]







                        . ($f['slug'] ? ' - ' . \App\Libraries\BotArtifacts::CATEGORIES[$f['slug']] : '') . ($f['period'] !== 'all time' ? ' (' . $f['period'] . ')' : '');















                    return $this->chartReply($req, $f, $uid);







                }







            }















            if ($chart) {







                return $this->chartReply($chart, \App\Libraries\BotArtifacts::filters($text), $uid);







            }















            // an export of the chart that is on screen ("export that to excel", "download the data")







            if (! empty($ctx['chart']) && $export === null && preg_match('/\b(export|download|save|get)\b.*\b(that|it|the chart|the graph|the data|chart data|this (chart|graph|data|result|one))\b/', $low)) {







                $export = ['format' => preg_match('/\bcsv\b/', $low) ? 'csv' : (preg_match('/\bpdf\b/', $low) ? 'pdf' : 'xlsx'), 'subject' => 'chart', 'labref' => null];







            } elseif ($export !== null && ! empty($ctx['chart']) && $export['labref'] === null && preg_match('/\b(that|it|the chart|the graph|chart data|this (chart|graph|data|result|one))\b/', $low) && ! preg_match('/\b(report)\b/', $low)) {







                $export['subject'] = 'chart';







            }







            if ($export === null) {







                return null;







            }















            return $this->exportReply($export, $text, $uid, $threadId, $ctx);







        } catch (\Throwable $e) {







            log_message('error', 'artifactIntent: ' . $e->getMessage());















            return $this->artReply('I could not make that just now. ' . (ENVIRONMENT === 'production' ? '' : $e->getMessage()), 'Could not create it', ['suggest_admin' => true]);







        }







    }















    private function chartReply(array $req, array $f, int $uid): array







    {







        $spec = \App\Libraries\BotArtifacts::chartSpec($req, $f);







        if ($spec['total'] === 0 || ! $spec['labels']) {







            return $this->artReply('There are no reports for that selection' . ($f['period'] !== 'all time' ? ' (' . $f['period'] . ')' : '') . ', so there is nothing to draw. Try another period or a different category.', 'No data for the chart', [







                'related' => [['title' => 'Chart of pass and fail by category'], ['title' => 'Trend of reports per month']]]);







        }







        cache()->save('botctx_' . $uid, array_merge((array) cache('botctx_' . $uid), ['chart' => ['req' => $req, 'f' => $f, 'spec' => $spec]]), 1800);















        $lines = [];







        foreach ($spec['series'] as $s) {







            $lines[] = $s['name'] . ': ' . number_format(array_sum($s['data']));







        }







        $top = '';







        if ($spec['dimension'] !== 'month' && count($spec['series']) === 1) {







            $top = ' Highest: ' . $spec['labels'][0] . ' (' . number_format($spec['series'][0]['data'][0]) . ').';







        }







        $head = array_merge(['Label'], array_map(static fn ($s) => $s['name'], $spec['series']));







        $rows = [];







        foreach ($spec['labels'] as $i => $label) {







            $rows[] = array_merge([$label], array_map(static fn ($s) => (string) $s['data'][$i], $spec['series']));







        }







        $files = [];







        $x = \App\Libraries\BotArtifacts::xlsx($head, $rows, 'Chart data');







        if ($x) {







            $files[] = $this->fileEntry(\App\Libraries\BotArtifacts::register($x, 'chart-data-' . date('Ymd-His') . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $uid), 'Data (Excel)');







        }















        return $this->artReply(







            $spec['title'] . ': ' . number_format($spec['total']) . ' report(s) from the live QC data. ' . implode(' | ', $lines) . '.' . $top . ' Use the buttons under the chart to save it as an image or PDF. You can say "as a pie", "same for leather" or "this month" to change it.',







            'Chart', ['chart' => $spec, 'files' => array_values(array_filter($files)), 'related' => [['title' => 'Export that to Excel'], ['title' => 'Show it as a pie chart']]]







        );







    }















    private function exportReply(array $e, string $text, int $uid, int $threadId, array $ctx): array







    {







        $fmt = $e['format'];







        $mime = ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv'];







        $stamp = date('Ymd-His');















        if ($e['subject'] === 'chart' && ! empty($ctx['chart']['spec'])) {







            $spec = $ctx['chart']['spec'];







            $head = array_merge(['Label'], array_map(static fn ($s) => $s['name'], $spec['series']));







            $rows = [];







            foreach ($spec['labels'] as $i => $label) {







                $rows[] = array_merge([$label], array_map(static fn ($s) => (string) $s['data'][$i], $spec['series']));







            }







            $p = $fmt === 'csv' ? \App\Libraries\BotArtifacts::csv($head, $rows) : \App\Libraries\BotArtifacts::xlsx($head, $rows, 'Chart data');







            $f = $p ? \App\Libraries\BotArtifacts::register($p, 'chart-data-' . $stamp . '.' . ($fmt === 'csv' ? 'csv' : 'xlsx'), $mime[$fmt === 'csv' ? 'csv' : 'xlsx'], $uid) : null;







            if (! $f) {







                return $this->artReply('I could not create the file just now (too many files in the last hour, or the server cannot write it). Try again in a few minutes.', 'Export', ['suggest_admin' => true]);







            }















            return $this->artReply('Here is the data of the chart "' . $spec['title'] . '" as ' . ($fmt === 'csv' ? 'a CSV' : 'an Excel') . ' file. The link works for one hour.', 'Download ready', ['files' => [$this->fileEntry($f, 'Download ' . $f['name'])]]);







        }















        if ($e['subject'] === 'one') {







            $ref = $e['labref'] ?: $this->labRefInContext($text, $threadId, $uid);







            if (! $ref) {







                return $this->artReply('Which report should I export? Type its Lab Reference, for example "export DTP20261012FAA8-L as PDF", or open the report first and ask again.', 'Which report?', ['related' => [['title' => 'Export failed bonding reports this month to Excel']]]);







            }







            cache()->save('botctx_' . $uid, array_merge((array) cache('botctx_' . $uid), ['labref' => $ref]), 1800);







            $res = \App\Libraries\BotArtifacts::reportPdf($ref);







            if (($res['error'] ?? '') === 'not_found') {







                return $this->artReply('I could not find ' . $ref . ' in NEXUS-L. Check the number (date part, letters and the -L / -R ending).', 'Report not found', ['actions' => [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')]]]);







            }







            $row = $res['row'];







            $files = [];







            $actions = [];







            if ($fmt === 'pdf') {







                $f = $res['pdf'] ? \App\Libraries\BotArtifacts::register($res['pdf'], $ref . '-summary.pdf', 'application/pdf', $uid) : null;







                if ($f) {







                    $files[] = $this->fileEntry($f, 'Download ' . $ref . ' summary (PDF)');







                }







            } else {







                $items = (json_decode((string) $row['test_items_json'], true)['items'] ?? []);







                if (! $items) {







                    return $this->artReply($ref . ' has no test readings saved yet, so a spreadsheet of its tests would be empty. Open the report, enter or load the readings and save it; I can also make the PDF summary.', 'Nothing to export yet', ['actions' => [['type' => 'open', 'label' => 'Open ' . $ref, 'url' => site_url('dtr?tab=' . ((string) $row['form_slug'] === 'bonding' ? 'bonding' : 'material') . '&open=' . rawurlencode($row['form_slug'] . ':' . $row['source_id']))]], 'related' => [['title' => 'Export ' . $ref . ' as PDF']]]);







                }







                $head = ['Test', 'Method', 'Requirement', 'Reading', 'Result'];







                $rows = array_map(static fn ($it) => [(string) ($it['test_name'] ?? ''), (string) ($it['test_method'] ?? ''), (string) ($it['requirement_spec'] ?? ''), (string) ($it['actual_reading'] ?? ''), (string) ($it['result_status'] ?? $it['raw_rating'] ?? '')], $items);







                $p = $fmt === 'csv' ? \App\Libraries\BotArtifacts::csv($head, $rows) : \App\Libraries\BotArtifacts::xlsx($head, $rows, $ref);







                $f = $p ? \App\Libraries\BotArtifacts::register($p, $ref . '.' . $fmt, $mime[$fmt], $uid) : null;







                if ($f) {







                    $files[] = $this->fileEntry($f, 'Download ' . $ref . ' tests (' . strtoupper($fmt) . ')');







                }







            }







            $slug = (string) $row['form_slug'];







            if ($slug !== 'bonding') {







                $actions[] = ['type' => 'open', 'label' => 'Official report PDF (Access)', 'url' => site_url('dtr/report-pdf?lab_ref=' . rawurlencode($ref))];







            }







            $actions[] = ['type' => 'open', 'label' => 'Open the official sheet', 'url' => site_url('dtr?tab=' . ($slug === 'bonding' ? 'bonding' : 'material') . '&open=' . rawurlencode($slug . ':' . $row['source_id']) . '&preview=1')];







            if (! $files) {







                return $this->artReply('I found ' . $ref . ' but the file could not be created just now' . ($fmt === 'pdf' ? ' (the server needs Chrome or Edge to make PDFs)' : '') . '. The official sheet opens with its own Download PDF button.', 'Export', ['actions' => $actions, 'suggest_admin' => true]);







            }















            return $this->artReply($ref . ' - ' . $row['form_category'] . ($row['overall_result'] ? ', result ' . $row['overall_result'] : '') . '. The file below is a summary made from the saved report; the signed official sheet is the document you send. The link works for one hour.', 'Download ready', ['files' => $files, 'actions' => $actions]);







        }















        // a list of reports







        $f = \App\Libraries\BotArtifacts::filters($text);







        if ($f['slug'] === null && $f['result'] === null && $f['from'] === null && ! preg_match('/\b(all|every|whole|entire|reports|results|list|table|database)\b/i', $text)) {







            return $this->artReply('What should I export? Tell me which reports, for example "export failed bonding reports this month to Excel", or ask for a chart first and then say "export that to Excel".', 'What to export?', ['related' => [['title' => 'Export failed bonding reports this month to Excel'], ['title' => 'Show a pie chart of pass and fail']]]);







        }







        $rows = \App\Libraries\BotArtifacts::rows($f);







        if (! $rows) {







            return $this->artReply('No reports match that selection, so there is nothing to export. Try another period or category.', 'Nothing to export');







        }







        $label = ($f['result'] ? $f['result'] . ' ' : '') . ($f['slug'] ? \App\Libraries\BotArtifacts::CATEGORIES[$f['slug']] . ' ' : '') . 'reports' . ($f['period'] !== 'all time' ? ' - ' . $f['period'] : '');







        if ($fmt === 'pdf') {







            $p = \App\Libraries\BotArtifacts::listPdf($rows, ucfirst($label), 'QC Lab Reports');







            $mimeT = 'application/pdf';







        } else {







            $cols = array_keys(\App\Libraries\BotArtifacts::COLUMNS);







            $data = array_map(static fn ($r) => array_map(static fn ($c) => (string) ($r[$c] ?? ''), $cols), $rows);







            $head = array_values(\App\Libraries\BotArtifacts::COLUMNS);







            $p = $fmt === 'csv' ? \App\Libraries\BotArtifacts::csv($head, $data) : \App\Libraries\BotArtifacts::xlsx($head, $data, 'QC Reports');







            $mimeT = $mime[$fmt];







        }







        $file = $p ? \App\Libraries\BotArtifacts::register($p, 'qc-reports-' . $stamp . '.' . $fmt, $mimeT, $uid) : null;







        if (! $file) {







            return $this->artReply('I could not create the ' . strtoupper($fmt) . ' file just now' . ($fmt === 'pdf' ? ' (the server needs Chrome or Edge to make PDFs; Excel and CSV work without it)' : '') . '.', 'Export', ['suggest_admin' => true]);







        }















        return $this->artReply(ucfirst($label) . ': ' . number_format(count($rows)) . ' row(s)' . (count($rows) >= \App\Libraries\BotArtifacts::MAX_ROWS ? ' (the first ' . number_format(\App\Libraries\BotArtifacts::MAX_ROWS) . ')' : '') . ' exported from the live data. The link works for one hour.', 'Download ready', ['files' => [$this->fileEntry($file, 'Download ' . $file['name'])]]);







    }















    /** GET bot/file/<token> : the file, only for the person it was made for, only for a while */







    public function file(string $token = '')







    {







        $f = \App\Libraries\BotArtifacts::open($token, (int) ($this->auth->user()['id'] ?? 0));







        if (! $f) {







            return $this->response->setStatusCode(404)->setBody('This file is no longer available. Ask the assistant to make it again.');







        }







        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $f['name']);















        return $this->response->setHeader('Content-Type', $f['mime'])->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')







            ->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Cache-Control', 'private, no-store')->setBody((string) file_get_contents($f['path']));







    }















    /** POST bot/chart-pdf {png, title} : the picture of a chart the page drew, as a PDF */







    public function chartPdf()







    {







        $p   = $this->input();







        $uid = (int) ($this->auth->user()['id'] ?? 0);







        $pdf = \App\Libraries\BotArtifacts::imagePdf((string) ($p['png'] ?? ''), mb_substr(strip_tags((string) ($p['title'] ?? 'Chart')), 0, 120));







        $f   = $pdf ? \App\Libraries\BotArtifacts::register($pdf, 'chart-' . date('Ymd-His') . '.pdf', 'application/pdf', $uid) : null;







        if (! $f) {







            return $this->json(['ok' => false, 'error' => 'The PDF could not be made (the server needs Chrome or Edge, or too many files were made in the last hour).'], 422);







        }















        return $this->json(['ok' => true, 'url' => $f['url'], 'name' => $f['name']]);







    }















    private function dbSearchIntent(string $text): ?array
    {
        try {
            $low = mb_strtolower(trim($text));
            if (preg_match('/\b(test\s*methods?|vf\s*methods?|vf\s*-\s*[a-z0-9]+|list\s*of\s*test)\b/i', $low)) {
                return null;
            }
















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







        /**
     * Normalizes non-technical user typos and phonetic misspellings.
     */
    private function normalizePromptTypos(string $text): string
    {
        $map = [
            '/\bha[dr]d?ness?\b/i' => 'hardness',
            '/\btensil[e]?s?\b/i' => 'tensile',
            '/\btenslie\b/i' => 'tensile',
            '/\belong[at]?[io]{1,2}n\b/i' => 'elongation',
            '/\belagan[ti]on\b/i' => 'elongation',
            '/\babras[si]{1,2}on\b/i' => 'abrasion',
            '/\babration\b/i' => 'abrasion',
            '/\bflx[in]{1,2}g\b/i' => 'flexing',
            '/\bcrock[in]{1,2}g\b/i' => 'crocking',
            '/\bstich\b/i' => 'stitch',
            '/\btrouz[er]{1,2}\b/i' => 'trouser',
            '/\bteer\b/i' => 'tear',
            '/\bhydrol[iy]sis\b/i' => 'hydrolysis',
            '/\bthicknes\b/i' => 'thickness',
            '/\bcompress[io]{1,2}n\b/i' => 'compression',
            '/\bap[pa]{1,2}ratu[s]?s?\b/i' => 'apparatus',
            '/\bcalclate\b/i' => 'calculate',
            '/\bcalulate\b/i' => 'calculate',
            '/\bstandr?ad?\b/i' => 'standard',
            '/\bstandar\b/i' => 'standard',
            '/\brequir?ment\b/i' => 'requirement',
            '/\breportt?\b/i' => 'report',
            '/\blabref(?:erence)?\b/i' => 'lab reference',
            '/\btest\s*methd\b/i' => 'test method',
            '/\btest\s*methed\b/i' => 'test method',
        ];
        return preg_replace(array_keys($map), array_values($map), $text);
    }

private function computeIntent(string $text, string $prev = '', string $lastBotMsg = '', string $threadContext = ''): ?array







    {







        $low = mb_strtolower(trim($text));
        // Guard: Never interpret VF test method queries or codes as specimen calculations!
        if (preg_match('/\b(?:vf|dtp-lab|series)\s*[-–]?\s*[a-z0-9]+/i', $text) || preg_match('/\b(?:test\s+methods?|illustrated|procedure|standard\s+for|what\s+is\s+vf)\b/i', $text)) {
            return null;
        }
        $comb = mb_strtolower(trim($prev . ' ' . $text . ' ' . $threadContext));
        // Detect pending calculation from previous bot message
        $pendingTest = '';
        if (preg_match('/To calculate the average and evaluate \*\*([^*]+)\*\*/i', $lastBotMsg, $pbm)) {
            $pendingTest = trim($pbm[1]);
            $comb .= ' ' . mb_strtolower($pendingTest);
        } elseif (preg_match('/\b(Hardness|Tensile Strength|Elongation|Trouser Tear|Stitch Tear|Die C Tear|DIN Abrasion|Ross Flex|Static Compression Set)\b/i', $lastBotMsg, $pbm)) {
            $pendingTest = trim($pbm[1]);
            $comb .= ' ' . mb_strtolower($pendingTest);
        }















        $hasComputeWord = (bool) preg_match('/\b(comput|calculat|verdict|pass\s*(or|\/)\s*fail|is\s*(this|it)\s*(pass|fail)|evaluate|work\s*out|average\s*of|how\s*much\s*is|formula|rating\s*of)\b/i', $low);







        $hasTestWord = (bool) preg_match('/\b(bonding|u60|utf|fto|fito|tensile|elongation|tear|trouser|stitch|die\s*c|sg|specific\s*gravity|compression\s*set|abrasion|din|ross\s*flex|shrinkage|thickness|specimen|hardness)\b/i', $comb);







        $hasNumbers = (bool) preg_match('/\d+(?:\.\d+)?/', $low);















        if (! $hasComputeWord && ! ($hasTestWord && $hasNumbers) && ! ($pendingTest !== '' && $hasNumbers)) {
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







        return $this->computeDirectionalOrGeneric($low, $comb, $pendingTest, $lastBotMsg);







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







                    . "   • **Adult**: Standard 3.0 kg/cm (Tolerable >= 2.4)\n"







                    . "   • **Children / Kids**: Standard 2.5 kg/cm (Tolerable >= 2.0)\n"







                    . "   • **Center FiTO**: Standard 1.0 kg/cm (Tolerable >= 0.8)\n\n"







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







                    . "• If **Adult (STD 3.0)**: Result is **{$adultRating}** (Tolerable limit: >= 2.4)\n"







                    . "• If **Children / Kids (STD 2.5)**: Result is **{$childRating}** (Tolerable limit: >= 2.0)\n\n"







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







            . "• **Standard (STD):** >= {$std} kg/cm\n"







            . "• **Tolerable Range (80% STD):** {$tol} – " . ($std - 0.01) . " kg/cm\n"







            . "• **Failure Threshold (< 80% STD):** < {$tol} kg/cm\n"







            . "• **Specimen Readings:** {$readingsStr}\n"







            . "• **Lowest Spot (Min):** {$min} kg/cm\n"







            . "• **Average:** {$ave} kg/cm\n\n"







            . "**Official Rule:** PASS >= STD, TOLERABLE >= 80% STD, FAILED < 80% STD.";















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







                $rateStr = "\n• **Standard:** {$std} (Tolerance +/- 0.02)\n• **Verdict:** " . ($ok ? '✅ PASS' : '❌ FAIL');







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







                        . "Tolerance against standard is +/- 0.02.",







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







                $rateStr = "\n• **Standard (Max):** <= {$std}%\n• **Verdict:** " . ($ok ? '✅ PASS' : '❌ FAIL');







            }















            return [







                'text'       => "📊 **Compression Set Calculation:**\n\n"







                    . "• **Original Thickness ($t_0$):** {$t0} mm\n"







                    . "• **After Recovery ($t_1$):** {$t1} mm\n"







                    . "• **Spacer Thickness ($t_s$):** {$ts} mm\n"







                    . "• **Calculated Compression Set:** **{$cs}%**\n"







                    . "• **Formula:** $\\frac{t_0 - t_1}{t_0 - t_s} \\times 100\\%" . $rateStr,







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















    private function computeDirectionalOrGeneric(string $low, string $comb, string $promptedTest = '', string $lastBotMsg = ''): array







    {







        preg_match_all('/\b\d+(?:\.\d+)?\b/', $low, $m);







        $nums = array_map('floatval', $m[0] ?? []);















        // Detect Standard if specified (enhanced for conversational phrasing like "the standard is 12")
        $std = null;
        if (preg_match('/\b(?:the\s+)?(?:std|standard|req|requirement)\s*(?:is\s*[:=]?|[:=])?\s*(?:>=|<=)?\s*(\d+(?:\.\d+)?)/i', $low . ' ' . $comb, $sm)) {
            $std = (float) $sm[1];
            // Remove STD from numbers list if present
            if (($key = array_search($std, $nums, true)) !== false && count($nums) > 1) {
                unset($nums[$key]);
                $nums = array_values($nums);
            }
        }

        // If numbers were omitted in current turn, check if previous bot message held them
        if (empty($nums) && preg_match('/(?:Readings|Values|Specimens)\s*:\s*`?([0-9.,\s]+)`?/i', $lastBotMsg, $prm)) {
            preg_match_all('/\b\d+(?:\.\d+)?\b/', $prm[1], $prmm);
            $nums = array_map('floatval', $prmm[0] ?? []);
        }

        // Detect Test Name (prioritize promptedTest from conversation memory)
        $testName = $promptedTest !== '' ? $promptedTest : 'Material Test';
        if ($testName === 'Material Test') {
            if (preg_match('/\b(tensile)\b/i', $comb)) $testName = 'Tensile Strength';







        elseif (preg_match('/\b(elongation)\b/i', $comb)) $testName = 'Elongation';







        elseif (preg_match('/\b(trouser\s*tear)\b/i', $comb)) $testName = 'Trouser Tear';







        elseif (preg_match('/\b(stitch\s*tear)\b/i', $comb)) $testName = 'Stitch Tear';







        elseif (preg_match('/\b(die\s*c)\b/i', $comb)) $testName = 'Die C Tear';







        elseif (preg_match('/\b(mullen\s*burst)\b/i', $comb)) $testName = 'Mullen Burst';







        elseif (preg_match('/\b(thickness)\b/i', $comb)) $testName = 'Thickness';







        elseif (preg_match('/\b(hardness)\b/i', $comb)) $testName = 'Hardness';
        }















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







                . "• **Standard Specification:** >= {$std}\n"







                . "• **Verdict:** **{$badge}** ({$ave} " . ($pass ? '>=' : '<') . " {$std})\n\n"







                . "**Formula:** Average = (Sum of Specimens) / N. Evaluated against standard priority rules.",







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







            return ['text' => 'Hello! I am LEXUS AI (Laboratory Execution Intelligence System). Ask me how to do something in this system, calculate test results, or check report status and I will help you.', 'steps' => [], 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => false];







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















        // questions made only of filler words have nothing to search for: answer the common ones directly







        $direct = null;







        if (preg_match('/^(what|how)\s+(can|could|do)\s+(you|u)\s+(do|help|offer)\b|^(what|which)\s+(are\s+)?(your\s+)?(features|abilities|capabilities|skills)|^what\s+can\s+i\s+(ask|say)\b/i', trim($low))) {







            $direct = 'What can you do?';







        } elseif (preg_match('/^(why|why not|how come|why is that|why\?+)$/i', trim($low))) {







            $direct = 'Why?';







        }







        if ($direct !== null) {







            $row = $this->db()->table('tbl_bot_knowledge')->where(['title' => $direct, 'status' => 'approved'])->get()->getRowArray();







            if ($row) {







                return ['text' => (string) $row['body'], 'title' => (string) $row['title'], 'steps' => array_values(array_filter((array) json_decode((string) ($row['steps_json'] ?? ''), true), 'is_string')), 'actions' => [], 'confidence' => 100, 'related' => [], 'diagnosis' => null, 'knowledge_id' => (int) $row['id'], 'suggest_admin' => false];







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







                // never a dead end: say what is possible, offer the closest topics and examples, and keep the administrator one click away







                'text'  => ($hits ? 'I am not sure I understood that. These topics look closest - pick one, or rephrase in other words. ' : 'I do not have a verified answer for that yet. ') . 'I have noted the question so it can be added. I can also answer how-to questions, count and chart the live QC data, and make PDF / Excel files; or I can connect you to an administrator.',







                'title' => 'Let me help you with that',







                'steps' => [], 'actions' => [], 'confidence' => $conf, 'diagnosis' => null, 'knowledge_id' => null, 'suggest_admin' => true,







                'related' => array_merge(







                    array_map(static fn ($h) => ['id' => (int) $h['row']['id'], 'title' => $h['row']['title']], array_slice($hits, 0, 3)),







                    [['title' => 'What can you do?'], ['title' => 'I do not know what to ask']]







                ),







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







        \App\Libraries\BrainEvents::log('feedback', '', $c['knowledge_id'] ? (int) $c['knowledge_id'] : null, $val, $val > 0 ? 'thumbs up' : 'thumbs down');







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







        $out = [







            'ok'          => true,







            'ai_enabled'  => \App\Libraries\SystemSettings::get('ai.enabled', '1') !== '0',







            'user_unread' => (int) ($db->table('tbl_bot_threads')->selectSum('user_unread', 's')->where('user_id', (int) $u['id'])->get()->getRow()->s ?? 0),







        ];







        $out['user_live'] = $db->table('tbl_bot_sessions')->where(['user_id' => (int) $u['id'], 'status' => 'open'])->where('last_activity_at >=', date('Y-m-d H:i:s', time() - BotSessions::IDLE))->countAllResults() > 0;







        if ($this->isAdmin()) {







            BotSessions::expire();







            $out['admin_unread'] = (int) $db->table('tbl_bot_sessions')->where('status', 'open')->groupStart()->where('admin_unread >', 0)->orWhere('admin_user IS NULL', null, false)->groupEnd()->countAllResults();







        }















        return $this->json($out);







    }















    // ================================================================================ admin console















    /** POST bot/toggle-ai or brain/toggle (admin only) */







    public function toggleAi()







    {







        if (! $this->isAdmin()) {







            return $this->json(['ok' => false, 'error' => 'Admin role required.'], 403);







        }







        $p = $this->input();







        $current = \App\Libraries\SystemSettings::get('ai.enabled', '1');







        $newVal = isset($p['enabled']) ? ($p['enabled'] ? '1' : '0') : ($current === '0' ? '1' : '0');

        \App\Libraries\SystemSettings::set('ai.enabled', $newVal, (string) ($this->auth->user()['username'] ?? 'admin'));
        if ($newVal === '1') {
            if (\App\Libraries\SystemSettings::get('ai.mode', 'shadow') === 'off') {
                \App\Libraries\SystemSettings::set('ai.mode', 'live', (string) ($this->auth->user()['username'] ?? 'admin'));
            }
            cache()->delete('brain_ai_online');
            if (\App\Libraries\AiBridge::get('/health', 1000) === null) {
                $cmd = ROOTPATH . 'run-ai.cmd';
                if (is_file($cmd)) {
                    @pclose(@popen('start /B "" "' . $cmd . '"', 'r'));
                    // the service loads its models for several seconds: wait for it (up to ~25 s) so the switch reports the real state
                    for ($i = 0; $i < 25 && \App\Libraries\AiBridge::get('/health', 800) === null; $i++) {
                        usleep(1000000);
                    }
                    cache()->delete('brain_ai_online');
                }
            }
        } else {
            cache()->delete('brain_ai_online');
        }

        \App\Libraries\BrainEvents::log('toggle', 'admin', (int) ($this->auth->user()['id'] ?? 0), null, 'AI Brain turned ' . ($newVal === '1' ? 'ON' : 'OFF'));

        $online = $newVal === '1' && \App\Libraries\AiBridge::get('/health', 1200) !== null;

        return $this->json([
            'ok'      => true,
            'enabled' => $newVal === '1',
            'status'  => $newVal === '1' ? 'ON' : 'OFF',
            'online'  => $online,
            'message' => 'AI Brain successfully turned ' . ($newVal === '1' ? 'ON' : 'OFF') . ($newVal === '1' ? ($online ? ' (connected).' : ' (starting service...).') : '.'),
        ]);







    }















    public function console(): string







    {







        return $this->render('brain/index', ['title' => 'Brain', 'withCss' => 'brain.css', 'withJs' => ['brain.js', 'brain_ai.js']]);







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







        $newId = (int) $db->insertID();







        \App\Libraries\BrainEvents::log('learn', '', $newId, null, $data['title']);















        return $newId;







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















    // ------------------------------------------------------------------------------------------ Brain Universe + Train AI (admins)















    /** GET brain/universe/graph : every approved answer with its topic cluster and how alive it is */







    public function universeGraph()







    {







        return $this->json(['ok' => true, 'nodes' => \App\Libraries\BrainEvents::nodes(), 'stats' => \App\Libraries\BrainEvents::stats(), 'mode' => \App\Libraries\AiBridge::mode(), 'now' => time()]);







    }















    /** GET brain/universe/feed?since= : what happened since the last poll (questions, new knowledge, votes, training) */







    public function universeFeed()







    {







        return $this->json(['ok' => true] + \App\Libraries\BrainEvents::feed((int) $this->request->getGet('since')));







    }















    /** GET brain/ai/status : the two brains, the training state and the review queue */







    public function aiStatus()







    {







        $s = \App\Libraries\AiBridge::get('/admin/train/status', 4000);















        return $this->json(['ok' => true, 'online' => $s !== null, 'mode' => \App\Libraries\AiBridge::mode(), 'threshold_php' => \App\Libraries\AiBridge::threshold(), 'ai' => $s,







            'php' => ['knowledge' => (int) $this->db()->table('tbl_bot_knowledge')->where('status', 'approved')->countAllResults()]]);







    }















    /** POST brain/ai/train : export the latest answers and questions, then start a training run in the AI service */







    public function aiTrain()







    {







        try {







            \App\Libraries\AiExporter::run();







        } catch (\Throwable $e) {







            return $this->json(['ok' => false, 'error' => 'Could not export the answers: ' . $e->getMessage()], 500);







        }







        $r = \App\Libraries\AiBridge::admin('POST', '/admin/train/start', [], 8000);







        if ($r === null) {







            return $this->json(['ok' => false, 'error' => 'The AI service is not answering. Start it with run-ai.cmd.'], 503);







        }







        \App\Libraries\BrainEvents::log('train', 'primary', null, null, 'Training started');







        \App\Libraries\AiBridge::admin('POST', '/admin/review/seed', [], 15000);















        return $this->json(['ok' => true, 'started' => (bool) ($r['started'] ?? false)]);







    }















    /** GET brain/ai/review : the next real question with its three best candidate answers */







    public function aiReview()







    {







        if (! \App\Libraries\AiBridge::enabled() || ! \App\Libraries\BrainEvents::aiOnline()) {
            return $this->json(['ok' => true, 'item_id' => null, 'pending' => 0, 'labelled_cases' => 0, 'candidates' => []]);
        }

        $r = \App\Libraries\AiBridge::admin('GET', '/admin/review/next', [], 20000);















        return $r === null ? $this->json(['ok' => true, 'item_id' => null, 'pending' => 0, 'labelled_cases' => 0, 'candidates' => [], 'notice' => 'Consolidating knowledge base...']) : $this->json(['ok' => true] + $r);







    }















    /** POST brain/ai/review/seed : bring in the newest real questions */







    public function aiReviewSeed()







    {







        try {







            \App\Libraries\AiExporter::run();







        } catch (\Throwable $e) {







            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);







        }







        $r = \App\Libraries\AiBridge::admin('POST', '/admin/review/seed', [], 20000);















        return $r === null ? $this->json(['ok' => true, 'item_id' => null, 'pending' => 0, 'labelled_cases' => 0, 'candidates' => [], 'notice' => 'Consolidating knowledge base...']) : $this->json(['ok' => true] + $r);







    }















    /** POST brain/ai/label {item_id, choice, kb_id?, answer?} */







    public function aiLabel()







    {







        $p = $this->input();







        $choice = in_array($p['choice'] ?? '', ['kb', 'none', 'offtopic', 'skip'], true) ? $p['choice'] : 'skip';







        $r = \App\Libraries\AiBridge::admin('POST', '/admin/review/' . (int) ($p['item_id'] ?? 0) . '/label', [







            'choice' => $choice, 'kb_id' => isset($p['kb_id']) ? (int) $p['kb_id'] : null, 'admin_id' => (string) ($this->auth->user()['id'] ?? 0), 'answer' => (string) ($p['answer'] ?? ''),







        ], 20000);







        if ($r === null) {







            return $this->json(['ok' => false, 'error' => 'The AI service is not answering.'], 503);







        }







        if ($choice === 'none' && mb_strlen(trim((string) ($p['answer'] ?? ''))) >= 20) {







            \App\Libraries\BrainEvents::log('learn', 'primary', null, null, 'New answer taught in the review task');







        }















        return $this->json(['ok' => ! empty($r['ok'])] + $r);







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







