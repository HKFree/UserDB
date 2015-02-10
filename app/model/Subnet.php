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
    
    public function getOverlapingSubnet($s) {
        list($network, $cidr) = explode("/", $s);
        $bigCidr = floor($cidr / 8);
        
        $bigNetwork = long2ip(ip2long($network) & ip2long($this->CIDRToMask($bigCidr*8)));
        
        $bigNetworkExploded = explode(".", $bigNetwork);
        for($i=0; $i<=3; $i++) {
            if($i >= $bigCidr) {
                $bigNetworkExploded[$i] = "%";
            }
        }
        
        $bigNetworkLike = implode(".", $bigNetworkExploded)."/%";
        
        $posiblyColiding = $this->findAll()->where("subnet LIKE ?", $bigNetworkLike);
        $out = array();
        foreach($posiblyColiding as $colSubnet) {
            //$out[] = $colSubnet->subnet;
            $this->checkColision($colSubnet->subnet, $s);
        }
        
        return($out);
    }
    
    private function checkColision($s1, $s2) {
        list($n1, $c1) = explode("/", $s1);
        list($n2, $c2) = explode("/", $s2);
    }
    
    public function validateSubnet($s) {
        list($network, $cidr) = explode("/", $s);
        
        // TODO - validate network part
        if(!is_numeric($cidr) || $cidr < 0 || $cidr > 32) {
            return(false);
        }
        
        $lnet = ip2long($network);
        $lmask = pow(2, 32 - $cidr);
        return(($lnet % $lmask) == 0);
    }
    
    public function CIDRToMask($cidr) {
        return(long2ip(pow(2, 32) - pow(2, 32-$cidr)));
    }
    
}