<?php

namespace App\Libraries;

/**
 * Lightweight versioned cache for lab dashboards, analytics, calendar, and quality insights.
 * Calling DataCache::bump() on report/test mutations invalidates derived summaries immediately.
 */
class DataCache
{
    private const VER_KEY = 'nx_data_ver';

    public static function version(): string
    {
        try {
            $v = (string) (cache(self::VER_KEY) ?? '');
            if ($v !== '') {
                return $v;
            }
            cache()->save(self::VER_KEY, '1', 86400 * 30);
        } catch (\Throwable $e) {
            // fallback when cache store is unavailable
        }

        return '1';
    }

    public static function bump(): void
    {
        try {
            cache()->save(self::VER_KEY, (string) (int) round(microtime(true) * 1000), 86400 * 30);
        } catch (\Throwable $e) {
            // never break a save because cache failed
        }
    }

    public static function remember(string $key, int $ttl, callable $builder)
    {
        $full = 'nxc_' . self::version() . '_' . $key;
        try {
            $hit = cache()->get($full);
            if ($hit !== null) {
                return $hit;
            }
        } catch (\Throwable $e) {
            // fallback cleanly
        }
        $val = $builder();
        try {
            cache()->save($full, $val, $ttl);
        } catch (\Throwable $e) {
            // ignore cache write failure
        }

        return $val;
    }
}
