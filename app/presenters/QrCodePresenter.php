<?php

namespace App\Presenters;

use Nette;

use Mpdf\QrCode\QrCode;
use Mpdf\QrCode\Output;

class QrCodePresenter extends BasePresenter
{

    public function renderPng() {
        $text = $this->getParameter('text') ?: '';

        $qrCode = new QrCode($text);

        $tmpname = sprintf('/dev/shm/qrcode_%u.png', rand(1, 1e9));

        // Save black on white PNG image. Colors are RGB arrays.
        $output = new Output\Png();
        $data = $output->output($qrCode, 300, [255, 255, 255], [0, 0, 0]);
        file_put_contents($tmpname, $data);

        $this->sendResponse(new Nette\Application\Responses\FileResponse($tmpname, "QR kód pro rychlou platbu", 'image/png', false));

        unlink($tmpname);
    }
}
