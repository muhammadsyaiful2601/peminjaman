<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use RuntimeException;

/**
 * Render QR Code menjadi gambar PNG biner tanpa ekstensi imagick.
 *
 * Runtime PHP bawaan aplikasi desktop hanya menyertakan ekstensi gd, sedangkan
 * output PNG bawaan simple-qrcode/bacon-qr-code membutuhkan imagick. Helper ini
 * meng-encode data memakai Encoder milik bacon-qr-code lalu menggambar
 * matriksnya langsung dengan gd sehingga aman dipakai di perangkat mana pun.
 *
 * PNG diperlukan untuk gambar QR yang ditanam inline (CID) pada email karena
 * sebagian besar klien email (Gmail, Outlook, Yahoo) tidak mendukung SVG maupun
 * gambar ber-URI data.
 */
class QrPng
{
    public static function generate(string $data, int $targetSize = 360, int $quietZone = 4): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Ekstensi gd dibutuhkan untuk membuat gambar QR Code.');
        }

        $matrix = Encoder::encode($data, ErrorCorrectionLevel::H())->getMatrix();
        $modules = $matrix->getWidth();
        $totalModules = $modules + ($quietZone * 2);
        $scale = max(1, (int) floor($targetSize / $totalModules));
        $dimension = $totalModules * $scale;

        $image = imagecreatetruecolor($dimension, $dimension);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = ($quietZone + $x) * $scale;
                $top = ($quietZone + $y) * $scale;
                imagefilledrectangle($image, $left, $top, $left + $scale - 1, $top + $scale - 1, $black);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
