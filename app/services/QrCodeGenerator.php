<?php

namespace App\Services;

require_once __DIR__ . '/../../lib/phpqrcode/qrlib.php';

class QrCodeGenerator
{
    public static function renderPngBase64($text) {
        $tmpname = tempnam(sys_get_temp_dir(), 'userdb-qr-');
        try {
            \QRcode::png($text, $tmpname, QR_ECLEVEL_L, 6);
            $base64png = base64_encode(file_get_contents($tmpname));
        } finally {
            unlink($tmpname);
        }

        return "data:image/png;base64,{$base64png}";
    }
}
