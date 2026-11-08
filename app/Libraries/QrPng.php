<?php

namespace App\Libraries;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** A QR code as a PNG picture, made on the server (for the Synology message and the e-mail, which cannot run the page's script). */
class QrPng
{
    /** the PNG bytes of a QR code that holds $text */
    public static function make(string $text, int $scale = 8): string
    {
        $o = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'  => false,
            'eccLevel'      => EccLevel::M,
            'scale'         => $scale,
            'quietzoneSize' => 3,
            'imageTransparent' => false,
        ]);

        return (new QRCode($o))->render($text);
    }
}
