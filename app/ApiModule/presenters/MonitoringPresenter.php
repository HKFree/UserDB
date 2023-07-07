<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse,
    App\Model;

class MonitoringPresenter extends ApiPresenter
{
    private $typZarizeni;
    private $ipAdresa;
    private $ap;

    function __construct(Model\TypZarizeni $typZarizeni, Model\IPAdresa $iPAdresa, Model\AP $ap) {
        $this->typZarizeni = $typZarizeni;
        $this->ipAdresa = $iPAdresa;
        $this->ap = $ap;
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

    public function actionGetZarizeni($typ, $uzivatele=0, $ap=null) {
        parent::checkApID($ap);

        $typZarizeni = $this->typZarizeni->find($typ);

        if(!$typZarizeni) {
            $this->sendResponse( new JsonResponse(['result' => 'ERROR, typZarizeni ' . $typ . ' not valid']) );
        }

        $adresy = $this->ipAdresa->findAll()->where('TypZarizeni_id', $typZarizeni->id);

        if(!$uzivatele) {
            $adresy = $adresy->where('Ap_id IS NOT NULL');
        }

        if($ap) {
            $apRec = $this->ap->find($ap);
            if(!$apRec) {
                $this->sendResponse( new JsonResponse(['result' => 'ERROR, AP ID ' . $ap . ' does not exist']) );
            }

            $adresy = $adresy->where("Ap_id", $apRec->id);
        }

        if($ap && $uzivatele) {
            $this->sendResponse( new JsonResponse(['result' => 'ERROR, selecting AP with uzivatele not implemented yet']) );
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
