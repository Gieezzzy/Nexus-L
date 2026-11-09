<?php

namespace App\Libraries;

/**
 * Reads a QIMA schedule workbook (.xlsx) and returns its rows plus the pictures embedded beside them.
 *
 * No spreadsheet library is installed, so the workbook (a zip of XML files) is read directly. The header row is found by
 * its titles (MTL, Production Code, PO, Style, Colorway, CRD, Pairs ...), so the column order does not matter.
 */
class QimaImporter
{
    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_XDR  = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
    private const NS_A    = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /** tbl_qima column => accepted header titles (upper case, letters and digits only); first listed match wins */
    private const HEADERS = [
        'ProductionLine' => ['PRODUCTIONLINE', 'PRODLINE', 'ASSYLINE', 'LINE'],
        'ProductionCode' => ['PRODUCTIONCODE', 'PRODCODE', 'PRODUCTION'],
        'PONo'           => ['PONO', 'PO', 'PONUMBER', 'PURCHASEORDER'],
        'Style'          => ['STYLE', 'STYLENO', 'STYLENAME', 'MODEL'],
        'Colorway'       => ['COLORWAY', 'COLOURWAY', 'COLOR', 'COLOUR'],
        'MTL'            => ['MTL', 'MTLNO', 'MATERIALNO', 'MATERIAL'],
        'CRD'            => ['CRD', 'CRDDATE'],
        'Pairs'          => ['PAIRS', 'PRS', 'QTY', 'QUANTITY'],
        'Cnts'           => ['CNTS', 'CTNS', 'CARTONS', 'CARTON'],
        'Destination'    => ['DESTINATION', 'DEST'],
        'RiskLevel'      => ['RISKLEVEL', 'RISK'],
        'SamplingPlan'   => ['SAMPLINGPLAN', 'SAMPLING', 'AQL'],
        'SampleSize'     => ['SAMPLESIZE', 'SAMPLE'],
        'Mold'           => ['MOLD', 'MOLDING'],
        'BookingDate'    => ['BOOKINGDATE', 'BOOKING', 'BOOKED'],
        'QimaAuditDate'  => ['QIMAAUDITDATE', 'AUDITDATE', 'QIMAAUDIT', 'INSPECTIONDATE'],
        'Inspector'      => ['INSPECTOR'],
        'QimaReference'  => ['QIMAREFERENCE', 'QIMAREF', 'QIMAREFERENCENO', 'BOOKINGREFERENCE', 'REFERENCE'],
        'Remarks'        => ['REMARKS', 'REMARK', 'NOTES'],
        'RowNo'          => ['NO', 'ITEM', 'SEQ', 'SEQUENCE'],
    ];

    private const DATE_COLS = ['CRD', 'BookingDate', 'QimaAuditDate'];
    private const INT_COLS  = ['Pairs', 'Cnts', 'SampleSize', 'RowNo'];
    private const MONTHS    = 'JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER';

    /** @var string[] */
    private array $shared = [];
    private int $found    = 0;
    private int $leftover = 0;

    /**
     * @return array{rows: array<int, array<string, mixed>>, images: array<string, array{ext: string, data: string}>, error?: string}
     *   rows: tbl_qima column => value; images: MTL => picture bytes
     */
    public function read(string $path): array
    {
        $zip = new SimpleZip();
        if ($zip->open($path) !== true) {
            return ['rows' => [], 'images' => [], 'error' => 'The file is not a readable .xlsx workbook.'];
        }

        $this->shared   = [];
        $this->found    = 0;
        $this->leftover = 0;
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false && ($sx = @simplexml_load_string($ss))) {
            foreach ($sx->si as $si) {
                $text = '';
                foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $t) {
                    $text .= (string) $t;
                }
                $this->shared[] = $text;
            }
        }

        $rows   = [];
        $images = [];
        foreach ($this->sheets($zip) as $sheet) {
            $xml = $zip->getFromName($sheet['path']);
            if ($xml === false) {
                continue;
            }
            $grid = $this->grid($xml);
            [$hdrRow, $map] = $this->findHeader($grid);
            if ($hdrRow === 0 || ! isset($map['MTL'])) {
                continue;
            }
            $grid  = $this->fillMerged($grid, $hdrRow);
            $pics  = $this->sheetPictures($zip, $sheet['path']);
            $totalPics = count($pics);
            $month = $this->reportMonth($grid, $hdrRow, $sheet['name']);

            $seq = 0;
            foreach ($grid as $r => $cells) {
                if ($r <= $hdrRow) {
                    continue;
                }
                $raw = [];
                foreach ($map as $col => $c) {
                    $raw[$col] = trim((string) ($cells[$c] ?? ''));
                }
                // some schedule lines have Sampling Plan and Sample Size typed the wrong way round (80 / LEVEL III)
                if (isset($raw['SamplingPlan'], $raw['SampleSize']) && is_numeric($raw['SamplingPlan']) && preg_match('/[A-Za-z]/', $raw['SampleSize'])) {
                    [$raw['SamplingPlan'], $raw['SampleSize']] = [$raw['SampleSize'], $raw['SamplingPlan']];
                }
                $row = [];
                foreach ($raw as $col => $text) {
                    $row[$col] = $this->value($col, $text);
                }
                $mtl = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($row['MTL'] ?? ''));
                if ($mtl === '') {
                    continue;   // blank / subtotal line
                }
                $row['MTL']         = $mtl;
                $row['RowNo']       = $row['RowNo'] ?? ++$seq;
                $row['ReportMonth'] = $month;
                if (isset($pics[$r])) {
                    $images[$mtl]     = $pics[$r];
                    $row['PhotoFile'] = $mtl . '.' . $pics[$r]['ext'];
                    unset($pics[$r]);
                }
                $rows[] = $row;
            }
            $this->leftover += count($pics);   // pictures in the sheet that matched no data row
            $this->found    += $totalPics;
        }

        $zip->close();

        return $rows ? ['rows' => $rows, 'images' => $images, 'picturesInFile' => $this->found, 'unmatchedPictures' => $this->leftover] : ['rows' => [], 'images' => [], 'error' => 'No QIMA schedule rows were found (a header row with MTL, Production Code, PO, Style ... is required).'];
    }

    /** @return array<int, array{name: string, path: string}> */
    private function sheets(SimpleZip $zip): array
    {
        $wb = @simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rl = @simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($wb === false || $rl === false) {
            return [['name' => '', 'path' => 'xl/worksheets/sheet1.xml']];
        }
        $targets = [];
        foreach ($rl->Relationship as $rel) {
            $targets[(string) $rel['Id']] = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
        }
        $out = [];
        foreach ($wb->sheets->sheet ?? [] as $s) {
            $id = (string) $s->attributes(self::NS_REL)['id'];
            if (isset($targets[$id])) {
                $out[] = ['name' => (string) $s['name'], 'path' => $targets[$id]];
            }
        }

        return $out ?: [['name' => '', 'path' => 'xl/worksheets/sheet1.xml']];
    }

    /** @var string[] merged ranges of the sheet being read ("Q3:Q6") */
    private array $merges = [];

    /** A merged cell holds its value only in the top-left cell: copy it into the rest of the range (data rows only). */
    private function fillMerged(array $grid, int $hdrRow): array
    {
        foreach ($this->merges as $ref) {
            if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $ref, $m) || (int) $m[2] <= $hdrRow) {
                continue;
            }
            $col = static function (string $letters): int {
                $n = 0;
                foreach (str_split($letters) as $ch) {
                    $n = $n * 26 + (ord($ch) - 64);
                }

                return $n;
            };
            $value = $grid[(int) $m[2]][$col($m[1])] ?? '';
            if ($value === '') {
                continue;
            }
            for ($r = (int) $m[2]; $r <= (int) $m[4]; $r++) {
                for ($c = $col($m[1]); $c <= $col($m[3]); $c++) {
                    if (($grid[$r][$c] ?? '') === '') {
                        $grid[$r][$c] = $value;
                    }
                }
            }
        }
        ksort($grid);

        return $grid;
    }

    /** @return array<int, array<int, string>> row number => column index (1-based) => text */
    private function grid(string $xml): array
    {
        $sx = @simplexml_load_string($xml);
        $this->merges = [];
        if ($sx === false) {
            return [];
        }
        $sx->registerXPathNamespace('m', self::NS_MAIN);
        foreach ($sx->xpath('//m:mergeCells/m:mergeCell') ?: [] as $mc) {
            $this->merges[] = (string) $mc['ref'];
        }
        $grid = [];
        foreach ($sx->xpath('//m:sheetData/m:row/m:c') ?: [] as $c) {
            if (! preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m)) {
                continue;
            }
            $col = 0;
            foreach (str_split($m[1]) as $ch) {
                $col = $col * 26 + (ord($ch) - 64);
            }
            $type = (string) $c['t'];
            if ($type === 's') {
                $val = $this->shared[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string) $c->is->t;
            } else {
                $val = (string) $c->v;
            }
            $grid[(int) $m[2]][$col] = trim($val);
        }
        ksort($grid);

        return $grid;
    }

    /** @return array{0: int, 1: array<string, int>} header row number and tbl_qima column => grid column */
    private function findHeader(array $grid): array
    {
        $scan = 0;
        foreach ($grid as $r => $cells) {
            if (++$scan > 40) {
                break;
            }
            $map = [];
            foreach ($cells as $c => $text) {
                $col = $this->headerColumn($text);
                if ($col !== null && ! isset($map[$col])) {
                    $map[$col] = $c;
                }
            }
            if (count($map) >= 4 && isset($map['MTL'])) {
                return [(int) $r, $map];
            }
        }

        return [0, []];
    }

    private function headerColumn(string $text): ?string
    {
        $norm = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
        if ($norm === '') {
            return null;
        }
        foreach (self::HEADERS as $col => $names) {
            if (in_array($norm, $names, true)) {
                return $col;
            }
        }
        foreach (self::HEADERS as $col => $names) {          // "PO # (Buyer)", "MTL No." ... : title starts with a known name
            foreach ($names as $n) {
                if (strlen($n) >= 4 && str_starts_with($norm, $n)) {
                    return $col;
                }
            }
        }

        return null;
    }

    private function value(string $col, string $raw): mixed
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (in_array($col, self::DATE_COLS, true)) {
            if (is_numeric($raw) && (float) $raw > 20000 && (float) $raw < 80000) {
                return gmdate('Y-m-d', (int) round(((float) $raw - 25569) * 86400));
            }
            $t = strtotime($raw);

            return $t ? date('Y-m-d', $t) : null;
        }
        if (in_array($col, self::INT_COLS, true)) {
            $n = preg_replace('/[^\d.\-]/', '', $raw);

            return is_numeric($n) ? (int) round((float) $n) : null;
        }
        if (is_numeric($raw) && str_contains($raw, 'E')) {
            return number_format((float) $raw, 0, '', '');   // long PO numbers stored as 5.2E+14
        }

        return mb_substr($raw, 0, 255);
    }

    private function reportMonth(array $grid, int $hdrRow, string $sheetName): string
    {
        $pattern = '/\b(' . self::MONTHS . ')\b[\s,\-]*(\d{4})?/i';
        foreach ($grid as $r => $cells) {
            if ($r >= $hdrRow) {
                break;
            }
            foreach ($cells as $text) {
                if (preg_match($pattern, $text, $m)) {
                    return strtoupper($m[1]) . ' ' . ($m[2] ?? date('Y'));
                }
            }
        }
        if (preg_match($pattern, $sheetName, $m)) {
            return strtoupper($m[1]) . ' ' . ($m[2] ?? date('Y'));
        }

        return strtoupper(date('F Y'));
    }

    /** @return array<int, array{ext: string, data: string}> sheet row number => picture anchored on that row */
    private function sheetPictures(SimpleZip $zip, string $sheetPath): array
    {
        $relPath = dirname($sheetPath) . '/_rels/' . basename($sheetPath) . '.rels';
        $rels    = @simplexml_load_string((string) $zip->getFromName($relPath));
        if ($rels === false) {
            return [];
        }
        $out = [];
        foreach ($rels->Relationship as $rel) {
            if (! str_ends_with((string) $rel['Type'], '/drawing')) {
                continue;
            }
            $drawing = $this->resolve(dirname($sheetPath), (string) $rel['Target']);
            $dx      = @simplexml_load_string((string) $zip->getFromName($drawing));
            $dr      = @simplexml_load_string((string) $zip->getFromName(dirname($drawing) . '/_rels/' . basename($drawing) . '.rels'));
            if ($dx === false || $dr === false) {   // not "! $dx": an element whose children are all prefixed reads as empty
                continue;
            }
            $media = [];
            foreach ($dr->Relationship as $dRel) {
                $media[(string) $dRel['Id']] = $this->resolve(dirname($drawing), (string) $dRel['Target']);
            }
            $dx->registerXPathNamespace('xdr', self::NS_XDR);
            $dx->registerXPathNamespace('a', self::NS_A);
            foreach ($dx->xpath('//xdr:twoCellAnchor | //xdr:oneCellAnchor') ?: [] as $anchor) {
                $anchor->registerXPathNamespace('xdr', self::NS_XDR);   // a node taken from a result does not inherit the prefixes
                $anchor->registerXPathNamespace('a', self::NS_A);
                $row   = $anchor->xpath('xdr:from/xdr:row');
                $blip  = $anchor->xpath('.//a:blip');
                if (! $row || ! $blip) {
                    continue;
                }
                $embed = (string) $blip[0]->attributes(self::NS_REL)['embed'];
                $file  = $media[$embed] ?? '';
                $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $data  = $file !== '' ? $zip->getFromName($file) : false;
                if ($data === false || ! in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                    continue;
                }
                // a picture can start in the row above (offset) or run over the next one: use the middle row of its box
                $to  = $anchor->xpath('xdr:to/xdr:row');
                $mid = (int) round(((int) $row[0] + ($to ? (int) $to[0] : (int) $row[0])) / 2) + 1;
                $out[$mid] = ['ext' => $ext === 'jpeg' ? 'jpg' : $ext, 'data' => $data];
            }
        }

        return $out;
    }

    /** Zip path of $target ("../media/image1.png" or "/xl/media/image1.png") seen from the folder $base. */
    private function resolve(string $base, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        $parts = explode('/', $base);
        foreach (explode('/', $target) as $seg) {
            if ($seg === '..') {
                array_pop($parts);
            } elseif ($seg !== '.' && $seg !== '') {
                $parts[] = $seg;
            }
        }

        return implode('/', $parts);
    }
}
