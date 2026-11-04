<?php

namespace App\Libraries;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads and updates the register workbooks of the Laboratory Documents (no spreadsheet library is installed, so this works on the
 * xlsx package itself).
 *
 *   read()   the cells of one sheet, the table that holds the register and the pictures of its header
 *   write()  puts the register back into the SAME sheet of the SAME workbook: the header block, the logos, the styles and the page
 *            setup stay as they are; the table (header row + data rows) is replaced, its Excel "table" definition, the print area,
 *            the merged header cells and the right logo are moved to the new size. The original file is copied to a backup first.
 */
class LabDocXlsx
{
    private const NS   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RNS  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const DRAW = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    // ------------------------------------------------------------------------------------------------ helpers

    public static function colLetter(int $n): string    // 1 -> A
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    public static function colIndex(string $letters): int   // A -> 1
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n;
    }

    private static function dom(string $xml): DOMDocument
    {
        $d = new DOMDocument();
        $d->preserveWhiteSpace = true;
        $d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        return $d;
    }

    /** @return array{sheet:string,table:?string,drawing:?string} part names of a sheet (by its name, or the n-th sheet) */
    private static function locate(ZipArchive $z, string|int $sheet): array
    {
        $wb   = self::dom((string) $z->getFromName('xl/workbook.xml'));
        $rels = self::dom((string) $z->getFromName('xl/_rels/workbook.xml.rels'));
        $x    = new DOMXPath($wb);
        $x->registerNamespace('m', self::NS);
        $x->registerNamespace('r', self::RNS);
        $rid = null;
        $i   = 0;
        foreach ($x->query('//m:sheets/m:sheet') as $sh) {
            /** @var DOMElement $sh */
            if ((is_int($sheet) && $i === $sheet) || (is_string($sheet) && $sh->getAttribute('name') === $sheet)) {
                $rid = $sh->getAttributeNS(self::RNS, 'id');
                break;
            }
            $i++;
        }
        if ($rid === null) {
            throw new RuntimeException('Sheet not found in the workbook.');
        }
        $target = null;
        foreach ($rels->getElementsByTagName('Relationship') as $r) {
            if ($r->getAttribute('Id') === $rid) {
                $target = $r->getAttribute('Target');
            }
        }
        if ($target === null) {
            throw new RuntimeException('Sheet part not found.');
        }
        $sheetPart = 'xl/' . ltrim($target, '/');
        $out       = ['sheet' => $sheetPart, 'table' => null, 'drawing' => null];
        $relName   = 'xl/worksheets/_rels/' . basename($sheetPart) . '.rels';
        $sr        = $z->getFromName($relName);
        if ($sr !== false) {
            foreach (self::dom($sr)->getElementsByTagName('Relationship') as $r) {
                $type = basename($r->getAttribute('Type'));
                $path = self::resolve('xl/worksheets/', $r->getAttribute('Target'));
                if ($type === 'table') {
                    $out['table'] = $path;
                } elseif ($type === 'drawing') {
                    $out['drawing'] = $path;
                }
            }
        }
        return $out;
    }

    private static function resolve(string $base, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        $parts = explode('/', rtrim($base, '/'));
        foreach (explode('/', $target) as $p) {
            if ($p === '..') {
                array_pop($parts);
            } elseif ($p !== '.' && $p !== '') {
                $parts[] = $p;
            }
        }
        return implode('/', $parts);
    }

    /** @return list<string> */
    private static function sharedStrings(ZipArchive $z): array
    {
        $raw = $z->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
            return [];
        }
        $out = [];
        foreach (self::dom($raw)->getElementsByTagNameNS(self::NS, 'si') as $si) {
            $t = '';
            foreach ($si->getElementsByTagNameNS(self::NS, 't') as $tn) {
                // phonetic runs (<rPh>) are not part of the text
                $p = $tn->parentNode;
                if ($p && $p->nodeName === 'rPh') {
                    continue;
                }
                $t .= $tn->textContent;
            }
            $out[] = $t;
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------------ read

    /**
     * @return array{rows:array<int,array<int,string>>,table_ref:?string,logos:list<array{col:int,part:string,ext:string}>,max_col:int}
     *   rows       row number -> column number (1-based) -> text
     *   table_ref  the Excel table range of the sheet, for example "A4:B48"
     *   logos      the pictures of the header, left to right, with their part names inside the package
     */
    public static function read(string $path, string|int $sheet): array
    {
        $z = new ZipArchive();
        if ($z->open($path) !== true) {
            throw new RuntimeException('The workbook cannot be opened (damaged or locked).');
        }
        try {
            $loc  = self::locate($z, $sheet);
            $ss   = self::sharedStrings($z);
            $doc  = self::dom((string) $z->getFromName($loc['sheet']));
            $rows = [];
            $max  = 0;
            foreach ($doc->getElementsByTagNameNS(self::NS, 'c') as $c) {
                /** @var DOMElement $c */
                if (! preg_match('/^([A-Z]+)(\d+)$/', $c->getAttribute('r'), $m)) {
                    continue;
                }
                $v = $c->getElementsByTagNameNS(self::NS, 'v')->item(0);
                $t = $c->getAttribute('t');
                if ($t === 'inlineStr') {
                    $txt = '';
                    foreach ($c->getElementsByTagNameNS(self::NS, 't') as $tn) {
                        $txt .= $tn->textContent;
                    }
                } elseif ($v === null) {
                    continue;
                } elseif ($t === 's') {
                    $txt = $ss[(int) $v->textContent] ?? '';
                } else {
                    $txt = $v->textContent;
                }
                $col = self::colIndex($m[1]);
                $max = max($max, $col);
                $rows[(int) $m[2]][$col] = $txt;
            }
            $ref = null;
            if ($loc['table'] !== null && ($tx = $z->getFromName($loc['table'])) !== false) {
                $ref = self::dom($tx)->documentElement->getAttribute('ref') ?: null;
            }
            $logos = [];
            if ($loc['drawing'] !== null && ($dx = $z->getFromName($loc['drawing'])) !== false) {
                $drels = [];
                $rn    = 'xl/drawings/_rels/' . basename($loc['drawing']) . '.rels';
                if (($rx = $z->getFromName($rn)) !== false) {
                    foreach (self::dom($rx)->getElementsByTagName('Relationship') as $r) {
                        $drels[$r->getAttribute('Id')] = self::resolve('xl/drawings/', $r->getAttribute('Target'));
                    }
                }
                $dd = self::dom($dx);
                foreach ($dd->documentElement->childNodes as $anchor) {
                    if (! $anchor instanceof DOMElement) {
                        continue;
                    }
                    $col   = (int) ($anchor->getElementsByTagNameNS(self::DRAW, 'col')->item(0)->textContent ?? 0);
                    $blip  = $anchor->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip')->item(0);
                    $embed = $blip ? $blip->getAttributeNS(self::RNS, 'embed') : '';
                    if ($embed !== '' && isset($drels[$embed])) {
                        $logos[] = ['col' => $col, 'part' => $drels[$embed], 'ext' => strtolower(pathinfo($drels[$embed], PATHINFO_EXTENSION))];
                    }
                }
                usort($logos, static fn ($a, $b) => $a['col'] <=> $b['col']);
            }
            return ['rows' => $rows, 'table_ref' => $ref, 'logos' => $logos, 'max_col' => $max];
        } finally {
            $z->close();
        }
    }

    /** @return list<string> the sheet names of a workbook, in order */
    public static function sheetNames(string $path): array
    {
        $z = new ZipArchive();
        if ($z->open($path) !== true) {
            return [];
        }
        $wb = (string) $z->getFromName('xl/workbook.xml');
        $z->close();
        preg_match_all('/<sheet [^>]*name="([^"]*)"/', $wb, $m);
        return array_map(static fn ($n) => html_entity_decode($n, ENT_QUOTES | ENT_XML1), $m[1] ?? []);
    }

    public static function extract(string $path, string $part, string $to): bool
    {
        $z = new ZipArchive();
        if ($z->open($path) !== true) {
            return false;
        }
        $data = $z->getFromName($part);
        $z->close();
        return $data !== false && file_put_contents($to, $data) !== false;
    }

    // ------------------------------------------------------------------------------------------------ write

    /**
     * Puts the register into the workbook's sheet.
     *
     * @param list<array{label:string,width:float}> $columns   the VISIBLE columns, in order (width in Excel character units)
     * @param list<list<string>>                    $rows      one list of cell texts per row, one text per visible column
     * @return array{ok:bool,message:string,backup:?string}
     */
    public static function write(string $path, string|int $sheet, array $columns, array $rows, string $backupDir): array
    {
        if (! is_file($path)) {
            return ['ok' => false, 'message' => 'The file is not reachable from the server: ' . $path, 'backup' => null];
        }
        if (! is_writable($path)) {
            return ['ok' => false, 'message' => 'The server may not write to the file (permission, or it is read-only).', 'backup' => null];
        }
        $dir  = dirname($path);
        $lock = $dir . DIRECTORY_SEPARATOR . '~$' . basename($path);
        if (is_file($lock)) {
            return ['ok' => false, 'message' => 'The workbook is open in Excel by someone (lock file found). Close it there and save again.', 'backup' => null];
        }
        if (! is_dir($backupDir) && ! @mkdir($backupDir, 0775, true) && ! is_dir($backupDir)) {
            return ['ok' => false, 'message' => 'The backup folder cannot be created.', 'backup' => null];
        }
        $backup = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . date('Ymd-His') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($path));
        $tmp    = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'work_' . bin2hex(random_bytes(4)) . '.xlsx';
        if (! @copy($path, $backup) || ! @copy($path, $tmp)) {
            return ['ok' => false, 'message' => 'A backup copy of the file could not be made, so nothing was changed.', 'backup' => null];
        }
        try {
            self::patch($tmp, $sheet, $columns, $rows);
            // the patched copy must open again and hold the data we wrote before it replaces the original
            $check = self::read($tmp, $sheet);
            $ref   = $check['table_ref'] ?? '';
            if (! preg_match('/^[A-Z]+(\d+):[A-Z]+(\d+)$/', $ref, $m) || ((int) $m[2] - (int) $m[1]) !== count($rows)) {
                throw new RuntimeException('The updated workbook did not pass its own check.');
            }
            if (! @copy($tmp, $path)) {
                throw new RuntimeException('The file on the share could not be replaced (it may be open or read-only).');
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            return ['ok' => false, 'message' => $e->getMessage(), 'backup' => $backup];
        }
        @unlink($tmp);
        return ['ok' => true, 'message' => 'File updated.', 'backup' => $backup];
    }

    private static function patch(string $file, string|int $sheet, array $columns, array $rows): void
    {
        $z = new ZipArchive();
        if ($z->open($file) !== true) {
            throw new RuntimeException('The workbook copy cannot be opened.');
        }
        $loc = self::locate($z, $sheet);
        if ($loc['table'] === null) {
            throw new RuntimeException('The sheet has no table to update.');
        }
        $nCols = count($columns);
        $last  = self::colLetter($nCols);

        // ---- the sheet
        $doc = self::dom((string) $z->getFromName($loc['sheet']));
        $x   = new DOMXPath($doc);
        $x->registerNamespace('m', self::NS);
        $tbl = self::dom((string) $z->getFromName($loc['table']));
        $ref = $tbl->documentElement->getAttribute('ref');
        if (! preg_match('/^[A-Z]+(\d+):[A-Z]+(\d+)$/', $ref, $rm)) {
            throw new RuntimeException('The table range is not readable.');
        }
        $headRow = (int) $rm[1];
        $sheetData = $x->query('//m:sheetData')->item(0);

        $styleOf = static function (?DOMElement $row, int $i): string {
            if ($row === null) {
                return '';
            }
            $cells = [];
            foreach ($row->childNodes as $c) {
                if ($c instanceof DOMElement && $c->nodeName === 'c') {
                    $cells[] = $c->getAttribute('s');
                }
            }
            return $cells === [] ? '' : ($cells[min($i, count($cells) - 1)] ?? '');
        };
        $headTpl = $x->query("//m:row[@r='{$headRow}']")->item(0);
        $dataTpl = $x->query('//m:row[@r=' . ($headRow + 1) . ']')->item(0);
        $headStyles = $dataStyles = [];
        for ($i = 0; $i < $nCols; $i++) {
            $headStyles[$i] = $styleOf($headTpl instanceof DOMElement ? $headTpl : null, $i);
            $dataStyles[$i] = $styleOf($dataTpl instanceof DOMElement ? $dataTpl : null, $i);
        }
        // the second data cell of the template (the description column) is the one with the wrapped style when the first is centred
        $rowStyles = [];
        foreach ($x->query('//m:row') as $r) {
            /** @var DOMElement $r */
            if ((int) $r->getAttribute('r') >= $headRow) {
                $rowStyles[] = $r;
            }
        }
        foreach ($rowStyles as $r) {
            $sheetData->removeChild($r);
        }

        $mk = static function (DOMDocument $d, string $ref, string $style, string $text): DOMElement {
            $c = $d->createElementNS(self::NS, 'c');
            $c->setAttribute('r', $ref);
            if ($style !== '') {
                $c->setAttribute('s', $style);
            }
            if ($text !== '') {
                $c->setAttribute('t', 'inlineStr');
                $is = $d->createElementNS(self::NS, 'is');
                $t  = $d->createElementNS(self::NS, 't');
                $t->setAttribute('xml:space', 'preserve');
                $t->appendChild($d->createTextNode($text));
                $is->appendChild($t);
                $c->appendChild($is);
            }
            return $c;
        };
        $names = [];
        $labels = [];
        foreach ($columns as $i => $col) {
            $l = trim((string) preg_replace('/\s+/', ' ', $col['label'])) ?: 'Column ' . ($i + 1);
            while (in_array(mb_strtolower($l), $names, true)) {
                $l .= ' ';
                $l  = rtrim($l) . ' (' . ($i + 1) . ')';
            }
            $names[]  = mb_strtolower($l);
            $labels[] = $l;
        }
        $row = $doc->createElementNS(self::NS, 'row');
        $row->setAttribute('r', (string) $headRow);
        $row->setAttribute('ht', '15');
        foreach ($labels as $i => $l) {
            $row->appendChild($mk($doc, self::colLetter($i + 1) . $headRow, $headStyles[$i], $l));
        }
        $sheetData->appendChild($row);
        foreach ($rows as $n => $cells) {
            $r = $headRow + 1 + $n;
            $e = $doc->createElementNS(self::NS, 'row');
            $e->setAttribute('r', (string) $r);
            $lines = 1;
            foreach ($columns as $i => $col) {
                $txt   = (string) ($cells[$i] ?? '');
                $per   = max(6, (int) floor(((float) $col['width']) * 1.05));
                $count = 0;
                foreach (preg_split('/\R/u', $txt) as $ln) {
                    $count += max(1, (int) ceil(mb_strlen($ln) / $per));
                }
                $lines = max($lines, $count);
                $e->appendChild($mk($doc, self::colLetter($i + 1) . $r, $dataStyles[$i], $txt));
            }
            $e->setAttribute('ht', (string) max(15.95, round(14.5 * $lines + 1.5, 2)));
            $e->setAttribute('customHeight', '1');
            $sheetData->appendChild($e);
        }
        $lastRow = $headRow + count($rows);

        // rows 1-3 (header block): their cells and styles extend over the new width, merged across
        foreach ($x->query('//m:row') as $r) {
            /** @var DOMElement $r */
            $rn = (int) $r->getAttribute('r');
            if ($rn >= $headRow) {
                continue;
            }
            $r->removeAttribute('spans');
            $first = null;
            foreach ($r->childNodes as $c) {
                if ($c instanceof DOMElement && $c->nodeName === 'c') {
                    $first ??= $c;
                }
            }
            $have = [];
            foreach ($r->getElementsByTagNameNS(self::NS, 'c') as $c) {
                $have[] = self::colIndex(preg_replace('/\d+/', '', $c->getAttribute('r')));
            }
            if ($first !== null) {
                $style = $first->getAttribute('s');
                for ($i = 1; $i <= $nCols; $i++) {
                    if (! in_array($i, $have, true)) {
                        $r->appendChild($mk($doc, self::colLetter($i) . $rn, $style, ''));
                    }
                }
                // drop cells beyond the new width and keep the cells in column order
                $cs = [];
                foreach ($r->getElementsByTagNameNS(self::NS, 'c') as $c) {
                    $cs[] = $c;
                }
                foreach ($cs as $c) {
                    if (self::colIndex(preg_replace('/\d+/', '', $c->getAttribute('r'))) > $nCols) {
                        $r->removeChild($c);
                    }
                }
                $cs = [];
                foreach ($r->getElementsByTagNameNS(self::NS, 'c') as $c) {
                    $cs[self::colIndex(preg_replace('/\d+/', '', $c->getAttribute('r')))] = $c;
                }
                ksort($cs);
                foreach ($cs as $c) {
                    $r->appendChild($c);
                }
            }
        }
        $merge = $x->query('//m:mergeCells')->item(0);
        if ($merge instanceof DOMElement) {
            foreach (iterator_to_array($merge->childNodes) as $mc) {
                if ($mc instanceof DOMElement && preg_match('/^A(\d+):[A-Z]+\d+$/', $mc->getAttribute('ref'), $mm)) {
                    $mc->setAttribute('ref', $nCols > 1 ? "A{$mm[1]}:{$last}{$mm[1]}" : "A{$mm[1]}:A{$mm[1]}");
                }
            }
        }
        $dim = $x->query('//m:dimension')->item(0);
        if ($dim instanceof DOMElement) {
            $dim->setAttribute('ref', "A1:{$last}{$lastRow}");
        }
        // column widths
        $cols = $x->query('//m:cols')->item(0);
        if ($cols instanceof DOMElement) {
            $style = '1';
            foreach ($cols->childNodes as $c) {
                if ($c instanceof DOMElement && $c->getAttribute('style') !== '') {
                    $style = $c->getAttribute('style');
                    break;
                }
            }
            while ($cols->firstChild) {
                $cols->removeChild($cols->firstChild);
            }
            foreach ($columns as $i => $col) {
                $c = $doc->createElementNS(self::NS, 'col');
                $c->setAttribute('min', (string) ($i + 1));
                $c->setAttribute('max', (string) ($i + 1));
                $c->setAttribute('width', (string) round(max(4.0, (float) $col['width']), 2));
                $c->setAttribute('style', $style);
                $c->setAttribute('customWidth', '1');
                $cols->appendChild($c);
            }
            $c = $doc->createElementNS(self::NS, 'col');
            $c->setAttribute('min', (string) ($nCols + 1));
            $c->setAttribute('max', '16384');
            $c->setAttribute('width', '8.85546875');
            $c->setAttribute('style', $style);
            $cols->appendChild($c);
        }
        // open at the top of the sheet
        $sv = $x->query('//m:sheetView')->item(0);
        if ($sv instanceof DOMElement) {
            $sv->removeAttribute('topLeftCell');
            foreach ($x->query('//m:sheetView/m:selection') as $sel) {
                /** @var DOMElement $sel */
                $sel->setAttribute('activeCell', 'A1');
                $sel->setAttribute('sqref', 'A1');
            }
        }
        $z->addFromString($loc['sheet'], $doc->saveXML());

        // ---- the Excel table: range and column names must match the header cells
        $te = $tbl->documentElement;
        $te->setAttribute('ref', "A{$headRow}:{$last}{$lastRow}");
        $tc = $te->getElementsByTagNameNS(self::NS, 'tableColumns')->item(0);
        while ($tc->firstChild) {
            $tc->removeChild($tc->firstChild);
        }
        $tc->setAttribute('count', (string) $nCols);
        foreach ($labels as $i => $l) {
            $c = $tbl->createElementNS(self::NS, 'tableColumn');
            $c->setAttribute('id', (string) ($i + 1));
            $c->setAttribute('name', $l);
            $tc->appendChild($c);
        }
        foreach (iterator_to_array($te->childNodes) as $ch) {
            if ($ch instanceof DOMElement && $ch->nodeName === 'autoFilter') {
                $ch->setAttribute('ref', "A{$headRow}:{$last}{$lastRow}");
            }
        }
        $z->addFromString($loc['table'], $tbl->saveXML());

        // ---- print area of this sheet
        $wb  = (string) $z->getFromName('xl/workbook.xml');
        $idx = null;
        $i   = 0;
        foreach (self::dom($wb)->getElementsByTagNameNS(self::NS, 'sheet') as $sh) {
            if ((is_int($sheet) && $i === $sheet) || (is_string($sheet) && $sh->getAttribute('name') === $sheet)) {
                $idx = $i;
            }
            $i++;
        }
        if ($idx !== null) {
            $wb = preg_replace_callback('~(<definedName name="_xlnm\.Print_Area" localSheetId="' . $idx . '">)([^<]*)(</definedName>)~', static function ($m) use ($last, $lastRow) {
                return $m[1] . preg_replace_callback('~\$A\$1:\$[A-Z]+\$\d+$~', static fn () => '$A$1:$' . $last . '$' . $lastRow, $m[2]) . $m[3];
            }, $wb) ?? $wb;
            $z->addFromString('xl/workbook.xml', $wb);
        }

        // ---- the right logo stays at the right edge of the (new) last column
        if ($loc['drawing'] !== null && ($dx = $z->getFromName($loc['drawing'])) !== false) {
            $dd      = self::dom($dx);
            $anchors = [];
            foreach ($dd->documentElement->childNodes as $a) {
                if ($a instanceof DOMElement) {
                    $anchors[] = $a;
                }
            }
            if (count($anchors) >= 2) {
                $a    = $anchors[count($anchors) - 1];
                $from = $a->getElementsByTagNameNS(self::DRAW, 'from')->item(0);
                $to   = $a->getElementsByTagNameNS(self::DRAW, 'to')->item(0);
                if ($from && $to) {
                    $w      = (int) $to->getElementsByTagNameNS(self::DRAW, 'colOff')->item(0)->textContent - (int) $from->getElementsByTagNameNS(self::DRAW, 'colOff')->item(0)->textContent;
                    $colEmu = (int) round((max(4.0, (float) $columns[$nCols - 1]['width']) * 7 + 5) * 9525);
                    $off    = max(0, $colEmu - $w - 40000);
                    foreach ([[$from, $off], [$to, $off + $w]] as [$el, $o]) {
                        $el->getElementsByTagNameNS(self::DRAW, 'col')->item(0)->nodeValue    = (string) ($nCols - 1);
                        $el->getElementsByTagNameNS(self::DRAW, 'colOff')->item(0)->nodeValue = (string) $o;
                    }
                    $z->addFromString($loc['drawing'], $dd->saveXML());
                }
            }
        }
        $z->close();
    }
}
