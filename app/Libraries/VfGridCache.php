<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * A saved copy of what InterLink's Applicant grid showed for one (package, search text, page). Users read this copy at once;
 * a refresh is asked from the InterLink service only when the copy is old, and only one refresh per key runs at a time, so
 * 100 users cause one trip to InterLink, not 100.
 *
 * No mixing: a row is keyed by package + search text + page and is only ever filled by the job that was queued for exactly that key
 * (claimed by job_uid). A result whose page is not the requested page is not saved.
 */
class VfGridCache
{
    public const TABLE       = 'tbl_vf_grid_cache';
    public const CLAIM_LOCK  = 120;   // seconds a refresh may run before another one may be started for the same key

    public static function ensure(BaseConnection $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $db->query('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(190) NOT NULL,
            package VARCHAR(80) NOT NULL DEFAULT "",
            q VARCHAR(80) NOT NULL DEFAULT "",
            page INT NOT NULL DEFAULT 1,
            rows_json MEDIUMTEXT NULL,
            pages INT NOT NULL DEFAULT 0,
            items INT NOT NULL DEFAULT 0,
            fetched_at DATETIME NULL,
            refreshing_job VARCHAR(40) NULL,
            refreshing_since DATETIME NULL,
            last_error VARCHAR(255) NOT NULL DEFAULT "",
            UNIQUE KEY uq_key (cache_key),
            KEY ix_job (refreshing_job)
        ) DEFAULT CHARSET=utf8mb4');
        $done = true;
    }

    public static function key(string $package, string $q, int $page): string
    {
        return mb_strtolower(trim($package)) . '|' . mb_strtolower(trim($q)) . '|' . $page;
    }

    /** seconds a copy counts as fresh: page 1 of the plain list moves most; deeper pages and searches move slowly */
    public static function ttl(string $q, int $page): int
    {
        return ($page === 1 && trim($q) === '') ? 90 : 300;
    }

    public static function get(BaseConnection $db, string $key): ?array
    {
        return $db->table(self::TABLE)->where('cache_key', $key)->get(1)->getRowArray() ?: null;
    }

    /** true = this caller may start the refresh (nobody else has one running for this key) */
    public static function claim(BaseConnection $db, string $key, string $package, string $q, int $page, string $jobUid): bool
    {
        $db->query('INSERT IGNORE INTO ' . self::TABLE . ' (cache_key, package, q, page) VALUES (?,?,?,?)', [$key, $package, mb_substr($q, 0, 80), $page]);
        $db->query(
            'UPDATE ' . self::TABLE . ' SET refreshing_job = ?, refreshing_since = NOW() WHERE cache_key = ? AND (refreshing_job IS NULL OR refreshing_since IS NULL OR refreshing_since < (NOW() - INTERVAL ' . self::CLAIM_LOCK . ' SECOND))',
            [$jobUid, $key]
        );

        return $db->affectedRows() > 0;
    }

    /** the refresh could not be started or failed: free the key and remember why */
    public static function fail(BaseConnection $db, string $jobUid, string $why): void
    {
        $db->table(self::TABLE)->where('refreshing_job', $jobUid)->update(['refreshing_job' => null, 'refreshing_since' => null, 'last_error' => mb_substr($why, 0, 250)]);
    }

    /** the service reported the end of the job $jobUid: save the rows to the key it was claimed for (and only that key) */
    public static function finish(BaseConnection $db, string $jobUid, string $state, string $message, ?array $result): void
    {
        $row = $db->table(self::TABLE)->where('refreshing_job', $jobUid)->get(1)->getRowArray();
        if (! $row) {
            return;   // not a refresh of ours (for example the manual sync)
        }
        if ($state !== 'done' || ! is_array($result) || ! is_array($result['rows'] ?? null)) {
            self::fail($db, $jobUid, $message !== '' ? $message : 'InterLink did not return the list.');

            return;
        }
        $withData = array_filter($result['rows'], static fn ($r) => is_array($r) && $r !== []);
        if ($result['rows'] !== [] && ! $withData) {
            self::fail($db, $jobUid, 'InterLink returned blank rows; not saved.');

            return;
        }
        $gotPage = (int) ($result['page'] ?? 0);
        $pages   = (int) ($result['pages'] ?? 0);
        // the page InterLink answered with must be the one asked for (a page past the end comes back as the last page: not saved under the asked key)
        if ($gotPage !== (int) $row['page'] && ! ($gotPage === 0 && (int) $row['page'] === 1)) {
            self::fail($db, $jobUid, 'InterLink returned page ' . $gotPage . ' instead of ' . $row['page'] . '; not saved.');

            return;
        }
        $db->table(self::TABLE)->where('id', $row['id'])->update([
            'rows_json'        => json_encode(array_values($result['rows']), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'pages'            => $pages,
            'items'            => (int) ($result['items'] ?? 0),
            'fetched_at'       => date('Y-m-d H:i:s'),
            'refreshing_job'   => null,
            'refreshing_since' => null,
            'last_error'       => '',
        ]);
    }
}
