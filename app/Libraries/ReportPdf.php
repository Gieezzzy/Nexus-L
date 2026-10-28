<?php

namespace App\Libraries;

/**
 * Report PDFs for InterLink:
 * - material reports use the lab's report forms ("Hardware Test Report Form", "VH Test Report Form", ...) filled from dbnexus,
 *   no Access involved (app/Libraries/report_templates + tools/build/render-report.js);
 * - signatures are stamped on page 1 by tools/build/stamp-pdf.js.
 * Bonding reports are the VF sheet made in the browser (dtr/sheet-pdf).
 */
class ReportPdf
{
    /** Report form of a material record, chosen like the lab does: by form and material type. */
    public static function accessReportFor(string $slug, array $row): ?string
    {
        $t = strtolower((string) ($row['MaterialType'] ?? ''));

        return match ($slug) {
            'wst'      => str_contains($t, 'velcro') ? 'VH Test Report Form'
                : (str_contains($t, 'thread') || str_contains($t, 'm30') ? 'TH Test Report Form'
                : (str_contains($t, 'lace') ? 'Lace Test Report Form' : 'WST Test Report Form')),
            'hardware' => str_contains($t, 'zipper') ? 'Zipper Test Report Form' : 'Hardware Test Report Form',
            'sklfm'    => str_contains($t, 'foam') ? 'FM Test Report Form' : 'SKL/FML Test Report Form',
            'fabric'   => str_contains($t, 'ink') ? 'Fabric Test Report Form - Ink' : 'Fabric Test Report Form - (w/ ASW)',
            'label'    => 'WL Label Test Report Form',
            'leather'  => 'Leather Test Report Form',
            'box'      => 'Box Test Report',
            'insole'   => 'Insole Test Report Form',
            'rubber'   => 'Outsole Test Report',
            'fthl'     => 'Foxing Tape and Heel Logo',
            default    => null,
        };
    }

    /**
     * The report of a material record as a PDF file path, or null. Printed without Access: the lab's blank report form plus the
     * record's values from dbnexus (tools/build/render-report.js), laid out exactly where the Access report puts them.
     */
    public static function material(string $slug, int $id): ?string
    {
        $cfg = \App\Controllers\DtrController::FORM_CONFIG[$slug] ?? null;
        if (! $cfg || $slug === 'bonding') {
            return null;
        }
        $row = \Config\Database::connect()->table($cfg['table'])->where($cfg['pk'], $id)->get()->getRowArray();
        $rpt = $row ? self::accessReportFor($slug, $row) : null;
        if (! $rpt) {
            return null;
        }
        $base   = preg_replace('/[^A-Za-z0-9]+/', '_', $rpt);
        $dir    = APPPATH . 'Libraries' . DIRECTORY_SEPARATOR . 'report_templates' . DIRECTORY_SEPARATOR;
        $outDir = WRITEPATH . 'cache/report_pdfs/';
        if (! is_file($dir . $base . '.pdf') || ! is_file($dir . $base . '.json')) {
            return null;
        }
        if (! is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }
        $pdf = $outDir . "{$slug}_{$id}.pdf";
        @unlink($pdf);
        $job = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($job, json_encode(['layout' => $dir . $base . '.json', 'template' => $dir . $base . '.pdf', 'record' => $row, 'out' => $pdf], JSON_INVALID_UTF8_SUBSTITUTE));
        $node = is_file('C:\Program Files\nodejs\node.exe') ? 'C:\Program Files\nodejs\node.exe' : 'node';
        @shell_exec('"' . $node . '" "' . ROOTPATH . 'tools\build\render-report.js" "' . $job . '" 2>&1');
        @unlink($job);

        return is_file($pdf) && filesize($pdf) > 1000 ? $pdf : null;
    }

    /** The "Reviewed by" / "Approved by" lines printed on page 1: ['approved' => ['name', 'label', ...], 'reviewed' => [...], 'w', 'h'] or []. */
    public static function signoff(string $pdf): array
    {
        $py  = is_file('C:\\Python314\\python.exe') ? 'C:\\Python314\\python.exe' : 'python';
        $out = @shell_exec('"' . $py . '" "' . ROOTPATH . 'tools\\pdf_signoff.py" "' . $pdf . '" 2>NUL');
        $j   = json_decode((string) $out, true);

        return is_array($j) && empty($j['error']) ? $j : [];
    }

    /** Does the name printed on a report ("nila", "KATE G.", "N.B.E.") belong to this signatory's full name? */
    public static function nameMatches(string $printed, string $fullName): bool
    {
        $norm = static fn (string $s): array => array_values(array_filter(preg_split('/[^a-z]+/', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $s))) ?: [], static fn ($x) => $x !== ''));
        $p    = $norm($printed);
        $f    = $norm($fullName);
        if (! $p || ! $f) {
            return false;
        }
        if (implode('', $p) === implode('', array_map(static fn ($x) => $x[0], $f))) {
            return true;                                                  // initials: N.B.E.
        }
        foreach ($p as $tok) {
            $hit = false;
            foreach ($f as $ft) {
                if ($ft === $tok || (strlen($tok) === 1 && $ft[0] === $tok)) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                return false;
            }
        }

        return true;
    }

    /** Signature PNG (base64) with its background removed: white / near-white turns transparent, then it is cropped to the ink. */
    public static function transparentPng(string $b64): string
    {
        $im = @imagecreatefromstring((string) base64_decode($b64, true));
        if (! $im) {
            return $b64;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $o = imagecreatetruecolor($w, $h);
        imagealphablending($o, false);
        imagesavealpha($o, true);
        imagefill($o, 0, 0, imagecolorallocatealpha($o, 0, 0, 0, 127));
        [$x0, $y0, $x1, $y1] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c  = imagecolorat($im, $x, $y);
                $a  = ($c >> 24) & 0x7F;                                  // 0 opaque .. 127 clear
                $r  = ($c >> 16) & 255;
                $g  = ($c >> 8) & 255;
                $b  = $c & 255;
                $lo = min($r, $g, $b);
                if ($lo >= 235) {
                    $a = 127;                                             // paper white
                } elseif ($lo >= 190) {
                    $a = max($a, (int) round(127 * (($lo - 190) / 45)));   // soft edge
                }
                if ($a >= 120) {
                    continue;
                }
                imagesetpixel($o, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);
                $x0 = min($x0, $x);
                $x1 = max($x1, $x);
                $y0 = min($y0, $y);
                $y1 = max($y1, $y);
            }
        }
        if ($x1 < 0) {
            return $b64;                                                  // nothing drawn
        }
        $crop = imagecreatetruecolor($x1 - $x0 + 1, $y1 - $y0 + 1);
        imagealphablending($crop, false);
        imagesavealpha($crop, true);
        imagefill($crop, 0, 0, imagecolorallocatealpha($crop, 0, 0, 0, 127));
        imagecopy($crop, $o, 0, 0, $x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1);
        ob_start();
        imagepng($crop);

        return base64_encode((string) ob_get_clean());
    }

    /** Copies $in to $out with the signature and date stamp of $s {png(base64), name, title, date, kind access|web}. */
    public static function stamp(string $in, string $out, array $s): bool
    {
        if (! empty($s['png'])) {
            $s['png'] = self::transparentPng($s['png']);
        }
        $s['pos'] = self::signoff($in);
        $json = tempnam(sys_get_temp_dir(), 'stp');
        file_put_contents($json, json_encode($s));
        $node = is_file('C:\\Program Files\\nodejs\\node.exe') ? 'C:\\Program Files\\nodejs\\node.exe' : 'node';
        @shell_exec('"' . $node . '" "' . ROOTPATH . 'tools\\build\\stamp-pdf.js" "' . $in . '" "' . $out . '" "' . $json . '" 2>&1');
        @unlink($json);

        return is_file($out) && filesize($out) > 1000;
    }
}
