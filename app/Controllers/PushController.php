<?php

namespace App\Controllers;

use App\Libraries\WebPush;

/** Browser push (works with NEXUS-L closed): the browser subscribes here, the server sends through App\Libraries\WebPush. */
class PushController extends BaseController
{
    private function json(array $d, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($d);
    }

    /** GET push/key : the public key the browser subscribes with */
    public function key()
    {
        try {
            return $this->json(['ok' => true, 'key' => WebPush::publicKey()]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST push/subscribe {endpoint, keys:{p256dh, auth}} */
    public function subscribe()
    {
        $p  = $this->request->getJSON(true) ?? [];
        $me = (array) service('auth')->user();
        $ep = trim((string) ($p['endpoint'] ?? ''));
        $k  = (array) ($p['keys'] ?? []);
        if (! preg_match('#^https://#', $ep) || strlen($ep) > 700 || empty($k['p256dh']) || empty($k['auth'])) {
            return $this->json(['ok' => false, 'error' => 'Not a valid push subscription.'], 422);
        }
        WebPush::ensureTables();
        \Config\Database::connect()->query(
            'INSERT INTO tbl_push_subscriptions (user_id, role, endpoint, p256dh, auth, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), role = VALUES(role), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), last_error = ""',
            [(string) ($me['id'] ?? ''), (string) ($me['role'] ?? ''), $ep, (string) $k['p256dh'], (string) $k['auth'], mb_substr((string) $this->request->getUserAgent(), 0, 250)]
        );

        return $this->json(['ok' => true]);
    }

    /** POST push/unsubscribe {endpoint} */
    public function unsubscribe()
    {
        $p = $this->request->getJSON(true) ?? [];
        WebPush::ensureTables();
        \Config\Database::connect()->table('tbl_push_subscriptions')->where('endpoint', (string) ($p['endpoint'] ?? ''))->delete();

        return $this->json(['ok' => true]);
    }

    /** POST push/test : a test notification to every device of the signed-in user */
    public function test()
    {
        $me = (array) service('auth')->user();
        $n  = WebPush::notify('NEXUS-L notifications are on', 'This is a test. You will get lab alerts here even when NEXUS-L is closed.', 'dashboard', ['user_ids' => [(string) ($me['id'] ?? '')], 'tag' => 'nxl-test']);

        return $this->json(['ok' => true, 'sent' => $n]);
    }
}
