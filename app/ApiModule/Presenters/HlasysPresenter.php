<?php

namespace App\ApiModule\Presenters;

use App\Model\TypSpravceOblasti;
use Nette\Application\Responses\JsonResponse;
use App\Model;

class HlasysPresenter extends ApiPresenter
{
    private Model\SpravceOblasti $spravceOblasti;
    private TypSpravceOblasti $typSpravceOblasti;
    private Model\Uzivatel $uzivatel;

    public function __construct(\App\Model\SpravceOblasti $spravceOblasti, TypSpravceOblasti $typSpravceOblasti, \App\Model\Uzivatel $uzivatel) {
        $this->spravceOblasti = $spravceOblasti;
        $this->typSpravceOblasti = $typSpravceOblasti;
        $this->uzivatel = $uzivatel;
    }

    /** @param string|null $typSpravce */
    public function actionGetSpravce($typSpravce): void {
        $typSpravceRec = $this->typSpravceOblasti->findOneBy(['text' => $typSpravce]);
        if (!$typSpravceRec) {
            $this->sendResponse(new JsonResponse(['result' => 'ERROR, typ spravce '.$typSpravce.' not found']));
        }

        $spravci = $this->spravceOblasti->getSpravce($typSpravceRec->id, true);

        $out = array();
        foreach ($spravci as $spravce) {
            $out[$spravce->uzivatel->id] = $spravce->uzivatel->nick;
        }

        $this->sendResponse(new JsonResponse(['result' => 'OK', 'spravci' => $out, 'typSpravce' => $typSpravce]));
    }

    public function actionGetClenySpolku(): void {
        $clenove = $this->uzivatel->findAll()->where('spolek = ? AND TypClenstvi_id > ?', 1, 1)->where('systemovy = ?', 0);

        $out = array();
        foreach ($clenove as $clen) {
            $out[$clen->id] = $clen->nick;
        }

        $this->sendResponse(new JsonResponse(['result' => 'OK', 'clenove' => $out]));
    }
}
