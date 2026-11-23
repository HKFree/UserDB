<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class DevicePresenter extends ApiPresenter
{
    private \App\Model\IPAdresa $ipadresa;
    private \App\Services\CryptoSluzba $cryptosvc;
    private \App\Model\Log $log;

    public function __construct(\App\Model\IPAdresa $ipadresa, \App\Services\CryptoSluzba $cryptosvc, \App\Model\Log $log) {
        parent::__construct();
        $this->ipadresa = $ipadresa;
        $this->cryptosvc = $cryptosvc;
        $this->log = $log;
    }

    public function renderDefault(): void {
        $this->sendResponse(new JsonResponse(['result' => 'Method not implemented']));
    }

    /** @param string|null $id */
    public function actionGetCredentials($id): void {
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $id));
        if ($ip === null) {
            $this->sendResponse(new JsonResponse(['result' => 'IP address not found', 'serverTime' => date("c")]));
        }

        $this->checkDeviceAp($ip);

        $this->sendResponse(new JsonResponse(['ip' => $ip->ip_adresa, 'login' => $ip->login, 'heslo' => $this->getHeslo($ip)]));
    }

    /** @param string|null $id */
    public function actionSetCredentials($id): void {
        parent::forceMethod("POST");

        $ip = $this->ipadresa->findIp(array('ip_adresa' => $id));
        if ($ip === null) {
            $this->sendResponse(new JsonResponse(['result' => 'IP address not found', 'serverTime' => date("c")]));
        }

        $this->checkDeviceAp($ip);

        $beforeUpdate = array(
            'login' => $ip->login,
            'heslo' => $ip->heslo,
            'heslo_sifrovane' => $ip->heslo_sifrovane
        );

        $toUpdate = array('login' => $this->getHttpRequest()->getPost("login"));

        $heslo = $this->getHttpRequest()->getPost("heslo");
        if (in_array($heslo, [null, false, 0, 0.0, '', '0', []], true) === false && strlen($heslo) > 0) {
            $toUpdate["heslo"] = $this->cryptosvc->encrypt($heslo);
            $toUpdate["heslo_sifrovane"] = 1;
        } else {
            $toUpdate["heslo"] = "";
            $toUpdate["heslo_sifrovane"] = 0;
        }

        $log = array();
        $this->log->logujUpdate($beforeUpdate, $toUpdate, 'IPAdresa['.$ip->id.']', $log);

        if (in_array($ip->Ap_id, [null, 0], true) === false) {
            $this->log->loguj('Ap', $ip->Ap_id, $log, 1);
        } else {
            $this->log->loguj('Uzivatel', $ip->Uzivatel_id, $log, 1);
        }

        $this->ipadresa->update($ip->id, $toUpdate);

        $this->sendResponse(new JsonResponse(['result' => 'OK', 'serverTime' => date("c")]));
    }

    private function checkDeviceAp(\Nette\Database\Table\ActiveRow $ip): void {
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        if ($apOfIp === null && $this->keyApID !== null && $this->keyApID !== 0) {
            $this->sendForbidden('device AP is unknown');
        }
        parent::checkApID($apOfIp);
    }

    private function getHeslo(\Nette\Database\Table\ActiveRow $ip): ?string {
        if ($ip->heslo_sifrovane === 1) {
            $decrypted = $this->cryptosvc->decrypt($ip->heslo);
            return $decrypted;
        } else {
            return $ip->heslo;
        }
    }
}
