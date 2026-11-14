<?php

namespace App\Libraries;

/**
 * Keeps the folders on \\192.168.32.130\qc lab files\#Lab Documents in step with what people do in the Laboratory / Other Documents.
 *
 * The web server (Apache, Windows SYSTEM account) cannot open that share, so every change is written to a queue (tbl_share_ops) and
 * done by the share worker (php spark share:worker), which runs as the Windows user who can reach the share and checks the queue every
 * 2 seconds. When the web server itself can reach the share, the change is made at once and the queue line is marked done.
 * The queue is done strictly in order (a failed step waits and is retried before anything after it), so a rename that comes after an
 * upload never runs before it.
 *
 * Operations (paths are inside a register folder, with "/"):
 *   put    write the stored copy of a file (file_id) to dst          mkdir  make folder dst
 *   delete delete file dst                                           rmdir  delete folder dst with everything in it
 *   move   rename / move src to dst (file or folder)                 copy   copy src to dst (file or folder)
 */
class ShareSync
{
    public const ROOT = '//192.168.32.130/qc lab files/#Lab Documents';

    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }
        \Config\Database::connect()->query('CREATE TABLE IF NOT EXISTS tbl_share_ops (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            register_id INT UNSIGNED NOT NULL DEFAULT 0,
            op VARCHAR(10) NOT NULL,
            src VARCHAR(800) NOT NULL DEFAULT "",
            dst VARCHAR(800) NOT NULL DEFAULT "",
            file_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT "queued",
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(500) NOT NULL DEFAULT "",
            created_by VARCHAR(120) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            next_try_at DATETIME NULL,
            done_at DATETIME NULL,
            KEY ix_status (status, id)
        ) DEFAULT CHARSET=utf8mb4');
        self::$ready = true;
    }

    /** the full share path of a path inside a register folder ('' when it is not a safe path) */
    public static function path(string $folderName, string $rel = ''): string
    {
        $rel = trim(str_replace('\\', '/', $rel), '/');
        foreach (array_merge([$folderName], $rel === '' ? [] : explode('/', $rel)) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..' || preg_match('/[<>:"|?*\x00-\x1f]/', $seg)) {
                return '';
            }
        }

        return self::ROOT . '/' . $folderName . ($rel === '' ? '' : '/' . $rel);
    }

    /** queue one change (and do it at once when the share can be reached from here) */
    public static function queue(int $registerId, string $op, string $src, string $dst, int $fileId = 0, string $by = ''): void
    {
        if (! in_array($op, ['put', 'mkdir', 'delete', 'rmdir', 'move', 'copy'], true) || $dst === '' || (in_array($op, ['move', 'copy'], true) && $src === '')) {
            return;   // an unsafe path came back as '' from path(): nothing is done on the share
        }
        self::ensureTable();
        $db = \Config\Database::connect();
        $db->table('tbl_share_ops')->insert([
            'register_id' => $registerId, 'op' => $op, 'src' => mb_substr($src, 0, 800), 'dst' => mb_substr($dst, 0, 800), 'file_id' => $fileId,
            'status' => 'queued', 'created_by' => mb_substr($by, 0, 120), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (@is_dir(self::ROOT)) {       // this process can reach the share (the worker, or a web server with a network account)
            self::work(20);
        }
    }

    /** changes still waiting for the share (for the "x changes waiting" note) */
    public static function pending(int $registerId = 0): int
    {
        self::ensureTable();
        $qb = \Config\Database::connect()->table('tbl_share_ops')->whereIn('status', ['queued', 'retry']);
        if ($registerId > 0) {
            $qb->where('register_id', $registerId);
        }

        return $qb->countAllResults();
    }

    /** is the worker alive (it touches a file every round) */
    public static function workerAlive(): bool
    {
        $f = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'share_worker.beat';

        return is_file($f) && time() - (int) @filemtime($f) < 30;
    }

    public static function beat(): void
    {
        @touch(WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'share_worker.beat');
    }

    /** do up to $max waiting changes, oldest first; stops at the first one that cannot be done yet (order matters). @return int done */
    public static function work(int $max = 50): int
    {
        self::ensureTable();
        $db   = \Config\Database::connect();
        $done = 0;
        $rows = $db->query("SELECT * FROM tbl_share_ops WHERE status IN ('queued', 'retry') ORDER BY id LIMIT " . max(1, $max))->getResultArray();
        foreach ($rows as $r) {
            if ($r['next_try_at'] !== null && strtotime((string) $r['next_try_at']) > time()) {
                break;                                                    // waiting for its retry time: keep the order
            }
            [$ok, $msg] = self::apply($r);
            if ($ok) {
                $db->table('tbl_share_ops')->where('id', $r['id'])->update(['status' => 'done', 'done_at' => date('Y-m-d H:i:s'), 'last_error' => mb_substr($msg, 0, 500)]);
                $done++;
                continue;
            }
            $n = (int) $r['attempts'] + 1;
            if ($n >= 8) {                                                // given up: recorded, and the queue goes on
                $db->table('tbl_share_ops')->where('id', $r['id'])->update(['status' => 'failed', 'attempts' => $n, 'last_error' => mb_substr($msg, 0, 500), 'done_at' => date('Y-m-d H:i:s')]);
                ActivityLog::record('labdocs', 'share-sync-failed', $r['op'] . ' ' . ($r['dst'] ?: $r['src']), $msg);
                continue;
            }
            $db->table('tbl_share_ops')->where('id', $r['id'])->update(['status' => 'retry', 'attempts' => $n, 'last_error' => mb_substr($msg, 0, 500),
                'next_try_at' => date('Y-m-d H:i:s', time() + min(300, 5 * (2 ** $n)))]);
            break;
        }

        return $done;
    }

    /** @return array{0:bool,1:string} */
    private static function apply(array $r): array
    {
        if (! @is_dir(self::ROOT)) {
            return [false, 'The share cannot be reached.'];
        }
        $src = (string) $r['src'];
        $dst = (string) $r['dst'];
        foreach ([$src, $dst] as $p) {
            if ($p !== '' && (! str_starts_with($p, self::ROOT . '/') || str_contains($p, '/../') || str_ends_with($p, '/..'))) {
                return [false, 'Refused: a path outside the documents folder.'];
            }
        }
        try {
            switch ($r['op']) {
                case 'mkdir':
                    return [is_dir($dst) || @mkdir($dst, 0775, true), ''];
                case 'put':
                    if (! is_dir(dirname($dst)) && ! @mkdir(dirname($dst), 0775, true)) {
                        return [false, 'Cannot make the folder ' . dirname($dst)];
                    }

                    return self::exportFile((int) $r['file_id'], $dst) ? [true, ''] : [false, 'The stored copy of the file could not be written.'];
                case 'delete':
                    return [! is_file($dst) || @unlink($dst), is_file($dst) ? 'in use or read-only' : ''];
                case 'rmdir':
                    return [! is_dir($dst) || self::rrmdir($dst), ''];
                case 'move':
                    if (! file_exists($src)) {
                        return [true, 'nothing at the old place'];   // already moved, or it was never on the share
                    }
                    if (file_exists($dst)) {
                        return [false, 'Something with that name is already there: ' . $dst];
                    }
                    if (! is_dir(dirname($dst)) && ! @mkdir(dirname($dst), 0775, true)) {
                        return [false, 'Cannot make the folder ' . dirname($dst)];
                    }

                    $ok = @rename($src, $dst);

                    return [$ok, $ok ? '' : 'rename failed (file in use?)'];
                case 'copy':
                    if (! file_exists($src)) {
                        return [true, 'nothing to copy on the share'];
                    }

                    $ok = self::rcopy($src, $dst);

                    return [$ok, $ok ? '' : 'copy failed'];
            }
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }

        return [false, 'Unknown operation'];
    }

    /** the stored copy of a Lab Docs file (disk cache or database parts) to a path */
    public static function exportFile(int $fileId, string $to): bool
    {
        $db = \Config\Database::connect();
        $f  = $db->table('tbl_labdoc_files')->select('id, disk_path')->where('id', $fileId)->get()->getRowArray();
        if (! $f) {
            return false;
        }
        $disk = trim((string) $f['disk_path']);
        if ($disk !== '' && ! str_contains($disk, '..') && is_file(WRITEPATH . 'labdocs/' . $disk)) {
            return @copy(WRITEPATH . 'labdocs/' . $disk, $to);
        }
        $h = @fopen($to, 'wb');
        if (! $h) {
            return false;
        }
        $n = 0;
        $id = 0;
        while (true) {   // part by part (files can be 60 MB)
            $pt = $db->query('SELECT part, data FROM tbl_labdoc_file_parts WHERE file_id = ? AND part >= ? ORDER BY part LIMIT 1', [$fileId, $id])->getRowArray();
            if (! $pt) {
                break;
            }
            fwrite($h, $pt['data']);
            $id = (int) $pt['part'] + 1;
            $n++;
        }
        fclose($h);

        return $n > 0 || filesize($to) === 0;
    }

    private static function rrmdir(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            is_dir($p) ? self::rrmdir($p) : @unlink($p);
        }

        return @rmdir($dir);
    }

    private static function rcopy(string $src, string $dst): bool
    {
        if (is_file($src)) {
            if (! is_dir(dirname($dst))) {
                @mkdir(dirname($dst), 0775, true);
            }

            return @copy($src, $dst);
        }
        if (! is_dir($dst) && ! @mkdir($dst, 0775, true)) {
            return false;
        }
        $ok = true;
        foreach (scandir($src) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                $ok = self::rcopy($src . '/' . $f, $dst . '/' . $f) && $ok;
            }
        }

        return $ok;
    }
}
