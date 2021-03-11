<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class DevicePresenter extends ApiPresenter
{
    private $ipadresa;
    private $cryptosvc;
    private $log;

    function __construct(\App\Model\IPAdresa $ipadresa, \App\Services\CryptoSluzba $cryptosvc, \App\Model\Log $log) {
        $this->ipadresa = $ipadresa;
        $this->cryptosvc = $cryptosvc;
        $this->log = $log;
    }

    public function renderDefault() {
        $this->sendResponse( new JsonResponse( ['result' => 'Method not implemented'] ) ); 
    }
    
    public function actionGetCredentials($id) {
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $id));
        if(!$ip) {
            $this->sendResponse( new JsonResponse( ['result' => 'IP address not found', 'serverTime' => date("c")] ) );
        }
        
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        parent::checkApID($apOfIp);
        
        $this->sendResponse( new JsonResponse(['ip' => $ip->ip_adresa, 'login' => $ip->login, 'heslo' => $this->getHeslo($ip)]) );
    }

    public function actionSetCredentials($id) {
        parent::forceMethod("POST");
        
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $id));
        if(!$ip) {
            $this->sendResponse( new JsonResponse( ['result' => 'IP address not found', 'serverTime' => date("c")] ) );
        }
        
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        parent::checkApID($apOfIp);
  
        $beforeUpdate = array(
            'login' => $ip->login,
            'heslo' => $ip->heslo,
            'heslo_sifrovane' => $ip->heslo_sifrovane
        );
        
        $toUpdate = array('login' => $this->getHttpRequest()->getPost("login", null));

        $heslo = $this->getHttpRequest()->getPost("heslo", null);
        if($heslo && strlen($heslo) > 0) {
            $toUpdate["heslo"] = $this->cryptosvc->encrypt($heslo);
            $toUpdate["heslo_sifrovane"] = 1;
        } else {
            $toUpdate["heslo"] = "";
            $toUpdate["heslo_sifrovane"] = 0;
        }
        
        $log = array();
        $this->log->logujUpdate($beforeUpdate, $toUpdate, 'IPAdresa['.$ip->id.']', $log);
        
        if($ip->Ap_id) {
            $this->log->loguj('Ap', $ip->Ap_id, $log, 1);
        } else {
            $this->log->loguj('Uzivatel', $ip->Uzivatel_id, $log, 1);
        }
        
        $this->ipadresa->update($ip->id, $toUpdate); 
        
        $this->sendResponse( new JsonResponse( ['result' => 'OK', 'serverTime' => date("c")] ) );
    }
    
    private function getHeslo($ip) {
        if($ip->heslo_sifrovane == 1) {
            $decrypted = $this->cryptosvc->decrypt($ip->heslo);
            return($decrypted);
        } else {
            return($ip->heslo);
        }
    }
}
