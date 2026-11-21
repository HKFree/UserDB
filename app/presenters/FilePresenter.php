<?php

namespace App\Presenters;

use App\Model;
use Nette\Application\Responses;

/**
 * File presenter.
 */
class FilePresenter extends BasePresenter
{
    /** @var Model\IPAdresa **/
    private $ipAdresa;

    /** @var Model\AP **/
    private $ap;

    public function __construct(Model\IPAdresa $ipAdresa, Model\AP $ap) {
        parent::__construct();
        $this->ipAdresa = $ipAdresa;
        $this->ap = $ap;
    }

    /** @param int|string|null $id */
    public function actionDownloadWinboxCmd($id): void {
        if ($id) {
            $ip = $this->ipAdresa->getIPAdresa($id);
            if ($ip !== null) {
                $apOfIp = $this->ipAdresa->getAPOfIP($ip);
                if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($apOfIp, $this->getUser())) {
                    $cmd = "winbox.exe " . $ip->ip_adresa . " " . $ip->login . "\n";
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
