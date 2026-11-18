<?php

namespace App\Libraries;

use App\Libraries\Chat\NexusStore;

/**
 * Sends a "Lab Alerts" message from NEXUS-L itself (the Notifier app does the same for its rules): the Inbox thread "Lab Alerts - NEXUS-L" of each
 * person plus a Synology Chat DM when the person has one. Every message is written to tbl_notif_log so the Notifier's log shows it.
 */
class LabAlerts
{
    /** @param list<int|string> $userIds @return int people reached */
    public static function send(array $userIds, string $title, string $text, string $ruleName = 'NEXUS-L'): int
    {
        $db    = \Config\Database::connect();
        $store = new NexusStore();
        $syn   = new SynologyChat();
        $n     = 0;
        $k     = 'nx-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            $u = $db->query('SELECT UserID, FullName, SynologyID FROM tbl_users WHERE UserID = ? AND IsActive = 1', [$uid])->getRowArray();
            if (! $u) {
                continue;
            }
            $via = [];
            try {
                $store->send('lab_alerts', (string) $uid, $text);
                $via[] = 'inbox';
            } catch (\Throwable $e) {
                log_message('error', '[LabAlerts] inbox: ' . $e->getMessage());
            }
            if ((int) $u['SynologyID'] > 0) {
                try {
                    $via[] = $syn->send((int) $u['SynologyID'], $text) ? 'synology' : 'synology failed';
                } catch (\Throwable $e) {
                    $via[] = 'synology failed';
                }
            }
            try {
                $db->table('tbl_notif_log')->insert(['rule_id' => 0, 'rule_name' => mb_substr($ruleName, 0, 120), 'k' => $k, 'recipient_id' => (string) $uid, 'recipient_name' => (string) $u['FullName'],
                    'level' => 0, 'title' => mb_substr($title, 0, 190), 'body' => $text, 'channels' => 'inbox,synology', 'ack_code' => '', 'sent_at' => date('Y-m-d H:i:s'), 'result' => implode(', ', $via)]);
            } catch (\Throwable $e) {
                // the log is a convenience
            }
            if ($via) {
                $n++;
            }
        }

        return $n;
    }
}
