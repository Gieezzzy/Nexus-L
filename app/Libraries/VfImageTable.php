<?php

namespace App\Libraries;

/**
 * KC Test requests sometimes come with the request table pasted into the mail as a PICTURE. This reads such a picture (Tesseract OCR, English
 * characters only - the Prod Code, Style and MTL# are Latin) and gives back the rows. A row is a line that carries a Prod Code (VSP########-KC);
 * its MTL# / Style / CRD are then taken from the ERP production order of that Prod Code when it is there, because OCR confuses 0/O and 9/O in
 * the MTL#. Used only for KC Test mails: nothing here is called for CPSIA.
 */
class VfImageTable
{
    private const PROD_RE = '/\b[VW][S5]P\s?(\d{8})\s?[-\x{2013}\x{2014}_]?\s?([A-Z]{2})\b/iu';
    private const MTL_RE  = '/\bV[NM][0OQ]{3}[A-Z0-9]{6}\b/i';

    /** path of tesseract.exe, '' when it is not installed */
    public static function tesseract(): string
    {
        $cfg = trim((string) SystemSettings::get('vf.tesseract_path', ''));
        $cands = array_filter([$cfg, 'C:\Program Files\Tesseract-OCR\tesseract.exe', 'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe']);
        foreach (glob('C:\Users\*\AppData\Local\Programs\Tesseract-OCR\tesseract.exe') ?: [] as $g) {
            $cands[] = $g;
        }
        foreach ($cands as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return '';
    }

    /** the text Tesseract reads from one picture ('' when it could not) */
    public static function ocr(string $bytes): string
    {
        $exe = self::tesseract();
        if ($exe === '' || $bytes === '') {
            return '';
        }
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vf_ocr_' . bin2hex(random_bytes(6)) . '.png';
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
        if ($img) {
            // table screenshots are small: 3x bigger, grey and a little more contrast reads far better
            $w   = imagesx($img);
            $h   = imagesy($img);
            $big = imagecreatetruecolor($w * 3, $h * 3);
            imagecopyresampled($big, $img, 0, 0, 0, 0, $w * 3, $h * 3, $w, $h);
            imagefilter($big, IMG_FILTER_GRAYSCALE);
            imagefilter($big, IMG_FILTER_CONTRAST, -40);
            imagepng($big, $tmp);
            imagedestroy($big);
            imagedestroy($img);
        } else {
            file_put_contents($tmp, $bytes);
        }
        $out = [];
        @exec('"' . $exe . '" "' . $tmp . '" stdout --psm 6 -l eng 2>NUL', $out);
        @unlink($tmp);

        return implode("\n", $out);
    }

    /** @return list<array{factory:string,prod_code:string,style_no:string,mtl_no:string,crd:string}> */
    public static function rowsFromText(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            if (! preg_match(self::PROD_RE, $line, $pm, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $prod = 'VSP' . $pm[1][0] . '-' . strtoupper($pm[2][0]);
            if (isset($rows[$prod])) {
                continue;
            }
            $after = substr($line, $pm[0][1] + strlen($pm[0][0]));
            $mtl   = '';
            $style = trim((string) preg_replace('/[|_\[\]]+/', ' ', $after));
            if (preg_match(self::MTL_RE, $after, $mm, PREG_OFFSET_CAPTURE)) {
                $mtl   = strtoupper(preg_replace('/^VM/i', 'VN', $mm[0][0]));
                $mtl   = 'VN' . str_replace(['O', 'Q'], '0', substr($mtl, 2, 3)) . substr($mtl, 5);
                $style = trim((string) preg_replace('/[|_\[\]]+/', ' ', substr($after, 0, $mm[0][1])));
            }
            $crd = preg_match('/\b(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $line, $dm) ? sprintf('%04d-%02d-%02d', $dm[1], $dm[2], $dm[3]) : '';
            $rows[$prod] = ['factory' => 'DTP', 'prod_code' => $prod, 'style_no' => $style, 'mtl_no' => $mtl, 'crd' => $crd];
        }

        return array_values($rows);
    }

    /** rows from several pictures: the first picture that holds a table wins (it is the newest message of a chain) */
    public static function rowsFromImages(array $imageBytes): array
    {
        foreach ($imageBytes as $b) {
            $rows = self::rowsFromText(self::ocr((string) $b));
            if ($rows) {
                return self::fixFromErp($rows);
            }
        }

        return [];
    }

    /** the ERP production order of the Prod Code is the truth for the MTL#, style and CRD (OCR mixes 0/O and 9/O) */
    private static function fixFromErp(array $rows): array
    {
        try {
            $db = \Config\Database::connect();
            foreach ($rows as &$r) {
                $e = $db->query('SELECT mtl_no, style_no, crd FROM dberp.production_orders WHERE prod_code = ? LIMIT 1', [$r['prod_code']])->getRowArray();
                if ($e) {
                    $r['mtl_no']   = (string) ($e['mtl_no'] ?: $r['mtl_no']);
                    $r['style_no'] = (string) ($e['style_no'] ?: $r['style_no']);
                    $r['crd']      = (string) ($e['crd'] ?: $r['crd']);
                }
            }
            unset($r);
        } catch (\Throwable $e) {
            // without the ERP the OCR values stay as they are
        }

        return $rows;
    }

    /** the rows as the kind of HTML table the request e-mail parser already understands */
    public static function toHtml(array $rows): string
    {
        $h = '<table><tr><th>Factory</th><th>指令编号 Prod Code</th><th>型體 Style No.</th><th>MTL#</th><th>DTP交期</th></tr>';
        foreach ($rows as $r) {
            $h .= '<tr><td>' . htmlspecialchars($r['factory']) . '</td><td>' . htmlspecialchars($r['prod_code']) . '</td><td>' . htmlspecialchars($r['style_no']) . '</td><td>' . htmlspecialchars($r['mtl_no']) . '</td><td>' . htmlspecialchars($r['crd']) . '</td></tr>';
        }

        return $h . '</table>';
    }
}
