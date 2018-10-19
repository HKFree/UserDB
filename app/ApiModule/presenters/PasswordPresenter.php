<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class PasswordPresenter extends ApiPresenter
{
    private $ipadresa;

    function __construct(\App\Model\IPAdresa $ipadresa)
    {
        $this->ipadresa = $ipadresa;
    }

    public function actionDefault($id, $ip)
    {
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $ip));
        if(!$ip)
        {
            $this->sendResponse( new JsonResponse( ['result' => 'ERROR', 'message' => 'IP address not found', 'serverTime' => date("c")] ) );
        }
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        if($id && $id != $apOfIp)
        {
            //if id (ap_id) provided then return credentials only for ip from this area
            $this->sendResponse( new JsonResponse( ['result' => 'ERROR', 'message' => 'Not allowed to change this IP address', 'serverTime' => date("c")] ) );
        }
        //TODO: decrypt password
        $this->sendResponse( new JsonResponse(['ip' => $ip->ip_adresa, 'login' => $ip->login, 'heslo' => $ip->heslo]) );
    }

    public function renderSave($id, $ip, $login, $heslo) 
    {
        $ip = $this->ipadresa->findIp(array('ip_adresa' => $ip));
        if(!$ip)
        {
            $this->sendResponse( new JsonResponse( ['result' => 'ERROR', 'message' => 'IP address not found', 'serverTime' => date("c")] ) );
        }
        $apOfIp = $this->ipadresa->getAPOfIP($ip->id);
        if($id && $id != $apOfIp)
        {
            //if id (ap_id) provided then update credentials only for ip from this area
            $this->sendResponse( new JsonResponse( ['result' => 'ERROR', 'message' => 'Not allowed to change this IP address', 'serverTime' => date("c")] ) );
        }
        //TODO: encrypt password
        $this->ipadresa->update($ip->id, array('login'=>$login,'heslo'=>$heslo)); 
        $this->sendResponse( new JsonResponse( ['result' => 'OK', 'serverTime' => date("c")] ) );
    }
}
