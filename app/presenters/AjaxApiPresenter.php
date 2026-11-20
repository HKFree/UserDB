<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;
use Nette\Application\Responses\JsonResponse;

/**
 * Ajax API presenter, obsluhuje vnitroaplikační AJAXové požadavky.
 */
class AjaxApiPresenter extends BasePresenter
{
    private $subnet;
    private $sojka;

    public function __construct(Model\Subnet $subnet, Model\Sojka $sojka) {
        $this->subnet = $subnet;
        $this->sojka = $sojka;
    }

    /**
     *  Najde subnet a gateway k příslušné IP adrese
     *
     *  Parametr požadavku ip obsahuje hledanou IP adresu.
     *  @return void
     */
    public function renderGetIpDetails() {
        $errorTable = array(
            Model\Subnet::ERR_NOT_FOUND => "Podsíť a brána pro tuto IP není v databázi!",
            Model\Subnet::ERR_NO_GW => "Brána pro tuto IP není v databázi!",
            Model\Subnet::ERR_MULTIPLE_GW => "Chyba! Pro tuto IP existuje více subnetů! ",
        );

        $ip = $this->getParameter("ip");
        $out = [];

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $out["error"] = "Neplatná IP adresa!";
        } else {
            $subnet = $this->subnet->getSubnetOfIP($ip);

            if (isset($subnet["error"])) {
                $out["error"] = $errorTable[$subnet["error"]];

                if ($subnet["error"] == Model\Subnet::ERR_MULTIPLE_GW) {
                    $out["error"] .= implode(", ", $subnet["multiple_subnets"]);
                }
            } else {
                $out = $subnet;
                $out['subnetLink'] = $this->getSubnetLinkFromIpAddress($ip);
            }
        }

        // featura pro javascript
        if ($reqid = $this->getParameter("reqid")) {
            $out["reqid"] = $reqid;
        }

        $this->sendResponse(new JsonResponse($out));
    }

    /**
     *  Opingá zadané IP adresy prostřednictvím SojkaPingeru
     *
     *  Parametr požadavku ips obsahuje pole hledaných IP adres.
     *  @return void
     */
    public function renderGetIpsPing() {
        $ips = $this->getParameter("ips");

        if (empty($ips)) {
            $this->sendResponse(new JsonResponse(array()));
        }

        $p = $this->sojka->pingIPS($ips);

        $this->sendResponse(new JsonResponse($p));
    }
}
