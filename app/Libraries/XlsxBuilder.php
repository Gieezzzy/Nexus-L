<?php

namespace App\Libraries;

/**
 * Minimal one-sheet .xlsx writer (no spreadsheet library is installed): text / number cells with styles, merged cells, column widths,
 * row heights and one column + line chart (secondary % axis) that reads its numbers from the sheet.
 */
class XlsxBuilder
{
    private string $sheet;
    /** @var array<int,array<int,array{0:mixed,1:int}>> */
    private array $cells = [];
    private array $merges = [];
    private array $cols = [];
    private array $rowH = [];
    /** @var array<int,array> charts of the first sheet */
    private array $charts = [];
    /** @var array<int,array> */
    private array $xfs = [];
    private array $fonts = [];
    private array $fills = [];
    private array $borders = [];

    /** @var XlsxBuilder[] further sheets of the workbook (they share this one's styles) */
    private array $extra = [];
    private bool $tabFirst = true;

    /** $share: another sheet of the same workbook - the two use one style table */
    public function __construct(string $sheetName, ?XlsxBuilder $share = null)
    {
        $this->sheet = preg_replace('/[\[\]\*\?\/\\\\:]/', '', $sheetName) ?: 'Sheet1';
        if ($share) {
            $this->fonts   = &$share->fonts;
            $this->fills   = &$share->fills;
            $this->borders = &$share->borders;
            $this->xfs     = &$share->xfs;
            $share->extra[] = $this;
            return;
        }
        $this->fonts   = ['<font><sz val="10"/><name val="Arial"/></font>'];
        $this->fills   = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
        $this->borders = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
        $this->xfs     = [['f' => 0, 'fi' => 0, 'b' => 0, 'h' => '', 'v' => 'center', 'w' => false, 'n' => 0]];
    }

    private static function reg(array &$list, string $xml): int
    {
        $i = array_search($xml, $list, true);
        if ($i === false) {
            $list[] = $xml;
            $i      = count($list) - 1;
        }

        return (int) $i;
    }

    /** Style: ['bold'=>bool,'italic'=>bool,'size'=>int,'color'=>'RRGGBB','fill'=>'RRGGBB','border'=>bool,'h'=>'left|center|right','wrap'=>bool,'fmt'=>int numFmtId] */
    public function style(array $o): int
    {
        $font = '<font>' . (! empty($o['bold']) ? '<b/>' : '') . (! empty($o['italic']) ? '<i/>' : '') . '<sz val="' . ($o['size'] ?? 10) . '"/><color rgb="FF' . ($o['color'] ?? '1E293B') . '"/><name val="Arial"/></font>';
        $fill = isset($o['fill']) ? '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $o['fill'] . '"/><bgColor indexed="64"/></patternFill></fill>' : '<fill><patternFill patternType="none"/></fill>';
        $bd   = ! empty($o['border'])
            ? '<border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border>'
            : (! empty($o['underline']) ? '<border><left/><right/><top/><bottom style="medium"><color rgb="FF0F2A47"/></bottom><diagonal/></border>' : '<border><left/><right/><top/><bottom/><diagonal/></border>');
        $xf = ['f' => self::reg($this->fonts, $font), 'fi' => self::reg($this->fills, $fill), 'b' => self::reg($this->borders, $bd),
            'h' => $o['h'] ?? '', 'v' => 'center', 'w' => ! empty($o['wrap']), 'n' => (int) ($o['fmt'] ?? 0)];
        $i = array_search($xf, $this->xfs, true);
        if ($i === false) {
            $this->xfs[] = $xf;
            $i           = count($this->xfs) - 1;
        }

        return (int) $i;
    }

    public static function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    public function set(int $r, int $c, $v, int $style = 0): void
    {
        $this->cells[$r][$c] = [$v, $style];
    }

    /** a merged block: the style goes on every cell so the borders / fill are complete */
    public function merge(int $r1, int $c1, int $r2, int $c2, $v = null, int $style = 0): void
    {
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) {
                $this->cells[$r][$c] = [($r === $r1 && $c === $c1) ? $v : null, $style];
            }
        }
        if ($r1 !== $r2 || $c1 !== $c2) {
            $this->merges[] = self::col($c1) . $r1 . ':' . self::col($c2) . $r2;
        }
    }

    public function width(int $c, float $w): void
    {
        $this->cols[$c] = $w;
    }

    public function height(int $r, float $h): void
    {
        $this->rowH[$r] = $h;
    }

    /** chart: [anchor => [c1, r1, c2, r2] (0-based), title, cat => "B21:F21", bars => [name, "B37:F37", rgb], line => [name, "B39:F39", rgb]] */
    public function chart(array $spec): void
    {
        $this->charts[] = $spec;   // several charts can sit on the sheet
    }

    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheetXml(bool $first = true): string
    {
        $maxC = 1;
        foreach ($this->cells as $row) {
            $maxC = max($maxC, max(array_keys($row)));
        }
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        if ($this->cols) {
            ksort($this->cols);
            $x .= '<cols>';
            foreach ($this->cols as $c => $w) {
                $x .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        ksort($this->cells);
        foreach ($this->cells as $r => $row) {
            ksort($row);
            $x .= '<row r="' . $r . '"' . (isset($this->rowH[$r]) ? ' ht="' . $this->rowH[$r] . '" customHeight="1"' : '') . '>';
            foreach ($row as $c => [$v, $s]) {
                $ref = self::col($c) . $r;
                if ($v === null || $v === '') {
                    $x .= '<c r="' . $ref . '" s="' . $s . '"/>';
                } elseif (is_int($v) || is_float($v)) {
                    $x .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $v . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . self::x((string) $v) . '</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        $this->tabFirst = $first;
        if ($this->merges) {
            $x .= '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) {
                $x .= '<mergeCell ref="' . $m . '"/>';
            }
            $x .= '</mergeCells>';
        }
        $x .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        if ($this->charts) {
            $x .= '<drawing r:id="rId1"/>';
        }

        return $x . '</worksheet>';
    }

    private function stylesXml(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $x .= '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>';
        $x .= '<fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>';
        $x .= '<borders count="' . count($this->borders) . '">' . implode('', $this->borders) . '</borders>';
        $x .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $x .= '<cellXfs count="' . count($this->xfs) . '">';
        foreach ($this->xfs as $f) {
            $x .= '<xf numFmtId="' . $f['n'] . '" fontId="' . $f['f'] . '" fillId="' . $f['fi'] . '" borderId="' . $f['b'] . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
                . '<alignment' . ($f['h'] !== '' ? ' horizontal="' . $f['h'] . '"' : '') . ' vertical="center"' . ($f['w'] ? ' wrapText="1"' : '') . '/></xf>';
        }

        return $x . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function chartXml(array $c): string
    {
        $q   = "'" . str_replace("'", "''", $this->sheet) . "'!";
        $abs = static function (string $rng) use ($q): string {
            return $q . preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $rng);
        };
        if (($c['type'] ?? '') === 'pie') {
            $dark  = ! empty($c['dark']);   // dark grey chart area with white title / legend, like the monitoring sheet
            $white = '<a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>';
            $dpt = '';
            foreach (($c['colors'] ?? []) as $i => $rgb) {
                $dpt .= '<c:dPt><c:idx val="' . $i . '"/><c:bubble3D val="0"/><c:spPr><a:solidFill><a:srgbClr val="' . $rgb . '"/></a:solidFill><a:ln w="9525"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill></a:ln></c:spPr></c:dPt>';
            }

            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><c:roundedCorners val="0"/><c:chart>'
                . '<c:title><c:tx><c:rich><a:bodyPr/><a:p><a:pPr><a:defRPr sz="' . ($dark ? 1000 : 1200) . '" b="1">' . ($dark ? $white : '') . '</a:defRPr></a:pPr><a:r><a:rPr lang="en-US" sz="' . ($dark ? 1000 : 1200) . '" b="1">' . ($dark ? $white : '') . '</a:rPr><a:t>' . self::x($c['title']) . '</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title><c:autoTitleDeleted val="0"/><c:plotArea><c:layout/>'
                . '<c:pieChart><c:varyColors val="1"/><c:ser><c:idx val="0"/><c:order val="0"/><c:tx><c:v>' . self::x($c['name'] ?? 'Cause') . '</c:v></c:tx>' . $dpt
                . '<c:dLbls><c:numFmt formatCode="0%" sourceLinked="0"/><c:spPr><a:noFill/></c:spPr><c:txPr><a:bodyPr/><a:p><a:pPr><a:defRPr sz="900" b="1"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill></a:defRPr></a:pPr><a:endParaRPr lang="en-US"/></a:p></c:txPr><c:showLegendKey val="0"/><c:showVal val="0"/><c:showCatName val="0"/><c:showSerName val="0"/><c:showPercent val="1"/><c:showBubbleSize val="0"/><c:showLeaderLines val="0"/></c:dLbls>'
                . '<c:cat><c:strRef><c:f>' . self::x($abs($c['cat'])) . '</c:f></c:strRef></c:cat><c:val><c:numRef><c:f>' . self::x($abs($c['val'])) . '</c:f></c:numRef></c:val></c:ser><c:firstSliceAng val="0"/></c:pieChart>'
                . '</c:plotArea><c:legend><c:legendPos val="' . ($dark ? 't' : 'r') . '"/><c:overlay val="0"/>' . ($dark ? '<c:txPr><a:bodyPr/><a:p><a:pPr><a:defRPr sz="800" b="1">' . $white . '</a:defRPr></a:pPr><a:endParaRPr lang="en-US"/></a:p></c:txPr>' : '') . '</c:legend><c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart>'
                . ($dark ? '<c:spPr><a:solidFill><a:srgbClr val="404040"/></a:solidFill><a:ln w="38100"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill></a:ln></c:spPr>' : '') . '</c:chartSpace>';
        }
        $cat ='<c:cat><c:strRef><c:f>' . self::x($abs($c['cat'])) . '</c:f></c:strRef></c:cat>';
        $dl  = static fn (string $fmt, string $pos): string => '<c:dLbls><c:numFmt formatCode="' . $fmt . '" sourceLinked="0"/><c:spPr><a:noFill/></c:spPr><c:txPr><a:bodyPr/><a:p><a:pPr><a:defRPr sz="800" b="1"/></a:pPr><a:endParaRPr lang="en-US"/></a:p></c:txPr><c:dLblPos val="' . $pos . '"/><c:showLegendKey val="0"/><c:showVal val="1"/><c:showCatName val="0"/><c:showSerName val="0"/><c:showPercent val="0"/><c:showBubbleSize val="0"/></c:dLbls>';
        $x   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><c:roundedCorners val="0"/><c:chart>'
            . '<c:title><c:tx><c:rich><a:bodyPr/><a:p><a:pPr><a:defRPr sz="1000" b="1"/></a:pPr><a:r><a:rPr lang="en-US" sz="1000" b="1"/><a:t>' . self::x($c['title']) . '</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title><c:autoTitleDeleted val="0"/><c:plotArea><c:layout/>'
            . '<c:barChart><c:barDir val="col"/><c:grouping val="clustered"/><c:varyColors val="0"/><c:ser><c:idx val="0"/><c:order val="0"/><c:tx><c:v>' . self::x($c['bars'][0]) . '</c:v></c:tx><c:spPr><a:solidFill><a:srgbClr val="' . $c['bars'][2] . '"/></a:solidFill></c:spPr><c:invertIfNegative val="0"/>' . $dl('0', 'outEnd') . $cat
            . '<c:val><c:numRef><c:f>' . self::x($abs($c['bars'][1])) . '</c:f></c:numRef></c:val></c:ser><c:gapWidth val="80"/><c:axId val="111"/><c:axId val="112"/></c:barChart>'
            . '<c:lineChart><c:grouping val="standard"/><c:varyColors val="0"/><c:ser><c:idx val="1"/><c:order val="1"/><c:tx><c:v>' . self::x($c['line'][0]) . '</c:v></c:tx><c:spPr><a:ln w="28575"><a:solidFill><a:srgbClr val="' . $c['line'][2] . '"/></a:solidFill></a:ln></c:spPr><c:marker><c:symbol val="circle"/><c:size val="6"/><c:spPr><a:solidFill><a:srgbClr val="' . $c['line'][2] . '"/></a:solidFill></c:spPr></c:marker>' . $dl('0.00%', 't') . $cat
            . '<c:val><c:numRef><c:f>' . self::x($abs($c['line'][1])) . '</c:f></c:numRef></c:val><c:smooth val="0"/></c:ser><c:marker val="1"/><c:axId val="113"/><c:axId val="114"/></c:lineChart>'
            . '<c:catAx><c:axId val="111"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:numFmt formatCode="General" sourceLinked="0"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/><c:crossAx val="112"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/><c:noMultiLvlLbl val="0"/></c:catAx>'
            . '<c:valAx><c:axId val="112"/><c:scaling><c:orientation val="minMax"/><c:min val="0"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:majorGridlines><c:spPr><a:ln w="6350"><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill></a:ln></c:spPr></c:majorGridlines><c:numFmt formatCode="0" sourceLinked="0"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/><c:crossAx val="111"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx>'
            . '<c:catAx><c:axId val="113"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="1"/><c:axPos val="b"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/><c:crossAx val="114"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/><c:noMultiLvlLbl val="0"/></c:catAx>'
            . '<c:valAx><c:axId val="114"/><c:scaling><c:orientation val="minMax"/><c:min val="0"/></c:scaling><c:delete val="0"/><c:axPos val="r"/><c:numFmt formatCode="0%" sourceLinked="0"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/><c:crossAx val="113"/><c:crosses val="max"/><c:crossBetween val="between"/></c:valAx>'
            . '</c:plotArea><c:legend><c:legendPos val="b"/><c:overlay val="0"/></c:legend><c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart></c:chartSpace>';

        return $x;
    }

    /** the .xlsx file as a binary string */
    public function build(): string
    {
        $z = new class () {
            public array $files = [];
            public function addFromString(string $n, string $d): void { $this->files[$n] = $d; }
        };
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . implode('', array_map(static fn ($i) => '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, 1 + count($this->extra)))) . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($this->charts ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' . implode('', array_map(static fn ($i) => '<Override PartName="/xl/charts/chart' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>', range(1, count($this->charts)))) : '')
            . '</Types>';
        $z->addFromString('[Content_Types].xml', $ct);
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::x($this->sheet) . '" sheetId="1" r:id="rId1"/>' . implode('', array_map(fn ($i, $s) => '<sheet name="' . self::x($s->sheet) . '" sheetId="' . ($i + 2) . '" r:id="rId' . ($i + 3) . '"/>', array_keys($this->extra), $this->extra)) . '</sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . implode('', array_map(static fn ($i) => '<Relationship Id="rId' . ($i + 3) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 2) . '.xml"/>', array_keys($this->extra))) . '</Relationships>');
        $z->addFromString('xl/styles.xml', $this->stylesXml());
        $z->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
        foreach ($this->extra as $i => $s) {
            $z->addFromString('xl/worksheets/sheet' . ($i + 2) . '.xml', $s->sheetXml(false));
        }
        if ($this->charts) {
            $z->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
            $anchors = '';
            $rels    = '';
            foreach ($this->charts as $i => $ch) {
                $n = $i + 1;
                [$c1, $r1, $c2, $r2] = $ch['anchor'];
                $anchors .= '<xdr:twoCellAnchor><xdr:from><xdr:col>' . $c1 . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $r1 . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>' . $c2 . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $r2 . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
                    . '<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="' . ($n + 1) . '" name="Chart ' . $n . '"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr><xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId' . $n . '"/></a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>';
                $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart' . $n . '.xml"/>';
                $z->addFromString('xl/charts/chart' . $n . '.xml', $this->chartXml($ch));
            }
            $z->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' . $anchors . '</xdr:wsDr>');
            $z->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
        }

        return self::zip($z->files);
    }

    /** a .zip of the given files (name => content), deflated when zlib is there (no zip extension needed) */
    private static function zip(array $files): string
    {
        $out = ''; $cen = ''; $n = 0;
        $dt  = (int) ((1 << 21) | (1 << 16));   // 1980-01-01 00:00
        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $raw = strlen($data);
            $def = function_exists('gzdeflate') ? gzdeflate($data, 6) : false;
            $method = $def !== false && strlen($def) < $raw ? 8 : 0;
            $body   = $method === 8 ? $def : $data;
            $off    = strlen($out);
            $nm     = (string) $name;
            $out   .= pack('VvvvVVVVvv', 0x04034b50, 20, 0, $method, $dt, $crc, strlen($body), $raw, strlen($nm), 0) . $nm . $body;
            $cen   .= pack('VvvvvVVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, $dt, $crc, strlen($body), $raw, strlen($nm), 0, 0, 0, 0, 0, $off) . $nm;
            $n++;
        }

        return $out . $cen . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cen), strlen($out), 0);
    }
}
