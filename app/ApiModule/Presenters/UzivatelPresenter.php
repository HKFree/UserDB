<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Http\Response;

class UzivatelPresenter extends ApiPresenter
{
    private \App\Model\Uzivatel $uzivatel;
    private \App\Model\IPAdresa $ipAdresa;

    public function __construct(\App\Model\Uzivatel $uzivatel, \App\Model\IPAdresa $ipAdresa) {
        $this->uzivatel = $uzivatel;
        $this->ipAdresa = $ipAdresa;
    }

    public function actionGetUzivatel(int $id): void {
        parent::forceMethod('GET');

        $uzivatel = $this->uzivatel->getUzivatel($id);
        if (!$uzivatel) {
            $this->httpResponse->setCode(Response::S404_NOT_FOUND);
            $this->sendResponse(new JsonResponse(['result' => 'User ID not found']));
        }

        $data = $uzivatel->toArray();
        parent::checkApID($data['Ap_id']);

        $pole = [
            'jmeno',
            'prijmeni',
            'nick',
            'email',
            'email2',
            'ulice_cp',
            'mesto',
            'psc',
            'telefon',
            'zalozen',
            'TypClenstvi_id',
            'spolek',
            'druzstvo',
            'smazano',
            'TypPravniFormyUzivatele_id',
            'firma_nazev',
            'firma_ico',
            'money_aktivni',
            'systemovy',
        ];

        $this->sendResponse(new JsonResponse([
            'result' => 'OK',
            'uzivatel' => array_intersect_key($data, array_flip($pole)),
        ]));
    }

    public function actionGetUzivatelbyIP(string $id): void {
        parent::forceMethod('GET');

        $ip = $this->ipAdresa->findIp(['ip_adresa' => $id, 'Ap_id' => null]);
        $uzivatel = $ip ? $ip->Uzivatel : null;
        if (!$uzivatel) {
            $this->httpResponse->setCode(Response::S404_NOT_FOUND);
            $this->sendResponse(new JsonResponse(['result' => 'User for IP address not found']));
        }

        parent::checkApID($uzivatel->Ap_id);
        $this->sendResponse(new JsonResponse(['result' => 'OK', 'Uzivatel_id' => $uzivatel->id]));
    }
}
