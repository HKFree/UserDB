<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author 
 */
class Subnet extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Subnet';

    public function getSeznamSubnetu()
    {
	//$row = $this->findAll();
        return($this->findAll());
    }

    public function getSubnet($id)
    {
        return($this->find($id));
    }
    public function deleteSubnet(array $subnets)
    {
		if(count($subnets)>0)
			return($this->delete(array('id' => $subnets)));
		else
			return true;
    }

    public function getSubnetForm(&$subnet) {	
		$subnet->addHidden('id')->setAttribute('class', 'id subnet');
		$subnet->addText('subnet', 'Subnet', 11)->setAttribute('class', 'subnet_text subnet')->setAttribute('placeholder', 'Subnet');
		$subnet->addText('gateway', 'Gateway', 11)->setAttribute('class', 'gateway_text subnet')->setAttribute('placeholder', 'Gateway');
		$subnet->addText('popis', 'Popis')->setAttribute('class', 'popis subnet')->setAttribute('placeholder', 'Popis');
    }    
    
    public function getSubnetTable($subnets) {
        $subnetyTab = Html::el('table')->setClass('table table-striped');
        $tr = $subnetyTab->create('tr');
        $tr->create('th')->setText('Subnet');
        $tr->create('th')->setText('Gateway');
        $tr->create('th')->setText('Popis');

        foreach ($subnets as $subnet) {
            $tr = $subnetyTab->create('tr');
            $tr->create('td')->setText($subnet->subnet);
            $tr->create('td')->setText($subnet->gateway);
            $tr->create('td')->setText($subnet->popis);
        } 
        return($subnetyTab);
    }
    
    public function getSubnetOfIP($ip) {
        $subnets = $this->genSubnets($ip);
        return($this->findAll()->where("subnet", $subnets));
    }
    
    private function genSubnets($ip) {
        $lip = ip2long($ip);
        $subnets = array();
        for($mask = 32; $mask >= 16; $mask--) {
            $lmask = pow(2, 32-$mask);
            $subnet = long2ip(floor($lip/$lmask)*$lmask);
            $subnets[] = $subnet."/".$mask;
        }
        return($subnets);
    }
    
    public function CIDRToMask($cidr) {
        return(long2ip(pow(2, 32) - pow(2, 32-$cidr)));
    }
    
}