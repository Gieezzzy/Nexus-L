<?php

namespace App\Services\Dtr;

/** Finds and caches the report images on the shared report folder. (moved out of DtrController; bodies unchanged). */
class DtrReportImages
{
    /** Settings > Lab Report Settings of one Lab Tracker tab (bonding | shoes | containers): is the picture on, where it is read from, how that folder is organised */
    public function reportImageCfg(string $tab): array
    {
        $tab  = in_array($tab, ['bonding', 'shoes', 'containers'], true) ? $tab : 'bonding';
        $root = trim((string) \App\Libraries\SystemSettings::get("labreport.{$tab}.folder", ''));
        if ($root !== '' && ! preg_match('~^(\\\\\\\\|[A-Za-z]:[\\\\/]|https?://)~', $root)) {
            $root = '';   // not a full folder path (a bare name like "BondingImages" is read from the web server's own folder, not the share): the main folder is used
        }
        if ($root === '') {
            $root = trim((string) \App\Libraries\SystemSettings::get('labreport.image_root', ''));
        }
        $root = rtrim($root, '\\/');

        return [
            'tab'    => $tab,
            'on'     => \App\Libraries\SystemSettings::bool("labreport.{$tab}.show", $tab === 'bonding'),
            'root'   => $this->resolvedRootCached($root, $tab),
            'layout' => (string) \App\Libraries\SystemSettings::get("labreport.{$tab}.layout", $tab === 'bonding' ? 'type_date' : 'flat'),
        ];
    }

    /**
     * Resolves the report picture folder: if the configured path is a UNC path pointing to a non-existent share on this machine
     * (e.g. \\192.168.32.45\html\BondingImages when the folder is at D:\BACK UP\SystemProcess\BondingImages or \\192.168.32.43\html\BondingImages),
     * automatically falls back to a reachable local or network folder.
     */
    public function resolveReportRoot(string $root, string $tab): string
    {
        if ($root !== '' && @is_dir($root)) {
            return $root;
        }
        $candidates = array_values(array_unique(array_filter([
            $root,
            '\\\\192.168.32.43\\html\\BondingImages',   // the live pictures (where the report editor saves them)
            '\\\\192.168.32.45\\SystemProcess\\BondingImages',
            $tab === 'bonding' ? 'D:\\BACK UP\\SystemProcess\\BondingImages' : '',   // old local backup copy: last resort only
        ])));
        $local = static fn (string $c): bool => (bool) preg_match('~^[A-Za-z]:[\\\\/]~', $c);
        // in order (the live .43 folder first): opened as a share, or - the Apache service often has no network login - read over the web server of the PC that holds it; an older copy on another PC is used only when the live one is out of reach
        foreach ($candidates as $cand) {
            if ($local($cand)) {
                continue;
            }
            if (@is_dir($cand)) {
                return $cand;
            }
            $http = $this->reportHttpRoot($cand);
            if ($http !== '') {
                $hdrs = @get_headers($http . '/', false, stream_context_create(['http' => ['timeout' => 3]]));
                if (is_array($hdrs) && isset($hdrs[0]) && str_contains($hdrs[0], '200')) {
                    return $cand;
                }
            }
        }
        foreach ($candidates as $cand) {
            if ($local($cand) && @is_dir($cand)) {
                return $cand;   // old local backup copy: last resort
            }
        }

        return $root;
    }

    /** resolveReportRoot, remembered for 5 minutes (every thumbnail asks for it) */
    public function resolvedRootCached(string $root, string $tab): string
    {
        $f = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'reportroot_' . md5($tab . '|' . $root) . '.txt';
        if (is_file($f) && time() - (int) @filemtime($f) < 300) {
            return (string) @file_get_contents($f);
        }
        $r = $this->resolveReportRoot($root, $tab);
        @file_put_contents($f, $r, LOCK_EX);

        return $r;
    }

    /** Can the web server open the report picture folder? */
    public function reportShareReachable(string $root): bool
    {
        return @is_dir($root);
    }

    public function reportImageCache(array $cfg): string
    {
        return WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'reportimg_' . md5($cfg['root'] . '|' . $cfg['layout']) . '.json';
    }

    /**
     * Every picture of the tab's folder, keyed by its Lab Ref No (letters and digits only, so DTP20261109RIVETA11L and DTP20261109RIVETA11-L meet).
     * Layout TYPE_DATE: <folder>\<TEST TYPE>\<date>\<Lab Ref>.png (and any loose picture in <folder>); FLAT: <folder>\<Lab Ref>.png.
     * Names:  <LabRef>.png = the current report (wins) | <LabRef>_<n>.png = older version | <date>-<time>_<LabRef>_<hash>.png = batch copy.
     * Kept for a minute in writable/cache so a list of 100 pictures does not scan the network share 100 times.
     *
     * @return array<string,array{p:string,t:int,e:bool}>
     */
    public function reportImageIndex(array $cfg): array
    {
        $root = $cfg['root'];
        if ($root === '') {
            return [];
        }
        $http = $this->reportShareReachable($root) ? '' : $this->reportHttpRoot($root);
        if ($http === '' && ! $this->reportShareReachable($root)) {
            return [];
        }
        $cache = $this->reportImageCache($cfg);
        $fresh = static function () use ($cache): ?array {
            if (is_file($cache) && time() - (int) @filemtime($cache) < 300) {
                $j = json_decode((string) @file_get_contents($cache), true);
                if (is_array($j) && count($j) > 0) {
                    return $j;
                }
            }

            return null;
        };
        if (($j = $fresh()) !== null) {
            return $j;
        }
        // only one request reads the folder; the others wait for it and then use its result
        $lock = @fopen($cache . '.lock', 'c');
        if ($lock) {
            @flock($lock, LOCK_EX);
            if (($j = $fresh()) !== null) {
                @flock($lock, LOCK_UN);
                @fclose($lock);

                return $j;
            }
        }
        try {
            return $this->buildReportImageIndex($cfg, $root, $http, $cache);
        } finally {
            if ($lock) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        }
    }

    /** reads the picture folder (see reportImageIndex) */
    public function buildReportImageIndex(array $cfg, string $root, string $http, string $cache): array
    {
        $normKey = fn (string $s): string => $this->reportKey($s);
        $map  = [];
        $scan = static function (string $dir) use (&$map, $normKey): void {
            foreach (@scandir($dir) ?: [] as $f) {
                if (! preg_match('/^(.+?)\.(png|jpe?g)$/i', $f, $m)) {
                    continue;
                }
                $name  = $m[1];
                $exact = true;
                if (preg_match('/^\d{8}-\d{4}_(.+)_[0-9a-f]{8}$/i', $name, $mm)) {
                    $name = $mm[1]; $exact = false;
                } elseif (preg_match('/^(.+)_\d+$/', $name, $mm)) {
                    $name = $mm[1]; $exact = false;
                } elseif (stripos($name, 'TRIAL') === false && preg_match('/[LR][\s\-]+\d+$/i', $name)) {
                    $exact = false;
                }
                $key  = $normKey($name);
                $path = $dir . DIRECTORY_SEPARATOR . $f;
                $t    = (int) @filemtime($path);
                $cur  = $map[$key] ?? null;
                if ($cur === null || ($exact && ! $cur['e']) || ($exact === $cur['e'] && $t >= $cur['t'])) {
                    $map[$key] = ['p' => $path, 't' => $t, 'e' => $exact];
                }
            }
        };
        if ($http !== '') {
            // the web server cannot open the share (the Apache service has no network login): read the same folder over the web server of the PC that holds it
            $list = static function (string $url): array {
                $html = (string) @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
                $out  = ['files' => [], 'dirs' => []];
                if (preg_match_all('~<a href="([^"?/][^"]*?)">[^<]*</a>(?:</td><td[^>]*>\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2}))?~i', $html, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $x) {
                        $n = $x[1];
                        if (substr($n, -1) === '/') {
                            $out['dirs'][] = $n;
                        } else {
                            $out['files'][] = [$n, isset($x[2]) ? (int) strtotime($x[2]) : 0];
                        }
                    }
                }

                return $out;
            };
            $add = static function (string $base, array $files) use (&$map, $normKey): void {
                foreach ($files as [$f, $t]) {
                    $dec = rawurldecode($f);
                    if (! preg_match('/^(.+?)\.(png|jpe?g)$/i', $dec, $m)) {
                        continue;
                    }
                    $name = $m[1]; $exact = true;
                    if (preg_match('/^\d{8}-\d{4}_(.+)_[0-9a-f]{8}$/i', $name, $mm)) {
                        $name = $mm[1]; $exact = false;
                    } elseif (preg_match('/^(.+)_\d+$/', $name, $mm)) {
                        $name = $mm[1]; $exact = false;
                    } elseif (stripos($name, 'TRIAL') === false && preg_match('/[LR][\s\-]+\d+$/i', $name)) {
                        $exact = false;
                    }
                    $key = $normKey($name);
                    $cur = $map[$key] ?? null;
                    if ($cur === null || ($exact && ! $cur['e']) || ($exact === $cur['e'] && $t >= $cur['t'])) {
                        $map[$key] = ['p' => $base . $f, 't' => $t, 'e' => $exact];
                    }
                }
            };
            $r0 = $list($http . '/');
            $add($http . '/', $r0['files']);
            if ($cfg['layout'] === 'type_date') {
                foreach ($r0['dirs'] as $a) {
                    $r1 = $list($http . '/' . $a);
                    $add($http . '/' . $a, $r1['files']);
                    foreach ($r1['dirs'] as $b) {
                        $r2 = $list($http . '/' . $a . $b);
                        $add($http . '/' . $a . $b, $r2['files']);
                        foreach ($r2['dirs'] as $c) {
                            $r3 = $list($http . '/' . $a . $b . $c);
                            $add($http . '/' . $a . $b . $c, $r3['files']);
                        }
                    }
                }
            }
            if ($map) {
                @file_put_contents($cache, json_encode($map));
            }

            return $map;
        }
        $scan($root);
        if ($cfg['layout'] === 'type_date') {
            foreach (@scandir($root) ?: [] as $a) {
                if ($a === '.' || $a === '..' || ! is_dir($root . DIRECTORY_SEPARATOR . $a)) {
                    continue;
                }
                $dirA = $root . DIRECTORY_SEPARATOR . $a;
                $scan($dirA);
                foreach (@scandir($dirA) ?: [] as $b) {
                    if ($b === '.' || $b === '..' || ! is_dir($dirA . DIRECTORY_SEPARATOR . $b)) {
                        continue;
                    }
                    $dirB = $dirA . DIRECTORY_SEPARATOR . $b;
                    $scan($dirB);
                    foreach (@scandir($dirB) ?: [] as $c) {
                        if ($c !== '.' && $c !== '..' && is_dir($dirB . DIRECTORY_SEPARATOR . $c)) {
                            $scan($dirB . DIRECTORY_SEPARATOR . $c);
                        }
                    }
                }
            }
        }
        if ($map) {
            @file_put_contents($cache, json_encode($map));
        }

        return $map;
    }

    /**
     * Matching key of a Lab Ref / picture name (letters and digits only). Forgives the old spellings found in the picture folders:
     * RIVET = RVT, "-RO1" typed with the letter O = "-R01", and a trailing " 2" / "-2" copy number after the side (L/R) - except TRIAL names, where that number is the trial number.
     */
    public function reportKey(string $s): string
    {
        $raw = trim($s);
        if (! str_contains(strtoupper($raw), 'TRIAL')) {
            $raw = (string) preg_replace('/([LR])[\s\-]+\d+$/i', '$1', $raw);
        }
        $k = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
        $k = (string) preg_replace('/^(DTP\d{8})RIVET/', '$1RVT', $k);

        return (string) preg_replace('/R[O0]([0-9])$/', 'R0$1', $k);
    }

    public function reportImageFile(string $tab, string $labRef): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $labRef)) {
            return null;
        }
        $key = $this->reportKey($labRef);
        $hit = $this->reportImageIndex($this->reportImageCfg($tab))[$key] ?? null;

        return ($hit && (preg_match('~^https?://~i', $hit['p']) || is_file($hit['p']))) ? $hit['p'] : null;
    }

    /** \\host\html\Folder -> http://host/Folder (the share "html" is the web root of that PC); an http(s):// folder is used as it is; '' when neither */
    public function reportHttpRoot(string $root): string
    {
        if (preg_match('~^https?://~i', $root)) {
            return rtrim($root, '/');
        }
        if (preg_match('~^\\\\\\\\([^\\\\]+)\\\\html(?:\\\\(.*))?$~i', $root, $m)) {
            return 'http://' . $m[1] . (isset($m[2]) && $m[2] !== '' ? '/' . implode('/', array_map('rawurlencode', explode('\\', $m[2]))) : '');
        }

        // \\host\BondingImages (the share itself named like the web folder) -> http://host/BondingImages
        if (preg_match('~^\\\\\\\\([^\\\\]+)\\\\(.+)$~', $root, $m)) {
            return 'http://' . $m[1] . '/' . implode('/', array_map('rawurlencode', explode('\\', $m[2])));
        }

        return '';
    }
}
