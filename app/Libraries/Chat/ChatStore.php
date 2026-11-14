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

    protected function supportsNexusCols(): bool
    {
        return false;
    }

    protected function encryptForStore(string $plain, string $cv): string
    {
        return RmsCrypto::encrypt($plain, $cv);
    }

    protected function decryptFromStore(?string $text, string $cv): string
    {
        return (string) RmsCrypto::decrypt($text, $cv);
    }

    protected function memberMatchClause(string $col = 'members'): string
    {
        return "CONCAT(';', COALESCE({$col}, ''), ';') LIKE ?";
    }

    public function touchPresence(string $me): void
    {
        // Overridden in NexusStore
    }

    /** @return array{sql:string,params:array} */
    protected function visibleSql(string $me, string $alias = ''): array
    {
        if (! $this->supportsNexusCols()) {
            return ['sql' => '1=1', 'params' => []];
        }
        $p = $alias !== '' ? $alias . '.' : '';

        return [
            'sql'    => "({$p}ExpiresAt IS NULL OR {$p}ExpiresAt > ?) AND ({$p}DeletedFor IS NULL OR {$p}DeletedFor NOT LIKE ?)",
            'params' => [self::now(), '%;' . $me . ';%'],
        ];
    }

    protected static function normalizeDisappearing(?string $raw): string
    {
        $v = strtolower(trim((string) $raw));
        if ($v === '' || $v === 'off' || $v === 'no' || $v === '0' || $v === 'false') {
            return 'off';
        }
        if (str_contains($v, 'forapproval') || str_contains($v, '-no')) {
            return 'off';
        }
        if ($v === '1m' || str_contains($v, '1m')) {
            return '1m';
        }
        if ($v === '1h' || str_contains($v, '1h')) {
            return '1h';
        }
        if ($v === '7d' || str_contains($v, '7d')) {
            return '7d';
        }
        if ($v === '24h' || $v === 'yes' || $v === 'on' || $v === '1' || $v === 'true' || str_contains($v, '-yes')) {
            return '24h';
        }

        return 'off';
    }

    protected static function disappearingTtl(?string $raw): ?int
    {
        $mode = self::normalizeDisappearing($raw);
        if ($mode === '1m') {
            return 60;
        }
        if ($mode === '1h') {
            return 3600;
        }
        if ($mode === '7d') {
            return 604800;
        }
        if ($mode === '24h') {
            return 86400;
        }

        return null;
    }

    protected static function disappearingLabel(string $mode): string
    {
        return match ($mode) {
            '1m'  => '1 minute',
            '1h'  => '1 hour',
            '7d'  => '7 days',
            '24h' => '24 hours',
            default => 'Off',
        };
    }

    protected function purgeExpired(): void
    {
        if (! $this->supportsNexusCols()) {
            return;
        }
        try {
            $now = self::now();
            $expired = $this->q("SELECT MessageID, FilePath FROM {M} WHERE ExpiresAt IS NOT NULL AND ExpiresAt <= ? " . $this->lim(50), [$now]);
            if (! $expired) {
                return;
            }
            $ids = [];
            foreach ($expired as $r) {
                $ids[] = (int) $r['MessageID'];
                $rel   = trim((string) ($r['FilePath'] ?? ''));
                if ($rel !== '' && $this instanceof NexusStore) {
                    $abs = NexusStore::absolutePath($rel);
                    if (is_file($abs)) {
                        @unlink($abs);
                    }
                }
            }
            $in = implode(',', $ids);
            $this->x("DELETE FROM {M} WHERE MessageID IN ({$in})");
        } catch (\Throwable $e) {
            // non-blocking cleanup
        }
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
        $this->purgeExpired();
        $vis = $this->visibleSql($me);
        $partners = $this->q(
            "SELECT t.Partner AS PeerID, MAX(t.MessageID) AS LastID FROM (
                SELECT MessageID, CASE WHEN SenderID = ? THEN ReceiverID ELSE SenderID END AS Partner
                FROM {M} WHERE (SenderID = ? OR ReceiverID = ?) AND COALESCE(IsGroup, 'No') <> 'Yes' AND ReceiverID NOT LIKE '%;%' AND {$vis['sql']}
             ) t GROUP BY t.Partner ORDER BY LastID DESC " . $this->lim(100),
            array_merge([$me, $me, $me], $vis['params']),
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
            foreach ($this->q("SELECT SenderID, COUNT(*) AS n FROM {M} WHERE ReceiverID = ? AND COALESCE(IsRead, 0) = 0 AND COALESCE(IsGroup, 'No') <> 'Yes' AND {$vis['sql']} GROUP BY SenderID", array_merge([$me], $vis['params'])) as $u) {
                $unread[(string) $u['SenderID']] = (int) $u['n'];
            }
            $mutedMap = [];
            $disMap   = [];
            try {
                foreach ($this->q("SELECT SingleID, IsMute, IsDisapearingMessage FROM {S} WHERE " . $this->memberMatchClause('Members'), ['%;' . $me . ';%']) as $sr) {
                    $sid = (string) $sr['SingleID'];
                    $mutedMap[$sid] = in_array(strtolower(trim((string) ($sr['IsMute'] ?? ''))), ['yes', '1', 'true'], true);
                    $disMap[$sid]   = self::normalizeDisappearing($sr['IsDisapearingMessage'] ?? '');
                }
            } catch (\Throwable $e) {
            }
            $people = $this->people(array_map(static fn ($r) => (string) $r['PeerID'], $partners));

            foreach ($partners as $p) {
                $peer = (string) $p['PeerID'];
                $m    = $last[(int) $p['LastID']] ?? null;
                if ($m === null) {
                    continue;
                }
                $cv = self::singleId($me, $peer);
                if (! empty($m['IsUnsent'])) {
                    $body = (string) $m['SenderID'] === $me ? 'You unsent a message' : 'Message unsent';
                } else {
                    $body = $this->display((string) $m['MessageContent'], $cv, [])['text'];
                    if ($this->hasAttachment($m)) {
                        $body = $body === '' || $body === '[Image Attachment]' ? ($this->isImageRow($m) ? 'Sent an image' : 'Sent a file') : $body;
                    }
                }
                $pp    = $people[$peer] ?? ['id' => $peer, 'name' => $peer, 'username' => '', 'online' => false, 'avatar' => null];
                $out[] = $pp + [
                    'last' => $body, 'mine' => (string) $m['SenderID'] === $me, 'at' => (string) $m['DateSent'],
                    'unread' => $unread[$peer] ?? 0, 'lastId' => (int) $p['LastID'], 'isGroup' => false,
                    'muted' => $mutedMap[$cv] ?? false, 'disappearing' => $disMap[$cv] ?? 'off',
                ];
            }
        }

        // Include Active Groups (exact ;id; match — never substring match)
        try {
            $likeMe = '%;' . $me . ';%';
            $groupRows = $this->q(
                "SELECT GroupID, GroupName, GroupPic, Admin, Policies, CurrentTheme, CurrentEmoji, IsDisapearingMessage, CreatedAt, UpdatedAt, members
                 FROM {G}
                 WHERE (" . $this->memberMatchClause('members') . " OR Admin = ?) AND (Policies IS NULL OR Policies NOT LIKE 'Rejected%')
                 ORDER BY UpdatedAt DESC",
                [$likeMe, $me]
            );
            foreach ($groupRows as $g) {
                $gid = (string) $g['GroupID'];
                $lastMsg = $this->q(
                    "SELECT MessageID, SenderID, MessageContent, DateSent, imagePath{$this->extraCols()}
                     FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND {$vis['sql']} ORDER BY MessageID DESC " . $this->lim(1),
                    array_merge([$gid], $vis['params'])
                )[0] ?? null;

                $gMuted = false;
                if ($this->supportsNexusCols()) {
                    $gmRow = $this->q("SELECT last_read_message_id, is_muted FROM tbl_group_members WHERE group_id = ? AND user_id = ?", [$gid, $me])[0] ?? null;
                    $lastReadId = (int) ($gmRow['last_read_message_id'] ?? 0);
                    $gMuted = ! empty($gmRow['is_muted']);
                    $gUnread = (int) ($this->q(
                        "SELECT COUNT(*) AS n FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND SenderID <> ? AND MessageID > ? AND {$vis['sql']}",
                        array_merge([$gid, $me, $lastReadId], $vis['params'])
                    )[0]['n'] ?? 0);
                } else {
                    $gUnread = (int) ($this->q(
                        "SELECT COUNT(*) AS n FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND SenderID <> ? AND (IsRead = 0 OR IsRead IS NULL)",
                        [$gid, $me]
                    )[0]['n'] ?? 0);
                }

                $memList = array_values(array_filter(explode(';', (string) ($g['members'] ?? ''))));
                $memberCount = count($memList);
                $body = '';
                $mine = false;
                $at = (string) ($lastMsg['DateSent'] ?? $g['UpdatedAt'] ?? $g['CreatedAt'] ?? self::now());
                $lastId = (int) ($lastMsg['MessageID'] ?? 0);

                if ($lastMsg) {
                    if (! empty($lastMsg['IsUnsent'])) {
                        $body = (string) $lastMsg['SenderID'] === $me ? 'You unsent a message' : 'Message unsent';
                    } else {
                        $body = $this->display((string) $lastMsg['MessageContent'], "group_{$gid}", [])['text'];
                        if ($this->hasAttachment($lastMsg)) {
                            $body = $body === '' || $body === '[Image Attachment]' ? ($this->isImageRow($lastMsg) ? 'Sent an image' : 'Sent a file') : $body;
                        }
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
                    'avatar'          => $this->resolveGroupAvatar($g['GroupPic'] ?? null, $gid),
                    'admin'           => (string) $g['Admin'],
                    'policies'        => $policies,
                    'pendingApproval' => $pendingApproval,
                    'theme'           => trim((string) ($g['CurrentTheme'] ?? '')) !== '' ? trim((string) $g['CurrentTheme']) : 'default',
                    'emoji'           => trim((string) ($g['CurrentEmoji'] ?? '')) !== '' ? trim((string) $g['CurrentEmoji']) : '👍',
                    'disappearing'    => self::normalizeDisappearing($g['IsDisapearingMessage'] ?? ''),
                    'memberCount'     => $memberCount,
                    'muted'           => $gMuted,
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
        $vis = $this->visibleSql($me);
        $single = (int) ($this->q("SELECT COUNT(*) AS n FROM {M} WHERE ReceiverID = ? AND COALESCE(IsRead, 0) = 0 AND COALESCE(IsGroup, 'No') <> 'Yes' AND ReceiverID NOT LIKE '%;%' AND {$vis['sql']}", array_merge([$me], $vis['params']))[0]['n'] ?? 0);
        $groups = 0;
        try {
            $likeMe = '%;' . $me . ';%';
            if ($this->supportsNexusCols()) {
                $vM = $this->visibleSql($me, 'm');
                $groups = (int) ($this->q(
                    "SELECT COUNT(*) AS n FROM {M} m
                     INNER JOIN {G} g ON m.GroupID = g.GroupID
                     LEFT JOIN tbl_group_members gm ON gm.group_id = g.GroupID AND gm.user_id = ?
                     WHERE m.IsGroup = 'Yes'
                       AND (" . $this->memberMatchClause('g.members') . " OR g.Admin = ?)
                       AND (g.Policies IS NULL OR g.Policies NOT LIKE 'Rejected%')
                       AND m.SenderID <> ?
                       AND m.MessageID > COALESCE(gm.last_read_message_id, 0)
                       AND COALESCE(gm.is_muted, 0) = 0
                       AND {$vM['sql']}",
                    array_merge([$me, $likeMe, $me, $me], $vM['params'])
                )[0]['n'] ?? 0);
            } else {
                $groups = (int) ($this->q(
                    "SELECT COUNT(*) AS n FROM {M} m INNER JOIN {G} g ON m.GroupID = g.GroupID
                     WHERE m.IsGroup = 'Yes' AND (" . $this->memberMatchClause('g.members') . " OR g.Admin = ?) AND (g.Policies IS NULL OR g.Policies NOT LIKE 'Rejected%') AND m.SenderID <> ? AND (m.IsRead = 0 OR m.IsRead IS NULL)",
                    [$likeMe, $me, $me]
                )[0]['n'] ?? 0);
            }
        } catch (\Throwable $e) {
        }

        return $single + $groups;
    }

    // ---------- thread ----------

    /** @return array{messages:array,seen:int,hasMore:bool,disappearing:string} */
    public function thread(string $me, string $peer, int $after = 0, int $limit = 60, int $before = 0): array
    {
        $this->purgeExpired();
        $limit = max(1, min(200, $limit));
        $vis   = $this->visibleSql($me);
        if ($before > 0) {
            $rows = array_reverse($this->q(
                "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
                 FROM {M} WHERE ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND COALESCE(IsGroup, 'No') <> 'Yes' AND MessageID < ? AND {$vis['sql']}
                 ORDER BY MessageID DESC " . $this->lim($limit),
                array_merge([$me, $peer, $peer, $me, $before], $vis['params']),
            ));
        } else {
            $rows = array_reverse($this->q(
                "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
                 FROM {M} WHERE ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND COALESCE(IsGroup, 'No') <> 'Yes' AND MessageID > ? AND {$vis['sql']}
                 ORDER BY MessageID DESC " . $this->lim($limit),
                array_merge([$me, $peer, $peer, $me, $after], $vis['params']),
            ));
        }

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
        $seen   = (int) ($this->q("SELECT MAX(MessageID) AS m FROM {M} WHERE SenderID = ? AND ReceiverID = ? AND COALESCE(IsRead, 0) = 1 AND COALESCE(IsGroup, 'No') <> 'Yes'", [$me, $peer])[0]['m'] ?? 0);
        $disRaw = $this->q('SELECT IsDisapearingMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['IsDisapearingMessage'] ?? '';

        return [
            'messages'     => $this->attachQuotes($msgs, $me, $peer, $cv),
            'seen'         => $seen,
            'hasMore'      => count($rows) >= $limit,
            'disappearing' => self::normalizeDisappearing($disRaw),
        ];
    }

    public function isGroupMember(string $me, string $groupId): bool
    {
        $likeMe = '%;' . $me . ';%';
        $row = $this->q(
            "SELECT GroupID, Policies FROM {G} WHERE GroupID = ? AND (" . $this->memberMatchClause('members') . " OR Admin = ?)",
            [$groupId, $likeMe, $me]
        )[0] ?? null;

        return $row !== null && ! str_starts_with((string) ($row['Policies'] ?? ''), 'Rejected');
    }

    public function isGroupAdmin(string $me, string $groupId): bool
    {
        $g = $this->q("SELECT Admin FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if ($g && (string) ($g['Admin'] ?? '') === $me) {
            return true;
        }
        if ($this->supportsNexusCols()) {
            $gm = $this->q("SELECT role FROM tbl_group_members WHERE group_id = ? AND user_id = ?", [$groupId, $me])[0] ?? null;
            if ($gm && strtolower((string) ($gm['role'] ?? '')) === 'admin') {
                return true;
            }
        }

        return false;
    }

    /** @return array{messages:array,group:array,hasMore:bool} */
    public function groupThread(string $me, string $groupId, int $after = 0, int $limit = 60, int $before = 0): array
    {
        $this->purgeExpired();
        $limit = max(1, min(200, $limit));
        $vis   = $this->visibleSql($me);
        if ($before > 0) {
            $rows = array_reverse($this->q(
                "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
                 FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND MessageID < ? AND {$vis['sql']}
                 ORDER BY MessageID DESC " . $this->lim($limit),
                array_merge([$groupId, $before], $vis['params']),
            ));
        } else {
            $rows = array_reverse($this->q(
                "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
                 FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ? AND MessageID > ? AND {$vis['sql']}
                 ORDER BY MessageID DESC " . $this->lim($limit),
                array_merge([$groupId, $after], $vis['params']),
            ));
        }

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
        $maxMsgId = 0;
        foreach ($rows as $r) {
            $maxMsgId = max($maxMsgId, (int) $r['MessageID']);
            $s = $this->shape($r, $cv, $names);
            $sender = $people[(string) $r['SenderID']] ?? null;
            $s['fromName'] = $sender['name'] ?? ('User ' . $r['SenderID']);
            $s['fromAvatar'] = $sender['avatar'] ?? null;
            $msgs[] = $s;
        }

        // Per-user read cursor in NEXUS-L (do NOT wipe unread for other group members)
        if ($this->supportsNexusCols()) {
            $latestId = $maxMsgId ?: (int) ($this->q("SELECT COALESCE(MAX(MessageID), 0) AS m FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ?", [$groupId])[0]['m'] ?? 0);
            if ($latestId > 0) {
                try {
                    $this->x(
                        "UPDATE tbl_group_members SET last_read_message_id = GREATEST(last_read_message_id, ?) WHERE group_id = ? AND user_id = ?",
                        [$latestId, $groupId, $me]
                    );
                } catch (\Throwable $e) {
                }
            }
        } else {
            $this->x("UPDATE {M} SET IsRead = 1 WHERE IsGroup = 'Yes' AND GroupID = ? AND SenderID <> ? AND (IsRead = 0 OR IsRead IS NULL)", [$groupId, $me]);
        }

        $groupInfo = $this->groupDetails($me, $groupId);

        return ['messages' => $this->attachQuotesGroup($msgs, $groupId), 'group' => $groupInfo, 'hasMore' => count($rows) >= $limit];
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
        if (! empty($r['IsUnsent']) || $raw === '[UNSENT]') {
            return [
                'id' => (int) $r['MessageID'], 'from' => (string) $r['SenderID'], 'text' => '', 'kind' => 'unsent',
                'unsent' => true, 'at' => (string) $r['DateSent'], 'reactions' => [],
                'image' => null, 'file' => null, 'replyTo' => null, 'quote' => null,
                'is_triggered' => true, 'expiresAt' => $r['ExpiresAt'] ?? null,
            ];
        }
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
            'unsent' => false, 'at' => (string) $r['DateSent'], 'reactions' => self::reactions($r['EmoteTag'] ?? null),
            'image' => null, 'file' => null, 'replyTo' => $replyTo, 'quote' => $quote,
            'is_triggered' => !empty($r['IsTriggered']), 'expiresAt' => $r['ExpiresAt'] ?? null,
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

    public function rawScopedMessage(string $me, string $targetId, int $id, bool $isGroup = false): ?array
    {
        return $isGroup ? $this->rawGroupMessage($targetId, $id) : $this->rawMessage($me, $targetId, $id);
    }

    public function scopedCv(string $me, string $targetId, bool $isGroup = false): string
    {
        return $isGroup ? "group_{$targetId}" : self::singleId($me, $targetId);
    }

    public function markTriggered(string $me, string $targetId, int $msgId, bool $isGroup = false): void
    {
        if ($msgId <= 0 || $this->rawScopedMessage($me, $targetId, $msgId, $isGroup) === null) {
            return;
        }
        $this->x('UPDATE {M} SET IsTriggered = 1 WHERE MessageID = ?', [$msgId]);
    }

    public function ensureConversation(string $me, string $peer): string
    {
        $cv = self::singleId($me, $peer);
        if (! $this->q('SELECT SingleID FROM {S} WHERE SingleID = ?', [$cv])) {
            $now = self::now();
            $this->x('INSERT INTO {S} (SingleID, Members, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?)', [$cv, ';' . $me . ';' . $peer . ';', $now, $now]);
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

    public function isBlockedBetween(string $me, string $peer): bool
    {
        $b = $this->q('SELECT IsBlocked FROM {S} WHERE SingleID = ?', [self::singleId($me, $peer)])[0]['IsBlocked'] ?? null;

        return $b !== null && trim((string) $b) !== '' && ! in_array(strtolower(trim((string) $b)), ['no', '0', 'false'], true);
    }

    public function setMute(string $me, string $targetId, bool $mute, bool $isGroup = false): bool
    {
        if ($isGroup) {
            if ($this->supportsNexusCols()) {
                $this->x(
                    "UPDATE tbl_group_members SET is_muted = ? WHERE group_id = ? AND user_id = ?",
                    [$mute ? 1 : 0, $targetId, $me]
                );
            }

            return $mute;
        }
        $cv = $this->ensureConversation($me, $targetId);
        $this->x('UPDATE {S} SET IsMute = ?, UpdatedAt = ? WHERE SingleID = ?', [$mute ? 'Yes' : 'No', self::now(), $cv]);

        return $mute;
    }

    public function setBlocked(string $me, string $peer, bool $block): bool
    {
        $cv = $this->ensureConversation($me, $peer);
        $this->x('UPDATE {S} SET IsBlocked = ?, UpdatedAt = ? WHERE SingleID = ?', [$block ? 'Yes' : 'No', self::now(), $cv]);

        return $block;
    }

    /**
     * Store a message (encrypted unless the chat has SecuredChat = No). $att is store specific
     * (see RmsStore / NexusStore::attachColumns). Returns the new MessageID.
     */
    public function send(string $me, string $peer, string $text, array $att = [], ?int $replyTo = null): int
    {
        if ($me !== $peer && $this->isBlockedBetween($me, $peer)) {
            throw new \DomainException('Messaging is blocked in this conversation.', 403);
        }
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

        if ($this->supportsNexusCols() && ! isset($att['expiresAt'])) {
            $disRaw = $this->q('SELECT IsDisapearingMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['IsDisapearingMessage'] ?? null;
            $ttl    = self::disappearingTtl($disRaw);
            if ($ttl !== null) {
                $att['expiresAt'] = date('Y-m-d H:i:s', time() + $ttl);
            }
        }

        $stored = $wireText !== '' && $this->isSecure($cv) ? $this->encryptForStore($wireText, $cv) : $wireText;
        $id     = $this->insertMessage($this->insertSql($att), $this->insertParams($me, $peer, $cv, $stored, $att));
        $this->x('UPDATE {M} SET ConnectedID = ? WHERE MessageID = ?', [$prefix . $id, $id]);
        $this->x('UPDATE {S} SET UpdatedAt = ? WHERE SingleID = ?', [self::now(), $cv]);
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
        $g = $this->q("SELECT GroupID, GroupName, Admin, Policies, SecuredChat, IsDisapearingMessage FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
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

        if ($this->supportsNexusCols() && ! isset($att['expiresAt'])) {
            $ttl = self::disappearingTtl($g['IsDisapearingMessage'] ?? null);
            if ($ttl !== null) {
                $att['expiresAt'] = date('Y-m-d H:i:s', time() + $ttl);
            }
        }

        $stored = $wireText !== '' && self::yes($g['SecuredChat'] ?? null, true) ? $this->encryptForStore($wireText, $cv) : $wireText;
        $id     = $this->insertMessage($this->insertGroupSql($att), $this->insertGroupParams($me, $groupId, $stored, $att));
        $this->x('UPDATE {M} SET ConnectedID = ? WHERE MessageID = ?', [$prefix . $id, $id]);
        $this->x('UPDATE {G} SET UpdatedAt = ? WHERE GroupID = ?', [self::now(), $groupId]);
        if ($this->supportsNexusCols()) {
            try {
                $this->x(
                    "UPDATE tbl_group_members SET last_read_message_id = GREATEST(last_read_message_id, ?) WHERE group_id = ? AND user_id = ?",
                    [$id, $groupId, $me]
                );
            } catch (\Throwable $e) {
            }
        }
        if ($replyTo) {
            try {
                $this->x('UPDATE {M} SET ReplyToMessageID = ?, ReplyToSenderName = ?, ReplySnippet = ? WHERE MessageID = ?', [$replyTo, $parentSenderName, $snippet, $id]);
            } catch (\Throwable $e) {}
        }

        return $id;
    }

    public function postSystemGroupEvent(string $groupId, string $actorId, string $text): int
    {
        $g = $this->q("SELECT GroupID, SecuredChat FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            return 0;
        }
        $cv = "group_{$groupId}";
        $wireText = '[SYS]' . $text;
        $stored = self::yes($g['SecuredChat'] ?? null, true) ? $this->encryptForStore($wireText, $cv) : $wireText;
        $id = $this->insertMessage($this->insertGroupSql([]), $this->insertGroupParams($actorId, $groupId, $stored, []));
        $this->x('UPDATE {M} SET ConnectedID = ? WHERE MessageID = ?', [(string) $id, $id]);
        $this->x('UPDATE {G} SET UpdatedAt = ? WHERE GroupID = ?', [self::now(), $groupId]);

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
        $muted   = false;
        $roleMap = [];
        if ($this->supportsNexusCols()) {
            foreach ($this->q("SELECT user_id, role, is_muted FROM tbl_group_members WHERE group_id = ?", [$groupId]) as $gm) {
                $roleMap[(string) $gm['user_id']] = strtolower((string) ($gm['role'] ?? 'member'));
                if ((string) $gm['user_id'] === $me) {
                    $muted = ! empty($gm['is_muted']);
                }
            }
        }
        foreach ($memIds as $uid) {
            $p = $people[$uid] ?? ['id' => $uid, 'name' => 'User ' . $uid, 'username' => '', 'online' => false, 'avatar' => null];
            $isGrpAdmin = ($uid === $creator) || (($roleMap[$uid] ?? '') === 'admin');
            $p['role'] = $isGrpAdmin ? 'Admin' : 'Member';
            $roster[] = $p;
        }

        $policies = (string) ($g['Policies'] ?? '');
        $dis = (string) ($g['IsDisapearingMessage'] ?? '');
        $isAdm = $this->isGroupAdmin($me, $groupId);

        return [
            'id'              => (string) $g['GroupID'],
            'groupId'         => (string) $g['GroupID'],
            'name'            => (string) $g['GroupName'],
            'avatar'          => $this->resolveGroupAvatar($g['GroupPic'] ?? null, (string) $g['GroupID']),
            'admin'           => $creator,
            'isGroupAdmin'    => $isAdm,
            'myRole'          => $isAdm ? 'Admin' : 'Member',
            'policies'        => $policies,
            'pendingApproval' => str_contains($policies, 'PendingAdminApproval'),
            'secured'         => self::yes($g['SecuredChat'] ?? null, true),
            'readReceipts'    => self::yes($g['IsReadReceipt'] ?? null, true),
            'muted'           => $muted,
            'theme'           => trim((string) ($g['CurrentTheme'] ?? '')) !== '' ? trim((string) $g['CurrentTheme']) : 'default',
            'emoji'           => trim((string) ($g['CurrentEmoji'] ?? '')) !== '' ? trim((string) $g['CurrentEmoji']) : '👍',
            'disappearing'    => self::normalizeDisappearing($dis),
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
        if ($key === 'CurrentEmoji' && ($value === '' || mb_strlen($value) > 8)) {
            throw new \InvalidArgumentException('Invalid emoji.');
        }
        if ($key === 'CurrentTheme' && ($value === '' || mb_strlen($value) > 50)) {
            throw new \InvalidArgumentException('Invalid theme.');
        }
        if ($key === 'GroupName' && ($value === '' || mb_strlen($value) > 100)) {
            throw new \InvalidArgumentException('Group name must be 1–100 characters.');
        }
        if (in_array($key, ['SecuredChat', 'IsReadReceipt'], true) && ! in_array($value, ['Yes', 'No'], true)) {
            throw new \InvalidArgumentException('Invalid value.');
        }
        if (! in_array($key, ['CurrentEmoji', 'CurrentTheme', 'GroupName', 'SecuredChat', 'IsReadReceipt'], true)) {
            throw new \InvalidArgumentException('Unknown setting.');
        }
        $this->x("UPDATE {G} SET {$key} = ?, UpdatedAt = ? WHERE GroupID = ?", [$value, self::now(), $groupId]);
    }

    public function renameGroup(string $me, string $groupId, string $name, bool $isSystemAdmin = false): array
    {
        if (! $isSystemAdmin && ! $this->isGroupMember($me, $groupId)) {
            throw new \DomainException('You are not a member of this group.', 403);
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Group name must be 1–100 characters.');
        }
        $this->setGroupSetting($groupId, 'GroupName', $name);
        $actor = $this->person($me)['name'] ?? 'Someone';
        $this->postSystemGroupEvent($groupId, $me, "{$actor} renamed the group to \"{$name}\"");

        return $this->groupDetails($me, $groupId);
    }

    protected function resolveGroupAvatar(?string $pic, string $gid): ?string
    {
        $pic = trim((string) $pic);
        if ($pic === '') {
            return null;
        }
        if (str_starts_with($pic, 'data:image/') || str_starts_with($pic, 'http://') || str_starts_with($pic, 'https://') || str_starts_with($pic, '/')) {
            return $pic;
        }
        $key = ($this->source() === 'r' ? 'rg:' : 'g:') . $gid;

        return site_url('inbox/group-avatar/' . rawurlencode($key) . '?v=' . substr(md5($pic), 0, 8));
    }

    public function rawGroupPic(string $groupId): ?string
    {
        $g = $this->q("SELECT GroupPic FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        $pic = trim((string) ($g['GroupPic'] ?? ''));

        return $pic !== '' ? $pic : null;
    }

    public function setGroupPhoto(string $me, string $groupId, ?string $pic): array
    {
        $g = $this->q("SELECT GroupID, GroupPic FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            throw new \InvalidArgumentException('Group not found.');
        }
        $oldPic = trim((string) ($g['GroupPic'] ?? ''));
        if ($oldPic !== '' && ! str_starts_with($oldPic, 'data:') && ! str_starts_with($oldPic, 'http') && ! str_starts_with($oldPic, '/') && $this instanceof NexusStore) {
            $abs = NexusStore::absolutePath($oldPic);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $clean = $pic !== null && trim($pic) !== '' ? trim($pic) : null;
        $this->x("UPDATE {G} SET GroupPic = ?, UpdatedAt = ? WHERE GroupID = ?", [$clean, self::now(), $groupId]);
        $actor = $this->person($me)['name'] ?? 'Someone';
        $this->postSystemGroupEvent($groupId, $me, $clean !== null ? "{$actor} changed the group photo" : "{$actor} removed the group photo");

        return $this->groupDetails($me, $groupId);
    }

    public function addGroupMembers(string $me, string $groupId, array $newIds, bool $isSystemAdmin = false): array
    {
        if (! $isSystemAdmin && ! $this->isGroupAdmin($me, $groupId)) {
            throw new \DomainException('Only group admins can add members.', 403);
        }
        $g = $this->q("SELECT members, Policies FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            throw new \InvalidArgumentException('Group not found.');
        }
        $curr = array_values(array_unique(array_filter(array_map('trim', explode(';', (string) ($g['members'] ?? ''))))));
        $added = [];
        foreach ($newIds as $uid) {
            $uid = trim((string) $uid);
            if ($uid !== '' && ! in_array($uid, $curr, true) && $this->person($uid) !== null) {
                $curr[] = $uid;
                $added[] = $uid;
            }
        }
        if (! $added) {
            throw new \InvalidArgumentException('No valid new members selected.');
        }
        $formatted = ';' . implode(';', $curr) . ';';
        $now = self::now();
        $this->x("UPDATE {G} SET members = ?, UpdatedAt = ? WHERE GroupID = ?", [$formatted, $now, $groupId]);

        if ($this->supportsNexusCols()) {
            $maxId = (int) ($this->q("SELECT COALESCE(MAX(MessageID), 0) AS m FROM {M} WHERE IsGroup = 'Yes' AND GroupID = ?", [$groupId])[0]['m'] ?? 0);
            foreach ($added as $uid) {
                try {
                    $exists = $this->q("SELECT id FROM tbl_group_members WHERE group_id = ? AND user_id = ?", [$groupId, $uid]);
                    if (! $exists) {
                        $this->x(
                            "INSERT INTO tbl_group_members (group_id, user_id, role, joined_at, last_read_message_id, is_muted) VALUES (?, ?, 'member', ?, ?, 0)",
                            [$groupId, $uid, $now, $maxId]
                        );
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        $actor = $this->person($me)['name'] ?? 'Admin';
        $addedPeople = $this->people($added);
        $names = array_map(static fn ($id) => $addedPeople[$id]['name'] ?? ('User ' . $id), $added);
        $this->postSystemGroupEvent($groupId, $me, "{$actor} added " . implode(', ', $names));

        return $this->groupDetails($me, $groupId);
    }

    public function removeGroupMember(string $me, string $groupId, string $targetId, bool $isSystemAdmin = false): array
    {
        if (! $isSystemAdmin && ! $this->isGroupAdmin($me, $groupId)) {
            throw new \DomainException('Only group admins can remove members.', 403);
        }
        $g = $this->q("SELECT members, Admin FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            throw new \InvalidArgumentException('Group not found.');
        }
        if ($targetId === (string) ($g['Admin'] ?? '')) {
            throw new \InvalidArgumentException('The group creator cannot be removed.');
        }
        $curr = array_values(array_unique(array_filter(array_map('trim', explode(';', (string) ($g['members'] ?? ''))))));
        if (! in_array($targetId, $curr, true)) {
            throw new \InvalidArgumentException('Member is not in this group.');
        }
        $next = array_values(array_diff($curr, [$targetId]));
        $formatted = $next ? ';' . implode(';', $next) . ';' : ';';
        $this->x("UPDATE {G} SET members = ?, UpdatedAt = ? WHERE GroupID = ?", [$formatted, self::now(), $groupId]);
        if ($this->supportsNexusCols()) {
            try {
                $this->x("DELETE FROM tbl_group_members WHERE group_id = ? AND user_id = ?", [$groupId, $targetId]);
            } catch (\Throwable $e) {
            }
        }
        $actor = $this->person($me)['name'] ?? 'Admin';
        $removed = $this->person($targetId)['name'] ?? ('User ' . $targetId);
        $this->postSystemGroupEvent($groupId, $me, "{$actor} removed {$removed} from the group");

        return $this->groupDetails($me, $groupId);
    }

    public function leaveGroup(string $me, string $groupId): void
    {
        $g = $this->q("SELECT members, Admin FROM {G} WHERE GroupID = ?", [$groupId])[0] ?? null;
        if (! $g) {
            throw new \InvalidArgumentException('Group not found.');
        }
        $curr = array_values(array_unique(array_filter(array_map('trim', explode(';', (string) ($g['members'] ?? ''))))));
        $next = array_values(array_diff($curr, [$me]));
        $newAdmin = (string) ($g['Admin'] ?? '');
        if ($newAdmin === $me && $next) {
            $newAdmin = $next[0];
            if ($this->supportsNexusCols()) {
                try {
                    $this->x("UPDATE tbl_group_members SET role = 'admin' WHERE group_id = ? AND user_id = ?", [$groupId, $newAdmin]);
                } catch (\Throwable $e) {
                }
            }
        }
        $formatted = $next ? ';' . implode(';', $next) . ';' : '';
        $this->x("UPDATE {G} SET members = ?, Admin = ?, UpdatedAt = ? WHERE GroupID = ?", [$formatted, $newAdmin, self::now(), $groupId]);
        if ($this->supportsNexusCols()) {
            try {
                $this->x("DELETE FROM tbl_group_members WHERE group_id = ? AND user_id = ?", [$groupId, $me]);
            } catch (\Throwable $e) {
            }
        }
        $actor = $this->person($me)['name'] ?? 'Someone';
        $this->postSystemGroupEvent($groupId, $me, "{$actor} left the group");
    }

    public function postSystemSingleEvent(string $me, string $peer, string $text): int
    {
        $cv       = $this->ensureConversation($me, $peer);
        $wireText = '[SYS]' . $text;
        $stored   = $this->isSecure($cv) ? $this->encryptForStore($wireText, $cv) : $wireText;
        $id       = $this->insertMessage($this->insertSql([]), $this->insertParams($me, $peer, $cv, $stored, []));
        $this->x('UPDATE {M} SET ConnectedID = ? WHERE MessageID = ?', [(string) $id, $id]);
        $this->x('UPDATE {S} SET UpdatedAt = ? WHERE SingleID = ?', [self::now(), $cv]);

        return $id;
    }

    public function setDisappearingMode(string $me, string $peer, string $mode): array
    {
        $cv   = $this->ensureConversation($me, $peer);
        $mode = strtolower(trim($mode));
        if (! in_array($mode, ['off', '1m', '1h', '24h', '7d', 'toggle'], true)) {
            throw new \InvalidArgumentException('Invalid disappearing timer. Choose Off, 1m, 1h, 24h, or 7d.');
        }
        $curr = self::normalizeDisappearing((string) ($this->q('SELECT IsDisapearingMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['IsDisapearingMessage'] ?? ''));
        if ($mode === 'toggle') {
            $mode = $curr === 'off' ? '24h' : 'off';
        }
        $newVal = $mode === 'off' ? '' : $mode;
        $this->x('UPDATE {S} SET IsDisapearingMessage = ?, UpdatedAt = ? WHERE SingleID = ?', [$newVal, self::now(), $cv]);
        if ($mode !== $curr) {
            $actor = $this->person($me)['name'] ?? 'Someone';
            $label = $mode === 'off' ? 'turned off disappearing messages' : 'set disappearing messages to ' . self::disappearingLabel($mode);
            $this->postSystemSingleEvent($me, $peer, "{$actor} {$label}");
        }

        return [
            'raw'    => $newVal,
            'status' => $mode,
        ];
    }

    public function setGroupDisappearingMode(string $groupId, string $mode, string $actorId = ''): array
    {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, ['off', '1m', '1h', '24h', '7d', 'toggle'], true)) {
            throw new \InvalidArgumentException('Invalid disappearing timer. Choose Off, 1m, 1h, 24h, or 7d.');
        }
        $curr = self::normalizeDisappearing((string) ($this->q('SELECT IsDisapearingMessage FROM {G} WHERE GroupID = ?', [$groupId])[0]['IsDisapearingMessage'] ?? ''));
        if ($mode === 'toggle') {
            $mode = $curr === 'off' ? '24h' : 'off';
        }
        $newVal = $mode === 'off' ? '' : $mode;
        $this->x('UPDATE {G} SET IsDisapearingMessage = ?, UpdatedAt = ? WHERE GroupID = ?', [$newVal, self::now(), $groupId]);
        if ($actorId !== '' && $mode !== $curr) {
            $actor = $this->person($actorId)['name'] ?? 'Admin';
            $label = $mode === 'off' ? 'turned off disappearing messages' : 'set disappearing messages to ' . self::disappearingLabel($mode);
            $this->postSystemGroupEvent($groupId, $actorId, "{$actor} {$label}");
        }

        return [
            'raw'    => $newVal,
            'status' => $mode,
        ];
    }

    abstract protected function insertSql(array $att): string;

    abstract protected function insertParams(string $me, string $peer, string $cv, string $stored, array $att): array;

    // ---------- reactions / edit / bump / forward ----------

    /** Toggle or switch this user's reaction (same rules as the Android app). @return array reactions */
    public function react(string $me, string $peer, int $id, string $emoji, string $myName, bool $isGroup = false): array
    {
        $row = $this->rawScopedMessage($me, $peer, $id, $isGroup);
        if ($row === null || ! empty($row['IsUnsent'])) {
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
    public function edit(string $me, string $peer, int $id, string $text, bool $isGroup = false): array
    {
        $row = $this->rawScopedMessage($me, $peer, $id, $isGroup);
        if ($row === null || (string) $row['SenderID'] !== $me || ! empty($row['IsUnsent'])) {
            throw new \InvalidArgumentException('You can only edit your own messages.');
        }
        if (time() - strtotime((string) $row['DateSent']) > self::EDIT_WINDOW_S) {
            throw new \InvalidArgumentException('Messages can only be edited within a minute of sending.');
        }
        if ($this->hasAttachment($row) && trim($text) === '') {
            throw new \InvalidArgumentException('Message text cannot be empty.');
        }
        $cv = $this->scopedCv($me, $peer, $isGroup);
        $secured = $isGroup
            ? self::yes($this->q('SELECT SecuredChat FROM {G} WHERE GroupID = ?', [$peer])[0]['SecuredChat'] ?? null, true)
            : $this->isSecure($cv);
        $this->x('UPDATE {M} SET MessageContent = ? WHERE MessageID = ?', [$secured ? $this->encryptForStore($text, $cv) : $text, $id]);

        return ($isGroup ? $this->groupMessage($peer, $id) : $this->message($me, $peer, $id)) ?? [];
    }

    /** Re-post a message to the end of the chat as "[BUMP:senderId:senderName]text". Returns new id. */
    public function bump(string $me, string $peer, int $id, string $myName, string $peerName, bool $isGroup = false, bool $isSystemAdmin = false): int
    {
        $row = $this->rawScopedMessage($me, $peer, $id, $isGroup);
        if ($row === null || ! empty($row['IsUnsent'])) {
            throw new \InvalidArgumentException('Message not found.');
        }
        $cv   = $this->scopedCv($me, $peer, $isGroup);
        $text = $this->plain((string) $row['MessageContent'], $cv);
        if (str_starts_with($text, '[BUMP:') && ($e = strpos($text, ']')) !== false) {
            $text = trim(substr($text, $e + 1));
        }
        if (str_starts_with($text, '[REPLY:') && ($e = strpos($text, ']')) !== false) {
            $text = trim(substr($text, $e + 1));
        }
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Only text messages can be bumped on the web.');
        }
        $origId   = (string) $row['SenderID'];
        $origName = $origId === $me ? $myName : ($isGroup ? ($this->person($origId)['name'] ?? $peerName) : $peerName);

        return $isGroup
            ? $this->sendGroup($me, $peer, "[BUMP:{$origId}:{$origName}]{$text}", [], null, $isSystemAdmin)
            : $this->send($me, $peer, "[BUMP:{$origId}:{$origName}]{$text}");
    }

    /** Unsend a message for everyone (sender, or group/system admin in a group). */
    public function unsend(string $me, string $peer, int $id, bool $isGroup = false, bool $isSystemAdmin = false): array
    {
        $row = $this->rawScopedMessage($me, $peer, $id, $isGroup);
        if ($row === null) {
            throw new \InvalidArgumentException('Message not found.');
        }
        $canUnsend = (string) $row['SenderID'] === $me || ($isGroup && ($isSystemAdmin || $this->isGroupAdmin($me, $peer)));
        if (! $canUnsend) {
            throw new \DomainException('You can only unsend your own messages.', 403);
        }

        if ($this->supportsNexusCols()) {
            $rel = trim((string) ($row['FilePath'] ?? ''));
            if ($rel !== '') {
                $abs = NexusStore::absolutePath($rel);
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
            $this->x(
                "UPDATE {M} SET IsUnsent = 1, MessageContent = '', FilePath = NULL, FileName = NULL, FileSize = NULL, FileType = NULL, imagePath = NULL, EmoteTag = NULL WHERE MessageID = ?",
                [$id]
            );
        } else {
            $cv = $this->scopedCv($me, $peer, $isGroup);
            $this->x(
                "UPDATE {M} SET MessageContent = ?, imagePath = NULL, EmoteTag = NULL WHERE MessageID = ?",
                [$this->encryptForStore('[UNSENT]', $cv), $id]
            );
        }

        // Unpin if it was pinned
        try {
            $this->setPinned($me, $peer, $id, false, $isGroup);
        } catch (\Throwable $e) {
        }

        return ($isGroup ? $this->groupMessage($peer, $id) : $this->message($me, $peer, $id)) ?? [
            'id' => $id, 'from' => (string) $row['SenderID'], 'text' => '', 'kind' => 'unsent', 'unsent' => true,
            'at' => (string) $row['DateSent'], 'reactions' => [], 'image' => null, 'file' => null,
        ];
    }

    /** Delete a message for the current user only (personal delete). */
    public function deleteForMe(string $me, string $peer, int $id, bool $isGroup = false): void
    {
        $row = $this->rawScopedMessage($me, $peer, $id, $isGroup);
        if ($row === null) {
            throw new \InvalidArgumentException('Message not found.');
        }
        if (! $this->supportsNexusCols()) {
            throw new \InvalidArgumentException('Delete for me is only supported on NEXUS-L chats. Use Unsend to remove your own message.');
        }
        $existing = array_values(array_unique(array_filter(array_map('trim', explode(';', (string) ($row['DeletedFor'] ?? ''))))));
        if (! in_array($me, $existing, true)) {
            $existing[] = $me;
        }
        $val = ';' . implode(';', $existing) . ';';
        $this->x('UPDATE {M} SET DeletedFor = ? WHERE MessageID = ?', [$val, $id]);
    }

    public function plainText(string $me, string $peer, int $id, bool $isGroup = false): ?string
    {
        $row = $this->rawScopedMessage($me, $peer, $id, $isGroup);
        if ($row === null || ! empty($row['IsUnsent'])) {
            return null;
        }
        $t = $this->plain((string) $row['MessageContent'], $this->scopedCv($me, $peer, $isGroup));
        if (str_starts_with($t, '[REPLY:') && ($e = strpos($t, ']')) !== false) {
            $t = trim(substr($t, $e + 1));
        }

        return $t;
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
        $r   = $this->q('SELECT SecuredChat, IsMute, IsBlocked, IsReadReceipt, CurrentEmoji, CurrentTheme, IsDisapearingMessage, PinnedMessage FROM {S} WHERE SingleID = ?', [$cv])[0] ?? [];
        $nick = $this->nickMap($cv);
        $dis = (string) ($r['IsDisapearingMessage'] ?? '');

        return [
            'secured'      => self::yes($r['SecuredChat'] ?? null, true),
            'readReceipts' => self::yes($r['IsReadReceipt'] ?? null, true),
            'muted'        => in_array(strtolower(trim((string) ($r['IsMute'] ?? ''))), ['yes', '1', 'true'], true),
            'blocked'      => ! empty($r['IsBlocked']) && ! in_array(strtolower(trim((string) ($r['IsBlocked'] ?? ''))), ['no', '0', 'false'], true),
            'emoji'        => trim((string) ($r['CurrentEmoji'] ?? '')) !== '' ? trim((string) $r['CurrentEmoji']) : '👍',
            'theme'        => trim((string) ($r['CurrentTheme'] ?? '')) !== '' ? trim((string) $r['CurrentTheme']) : 'default',
            'disappearing' => self::normalizeDisappearing($dis),
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

    public function pinned(string $me, string $peer, bool $isGroup = false): array
    {
        $cv  = $this->scopedCv($me, $peer, $isGroup);
        $raw = $isGroup
            ? ($this->q('SELECT PinnedMessage FROM {G} WHERE GroupID = ?', [$peer])[0]['PinnedMessage'] ?? '')
            : ($this->q('SELECT PinnedMessage FROM {S} WHERE SingleID = ?', [$cv])[0]['PinnedMessage'] ?? '');
        $ids = $this->pinIds($raw);
        if (! $ids) {
            return [];
        }
        $in  = implode(',', $ids);
        $vis = $this->visibleSql($me);
        if ($isGroup) {
            $rows = $this->q(
                "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
                 FROM {M} WHERE MessageID IN ({$in}) AND IsGroup = 'Yes' AND GroupID = ? AND {$vis['sql']} ORDER BY MessageID DESC",
                array_merge([$peer], $vis['params']),
            );

            return $this->attachQuotesGroup(array_map(fn ($r) => $this->shape($r, $cv, []), $rows), $peer);
        }
        $rows = $this->q(
            "SELECT MessageID, SenderID, ConnectedID, MessageContent, DateSent, COALESCE(IsRead, 0) AS IsRead, imagePath, EmoteTag{$this->extraCols()}
             FROM {M} WHERE MessageID IN ({$in}) AND ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND COALESCE(IsGroup, 'No') <> 'Yes' AND {$vis['sql']} ORDER BY MessageID DESC",
            array_merge([$me, $peer, $peer, $me], $vis['params']),
        );

        return $this->attachQuotes(array_map(fn ($r) => $this->shape($r, $cv, []), $rows), $me, $peer, $cv);
    }

    public function setPinned(string $me, string $peer, int $id, bool $pin, bool $isGroup = false): array
    {
        if ($pin && $this->rawScopedMessage($me, $peer, $id, $isGroup) === null) {
            throw new \InvalidArgumentException('Message not found.');
        }
        if ($isGroup) {
            $ids = $this->pinIds($this->q('SELECT PinnedMessage FROM {G} WHERE GroupID = ?', [$peer])[0]['PinnedMessage'] ?? '');
            $ids = array_values(array_diff($ids, [$id]));
            if ($pin) {
                if (count($ids) >= self::PIN_LIMIT) {
                    throw new \InvalidArgumentException('You can pin up to ' . self::PIN_LIMIT . ' messages. Unpin one first.');
                }
                $ids[] = $id;
            }
            $this->x('UPDATE {G} SET PinnedMessage = ?, UpdatedAt = ? WHERE GroupID = ?', [$ids ? implode(';', $ids) . ';' : '', self::now(), $peer]);

            return $ids;
        }
        $cv  = $this->ensureConversation($me, $peer);
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
    public function searchChat(string $me, string $peer, string $q, bool $isGroup = false): array
    {
        $cv  = $this->scopedCv($me, $peer, $isGroup);
        $vis = $this->visibleSql($me);
        if ($isGroup) {
            $rows = $this->q(
                "SELECT MessageID, SenderID, MessageContent, DateSent{$this->extraCols()} FROM {M}
                 WHERE IsGroup = 'Yes' AND GroupID = ? AND {$vis['sql']} ORDER BY MessageID DESC " . $this->lim(600),
                array_merge([$peer], $vis['params']),
            );
        } else {
            $rows = $this->q(
                "SELECT MessageID, SenderID, MessageContent, DateSent{$this->extraCols()} FROM {M}
                 WHERE ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND COALESCE(IsGroup, 'No') <> 'Yes' AND {$vis['sql']} ORDER BY MessageID DESC " . $this->lim(600),
                array_merge([$me, $peer, $peer, $me], $vis['params']),
            );
        }
        $out = [];
        foreach ($rows as $r) {
            if (! empty($r['IsUnsent'])) {
                continue;
            }
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
    public function media(string $me, string $peer, int $before = 0, bool $files = false, int $limit = 24, bool $isGroup = false): array
    {
        $where = $files ? $this->filesWhere() : $this->imageWhere();
        if ($where === null) {
            return [];
        }
        $vis = $this->visibleSql($me);
        $notUnsent = $this->supportsNexusCols() ? 'AND COALESCE(IsUnsent, 0) = 0' : '';
        if ($isGroup) {
            $rows = $this->q(
                "SELECT MessageID, SenderID, imagePath, DateSent{$this->extraCols()} FROM {M}
                 WHERE IsGroup = 'Yes' AND GroupID = ? AND {$where} {$notUnsent} AND {$vis['sql']} AND (? = 0 OR MessageID < ?)
                 ORDER BY MessageID DESC " . $this->lim(max(1, min(60, $limit))),
                array_merge([$peer], $vis['params'], [$before, $before]),
            );
        } else {
            $rows = $this->q(
                "SELECT MessageID, SenderID, imagePath, DateSent{$this->extraCols()} FROM {M}
                 WHERE ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?)) AND COALESCE(IsGroup, 'No') <> 'Yes' AND {$where} {$notUnsent} AND {$vis['sql']} AND (? = 0 OR MessageID < ?)
                 ORDER BY MessageID DESC " . $this->lim(max(1, min(60, $limit))),
                array_merge([$me, $peer, $peer, $me], $vis['params'], [$before, $before]),
            );
        }

        return array_map(function ($r) {
            $s = $this->shape($r + ['MessageContent' => '', 'EmoteTag' => null], '', []);

            return ['id' => $s['id'], 'from' => $s['from'], 'at' => $s['at'], 'image' => $s['image'], 'file' => $s['file']];
        }, $rows);
    }

    // ---------- text helpers ----------

    protected function plain(string $content, string $cv): string
    {
        return $this->decryptFromStore($content, $cv);
    }

    /** @return array{text:string,kind:string} */
    protected function display(string $content, string $cv, array $names): array
    {
        $t = $this->plain($content, $cv);
        if (str_starts_with($t, '[ENC]') || str_starts_with($t, '[ENC2]')) {
            return ['text' => 'Encrypted message', 'kind' => 'msg'];
        }
        if (str_starts_with($t, '[SYS]')) {
            return ['text' => trim(substr($t, 5)), 'kind' => 'sys'];
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
