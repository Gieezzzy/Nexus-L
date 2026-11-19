<?php

namespace App\Libraries;

/**
 * Lightweight versioned cache for lab dashboards, analytics, calendar, and quality insights.
 * Calling DataCache::bump() on report/test mutations invalidates derived summaries immediately.
 */
class DataCache
{
    private const VER_KEY = 'nx_data_ver';
    /** areas a save can touch; a save that names its areas (bump('wholeshoe', 'material')) only invalidates the summaries that depend on them */
    public const AREAS = ['bonding', 'wholeshoe', 'material'];

    private static function ver(string $key): string
    {
        try {
            $v = (string) (cache($key) ?? '');
            if ($v !== '') {
                return $v;
            }
            cache()->save($key, '1', 86400 * 30);
        } catch (\Throwable $e) {
            // fallback when cache store is unavailable
        }

        return '1';
    }

    /** the cache version of the given areas (all areas when none are named): it changes when a save bumps the whole cache or one of these areas */
    public static function version(string ...$areas): string
    {
        $areas = $areas ?: self::AREAS;
        $v     = self::ver(self::VER_KEY);
        foreach ($areas as $a) {
            $v .= '.' . self::ver(self::VER_KEY . '_' . $a);
        }

        return $v;
    }

    /** bump() clears every summary; bump('bonding') only the ones that depend on that area (others keep their cache) */
    public static function bump(string ...$areas): void
    {
        $now = (string) (int) round(microtime(true) * 1000);
        try {
            foreach ($areas ?: [self::VER_KEY] as $a) {
                cache()->save($a === self::VER_KEY ? $a : self::VER_KEY . '_' . $a, $now, 86400 * 30);
            }
        } catch (\Throwable $e) {
            // never break a save because cache failed
        }
    }

    /** $areas: the areas the summary depends on (default: all, so any save refreshes it) */
    public static function remember(string $key, int $ttl, callable $builder, array $areas = [])
    {
        $full = 'nxc_' . self::version(...$areas) . '_' . $key;
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
