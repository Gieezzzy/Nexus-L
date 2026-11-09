<?php

namespace App\Libraries;

/**
 * DsrShareService
 * Manages fast lookup, indexing, and parsing of Vans DSR / CFM Excel workbooks (.xls / .xlsx)
 * from \\192.168.0.8\团队文件-確認dsr into dbnexus (tbl_dtr_dsr_files + tbl_dtr_dsr).
 */
class DsrShareService
{
    public const DEFAULT_SHARE = '\\\\192.168.0.8\\团队文件-確認dsr';
    public const DEFAULT_USER  = 'Qc_02';
    public const DEFAULT_PASS  = 'W4Wt0l&Rk&';

    private static bool $mounted = false;

    public static function shareRoot(): string
    {
        $root = (string) SystemSettings::get('dsr.share_path', self::DEFAULT_SHARE);
        return rtrim($root !== '' ? $root : self::DEFAULT_SHARE, '\\/');
    }

    /**
     * Ensures the SMB share is mounted in the current Windows session and reachable.
     */
    public static function ensureMounted(): bool
    {
        $root = self::shareRoot();
        if (@is_dir($root)) {
            self::$mounted = true;
            return true;
        }
        if (self::$mounted) {
            return @is_dir($root);
        }
        $user = (string) SystemSettings::get('dsr.share_user', self::DEFAULT_USER);
        $pass = (string) SystemSettings::get('dsr.share_pass', self::DEFAULT_PASS);
        $cmd  = sprintf('net use %s %s /user:%s /persistent:no 2>&1', escapeshellarg($root), escapeshellarg($pass), escapeshellarg($user));
        @shell_exec($cmd);
        self::$mounted = @is_dir($root);
        return self::$mounted;
    }

    /**
     * Ensures tbl_dtr_dsr_files exists in dbnexus for sub-10ms file-level search by MTLNo or filename.
     */
    public static function ensureTables($db = null): void
    {
        $db = $db ?: \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_dsr_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            relative_path VARCHAR(500) NOT NULL,
            folder_name VARCHAR(120) NOT NULL DEFAULT "",
            season VARCHAR(40) NOT NULL DEFAULT "",
            mtl_nos VARCHAR(500) NOT NULL DEFAULT "",
            style_no VARCHAR(80) NOT NULL DEFAULT "",
            style_desc VARCHAR(191) NOT NULL DEFAULT "",
            colorway VARCHAR(191) NOT NULL DEFAULT "",
            size_category VARCHAR(80) NOT NULL DEFAULT "",
            factory VARCHAR(120) NOT NULL DEFAULT "",
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_mtime DATETIME NULL,
            is_parsed TINYINT(1) NOT NULL DEFAULT 0,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            indexed_at DATETIME NOT NULL,
            UNIQUE KEY uq_rel_path (relative_path(255)),
            KEY ix_mtl_nos (mtl_nos(191)),
            KEY ix_file_name (file_name(191)),
            KEY ix_season (season)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /**
     * Extracts all Vans MTL# tokens (e.g. VN000ZB8BKO, VN0A3MTJQ33, VN-0QG3BKA) from a filename or string.
     *
     * @return list<string>
     */
    public static function extractMtlTokens(string $text): array
    {
        $tokens = [];
        $cleanedText = (string) preg_replace('/[-_]?CFM\b/i', '', strtoupper($text));
        if (preg_match_all('/\b(VN[0-9A-Z\-]{6,16})\b/i', $cleanedText, $m)) {
            foreach ($m[1] as $tok) {
                $tok = trim($tok, '-_');
                $clean = str_replace('-', '', $tok);
                $tokens[$tok] = $tok;
                if ($clean !== $tok) {
                    $tokens[$clean] = $clean;
                }
            }
        }
        if (preg_match_all('/MTL#\s*([A-Z0-9\-]{6,18})/i', $cleanedText, $m2)) {
            foreach ($m2[1] as $tok) {
                $tok = trim($tok, '-_');
                $tokens[$tok] = $tok;
            }
        }
        return array_values($tokens);
    }

    /**
     * Extracts normalized Season code (e.g. S27, F26, H26) from folder name, filename, or Season header text.
     */
    public static function normalizeSeason(string $raw, string $fallbackFolder = ''): string
    {
        $u = strtoupper(trim($raw));
        if (preg_match('/\b([SFH]\d{2})\b/', $u, $m)) {
            return $m[1];
        }
        if (preg_match('/(SPRING|SUMMER|FALL|HOLIDAY|WINTER)\s*[\'"]?(\d{2})\b/i', $u, $m)) {
            $word = strtoupper($m[1]);
            $p = match ($word) {
                'SPRING', 'SUMMER' => 'S',
                'FALL'             => 'F',
                'HOLIDAY', 'WINTER'=> 'H',
                default            => 'S',
            };
            return $p . $m[2];
        }
        $fu = strtoupper(trim($fallbackFolder));
        if (preg_match('/\b([SFH]\d{2})\b/', $fu, $m2)) {
            return $m2[1];
        }
        return '';
    }

    /**
     * Splits a Vans CFM color string such as "Marshmallow White (Coloro) 045-89-02" or "Military Olive ( Pantone 19-0622 TPG )"
     * or "BKO - Pearlized Woodland BLACK/OLIVE" into ['code' => ..., 'name' => ...].
     *
     * @return array{code:string, name:string}
     */
    public static function splitColor(string $colorJ, string $colorK = '', string $headerColorway = ''): array
    {
        $j = trim((string) preg_replace('/\s+/', ' ', $colorJ));
        $k = trim((string) preg_replace('/\s+/', ' ', $colorK));
        $src = $j !== '' && strtolower($j) !== 'no color name listed' ? $j : $k;

        $code = '';
        $name = '';

        // 1. Check for Coloro 3-part numeric code like 045-89-02 or Pantone 19-0622 TPG / TCX
        if (preg_match('/\b(\d{2,3}-\d{2}-\d{2}|\d{2}-\d{4}\s*(?:TPG|TCX|TPX)?)\b/i', $src . ' ' . $k, $cm)) {
            $code = strtoupper(trim($cm[1]));
        }

        // 2. Clean color name from K first if K is concise (e.g. "Marshmallow 045-89-02" -> "Marshmallow")
        $baseName = $k !== '' && strtolower($k) !== 'no color name listed' ? $k : $j;
        if ($code !== '') {
            $baseName = trim(str_ireplace($code, '', $baseName));
        }
        $baseName = trim((string) preg_replace('/\(\s*(?:Coloro|Pantone|Stock|[^)]*)\s*\)/i', '', $baseName));
        $baseName = trim((string) preg_replace('/\s+/', ' ', $baseName), " \t\n\r\0\x0B-_/,");
        $name = $baseName;

        // 3. Fallback to header colorway (e.g. "BKO - Pearlized Woodland BLACK/OLIVE")
        if ($code === '' && $headerColorway !== '' && preg_match('/^([A-Z0-9]{2,6})\s*-\s*(.+)$/i', trim($headerColorway), $hm)) {
            $code = strtoupper(trim($hm[1]));
            if ($name === '') {
                $name = trim($hm[2]);
            }
        }

        return ['code' => $code, 'name' => $name];
    }

    /**
     * Searches for DSR files and BOM rows matching a query (MTL#, ProductionCode, StyleNo, or file name).
     * Combines fast MySQL lookup (tbl_dtr_dsr_files + tbl_dtr_dsr + tbl_dtr_shoe_list) with live SMB share scan on cache miss.
     */
    public static function search(string $query, string $prodCode = ''): array
    {
        $db = \Config\Database::connect();
        self::ensureTables($db);

        $q = trim($query);
        $p = trim($prodCode);
        $resolvedMtl = '';
        $shoeInfo    = null;

        // 1. If a ProductionCode / PO No. was given (or if $q is a ProductionCode in tbl_dtr_shoe_list), resolve its MTL#
        foreach (array_filter([$p, $q]) as $cand) {
            $shoe = $db->table('tbl_dtr_shoe_list')
                ->groupStart()
                    ->where('ProdCode', $cand)
                    ->orWhere('MTL', $cand)
                ->groupEnd()
                ->orderBy('ID', 'DESC')
                ->get(1)
                ->getRowArray();
            if ($shoe) {
                $shoeInfo = $shoe;
                if (! empty($shoe['MTL'])) {
                    $resolvedMtl = trim((string) $shoe['MTL']);
                    break;
                }
            }
        }

        $searchTerm = $resolvedMtl !== '' ? $resolvedMtl : $q;
        if ($searchTerm === '') {
            return ['ok' => true, 'query' => $q, 'resolved_mtl' => '', 'shoe' => $shoeInfo, 'files' => []];
        }

        // 2. Search tbl_dtr_dsr_files first
        $indexedFiles = $db->table('tbl_dtr_dsr_files')
            ->groupStart()
                ->like('mtl_nos', $searchTerm)
                ->orLike('file_name', $searchTerm)
                ->orLike('style_no', $searchTerm)
                ->orLike('style_desc', $searchTerm)
            ->groupEnd()
            ->orderBy('file_mtime', 'DESC')
            ->limit(25)
            ->get()
            ->getResultArray();

        // 3. Also check legacy tbl_dtr_dsr distinct SourceFiles if not in tbl_dtr_dsr_files yet
        $legacyRows = [];
        if (empty($indexedFiles)) {
            $legacyRows = $db->table('tbl_dtr_dsr')
                ->select('SourceFile, MTLNo, StyleNumber, StyleDescription, Colorway')
                ->where('MTLNo', $searchTerm)
                ->limit(25)
                ->get()
                ->getResultArray();
        }

        // 4. If no indexed file matches $searchTerm, scan only the newest seasonal folders on \\192.168.0.8\团队文件-確認dsr
        if (empty($indexedFiles) && empty($legacyRows)) {
            $liveFound = self::scanShareForTerm($searchTerm);
            if ($liveFound) {
                $indexedFiles = array_merge($indexedFiles, $liveFound);
            }
        }

        // Deduplicate & merge file list
        $filesByKey = [];
        foreach ($indexedFiles as $f) {
            $key = strtolower(trim((string) $f['file_name']));
            $rel = (string) ($f['relative_path'] ?? $f['file_name']);
            $fld = (string) ($f['folder_name'] ?? '');
            $szn = (string) ($f['season'] ?? self::normalizeSeason((string) $f['file_name'], $fld));
            $cleanMtls = self::extractMtlTokens((string) ($f['mtl_nos'] ?? '') . ' ' . (string) ($f['file_name'] ?? ''));
            $mtl = ! empty($cleanMtls) ? implode(', ', $cleanMtls) : (string) ($f['mtl_nos'] ?? '');
            $filesByKey[$key] = [
                'id'            => (int) ($f['id'] ?? 0),
                'file_name'     => (string) $f['file_name'],
                'rel_path'      => $rel,
                'relative_path' => $rel,
                'season_folder' => $fld ?: $szn,
                'folder_name'   => $fld,
                'season'        => $szn,
                'mtl_no'        => $cleanMtls[0] ?? $mtl,
                'mtl_nos'       => $mtl,
                'style_no'      => (string) ($f['style_no'] ?? ''),
                'style_desc'    => (string) ($f['style_desc'] ?? ''),
                'colorway'      => (string) ($f['colorway'] ?? ''),
                'size_category' => (string) ($f['size_category'] ?? ''),
                'is_parsed'     => (int) ($f['is_parsed'] ?? 0),
                'source'        => 'share_index',
            ];
        }
        foreach ($legacyRows as $lr) {
            $fn  = trim((string) ($lr['SourceFile'] ?? ''));
            if ($fn === '') {
                continue;
            }
            $key = strtolower($fn);
            $szn = self::normalizeSeason($fn);
            $mtl = (string) ($lr['MTLNo'] ?? '');
            if (! isset($filesByKey[$key])) {
                $filesByKey[$key] = [
                    'id'            => 0,
                    'file_name'     => $fn,
                    'rel_path'      => $fn,
                    'relative_path' => $fn,
                    'season_folder' => $szn,
                    'folder_name'   => '',
                    'season'        => $szn,
                    'mtl_no'        => $mtl,
                    'mtl_nos'       => $mtl,
                    'style_no'      => (string) ($lr['StyleNumber'] ?? ''),
                    'style_desc'    => (string) ($lr['StyleDescription'] ?? ''),
                    'colorway'      => (string) ($lr['Colorway'] ?? ''),
                    'size_category' => '',
                    'is_parsed'     => 1,
                    'source'        => 'db_legacy',
                ];
            } else {
                if ($filesByKey[$key]['style_no'] === '' && ! empty($lr['StyleNumber'])) {
                    $filesByKey[$key]['style_no'] = (string) $lr['StyleNumber'];
                }
                if ($filesByKey[$key]['style_desc'] === '' && ! empty($lr['StyleDescription'])) {
                    $filesByKey[$key]['style_desc'] = (string) $lr['StyleDescription'];
                }
                if ($filesByKey[$key]['colorway'] === '' && ! empty($lr['Colorway'])) {
                    $filesByKey[$key]['colorway'] = (string) $lr['Colorway'];
                }
            }
        }

        return [
            'ok'           => true,
            'query'        => $q,
            'resolved_mtl' => $resolvedMtl ?: $searchTerm,
            'shoe'         => $shoeInfo,
            'files'        => array_values($filesByKey),
        ];
    }

    /**
     * Scans the DSR SMB share for files matching $term, prioritizing recent seasonal folders (S27, F27, S26, F26, 2026*, 2025*).
     * Automatically registers matched files in tbl_dtr_dsr_files.
     */
    public static function scanShareForTerm(string $term): array
    {
        if ($term === '' || ! self::ensureMounted()) {
            return [];
        }
        $root = self::shareRoot();
        $db   = \Config\Database::connect();
        self::ensureTables($db);

        $topDirs = @scandir($root) ?: [];
        // Prioritize newer folders first (S27, F27, S26, 2026*, 2025*, then others)
        usort($topDirs, static function (string $a, string $b): int {
            $score = static function (string $d): int {
                if (preg_match('/^[SFH]2[6789]$/i', $d)) return 100;
                if (str_starts_with($d, '2026')) return 90;
                if (preg_match('/^[SFH]2[45]$/i', $d)) return 80;
                if (str_starts_with($d, '2025')) return 70;
                if (str_contains($d, '之前') || str_contains($d, '前')) return 10;
                return 50;
            };
            return $score($b) <=> $score($a);
        });

        $found = [];
        $now   = date('Y-m-d H:i:s');

        foreach ($topDirs as $d) {
            if ($d === '.' || $d === '..' || str_starts_with($d, '~$') || str_contains($d, '之前') || str_contains($d, '前')) {
                continue;
            }
            $full = $root . '\\' . $d;
            if (preg_match('/\.xlsx?$/i', $d)) {
                if (stripos($d, $term) !== false && is_file($full)) {
                    $found[] = self::registerShareFile($db, $full, $d, '', $now);
                }
                continue;
            }
            if (! is_dir($full)) {
                continue;
            }
            // Scan level 1 inside seasonal/monthly folder (check filename string in memory BEFORE SMB stat/is_dir!)
            foreach (@scandir($full) ?: [] as $f) {
                if ($f === '.' || $f === '..' || str_starts_with($f, '~$')) {
                    continue;
                }
                $sub = $full . '\\' . $f;
                if (preg_match('/\.xlsx?$/i', $f)) {
                    if (stripos($f, $term) !== false) {
                        $found[] = self::registerShareFile($db, $sub, $d . '\\' . $f, $d, $now);
                        if (count($found) >= 15) {
                            break 2;
                        }
                    }
                    continue;
                }
                if (! str_contains($f, '.') && is_dir($sub)) {
                    foreach (@scandir($sub) ?: [] as $f2) {
                        if ($f2 === '.' || $f2 === '..' || str_starts_with($f2, '~$')) {
                            continue;
                        }
                        if (preg_match('/\.xlsx?$/i', $f2) && stripos($f2, $term) !== false) {
                            $sub2 = $sub . '\\' . $f2;
                            $found[] = self::registerShareFile($db, $sub2, $d . '\\' . $f . '\\' . $f2, $d, $now);
                            if (count($found) >= 15) {
                                break 3;
                            }
                        }
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Inserts or updates a single file record in tbl_dtr_dsr_files.
     */
    public static function registerShareFile($db, string $fullPath, string $relPath, string $folderName, ?string $now = null): array
    {
        $now    = $now ?: date('Y-m-d H:i:s');
        $fname  = basename($fullPath);
        $mtls   = implode(',', self::extractMtlTokens($fname));
        $season = self::normalizeSeason($fname, $folderName);
        $size   = (int) @filesize($fullPath);
        $mtime  = @filemtime($fullPath);
        $mtimeStr = $mtime ? date('Y-m-d H:i:s', $mtime) : null;

        $existing = $db->table('tbl_dtr_dsr_files')->where('relative_path', $relPath)->get()->getRowArray();
        $row = [
            'file_name'     => $fname,
            'relative_path' => $relPath,
            'folder_name'   => $folderName,
            'season'        => $season,
            'mtl_nos'       => $mtls,
            'file_size'     => $size,
            'file_mtime'    => $mtimeStr,
            'indexed_at'    => $now,
        ];
        if ($existing) {
            $db->table('tbl_dtr_dsr_files')->where('id', $existing['id'])->update($row);
            return array_merge($existing, $row);
        }
        $db->table('tbl_dtr_dsr_files')->insert($row);
        $row['id'] = (int) $db->insertID();
        $row['style_no'] = '';
        $row['style_desc'] = '';
        $row['colorway'] = '';
        $row['size_category'] = '';
        $row['is_parsed'] = 0;
        return $row;
    }

    /**
     * Loads and returns the complete BOM / CFM details for a selected file (or MTL#).
     * If the file has not been parsed into tbl_dtr_dsr yet (or force=true), parses the .xls/.xlsx on the fly via Python xlrd/openpyxl,
     * caches the rows into tbl_dtr_dsr and tbl_dtr_dsr_files, and returns structured header + BOM component rows.
     */
    public static function getFileDetails(string $fileOrRelPath, string $mtlNo = '', bool $forceParse = false): array
    {
        $db = \Config\Database::connect();
        self::ensureTables($db);

        $cleanMtls = self::extractMtlTokens($mtlNo . ' ' . $fileOrRelPath);
        $cleanMtl  = $cleanMtls[0] ?? trim(explode(',', $mtlNo)[0]);
        $cleanMtl  = (string) preg_replace('/[-_]?CFM$/i', '', $cleanMtl);

        $fileRec = null;
        if ($fileOrRelPath !== '') {
            $normRel = ltrim(str_replace('/', '\\', $fileOrRelPath), '\\');
            $baseTry = basename($normRel);
            $fileRec = $db->table('tbl_dtr_dsr_files')
                ->where('relative_path', $normRel)
                ->orWhere('relative_path', $fileOrRelPath)
                ->orWhere('file_name', $baseTry)
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();
        }
        if (! $fileRec && $cleanMtl !== '') {
            $fileRec = $db->table('tbl_dtr_dsr_files')
                ->like('mtl_nos', $cleanMtl)
                ->orLike('file_name', $cleanMtl)
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();
        }

        $baseName = $fileRec ? (string) $fileRec['file_name'] : basename(str_replace('/', '\\', $fileOrRelPath));

        // Check existing rows in tbl_dtr_dsr
        $existingRows = [];
        if (! $forceParse && $baseName !== '') {
            $existingRows = $db->table('tbl_dtr_dsr')
                ->where('SourceFile', $baseName)
                ->get()
                ->getResultArray();
        }
        if (! $forceParse && empty($existingRows) && $cleanMtl !== '') {
            $existingRows = $db->table('tbl_dtr_dsr')
                ->where('MTLNo', $cleanMtl)
                ->get()
                ->getResultArray();
        }

        // If not in tbl_dtr_dsr yet (or if we have the file on the share and want full header metadata), parse the Excel file from the share
        $parsedMeta = null;
        if (empty($existingRows) || $forceParse || ($fileRec && empty($fileRec['style_no']))) {
            $fullPath = '';
            if (self::ensureMounted()) {
                $root = self::shareRoot();
                $relCand = $fileRec && ! empty($fileRec['relative_path']) ? str_replace('/', '\\', (string) $fileRec['relative_path']) : '';
                $argCand = $fileOrRelPath !== '' ? ltrim(str_replace('/', '\\', $fileOrRelPath), '\\') : '';
                if ($relCand !== '' && is_file($root . '\\' . $relCand)) {
                    $fullPath = $root . '\\' . $relCand;
                } elseif ($argCand !== '' && is_file($root . '\\' . $argCand)) {
                    $fullPath = $root . '\\' . $argCand;
                } elseif ($baseName !== '' || $cleanMtl !== '') {
                    $term  = $cleanMtl !== '' ? $cleanMtl : pathinfo($baseName, PATHINFO_FILENAME);
                    $found = self::scanShareForTerm($term);
                    if (! empty($found[0]['relative_path'])) {
                        $fullPath = $root . '\\' . str_replace('/', '\\', (string) $found[0]['relative_path']);
                        $fileRec  = $found[0];
                        $baseName = (string) ($fileRec['file_name'] ?? $baseName);
                    }
                }
            }
            if ($fullPath !== '' && is_file($fullPath)) {
                $parsedMeta = self::parseAndStoreExcel($db, $fullPath, $fileRec);
                if (! empty($parsedMeta['db_rows'])) {
                    $existingRows = $parsedMeta['db_rows'];
                }
            }
        }

        if (empty($existingRows) && (empty($parsedMeta) || empty($parsedMeta['ok']))) {
            $msg = 'Could not find or read DSR file details for ' . ($baseName ?: $cleanMtl ?: $fileOrRelPath);
            if (! empty($parsedMeta['error'])) {
                $msg .= ' (' . trim((string) $parsedMeta['error']) . ')';
            }
            return [
                'ok'      => false,
                'message' => $msg,
                'error'   => $msg,
            ];
        }

        // Build clean structured header & component list
        $first = $existingRows[0] ?? [];
        $colorwayRaw = (string) ($parsedMeta['header']['colorway'] ?? $first['Colorway'] ?? '');
        $cwSplit     = self::splitColor('', '', $colorwayRaw);
        $season      = (string) ($parsedMeta['header']['season_code'] ?? ($fileRec['season'] ?? '') ?: self::normalizeSeason($baseName));
        $sizeCatRaw  = (string) ($parsedMeta['header']['size_category'] ?? ($fileRec['size_category'] ?? ''));

        $header = [
            'source_file'      => $baseName ?: (string) ($first['SourceFile'] ?? ''),
            'brand'            => (string) ($parsedMeta['header']['brand'] ?? 'Vans'),
            'mtl_no'           => (string) ($parsedMeta['header']['mtl_no'] ?? $first['MTLNo'] ?? $mtlNo),
            'style_no'         => (string) ($parsedMeta['header']['style_no'] ?? $first['StyleNumber'] ?? ''),
            'style_desc'       => (string) ($parsedMeta['header']['style_desc'] ?? $first['StyleDescription'] ?? ''),
            'colorway'         => $colorwayRaw,
            'color_code'       => $cwSplit['code'],
            'color_name'       => $cwSplit['name'] ?: $colorwayRaw,
            'season'           => $season ?: 'S27',
            'season_raw'       => (string) ($parsedMeta['header']['season_raw'] ?? ''),
            'size_category'    => $sizeCatRaw,
            'factory'          => (string) ($parsedMeta['header']['factory'] ?? ($fileRec['factory'] ?? '')),
            'construction'     => (string) ($parsedMeta['header']['construction'] ?? ''),
            'development_code' => (string) ($parsedMeta['header']['development_code'] ?? ''),
        ];

        $components     = [];
        $currentSection = 'General';
        foreach ($existingRows as $r) {
            $no   = trim((string) ($r['No'] ?? ''));
            $comp = trim((string) ($r['ComponentorLocation'] ?? ''));
            $code = trim((string) ($r['VFMaterialCode'] ?? ''));
            $mat  = trim((string) ($r['Material'] ?? ''));
            $supCode = trim((string) ($r['MaterialSupplierCode'] ?? ''));
            $intVans = trim((string) ($r['Internal NameVans1'] ?? ''));
            $size    = trim((string) ($r['Size'] ?? ''));
            $sup     = trim((string) ($r['Supplier'] ?? ''));
            $colJ    = trim((string) ($r['Internal NamVans2'] ?? ''));
            $colK    = trim((string) ($r['Color'] ?? ''));
            $comm    = trim((string) ($r['Comments'] ?? ''));

            // Section header detection: row has a section title in ComponentorLocation and no VFMaterialCode / Material / Supplier
            if ($comp !== '' && $code === '' && $mat === '' && $sup === '' && $colK === '') {
                $currentSection = $comp;
                continue;
            }
            if ($comp === '' && $code === '' && $mat === '') {
                continue;
            }

            $cSplit = self::splitColor($colJ, $colK, $colorwayRaw);
            $cCode  = trim((string) ($r['ColorCode'] ?? ''));
            $cName  = trim((string) ($r['ColorName'] ?? ''));
            if ($cCode === '' || strtolower($cCode) === 'no color code listed') {
                $cCode = $cSplit['code'];
            }
            if ($cName === '' || strtolower($cName) === 'no color name listed') {
                $cName = $cSplit['name'];
            }

            $infType    = self::inferInterlinkMaterialType($currentSection, $comp, $mat);
            $infSubType = self::inferMaterialSubType($currentSection, $comp, $mat);
            $infPkg     = self::inferTestPackage($currentSection, $comp, $mat);

            $components[] = [
                'no'                      => $no,
                'section_no'              => $no,
                'section'                 => $currentSection,
                'component'               => $comp,
                'component_name'          => $comp,
                'material_code'           => $code,
                'vf_material_code'        => $code,
                'supplier_code'           => $supCode ?: $intVans,
                'internal_name'           => $intVans,
                'material'                => $mat,
                'material_spec'           => $mat,
                'size'                    => $size,
                'size_val'                => $size,
                'supplier'                => $sup,
                'supplier_name'           => $sup,
                'color_raw'               => $colJ !== '' ? $colJ : $colK,
                'color_text'              => $colK,
                'color_code'              => $cCode,
                'color_name'              => $cName,
                'comments'                => $comm,
                'season'                  => $header['season'],
                'inferred_type'           => $infType,
                'interlink_material_type' => $infType,
                'inferred_subtype'        => $infSubType,
                'material_sub_type'       => $infSubType,
                'inferred_package'        => $infPkg,
                'test_package'            => $infPkg,
            ];
        }

        return [
            'ok'         => true,
            'file'       => $fileRec,
            'header'     => $header,
            'components' => $components,
        ];
    }

    /**
     * Infers the InterLink "Material Type" LOV value from a DSR component section, name, and specification.
     */
    public static function inferInterlinkMaterialType(string $section, string $comp, string $mat): string
    {
        $all = strtolower($section . ' ' . $comp . ' ' . $mat);
        if (str_contains($all, 'foxing') || str_contains($all, 'outsole') || str_contains($all, 'bumper') || str_contains($all, 'midsole') || str_contains($all, 'sockliner') || str_contains($all, 'footbed') || str_contains($all, 'insole') || str_contains($all, 'heel tag')) {
            return 'Bottom Components (Outsole / Midsole / Footbed / Foxing Tape)';
        }
        if (str_contains($all, 'suede') || str_contains($all, 'leather') || str_contains($all, 'nubuck') || str_contains($all, 'cow') || str_contains($all, 'goat') || str_contains($all, 'pig')) {
            return 'Leather';
        }
        if (str_contains($all, 'eyelet') || str_contains($all, 'rivet') || str_contains($all, 'buckle') || str_contains($all, 'grommet') || str_contains($all, 'zipper') || str_contains($all, 'jewel') || str_contains($all, 'hardware') || str_contains($all, 'metal')) {
            return 'Hardware & trims';
        }
        if (str_contains($all, 'lace') || str_contains($all, 'shoelace') || str_contains($all, 'webbing') || str_contains($all, 'thread') || str_contains($all, 'stitching') || str_contains($all, 'binding') || str_contains($all, 'elastic') || str_contains($all, 'velcro')) {
            return 'Laces / Webbing / Tapes / Thread';
        }
        if (str_contains($all, 'box') || str_contains($all, 'carton') || str_contains($all, 'packaging') || str_contains($all, 'tissue') || str_contains($all, 'hangtag') || str_contains($all, 'sticker')) {
            return 'Packaging';
        }
        if (str_contains($all, 'label') || str_contains($all, 'transfer') || str_contains($all, 'logo') || str_contains($all, 'print')) {
            return 'Labels / Heat Transfers / Prints';
        }
        if (str_contains($all, 'canvas') || str_contains($all, 'woven') || str_contains($all, 'cotton') || str_contains($all, 'polyester') || str_contains($all, 'mesh') || str_contains($all, 'textile') || str_contains($all, 'drill') || str_contains($all, 'fleece') || str_contains($all, 'nylon')) {
            return 'Fabric';
        }
        return 'Upper';
    }

    /**
     * Infers the InterLink "Material Sub-Type" (Denim, Knit, Knit (Brush or Fleece), Laminated Fabric, Nonwoven, Woven, Others).
     */
    public static function inferMaterialSubType(string $section, string $comp, string $mat): string
    {
        $all = strtolower($section . ' ' . $comp . ' ' . $mat);
        if (str_contains($all, 'denim')) return 'Denim';
        if (str_contains($all, 'fleece') || str_contains($all, 'brush')) return 'Knit (Brush or Fleece)';
        if (str_contains($all, 'knit') || str_contains($all, 'mesh') || str_contains($all, 'jersey')) return 'Knit';
        if (str_contains($all, 'non-woven') || str_contains($all, 'nonwoven')) return 'Nonwoven';
        if (str_contains($all, 'laminat')) return 'Laminated Fabric';
        if (str_contains($all, 'canvas') || str_contains($all, 'woven') || str_contains($all, 'drill') || str_contains($all, 'twill') || str_contains($all, 'ripstop')) return 'Woven';
        return 'Others';
    }

    /**
     * Infers the InterLink "Test Package(s)" name from the component/material.
     */
    public static function inferTestPackage(string $section, string $comp, string $mat): string
    {
        $all = strtolower($section . ' ' . $comp . ' ' . $mat);
        if (str_contains($all, 'foxing') || str_contains($all, 'bumper') || str_contains($all, 'friction tape')) {
            return 'VANS Foxing Tape - Rubber';
        }
        if (str_contains($all, 'outsole')) {
            return 'VANS Outsole - Rubber';
        }
        if (str_contains($all, 'carton') || str_contains($all, 'box')) {
            return 'Carton Box (#ODUSACB)';
        }
        return '';
    }

    /**
     * Parses a Vans CFM / DSR Excel workbook (.xls or .xlsx) using Python (xlrd / openpyxl),
     * populates tbl_dtr_dsr and updates tbl_dtr_dsr_files.
     */
    public static function parseAndStoreExcel($db, string $fullPath, ?array $fileRec = null): array
    {
        $pyScript = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'parse_dsr_excel.py';
        if (! is_dir(dirname($pyScript))) {
            @mkdir(dirname($pyScript), 0775, true);
        }

        $code = <<<'PY'
import sys, os, json, re

path = sys.argv[1]
ext = os.path.splitext(path)[1].lower()

rows_grid = []
sheet_name = ''

if ext == '.xls':
    import xlrd
    wb = xlrd.open_workbook(path)
    sh = wb.sheet_by_index(0)
    sheet_name = sh.name
    for r in range(sh.nrows):
        row = []
        for c in range(min(20, sh.ncols)):
            v = sh.cell_value(r, c)
            if isinstance(v, float) and v.is_integer():
                v = str(int(v)) if c > 0 else f"{v:.1f}"
            else:
                v = str(v).strip()
            row.append(re.sub(r'[\r\n]+', ' ', v).strip())
        rows_grid.append(row)
else:
    import openpyxl
    wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
    sh = wb.worksheets[0]
    sheet_name = sh.title
    for r_vals in sh.iter_rows(values_only=True):
        row = []
        for c, v in enumerate((r_vals or [])[:20]):
            if v is None:
                row.append('')
            elif isinstance(v, float) and v.is_integer():
                row.append(str(int(v)) if c > 0 else f"{v:.1f}")
            else:
                row.append(re.sub(r'[\r\n]+', ' ', str(v)).strip())
        rows_grid.append(row)

header = {
    'sheet_name': sheet_name,
    'brand': 'Vans',
    'colorway': '',
    'season_raw': '',
    'development_code': '',
    'factory': '',
    'mtl_no': '',
    'construction': '',
    'style_no': '',
    'style_desc': '',
    'size_category': '',
    'model_size': '',
}

header_end = 13
for idx, r in enumerate(rows_grid[:25]):
    c0 = (r[0] if len(r) > 0 else '').strip()
    c1 = (r[1] if len(r) > 1 else '').strip().lower()
    c2 = (r[2] if len(r) > 2 else '').strip()
    c3 = (r[3] if len(r) > 3 else '').strip().lower()
    c4 = (r[4] if len(r) > 4 else '').strip()
    if c0 == '#' or 'component or location' in c1 or 'vf material code' in c2.lower():
        header_end = idx
        break
    if c1 == 'brand' and c2: header['brand'] = c2
    elif 'colorway' in c1 and c2: header['colorway'] = c2
    elif 'development code' in c1 and c2: header['development_code'] = c2
    elif 'material #' in c1 and c2: header['mtl_no'] = c2
    elif 'style number' in c1 and c2: header['style_no'] = c2
    elif 'style description' in c1 and c2: header['style_desc'] = c2
    elif 'size category' in c1 and c2: header['size_category'] = c2
    elif 'model size' in c1 and c2: header['model_size'] = c2

    if c3 == 'season' and c4: header['season_raw'] = c4
    elif c3 == 'factory' and c4: header['factory'] = c4
    elif 'construction' in c3 and c4: header['construction'] = c4

items = []
for r in rows_grid[header_end + 1:]:
    while len(r) < 14:
        r.append('')
    if not any(r[:13]):
        continue
    items.append({
        'No': r[0],
        'ComponentorLocation': r[1],
        'VFMaterialCode': r[2],
        'ConfiguratorCreated': r[3],
        'MaterialSupplierCode': r[4],
        'InternalNameVans1': r[5],
        'Material': r[6],
        'Size': r[7],
        'Supplier': r[8],
        'InternalNamVans2': r[9],
        'Color': r[10],
        'GraphicURL': r[11],
        'Comments': r[12],
    })

print(json.dumps({'ok': True, 'header': header, 'items': items}, ensure_ascii=False))
PY;
        file_put_contents($pyScript, $code);

        $pyBin   = is_file('C:\\Python314\\python.exe') ? 'C:\\Python314\\python.exe' : 'python';
        $ext     = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $tmpFile = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'dsr_tmp_' . md5($fullPath) . '.' . ($ext ?: 'xls');
        $runPath = @copy($fullPath, $tmpFile) ? $tmpFile : $fullPath;

        $cmd = sprintf('%s -X utf8 %s %s 2>&1', escapeshellarg($pyBin), escapeshellarg($pyScript), escapeshellarg($runPath));
        $out = (string) @shell_exec($cmd);
        if ($runPath === $tmpFile && is_file($tmpFile)) {
            @unlink($tmpFile);
        }
        $data = json_decode($out, true);
        if (! is_array($data) || empty($data['ok'])) {
            return ['ok' => false, 'error' => $out];
        }

        $fname  = basename($fullPath);
        $hdr    = $data['header'] ?? [];
        $mtls   = self::extractMtlTokens($fname . ' ' . ($hdr['mtl_no'] ?? ''));
        $mainMtl= $hdr['mtl_no'] ?: ($mtls[0] ?? '');
        $season = self::normalizeSeason((string) ($hdr['season_raw'] ?? ''), $fname);
        $hdr['season_code'] = $season;
        $hdr['mtl_no']      = $mainMtl;

        // Replace rows for this SourceFile in tbl_dtr_dsr
        $db->table('tbl_dtr_dsr')->where('SourceFile', $fname)->delete();
        $dbRows = [];
        foreach ($data['items'] ?? [] as $it) {
            $cSplit = self::splitColor((string) ($it['InternalNamVans2'] ?? ''), (string) ($it['Color'] ?? ''), (string) ($hdr['colorway'] ?? ''));
            $jSplit = self::splitColor((string) ($it['InternalNamVans2'] ?? ''), '', '');
            $row = [
                'SourceFile'                  => $fname,
                'MTLNo'                       => $mainMtl,
                'MTLfrmfilename'              => $mainMtl !== '' ? ($mainMtl . '-CFM') : pathinfo($fname, PATHINFO_FILENAME),
                'MaterialNo'                  => $mainMtl,
                'StyleNumber'                 => (string) ($hdr['style_no'] ?? ''),
                'StyleDescription'            => (string) ($hdr['style_desc'] ?? ''),
                'Colorway'                    => (string) ($hdr['colorway'] ?? ''),
                'No'                          => (string) ($it['No'] ?? ''),
                'ComponentorLocation'         => (string) ($it['ComponentorLocation'] ?? ''),
                'VFMaterialCode'              => (string) ($it['VFMaterialCode'] ?? ''),
                'ConfiguratorCreated'         => (string) ($it['ConfiguratorCreated'] ?? ''),
                'MaterialSupplierCode'        => (string) ($it['MaterialSupplierCode'] ?? ''),
                'Internal NameVans1'          => (string) ($it['InternalNameVans1'] ?? ''),
                'Material'                    => (string) ($it['Material'] ?? ''),
                'Size'                        => (string) ($it['Size'] ?? ''),
                'Supplier'                    => (string) ($it['Supplier'] ?? ''),
                'Internal NamVans2'           => (string) ($it['InternalNamVans2'] ?? ''),
                'Color'                       => (string) ($it['Color'] ?? ''),
                'GraphicURL'                  => (string) ($it['GraphicURL'] ?? ''),
                'Comments'                    => (string) ($it['Comments'] ?? ''),
                'Internal NameVans2ColorName' => $jSplit['name'] ?: 'No color name listed',
                'Internal NameVans2ColorCode' => $jSplit['code'] ?: 'No color code listed',
                'ColorName'                   => $cSplit['name'] ?: 'No color name listed',
                'ColorCode'                   => $cSplit['code'] ?: 'No color code listed',
            ];
            $dbRows[] = $row;
        }
        if ($dbRows) {
            $cols = array_keys($dbRows[0]);
            $colSql = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $cols));
            $rowPlaceholders = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            foreach (array_chunk($dbRows, 50) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), $rowPlaceholders));
                $binds = [];
                foreach ($chunk as $r) {
                    foreach ($cols as $c) {
                        $binds[] = $r[$c];
                    }
                }
                $db->query("INSERT INTO `tbl_dtr_dsr` ({$colSql}) VALUES {$placeholders}", $binds);
            }
        }

        // Update tbl_dtr_dsr_files metadata
        $updFile = [
            'season'        => $season,
            'mtl_nos'       => implode(',', array_unique(array_filter(array_merge([$mainMtl], $mtls)))),
            'style_no'      => (string) ($hdr['style_no'] ?? ''),
            'style_desc'    => (string) ($hdr['style_desc'] ?? ''),
            'colorway'      => (string) ($hdr['colorway'] ?? ''),
            'size_category' => (string) ($hdr['size_category'] ?? ''),
            'factory'       => (string) ($hdr['factory'] ?? ''),
            'is_parsed'     => 1,
            'row_count'     => count($dbRows),
            'indexed_at'    => date('Y-m-d H:i:s'),
        ];
        if ($fileRec && ! empty($fileRec['id'])) {
            $db->table('tbl_dtr_dsr_files')->where('id', $fileRec['id'])->update($updFile);
        } else {
            $relPath = str_starts_with($fullPath, self::shareRoot()) ? ltrim(substr($fullPath, strlen(self::shareRoot())), '\\/') : $fname;
            $folder  = dirname($relPath) !== '.' ? explode('\\', str_replace('/', '\\', $relPath))[0] : '';
            $reg     = self::registerShareFile($db, $fullPath, $relPath, $folder);
            $db->table('tbl_dtr_dsr_files')->where('id', $reg['id'])->update($updFile);
        }

        return ['ok' => true, 'header' => $hdr, 'db_rows' => $dbRows];
    }
}
