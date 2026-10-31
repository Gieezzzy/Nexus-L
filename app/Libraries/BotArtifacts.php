<?php

namespace App\Libraries;

/**
 * What the assistant can MAKE from the live QC data: charts (the page draws them from a small JSON spec), spreadsheets (CSV / XLSX),
 * PDF documents and a one-report summary. Everything is built from fixed, whitelisted columns and bound values: the user's words only
 * pick WHICH of those to use, never become SQL. Files are written to writable/bot-exports and handed out through an authenticated,
 * expiring download address (bot/file/<token>) that only the user who asked can open.
 */
class BotArtifacts
{
    private const TABLE = 'tbl_dtr_unified_labrefs';
    public const TTL = 3600;                 // seconds a download link works
    public const MAX_ROWS = 5000;
    public const MAX_FILES_PER_HOUR = 20;

    public const CATEGORIES = [
        'bonding' => 'Whole Shoe Bonding', 'fabric' => 'Fabric', 'leather' => 'Leather', 'rubber' => 'Rubber Outsole',
        'wst' => 'Webbing / Shoelace / Thread', 'fthl' => 'Foxing Tape & Heel Logo', 'hardware' => 'Hardware', 'label' => 'Label',
        'sklfm' => 'Sockliner & Foam', 'box' => 'Carton Box', 'insole' => 'Insole Board',
    ];

    private const RESULT_EXPR = "CASE WHEN LOWER(overall_result) IN ('pass','passed','p') THEN 'Pass' WHEN LOWER(overall_result) IN ('fail','failed','f') THEN 'Fail' WHEN LOWER(overall_result) = 'tolerable' THEN 'Tolerable' ELSE 'Other' END";
    private const DATE_EXPR   = "STR_TO_DATE(tested_date, '%d %b %Y')";

    /** dimension => [SQL expression (fixed text), label] */
    private const DIMS = [
        'category' => ['form_slug', 'category'],
        'result'   => [self::RESULT_EXPR, 'result'],
        'month'    => ["DATE_FORMAT(" . self::DATE_EXPR . ", '%Y-%m')", 'month'],
        'supplier' => ["NULLIF(TRIM(supplier), '')", 'supplier'],
        'model'    => ["NULLIF(TRIM(model_style), '')", 'model / style'],
        'material' => ["NULLIF(TRIM(material_type), '')", 'material type'],
        'technician' => ["NULLIF(TRIM(technician), '')", 'technician'],
        'status'   => ["NULLIF(TRIM(status), '')", 'status'],
        'day'      => ["DATE_FORMAT(" . self::DATE_EXPR . ", '%Y-%m-%d')", 'day'],
    ];

    private static function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    // ----------------------------------------------------------------------------------------------- understanding the request

    /** @return array<string,mixed> the filters a sentence asks for: category, result, period */
    public static function filters(string $text): array
    {
        $low = mb_strtolower($text);
        $f   = ['slug' => null, 'result' => null, 'from' => null, 'to' => null, 'period' => 'all time'];
        $cats = ['bonding' => '/\b(bonding|whole\s*shoe)\b/', 'fabric' => '/\bfabrics?\b/', 'leather' => '/\bleather\b/', 'rubber' => '/\brubber\b/',
            'wst' => '/\b(wst|webbing|shoelace|thread)\b/', 'fthl' => '/\b(fthl|foxing|heel\s*logo)\b/', 'hardware' => '/\b(hardware|eyelet|zipper)\b/',
            'label' => '/\blabels?\b/', 'sklfm' => '/\b(sockliner|foam|sklfm)\b/', 'box' => '/\b(box|carton)\b/', 'insole' => '/\binsoles?\b/'];
        foreach ($cats as $slug => $re) {
            if (preg_match($re, $low) && ! preg_match('/\bby\s+(category|type|kind)\b/', $low)) {
                $f['slug'] = $slug;
                break;
            }
        }
        $wantFail = (bool) preg_match('/\b(fail|failed|failure|failures)\b/', $low);
        $wantPass = (bool) preg_match('/\b(pass|passed)\b/', $low);
        $vs       = (bool) preg_match('/\b(vs|versus|and|&|\/|against|compare)\b/', $low);
        if ($wantFail && ! $wantPass && ! $vs) {
            $f['result'] = 'Fail';
        } elseif ($wantPass && ! $wantFail && ! $vs) {
            $f['result'] = 'Pass';
        } elseif (preg_match('/\btolerable\b/', $low) && ! $wantFail && ! $wantPass) {
            $f['result'] = 'Tolerable';
        }

        $today = new \DateTimeImmutable('today');
        if (preg_match('/\b(this|current)\s*month\b/', $low)) {
            $f['from'] = $today->modify('first day of this month')->format('Y-m-d');
            $f['to']   = $today->modify('last day of this month')->format('Y-m-d');
            $f['period'] = $today->format('F Y');
        } elseif (preg_match('/\blast\s*month\b/', $low)) {
            $f['from'] = $today->modify('first day of last month')->format('Y-m-d');
            $f['to']   = $today->modify('last day of last month')->format('Y-m-d');
            $f['period'] = $today->modify('first day of last month')->format('F Y');
        } elseif (preg_match('/\b(this|current)\s*year\b/', $low)) {
            $f['from'] = $today->format('Y') . '-01-01';
            $f['to']   = $today->format('Y') . '-12-31';
            $f['period'] = 'year ' . $today->format('Y');
        } elseif (preg_match('/\blast\s*year\b/', $low)) {
            $y = (int) $today->format('Y') - 1;
            $f['from'] = "$y-01-01";
            $f['to']   = "$y-12-31";
            $f['period'] = "year $y";
        } elseif (preg_match('/\btoday\b/', $low)) {
            $f['from'] = $f['to'] = $today->format('Y-m-d');
            $f['period'] = 'today';
        } elseif (preg_match('/\b(?:last|past)\s+(\d{1,3})\s*(day|week|month|year)s?\b/', $low, $m)) {
            $n = max(1, min(120, (int) $m[1]));
            $f['from'] = $today->modify("-$n {$m[2]}s")->format('Y-m-d');
            $f['to']   = $today->format('Y-m-d');
            $f['period'] = "last $n {$m[2]}" . ($n > 1 ? 's' : '');
        } elseif (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s*(20\d{2})?\b/', $low, $m)) {
            $y  = $m[2] !== '' ? (int) $m[2] : (int) $today->format('Y');
            $d0 = \DateTimeImmutable::createFromFormat('!M Y', ucfirst($m[1]) . ' ' . $y);
            if ($d0) {
                $f['from'] = $d0->format('Y-m-01');
                $f['to']   = $d0->modify('last day of this month')->format('Y-m-d');
                $f['period'] = $d0->format('F Y');
            }
        }

        return $f;
    }

    /** @return array{kind:string,dim:string,split:?string,title:string}|null null = this is not a chart request */
    public static function chartRequest(string $text): ?array
    {
        $low = mb_strtolower($text);
        // strong words always mean a chart; softer ones (picture, image, diagram) only when the sentence is about the data
        $strong = (bool) preg_match('/\b(chart|graph|plot|pie|donut|doughnut|histogram|visuali[sz]e|visuali[sz]ation|trend|infographic)\b/', $low);
        $soft   = (bool) preg_match('/\b(image|picture|photo|diagram|visual)\b/', $low) && preg_match('/\b(reports?|results?|pass(ed)?|fail(ed|ures?)?|categor(y|ies)|suppliers?|months?|data|numbers|tolerable)\b/', $low);
        if (! $strong && ! $soft) {
            return null;
        }
        $kind = 'bar';
        if (preg_match('/\b(pie|donut|doughnut|share|proportion|distribution|percentage)\b/', $low)) {
            $kind = 'pie';
        } elseif (preg_match('/\b(line|trend|over\s*time|timeline|monthly|per\s*month|each\s*month|by\s*month|month\s*by\s*month)\b/', $low)) {
            $kind = 'line';
        }
        $dim = null;
        $map = ['month' => '/\b(by|per|each|every)\s+month|monthly|over\s*time|trend|timeline/', 'supplier' => '/\b(by|per|each)\s+supplier/', 'model' => '/\b(by|per|each)\s+(model|style)/',
            'material' => '/\b(by|per|each)\s+material/', 'technician' => '/\b(by|per|each)\s+(technician|tester|staff)/', 'status' => '/\b(by|per|each)\s+status/',
            'category' => '/\b(by|per|each)\s+(category|type|kind|test\s*type|report\s*type)/', 'result' => '/\b(by|per)\s+result|pass\s*(\/|and|&|vs\.?|versus)\s*fail|fail\s*(\/|and|&|vs\.?|versus)\s*pass/'];
        foreach ($map as $d => $re) {
            if (preg_match($re, $low)) {
                $dim = $d;
                break;
            }
        }
        $mentionsResult = (bool) preg_match('/\b(pass|passed|fail|failed|tolerable|result|results|rate|quality)\b/', $low);
        $f = self::filters($text);
        if ($dim === null) {
            if ($f['result'] !== null && $f['slug'] !== null) {
                // both are fixed: the interesting question is WHEN (days inside a short period, months otherwise)
                $dim = $f['from'] !== null && (strtotime($f['to']) - strtotime($f['from'])) <= 40 * 86400 ? 'day' : 'month';
            } elseif ($f['result'] !== null) {
                $dim = $kind === 'line' ? 'month' : 'category';
            } else {
                $dim = $kind === 'line' ? 'month' : ($f['slug'] === null && ! $mentionsResult ? 'category' : 'result');
            }
        }
        // "pass vs fail by category": the dimension is the category and the bars are split by result
        $split = null;
        if ($dim !== 'result' && $mentionsResult && $f['result'] === null) {
            $split = 'result';
        }
        if ($kind === 'pie' && in_array($dim, ['month', 'day'], true)) {
            $kind = 'bar';
        }
        $dimLabel = self::DIMS[$dim][1];
        $title    = ($f['result'] ? $f['result'] . ' reports' : 'Reports') . ' by ' . $dimLabel . ($f['slug'] ? ' - ' . self::CATEGORIES[$f['slug']] : '') . ($f['period'] !== 'all time' ? ' (' . $f['period'] . ')' : '');

        return ['kind' => $kind, 'dim' => $dim, 'split' => $split, 'title' => $title];
    }

    /** @return array{format:string,subject:string,labref:?string}|null null = this is not an export request */
    public static function exportRequest(string $text): ?array
    {
        $low = mb_strtolower($text);
        $verb = (bool) preg_match('/\b(export|download|generate|create|make|save|give\s*me|send\s*me|get|produce|print)\b/', $low);
        $file = (bool) preg_match('/\b(pdf|excel|xlsx|xls|spreadsheet|csv|file|document|sheet|workbook)\b/', $low);
        if (! $verb || ! $file) {
            return null;
        }
        $format = 'pdf';
        if (preg_match('/\b(excel|xlsx|xls|spreadsheet|workbook)\b/', $low)) {
            $format = 'xlsx';
        } elseif (preg_match('/\bcsv\b/', $low)) {
            $format = 'csv';
        }
        $ref = null;
        if (preg_match('/\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b/i', $text, $m)) {
            $ref = strtoupper($m[1]);
        }
        $one = $ref !== null || (bool) preg_match('/\b(this|that|the|current|last)\s+(report|result|test)\b/', $low);
        $many = (bool) preg_match('/\b(list|all|reports|results|data|table|summary|failed|passed|failures|statistics|stats|by\s+\w+)\b/', $low) && $ref === null;

        return ['format' => $format, 'subject' => $many && ! preg_match('/\b(this|that)\s+report\b/', $low) ? 'list' : ($one ? 'one' : 'list'), 'labref' => $ref];
    }

    // ----------------------------------------------------------------------------------------------- data

    private static function base(array $f): \CodeIgniter\Database\BaseBuilder
    {
        $db = self::db();
        $b  = $db->table(self::TABLE);
        if (! empty($f['slug'])) {
            $b->where('form_slug', $f['slug']);
        }
        if (! empty($f['result'])) {
            $b->where(self::RESULT_EXPR . ' = ' . $db->escape($f['result']), null, false);
        }
        if (! empty($f['from'])) {
            $b->where(self::DATE_EXPR . ' >= ' . $db->escape($f['from']), null, false);
        }
        if (! empty($f['to'])) {
            $b->where(self::DATE_EXPR . ' <= ' . $db->escape($f['to']), null, false);
        }

        return $b;
    }

    /**
     * The chart spec the page draws. @param array{kind:string,dim:string,split:?string,title:string} $req
     * @return array<string,mixed>
     */
    public static function chartSpec(array $req, array $f): array
    {
        $dim   = self::DIMS[$req['dim']];
        $split = $req['split'] ? self::DIMS[$req['split']] : null;
        $b     = self::base($f)->select($dim[0] . ' AS d' . ($split ? ', ' . $split[0] . ' AS s' : '') . ', COUNT(*) AS n', false);
        if (in_array($req['dim'], ['month', 'day'], true)) {
            if (empty($f['from'])) {
                $since = $req['dim'] === 'day' ? (new \DateTimeImmutable('today'))->modify('-29 days') : (new \DateTimeImmutable('first day of this month'))->modify('-11 months');
                $b->where(self::DATE_EXPR . ' >= ' . self::db()->escape($since->format('Y-m-d')), null, false);
            }
            $b->where(self::DATE_EXPR . ' IS NOT NULL', null, false);
        }
        $rows = $b->groupBy($split ? 'd, s' : 'd')->get()->getResultArray();

        // dimension values -> labels
        $labelOf = static function (string $d, ?string $v): string {
            if ($v === null || $v === '') {
                return '(none)';
            }
            if ($d === 'category') {
                return self::CATEGORIES[$v] ?? $v;
            }
            if ($d === 'month') {
                $t = strtotime($v . '-01');

                return $t ? date('M Y', $t) : $v;
            }
            if ($d === 'day') {
                $t = strtotime($v);

                return $t ? date('d M', $t) : $v;
            }

            return $v;
        };
        $totals = [];
        foreach ($rows as $r) {
            $k = (string) $r['d'];
            $totals[$k] = ($totals[$k] ?? 0) + (int) $r['n'];
        }
        if (in_array($req['dim'], ['month', 'day'], true)) {
            ksort($totals);
        } else {
            arsort($totals);
        }
        $keys = array_keys($totals);
        // too many bars: keep the biggest, group the rest (not for months, which keep their order)
        $limit = $req['kind'] === 'pie' ? 8 : 12;
        $other = [];
        if (! in_array($req['dim'], ['month', 'day'], true) && count($keys) > $limit) {
            $other = array_slice($keys, $limit - 1);
            $keys  = array_slice($keys, 0, $limit - 1);
        }
        $labels = array_map(static fn ($k) => $labelOf($req['dim'], $k === '' ? null : $k), $keys);
        if ($other) {
            $labels[] = 'Others (' . count($other) . ')';
        }

        $series = [];
        if ($split) {
            $names = [];
            foreach ($rows as $r) {
                $names[(string) $r['s']] = true;
            }
            $order = ['Pass', 'Tolerable', 'Fail', 'Other'];
            $names = array_keys($names);
            usort($names, static fn ($a, $b) => (array_search($a, $order, true) === false ? 9 : array_search($a, $order, true)) <=> (array_search($b, $order, true) === false ? 9 : array_search($b, $order, true)));
            foreach ($names as $name) {
                $data = array_fill(0, count($labels), 0);
                foreach ($rows as $r) {
                    if ((string) $r['s'] !== $name) {
                        continue;
                    }
                    $i = array_search((string) $r['d'], $keys, true);
                    if ($i === false && in_array((string) $r['d'], $other, true)) {
                        $i = count($labels) - 1;
                    }
                    if ($i !== false) {
                        $data[$i] += (int) $r['n'];
                    }
                }
                $series[] = ['name' => $name, 'data' => $data];
            }
        } else {
            $data = [];
            foreach ($keys as $k) {
                $data[] = $totals[$k];
            }
            if ($other) {
                $data[] = array_sum(array_map(static fn ($k) => $totals[$k], $other));
            }
            $series[] = ['name' => 'Reports', 'data' => $data];
        }

        return ['kind' => $req['kind'], 'title' => $req['title'], 'labels' => $labels, 'series' => $series, 'total' => array_sum($totals), 'dimension' => $req['dim']];
    }

    /** Rows for a list export (fixed, non-sensitive columns). @return list<array<string,mixed>> */
    public static function rows(array $f, int $max = self::MAX_ROWS): array
    {
        return self::base($f)->select('lab_ref_no, tr_no, form_category, material_type, material_description, supplier, model_style, color, po_lot_no, received_date, tested_date, finished_date, technician, status, overall_result', false)
            ->orderBy('id', 'DESC')->limit($max)->get()->getResultArray();
    }

    public const COLUMNS = ['lab_ref_no' => 'Lab Ref No.', 'tr_no' => 'Application / TR No.', 'form_category' => 'Category', 'material_type' => 'Material type', 'material_description' => 'Material',
        'supplier' => 'Supplier', 'model_style' => 'Model / Style', 'color' => 'Color', 'po_lot_no' => 'PO / Lot', 'received_date' => 'Received', 'tested_date' => 'Tested',
        'finished_date' => 'Finished', 'technician' => 'Technician', 'status' => 'Status', 'overall_result' => 'Result'];

    // ----------------------------------------------------------------------------------------------- files

    private static function dir(): string
    {
        $d = WRITEPATH . 'bot-exports' . DIRECTORY_SEPARATOR;
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
            @file_put_contents($d . 'index.html', '');
            @file_put_contents($d . '.htaccess', "Require all denied\n");
        }

        return $d;
    }

    /** Gives a finished file a download token. @return array{token:string,name:string,url:string,size:int}|null null = too many files */
    public static function register(string $path, string $downloadName, string $mime, int $userId): ?array
    {
        $dir = self::dir();
        // clean up: expired files go, and one person cannot make an unlimited number
        $recent = 0;
        foreach (glob($dir . 'tmp_*') ?: [] as $stale) {          // half-made files from an interrupted request
            if (is_file($stale) && filemtime($stale) < time() - 3600) {
                @unlink($stale);
            }
        }
        foreach (glob($dir . '*.json') ?: [] as $meta) {
            $m = json_decode((string) @file_get_contents($meta), true) ?: [];
            if (($m['expires'] ?? 0) < time() - 86400) {
                @unlink($dir . ($m['file'] ?? ''));
                @unlink($meta);
                continue;
            }
            if ((int) ($m['user_id'] ?? 0) === $userId && ($m['created'] ?? 0) > time() - 3600) {
                $recent++;
            }
        }
        if ($recent >= self::MAX_FILES_PER_HOUR) {
            @unlink($path);

            return null;
        }
        $token = bin2hex(random_bytes(16));
        $ext   = pathinfo($downloadName, PATHINFO_EXTENSION);
        $file  = $token . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
        rename($path, $dir . $file);
        file_put_contents($dir . $token . '.json', json_encode(['file' => $file, 'name' => $downloadName, 'mime' => $mime, 'user_id' => $userId, 'created' => time(), 'expires' => time() + self::TTL]));

        return ['token' => $token, 'name' => $downloadName, 'url' => site_url('bot/file/' . $token), 'size' => (int) filesize($dir . $file)];
    }

    /** @return array{path:string,name:string,mime:string}|null null = unknown, expired, or somebody else's */
    public static function open(string $token, int $userId): ?array
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $dir  = self::dir();
        $meta = json_decode((string) @file_get_contents($dir . $token . '.json'), true);
        if (! is_array($meta) || (int) $meta['user_id'] !== $userId || (int) $meta['expires'] < time() || ! is_file($dir . $meta['file'])) {
            return null;
        }

        return ['path' => $dir . $meta['file'], 'name' => (string) $meta['name'], 'mime' => (string) $meta['mime']];
    }

    private static function tmp(string $ext): string
    {
        return self::dir() . 'tmp_' . bin2hex(random_bytes(6)) . '.' . $ext;
    }

    /** Spreadsheet-formula safe: a cell that starts with = + - @ would be run by Excel. */
    private static function cell(?string $v): string
    {
        $v = (string) $v;

        return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
    }

    /** @param list<string> $head @param list<list<string>> $rows */
    public static function csv(array $head, array $rows): string
    {
        $p  = self::tmp('csv');
        $fh = fopen($p, 'wb');
        fwrite($fh, "\xEF\xBB\xBF");                                    // Excel opens UTF-8 correctly with this
        fputcsv($fh, $head);
        foreach ($rows as $r) {
            fputcsv($fh, array_map([self::class, 'cell'], $r));
        }
        fclose($fh);

        return $p;
    }

    /** A real .xlsx (zip of XML) without any library. @param list<string> $head @param list<list<string>> $rows */
    public static function xlsx(array $head, array $rows, string $sheet = 'Report'): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }
        $col = static function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26) . $s;
            }

            return $s;
        };
        $esc = static fn (string $v): string => htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        foreach ($head as $i => $h) {
            $w = 14;
            foreach (array_slice($rows, 0, 200) as $r) {
                $w = max($w, min(48, mb_strlen((string) ($r[$i] ?? '')) + 2));
            }
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData><row r="1">';
        foreach ($head as $i => $h) {
            $xml .= '<c r="' . $col($i) . '1" t="inlineStr" s="1"><is><t>' . $esc($h) . '</t></is></c>';
        }
        $xml .= '</row>';
        foreach ($rows as $ri => $r) {
            $xml .= '<row r="' . ($ri + 2) . '">';
            foreach ($r as $i => $v) {
                $v = (string) $v;
                $ref = $col($i) . ($ri + 2);
                $xml .= is_numeric($v) && ! preg_match('/^0\d/', $v) && strlen($v) < 15
                    ? '<c r="' . $ref . '"><v>' . $v . '</v></c>'
                    : '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $p   = self::tmp('xlsx');
        $zip = new \ZipArchive();
        if ($zip->open($p, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheet), 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return $p;
    }

    /** Headless Chrome / Edge on the server prints HTML to PDF (same engine as the report sheet). @return string|null path of the PDF */
    public static function pdfFromHtml(string $bodyHtml, string $title, bool $landscape = false): ?string
    {
        $browser = null;
        foreach (['C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'] as $c) {
            if (is_file($c)) {
                $browser = $c;
                break;
            }
        }
        if ($browser === null) {
            return null;
        }
        $dir  = self::dir();
        $id   = bin2hex(random_bytes(6));
        $html = $dir . 'tmp_' . $id . '.html';
        $pdf  = $dir . 'tmp_' . $id . '.pdf';
        $css  = '@page{size:A4 ' . ($landscape ? 'landscape' : 'portrait') . ';margin:10mm}body{font:11px/1.35 Segoe UI,Arial,sans-serif;color:#0f172a}h1{font-size:18px;margin:0 0 4px}h2{font-size:13px;margin:14px 0 6px}'
            . '.meta{color:#475569;margin:0 0 10px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cbd5e1;padding:4px 6px;text-align:left;vertical-align:top}th{background:#e2e8f0}'
            . 'tr:nth-child(even) td{background:#f8fafc}.pass{color:#15803d;font-weight:600}.fail{color:#b91c1c;font-weight:600}.tol{color:#b45309;font-weight:600}.foot{margin-top:12px;color:#64748b;font-size:9px}';
        file_put_contents($html, '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES) . '</title><style>' . $css . '</style></head><body>' . $bodyHtml . '</body></html>');
        $profile = $dir . 'chrome_profile';
        $cmd = '"' . $browser . '" --headless=new --disable-gpu --disable-extensions --no-first-run --no-pdf-header-footer --user-data-dir="' . $profile . '" --print-to-pdf="' . $pdf . '" "file:///' . str_replace('\\', '/', $html) . '" 2>&1';
        @shell_exec($cmd);
        @unlink($html);
        if (! is_file($pdf) || filesize($pdf) < 500) {
            @unlink($pdf);

            return null;
        }

        return $pdf;
    }

    private static function resultClass(string $r): string
    {
        $l = strtolower($r);

        return in_array($l, ['pass', 'passed', 'p'], true) ? 'pass' : (in_array($l, ['fail', 'failed', 'f'], true) ? 'fail' : ($l === 'tolerable' ? 'tol' : ''));
    }

    /** A PDF of a list of reports. @return string|null */
    public static function listPdf(array $rows, string $title, string $subtitle): ?string
    {
        $h = '<h1>' . htmlspecialchars($title) . '</h1><p class="meta">' . htmlspecialchars($subtitle) . ' - ' . count($rows) . ' report(s) - made ' . date('d M Y H:i') . '</p><table><tr>';
        $cols = ['lab_ref_no', 'form_category', 'material_description', 'supplier', 'model_style', 'tested_date', 'technician', 'overall_result'];
        foreach ($cols as $c) {
            $h .= '<th>' . htmlspecialchars(self::COLUMNS[$c]) . '</th>';
        }
        $h .= '</tr>';
        foreach (array_slice($rows, 0, 1500) as $r) {
            $h .= '<tr>';
            foreach ($cols as $c) {
                $v = (string) ($r[$c] ?? '');
                $h .= '<td' . ($c === 'overall_result' ? ' class="' . self::resultClass($v) . '"' : '') . '>' . htmlspecialchars(mb_substr($v, 0, 70)) . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</table>' . (count($rows) > 1500 ? '<p class="foot">Only the first 1,500 rows are printed; the Excel export has all of them.</p>' : '') . '<p class="foot">NEXUS-L QC Lab - generated by the assistant from live data.</p>';

        return self::pdfFromHtml($h, $title, true);
    }

    /** One report on one page: header facts and every test with its reading and result. @return array{pdf:?string,error?:string} */
    public static function reportPdf(string $labRef): array
    {
        $r = self::db()->table(self::TABLE)->where('lab_ref_no', $labRef)->get(1)->getRowArray();
        if (! $r) {
            return ['pdf' => null, 'error' => 'not_found'];
        }
        $h = '<h1>' . htmlspecialchars($r['lab_ref_no']) . ' - ' . htmlspecialchars((string) $r['form_category']) . '</h1><p class="meta">Overall result: <span class="' . self::resultClass((string) $r['overall_result']) . '">' . htmlspecialchars((string) ($r['overall_result'] ?: 'not rated')) . '</span> - status ' . htmlspecialchars((string) $r['status']) . '</p><table>';
        $facts = ['Application / TR No.' => 'tr_no', 'Material type' => 'material_type', 'Material' => 'material_description', 'Supplier' => 'supplier', 'Model / Style' => 'model_style', 'Color' => 'color',
            'PO / Lot' => 'po_lot_no', 'MTL No.' => 'mtl_no', 'Received' => 'received_date', 'Tested' => 'tested_date', 'Finished' => 'finished_date', 'Technician' => 'technician', 'Authorized by' => 'authorized_by'];
        foreach ($facts as $label => $col) {
            if (trim((string) ($r[$col] ?? '')) !== '') {
                $h .= '<tr><th style="width:28%">' . htmlspecialchars($label) . '</th><td>' . htmlspecialchars((string) $r[$col]) . '</td></tr>';
            }
        }
        $h .= '</table>';
        $items = (json_decode((string) $r['test_items_json'], true)['items'] ?? []);
        if ($items) {
            $h .= '<h2>Tests (' . count($items) . ')</h2><table><tr><th>Test</th><th>Method</th><th>Requirement</th><th>Reading</th><th>Result</th></tr>';
            foreach ($items as $it) {
                $res = (string) ($it['result_status'] ?? $it['raw_rating'] ?? '');
                $h .= '<tr><td>' . htmlspecialchars((string) ($it['test_name'] ?? '')) . '</td><td>' . htmlspecialchars((string) ($it['test_method'] ?? '')) . '</td><td>' . htmlspecialchars((string) ($it['requirement_spec'] ?? '')) . '</td><td>' . htmlspecialchars((string) ($it['actual_reading'] ?? '')) . '</td><td class="' . self::resultClass($res) . '">' . htmlspecialchars($res) . '</td></tr>';
            }
            $h .= '</table>';
        }
        $h .= '<p class="foot">Summary made ' . date('d M Y H:i') . ' by the NEXUS-L assistant from the saved report. The official report sheet is the signed document.</p>';

        return ['pdf' => self::pdfFromHtml($h, $labRef), 'row' => $r];
    }

    /** A chart picture (PNG data URL made by the page) as a one-page PDF. */
    public static function imagePdf(string $dataUrl, string $title): ?string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) || strlen($m[1]) > 4_000_000) {
            return null;
        }
        $png = base64_decode($m[1], true);
        if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        return self::pdfFromHtml('<h1>' . htmlspecialchars($title) . '</h1><p class="meta">Made ' . date('d M Y H:i') . ' from live NEXUS-L data</p><img style="max-width:100%" src="data:image/png;base64,' . $m[1] . '">', $title, true);
    }
}
