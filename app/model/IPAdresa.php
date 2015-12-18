<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author 
 */
class IPAdresa extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'IPAdresa';
    
    public function getSeznamIPAdres()
    {
	//$row = $this->findAll();
        return($this->findAll());
    }
    
    public function getSeznamIPAdresZacinajicich($prefix)
    {
        return $this->findAll()->where("ip_adresa LIKE ?", $prefix.'%')->fetchPairs('ip_adresa');
    }

    public function getIPAdresa($id)
    {
        return($this->find($id));
    }
    
    public function getDuplicateIP($ip, $id)
    {
        $existujici = $this->findAll()->where('ip_adresa = ?', $ip)->where('id != ?', $id)->fetch();
        if($existujici)
        {
            return $existujici->ip_adresa;//$existujici->ref('Uzivatel', 'Uzivatel_id')->id;
        }
        return null;
    }
    
    
    /**
     * Párová metoda k \App\Model\Log::getAdvancedzLogu(), Vrati seznam idIp -> ipAdresa
     * 
     * @param array $ids ipId pro které chceme zjistit ipAdresy
     * @return array pole ipId=>ipAdresa
     */
    public function getIPzDB(array $ids)
    {
        return($this->getTable()->where("id", $ids)->fetchPairs("id", "ip_adresa"));
    }

    public function deleteIPAdresy(array $ips)
    {
		if(count($ips)>0)
			return($this->delete(array('id' => $ips)));
		else
			return true;
    }
    
    public function validateIP($ip) {
        return(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }
    
    public function getIPForm(&$ip, $typyZarizeni) {	
		$ip->addHidden('id')->setAttribute('class', 'id ip');
		$ip->addText('ip_adresa', 'IP Adresa', 11)->setAttribute('class', 'ip_adresa ip')->setAttribute('placeholder', 'IP Adresa');
		$ip->addText('hostname', 'Hostname', 11)->setAttribute('class', 'hostname ip')->setAttribute('placeholder', 'Hostname');
		$ip->addSelect('TypZarizeni_id', 'Typ Zařízení', $typyZarizeni)->setAttribute('class', 'TypZarizeni_id ip')->setPrompt('--Vyberte--');;
		$ip->addCheckbox('internet', 'Internet')->setAttribute('class', 'internet ip')->setDefaultValue(1);
		$ip->addCheckbox('smokeping', 'Smokeping')->setAttribute('class', 'smokeping ip');
		$ip->addText('login', 'Login', 11)->setAttribute('class', 'login ip')->setAttribute('placeholder', 'Login');
		$ip->addText('heslo', 'Heslo', 11)->setAttribute('class', 'heslo ip')->setAttribute('placeholder', 'Heslo');
		$ip->addText('mac_adresa', 'MAC Adresa', 24)->setAttribute('class', 'mac_adresa ip')->setAttribute('placeholder', 'MAC Adresa');
		$ip->addCheckbox('mac_filter', 'MAC Filtr')->setAttribute('class', 'mac_filter ip');
		$ip->addCheckbox('dhcp', 'DHCP')->setAttribute('class', 'dhcp ip');
		$ip->addText('popis', 'Popis')->setAttribute('class', 'popis ip')->setAttribute('placeholder', 'Popis');
    }

    public function getIPTable($ips, $canViewCredentials, $subnetLinks)
	{
		$adresyTab = Html::el('table')->setClass('table table-striped');

		$this->addIPTableHeader($adresyTab, $canViewCredentials);

		foreach ($ips as $ip)
		{
			$subnetLink = $subnetLinks[$ip->ip_adresa];
			$this->addIPTableRow($ip, $canViewCredentials, $adresyTab, null, $subnetLink);
		}

		return $adresyTab;
	}

	public function addIPTableHeader($adresyTab, $canViewCredentials, $subnetMode=false)
	{
		$tr = $adresyTab->create('tr');

		$tr->create('th')->setText('IP');
		if ($subnetMode) {
			$tr->create('th')->setText(''); // edit button
			$tr->create('th')->setText('UID');
			$tr->create('th')->setText('Nick');
		}
		$tr->create('th')->setText('Hostname');
		$tr->create('th')->setText('MAC Adresa');
		$tr->create('th')->setText('Zařízení');
		$tr->create('th')->setText('Atributy');
		$tr->create('th')->setText('Popis');
		if ($canViewCredentials) {
			$tr->create('th')->setText('Login');
			$tr->create('th')->setText('Heslo');
		}
		if ($subnetMode)
		{
			$tr->create('th')->setText('Subnet');
		}
	}

	public function addIPTableRow($ip, $canViewCredentials, $adresyTab, $subnetModeInfo=null, $subnetLink=null)
	{
		$tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

		$ipTitle = $subnetModeInfo ? $subnetModeInfo['ipTitle'] : $ip->ip_adresa;

		$tr = $adresyTab->create('tr');
		$tr->setId('highlightable-ip'.($ip ? $ip->ip_adresa : $subnetModeInfo['ipAdresa']));
		if ($subnetModeInfo && array_key_exists('rowClass', $subnetModeInfo)) $tr->setClass($subnetModeInfo['rowClass']);

		if ($subnetLink) {
			// IP jako link do subnets z beznych agend (uzivatel, AP)
			$tr->create('td')->create('a')->setHref($subnetLink)->setTarget('_blank')->setText($ipTitle);
		} else {
			// zobrazit IP jen jako text
			$tr->create('td')->setText($ipTitle);
		}

		if ($ip)
		{
			if ($subnetModeInfo) {
				if ($subnetModeInfo['type'] == 'Uzivatel')
				{
					if ($subnetModeInfo['canViewOrEdit']) {
						$tr->create('td')
							->create('a')
								->setHref($subnetModeInfo['editLink'])
								->setClass('btn btn-default btn-xs btn-in-table')
								->setTitle('Editovat')
								->create('span')
									->setClass('glyphicon glyphicon-pencil'); // edit button
					} else {
						$tr->create('td'); //  nema edit button
					}
					$tr->create('td')->create('a')->setHref($subnetModeInfo['link'])->setText($subnetModeInfo['id']); // UID
					$tr->create('td')->setText($subnetModeInfo['nick']); // nick
				}
				elseif ($subnetModeInfo['type'] == 'Ap')
				{
					if ($subnetModeInfo['canViewOrEdit']) {
						$tr->create('td')
							->create('a')
								->setHref($subnetModeInfo['editLink'])
							->setHref($subnetModeInfo['editLink'])
							->setClass('btn btn-default btn-xs btn-in-table')
							->setTitle('Editovat')
							->create('span')
							->setClass('glyphicon glyphicon-pencil'); // edit button
					} else {
						$tr->create('td'); //  nema edit button
					}
					// nazev AP + cislo (pres 2 bunky, aby se to nepletlo s UID+nick)
					$tr->create('td')->setColspan(2)->create('a')->setHref($subnetModeInfo['link'])->setText($subnetModeInfo['jmeno'] . ' (' . $subnetModeInfo['id'] . ')');
				}
			}
			if (!$subnetModeInfo || $subnetModeInfo['canViewOrEdit'])
			{
				$tr->create('td')->setText($ip->hostname); // hostname
				$tr->create('td')->setText($ip->mac_adresa); // MAC
				$tr->create('td')->setText((isset($ip->TypZarizeni->text)) ? $ip->TypZarizeni->text : ""); // typ zarizeni
				$attr = $tr->create('td'); // atributy
				if ($ip->internet) {
					$attr->create('span')
						->setClass('glyphicon glyphicon-transfer')
						->setTitle('IP je povolená do internetu')
						->addAttributes($tooltips);
					$attr->add(' ');
				}

				if ($ip->smokeping) {
					$attr->create('span')
						->setClass('glyphicon glyphicon-eye-open')
						->setTitle('IP je sledovaná ve smokepingu')
						->addAttributes($tooltips);
					$attr->add(' ');
				}

				if ($ip->dhcp) {
					$attr->create('span')
						->setClass('glyphicon glyphicon-open')
						->setTitle('IP se exportuje do DHCP')
						->addAttributes($tooltips);
					$attr->add(' ');
				}

				if ($ip->mac_filter) {
					$attr->create('span')
						->setClass('glyphicon glyphicon glyphicon-filter')
						->setTitle('IP exportuje do MAC filteru')
						->addAttributes($tooltips);
					$attr->add(' ');
				}

				$tr->create('td')->setText($ip->popis); // popis
				if ($canViewCredentials) {
					$tr->create('td')->setText($ip->login);
					$tr->create('td')->setText($ip->heslo);
				}
			} else {
				// subnet mode, nesmi videt detaily
				$tr->create('td')
					->create('span')
					->setText('---')
					->setTitle('Nemáte právo vidět detaily') // hostname
					->addAttributes($tooltips);
				$tr->create('td'); // MAC
				$tr->create('td'); // typ zarizeni
				$tr->create('td'); // atributy
				$tr->create('td'); // popis
				if ($canViewCredentials) {
					$tr->create('td'); // login
					$tr->create('td'); // heslo
				}
			}
		} else if ($subnetModeInfo) {
			// empty row
			$tr->create('td');
			$tr->create('td');
			$tr->create('td');
			$tr->create('td');
			$tr->create('td');
			$tr->create('td');
			$tr->create('td');
			$tr->create('td');
			if ($canViewCredentials) {
				$tr->create('td')->setText(''); // login
				$tr->create('td')->setText(''); // heslo
			}
		}

		return $tr;
    }
    
    /**
     * 
     * @param string $ipsubnet ip address subnet
     * @return array of ips
     */
    public function getListOfIPFromSubnet($ipsubnet)
    {
        $genaddresses = array();
        if(isset($ipsubnet) && !empty($ipsubnet))
        {  
            if (preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))$/i", $ipsubnet)) {
                @list($sip, $slen) = explode('/', $ipsubnet);
                if (($smin = ip2long($sip)) !== false) {
                  $smax = ($smin | (1<<(32-$slen))-1);
                  for ($i = $smin; $i < $smax; $i++)
                    $genaddresses[] = long2ip($i);
                }                 
            }
        }
        return $genaddresses;
    }
    
    /**
     * 
     * @param string $iprange ip address range
     * @return array of ips
     */
    public function getListOfIPFromRange($iprange)
    {
        $genaddresses = array();
        if(isset($iprange) && !empty($iprange))
        {
            if (preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])-(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $iprange)) {
                $temp = preg_split("/-/",$iprange, -1, PREG_SPLIT_NO_EMPTY); 
                $QRange1 = $temp[0]; 
                $QRange2 = $temp[1];
                $start = ip2long($QRange1);
                $end = ip2long($QRange2);
                $range = range($start, $end);
                $genaddresses = array_map('long2ip', $range);          
            }
        }
        return $genaddresses;
    }
}