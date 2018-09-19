<?php

namespace App\Presenters;

use App\Model,
    Nette\Application\Responses;
/**
 * File presenter.
 */
class FilePresenter extends BasePresenter
{
    /** @var Model\IPAdresa **/
    private $ipAdresa;
    
    /** @var Model\Ap **/
    private $ap;
    
    function __construct(Model\IPAdresa $ipAdresa, Model\AP $ap) {
    	$this->ipAdresa = $ipAdresa;
        $this->ap = $ap;
    }


    public function actionDownloadWinboxCmd($id) {
        if($id) {
            $ip = $this->ipAdresa->getIPAdresa($id);
            if ($ip) {
                $apOfIp = $this->ipAdresa->getAPOfIP($ip);
                if($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($apOfIp, $this->getUser())) {

                    $cmd = "C:\winbox.exe " . $ip->ip_adresa . " " . $ip->login . "\n";
                    $t = tempnam(sys_get_temp_dir(), 'wbx');
                    file_put_contents($t, $cmd);

                    $this->sendResponse(new Responses\FileResponse($t, "winbox.bat"));
                } else {
                    $this->error("IP not found");
                }
            } else {
                $this->error("IP not found");
            }
        } else {
            $this->error("ID not found");
        }
    }
}
