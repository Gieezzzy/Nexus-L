<?php

namespace App\Libraries;

use App\Libraries\Chat\ChatStore;

/**
 * Tells the recipient about a new web message: Firebase push to their Android device(s) (RMS chats only,
 * since only RMS users have registered devices) and a Synology Chat DM. Failures are logged, never thrown.
 */
class InboxNotifier
{
    /** @return array{muted:bool,fcmTokens:int,fcmSent:int,synology:?bool} what was attempted */
    public function newMessage(ChatStore $store, string $fromId, string $fromName, string $toId, string $text, ?string $toSynologyId = null): array
    {
        $res = ['muted' => false, 'fcmTokens' => 0, 'fcmSent' => 0, 'synology' => null];
        try {
            if ($store->isMuted($fromId, $toId)) {
                $res['muted'] = true;

                return $res;
            }
        } catch (\Throwable $e) {
            // mute lookup is optional
        }

        $preview = mb_strlen($text) > 140 ? mb_substr($text, 0, 140) . '…' : $text;
        $convId  = ChatStore::singleId($fromId, $toId);

        // 1) Firebase push, same payload contract as send-reply.php (RMS or linked users)
        $fcm = new FcmService();
        $fcmTarget = $store->source() === 'r' ? $toId : (string) $toSynologyId;
        if ($fcmTarget !== '' && $fcmTarget !== '0' && strcasecmp($fcmTarget, 'n/a') !== 0) {
            try {
                if ($fcm->isConfigured()) {
                    foreach ($fcm->tokensFor($fcmTarget) as $token) {
                        $res['fcmTokens']++;
                        $res['fcmSent'] += (int) $fcm->sendChat($token, $fromName, $preview, [
                            'sender' => $fromName, 'message' => $preview, 'conversation_id' => $convId,
                            'sender_id' => $fromId, 'TARGET_PEER_ID' => $convId,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', '[Inbox] FCM RMS: ' . $e->getMessage());
            }
        }

        // 1b) Firebase push for NEXUS-L web push tokens (tbl_user_push_tokens)
        try {
            $db = db_connect();
            if ($db->tableExists('tbl_user_push_tokens') && $fcm->isConfigured()) {
                $userTokens = $db->query("SELECT token FROM tbl_user_push_tokens WHERE user_id = ?", [$toId])->getResultArray();
                foreach ($userTokens as $tRow) {
                    $webTok = trim((string) ($tRow['token'] ?? ''));
                    if ($webTok !== '') {
                        $res['fcmTokens']++;
                        $res['fcmSent'] += (int) $fcm->sendChat($webTok, $fromName, $preview, [
                            'sender' => $fromName, 'message' => $preview, 'conversation_id' => $convId,
                            'sender_id' => $fromId, 'TARGET_PEER_ID' => $convId,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[Inbox] FCM Nexus web push: ' . $e->getMessage());
        }

        // 2) Synology Chat DM when the recipient has a numeric Synology id
        $syn = $store->source() === 'r' ? $toId : (string) $toSynologyId;
        if (ctype_digit($syn) && $syn !== '0') {
            $synologyMsg = "You have Recieved a new Message in Nexus-L login here to access the message 192.168.32.43/Nexus-L";
            $res['synology'] = service('synologyChat')->send((int) $syn, $synologyMsg, false);
        }

        // 3) Audit notification in tbl_notification_logs
        try {
            $db = db_connect();
            if ($db->tableExists('tbl_notification_logs')) {
                $db->table('tbl_notification_logs')->insert([
                    'user_id'    => $toId,
                    'title'      => $fromName,
                    'body'       => $preview,
                    'source'     => $store->source() === 'r' ? 'rms' : 'nexus',
                    'status'     => $res['fcmSent'] > 0 ? 'sent' : ($res['synology'] ? 'synology_sent' : 'logged'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            // log insertion is non-blocking
        }

        return $res;
    }
}
