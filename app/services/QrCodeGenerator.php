<?php

namespace App\Services;

require_once('../lib/phpqrcode/qrlib.php');

class QrCodeGenerator
{
  public static function renderPngBase64($text) {
    $tmpname = sprintf('/dev/shm/qrcode_%u.png', rand(1, 1e9));
    \QRcode::png($text, $tmpname, QR_ECLEVEL_L, 6);

    $binaryPng = file_get_contents($tmpname);
    $base64png = base64_encode($binaryPng);

    unlink($tmpname);

    return "data:image/png;base64,{$base64png}";
  }
}
