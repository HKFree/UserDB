<?php

namespace App\Services;

require_once __DIR__ . '/../../lib/phpqrcode/qrlib.php';

class QrCodeGenerator
{
    public static function renderPngBase64(string $text): string {
        $tmpname = tempnam(sys_get_temp_dir(), 'userdb-qr-');
        if ($tmpname === false) {
            throw new \RuntimeException('Nelze vytvořit dočasný soubor pro QR kód.');
        }
        try {
            \QRcode::png($text, $tmpname, QR_ECLEVEL_L, 6);
            $base64png = base64_encode(\Nette\Utils\FileSystem::read($tmpname));
        } finally {
            unlink($tmpname);
        }

        return "data:image/png;base64,{$base64png}";
    }
}
