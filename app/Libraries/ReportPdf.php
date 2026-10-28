<?php

namespace App\Libraries;

/**
 * Report PDFs for InterLink:
 * - material reports are the lab's official Access reports ("Hardware Test Report Form", "VH Test Report Form", ...)
 *   printed for one record through Access (app/Libraries/export_access_report.ps1);
 * - signatures are stamped on page 1 by tools/build/stamp-pdf.js.
 * Bonding reports are the VF sheet made in the browser (dtr/sheet-pdf).
 */
class ReportPdf
{
    /** the Access front-end that holds the material reports; a private copy is used so the lab's file is never locked */
    private const MATERIAL_FE = 'D:\\#Digital Test Report\\V20261006.accdb';

    /** Access report of a material record, chosen like the lab does: by form and material type. */
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

    private static function frontEnd(): ?string
    {
        if (! is_file(self::MATERIAL_FE)) {
            return null;
        }
        $dir = WRITEPATH . 'cache/access_fe/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $copy = $dir . 'material_fe.accdb';
        if (! is_file($copy) || filemtime($copy) < filemtime(self::MATERIAL_FE)) {
            @copy(self::MATERIAL_FE, $copy . '.tmp') && @rename($copy . '.tmp', $copy);
        }

        return is_file($copy) ? $copy : null;
    }

    /** Official Access report of a material record as a PDF file path (kept 5 minutes), or null. */
    public static function material(string $slug, int $id): ?string
    {
        $cfg = \App\Controllers\DtrController::FORM_CONFIG[$slug] ?? null;
        if (! $cfg || $slug === 'bonding') {
            return null;
        }
        $row = \Config\Database::connect()->table($cfg['table'])->where($cfg['pk'], $id)->get()->getRowArray();
        $rpt = $row ? self::accessReportFor($slug, $row) : null;
        $fe  = $rpt ? self::frontEnd() : null;
        if (! $fe) {
            return null;
        }
        $dir = WRITEPATH . 'cache/report_pdfs/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pdf = $dir . "{$slug}_{$id}.pdf";
        if (is_file($pdf) && filesize($pdf) > 1000 && filemtime($pdf) > time() - 300) {
            return $pdf;
        }
        // one Access at a time: two reports printed together would fight over the same front-end copy
        $lock = fopen($dir . 'access.lock', 'c');
        flock($lock, LOCK_EX);
        @unlink($pdf);
        @shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg(APPPATH . 'Libraries/export_access_report.ps1')
            . ' -Database ' . escapeshellarg($fe) . ' -Report ' . escapeshellarg($rpt) . ' -Where ' . escapeshellarg('ID = ' . $id)
            . ' -OutPdf ' . escapeshellarg($pdf) . ' 2>&1');
        flock($lock, LOCK_UN);
        fclose($lock);

        return is_file($pdf) && filesize($pdf) > 1000 ? $pdf : null;
    }

    /** Copies $in to $out with the signature and date stamp of $s {png(base64), name, title, date, kind access|web}. */
    public static function stamp(string $in, string $out, array $s): bool
    {
        $json = tempnam(sys_get_temp_dir(), 'stp');
        file_put_contents($json, json_encode($s));
        $node = is_file('C:\\Program Files\\nodejs\\node.exe') ? 'C:\\Program Files\\nodejs\\node.exe' : 'node';
        @shell_exec('"' . $node . '" "' . ROOTPATH . 'tools\\build\\stamp-pdf.js" "' . $in . '" "' . $out . '" "' . $json . '" 2>&1');
        @unlink($json);

        return is_file($out) && filesize($out) > 1000;
    }
}
