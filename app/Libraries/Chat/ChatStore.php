<?php

namespace App\Libraries\Chat;

use App\Libraries\RmsCrypto;

/**
 * One chat engine, two databases. All behaviour (queries written in the SQL subset both
 * SQL Server and MariaDB understand, encryption, reactions, pins, nicknames, ...) lives here;
 * a store only supplies the connection, a few dialect hooks and its user directory.
 *
 * Table placeholders: {M} messages, {S} per-chat settings.
 */
abstract class ChatStore
{
    public const PIN_LIMIT      = 8;
    public const EDIT_WINDOW_S  = 60;

    /** 'n' = NEXUS-L (dbnexus), 'r' = RMS (dbRMSAndroid). */
    abstract public function source(): string;

    /** @return array<int,array<string,mixed>> */
    abstract protected function run(string $sql, array $params = []): array;

    abstract protected function exec(string $sql, array $params = []): void;

    /** Execute an INSERT into {M} and return the new MessageID. */
    abstract protected function insertMessage(string $sql, array $params): int;

    abstract protected function insertGroupSql(array $att): string;

    abstract protected function insertGroupParams(string $me, string $groupId, string $stored, array $att): array;

    abstract public function createGroupRecord(string $creator, string $name, array $members, string $policies, ?string $pic = null): int;

    /** Row-limit clause that goes after ORDER BY. */
    abstract protected function lim(int $n): string;

    abstract protected function tables(): array;

    /** Extra message columns (attachments) selected by this store, with a leading comma, or ''. */
    protected function extraCols(): string
    {
        return '';
    }

    /** SQL predicate selecting image messages / non-image file messages (no leading AND). */
    abstract protected function imageWhere(): string;

    protected function filesWhere(): ?string
    {
        return null;
    }

    // ---- user directory (store specific) ----

    /** @return array{id:string,name:string,username:string,online:bool,avatar:?string,dept?:string}|null */
    abstract public function person(string $id): ?array;

    /** @return array<int,array> */
    abstract public function search(string $me, string $q): array;

    /**
     * Everyone this user can start a chat with (active accounts, not themselves), optionally filtered by
     * name / username / Synology ID. Used by the "New message" dialog.
     * @return array<int,array>
     */
    abstract public function directory(string $me, string $q = '', int $limit = 30): array;

    /** @param string[] $ids @return array<string,array> id => person */
    abstract protected function people(array $ids): array;

    // ------------------------------------------------------------------

    protected function q(string $sql, array $p = []): array
    {
        return $this->run(strtr($sql, $this->tables()), $p);
    }

    protected function x(string $sql, array $p = []): void
    {
        $this->exec(strtr($sql, $this->tables()), $p);
    }

    protected static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function singleId(string $a, string $b): string
    {
        $a = trim($a);
        $b = trim($b);

        return strcmp($a, $b) <= 0 ? "{$a};{$b}" : "{$b};{$a}";
    }

    // ---------- conversation list ----------

    public function conversations(string $me): array
    {
        $partners = $this->q(
            "SELECT t.Partner AS PeerID, MAX(t.MessageID) AS LastID FROM (
                SELECT MessageID, CASE WHEN SenderID = ? THEN ReceiverID ELSE SenderID END AS Partner
                FROM {M} WHERE (SenderID = ? OR ReceiverID = ?) AND COALESCE(IsGroup, 'No') <> 'Yes' AND ReceiverID NOT LIKE '%;%'
             ) t GROUP BY t.Partner ORDER BY LastID DESC " . $this->lim(100),
            [$me, $me, $me],
        );

        $out = [];
        if ($partners) {
            $ids  = array_map(static fn ($r) => (int) $r['LastID'], $partners);
            $in   = implode(',', $ids);
            $last = [];
            foreach ($this->q("SELECT MessageID, SenderID, MessageContent, DateSent, imagePath{$this->extraCols()} FROM {M} WHERE MessageID IN ({$in})") as $r) {
                $last[(int) $r['MessageID']] = $r;
            }
            $unread = [];
            foreach ($this->q("SELECT SenderID, COUNT(*) AS n FROM {M} WHERE ReceiverID = ? AND COALESCE(IsRead, 0) = 0 GROUP BY SenderID", [$me]) as $u) {
                $unread[(string) $u['SenderID']] = (int) $u['n'];
            }
            $people = $this->people(array_map(static fn ($r) => (string) $r['PeerID'], $partners));

            foreach ($partners as $p) {
                $peer = (string) $p['PeerID'];
                $m    = $last[(int) $p['LastID']] ?? null;
                if ($m === null) {
                    continue;
                }
                $body = $this->display((string) $m['MessageContent'], self::singleId($me, $peer), [])['text'];
                if ($this->hasAttachment($m)) {
                    $body = $body === '' || $body === '[Image Attachment]' ? ($this->isImageRow($m) ? 'Sent an image' : 'Sent a file') : $body;
                }
                $pp    = $people[$peer] ?? ['id' => $peer, 'name' => $peer, 'username' => '', 'online' => false, 'avatar' => null];
                $out[] = $pp + [
                    'last' => $body, 'mine' => (string) $m['SenderID'] === $me, 'at' => (string) $m['DateSent'],
                    'unread' => $unread[$peer] ?? 0, 'lastId' => (int) $p['LastID'], 'isGroup' => false,
                ];
            }
        }

        // Include Active Groups
        try {
            $likeMe = '%;' . $me . ';%';
            $groupRows = $this->q(
                "SELECT GroupID, GroupName, GroupPic, Admin, Policies, CurrentTheme, CurrentEmoji, IsDisapearingMessage, CreatedAt
                 FROM {G}
                 WHERE (members LIKE ? OR members LIKE ? OR Admin = ?) AND (Policies IS NULL OR Policies NOT LIKE 'Rejected%')
                 ORDER BY UpdatedAt DESC",
                [$likeMe, '%' . $me . '%', $me]
            );
            foreach ($groupRows as $g) {
                $gid = (string) $g['GroupID'];
                $lastMsg = $this->q(
                    "SELECT MessageID, SenderID, MessageContent, DateSent, imagePath{$this->extraCols()}
                     FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? ORDER BY MessageID DESC " . $this->lim(1),
                    [$gid]
                )[0] ?? null;

                $gUnread = (int) ($this->q(
                    "SELECT COUNT(*) AS n FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND SenderID <> ? AND (IsRead = 0 OR IsRead IS NULL)",
                    [$gid, $me]
                )[0]['n'] ?? 0);

                $memList = array_values(array_filter(explode(';', (string) ($g['members'] ?? ''))));
                $memberCount = count($memList);
                $body = '';
                $mine = false;
                $at = (string) ($lastMsg['DateSent'] ?? $g['CreatedAt'] ?? self::now());
                $lastId = (int) ($lastMsg['MessageID'] ?? 0);

                if ($lastMsg) {
                    $body = $this->display((string) $lastMsg['MessageContent'], "group_{$gid}", [])['text'];
                    if ($this->hasAttachment($lastMsg)) {
                        $body = $body === '' || $body === '[Image Attachment]' ? ($this->isImageRow($lastMsg) ? 'Sent an image' : 'Sent a file') : $body;
                    }
                    $mine = (string) $lastMsg['SenderID'] === $me;
                } else {
                    $body = 'Group created';
                }

                $policies = (string) ($g['Policies'] ?? '');
                $pendingApproval = str_contains($policies, 'PendingAdminApproval');

                $out[] = [
                    'id'              => $gid,
                    'groupId'         => $gid,
                    'isGroup'         => true,
                    'name'            => (string) $g['GroupName'],
                    'username'        => 'Group · ' . $memberCount . ' members',
                    'online'          => false,
                    'avatar'          => $g['GroupPic'] ?? null,
                    'admin'           => (string) $g['Admin'],
                    'policies'        => $policies,
                    'pendingApproval' => $pendingApproval,
                    'theme'           => trim((string) ($g['CurrentTheme'] ?? '')) !== '' ? trim((string) $g['CurrentTheme']) : 'default',
                    'emoji'           => trim((string) ($g['CurrentEmoji'] ?? '')) !== '' ? trim((string) $g['CurrentEmoji']) : '👍',
                    'disappearing'    => trim((string) ($g['IsDisapearingMessage'] ?? '')) !== '',
                    'memberCount'     => $memberCount,
                    'last'            => $body,
                    'mine'            => $mine,
                    'at'              => $at,
                    'unread'          => $gUnread,
                    'lastId'          => $lastId,
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', '[ChatStore] Group query error: ' . $e->getMessage());
        }

        return $out;
    }

    public function unreadCount(string $me): int
    {
        $single = (int) ($this->q("SELECT COUNT(*) AS n FROM {M} WHERE ReceiverID = ? AND COALESCE(IsRead, 0) = 0 AND ReceiverID NOT LIKE '%;%'", [$me])[0]['n'] ?? 0);
        $groups = 0;
        try {
            $likeMe = '%;' . $me . ';%';
            $groups = (int) ($this->q(
                "SELECT COUNT(*) AS n FROM {M} m INNER JOIN {G} g ON m.GroupID = g.GroupID
                 WHERE m.IsGroup = 'Yes' AND (g.members LIKE ? OR g.Admin = ?) AND m.SenderID <> ? AND (m.IsRead = 0 OR m.IsRead IS NULL)",
                [$likeMe, $me, $me]
            )[0]['n'] ?? 0);
        } catch (\Throwable $e) {}

        return $single + $groups;
    }

    // ---------- thread ----------

    /** @return array{messages:array,seen:int} */
    public function thread(string $me, string $peer, int $after = 0, int $limit = 60): array
    {
        $limit = max(1, min(200, $limit));
        $rows  = array_reverse($this->q(
            "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
             FROM {M} WHERE ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND MessageID > ?
             ORDER BY MessageID DESC " . $this->lim($limit),
            [$me, $peer, $peer, $me, $after],
        ));

        $cv    = self::singleId($me, $peer);
        $ids   = [];
        foreach ($rows as $r) {
            if (preg_match_all('/@u:(\d+)/i', $this->plain((string) $r['MessageContent'], $cv), $mm)) {
                array_push($ids, ...$mm[1]);
            }
        }
        $names = [];
        foreach ($this->people(array_values(array_unique($ids))) as $id => $pp) {
            $names[(string) $id] = $pp['name'];
        }

        $msgs = [];
        foreach ($rows as $r) {
            $msgs[] = $this->shape($r, $cv, $names);
        }
        $seen = (int) ($this->q("SELECT MAX(MessageID) AS m FROM {M} WHERE SenderID = ? AND ReceiverID = ? AND COALESCE(IsRead, 0) = 1", [$me, $peer])[0]['m'] ?? 0);

        return ['messages' => $this->attachQuotes($msgs, $me, $peer, $cv), 'seen' => $seen];
    }

    /** @return array{messages:array,group:array} */
    public function groupThread(string $me, string $groupId, int $after = 0, int $limit = 60): array
    {
        $limit = max(1, min(200, $limit));
        $rows  = array_reverse($this->q(
            "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
             FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND MessageID > ?
             ORDER BY MessageID DESC " . $this->lim($limit),
            [$groupId, $after],
        ));

        $cv    = "group_{$groupId}";
        $ids   = [];
        foreach ($rows as $r) {
            $ids[] = (string) $r['SenderID'];
            if (preg_match_all('/@u:(\d+)/i', $this->plain((string) $r['MessageContent'], $cv), $mm)) {
                array_push($ids, ...$mm[1]);
            }
        }
        $people = $this->people(array_values(array_unique($ids)));
        $names  = [];
        foreach ($people as $id => $pp) {
            $names[(string) $id] = $pp['name'];
        }

        $msgs = [];
        foreach ($rows as $r) {
            $s = $this->shape($r, $cv, $names);
            $sender = $people[(string) $r['SenderID']] ?? null;
            $s['fromName'] = $sender['name'] ?? ('User ' . $r['SenderID']);
            $s['fromAvatar'] = $sender['avatar'] ?? null;
            $msgs[] = $s;
        }

        // Mark read for this user
        $this->x("UPDATE {M} SET IsRead = 1 WHERE IsGroup = 'Yes' AND GroupID = ? AND SenderID <> ? AND (IsRead = 0 OR IsRead IS NULL)", [$groupId, $me]);

        // Group metadata
        $g = $this->q("SELECT GroupID, GroupName, GroupPic, Admin, Policies, CurrentTheme, CurrentEmoji, IsDisapearingMessage, PinnedMessage, members FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? [];
        $memList = array_values(array_filter(explode(';', (string) ($g['members'] ?? ''))));
        $policies = (string) ($g['Policies'] ?? '');

        $groupInfo = [
            'id'              => (string) ($g['GroupID'] ?? $groupId),
            'groupId'         => (string) ($g['GroupID'] ?? $groupId),
            'name'            => (string) ($g['GroupName'] ?? 'Group ' . $groupId),
            'avatar'          => $g['GroupPic'] ?? null,
            'admin'           => (string) ($g['Admin'] ?? ''),
            'policies'        => $policies,
            'pendingApproval' => str_contains($policies, 'PendingAdminApproval'),
            'theme'           => trim((string) ($g['CurrentTheme'] ?? '')) !== '' ? trim((string) $g['CurrentTheme']) : 'default',
            'emoji'           => trim((string) ($g['CurrentEmoji'] ?? '')) !== '' ? trim((string) $g['CurrentEmoji']) : '👍',
            'disappearing'    => trim((string) ($g['IsDisapearingMessage'] ?? '')) !== '',
            'memberCount'     => count($memList),
            'pinned'          => $this->pinIds($g['PinnedMessage'] ?? ''),
        ];

        return ['messages' => $this->attachQuotesGroup($msgs, $groupId), 'group' => $groupInfo];
    }

    /** One message row in client shape (null when it is not part of this chat). */
    public function message(string $me, string $peer, int $id): ?array
    {
        $r = $this->q(
            "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
             FROM {M} WHERE MessageID = ? AND ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?))",
            [$id, $me, $peer, $peer, $me],
        )[0] ?? null;

        if ($r === null) {
            return null;
        }
        $cv = self::singleId($me, $peer);

        return $this->attachQuotes([$this->shape($r, $cv, [])], $me, $peer, $cv)[0];
    }

    /** Raw row, for ownership/attachment checks. */
    public function rawMessage(string $me, string $peer, int $id): ?array
    {
        return $this->q(
            'SELECT * FROM {M} WHERE MessageID = ? AND ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?))',
            [$id, $me, $peer, $peer, $me],
        )[0] ?? null;
    }

    protected function hasAttachment(array $r): bool
    {
        return trim((string) ($r['imagePath'] ?? '')) !== '' || trim((string) ($r['FileName'] ?? '')) !== '';
    }

    protected function isImageRow(array $r): bool
    {
        return trim((string) ($r['imagePath'] ?? '')) !== '' || str_starts_with((string) ($r['FileType'] ?? ''), 'image/');
    }

    /** @return array<string,mixed> */
    protected function shape(array $r, string $cv, array $names): array
    {
        $raw = $this->plain((string) ($r['MessageContent'] ?? ''), $cv);
        $d   = $this->display((string) ($r['MessageContent'] ?? ''), $cv, $names);
        $replyTo = self::parentOf($r);
        $quote   = null;

        // Parse wire format [REPLY:origMsgId:origSenderId:origSenderName:snippet]cleanText
        if (str_starts_with($raw, '[REPLY:') && ($end = strpos($raw, ']')) !== false) {
            $tag   = substr($raw, 7, $end - 7);
            $parts = explode(':', $tag, 4);
            $origMsgId = (int) ($parts[0] ?? 0);
            if ($origMsgId > 0) {
                $replyTo = $origMsgId;
            }
            if (count($parts) === 4) {
                $quote = [
                    'id'       => $origMsgId,
                    'from'     => $parts[1] ?? '',
                    'fromName' => $parts[2] ?? '',
                    'text'     => $parts[3] ?? '',
                    'gone'     => false,
                ];
            } elseif (count($parts) >= 2) {
                $quote = [
                    'id'       => $origMsgId,
                    'from'     => '',
                    'fromName' => $parts[1] ?? '',
                    'text'     => $parts[2] ?? '',
                    'gone'     => false,
                ];
            }
        }

        $out = [
            'id' => (int) $r['MessageID'], 'from' => (string) $r['SenderID'], 'text' => $d['text'], 'kind' => $d['kind'],
            'at' => (string) $r['DateSent'], 'reactions' => self::reactions($r['EmoteTag'] ?? null),
            'image' => null, 'file' => null, 'replyTo' => $replyTo, 'quote' => $quote,
            'is_triggered' => !empty($r['IsTriggered']),
        ];
        $legacy = trim((string) ($r['imagePath'] ?? ''));
        if ($legacy !== '') {
            $out['image'] = \App\Libraries\Chat\RmsStore::mediaUrl($legacy);
        }
        if (trim((string) ($r['FileName'] ?? '')) !== '') {
            $att = ['name' => (string) $r['FileName'], 'size' => (int) ($r['FileSize'] ?? 0), 'type' => (string) ($r['FileType'] ?? '')];
            if (str_starts_with($att['type'], 'image/')) {
                $out['image'] = '@file'; // controller swaps in the authenticated URL
            } else {
                $out['file'] = $att;
            }
        }

        return $out;
    }

    // ---------- read / send ----------

    public function markRead(string $me, string $peer): void
    {
        $rr = $this->q('SELECT IsReadReceipt FROM {S} WHERE SingleID = ?', [self::singleId($me, $peer)])[0]['IsReadReceipt'] ?? null;
        if ($rr !== null && in_array(strtolower(trim((string) $rr)), ['no', '0', 'false'], true)) {
            return;
        }
        $this->x('UPDATE {M} SET IsRead = 1 WHERE ReceiverID = ? AND SenderID = ? AND COALESCE(IsRead, 0) = 0', [$me, $peer]);
    }

    public function markTriggered(int $msgId): void
    {
        if ($msgId > 0) {
            $this->x('UPDATE {M} SET IsTriggered = 1 WHERE MessageID = ?', [$msgId]);
        }
    }

    public function ensureConversation(string $me, string $peer): string
    {
        $cv = self::singleId($me, $peer);
        if (! $this->q('SELECT SingleID FROM {S} WHERE SingleID = ?', [$cv])) {
            $now = self::now();
            $this->x('INSERT INTO {S} (SingleID, Members, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?)', [$cv, $me . ';' . $peer, $now, $now]);
        }

        return $cv;
    }

    public function isSecure(string $cv): bool
    {
        $sec = $this->q('SELECT SecuredChat FROM {S} WHERE SingleID = ?', [$cv])[0]['SecuredChat'] ?? null;

        return ! ($sec !== null && strcasecmp(trim((string) $sec), 'No') === 0);
    }

    public function isMuted(string $me, string $peer): bool
    {
        $m = $this->q('SELECT IsMute FROM {S} WHERE SingleID = ?', [self::singleId($me, $peer)])[0]['IsMute'] ?? null;

        return $m !== null && in_array(strtolower(trim((string) $m)), ['yes', '1', 'true'], true);
    }

    /**
     * Store a message (encrypted unless the chat has SecuredChat = No). $att is store specific
     * (see RmsStore / NexusStore::attachColumns). Returns the new MessageID.
     */
    public function send(string $me, string $peer, string $text, array $att = [], ?int $replyTo = null): int
    {
        $cv     = $this->ensureConversation($me, $peer);
        $prefix = '';
        $wireText = $text;
        if ($replyTo) {
            // Same threading convention as send-reply.php: ConnectedID = "<parent's chain>,<new id>".
            $parent = $this->rawMessage($me, $peer, $replyTo);
            if ($parent === null) {
                throw new \InvalidArgumentException('The message you are replying to no longer exists.');
            }
            $parts = array_values(array_filter(explode(',', trim((string) ($parent['ConnectedID'] ?? ''))), 'strlen'));
            if (! $parts || end($parts) !== (string) $replyTo) {
                $parts = [(string) $replyTo];
            }
            $prefix = implode(',', $parts) . ',';

            // Build wire format [REPLY:origMsgId:origSenderId:origSenderName:snippet]text
            $parentSenderId = (string) ($parent['SenderID'] ?? '');
            $parentPerson   = $this->person($parentSenderId);
            $parentSenderName = $parentPerson['name'] ?? ($parentSenderId === $me ? 'You' : 'User');
            $parentPlain = $this->display((string) ($parent['MessageContent'] ?? ''), $cv, [])['text'];
            if ($this->hasAttachment($parent)) {
                $snippet = $this->isImageRow($parent) ? 'Photo' : ($parent['FileName'] ?? 'Attachment');
            } else {
                $cleanPlain = preg_replace('/[\r\n\t]+/', ' ', $parentPlain);
                $snippet = mb_strimwidth(str_replace(':', ' ', trim($cleanPlain)), 0, 40, '…');
            }
            $wireText = "[REPLY:{$replyTo}:{$parentSenderId}:{$parentSenderName}:{$snippet}]{$text}";
        }
        $stored = $wireText !== '' && $this->isSecure($cv) ? RmsCrypto::encrypt($wireText, $cv) : $wireText;
        $id     = $this->insertMessage($this->insertSql($att), $this->insertParams($me, $peer, $cv, $stored, $att));
        $this->x('UPDATE {M} SET ConnectedID = ? WHERE MessageID = ?', [$prefix . $id, $id]);
        if ($replyTo) {
            try {
                $this->x('UPDATE {M} SET ReplyToMessageID = ?, ReplyToSenderName = ?, ReplySnippet = ? WHERE MessageID = ?', [$replyTo, $parentSenderName, $snippet, $id]);
            } catch (\Throwable $e) {
                // column update is optional
            }
        }

        return $id;
    }

    /** Parent of a reply: the id before the message's own id in its ConnectedID chain (null if it is not a reply). */
    protected static function parentOf(array $r): ?int
    {
        $parts = array_values(array_filter(explode(',', trim((string) ($r['ConnectedID'] ?? ''))), 'strlen'));
        $n     = count($parts);

        return $n >= 2 && $parts[$n - 1] === (string) $r['MessageID'] && ctype_digit($parts[$n - 2]) ? (int) $parts[$n - 2] : null;
    }

    /** Fill in "quote" (who/what was replied to) for messages that are replies. */
    protected function attachQuotes(array $msgs, string $me, string $peer, string $cv): array
    {
        $ids = array_values(array_unique(array_filter(array_column($msgs, 'replyTo'))));
        if (! $ids) {
            return $msgs;
        }
        $in   = implode(',', array_map('intval', $ids));
        $rows = $this->q(
            "SELECT MessageID, SenderID, MessageContent, imagePath{$this->extraCols()} FROM {M}
             WHERE MessageID IN ({$in}) AND ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?))",
            [$me, $peer, $peer, $me],
        );
        $map = [];
        foreach ($rows as $r) {
            $t  = $this->display((string) $r['MessageContent'], $cv, [])['text'];
            $fn = trim((string) ($r['FileName'] ?? ''));
            $map[(int) $r['MessageID']] = [
                'id' => (int) $r['MessageID'], 'from' => (string) $r['SenderID'], 'text' => mb_strimwidth($t, 0, 140, '…'),
                'image' => $this->isImageRow($r), 'file' => $fn !== '' && ! $this->isImageRow($r) ? $fn : null,
            ];
        }
        foreach ($msgs as &$m) {
            if (! empty($m['replyTo'])) {
                if (isset($map[$m['replyTo']])) {
                    $dbQuote = $map[$m['replyTo']];
                    if (! empty($m['quote']) && ! empty($m['quote']['fromName'])) {
                        $dbQuote['fromName'] = $m['quote']['fromName'];
                    }
                    $m['quote'] = $dbQuote;
                } elseif (empty($m['quote'])) {
                    $m['quote'] = ['id' => $m['replyTo'], 'gone' => true];
                }
            }
        }

        return $msgs;
    }

    protected function attachQuotesGroup(array $msgs, string $groupId): array
    {
        $ids = array_values(array_unique(array_filter(array_column($msgs, 'replyTo'))));
        if (! $ids) {
            return $msgs;
        }
        $in   = implode(',', array_map('intval', $ids));
        $rows = $this->q(
            "SELECT MessageID, SenderID, MessageContent, imagePath{$this->extraCols()} FROM {M}
             WHERE MessageID IN ({$in}) AND IsGroup = 'Yes' AND GroupID = ?",
            [$groupId],
        );
        $map = [];
        $cv  = "group_{$groupId}";
        foreach ($rows as $r) {
            $t  = $this->display((string) $r['MessageContent'], $cv, [])['text'];
            $fn = trim((string) ($r['FileName'] ?? ''));
            $map[(int) $r['MessageID']] = [
                'id' => (int) $r['MessageID'], 'from' => (string) $r['SenderID'], 'text' => mb_strimwidth($t, 0, 140, '…'),
                'image' => $this->isImageRow($r), 'file' => $fn !== '' && ! $this->isImageRow($r) ? $fn : null,
            ];
        }
        foreach ($msgs as &$m) {
            if (! empty($m['replyTo'])) {
                if (isset($map[$m['replyTo']])) {
                    $dbQuote = $map[$m['replyTo']];
                    if (! empty($m['quote']) && ! empty($m['quote']['fromName'])) {
                        $dbQuote['fromName'] = $m['quote']['fromName'];
                    }
                    $m['quote'] = $dbQuote;
                } elseif (empty($m['quote'])) {
                    $m['quote'] = ['id' => $m['replyTo'], 'gone' => true];
                }
            }
        }

        return $msgs;
    }

    public function rawGroupMessage(string $groupId, int $id): ?array
    {
        return $this->q(
            "SELECT * FROM {M} WHERE MessageID = ? AND IsGroup = 'Yes' AND GroupID = ?",
            [$id, $groupId],
        )[0] ?? null;
    }

    public function groupMessage(string $groupId, int $id): ?array
    {
        $r = $this->rawGroupMessage($groupId, $id);
        if ($r === null) {
            return null;
        }
        $cv = "group_{$groupId}";
        $m = $this->shape($r, $cv, []);
        $sender = $this->person((string) $r['SenderID']);
        $m['fromName'] = $sender['name'] ?? ('User ' . $r['SenderID']);
        $m['fromAvatar'] = $sender['avatar'] ?? null;

        return $this->attachQuotesGroup([$m], $groupId)[0];
    }

    public function sendGroup(string $me, string $groupId, string $text, array $att = [], ?int $replyTo = null, bool $isAdmin = false): int
    {
        $g = $this->q("SELECT GroupID, GroupName, Admin, Policies, SecuredChat FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            throw new \InvalidArgumentException('Group not found.');
        }

        // Limit rule: > 50 members requires admin approval
        $policies = (string) ($g['Policies'] ?? '');
        if (str_contains($policies, 'PendingAdminApproval') && ! $isAdmin) {
            throw new \InvalidArgumentException('This group has > 50 members and is pending administrator approval before messages can be sent.');
        }

        $cv = "group_{$groupId}";
        $prefix = '';
        $wireText = $text;
        if ($replyTo) {
            $parent = $this->rawGroupMessage($groupId, $replyTo);
            if ($parent === null) {
                throw new \InvalidArgumentException('The message you are replying to no longer exists.');
            }
            $parts = array_values(array_filter(explode(',', trim((string) ($parent['ConnectedID'] ?? ''))), 'strlen'));
            if (! $parts || end($parts) !== (string) $replyTo) {
                $parts = [(string) $replyTo];
            }
            $prefix = implode(',', $parts) . ',';

            $parentSenderId = (string) ($parent['SenderID'] ?? '');
            $parentPerson   = $this->person($parentSenderId);
            $parentSenderName = $parentPerson['name'] ?? ($parentSenderId === $me ? 'You' : 'User ' . $parentSenderId);
            $parentPlain = $this->display((string) ($parent['MessageContent'] ?? ''), $cv, [])['text'];
            if ($this->hasAttachment($parent)) {
                $snippet = $this->isImageRow($parent) ? 'Photo' : ($parent['FileName'] ?? 'Attachment');
            } else {
                $cleanPlain = preg_replace('/[\r\n\t]+/', ' ', $parentPlain);
                $snippet = mb_strimwidth(str_replace(':', ' ', trim($cleanPlain)), 0, 40, '…');
            }
            $wireText = "[REPLY:{$replyTo}:{$parentSenderId}:{$parentSenderName}:{$snippet}]{$text}";
        }

        $stored = $wireText !== '' && self::yes($g['SecuredChat'] ?? null, true) ? RmsCrypto::encrypt($wireText, $cv) : $wireText;
        $id     = $this->insertMessage($this->insertGroupSql($att), $this->insertGroupParams($me, $groupId, $stored, $att));
        $this->x('UPDATE {M} SET ConnectedID = ? WHERE MessageID = ?', [$prefix . $id, $id]);
        if ($replyTo) {
            try {
                $this->x('UPDATE {M} SET ReplyToMessageID = ?, ReplyToSenderName = ?, ReplySnippet = ? WHERE MessageID = ?', [$replyTo, $parentSenderName, $snippet, $id]);
            } catch (\Throwable $e) {}
        }

        return $id;
    }

    public function groupDetails(string $me, string $groupId): array
    {
        $g = $this->q("SELECT * FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            throw new \InvalidArgumentException('Group not found.');
        }
        $memIds = array_values(array_filter(explode(';', (string) ($g['members'] ?? ''))));
        $people = $this->people($memIds);
        $roster = [];
        $creator = (string) ($g['Admin'] ?? '');
        foreach ($memIds as $uid) {
            $p = $people[$uid] ?? ['id' => $uid, 'name' => 'User ' . $uid, 'username' => '', 'online' => false, 'avatar' => null];
            $p['role'] = ($uid === $creator) ? 'Admin' : 'Member';
            $roster[] = $p;
        }

        $policies = (string) ($g['Policies'] ?? '');
        $dis = (string) ($g['IsDisapearingMessage'] ?? '');

        return [
            'id'              => (string) $g['GroupID'],
            'groupId'         => (string) $g['GroupID'],
            'name'            => (string) $g['GroupName'],
            'avatar'          => $g['GroupPic'] ?? null,
            'admin'           => $creator,
            'policies'        => $policies,
            'pendingApproval' => str_contains($policies, 'PendingAdminApproval'),
            'theme'           => trim((string) ($g['CurrentTheme'] ?? '')) !== '' ? trim((string) $g['CurrentTheme']) : 'default',
            'emoji'           => trim((string) ($g['CurrentEmoji'] ?? '')) !== '' ? trim((string) $g['CurrentEmoji']) : '👍',
            'disappearing'    => trim($dis) === '' ? 'off' : 'on',
            'members'         => $roster,
            'memberCount'     => count($roster),
            'pinned'          => $this->pinIds($g['PinnedMessage'] ?? ''),
        ];
    }

    public function approveGroup(string $groupId, bool $approve = true): void
    {
        $policy = $approve ? 'Active;' : 'Rejected;';
        $this->x("UPDATE {G} SET Policies = ?, UpdatedAt = ? WHERE GroupID = ?", [$policy, self::now(), $groupId]);
    }

    public function setGroupSetting(string $groupId, string $key, string $value): void
    {
        $value = trim($value);
        if ($key === 'CurrentEmoji' && mb_strlen($value) > 8) {
            throw new \InvalidArgumentException('Invalid emoji.');
        }
        if ($key === 'CurrentTheme' && mb_strlen($value) > 50) {
            throw new \InvalidArgumentException('Invalid theme.');
        }
        if (! in_array($key, ['CurrentEmoji', 'CurrentTheme', 'GroupName'], true)) {
            throw new \InvalidArgumentException('Unknown setting.');
        }
        $this->x("UPDATE {G} SET {$key} = ?, UpdatedAt = ? WHERE GroupID = ?", [$value, self::now(), $groupId]);
    }

    public function setDisappearingMode(string $me, string $peer, string $mode): array
    {
        $cv = $this->ensureConversation($me, $peer);
        $curr = (string) ($this->q('SELECT IsDisapearingMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['IsDisapearingMessage'] ?? '');
        $newVal = '';
        if ($mode === 'off') {
            $newVal = '';
        } elseif ($mode === 'approve') {
            $newVal = "{$me}-Yes;{$peer}-Yes;";
        } elseif ($mode === 'on' || $mode === 'toggle') {
            if ($curr === '' || $curr === null) {
                // Request to turn on (peer approval required)
                $newVal = "{$me}-Yes;{$peer}-ForApproval;";
            } elseif (str_contains($curr, 'ForApproval')) {
                // Already waiting: activate
                $newVal = "{$me}-Yes;{$peer}-Yes;";
            } else {
                // Currently ON: toggle turns it OFF
                $newVal = '';
            }
        }
        $this->x('UPDATE {S} SET IsDisapearingMessage = ?, UpdatedAt = ? WHERE SingleID = ?', [$newVal, self::now(), $cv]);

        return [
            'raw'    => $newVal,
            'status' => $newVal === '' || $newVal === null ? 'off' : (str_contains($newVal, 'ForApproval') ? 'pending' : 'on'),
        ];
    }

    public function setGroupDisappearingMode(string $groupId, string $mode): array
    {
        $curr = (string) ($this->q('SELECT IsDisapearingMessage FROM {G} WHERE GroupID = ?', [$groupId])[0]['IsDisapearingMessage'] ?? '');
        $newVal = ($mode === 'off' || ($mode === 'toggle' && $curr !== '')) ? '' : 'ON';
        $this->x('UPDATE {G} SET IsDisapearingMessage = ?, UpdatedAt = ? WHERE GroupID = ?', [$newVal, self::now(), $groupId]);

        return [
            'raw'    => $newVal,
            'status' => $newVal === '' ? 'off' : 'on',
        ];
    }

    abstract protected function insertSql(array $att): string;

    abstract protected function insertParams(string $me, string $peer, string $cv, string $stored, array $att): array;

    // ---------- reactions / edit / bump / forward ----------

    /** Toggle or switch this user's reaction (same rules as the Android app). @return array reactions */
    public function react(string $me, string $peer, int $id, string $emoji, string $myName): array
    {
        $row = $this->rawMessage($me, $peer, $id);
        if ($row === null) {
            throw new \InvalidArgumentException('Message not found.');
        }
        $emoji = trim($emoji);
        if (mb_strlen($emoji) > 16) {
            throw new \InvalidArgumentException('Invalid emoji.');
        }
        $list = self::reactions($row['EmoteTag'] ?? null);
        $mine = null;
        foreach ($list as $i => $r) {
            if (strcasecmp((string) $r['u'], $me) === 0) {
                $mine = $i;
                break;
            }
        }
        if ($mine !== null) {
            if ($emoji === '' || $list[$mine]['e'] === $emoji) {
                array_splice($list, $mine, 1);
            } else {
                $list[$mine] = ['u' => $me, 'n' => $myName, 'e' => $emoji, 't' => (int) (microtime(true) * 1000)];
            }
        } elseif ($emoji !== '') {
            $list[] = ['u' => $me, 'n' => $myName, 'e' => $emoji, 't' => (int) (microtime(true) * 1000)];
        }
        $this->x('UPDATE {M} SET EmoteTag = ? WHERE MessageID = ?', [json_encode($list, JSON_UNESCAPED_UNICODE), $id]);

        return $list;
    }

    /** Edit own text message within a minute of sending. */
    public function edit(string $me, string $peer, int $id, string $text): array
    {
        $row = $this->rawMessage($me, $peer, $id);
        if ($row === null || (string) $row['SenderID'] !== $me) {
            throw new \InvalidArgumentException('You can only edit your own messages.');
        }
        if (time() - strtotime((string) $row['DateSent']) > self::EDIT_WINDOW_S) {
            throw new \InvalidArgumentException('Messages can only be edited within a minute of sending.');
        }
        if ($this->hasAttachment($row) && trim($text) === '') {
            throw new \InvalidArgumentException('Message text cannot be empty.');
        }
        $cv = self::singleId($me, $peer);
        $this->x('UPDATE {M} SET MessageContent = ? WHERE MessageID = ?', [$this->isSecure($cv) ? RmsCrypto::encrypt($text, $cv) : $text, $id]);

        return $this->message($me, $peer, $id) ?? [];
    }

    /** Re-post a message to the end of the chat as "[BUMP:senderId:senderName]text". Returns new id. */
    public function bump(string $me, string $peer, int $id, string $myName, string $peerName): int
    {
        $row = $this->rawMessage($me, $peer, $id);
        if ($row === null) {
            throw new \InvalidArgumentException('Message not found.');
        }
        $cv   = self::singleId($me, $peer);
        $text = $this->plain((string) $row['MessageContent'], $cv);
        if (str_starts_with($text, '[BUMP:') && ($e = strpos($text, ']')) !== false) {
            $text = trim(substr($text, $e + 1));
        }
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Only text messages can be bumped on the web.');
        }
        $origId   = (string) $row['SenderID'];
        $origName = $origId === $me ? $myName : $peerName;

        return $this->send($me, $peer, "[BUMP:{$origId}:{$origName}]{$text}");
    }

    public function plainText(string $me, string $peer, int $id): ?string
    {
        $row = $this->rawMessage($me, $peer, $id);

        return $row === null ? null : $this->plain((string) $row['MessageContent'], self::singleId($me, $peer));
    }

    // ---------- details / settings ----------

    protected static function yes(?string $v, bool $default): bool
    {
        if ($v === null || trim($v) === '') {
            return $default;
        }

        return ! in_array(strtolower(trim($v)), ['no', '0', 'false'], true);
    }

    /** @return array<string,string> member id => nickname */
    protected function nickMap(string $cv): array
    {
        $map = [];
        foreach (explode(';', (string) ($this->q('SELECT Nickname FROM {S} WHERE SingleID = ?', [$cv])[0]['Nickname'] ?? '')) as $e) {
            if (str_contains($e, '-')) {
                [$id, $n] = explode('-', $e, 2);
                $map[trim($id)] = trim($n);
            }
        }

        return $map;
    }

    /** @return array{me:string,peer:string} */
    public function nicknames(string $me, string $peer): array
    {
        $nick = $this->nickMap(self::singleId($me, $peer));

        return ['me' => $nick[$me] ?? '', 'peer' => $nick[$peer] ?? ''];
    }

    public function details(string $me, string $peer): array
    {
        $cv  = self::singleId($me, $peer);
        $r   = $this->q('SELECT SecuredChat, IsMute, IsReadReceipt, CurrentEmoji, CurrentTheme, IsDisapearingMessage, PinnedMessage FROM {S} WHERE SingleID = ?', [$cv])[0] ?? [];
        $nick = $this->nickMap($cv);
        $dis = (string) ($r['IsDisapearingMessage'] ?? '');

        return [
            'secured'      => self::yes($r['SecuredChat'] ?? null, true),
            'readReceipts' => self::yes($r['IsReadReceipt'] ?? null, true),
            'emoji'        => trim((string) ($r['CurrentEmoji'] ?? '')) !== '' ? trim((string) $r['CurrentEmoji']) : '👍',
            'theme'        => trim((string) ($r['CurrentTheme'] ?? '')) !== '' ? trim((string) $r['CurrentTheme']) : 'default',
            'disappearing' => trim($dis) === '' ? 'off' : (str_contains($dis, 'ForApproval') ? 'pending' : 'on'),
            'nicknames'    => ['me' => $nick[$me] ?? '', 'peer' => $nick[$peer] ?? ''],
            'pinned'       => $this->pinIds($r['PinnedMessage'] ?? ''),
        ];
    }

    public function setSetting(string $me, string $peer, string $key, string $value): void
    {
        $cv    = $this->ensureConversation($me, $peer);
        $value = trim($value);
        switch ($key) {
            case 'SecuredChat':
            case 'IsReadReceipt':
                if (! in_array($value, ['Yes', 'No'], true)) {
                    throw new \InvalidArgumentException('Invalid value.');
                }
                break;
            case 'CurrentEmoji':
                if ($value === '' || mb_strlen($value) > 8) {
                    throw new \InvalidArgumentException('Invalid emoji.');
                }
                break;
            case 'CurrentTheme':
                if ($value === '' || mb_strlen($value) > 50) {
                    throw new \InvalidArgumentException('Invalid theme.');
                }
                break;
            default:
                throw new \InvalidArgumentException('Unknown setting.');
        }
        $this->x("UPDATE {S} SET {$key} = ?, UpdatedAt = ? WHERE SingleID = ?", [$value, self::now(), $cv]);
    }

    /** Nickname for member $target ("id-nick;" format, as in the app). */
    public function setNickname(string $me, string $peer, string $target, string $nick): void
    {
        if ($target !== $me && $target !== $peer) {
            throw new \InvalidArgumentException('Invalid member.');
        }
        $nick = trim(preg_replace('/[;\r\n]+/', ' ', $nick));
        if (mb_strlen($nick) > 40) {
            throw new \InvalidArgumentException('Nickname is too long.');
        }
        $cv  = $this->ensureConversation($me, $peer);
        $map = $this->nickMap($cv);
        if ($nick === '') {
            unset($map[$target]);
        } else {
            $map[$target] = $nick;
        }
        $out = '';
        foreach ($map as $id => $n) {
            $out .= "{$id}-{$n};";
        }
        $this->x('UPDATE {S} SET Nickname = ?, UpdatedAt = ? WHERE SingleID = ?', [$out, self::now(), $cv]);
    }

    // ---------- pins / search / media ----------

    /** @return int[] */
    protected function pinIds(?string $raw): array
    {
        return array_values(array_map('intval', array_filter(array_map('trim', explode(';', (string) $raw)), 'ctype_digit')));
    }

    public function pinned(string $me, string $peer): array
    {
        $cv  = self::singleId($me, $peer);
        $ids = $this->pinIds($this->q('SELECT PinnedMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['PinnedMessage'] ?? '');
        if (! $ids) {
            return [];
        }
        $in   = implode(',', $ids);
        $rows = $this->q(
            "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
             FROM {M} WHERE MessageID IN ({$in}) AND ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) ORDER BY MessageID DESC",
            [$me, $peer, $peer, $me],
        );

        return $this->attachQuotes(array_map(fn ($r) => $this->shape($r, $cv, []), $rows), $me, $peer, $cv);
    }

    public function setPinned(string $me, string $peer, int $id, bool $pin): array
    {
        $cv = $this->ensureConversation($me, $peer);
        if ($this->rawMessage($me, $peer, $id) === null) {
            throw new \InvalidArgumentException('Message not found.');
        }
        $ids = $this->pinIds($this->q('SELECT PinnedMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['PinnedMessage'] ?? '');
        $ids = array_values(array_diff($ids, [$id]));
        if ($pin) {
            if (count($ids) >= self::PIN_LIMIT) {
                throw new \InvalidArgumentException('You can pin up to ' . self::PIN_LIMIT . ' messages. Unpin one first.');
            }
            $ids[] = $id;
        }
        $this->x('UPDATE {S} SET PinnedMessage = ?, UpdatedAt = ? WHERE SingleID = ?', [$ids ? implode(';', $ids) . ';' : '', self::now(), $cv]);

        return $ids;
    }

    /** Messages are encrypted, so scan the latest 600 and match the plaintext. */
    public function searchChat(string $me, string $peer, string $q): array
    {
        $cv   = self::singleId($me, $peer);
        $rows = $this->q(
            'SELECT MessageID, SenderID, MessageContent, DateSent FROM {M}
             WHERE (SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?) ORDER BY MessageID DESC ' . $this->lim(600),
            [$me, $peer, $peer, $me],
        );
        $out = [];
        foreach ($rows as $r) {
            $t = $this->display((string) $r['MessageContent'], $cv, [])['text'];
            if ($t !== '' && mb_stripos($t, $q) !== false) {
                $out[] = ['id' => (int) $r['MessageID'], 'from' => (string) $r['SenderID'], 'at' => (string) $r['DateSent'], 'text' => $t];
                if (count($out) >= 30) {
                    break;
                }
            }
        }

        return $out;
    }

    /** Shared images (or non-image files when $files). Newest first; page with $before. */
    public function media(string $me, string $peer, int $before = 0, bool $files = false, int $limit = 24): array
    {
        $where = $files ? $this->filesWhere() : $this->imageWhere();
        if ($where === null) {
            return [];
        }
        $rows = $this->q(
            "SELECT MessageID, SenderID, imagePath, DateSent{$this->extraCols()} FROM {M}
             WHERE ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND {$where} AND (? = 0 OR MessageID < ?)
             ORDER BY MessageID DESC " . $this->lim(max(1, min(60, $limit))),
            [$me, $peer, $peer, $me, $before, $before],
        );

        return array_map(function ($r) {
            $s = $this->shape($r + ['MessageContent' => '', 'EmoteTag' => null], '', []);

            return ['id' => $s['id'], 'from' => $s['from'], 'at' => $s['at'], 'image' => $s['image'], 'file' => $s['file']];
        }, $rows);
    }

    // ---------- text helpers ----------

    protected function plain(string $content, string $cv): string
    {
        return (string) RmsCrypto::decrypt($content, $cv);
    }

    /** @return array{text:string,kind:string} */
    protected function display(string $content, string $cv, array $names): array
    {
        $t = $this->plain($content, $cv);
        if (str_starts_with($t, '[ENC]')) {
            return ['text' => 'Encrypted message', 'kind' => 'msg'];
        }
        $t = trim((string) preg_replace('~(?i)/rms~', '', $t));
        $t = (string) preg_replace_callback('/@u:(\d+)/i', static fn ($m) => isset($names[$m[1]]) ? '@' . $names[$m[1]] : $m[0], $t);

        if (str_starts_with($t, '[CALL_MISSED]')) {
            return ['text' => 'Missed call · ' . trim(substr($t, 13)), 'kind' => 'call'];
        }
        if (str_starts_with($t, '[CALL_ENDED]')) {
            return ['text' => 'Call ended · ' . trim(substr($t, 12)), 'kind' => 'call'];
        }
        if (str_starts_with($t, '[BUMP:') && ($e = strpos($t, ']')) !== false) {
            $rest = trim(substr($t, $e + 1));
            $t    = $rest === '' ? 'Bumped a message' : 'Bumped: ' . $rest;
        }
        if (str_starts_with($t, '[REPLY:') && ($e = strpos($t, ']')) !== false) {
            $t = trim(substr($t, $e + 1));
        }

        return ['text' => $t, 'kind' => 'msg'];
    }

    /** EmoteTag: JSON [{"u","n","e","t"}...] (current format) or a single legacy emoji. */
    public static function reactions(?string $tag): array
    {
        $tag = trim((string) $tag);
        if ($tag === '') {
            return [];
        }
        if ($tag[0] !== '[') {
            return [['u' => '', 'n' => '', 'e' => $tag, 't' => 0]];
        }
        $list = json_decode($tag, true);
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($r) => [
            'u' => (string) ($r['u'] ?? ''), 'n' => (string) ($r['n'] ?? ''), 'e' => (string) ($r['e'] ?? ''), 't' => (int) ($r['t'] ?? 0),
        ], $list), static fn ($r) => $r['e'] !== ''));
    }
}
