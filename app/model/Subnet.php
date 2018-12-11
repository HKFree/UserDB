<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author 
 */
class Subnet extends Table
{
    const ERR_NOT_FOUND = 1;
    const ERR_NO_GW = 2;
    const ERR_MULTIPLE_GW = 3;
    
    const ARP_PROXY_SUBNET = "89.248.240.0/20";
    const ARP_PROXY_GW = "89.248.255.254";
    
    const ARP_PROXY_DESCRIPTION = "Při zapnuté ARP Proxy se veřejné IP adrese z daného subnetu automaticky nastaví subnet 89.248.240/20 s bránou 89.248.255.254";

    
    /**
    * @var string
    */
    protected $tableName = 'Subnet';

    public function getSeznamSubnetu()
    {
        return($this->findAll());
    }
    
    public function getSeznamSubnetuZacinajicich($prefix)
    {
        return $this->findAll()->where("subnet LIKE ?", $prefix.'%')->fetchAll();
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
        $subnet->addCheckbox('arp_proxy', 'ARP Proxy')->setAttribute('class', 'arp_proxy_check subnet');
    }    
    
    /**
     * Párová metoda k \App\Model\Log::getAdvancedzLogu(), Vrati seznam idSubnet -> subnet
     * 
     * @param int[] $ids idSubnet pro které chceme zjistit subnet
     * @return array pole idSubnet=>subnet
     */
    public function getSubnetzDB(array $ids)
    {
        return($this->getTable()->where("id", $ids)->fetchPairs("id", "subnet"));
    }
    
    /**
     * Funkce která ze seznamu subnetů vrátí jejich C verze
     * 
     * Vrací pole (x.y.z, x.y.z, x.y.z)
     * 
     * Př: vstup 10.107.17.0/26 , 10.107.17.64/26
     *     výstup 10.107.17
     * 
     * @param string $subnet Vstupní subnet
     * @return string[] Pole s položkami
     */
    public function getAPCSubnets($subnets) {
        foreach ($subnets as $subnet) {
            $out = $this->parseSubnet($subnet->subnet);
            //\Tracy\Debugger::barDump(explode(".", $out["network"]));
            list($a, $b, $c, $d) = explode(".", $out["network"]);
            $resultnet = $a .".". $b .".". $c .".";
            $results[] = $resultnet;
        } 
        if(isset($results))
        {
            return(array_unique($results));
        }
        else
        {
            return array();
        }
    }
    
    public function getSubnetTable($subnets) {
        $tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');
        
        $subnetyTab = Html::el('table')->setClass('table table-striped');
        $tr = $subnetyTab->create('tr');
        $tr->create('th')->setText('Subnet');
        $tr->create('th')->setText('Gateway');
        $tr->create('th')->setText('ARP Proxy');
        $tr->create('th')->setText('Popis');

        foreach ($subnets as $subnet) {
            $tr = $subnetyTab->create('tr');
            $tr->create('td')->setText($subnet->subnet);
            $tr->create('td')->setText($subnet->gateway);
            $arpProxy = $tr->create('td')->create('span');
            $arpProxy->setTitle(Subnet::ARP_PROXY_DESCRIPTION)
                     ->addAttributes($tooltips);
            if($subnet->arp_proxy) {
                $arpProxy->setClass('label label-success');
                $arpProxy->setText("zapnuto");
            } else {
                $arpProxy->setClass('label label-default');
                $arpProxy->setText("vypnuto");
            }
            $tr->create('td')->setText($subnet->popis);
        } 
        return($subnetyTab);
    }
    
    /**
     * Najde subnet k zadané IP adrese
     * 
     * Malinko uprasené, prostě vygeneruju všechny možné subnety
     * a udělám WHERE subnet IN ...
     * 
     * @param string $ip IP adresa
     * @return string[] Subnet a informace o něm
     */
    public function getSubnetOfIP($ip) {
        $possibleSubnets = $this->genSubnets($ip);
        $subnets = $this->findAll()->where("subnet", $possibleSubnets);
                
        if(count($subnets) > 1) {
            $out["error"] = Subnet::ERR_MULTIPLE_GW;
            $out["multiple_subnets"] = $subnets->fetchPairs("id", "subnet");
            return($out);
        }
        
        if(count($subnets) == 1) {
            $subnet = $subnets->fetch();
            
            if($subnet->arp_proxy) {
                $out = $this->parseSubnet(Subnet::ARP_PROXY_SUBNET);
                $out["gateway"] = Subnet::ARP_PROXY_GW;
            } else {
                $out = $this->parseSubnet($subnet->subnet);

                if(empty($subnet->gateway)) {
                    $out["error"] = Subnet::ERR_NO_GW;
                } else {
                    $out["gateway"] = $subnet->gateway;
                }
            }
            
            return($out);
        }
        
        $out["error"] = Subnet::ERR_NOT_FOUND;
        return($out);
    }
    
    /**
     * Funkce která ze stringu ve tvaru x.y.z.w/c rozparsuje položky
     * 
     * Vrací pole 
     *  - subnet (x.y.z.w/c)
     *  - network (x.y.z.w)
     *  - cidr (c)
     *  - mask (c->mask)
     * 
     * @param string $subnet Vstupní subnet
     * @return string[] Pole s položkami
     */
    public function parseSubnet($subnet) {
        list($network, $cidr) = explode("/", $subnet);
        
        $out["subnet"] = $subnet;
        $out["network"] = $network;
        $out["cidr"] = $cidr;
        $out["mask"] = $this->CIDRToMask($cidr);
        
        return($out);
    }
    
    /**
     * Generuje všechny možné subnety k IP adrese
     * 
     * Tvoří /16 až /32, víc není snad potřeba.
     * Používá se k dotazům do DB v getSubnetOfIP()
     * 
     * @param string $ip IP adresa
     * @return string[] Subnety
     */
    private function genSubnets($ip) {
        $lip = ip2long($ip);
        $subnets = array();
        for($mask = 32; $mask >= 16; $mask--) {
            $lmask = pow(2, 32 - $mask);
            $subnet = long2ip(floor($lip/$lmask)*$lmask);
            $subnets[] = $subnet."/".$mask;
        }
        return($subnets);
    }
    
    /**
     * Najde kolidující/překrývající-se subnety v DB
     * 
     * @param string $s Subnet
     * @param integer $apId ID testovaného subnetu - určené k vyhnutí se
     * @return boolean|string[] false pokud zadaný subnet nekoliduje, 
     *               pole kolidujících subnetů pokud koliduje
     */
    public function getOverlapingSubnet($s, $apId = NULL) {
        $posiblyColiding = $this->getPossiblyColiding($s);
        if (!is_null($apId)) {
            $posiblyColiding = $posiblyColiding->where("Ap_id != ?", $apId);
        }
            
        $coliding = array();
        foreach($posiblyColiding as $colSubnet) {
            if($this->checkColision($colSubnet->subnet, $s)) {
                $coliding[] = $colSubnet->subnet;
            }
        }
        
        if(count($coliding) > 0){
            return($coliding);
        }
        return(false);
    }
    
    /**
     * Vrátí subnety z DB se stejnou částí před CIDR modulo 8
     * 
     * příklad: při zadání 10.107.91.0/25 udělá dotaz
     * WHERE subnet LIKE 10.107.91.%/%
     * 
     * Používá se, protože DB neumí se subnety přímo pracovat.
     * 
     * @param string $s Subnet
     * @return Nette\Database\Table\Selection Možné kolidující subnety
     */
    private function getPossiblyColiding($s) {
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
        
        return($this->findAll()->where("subnet LIKE ?", $bigNetworkLike));
    }
    
    /**
     * Zjistí, zda dva subnety kolidují
     * 
     * @param string $s1 Testovaný subnet 1
     * @param string $s2 Testovaný subnet 2
     * @return boolean false když subnety nekolidují, 
     *                 true  když kolidují.
     */
    public function checkColision($s1, $s2) {
        list($n1, $c1) = explode("/", $s1);
        list($n2, $c2) = explode("/", $s2);
        
        $first_1 = ip2long($n1);
        $last_1 = $first_1 + pow(2, 32 - $c1) - 1;
        
        $first_2 = ip2long($n2);
        $last_2 = $first_2 + pow(2, 32 - $c2) - 1;

        if(($first_1 < $first_2) && ($last_1 < $first_2)) {
            return(false);
        }
                
        if(($first_2 < $first_1) && ($last_2 < $first_1)) {
            return(false);
        }
        
        return(true);
    }
    
    /**
     * Funkce testující, nachází-li se IP v daném subnetu
     * 
     * @param string $ip Testovaná IP
     * @param string $subnet Testovaný subnet
     * @return boolean
     */
    public function inSubnet($ip, $subnet) {
        if($subnet == "0.0.0.0/0") {
            return(true);
        }
        return($this->checkColision($ip."/32", $subnet));
    }
    
    /**
     * Pro zadané pole subnetů zjistí, jestli se nějaké nepřekrývají
     * 
     * @param string[] $subnets Pole subnetů ke kontrole
     * @return boolean|string[] false pokud se žádné dva subnety nepřekrývají
     *                          string[] pokud se překrývají
     */
    public function checkColisions($subnets) {
        $collisions = array();
        foreach ($subnets as $id1 => $subnet1) {
            foreach ($subnets as $id2 => $subnet2) {
                // Projizdime jenom trojuhelnik misto ctverce
                if($id1 <= $id2) {
                    continue(1);
                }
                
                $colides = $this->checkColision($subnet1, $subnet2);
                if($colides) {
                    $collisions[] = $subnet1." - ".$subnet2;
                }
            } 
        }
        
        if(count($collisions) == 0) {
            return(false);
        } else {
            return($collisions);
        }
    }
    
    /**
     * Validuje subnet (x.x.x.x/y)
     * 
     * Dává pozor, jestli jde opravdu o subnet nebo IP s maskou
     * - 10.107.0.0/16 => true
     * - 10.107.0.64/16 => false
     * - 10.107.0.64/26 => true
     * 
     * @param string $subnet Validovaný subnet
     * @return boolean Výsledek validace
     */
    public function validateSubnet($subnet) {
        $disected_subnet = explode("/", $subnet); 
        
        // Zkontrolujeme jestli máme přesně jedno lomítko
        if(count($disected_subnet) != 2) {
            return(false);
        }
        
        list($network, $cidr) = $disected_subnet;
        
        // Zkontroluje validitu adresy sítě
        if(!filter_var($network, FILTER_VALIDATE_IP)) {
            return(false);
        }
        
        // Zkontroluje CIDR (0-32)
        if(!is_numeric($cidr) || $cidr < 0 || $cidr > 32) {
            return(false);
        }
                
        $lnet = ip2long($network);
        
        // Zkontrolujeme síť 0.0.0.0/0 (cokoliv jiného/0 zahodíme)
        // (hlavně protože % nemá rádo 2^32, musíme to vyfiltrovat tady)
        if($cidr == 0){
            return($lnet == 0);
        }            
        
        // Zkontroluje jestli opravdu jde o subnet a ne IP/maska
        $lmask = pow(2, 32 - $cidr);
        return(($lnet % $lmask) == 0);
    }
    
    /**
     * Převede CIDR (číslo za /) na masku
     * 
     * například 24 na 255.255.255.0
     * 
     * @param integer $cidr CIDR
     * @return string Maska
     */
    public function CIDRToMask($cidr) {
        return(long2ip(pow(2, 32) - pow(2, 32-$cidr)));
    }
    
}