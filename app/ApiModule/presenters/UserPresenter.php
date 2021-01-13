<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class UserPresenter extends ApiPresenter
{
    private $uzivatel;

    function __construct(\App\Model\Uzivatel $uzivatel)
    {
        $this->uzivatel = $uzivatel;
    }

    public function actionDefault()
    {
        $uzivatele = [];
        $uzivateleData = $this->uzivatel->getSeznamUzivatelu();
        foreach ($uzivateleData as $iduser => $u) {
            $uzivatele[$iduser] = $this->prepareUser($u);
        }
        $this->sendResponse( new JsonResponse($uzivatele) );
    }

    public function actionShow($id)
    {
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if(!$uzivatel){
            $this->sendResponse( new JsonResponse(null) );
        }
        $this->sendResponse( new JsonResponse($this->prepareUser($uzivatel)) );
    }

    private function prepareUser($u){
        $uzivatel;
        $role = [];
        foreach ($u->related('SpravceOblasti') as $idSpravce => $spravce) {
            $typSpravce = $spravce->ref('TypSpravceOblasti', 'TypSpravceOblasti_id');

            $role[$idSpravce] = [
                'id' => $spravce['id'],
                'od' => $spravce['od'],
                'do' => $spravce['do'],
                'typ' => $typSpravce['text'],
            ];
        }

        $uzivatel = [
            'id' => $u['id'],
            'jmeno' => $u['jmeno'],
            'prijmeni' => $u['prijmeni'],
            'email' => $u['email'],
            'nick' => $u['nick'],
            'role' => $role
        ];
        return $uzivatel;
    }

}
