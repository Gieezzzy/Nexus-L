<?php

namespace App\Libraries;

/**
 * One record of every change people make in NEXUS-L (tbl_activity_log): who (name, user id, role), what (module, action, target, detail),
 * from where (IP, computer) and when. Deletions are also written to the delete log of the lab (tbl_dtr_delete_audit), so one place lists
 * everything that was removed.
 *
 *   ActivityLog::record('labdocs', 'rename-file', 'SATRA Files/a.pdf', 'to b.pdf');
 *
 * The RequestLog filter adds one line for every other change (any POST) automatically, so nothing goes unrecorded. Writing a log line
 * never stops the action itself: errors are only written to the error log.
 */
class ActivityLog
{
    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }
        $flag = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'activity_log_v1.flag';
        if (! is_file($flag)) {
            \Config\Database::connect()->query('CREATE TABLE IF NOT EXISTS tbl_activity_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL,
                user_id INT UNSIGNED NOT NULL DEFAULT 0,
                user_name VARCHAR(120) NOT NULL DEFAULT "",
                user_role VARCHAR(120) NOT NULL DEFAULT "",
                module VARCHAR(40) NOT NULL DEFAULT "",
                action VARCHAR(60) NOT NULL DEFAULT "",
                target VARCHAR(600) NOT NULL DEFAULT "",
                detail TEXT NULL,
                method VARCHAR(8) NOT NULL DEFAULT "",
                path VARCHAR(300) NOT NULL DEFAULT "",
                status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                ip VARCHAR(60) NOT NULL DEFAULT "",
                KEY ix_at (created_at),
                KEY ix_user (user_id, created_at),
                KEY ix_module (module, created_at)
            ) DEFAULT CHARSET=utf8mb4');
            @touch($flag);
        }
        self::$ready = true;
    }

    /** the signed-in person: [id, name, role] */
    private static function who(): array
    {
        try {
            $u = service('auth')->user() ?? [];
        } catch (\Throwable $e) {
            $u = [];
        }

        return [(int) ($u['id'] ?? 0), (string) ($u['name'] ?? $u['username'] ?? 'system'), (string) ($u['role'] ?? '')];
    }

    private static function ip(): string
    {
        try {
            return (string) service('request')->getIPAddress();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** one action, as people would describe it */
    public static function record(string $module, string $action, string $target = '', string $detail = '', array $extra = []): void
    {
        try {
            self::ensureTable();
            [$id, $name, $role] = self::who();
            \Config\Database::connect()->table('tbl_activity_log')->insert([
                'created_at' => date('Y-m-d H:i:s'), 'user_id' => $id, 'user_name' => mb_substr($name, 0, 120), 'user_role' => mb_substr($role, 0, 120),
                'module' => mb_substr($module, 0, 40), 'action' => mb_substr($action, 0, 60), 'target' => mb_substr($target, 0, 600), 'detail' => $detail,
                'method' => (string) ($extra['method'] ?? ''), 'path' => mb_substr((string) ($extra['path'] ?? ''), 0, 300), 'status' => (int) ($extra['status'] ?? 0), 'ip' => self::ip(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[ActivityLog] ' . $e->getMessage());
        }
    }

    /** a deletion: the activity log + the lab's delete log (tbl_dtr_delete_audit) */
    public static function deleted(string $module, string $what, string $detail = '', string $reason = ''): void
    {
        self::record($module, 'delete', $what, $detail);
        try {
            [, $name] = self::who();
            $db = \Config\Database::connect();
            if ($db->tableExists('tbl_dtr_delete_audit')) {
                $db->table('tbl_dtr_delete_audit')->insert([
                    'DeletedAt' => date('Y-m-d H:i:s'), 'DeletedBy' => mb_substr($name, 0, 100), 'Reason' => mb_substr($reason !== '' ? $reason : 'Deleted in ' . $module, 0, 250),
                    'Module' => mb_substr($module, 0, 50), 'EntryRef' => mb_substr($what, 0, 250), 'Remarks' => $detail,
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', '[ActivityLog] delete audit: ' . $e->getMessage());
        }
    }
}
