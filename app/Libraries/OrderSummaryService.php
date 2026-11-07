<?php

namespace App\Libraries;

/**
 * Reads the "#ORDER SUMMARY" PDFs (MTL running-style lists) from the QC lab share
 * and looks an MTL# up in them: season ticks (F26/H26/S27), season text, style,
 * colour, risk, note, remark, cutting die ...
 *
 * Real time: every lookup lists the folder (cheap) and re-reads only the PDFs whose
 * size or modified time changed since the cached copy, so a PDF the PIC drops in
 * the folder is searchable on the next lookup with no sync step.
 *
 * Parsing is done by tools/order_summary_parse.py (PyMuPDF, column-aware).
 */
class OrderSummaryService
{
    public const DEFAULT_PATH = '\\\\192.168.32.130\\qc lab files\\#ORDER SUMMARY';

    public static function root(): string
    {
        $p = trim((string) SystemSettings::get('order_summary.path', self::DEFAULT_PATH));

        return rtrim($p !== '' ? $p : self::DEFAULT_PATH, '\\/');
    }

    private static function cacheDir(): string
    {
        $d = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'order_summary';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    /** Net-use only when a share login is configured in Settings (nothing is hard-coded). */
    private static function reachable(): bool
    {
        $root = self::root();
        if (@is_dir($root)) {
            return true;
        }
        $user = trim((string) SystemSettings::get('order_summary.share_user', ''));
        $pass = (string) SystemSettings::get('order_summary.share_pass', '');
        if ($user === '' || ! preg_match('~^\\\\\\\\[^\\\\]+\\\\[^\\\\]+~', $root, $m)) {
            return false;
        }
        $cmd = 'net use ' . escapeshellarg($m[0]) . ' ' . escapeshellarg($pass) . ' /user:' . escapeshellarg($user) . ' /persistent:no 2>&1';
        @shell_exec($cmd);
        clearstatcache();

        return @is_dir($root);
    }

    /** @return array<int,array{name:string,path:string,mtime:int,size:int}> newest first */
    public static function files(): array
    {
        if (! self::reachable()) {
            return [];
        }
        $out = [];
        foreach (@scandir(self::root()) ?: [] as $n) {
            if ($n[0] === '~' || strtolower(pathinfo($n, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }
            $p = self::root() . DIRECTORY_SEPARATOR . $n;
            if (! is_file($p)) {
                continue;
            }
            $out[] = ['name' => $n, 'path' => $p, 'mtime' => (int) @filemtime($p), 'size' => (int) @filesize($p)];
        }
        usort($out, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    /** @return array{rows:array,error?:string} rows of one PDF, from cache when unchanged */
    private static function load(array $f): array
    {
        $cache = self::cacheDir() . DIRECTORY_SEPARATOR . sha1(strtolower($f['path'])) . '.json';
        $hit   = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
        if (is_array($hit) && ($hit['mtime'] ?? 0) === $f['mtime'] && ($hit['size'] ?? 0) === $f['size'] && ($hit['v'] ?? 0) === 1) {
            return ['rows' => $hit['rows']];
        }

        // one parse at a time so two lookups do not parse the same new file twice
        $lock = @fopen($cache . '.lock', 'c');
        if ($lock) {
            @flock($lock, LOCK_EX);
            clearstatcache();
            $hit = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
            if (is_array($hit) && ($hit['mtime'] ?? 0) === $f['mtime'] && ($hit['size'] ?? 0) === $f['size'] && ($hit['v'] ?? 0) === 1) {
                @flock($lock, LOCK_UN);
                fclose($lock);

                return ['rows' => $hit['rows']];
            }
        }

        $res = self::runParser($f['path']);
        if (isset($res['rows'])) {
            @file_put_contents($cache, json_encode(['v' => 1, 'mtime' => $f['mtime'], 'size' => $f['size'], 'rows' => $res['rows']], JSON_UNESCAPED_UNICODE));
        }
        if ($lock) {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $res;
    }

    private static function runParser(string $pdf): array
    {
        $py     = trim((string) SystemSettings::get('order_summary.python', 'python')) ?: 'python';
        $script = ROOTPATH . 'tools' . DIRECTORY_SEPARATOR . 'order_summary_parse.py';
        $proc   = @proc_open([$py, '-W', 'ignore', $script, $pdf], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            return ['rows' => [], 'error' => 'Could not start Python (' . $py . ').'];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $end = microtime(true) + 60;
        while (microtime(true) < $end) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            if (! (proc_get_status($proc)['running'] ?? false)) {
                break;
            }
            usleep(50000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        $running = proc_get_status($proc)['running'] ?? false;
        if ($running) {
            proc_terminate($proc);
        }
        proc_close($proc);
        $j = json_decode($out, true);
        if (! is_array($j) || ! isset($j['rows'])) {
            return ['rows' => [], 'error' => 'PDF read failed: ' . trim(substr($err !== '' ? $err : 'no output', -300))];
        }

        return ['rows' => $j['rows']];
    }

    public static function normalizeMtl(string $s): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($s)));
    }

    /**
     * @return array{ok:bool,mtl:string,matches:array,files:array,errors:array,reachable:bool}
     */
    public static function lookup(string $mtl): array
    {
        $mtl   = self::normalizeMtl($mtl);
        $files = self::files();
        $res   = ['ok' => true, 'mtl' => $mtl, 'matches' => [], 'files' => [], 'errors' => [], 'reachable' => $files !== [] || @is_dir(self::root())];
        foreach ($files as $f) {
            $r = self::load($f);
            $res['files'][] = ['name' => $f['name'], 'modified' => date('Y-m-d H:i:s', $f['mtime']), 'rows' => count($r['rows'])];
            if (! empty($r['error'])) {
                $res['errors'][] = $f['name'] . ': ' . $r['error'];
            }
            if ($mtl === '') {
                continue;
            }
            foreach ($r['rows'] as $row) {
                $m = self::normalizeMtl((string) $row['mtl']);
                // exact code, or the same code with a suffix such as -OLD (shown, flagged)
                if ($m !== $mtl && ! (strlen($mtl) >= 10 && str_starts_with($m, $mtl . '-'))) {
                    continue;
                }
                $fields = $row['fields'] ?? [];
                $ticks  = array_values($row['ticks'] ?? []);
                $seasonField = trim((string) ($fields['Season'] ?? ''));
                $seasons = $ticks;
                $origin  = '';
                if (preg_match('/^[FHSW]\d{2}$/i', $seasonField)) {
                    $seasons[] = strtoupper($seasonField);
                } elseif ($seasonField !== '') {
                    $origin = $seasonField;
                }
                $res['matches'][] = [
                    'file'       => $f['name'],
                    'modified'   => date('Y-m-d H:i:s', $f['mtime']),
                    'page'       => $row['page'] ?? null,
                    'section'    => $row['section'] ?? '',
                    'mtl'        => $row['mtl'],
                    'suffix'     => $m !== $mtl,
                    'style'      => $fields['Style Code'] ?? $fields['Style No.'] ?? $fields['Reference Style No.'] ?? '',
                    'color'      => $fields['Color Description'] ?? $fields['Colorway'] ?? '',
                    'risk'       => $fields['Risk Level'] ?? '',
                    'note'       => $fields['Note'] ?? '',
                    'remark'     => $fields['Remark'] ?? '',
                    'material'   => $fields['Material'] ?? '',
                    'cutting_die' => $fields['Cutting Die No.'] ?? '',
                    'seasons'    => array_values(array_unique($seasons)),
                    'origin_season' => $origin,
                    'fields'     => $fields,
                ];
            }
        }
        if ($mtl !== '' && ! $res['matches'] && $res['files']) {
            $res['note'] = 'Not found in ' . count($res['files']) . ' Order Summary file(s).';
        }

        return $res;
    }
}
