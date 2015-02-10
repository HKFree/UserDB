<?php

namespace App\Presenters;

use Nette,
	App\Model,
	Tracy\Debugger,
    Nette\Application\Responses\JsonResponse    ;


/**
 * Error presenter.
 */
class ApiPresenter extends BasePresenter
{    
    private $subnet;
    
    function __construct(Model\Subnet $subnet) {
        $this->subnet = $subnet;
    }

	/**
     *  Najde subnet a gateway k příslušné IP adrese
     * 
     *  @param string $ip Hledaná IP adresa
     *  @return type Description
     */
	public function renderGetIpDetails()
	{        
        $ip = $this->getParameter("ip");
        if(!filter_var($ip, FILTER_VALIDATE_IP)){
            $out = array('error' => "Neplatná IP adresa");
        } else {
            $subnets = $this->subnet->getSubnetOfIP($ip);
            if(count($subnets) == 1) {
                $subnet = $subnets->fetch();
                if(empty($subnet->subnet)) {
                    $out = array('error' => "Podsíť pro tuto IP není v databázi!");
                } elseif( empty($subnet->gateway)) {
                    $out = array('error' => "Brána pro tuto IP není v databázi!");
                } else {
                    list($network, $cidr) = explode("/", $subnet->subnet);
                    $out = array('subnet' => $subnet->subnet, 'gateway' => $subnet->gateway, 'mask' => $this->subnet->CIDRToMask($cidr));  
                }
            } elseif(count($subnets) > 1) {
                $error_subnets = array();
                while($subnet = $subnets->fetch()) {
                    $error_subnets[] = $subnet->subnet;
                }
                $error_text = "Chyba! Pro tuto IP existuje více subnetů (".implode(", ", $error_subnets).")";
                $out = array('error' => $error_text);
            } else {    
                $out = array('error' => "Podsíť a brána pro tuto IP není v databázi!");
            }
        }
        
        // featura pro javascript
        if($reqid = $this->getParameter("reqid")) {
            $out["reqid"] = $reqid;
        }
        
        $this->sendResponse(new JsonResponse($out));	
	}

}
