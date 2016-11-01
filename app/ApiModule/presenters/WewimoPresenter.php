<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class WewimoPresenter extends ApiPresenter
{
    private $wewimo;
    private $ipadresa;
    private $ap;

    function __construct(\App\Model\Wewimo $wewimo, \App\Model\IPAdresa $ipadresa, \App\Model\AP $ap)
    {
        $this->wewimo = $wewimo;
        $this->ipadresa = $ipadresa;
        $this->ap = $ap;
    }

    public function actionDefault($id)
    {
        $apt = $this->ap->getAP($id*1);
        $apIps = $apt->related('IPAdresa.Ap_id')->where('wewimo', 1);
        $apIps = $apIps->order('INET_ATON(ip_adresa)');
        $wewimoMultiData = [];
        foreach ($apIps as $ip) {
            $wewimoData;
            try {
                $wewimoData = $this->wewimo->getWewimoData($ip['ip_adresa'], $ip['login'], $ip['heslo'], $this->getUser()->getId() . " (" . $this->getUser()->getIdentity()->nick . ")");
                $wewimoData['error'] = '';
            } catch (\Exception $ex) {
                $wewimoData = [];
                $wewimoData['interfaces'] = [];
                $wewimoData['error'] = $ex->getMessage();
            }
            $wewimoData['ip'] = $ip['ip_adresa'];
            $wewimoData['hostname'] = $ip['hostname'];
            $wewimoMultiData[] = $wewimoData;
        }
        $this->sendResponse( new JsonResponse($wewimoMultiData) );
    }
}
