<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse,
    App\Model;

class MonitoringPresenter extends ApiPresenter
{
    private $typZarizeni;
    private $ipAdresa;

    function __construct(Model\TypZarizeni $typZarizeni, Model\IPAdresa $iPAdresa) {
        $this->typZarizeni = $typZarizeni;
        $this->ipAdresa = $iPAdresa;
    }

    public function actionGetTypyZarizeni()
    {
        $typyZarizení = $this->typZarizeni->getTypyZarizeni();

        $out = array();

        foreach($typyZarizení as $typ) {
            $out[$typ->id] = $typ->text;
        }

        $this->sendResponse( new JsonResponse(['result' => 'OK', 'typyZarizeni' => $out]) );
    }

    public function actionGetZarizeni($typ, $uzivatele=0) {
        $typZarizeni = $this->typZarizeni->find($typ);

        if(!$typZarizeni) {
            $this->sendResponse( new JsonResponse(['result' => 'ERROR', 'error' => 'typZarizeni ' . $typ . " not valid"]) );
        }

        $adresy = $this->ipAdresa->findAll()->where('TypZarizeni_id', $typ);

        if(!$uzivatele) {
            $adresy = $adresy->where('Ap_id IS NOT NULL');
        }

        $out = array();
        foreach($adresy as $adresa) {
            $out[$adresa->ip_adresa] = array(
                'hostname' => $adresa->hostname,
                'popis' => $adresa->popis
            );

            if($adresa->Ap_id) {
                $out[$adresa->ip_adresa]['Ap_id'] = $adresa->Ap_id;
            } else {
                $out[$adresa->ip_adresa]['Uzivatel_id'] = $adresa->Uzivatel_id;
            }
        }
        $this->sendResponse( new JsonResponse(['result' => 'OK', 'typZarizeni' => $typ, 'uzivatele' => $uzivatele, 'zarizeni' => $out]) );
    }
}
