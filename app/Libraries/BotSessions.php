<?php

namespace App\Libraries;

use Config\Synology;

/**
 * Live sessions with an administrator inside the assistant chat.
 *  - "Talk to an administrator" opens a session: the chat shows the "Current Session with Admin" line, every active
 *    administrator gets a Synology Chat DM right away, and the admins' pages show an attention card.
 *  - While the session is open the user's messages go to the administrator (the bot stays quiet).
 *  - The administrator closes it, or it closes by itself after IDLE seconds without a message from either side.
 *    After that nobody can write into it any more and the chat is back with the bot.
 */
class BotSessions
{
    public const IDLE = 120;

    public const LINE_OPEN  = '------------Current Session with Admin------------';
    public const LINE_CLOSE = '------------Session with Admin closed------------';

    /** Seconds between two Synology reminders for new user messages of the same session. */
    private const RENOTIFY = 60;

    private static function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    /** Chat name on the admin side: <username>-S<id>. */
    public static function code(array $s): string
    {
        return ($s['username'] ?: 'user') . '-S' . str_pad((string) $s['id'], 4, '0', STR_PAD_LEFT);
    }

    /** What the pages need to know about a session. */
    public static function public(?array $s): ?array
    {
        if (! $s) {
            return null;
        }
        $left = $s['status'] === 'open' ? max(0, self::IDLE - (time() - strtotime((string) $s['last_activity_at']))) : 0;

        return [
            'id' => (int) $s['id'], 'code' => self::code($s), 'status' => $s['status'], 'thread_id' => (int) $s['thread_id'],
            'username' => $s['username'], 'user_name' => $s['user_name'], 'topic' => $s['topic'], 'page' => $s['page'],
            'admin_user' => $s['admin_user'], 'admin_name' => $s['admin_name'], 'admin_unread' => (int) $s['admin_unread'],
            'started_at' => $s['started_at'], 'last_activity_at' => $s['last_activity_at'], 'closed_at' => $s['closed_at'],
            'closed_by' => $s['closed_by'], 'seconds_left' => $left,
        ];
    }

    public static function find(int $id): ?array
    {
        return self::db()->table('tbl_bot_sessions')->where('id', $id)->get()->getRowArray() ?: null;
    }

    /** The open session of a thread, if any (closes it first when it has been idle too long). */
    public static function active(int $threadId): ?array
    {
        self::expire();

        return self::db()->table('tbl_bot_sessions')->where(['thread_id' => $threadId, 'status' => 'open'])->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    /** @return list<array> open sessions, newest activity first */
    public static function openList(): array
    {
        self::expire();

        return self::db()->table('tbl_bot_sessions')->where('status', 'open')->orderBy('last_activity_at', 'DESC')->limit(30)->get()->getResultArray();
    }

    /** Closes every open session without a message for IDLE seconds. Returns how many were closed. */
    public static function expire(): int
    {
        $rows = self::db()->table('tbl_bot_sessions')->where('status', 'open')->where('last_activity_at <', date('Y-m-d H:i:s', time() - self::IDLE))->get()->getResultArray();
        foreach ($rows as $s) {
            self::close($s, 'timeout');
        }

        return count($rows);
    }

    /** Adds a chat line to the thread (and the session). Returns the chat id. */
    public static function line(int $threadId, ?int $sessionId, string $sender, string $name, string $message): int
    {
        $db  = self::db();
        $now = date('Y-m-d H:i:s');
        $db->table('tbl_bot_chats')->insert(['thread_id' => $threadId, 'session_id' => $sessionId, 'sender' => $sender, 'sender_name' => $name, 'message' => $message, 'created_at' => $now]);
        $id = (int) $db->insertID();
        $db->table('tbl_bot_threads')->where('id', $threadId)->update(['updated_at' => $now]);

        return $id;
    }

    /** Opens (or returns the already open) session for the thread and alerts the administrators. */
    public static function open(array $thread, array $user, string $page): array
    {
        $s = self::active((int) $thread['id']);
        if ($s) {
            return $s;
        }
        $db  = self::db();
        $now = date('Y-m-d H:i:s');
        $topic = (string) ($thread['topic'] ?? '');
        $db->table('tbl_bot_sessions')->insert([
            'thread_id' => (int) $thread['id'], 'user_id' => (int) $user['id'], 'username' => (string) ($user['username'] ?? ''),
            'user_name' => (string) ($user['name'] ?? $user['username'] ?? ''), 'status' => 'open', 'admin_unread' => 1,
            'topic' => mb_substr($topic, 0, 200), 'page' => mb_substr($page, 0, 160), 'started_at' => $now, 'last_activity_at' => $now, 'notified_at' => $now,
        ]);
        $s = self::find((int) $db->insertID());
        $db->table('tbl_bot_threads')->where('id', $thread['id'])->update(['status' => 'escalated', 'admin_unread' => 1, 'updated_at' => $now]);
        self::line((int) $thread['id'], (int) $s['id'], 'system', 'NEXUS-L', self::LINE_OPEN);
        self::line((int) $thread['id'], (int) $s['id'], 'bot', 'NEXUS-L Assistant', 'An administrator has been notified and will reply here. Write your question below. This chat closes by itself after 2 minutes without messages.');
        static::notifyAdmins($s, 'needs an administrator', $topic);

        return $s;
    }

    /** A message from the user inside the session. */
    public static function fromUser(array $s, string $text): void
    {
        $db  = self::db();
        $now = date('Y-m-d H:i:s');
        $db->table('tbl_bot_sessions')->where('id', $s['id'])->set('admin_unread', 'admin_unread + 1', false)->update(['last_activity_at' => $now]);
        $db->table('tbl_bot_threads')->where('id', $s['thread_id'])->set('admin_unread', 'admin_unread + 1', false)->update();
        if (! $s['notified_at'] || time() - strtotime((string) $s['notified_at']) >= self::RENOTIFY) {
            $db->table('tbl_bot_sessions')->where('id', $s['id'])->update(['notified_at' => $now]);
            static::notifyAdmins($s, 'wrote', $text, $s['admin_user'] ?: null);
        }
    }

    /** A message from an administrator; the first one joins them to the session. */
    public static function fromAdmin(array $s, array $admin, string $text): int
    {
        $db   = self::db();
        $now  = date('Y-m-d H:i:s');
        $name = (string) ($admin['name'] ?? $admin['username'] ?? 'Administrator');
        if (! $s['admin_user']) {
            $db->table('tbl_bot_sessions')->where('id', $s['id'])->update(['admin_user' => (string) ($admin['username'] ?? ''), 'admin_name' => $name]);
            self::line((int) $s['thread_id'], (int) $s['id'], 'system', 'NEXUS-L', $name . ' joined the chat');
        }
        $id = self::line((int) $s['thread_id'], (int) $s['id'], 'admin', $name, $text);
        $db->table('tbl_bot_sessions')->where('id', $s['id'])->update(['last_activity_at' => $now, 'admin_unread' => 0]);
        $db->table('tbl_bot_threads')->where('id', $s['thread_id'])->set('user_unread', 'user_unread + 1', false)
            ->update(['admin_user' => (string) ($admin['username'] ?? ''), 'admin_unread' => 0]);

        return $id;
    }

    /** Closes the session ($by = admin username or "timeout"); the thread goes back to the bot. */
    public static function close(array $s, string $by): void
    {
        if ($s['status'] !== 'open') {
            return;
        }
        $db  = self::db();
        $now = date('Y-m-d H:i:s');
        $ok  = $db->table('tbl_bot_sessions')->where(['id' => $s['id'], 'status' => 'open'])->update(['status' => 'closed', 'closed_at' => $now, 'closed_by' => $by, 'admin_unread' => 0]);
        if (! $ok || $db->affectedRows() === 0) {
            return;   // closed meanwhile by another request
        }
        $why = $by === 'timeout' ? 'There were no messages for 2 minutes, so the chat with the administrator was closed.' : 'The administrator closed this chat.';
        self::line((int) $s['thread_id'], (int) $s['id'], 'system', 'NEXUS-L', self::LINE_CLOSE);
        self::line((int) $s['thread_id'], null, 'bot', 'NEXUS-L Assistant', $why . ' You are back with the assistant; press "Talk to an administrator" again if you still need help.');
        $db->table('tbl_bot_threads')->where('id', $s['thread_id'])->set('user_unread', 'user_unread + 1', false)
            ->update(['status' => 'bot', 'admin_unread' => 0, 'updated_at' => $now]);
    }

    /**
     * Synology Chat DM to the administrators (one call per person: the bot API refuses several user_ids at once).
     * $only limits it to the administrator who joined the session.
     */
    public static function notifyAdmins(array $s, string $what, string $text, ?string $only = null): int
    {
        try {
            $q = self::db()->table('tbl_users')->select('Username, SynologyID')->where('Role', 'admin')->where('IsActive', 1);
            if ($only) {
                $q->where('Username', $only);
            }
            $admins = $q->get()->getResultArray();
            $cfg = clone config(Synology::class);
            $cfg->timeout = 4;
            $chat = new SynologyChat($cfg);
            $name = $s['user_name'] ?: $s['username'];
            $msg  = '[NEXUS-L Assistant] ' . $name . ' ' . $what . "\nChat: " . self::code($s)
                . ($text !== '' ? "\nMessage: " . mb_substr($text, 0, 300) : '')
                . "\nOpen: " . site_url('dashboard?nxsession=' . $s['id']);
            $sent = 0;
            foreach ($admins as $a) {
                $syn = trim((string) $a['SynologyID']);
                if (ctype_digit($syn) && $syn !== '0' && strcasecmp((string) $a['Username'], (string) $s['username']) !== 0) {
                    $sent += (int) $chat->send((int) $syn, $msg);
                }
            }

            return $sent;
        } catch (\Throwable $e) {
            log_message('error', '[BotSessions] notify: ' . $e->getMessage());

            return 0;
        }
    }
}
