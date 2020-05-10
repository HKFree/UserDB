<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author
 */
class Subnet6 extends Table
{
    const ERR_NOT_FOUND = 1;

    /**
    * @var string
    */
    protected $tableName = 'Subnet6';

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
		$subnet->addText('subnet', 'Subnet', 11)->setAttribute('class', 'subnet_text subnet')->setAttribute('placeholder', '2001:db8::');
		$subnet->addText('popis', 'Popis')->setAttribute('class', 'popis subnet')->setAttribute('placeholder', 'Popis');
    }

    public function getSubnetTable($subnets) {
        $tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

        $subnetyTab = Html::el('table')->setClass('table table-striped');
        $tr = $subnetyTab->create('tr');
        $tr->create('th')->setText('Subnet');
        $tr->create('th')->setText('Popis');

        foreach ($subnets as $subnet) {
            $tr = $subnetyTab->create('tr');
            $tr->create('td')->setText($subnet->subnet);
            $tr->create('td')->setText($subnet->popis);
        }
        return($subnetyTab);
    }

    /**
     * Validuje IPv6 subnet (a:b:c:d:e::/y)
     *
     * Dává pozor, jestli jde opravdu o subnet nebo IP s maskou
     *
     * @param string $subnet Validovaný subnet
     * @return boolean Výsledek validace
     */
    public function validateSubnet6Syntax($subnet) {
        $disected_subnet = explode("/", $subnet);

        // Zkontrolujeme jestli máme přesně jedno lomítko
        if(count($disected_subnet) != 2) {
            return(false);
        }

        list($network, $cidr) = $disected_subnet;

        // Zkontroluje validitu adresy sítě
        if(!filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return(false);
        }

        // Zkontroluje CIDR (0-128)
        if(!is_numeric($cidr) || $cidr < 0 || $cidr > 128) {
            return(false);
        }

        // Zkontrolujeme že to je opravdu "jen" subnet, tj. od CIDR-tého bitu samé nuly
        $network_bin = $this->ipv6addr_to_bits($network);
        $network_bin_zbytek = substr($network_bin, $cidr);
        return preg_match('/^0+$/', $network_bin_zbytek) ? true : false;
    }

    public function validateIPv6Whitelist($ip, $whitelistRanges)
    {
        if (!$whitelistRanges) return true;
        if (is_array($whitelistRanges) && sizeof($whitelistRanges) == 0) return true;
        foreach ($whitelistRanges as $range) {
            if ($this->ipv6_in_range($ip, $range)) return true;
        }
        return false;
    }

    private function ipv6_in_range($ip, $range) {
        list($range_base, $cidr) = explode('/', $range);
        $binary_ip = $this->ipv6addr_to_bits($ip);
        $binary_base = $this->ipv6addr_to_bits($range_base);

        return (substr($binary_ip, 0, $cidr) == substr($binary_base, 0, $cidr));
    }

    // converts IPv6 address to a 128-byte long binary representation string
    // in: "2000:db8:123::9
    // out: "00100000000000000000110110111000000000010010001100000000000000000000000000000000000000000000000000000000000000000000000000001001"
    private function ipv6addr_to_bits($inet)
    {
        $binaryip = '';
        foreach (str_split(inet_pton($inet)) as $char) {
            $binaryip .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        return $binaryip;
    }
}
