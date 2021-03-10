<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class PasswordPresenter extends ApiPresenter
{
    private $ipadresa;
    private $cryptosvc;

    function __construct(\App\Model\IPAdresa $ipadresa, \App\Services\CryptoSluzba $cryptosvc)
    {
        $this->ipadresa = $ipadresa;
        $this->cryptosvc = $cryptosvc;
    }

    public function actionDefault($id, $ip)
    {
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $ip));
        if(!$ip)
        {
            $this->sendResponse( new JsonResponse( ['result' => 'ERROR', 'message' => 'IP address not found', 'serverTime' => date("c")] ) );
        }
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        parent::checkApID($apOfIp);
        
        if($ip->heslo_sifrovane == 1)
        {
            $decrypted = $this->cryptosvc->decrypt($ip->heslo);
            $this->sendResponse( new JsonResponse(['ip' => $ip->ip_adresa, 'login' => $ip->login, 'heslo' => $decrypted]) );
        }
        else {
            $this->sendResponse( new JsonResponse(['ip' => $ip->ip_adresa, 'login' => $ip->login, 'heslo' => $ip->heslo]) );
        }
    }

    public function renderSave($id, $ip, $login, $heslo) 
    {
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $ip));
        if(!$ip)
        {
            $this->sendResponse( new JsonResponse( ['result' => 'ERROR', 'message' => 'IP address not found', 'serverTime' => date("c")] ) );
        }
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        parent::checkApID($apOfIp);

        $encrypted = $this->cryptosvc->encrypt($heslo);
        $this->ipadresa->update($ip->id, array('login'=>$login,'heslo'=>$encrypted, 'heslo_sifrovane'=>1)); 
        $this->sendResponse( new JsonResponse( ['result' => 'OK', 'serverTime' => date("c")] ) );
    }
}
