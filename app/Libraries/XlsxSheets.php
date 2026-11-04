<?php

namespace App\Libraries;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads the sheets of an Excel workbook into a model that a browser can show and edit, and puts an edited sheet back into the SAME
 * workbook (no spreadsheet library is installed, so this works on the xlsx package itself).
 *
 * Model of a sheet (JSON friendly):
 *   name, cols:[{w: px, k: original column number|null}], rows:[{h: px|null}], grid:[row][col] = {v: text, s: style index, k: "row:col" of the cell in the file}
 *   merges:[[r1,c1,r2,c2]] (1-based), images:[{src,r,c,dx,dy,w,h}], print:{orient, fit}
 *
 * write() regenerates the rows, cell values, merged cells and column widths of one sheet from the model. A cell whose text was not
 * changed keeps exactly what the file had (shared string with its rich formatting, number with its date format, formula); a changed or
 * new cell is written as text. Styles, the header pictures, the page setup and everything else stay as they are. The workbook is
 * copied to a backup first, the patched copy is read back and compared with the model, and only then it replaces the original.
 */
class XlsxSheets
{
    private const NS   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RNS  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const DRAW = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
    private const A    = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    public const MAX_ROWS = 2000;
    public const MAX_COLS = 60;

    // ------------------------------------------------------------------------------------------------ package helpers

    private static function dom(string $xml): DOMDocument
    {
        $d = new DOMDocument();
        $d->preserveWhiteSpace = true;
        $d->loadXML(trim($xml) === '' ? '<empty/>' : $xml, LIBXML_NONET | LIBXML_COMPACT);       // a missing part reads as empty
        return $d;
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

    /** @return list<array{name:string,part:string,rels:?string}> the sheets in workbook order */
    private static function sheetParts(ZipArchive $z): array
    {
        $wb   = self::dom((string) $z->getFromName('xl/workbook.xml'));
        $rels = [];
        foreach (self::dom((string) $z->getFromName('xl/_rels/workbook.xml.rels'))->getElementsByTagName('Relationship') as $r) {
            $rels[$r->getAttribute('Id')] = $r->getAttribute('Target');
        }
        $out = [];
        foreach ($wb->getElementsByTagNameNS(self::NS, 'sheet') as $sh) {
            $t = $rels[$sh->getAttributeNS(self::RNS, 'id')] ?? null;
            if ($t === null) {
                continue;
            }
            $part  = self::resolve('xl/', $t);
            $rel   = 'xl/worksheets/_rels/' . basename($part) . '.rels';
            $out[] = ['name' => $sh->getAttribute('name'), 'part' => $part, 'rels' => $z->getFromName($rel) !== false ? $rel : null];
        }
        return $out;
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
                if ($tn->parentNode && $tn->parentNode->nodeName === 'rPh') {
                    continue;
                }
                $t .= $tn->textContent;
            }
            $out[] = $t;
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------------ styles -> css

    /** @return array{css:list<string>,fmt:list<string>} one css declaration block and one number format code per cell style (xf) */
    private static function styles(ZipArchive $z): array
    {
        $raw = $z->getFromName('xl/styles.xml');
        if ($raw === false) {
            return ['css' => [''], 'fmt' => ['General']];
        }
        $d = self::dom($raw);
        $x = new DOMXPath($d);
        $x->registerNamespace('m', self::NS);
        $theme = self::themeColors($z);
        $color = static function (?DOMElement $c) use ($theme): ?string {
            if (! $c) {
                return null;
            }
            $hex = null;
            if ($c->hasAttribute('rgb')) {
                $hex = substr($c->getAttribute('rgb'), -6);
            } elseif ($c->hasAttribute('theme')) {
                $hex = $theme[(int) $c->getAttribute('theme')] ?? null;
            } elseif ($c->hasAttribute('indexed')) {
                $i   = (int) $c->getAttribute('indexed');
                $hex = $i === 64 ? '000000' : ($i === 65 ? 'FFFFFF' : null);
            }
            if ($hex === null || ! preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
                return null;
            }
            $tint = (float) $c->getAttribute('tint');
            if ($tint !== 0.0) {
                $ch = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
                $ch = array_map(static fn ($v) => (int) round($tint > 0 ? $v + (255 - $v) * $tint : $v * (1 + $tint)), $ch);
                $hex = sprintf('%02x%02x%02x', ...$ch);
            }
            return '#' . strtolower($hex);
        };
        $fonts = [];
        foreach ($x->query('//m:fonts/m:font') as $f) {
            $css = [];
            foreach ($f->childNodes as $c) {
                if (! $c instanceof DOMElement) {
                    continue;
                }
                match ($c->nodeName) {
                    'b'     => $css[] = 'font-weight:700',
                    'i'     => $css[] = 'font-style:italic',
                    'u'     => $css[] = 'text-decoration:underline',
                    'strike' => $css[] = 'text-decoration:line-through',
                    'sz'    => $css[] = 'font-size:' . round((float) $c->getAttribute('val'), 1) . 'pt',
                    'name'  => $css[] = "font-family:'" . preg_replace('/[^A-Za-z0-9 _-]/', '', $c->getAttribute('val')) . "',Arial,sans-serif",
                    'color' => ($col = $color($c)) ? $css[] = 'color:' . $col : null,
                    default => null,
                };
            }
            $fonts[] = implode(';', $css);
        }
        $fills = [];
        foreach ($x->query('//m:fills/m:fill') as $f) {
            $p = $f->getElementsByTagNameNS(self::NS, 'patternFill')->item(0);
            $col = null;
            if ($p instanceof DOMElement && $p->getAttribute('patternType') === 'solid') {
                $col = $color($p->getElementsByTagNameNS(self::NS, 'fgColor')->item(0));
            }
            $fills[] = $col ? 'background:' . $col : '';
        }
        $borders = [];
        $bw = ['thin' => '1px solid', 'hair' => '1px solid', 'medium' => '2px solid', 'thick' => '3px solid', 'dashed' => '1px dashed', 'dotted' => '1px dotted', 'double' => '3px double',
            'mediumDashed' => '2px dashed', 'dashDot' => '1px dashed', 'slantDashDot' => '1px dashed'];
        foreach ($x->query('//m:borders/m:border') as $b) {
            $css = [];
            foreach (['left', 'right', 'top', 'bottom'] as $side) {
                $e = $b->getElementsByTagNameNS(self::NS, $side)->item(0);
                if ($e instanceof DOMElement && $e->getAttribute('style') !== '' && isset($bw[$e->getAttribute('style')])) {
                    $css[] = "border-{$side}:" . $bw[$e->getAttribute('style')] . ' ' . ($color($e->getElementsByTagNameNS(self::NS, 'color')->item(0)) ?? '#000');
                }
            }
            $borders[] = implode(';', $css);
        }
        $codes = [];
        foreach ($x->query('//m:numFmts/m:numFmt') as $n) {
            $codes[(int) $n->getAttribute('numFmtId')] = $n->getAttribute('formatCode');
        }
        $builtin = [0 => 'General', 1 => '0', 2 => '0.00', 3 => '#,##0', 4 => '#,##0.00', 9 => '0%', 10 => '0.00%', 14 => 'm/d/yyyy', 15 => 'd-mmm-yy', 16 => 'd-mmm', 17 => 'mmm-yy',
            18 => 'h:mm AM/PM', 19 => 'h:mm:ss AM/PM', 20 => 'h:mm', 21 => 'h:mm:ss', 22 => 'm/d/yyyy h:mm', 37 => '#,##0', 38 => '#,##0', 39 => '#,##0.00', 40 => '#,##0.00', 45 => 'mm:ss',
            46 => '[h]:mm:ss', 47 => 'mm:ss.0', 49 => '@'];
        $css = [];
        $fmt = [];
        foreach ($x->query('//m:cellXfs/m:xf') as $xf) {
            /** @var DOMElement $xf */
            $r = [];
            $r[] = $fonts[(int) $xf->getAttribute('fontId')] ?? '';
            $r[] = $fills[(int) $xf->getAttribute('fillId')] ?? '';
            $r[] = $borders[(int) $xf->getAttribute('borderId')] ?? '';
            $al = $xf->getElementsByTagNameNS(self::NS, 'alignment')->item(0);
            if ($al instanceof DOMElement) {
                $h = $al->getAttribute('horizontal');
                if (in_array($h, ['left', 'center', 'right', 'justify'], true)) {
                    $r[] = 'text-align:' . $h;
                } elseif ($h === 'centerContinuous') {
                    $r[] = 'text-align:center';
                }
                $v = $al->getAttribute('vertical');
                $r[] = 'vertical-align:' . ($v === 'center' ? 'middle' : ($v === 'top' ? 'top' : 'bottom'));
                $r[] = $al->getAttribute('wrapText') === '1' ? 'white-space:pre-wrap' : 'white-space:nowrap';
                if ((int) $al->getAttribute('indent') > 0) {
                    $r[] = 'padding-left:' . (3 + 9 * (int) $al->getAttribute('indent')) . 'px';
                }
            } else {
                $r[] = 'vertical-align:bottom;white-space:nowrap';
            }
            $css[] = implode(';', array_filter($r, 'strlen'));
            $id    = (int) $xf->getAttribute('numFmtId');
            $fmt[] = $codes[$id] ?? ($builtin[$id] ?? 'General');
        }
        return ['css' => $css ?: [''], 'fmt' => $fmt ?: ['General']];
    }

    /** @return array<int,string> theme colour index (as used by Excel: 0 lt1, 1 dk1, 2 lt2, 3 dk2, 4-9 accents) -> RRGGBB */
    private static function themeColors(ZipArchive $z): array
    {
        $raw = $z->getFromName('xl/theme/theme1.xml');
        $out = [0 => 'FFFFFF', 1 => '000000', 2 => 'EEECE1', 3 => '1F497D', 4 => '4F81BD', 5 => 'C0504D', 6 => '9BBB59', 7 => '8064A2', 8 => '4BACC6', 9 => 'F79646'];
        if ($raw === false) {
            return $out;
        }
        $names = ['lt1', 'dk1', 'lt2', 'dk2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6'];
        foreach ($names as $i => $n) {
            if (preg_match('~<a:' . $n . '>\s*<a:(?:srgbClr val|sysClr val="[^"]*" lastClr)="([0-9A-Fa-f]{6})"~', $raw, $m)) {
                $out[$i] = $m[1];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------------ number display

    private static function isDateFormat(string $code): bool
    {
        $c = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\.|_.|\*./', '', $code) ?? $code;
        return (bool) preg_match('/[dmyhs]/i', $c) && ! preg_match('/^General$/i', $code);
    }

    public static function display(string $raw, string $code): string
    {
        if ($raw === '' || ! is_numeric($raw)) {
            return $raw;
        }
        $n = (float) $raw;
        if ($code === '' || strcasecmp($code, 'General') === 0 || $code === '@') {
            return self::plain($n);
        }
        $sec = explode(';', $code)[0];
        if (self::isDateFormat($sec)) {
            $dt = \DateTime::createFromFormat('U', (string) (int) round(($n - 25569) * 86400));
            if (! $dt) {
                return $raw;
            }
            $dt->setTimezone(new \DateTimeZone('UTC'));
            $s   = preg_replace('/\[[^\]]*\]|_.|\*./', '', $sec) ?? $sec;
            $ampm = (bool) preg_match('~AM/PM|A/P~i', $s);
            $out = '';
            $len = strlen($s);
            $seenH = false;
            for ($i = 0; $i < $len;) {
                $rest = substr($s, $i);
                if ($rest[0] === '"') {
                    $e = strpos($s, '"', $i + 1);
                    $out .= $e === false ? '' : preg_replace('/(.)/u', '\\\\$1', substr($s, $i + 1, $e - $i - 1));
                    $i   = $e === false ? $len : $e + 1;
                } elseif ($rest[0] === '\\') {
                    $out .= '\\' . ($rest[1] ?? '');
                    $i   += 2;
                } elseif (preg_match('~^(AM/PM|A/P)~i', $rest, $m)) {
                    $out .= 'A';
                    $i   += strlen($m[1]);
                } elseif (preg_match('/^(yyyy|yy|mmmmm|mmmm|mmm|mm|m|dddd|ddd|dd|d|hh|h|ss|s)/i', $rest, $m)) {
                    $t = strtolower($m[1]);
                    if ($t[0] === 'h') {
                        $seenH = true;
                        $out  .= $ampm ? ($t === 'hh' ? 'h' : 'g') : ($t === 'hh' ? 'H' : 'G');
                    } elseif ($t[0] === 'm' && strlen($t) <= 2 && ($seenH || preg_match('/^m{1,2}[^a-z]*s/i', $rest))) {
                        $out .= $t === 'mm' ? 'i' : 'i';
                    } else {
                        $out .= ['yyyy' => 'Y', 'yy' => 'y', 'mmmmm' => 'M', 'mmmm' => 'F', 'mmm' => 'M', 'mm' => 'm', 'm' => 'n', 'dddd' => 'l', 'ddd' => 'D', 'dd' => 'd', 'd' => 'j', 'ss' => 's', 's' => 's'][$t] ?? '';
                    }
                    $i += strlen($m[1]);
                } else {
                    $ch   = $rest[0];
                    $out .= ctype_alpha($ch) ? '\\' . $ch : $ch;
                    $i++;
                }
            }
            return $dt->format($out);
        }
        $pct = str_contains($sec, '%');
        if ($pct) {
            $n *= 100;
        }
        $dec = preg_match('/\.(0+)/', $sec, $m) ? strlen($m[1]) : 0;
        $s   = number_format($n, $dec, '.', str_contains($sec, ',') ? ',' : '');
        return $s . ($pct ? '%' : '');
    }

    private static function plain(float $n): string
    {
        if ($n == (int) $n && abs($n) < 1e15) {
            return (string) (int) $n;
        }
        return rtrim(rtrim(sprintf('%.10F', $n), '0'), '.');
    }

    // ------------------------------------------------------------------------------------------------ read

    /**
     * @return array{styles:list<string>,sheets:list<array<string,mixed>>}
     */
    public static function read(string $path, ?string $assetDir = null, string $assetUrl = ''): array
    {
        $z = new ZipArchive();
        if ($z->open($path) !== true) {
            throw new RuntimeException('The workbook cannot be opened (damaged or locked).');
        }
        try {
            $ss     = self::sharedStrings($z);
            $st     = self::styles($z);
            $parts  = self::sheetParts($z);
            $wbXml  = (string) $z->getFromName('xl/workbook.xml');
            $sheets = [];
            foreach ($parts as $i => $p) {
                $sheets[] = self::readSheet($z, $p, $i, $ss, $st['fmt'], $wbXml, $assetDir, $assetUrl);
            }
            return ['styles' => $st['css'], 'sheets' => $sheets];
        } finally {
            $z->close();
        }
    }

    /** @param list<string> $ss @param list<string> $fmt */
    private static function readSheet(ZipArchive $z, array $p, int $idx, array $ss, array $fmt, string $wbXml, ?string $assetDir, string $assetUrl): array
    {
        $doc = self::dom((string) $z->getFromName($p['part']));
        $x   = new DOMXPath($doc);
        $x->registerNamespace('m', self::NS);
        $defW = (float) ($x->query('//m:sheetFormatPr')->item(0)?->getAttribute('defaultColWidth') ?: 8.43);
        $defH = (float) ($x->query('//m:sheetFormatPr')->item(0)?->getAttribute('defaultRowHeight') ?: 15);
        $cw   = [];
        foreach ($x->query('//m:cols/m:col') as $c) {
            /** @var DOMElement $c */
            for ($i = (int) $c->getAttribute('min'); $i <= min((int) $c->getAttribute('max'), self::MAX_COLS); $i++) {
                $cw[$i] = (float) $c->getAttribute('width');
            }
        }
        $cells = [];
        $rowH  = [];
        $maxR  = $maxC = 0;
        foreach ($x->query('//m:sheetData/m:row') as $row) {
            /** @var DOMElement $row */
            $r = (int) $row->getAttribute('r');
            if ($r < 1 || $r > self::MAX_ROWS) {
                continue;
            }
            if ($row->getAttribute('ht') !== '') {
                $rowH[$r] = (float) $row->getAttribute('ht');
            }
            foreach ($row->childNodes as $c) {
                if (! $c instanceof DOMElement || $c->nodeName !== 'c' || ! preg_match('/^([A-Z]+)(\d+)$/', $c->getAttribute('r'), $m)) {
                    continue;
                }
                $col = LabDocXlsx::colIndex($m[1]);
                if ($col > self::MAX_COLS) {
                    continue;
                }
                $v = $c->getElementsByTagNameNS(self::NS, 'v')->item(0);
                $t = $c->getAttribute('t');
                $s = (int) $c->getAttribute('s');
                $text = '';
                if ($t === 'inlineStr') {
                    foreach ($c->getElementsByTagNameNS(self::NS, 't') as $tn) {
                        $text .= $tn->textContent;
                    }
                } elseif ($v !== null) {
                    $text = match ($t) {
                        's'     => $ss[(int) $v->textContent] ?? '',
                        'b'     => $v->textContent === '1' ? 'TRUE' : 'FALSE',
                        'str', 'e' => $v->textContent,
                        default => self::display($v->textContent, $fmt[$s] ?? 'General'),
                    };
                }
                $cells[$r][$col] = ['v' => $text, 's' => $s, 'k' => $r . ':' . $col];
                $maxR = max($maxR, $r);
                $maxC = max($maxC, $col);
            }
        }
        $merges = [];
        foreach ($x->query('//m:mergeCells/m:mergeCell') as $mc) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $mc->getAttribute('ref'), $m)) {
                $mm = [(int) $m[2], LabDocXlsx::colIndex($m[1]), (int) $m[4], LabDocXlsx::colIndex($m[3])];
                if ($mm[2] <= self::MAX_ROWS && $mm[3] <= self::MAX_COLS) {
                    $merges[] = $mm;
                    $maxR     = max($maxR, $mm[2]);
                    $maxC     = max($maxC, $mm[3]);
                }
            }
        }
        $px   = static fn (float $w): int => (int) round($w * 7 + 5);
        $cols = [];
        for ($c = 1; $c <= $maxC; $c++) {
            $cols[] = ['w' => $px($cw[$c] ?? $defW), 'k' => $c];
        }
        $rows = [];
        $grid = [];
        for ($r = 1; $r <= $maxR; $r++) {
            $rows[] = ['h' => isset($rowH[$r]) ? (int) round($rowH[$r] * 96 / 72) : null, 'k' => $r];
            $line = [];
            for ($c = 1; $c <= $maxC; $c++) {
                $line[] = $cells[$r][$c] ?? ['v' => '', 's' => 0];
            }
            $grid[] = $line;
        }
        $images = self::readImages($z, $p, $assetDir, $assetUrl . 's' . $idx . '_');
        $ps     = $x->query('//m:pageSetup')->item(0);
        $fit    = $x->query('//m:sheetPr/m:pageSetUpPr')->item(0)?->getAttribute('fitToPage') === '1';
        return ['name' => $p['name'], 'cols' => $cols, 'rows' => $rows, 'grid' => $grid, 'merges' => $merges, 'images' => $images,
            'defH' => (int) round($defH * 96 / 72), 'print' => ['orient' => $ps?->getAttribute('orientation') === 'landscape' ? 'landscape' : 'portrait', 'fit' => $fit]];
    }

    /** @return list<array<string,mixed>> */
    private static function readImages(ZipArchive $z, array $p, ?string $assetDir, string $prefix): array
    {
        if ($p['rels'] === null) {
            return [];
        }
        $drawing = null;
        foreach (self::dom((string) $z->getFromName($p['rels']))->getElementsByTagName('Relationship') as $r) {
            if (basename($r->getAttribute('Type')) === 'drawing') {
                $drawing = self::resolve('xl/worksheets/', $r->getAttribute('Target'));
            }
        }
        if ($drawing === null || ($dx = $z->getFromName($drawing)) === false) {
            return [];
        }
        $rels = [];
        if (($rx = $z->getFromName('xl/drawings/_rels/' . basename($drawing) . '.rels')) !== false) {
            foreach (self::dom($rx)->getElementsByTagName('Relationship') as $r) {
                $rels[$r->getAttribute('Id')] = self::resolve('xl/drawings/', $r->getAttribute('Target'));
            }
        }
        $out = [];
        $n   = 0;
        foreach (self::dom($dx)->documentElement->childNodes as $a) {
            if (! $a instanceof DOMElement) {
                continue;
            }
            $blip = $a->getElementsByTagNameNS(self::A, 'blip')->item(0);
            $emb  = $blip ? $blip->getAttributeNS(self::RNS, 'embed') : '';
            if ($emb === '' || ! isset($rels[$emb])) {
                continue;
            }
            $ext = strtolower(pathinfo($rels[$emb], PATHINFO_EXTENSION));
            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true) || $assetDir === null) {
                continue;
            }
            $from = $a->getElementsByTagNameNS(self::DRAW, 'from')->item(0);
            if (! $from) {
                continue;
            }
            $g = static fn (DOMElement $e, string $n): int => (int) ($e->getElementsByTagNameNS(self::DRAW, $n)->item(0)->textContent ?? 0);
            $xf = $a->getElementsByTagNameNS(self::A, 'ext')->item(0);
            $to = $a->getElementsByTagNameNS(self::DRAW, 'to')->item(0);
            $w  = $xf instanceof DOMElement && $xf->getAttribute('cx') !== '' ? (int) $xf->getAttribute('cx') / 9525 : 0;
            $h  = $xf instanceof DOMElement && $xf->getAttribute('cy') !== '' ? (int) $xf->getAttribute('cy') / 9525 : 0;
            if (! $w || ! $h) {
                $sp = $a->getElementsByTagNameNS(self::A, 'ext');
                foreach ($sp as $e) {
                    if ($e->getAttribute('cx') !== '') {
                        $w = (int) $e->getAttribute('cx') / 9525;
                        $h = (int) $e->getAttribute('cy') / 9525;
                        break;
                    }
                }
            }
            if ($to instanceof DOMElement && (! $w || ! $h)) {
                $w = 120;
                $h = 60;
            }
            $n++;
            $fn = $prefix . $n . '.' . $ext;
            if (! is_dir($assetDir) && ! @mkdir($assetDir, 0775, true) && ! is_dir($assetDir)) {
                continue;
            }
            file_put_contents(rtrim($assetDir, '/\\') . DIRECTORY_SEPARATOR . $fn, (string) $z->getFromName($rels[$emb]));
            $out[] = ['src' => $fn, 'r' => $g($from, 'row') + 1, 'c' => $g($from, 'col') + 1, 'dx' => (int) round($g($from, 'colOff') / 9525), 'dy' => (int) round($g($from, 'rowOff') / 9525),
                'w' => (int) round($w), 'h' => (int) round($h)];
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------------ write

    /**
     * Puts edited sheets back into the workbook.
     *
     * @param array<int,array<string,mixed>> $models sheet index => sheet model (as from read())
     * @return array{ok:bool,message:string,backup:?string,read?:array<string,mixed>}
     */
    public static function write(string $path, array $models, string $backupDir): array
    {
        if (! is_file($path)) {
            return ['ok' => false, 'message' => 'The file is not reachable from the server: ' . $path, 'backup' => null];
        }
        if (! is_writable($path)) {
            return ['ok' => false, 'message' => 'The server may not write to the file (permission, or it is read-only).', 'backup' => null];
        }
        if (is_file(dirname($path) . DIRECTORY_SEPARATOR . '~$' . basename($path))) {
            return ['ok' => false, 'message' => 'The workbook is open in Excel by someone (lock file found). Close it there and save again.', 'backup' => null];
        }
        if (! is_dir($backupDir) && ! @mkdir($backupDir, 0775, true) && ! is_dir($backupDir)) {
            return ['ok' => false, 'message' => 'The backup folder cannot be created.', 'backup' => null];
        }
        $stamp  = date('Ymd-His');
        $backup = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $stamp . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($path));
        $tmp    = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'work_' . bin2hex(random_bytes(4)) . '.xlsx';
        if (! @copy($path, $backup) || ! @copy($path, $tmp)) {
            return ['ok' => false, 'message' => 'A backup copy of the file could not be made, so nothing was changed.', 'backup' => null];
        }
        try {
            foreach ($models as $i => $m) {
                self::patch($tmp, (int) $i, $m);
            }
            $check = self::read($tmp);
            foreach ($models as $i => $m) {
                $got = $check['sheets'][(int) $i] ?? null;
                if (! $got || self::texts($got) !== self::texts($m)) {
                    throw new RuntimeException('The updated workbook did not pass its own check (sheet ' . ((int) $i + 1) . '); the file was not changed.');
                }
            }
            if (! @copy($tmp, $path)) {
                throw new RuntimeException('The file on the share could not be replaced (it may be open or read-only).');
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            return ['ok' => false, 'message' => $e->getMessage(), 'backup' => $backup];
        }
        @unlink($tmp);
        return ['ok' => true, 'message' => 'File updated.', 'backup' => $backup, 'read' => $check];
    }

    /** @return list<list<string>> the text of every cell (what must survive a write) */
    private static function texts(array $sheet): array
    {
        $out = [];
        foreach ($sheet['grid'] as $row) {
            $out[] = array_map(static fn ($c) => (string) ($c['v'] ?? ''), $row);
        }
        return $out;
    }

    /** @param array<string,mixed> $m */
    private static function patch(string $file, int $idx, array $m): void
    {
        $z = new ZipArchive();
        if ($z->open($file) !== true) {
            throw new RuntimeException('The workbook copy cannot be opened.');
        }
        $parts = self::sheetParts($z);
        if (! isset($parts[$idx])) {
            throw new RuntimeException('Sheet ' . ($idx + 1) . ' is not in the workbook.');
        }
        $part = $parts[$idx]['part'];
        $ss   = self::sharedStrings($z);
        $st   = self::styles($z);
        $doc  = self::dom((string) $z->getFromName($part));
        $x    = new DOMXPath($doc);
        $x->registerNamespace('m', self::NS);

        // what the file has now, by position, so that unchanged cells keep their exact encoding
        $orig  = [];
        $oRows = [];
        $oMaxR = $oMaxC = 0;
        foreach ($x->query('//m:sheetData/m:row') as $row) {
            /** @var DOMElement $row */
            $r = (int) $row->getAttribute('r');
            $oRows[$r] = $row;
            foreach ($row->childNodes as $c) {
                if ($c instanceof DOMElement && $c->nodeName === 'c' && preg_match('/^([A-Z]+)(\d+)$/', $c->getAttribute('r'), $mm)) {
                    $col = LabDocXlsx::colIndex($mm[1]);
                    $v   = $c->getElementsByTagNameNS(self::NS, 'v')->item(0);
                    $t   = $c->getAttribute('t');
                    $f   = $c->getElementsByTagNameNS(self::NS, 'f')->item(0);
                    $txt = '';
                    if ($t === 'inlineStr') {
                        foreach ($c->getElementsByTagNameNS(self::NS, 't') as $tn) {
                            $txt .= $tn->textContent;
                        }
                    } elseif ($v !== null) {
                        $txt = match ($t) {
                            's'     => $ss[(int) $v->textContent] ?? '',
                            'b'     => $v->textContent === '1' ? 'TRUE' : 'FALSE',
                            'str', 'e' => $v->textContent,
                            default => self::display($v->textContent, $st['fmt'][(int) $c->getAttribute('s')] ?? 'General'),
                        };
                    }
                    $orig[$r . ':' . $col] = ['node' => $c, 'text' => $txt, 'f' => $f !== null];
                    $oMaxR = max($oMaxR, $r);
                    $oMaxC = max($oMaxC, $col);
                }
            }
        }
        $origCols = [];
        foreach ($x->query('//m:cols/m:col') as $c) {
            /** @var DOMElement $c */
            for ($i = (int) $c->getAttribute('min'); $i <= min((int) $c->getAttribute('max'), self::MAX_COLS); $i++) {
                $origCols[$i] = $c;
            }
        }
        $origRowAttr = [];
        foreach ($oRows as $r => $node) {
            $origRowAttr[$r] = $node;
        }

        // ---- sheetData from the model
        $sd = $x->query('//m:sheetData')->item(0);
        $keepRows = [];
        $grid = $m['grid'];
        $nR   = count($grid);
        $nC   = count($m['cols']);
        $nStyles = count($st['css']);
        foreach ($grid as $ri => $line) {
            $r   = $ri + 1;
            $row = $doc->createElementNS(self::NS, 'row');
            $row->setAttribute('r', (string) $r);
            $h = $m['rows'][$ri]['h'] ?? null;
            if ($h !== null && (int) $h > 0) {
                $row->setAttribute('ht', (string) round(((int) $h) * 72 / 96, 2));
                $row->setAttribute('customHeight', '1');
            } elseif (isset($m['rows'][$ri]['k']) && isset($origRowAttr[(int) $m['rows'][$ri]['k']]) && $origRowAttr[(int) $m['rows'][$ri]['k']]->getAttribute('ht') !== '') {
                $row->setAttribute('ht', $origRowAttr[(int) $m['rows'][$ri]['k']]->getAttribute('ht'));
            }
            foreach ($line as $ci => $cell) {
                $c    = $ci + 1;
                $text = str_replace("\r", '', (string) ($cell['v'] ?? ''));
                $s    = (int) ($cell['s'] ?? 0);
                $s    = $s >= 0 && $s < $nStyles ? $s : 0;
                $src  = isset($cell['k']) && isset($orig[$cell['k']]) && $orig[$cell['k']]['text'] === $text ? $orig[$cell['k']]['node'] : null;
                if ($src === null && $text === '' && $s === 0 && ! ($ri === $nR - 1 && $ci === $nC - 1)) {          // the last cell always exists: it fixes the size of the sheet
                    continue;
                }
                $ref = LabDocXlsx::colLetter($c) . $r;
                if ($src !== null) {
                    $n = $src->cloneNode(true);
                    /** @var DOMElement $n */
                    $n->setAttribute('r', $ref);
                    $n->setAttribute('s', (string) $s);
                    $row->appendChild($n);
                    continue;
                }
                $n = $doc->createElementNS(self::NS, 'c');
                $n->setAttribute('r', $ref);
                if ($s) {
                    $n->setAttribute('s', (string) $s);
                }
                if ($text !== '') {
                    $n->setAttribute('t', 'inlineStr');
                    $is = $doc->createElementNS(self::NS, 'is');
                    $t  = $doc->createElementNS(self::NS, 't');
                    $t->setAttribute('xml:space', 'preserve');
                    $t->appendChild($doc->createTextNode(mb_substr($text, 0, 8000)));
                    $is->appendChild($t);
                    $n->appendChild($is);
                }
                $row->appendChild($n);
            }
            $keepRows[] = $row;
        }
        while ($sd->firstChild) {
            $sd->removeChild($sd->firstChild);
        }
        foreach ($keepRows as $row) {
            $sd->appendChild($row);
        }

        // ---- merged cells
        $mcs = $x->query('//m:mergeCells')->item(0);
        $valid = [];
        foreach ($m['merges'] ?? [] as $mg) {
            if (count($mg) === 4 && $mg[0] >= 1 && $mg[1] >= 1 && $mg[2] <= $nR && $mg[3] <= $nC && ($mg[2] > $mg[0] || $mg[3] > $mg[1])) {
                $valid[] = LabDocXlsx::colLetter((int) $mg[1]) . (int) $mg[0] . ':' . LabDocXlsx::colLetter((int) $mg[3]) . (int) $mg[2];
            }
        }
        if ($mcs instanceof DOMElement) {
            while ($mcs->firstChild) {
                $mcs->removeChild($mcs->firstChild);
            }
            foreach ($valid as $ref) {
                $e = $doc->createElementNS(self::NS, 'mergeCell');
                $e->setAttribute('ref', $ref);
                $mcs->appendChild($e);
            }
            $mcs->setAttribute('count', (string) count($valid));
            if (! $valid) {
                $mcs->parentNode->removeChild($mcs);
            }
        } elseif ($valid) {
            $mcs = $doc->createElementNS(self::NS, 'mergeCells');
            $mcs->setAttribute('count', (string) count($valid));
            foreach ($valid as $ref) {
                $e = $doc->createElementNS(self::NS, 'mergeCell');
                $e->setAttribute('ref', $ref);
                $mcs->appendChild($e);
            }
            $sd->parentNode->insertBefore($mcs, $sd->nextSibling);
        }

        // ---- column widths (the style of a column follows its original column)
        $colsEl = $x->query('//m:cols')->item(0);
        if (! $colsEl instanceof DOMElement) {
            $colsEl = $doc->createElementNS(self::NS, 'cols');
            $sd->parentNode->insertBefore($colsEl, $sd);
        }
        while ($colsEl->firstChild) {
            $colsEl->removeChild($colsEl->firstChild);
        }
        foreach ($m['cols'] as $ci => $col) {
            $e = $doc->createElementNS(self::NS, 'col');
            $e->setAttribute('min', (string) ($ci + 1));
            $e->setAttribute('max', (string) ($ci + 1));
            $e->setAttribute('width', (string) round(max(1.0, ((int) $col['w'] - 5) / 7), 2));
            $e->setAttribute('customWidth', '1');
            $o = isset($col['k']) ? ($origCols[(int) $col['k']] ?? null) : null;
            if ($o && $o->getAttribute('style') !== '') {
                $e->setAttribute('style', $o->getAttribute('style'));
            }
            $colsEl->appendChild($e);
        }
        $dim = $x->query('//m:dimension')->item(0);
        if ($dim instanceof DOMElement) {
            $dim->setAttribute('ref', 'A1:' . LabDocXlsx::colLetter(max(1, $nC)) . max(1, $nR));
        }
        // page breaks were set for the old rows: drop them when the number of rows changed
        if ($nR !== $oMaxR) {
            foreach ($x->query('//m:rowBreaks') as $rb) {
                $rb->parentNode->removeChild($rb);
            }
        }
        $z->addFromString($part, $doc->saveXML());

        // ---- print area, pictures, recalculation
        $wb = (string) $z->getFromName('xl/workbook.xml');
        $hasF = false;
        foreach ($keepRows as $row) {
            if ($row->getElementsByTagNameNS(self::NS, 'f')->length) {
                $hasF = true;
                break;
            }
        }
        $nameIdx = $idx;
        $wb = preg_replace_callback('~(<definedName name="_xlnm\.Print_Area" localSheetId="' . $nameIdx . '"[^>]*>)([^<]*)(</definedName>)~', static function ($mm) use ($nR, $nC, $oMaxR, $oMaxC) {
            return $mm[1] . preg_replace_callback('~\$([A-Z]+)\$(\d+):\$([A-Z]+)\$(\d+)$~', static function ($a) use ($nR, $nC, $oMaxR, $oMaxC) {
                $er = (int) $a[4] === $oMaxR ? $nR : (int) $a[4];
                $ec = LabDocXlsx::colIndex($a[3]) === $oMaxC ? $nC : LabDocXlsx::colIndex($a[3]);
                return '$' . $a[1] . '$' . $a[2] . ':$' . LabDocXlsx::colLetter(max(1, $ec)) . '$' . max(1, $er);
            }, $mm[2]) . $mm[3];
        }, $wb) ?? $wb;
        if ($hasF && ! str_contains($wb, 'fullCalcOnLoad')) {
            $wb = preg_replace('~<calcPr\b~', '<calcPr fullCalcOnLoad="1"', $wb, 1, $cnt) ?? $wb;
        }
        $z->addFromString('xl/workbook.xml', $wb);
        $z->close();
    }
}
