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
	 * @param  Exception
	 * @return void
	 */
	public function renderGetIpDetails()
	{        
        $ip = $this->getParameter("ip");
        $ap = $this->getParameter("ap");
        $subnets = $this->subnet->getSubnetOfIP($ip, $ap);
        if(count($subnets) == 1) {
            $subnet = $subnets->fetch();
            $out = array('subnet' => $subnet->subnet, 'gateway' => $subnet->gateway);
        } else {
            $out = array('subnet' => null, 'gateway' => null);
        }
        $this->sendResponse(new JsonResponse($out));	
	}

}
