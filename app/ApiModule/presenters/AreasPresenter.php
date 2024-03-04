<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class AreasPresenter extends ApiPresenter
{
    private $oblast;

    function __construct(\App\Model\Oblast $oblast)
    {
        $this->oblast = $oblast;
    }

    public function actionDefault()
    {
        $oblasti = [];
        $oblastiData = $this->oblast->getSeznamOblasti();
        foreach ($oblastiData as $idoblast => $o) {
            if($o['id'] < 0) {
                continue;
            }

            $oblasti[$idoblast] = [
                'id' => $o['id'],
                'jmeno' => $o['jmeno'],
            ];
            // associated APs
            $apckaData = $o->related('Ap.Oblast_id')->order("jmeno");
            $apcka = [];
            foreach ($apckaData as $idApcka => $apcko) {
                $apcka[$idApcka] = [
                    'jmeno' => $apcko['jmeno'],
                    'id' => $apcko['id'],
                    'gps' => $apcko['gps']
                ];
            }
            $oblasti[$idoblast]['aps'] = $apcka;
            // associated admins
            $spravci = [];
            foreach ($o->related('SpravceOblasti.Oblast_id')->where('SpravceOblasti.od < NOW() AND (SpravceOblasti.do IS NULL OR SpravceOblasti.do > NOW())') as $spravceMtm) {
                $spravce = $spravceMtm->ref('Uzivatel', 'Uzivatel_id');
                if($spravce['systemovy']) {
                    continue;
                }

                $role = $spravceMtm->ref('TypSpravceOblasti', 'TypSpravceOblasti_id');
                $spravci[$spravce['id']] = [
                    'id' => $spravce['id'],
                    'nick' => $spravce['nick'],
                    'email' => $spravce['email'],
                    'role' => $role['text'],
                ];
            }
            $oblasti[$idoblast]['admins'] = $spravci;
        }
        $this->sendResponse( new JsonResponse($oblasti) );
    }
}
