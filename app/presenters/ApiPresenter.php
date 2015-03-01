<?php

namespace App\Presenters;

use Nette,
	App\Model,
	Tracy\Debugger,
    Nette\Application\Responses\JsonResponse;


/**
 * Error presenter.
 */
class ApiPresenter extends BasePresenter
{    
    private $subnet;
    private $ap;
    
    function __construct(Model\Subnet $subnet, Model\AP $ap) {
        $this->subnet = $subnet;
        $this->ap = $ap;
    }

	/**
     *  Najde subnet a gateway k příslušné IP adrese
     * 
     *  @param string $ip Hledaná IP adresa
     *  @return type Description
     */
	public function renderGetIpDetails()
	{       
        $errorTable = array(
            Model\Subnet::ERR_NOT_FOUND => "Podsíť a brána pro tuto IP není v databázi!",
            Model\Subnet::ERR_NO_GW => "Brána pro tuto IP není v databázi!",
            Model\Subnet::ERR_MULTIPLE_GW => "Chyba! Pro tuto IP existuje více subnetů! ",
        );
        
        $ip = $this->getParameter("ip");
        $apid = $this->getParameter("apid", -2);
        
        $ARPProxy = false;
        
        if(!filter_var($ip, FILTER_VALIDATE_IP)){
            $out["error"] = "Neplatná IP adresa!";
        } else {
            if($ap = $this->ap->getAP($apid)) {
                $ARPProxy = $ap->proxy_arp;
            }
            $subnet = $this->subnet->getSubnetOfIP($ip, $ARPProxy);
            
            if(isset($subnet["error"])) {
                $out["error"] = $errorTable[$subnet["error"]];
                
                if($subnet["error"] == Model\Subnet::ERR_MULTIPLE_GW) {
                    $out["error"] .= implode(", ", $subnet["multiple_subnets"]);
                }
            } else {
                $out = $subnet;
            }
        }
        
        // featura pro javascript
        if($reqid = $this->getParameter("reqid")) {
            $out["reqid"] = $reqid;
        }
        
        $this->sendResponse(new JsonResponse($out));
	}
}
