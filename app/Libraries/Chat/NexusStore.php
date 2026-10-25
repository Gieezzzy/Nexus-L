<?php

namespace App\Libraries\Chat;

use CodeIgniter\Database\BaseConnection;

/**
 * NEXUS-L's own messaging (dbnexus, MySQL/MariaDB). Identity = tbl_users.UserID (as a string).
 * Supports images and general files; files live in writable/uploads/inbox and are only served through an authenticated route.
 */
class NexusStore extends ChatStore
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function source(): string
    {
        return 'n';
    }

    protected function run(string $sql, array $params = []): array
    {
        return $this->db->query($sql, $params)->getResultArray();
    }

    protected function exec(string $sql, array $params = []): void
    {
        $this->db->query($sql, $params);
    }

    protected function insertMessage(string $sql, array $params): int
    {
        $this->db->query(strtr($sql, $this->tables()), $params);

        return (int) $this->db->insertID();
    }

    protected function lim(int $n): string
    {
        return "LIMIT {$n}";
    }

    protected function tables(): array
    {
        return ['{M}' => 'tbl_Messages', '{S}' => 'SingleUserMessage', '{G}' => 'tbl_groupmessage'];
    }

    protected function extraCols(): string
    {
        return ', FileName, FileSize, FileType';
    }

    protected function imageWhere(): string
    {
        return "FileType LIKE 'image/%'";
    }

    protected function filesWhere(): ?string
    {
        return "FileName IS NOT NULL AND FileType NOT LIKE 'image/%'";
    }

    protected function insertSql(array $att): string
    {
        return "INSERT INTO {M} (TargetReferenceID, SenderID, MessageContent, IsSyncedToSynology, ReceiverID, ConnectedID, IsGroup, SingleID, IsRead, DateSent, FilePath, FileName, FileSize, FileType)
                VALUES ('WEB', ?, ?, 0, ?, '0', 'No', ?, 0, ?, ?, ?, ?, ?)";
    }

    protected function insertParams(string $me, string $peer, string $cv, string $stored, array $att): array
    {
        return [$me, $stored, $peer, $cv, self::now(), $att['path'] ?? null, $att['name'] ?? null, $att['size'] ?? null, $att['type'] ?? null];
    }

    protected function insertGroupSql(array $att): string
    {
        return "INSERT INTO {M} (TargetReferenceID, SenderID, MessageContent, IsSyncedToSynology, ReceiverID, ConnectedID, IsGroup, GroupID, IsRead, DateSent, FilePath, FileName, FileSize, FileType)
                VALUES ('WEB', ?, ?, 0, ?, '0', 'Yes', ?, 0, ?, ?, ?, ?, ?)";
    }

    protected function insertGroupParams(string $me, string $groupId, string $stored, array $att): array
    {
        return [$me, $stored, $groupId, $groupId, self::now(), $att['path'] ?? null, $att['name'] ?? null, $att['size'] ?? null, $att['type'] ?? null];
    }

    public function createGroupRecord(string $creator, string $name, array $members, string $policies, ?string $pic = null): int
    {
        $mList = array_values(array_unique(array_filter(array_map('trim', $members))));
        if (! in_array($creator, $mList, true)) {
            $mList[] = $creator;
        }
        $formattedMembers = ';' . implode(';', $mList) . ';';
        $now = self::now();
        $this->exec(
            "INSERT INTO tbl_groupmessage (GroupName, members, Admin, Policies, GroupPic, CurrentEmoji, CurrentTheme, CreatedAt, UpdatedAt)
             VALUES (?, ?, ?, ?, ?, '👍', 'default', ?, ?)",
            [$name, $formattedMembers, $creator, $policies, $pic, $now, $now]
        );
        $gid = (int) $this->db->insertID();

        // Relational sync into tbl_group_members
        foreach ($mList as $u) {
            try {
                $role = ($u === $creator) ? 'admin' : 'member';
                $this->exec(
                    "INSERT INTO tbl_group_members (group_id, user_id, role, joined_at, is_muted) VALUES (?, ?, ?, ?, 0)",
                    [(string)$gid, (string)$u, $role, $now]
                );
            } catch (\Throwable $e) {}
        }

        return $gid;
    }

    /** Absolute path of a stored attachment (for the authenticated download route). */
    public static function absolutePath(string $relative): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    // ---- directory: tbl_users ----

    private function shapePerson(array $r): array
    {
        return [
            'id' => (string) $r['UserID'], 'name' => (string) $r['FullName'], 'username' => (string) $r['Username'],
            'online' => false, 'avatar' => null, 'src' => 'n', 'dept' => (string) ($r['Department'] ?? ''), 'synologyId' => $r['SynologyID'] ?? null,
        ];
    }

    public function person(string $id): ?array
    {
        $r = $this->run('SELECT UserID, FullName, Username, Department, SynologyID FROM tbl_users WHERE UserID = ? AND IsActive = 1', [(int) $id])[0] ?? null;

        return $r === null ? null : $this->shapePerson($r);
    }

    public function search(string $me, string $q): array
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';

        return array_map([$this, 'shapePerson'], $this->run(
            'SELECT UserID, FullName, Username, Department, SynologyID FROM tbl_users WHERE IsActive = 1 AND UserID <> ? AND (FullName LIKE ? OR Username LIKE ?) ORDER BY FullName LIMIT 10',
            [(int) $me, $like, $like],
        ));
    }

    public function directory(string $me, string $q = '', int $limit = 30): array
    {
        $limit  = max(1, min(300, $limit));
        $params = [(int) $me];
        $where  = '';
        if ($q !== '') {
            $like   = '%' . addcslashes($q, '%_\\') . '%';
            $where  = ' AND (FullName LIKE ? OR Username LIKE ? OR CAST(SynologyID AS CHAR) LIKE ?)';
            $params = [(int) $me, $like, $like, $like];
        }

        return array_map([$this, 'shapePerson'], $this->run(
            "SELECT UserID, FullName, Username, Department, SynologyID FROM tbl_users WHERE IsActive = 1 AND UserID <> ?{$where} ORDER BY FullName LIMIT {$limit}",
            $params,
        ));
    }

    protected function people(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }
        $in  = implode(',', $ids);
        $out = [];
        foreach ($this->run("SELECT UserID, FullName, Username, Department, SynologyID FROM tbl_users WHERE UserID IN ({$in})") as $r) {
            $out[(string) $r['UserID']] = $this->shapePerson($r);
        }

        return $out;
    }
}
