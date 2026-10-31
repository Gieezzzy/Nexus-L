<?php

namespace App\Libraries\Chat;

use App\Libraries\RmsCrypto;
use App\Libraries\RmsDb;
use Config\Rms;

/** The RMS Android app's messaging database (SQL Server dbRMSAndroid). Images only, stored the way send-reply.php does. */
class RmsStore extends ChatStore
{
    public function source(): string
    {
        return 'r';
    }

    protected function run(string $sql, array $params = []): array
    {
        return RmsDb::rows($sql, $params);
    }

    protected function exec(string $sql, array $params = []): void
    {
        RmsDb::exec($sql, $params);
    }

    protected function insertMessage(string $sql, array $params): int
    {
        return (int) RmsDb::exec(strtr($sql, $this->tables()), $params)->fetchColumn();
    }

    protected function lim(int $n): string
    {
        return "OFFSET 0 ROWS FETCH NEXT {$n} ROWS ONLY";
    }

    protected function tables(): array
    {
        return ['{M}' => 'dbo.tbl_Messages', '{S}' => 'dbo.SingleUserMessage', '{G}' => 'dbo.tbl_GroupMessage'];
    }

    protected function extraCols(): string
    {
        return ', COALESCE(IsTriggered, 0) AS IsTriggered';
    }

    protected function imageWhere(): string
    {
        return "imagePath IS NOT NULL AND imagePath <> ''";
    }

    protected function insertSql(array $att): string
    {
        return "INSERT INTO {M} (TargetReferenceID, SenderID, MessageContent, IsSyncedToSynology, ReceiverID, ConnectedID, imagePath, IsGroup, SingleID, IsRead, DateSent)
                OUTPUT INSERTED.MessageID VALUES ('WEB_NEXUS', ?, ?, 1, ?, '0', ?, 'No', ?, 0, GETDATE())";
    }

    protected function insertParams(string $me, string $peer, string $cv, string $stored, array $att): array
    {
        return [$me, $stored, $peer, $att['imagePath'] ?? null, $cv];
    }

    protected function insertGroupSql(array $att): string
    {
        return "INSERT INTO {M} (TargetReferenceID, SenderID, MessageContent, IsSyncedToSynology, ReceiverID, ConnectedID, imagePath, IsGroup, GroupID, IsRead, DateSent)
                OUTPUT INSERTED.MessageID VALUES ('WEB_NEXUS', ?, ?, 1, ?, '0', ?, 'Yes', ?, 0, GETDATE())";
    }

    protected function insertGroupParams(string $me, string $groupId, string $stored, array $att): array
    {
        return [$me, $stored, $groupId, $att['imagePath'] ?? null, $groupId];
    }

    public function createGroupRecord(string $creator, string $name, array $members, string $policies, ?string $pic = null): int
    {
        $mList = array_values(array_unique(array_filter(array_map('trim', $members))));
        if (! in_array($creator, $mList, true)) {
            $mList[] = $creator;
        }
        $formattedMembers = ';' . implode(';', $mList) . ';';
        $rows = RmsDb::rows(
            "INSERT INTO dbo.tbl_GroupMessage (GroupName, members, Admin, Policies, GroupPic, CurrentEmoji, CurrentTheme, CreatedAt, UpdatedAt)
             OUTPUT INSERTED.GroupID
             VALUES (?, ?, ?, ?, ?, '👍', 'default', GETDATE(), GETDATE())",
            [$name, $formattedMembers, $creator, $policies, $pic]
        );

        return (int) ($rows[0]['GroupID'] ?? 0);
    }

    public static function mediaUrl(string $relative): string
    {
        return rtrim(config(Rms::class)->mediaBaseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    /** Save a validated JPEG the way send-reply.php does; returns the relative imagePath stored in tbl_Messages. */
    public function saveImage(string $me, string $peer, string $jpegBytes): string
    {
        $clean = static fn (string $s) => preg_replace('/[^a-zA-Z0-9_\-]/', '_', $s);
        $rel   = 'ImageMessageDirectory/' . $clean($me) . '/' . $clean($peer) . '/img_' . time() . '_' . random_int(1000, 9999) . '.jpg';
        $abs   = 'E:\\html\\' . str_replace('/', '\\', $rel);
        if (! is_dir(dirname($abs)) && ! @mkdir(dirname($abs), 0755, true)) {
            throw new \RuntimeException('Could not create the image folder.');
        }
        if (file_put_contents($abs, $jpegBytes) === false) {
            throw new \RuntimeException('Could not save the image.');
        }

        return $rel;
    }

    // ---- directory: dbo.users ----

    private const COLS = "synologyID AS Id, COALESCE(Name, UserName) AS Name, UserName, COALESCE(IsLoggedIn, 0) AS Online, ImagePath";

    private function shapePerson(array $r): array
    {
        return [
            'id' => (string) $r['Id'], 'name' => (string) $r['Name'], 'username' => (string) ($r['UserName'] ?? ''),
            'online' => (int) ($r['Online'] ?? 0) === 1, 'avatar' => RmsCrypto::profileUrl($r['ImagePath'] ?? null), 'src' => 'r',
        ];
    }

    public function person(string $id): ?array
    {
        $r = RmsDb::rows('SELECT ' . self::COLS . ' FROM dbo.users WHERE synologyID = ?', [$id])[0] ?? null;

        return $r === null ? null : $this->shapePerson($r);
    }

    public function search(string $me, string $q): array
    {
        $like = '%' . str_replace(['%', '_', '['], ['[%]', '[_]', '[[]'], $q) . '%';

        return array_map([$this, 'shapePerson'], RmsDb::rows(
            "SELECT " . self::COLS . " FROM dbo.users WHERE synologyID IS NOT NULL AND LTRIM(RTRIM(synologyID)) <> '' AND synologyID <> 'N/A' AND synologyID <> ?
               AND COALESCE(isActive, 1) = 1 AND deleted_at IS NULL AND (Name LIKE ? OR UserName LIKE ?) ORDER BY Name OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
            [$me, $like, $like],
        ));
    }

    public function directory(string $me, string $q = '', int $limit = 30): array
    {
        $limit  = max(1, min(300, $limit));
        $params = [$me];
        $where  = '';
        if ($q !== '') {
            $like   = '%' . str_replace(['%', '_', '['], ['[%]', '[_]', '[[]'], $q) . '%';
            $where  = ' AND (Name LIKE ? OR UserName LIKE ? OR synologyID LIKE ?)';
            $params = [$me, $like, $like, $like];
        }
        $rows = RmsDb::rows(
            'SELECT ' . self::COLS . " FROM dbo.users WHERE synologyID IS NOT NULL AND LTRIM(RTRIM(synologyID)) <> '' AND synologyID <> 'N/A' AND synologyID <> ?
               AND COALESCE(isActive, 1) = 1 AND deleted_at IS NULL{$where} ORDER BY COALESCE(Name, UserName) OFFSET 0 ROWS FETCH NEXT {$limit} ROWS ONLY",
            $params,
        );

        // dbo.users can hold more than one row per Synology ID: keep one person per ID.
        $out = [];
        foreach ($rows as $r) {
            $id = (string) $r['Id'];
            if (! isset($out[$id])) {
                $out[$id] = $this->shapePerson($r);
            }
        }

        return array_values($out);
    }

    protected function people(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach (RmsDb::rows('SELECT ' . self::COLS . " FROM dbo.users WHERE synologyID IN ({$in})", array_values($ids)) as $r) {
            $out[(string) $r['Id']] = $this->shapePerson($r);
        }

        return $out;
    }
}
