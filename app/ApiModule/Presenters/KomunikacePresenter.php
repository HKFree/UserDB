<?php

namespace App\ApiModule\Presenters;

use Exception;
use Nette\Application\Responses\JsonResponse;

class KomunikacePresenter extends ApiPresenter
{
    private $komunikace;
    private $uzivatel;

    public function __construct(\App\Model\Komunikace $komunikace, \App\Model\Uzivatel $uzivatel) {
        $this->komunikace = $komunikace;
        $this->uzivatel = $uzivatel;
    }

    public function renderDefault() {
        $this->sendResponse(new JsonResponse(['result' => 'Method not implemented', 'resultNumeric' => -1]));
    }

    public function actionSendSMS() {
        parent::forceMethod("POST");

        $uid = $this->getHttpRequest()->getPost("uid", null);
        $text = $this->getHttpRequest()->getPost("text", null);

        $u = $this->uzivatel->getUzivatel($uid);
        if (!$u) {
            $this->sendResponse(new JsonResponse(['result' => 'User ID not found', 'resultNumeric' => -2, 'serverTime' => date("c")]));
        }

        parent::checkApID($u->Ap->id);

        try {
            $this->komunikace->posliSMS([$u], $text);
        } catch (Exception $e) {
            $this->sendResponse(new JsonResponse(['result' => 'SMS send failed', 'resultNumeric' => -3, 'reason' => strval($e), 'serverTime' => date("c")]));
        }

        $this->sendResponse(new JsonResponse(['result' => 'OK', 'resultNumeric' => 1]));
    }
}
