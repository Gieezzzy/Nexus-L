<?php

namespace App\Services\Dtr;

/** HTML to PDF bytes. (moved out of DtrController; bodies unchanged). */
class DtrPdf
{
    /** Body HTML + CSS to an A4 PDF with the installed Chrome / Edge (headless); null when it cannot be made. */
    public function htmlToPdfBytes(string $body, string $css): ?string
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
        $dir = WRITEPATH . 'cache/sheet_pdfs/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $id       = bin2hex(random_bytes(8));
        $htmlFile = $dir . $id . '.html';
        $pdfFile  = $dir . $id . '.pdf';
        file_put_contents($htmlFile, '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 portrait;margin:8mm}' . $css . '</style></head><body>' . $body . '</body></html>');
        $prof = $dir . 'p_' . $id; // own profile per run: a shared one gets locked by a leftover browser and the next run makes no PDF
        @shell_exec('"' . $browser . '" --headless=new --disable-gpu --disable-extensions --no-first-run --no-pdf-header-footer'
            . ' --user-data-dir="' . $prof . '" --print-to-pdf="' . $pdfFile . '" "file:///' . str_replace('\\', '/', $htmlFile) . '" 2>&1');
        @unlink($htmlFile);
        @shell_exec('rmdir /s /q "' . str_replace('/', '\\', $prof) . '" 2>nul');
        if (! is_file($pdfFile) || filesize($pdfFile) < 500) {
            @unlink($pdfFile);

            return null;
        }
        $bytes = (string) file_get_contents($pdfFile);
        @unlink($pdfFile);

        return $bytes;
    }
}
