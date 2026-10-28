<?php

namespace App\Libraries;

/**
 * MachineFileReader - reads test-machine output files (.xlsx / .csv) from \\192.168.32.45\OUTDATA (read-only: files are
 * copied to writable/cache first) and turns cells into report fields using admin-defined rules (tbl_machine_maps),
 * so a change of columns / rows / sheet per report type is a setting, not a code change.
 *
 * Rule types (JSON objects):
 *  - row_by_labref : find the Lab Ref in row `labRow` (from column `startCol`), then read `resultRow` across the
 *                    columns starting there into `fields` (list, one field per column).
 *  - column_range  : read column `col` from `rowFrom` to `rowTo`; cell i goes to `pattern` with {n} = i (1-based).
 *                    Optional `stats` prefix adds <prefix>Min / Max / Average of the numbers read.
 *  - cell          : one cell (`row`, `col`) -> `field`.
 *  - anchor_cell   : find the cell whose text equals `anchor` (anywhere), then move `rowOffset` / `colOffset` -> `field`.
 *                    Survives columns/rows moving around as long as the label text stays.
 *  - label_rows    : (U60 bonding) every row whose `labelCol` text matches `labelRegex` (default ^M(\d+)$) is point n;
 *                    `cols` = [{col, pattern, maxN, stats}] reads one value per column for that point.
 *  - point_grid    : (U60 csv) cells reading "pointN" anywhere; values sit `rowOffset` rows below in the same column
 *                    (`reads` = [{pattern, rowOffset, maxN, stats}]).
 *  - header_value  : first non-empty cell below the header cell whose text equals `header` -> `field`.
 * `cell` accepts `afterColon` (keep the text after ":") and `altCol` + `altWhen` {field, in[]} (use another column).
 * The OUTDATA root is an admin setting (tbl_app_settings.outdata_root).
 */
class MachineFileReader
{
    public const NET_ROOT = '\\\\192.168.32.45\\OUTDATA\\';

    /** OUTDATA root: admin setting `outdata_root` (Report Designer > Locations), default NET_ROOT. */
    public static function root(): string
    {
        static $root = null;
        if ($root === null) {
            $root = self::NET_ROOT;
            try {
                $db = \Config\Database::connect();
                if ($db->tableExists('tbl_app_settings')) {
                    $row = $db->table('tbl_app_settings')->where('skey', 'outdata_root')->get()->getRowArray();
                    $v   = trim((string) ($row['svalue'] ?? ''));
                    if ($v !== '') {
                        $root = rtrim($v, '\\/') . '\\';
                    }
                }
            } catch (\Throwable $e) {
                // keep the default
            }
        }

        return $root;
    }

    /** A folder below OUTDATA: letters, digits, space, _ - . and \ separators only; never "..". */
    public static function safeFolder(string $folder): ?string
    {
        $folder = trim(str_replace('/', '\\', $folder), '\\ ');
        if ($folder === '' || str_contains($folder, '..') || ! preg_match('/^[A-Za-z0-9 _.\\\\-]{1,120}$/', $folder)) {
            return null;
        }

        return $folder;
    }

    public static function safeRef(string $ref): ?string
    {
        $ref = trim($ref);

        return preg_match('/^[A-Za-z0-9 _.()#-]{1,80}$/', $ref) ? $ref : null;
    }

    /** Finds the file for a lab reference (full reference first, then the part before the first "-"). */
    public function findFile(string $folder, string $labRef): ?string
    {
        $folder = self::safeFolder($folder);
        $labRef = self::safeRef($labRef);
        if ($folder === null || $labRef === null) {
            return null;
        }
        $dash       = strpos($labRef, '-');
        $baseRef    = ($dash !== false && $dash > 0) ? substr($labRef, 0, $dash) : $labRef;
        $candidates = array_values(array_unique([$labRef, $baseRef]));
        $cacheDir   = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'outdata_samples' . DIRECTORY_SEPARATOR . $folder;
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        // The share is the source of truth: copy again when it holds a newer file (retests overwrite the same name);
        // the cache keeps serving when the share is not reachable.
        foreach ($candidates as $c) {
            foreach (['.xlsx', '.csv'] as $ext) {
                $net   = self::root() . $folder . '\\' . $c . $ext;
                $local = $cacheDir . DIRECTORY_SEPARATOR . $c . $ext;
                $netT  = @is_file($net) ? @filemtime($net) : false;
                if ($netT !== false && (! is_file($local) || $netT > (int) @filemtime($local))) {
                    @copy($net, $local);
                }
                if (is_file($local)) {
                    return $local; // never the network path: the source is only ever read
                }
            }
        }

        return null;
    }

    /** @return list<string> sheet names of an .xlsx (empty for csv) */
    public function sheets(string $path): array
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            return [];
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $names = [];
        $wb    = $zip->getFromName('xl/workbook.xml');
        $zip->close();
        if ($wb !== false && ($x = @simplexml_load_string($wb)) && isset($x->sheets->sheet)) {
            foreach ($x->sheets->sheet as $sh) {
                $names[] = (string) $sh['name'];
            }
        }

        return $names;
    }

    /**
     * @param string $sheet sheet name, or a 1-based number; empty = first sheet that has cells
     *
     * @return array<int,array<int,string>> grid[row][col] (1-based)
     */
    public function grid(string $path, string $sheet = ''): array
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            $grid = [];
            if (($fh = @fopen($path, 'r')) !== false) {
                $r = 1;
                while (($row = fgetcsv($fh)) !== false) {
                    foreach ($row as $c => $val) {
                        $grid[$r][$c + 1] = (string) $val;
                    }
                    $r++;
                }
                fclose($fh);
            }

            return $grid;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false && ($sx = @simplexml_load_string($ss))) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $run) {
                        $t .= (string) $run->t;
                    }
                    $shared[] = $t;
                }
            }
        }

        $xml = false;
        $sheet = trim($sheet);
        if ($sheet !== '') {
            $target = $this->sheetTarget($zip, $sheet);
            if ($target !== null) {
                $xml = $zip->getFromName($target);
            }
        }
        if ($xml === false) {
            for ($i = 1; $i <= 8; $i++) {
                $cand = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
                if ($cand !== false && strpos($cand, '<c ') !== false) {
                    $xml = $cand;
                    break;
                }
            }
        }
        $zip->close();
        if ($xml === false || ! ($sx = @simplexml_load_string($xml)) || ! isset($sx->sheetData->row)) {
            return [];
        }

        $grid = [];
        foreach ($sx->sheetData->row as $row) {
            $r = (int) $row['r'];
            foreach ($row->c as $c) {
                $letters = preg_replace('/[0-9]/', '', (string) $c['r']);
                $col     = 0;
                for ($i = 0, $n = strlen($letters); $i < $n; $i++) {
                    $col = $col * 26 + (ord($letters[$i]) - 64);
                }
                $type = (string) $c['t'];
                $val  = isset($c->v) ? (string) $c->v : '';
                if ($type === 's') {
                    $val = $shared[(int) $val] ?? $val;
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                }
                $grid[$r][$col] = $val;
            }
        }

        return $grid;
    }

    private function sheetTarget(\ZipArchive $zip, string $sheet): ?string
    {
        $wb   = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $rels === false) {
            return null;
        }
        $wx = @simplexml_load_string($wb);
        $rx = @simplexml_load_string($rels);
        if (! $wx || ! $rx || ! isset($wx->sheets->sheet)) {
            return null;
        }

        $map = [];
        foreach ($rx->Relationship as $rel) {
            $map[(string) $rel['Id']] = (string) $rel['Target'];
        }
        $i = 0;
        foreach ($wx->sheets->sheet as $sh) {
            $i++;
            $rid = (string) $sh->attributes('r', true)['id'];
            if ((ctype_digit($sheet) && (int) $sheet === $i) || strcasecmp((string) $sh['name'], $sheet) === 0) {
                $t = $map[$rid] ?? null;

                return $t === null ? null : 'xl/' . ltrim(preg_replace('#^/?xl/#', '', $t), '/');
            }
        }

        return null;
    }

    /**
     * Applies the rules to a grid.
     *
     * @param list<array<string,mixed>> $rules
     *
     * @return array<string,mixed> field => value
     */
    public function apply(array $grid, array $rules, string $labRef): array
    {
        $out = [];
        foreach ($rules as $r) {
            switch ($r['type'] ?? '') {
                case 'row_by_labref':
                    $out = array_merge($out, $this->ruleRowByLabRef($grid, $r, $labRef));
                    break;

                case 'column_range':
                    $out = array_merge($out, $this->ruleColumnRange($grid, $r));
                    break;

                case 'label_rows':
                    $out = array_merge($out, $this->ruleLabelRows($grid, $r));
                    break;

                case 'point_grid':
                    $out = array_merge($out, $this->rulePointGrid($grid, $r));
                    break;

                case 'header_value':
                    $pos = $this->findText($grid, (string) ($r['header'] ?? ''));
                    if ($pos !== null && ! empty($r['field']) && (empty($r['onlyIfMissing']) || ! isset($out[$r['field']]))) {
                        for ($o = 1; $o <= 50; $o++) {
                            $v = $this->clean($grid[$pos[0] + $o][$pos[1]] ?? null);
                            if ($v !== null) {
                                $out[(string) $r['field']] = $v;
                                break;
                            }
                        }
                    }
                    break;

                case 'cell':
                    $col = (int) ($r['col'] ?? 0);
                    if (! empty($r['altCol']) && ! empty($r['altWhen']['field']) && in_array($out[$r['altWhen']['field']] ?? null, (array) ($r['altWhen']['in'] ?? []), true)) {
                        $col = (int) $r['altCol'];
                    }
                    $v = $this->clean($grid[(int) ($r['row'] ?? 0)][$col] ?? null);
                    if ($v !== null && ! empty($r['afterColon']) && is_string($v) && str_contains($v, ':')) {
                        $v = trim(explode(':', $v, 2)[1]);
                        $v = $v === '' ? null : $v;
                    }
                    if ($v !== null && ! empty($r['field'])) {
                        $out[(string) $r['field']] = $v;
                    }
                    break;

                case 'anchor_cell':
                    $pos = $this->findText($grid, (string) ($r['anchor'] ?? ''));
                    if ($pos !== null && ! empty($r['field'])) {
                        $v = $this->clean($grid[$pos[0] + (int) ($r['rowOffset'] ?? 0)][$pos[1] + (int) ($r['colOffset'] ?? 1)] ?? null);
                        if ($v !== null) {
                            $out[(string) $r['field']] = $v;
                        }
                    }
                    break;
            }
        }

        return $out;
    }

    private function clean($v)
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        if ($v === '' || $v === '---') {
            return null;
        }

        return is_numeric($v) ? round((float) $v, 2) : $v;
    }

    /** @return array{0:int,1:int}|null */
    private function findText(array $grid, string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        foreach ($grid as $row => $cells) {
            foreach ($cells as $col => $val) {
                if (strcasecmp(trim((string) $val), $text) === 0) {
                    return [(int) $row, (int) $col];
                }
            }
        }

        return null;
    }

    private function ruleRowByLabRef(array $grid, array $r, string $labRef): array
    {
        $labRow = (int) ($r['labRow'] ?? 0);
        $resRow = (int) ($r['resultRow'] ?? 0);
        $start  = max(1, (int) ($r['startCol'] ?? 3));
        $fields = array_values(array_filter((array) ($r['fields'] ?? []), 'strlen'));
        $found  = null;

        foreach ($grid[$labRow] ?? [] as $col => $val) {
            if ($col >= $start && strcasecmp(trim((string) $val), trim($labRef)) === 0) {
                $found = (int) $col;
                break;
            }
        }
        // some machines write a single sample: optionally read from the first column when the reference is not in the header
        if ($found === null && ! empty($r['fallbackFirstCol']) && isset($grid[$resRow][$start]) && is_numeric($grid[$resRow][$start])) {
            $found = $start;
        }
        if ($found === null) {
            return [];
        }

        $out = [];
        foreach ($fields as $i => $f) {
            $v = $this->clean($grid[$resRow][$found + $i] ?? null);
            if ($v !== null) {
                $out[$f] = $v;
            }
        }

        return $out;
    }

    private function ruleColumnRange(array $grid, array $r): array
    {
        $col     = (int) ($r['col'] ?? 0);
        $from    = (int) ($r['rowFrom'] ?? 0);
        $to      = min((int) ($r['rowTo'] ?? 0), $from + 200);
        $pattern = (string) ($r['pattern'] ?? '');
        $max     = (int) ($r['maxN'] ?? 0);
        if ($col < 1 || $from < 1 || $to < $from || ! str_contains($pattern, '{n}')) {
            return [];
        }

        $out = [];
        $nums = [];
        for ($row = $from; $row <= $to; $row++) {
            $n = $row - $from + 1;
            if ($max > 0 && $n > $max) {
                break;
            }
            $v = $this->clean($grid[$row][$col] ?? null);
            if ($v === null || ! is_numeric($v)) {
                continue;
            }
            $out[str_replace('{n}', (string) $n, $pattern)] = $v;
            $nums[] = (float) $v;
        }
        $p = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($r['stats'] ?? ''));
        if ($p !== '' && $nums) {
            $out[$p . 'Min']     = round(min($nums), 2);
            $out[$p . 'Max']     = round(max($nums), 2);
            $out[$p . 'Average'] = round(array_sum($nums) / count($nums), 2);
        }

        return $out;
    }

    /** Numeric value of a machine cell: blanks and "-" are no reading (same as the Access import). */
    private function num($v): ?float
    {
        $t = trim(str_replace('-', '', (string) $v));

        return ($t !== '' && is_numeric($t)) ? (float) $t : null;
    }

    private function addStats(array &$out, string $prefix, array $nums): void
    {
        $p = preg_replace('/[^A-Za-z0-9_]/', '', $prefix);
        if ($p !== '' && $nums) {
            $out[$p . 'Min']     = round(min($nums), 2);
            $out[$p . 'Max']     = round(max($nums), 2);
            $out[$p . 'Average'] = round(array_sum($nums) / count($nums), 2);
            $out[$p . 'Count']   = count($nums);
        }
    }

    private function ruleLabelRows(array $grid, array $r): array
    {
        $labelCol = max(1, (int) ($r['labelCol'] ?? 1));
        $regex    = (string) ($r['labelRegex'] ?? '/^M(\d+)$/i');
        if (@preg_match($regex, '') === false) {
            return [];
        }
        $out  = [];
        $nums = [];
        foreach ($grid as $cells) {
            if (! preg_match($regex, trim((string) ($cells[$labelCol] ?? '')), $m)) {
                continue;
            }
            $n = (int) ($m[1] ?? 0);
            foreach ((array) ($r['cols'] ?? []) as $ci => $c) {
                $v = $this->num($cells[(int) ($c['col'] ?? 0)] ?? null);
                if ($n < 1 || $n > (int) ($c['maxN'] ?? 45) || $v === null || empty($c['pattern'])) {
                    continue;
                }
                $out[str_replace('{n}', (string) $n, (string) $c['pattern'])] = round($v, 2);
                $nums[$ci][] = $v;
            }
        }
        foreach ((array) ($r['cols'] ?? []) as $ci => $c) {
            $this->addStats($out, (string) ($c['stats'] ?? ''), $nums[$ci] ?? []);
        }

        return $out;
    }

    private function rulePointGrid(array $grid, array $r): array
    {
        $out  = [];
        $nums = [];
        $seen = [];
        foreach ($grid as $row => $cells) {
            foreach ($cells as $col => $val) {
                $t = strtolower(str_replace([' ', '-', '_', ':', "\xC2\xA0", "\r", "\n", "\t"], '', (string) $val));
                if (strncmp($t, 'point', 5) !== 0 || ! ctype_digit(substr($t, 5)) || isset($seen[(int) substr($t, 5)])) {
                    continue;
                }
                $n   = (int) substr($t, 5);
                $any = false;
                foreach ((array) ($r['reads'] ?? []) as $ri => $rd) {
                    $v = $this->num($grid[$row + (int) ($rd['rowOffset'] ?? 2)][$col] ?? null);
                    if ($v === null || $n > (int) ($rd['maxN'] ?? 45) || empty($rd['pattern'])) {
                        continue;
                    }
                    $out[str_replace('{n}', (string) $n, (string) $rd['pattern'])] = round($v, 2);
                    $nums[$ri][] = $v;
                    $any = true;
                }
                if ($any) {
                    $seen[$n] = true;
                }
            }
        }
        foreach ((array) ($r['reads'] ?? []) as $ri => $rd) {
            $this->addStats($out, (string) ($rd['stats'] ?? ''), $nums[$ri] ?? []);
        }

        return $out;
    }
}
