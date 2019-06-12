<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse,
    Nette;

class AppPresenter extends ApiPresenter
{
    private $uzivatel;
    private $aplikaceToken;
    private $aplikaceLog;

    function __construct(\App\Model\Uzivatel $uzivatel, \App\Model\AplikaceToken $aplikaceToken, \App\Model\AplikaceLog $aplikaceLog)
    {
        $this->uzivatel = $uzivatel;
        $this->aplikaceToken = $aplikaceToken;
        $this->aplikaceLog = $aplikaceLog;
    }

    public function renderGetToken()
    {
        if($this->request->method != 'POST') {
            $this->sendLoginFailed();
        }

        if($this->getFailedGetTokenAttempts() > 5) {
            $this->sendResponse( new JsonResponse(['result' => 'Too many unsuccessful attempts, try again in 15 minutes']) );
        }

        $uid = $this->request->getPost('uid');
        $heslo = $this->request->getPost('heslo');

        $u = $this->uzivatel->getUzivatel($uid);
        if(!$u) {
            $this->sendLoginFailed($uid);
        }

        if($u->TypClenstvi_id <= 1) {
            $this->sendLoginFailed($uid);
        }

        if($u->heslo_strong_hash === $this->uzivatel->generateStrongHash($heslo))
        {
            $token = $this->aplikaceToken->createAplikaceToken($uid);
            $this->aplikaceLog->log('app.getToken.successful', array($token->id));
            $this->sendResponse( new JsonResponse(['result' => 'OK', 'token' => $token->token]) );
        }

        $this->sendLoginFailed($uid);
    }

    private function getFailedGetTokenAttempts()
    {
        return($this->aplikaceLog->getLogy()
            ->where('action', 'app.getToken.failed')
            ->where('ip', $this->httpRequest->remoteAddress)
            ->where('time > ?', new Nette\Utils\DateTime('now - 15 minutes'))
            ->count());
    }

    private function sendLoginFailed($uid = '')
    {
        $this->aplikaceLog->log('app.getToken.failed', array($uid));
        $this->sendResponse( new JsonResponse(['result' => 'Login failed']) );
    }

    public function renderGetMembership($uid, $token)
    {
        $this->verifyToken($uid, $token);

        $u = $this->uzivatel->getUzivatel($uid);
        if(!$u) {
            $this->sendResponse( new JsonResponse(['result' => 'App error, userid not found']) );
        }

        $this->aplikaceLog->log('app.getMembership.successful', array($uid, $token));
        $this->sendResponse( new JsonResponse(['result' => 'OK', 'clenstvi' => $u->TypClenstvi->text, 'jmeno' => $u->jmeno]) );
    }

    private function verifyToken($uid, $token)
    {
        if(!$this->aplikaceToken->verifyToken($uid, $token)) {
            $this->aplikaceLog->log('app.verifyToken.failed', array($uid, $token));
            $this->sendResponse( new JsonResponse(['result' => 'Token invalid']) );
        }
    }
}
