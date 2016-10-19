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
    
    public function getIPForm(&$ip, $typyZarizeni, $apMode=false) {
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
		if ($apMode) {
			$ip->addCheckbox('wewimo', 'Wewimo')->setAttribute('class', 'wewimo ip');
		}
		$ip->addText('popis', 'Popis')->setAttribute('class', 'popis ip')->setAttribute('placeholder', 'Popis');
    }

	/**
	 * @param $ips zaznamy z tabulky IpAdresa
	 * @param $canViewCredentialsOrEdit zda vygenerovat tabulku, kde budou zobrazena hesla a odkazy primo
	 * 							na editaci dane IP (at uz do AP nebo Uzivatele, podle toho, kde je pouzita)
	 * @param $subnetLinks asoc. pole ip -> odkaz do subnet zobrazeni (do tabulky s IP adresami v danem C subnetu)
	 * @param null $editLink link na editaci AP nebo Uzivatele, pokud zobrazujeme tabulku v detailu APcka nebo Uzivatele, jinak null
	 * @return mixed
	 */
    public function getIPTable($ips, $canViewCredentialsOrEdit, $subnetLinks, $editLink=null, $igwCheck=false)
	{
		$adresyTab = Html::el('table')->setClass('table table-striped');

		$this->addIPTableHeader($adresyTab, $canViewCredentialsOrEdit);

		foreach ($ips as $ip)
		{
			$subnetLink = $subnetLinks[$ip->ip_adresa];
			$this->addIPTableRow($ip, $canViewCredentialsOrEdit, $adresyTab, null, $subnetLink, $editLink, $igwCheck);
		}

		return $adresyTab;
	}

	public function addIPTableHeader($adresyTab, $canViewCredentialsOrEdit=false, $subnetMode=false)
	{
		$tr = $adresyTab->create('tr');

		$tr->create('th')->setText('IP');
        if (!$subnetMode)
		{
            $tr->create('th')->setText('IGW');
        }
		$tr->create('th')->setText(''); // action buttons
		if ($subnetMode) {
			$tr->create('th')->setText('UID');
			$tr->create('th')->setText('Nick');
		}
		$tr->create('th')->setText('Hostname');
		$tr->create('th')->setText('MAC Adresa');
		$tr->create('th')->setText('Zařízení');
		$tr->create('th')->setText('Atributy');
		$tr->create('th')->setText('Popis');
		if ($canViewCredentialsOrEdit) {
			$tr->create('th')->setText('Login');
			$tr->create('th')->setText('Heslo');
		}
		if ($subnetMode)
		{
			$tr->create('th')->setText('Subnet');
		}
	}

	/**
	 * @param Nette\Utils\Html $tr
	 * @param  Nette\Utils\Html[] $buttons
	 */
	private function addActionButtonsTd($tr, $buttons) {
		$td = $tr->create('td');
		foreach ($buttons as $b) {
			$td->add($b);
		}
	}
    
    public function file_get_contents_curl($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);       

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

	public function addIPTableRow($ip, $canViewCredentialsOrEdit, $adresyTab, $subnetModeInfo=null, $subnetLink=null, $editLink=null, $igwCheck=false)
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
        
        if ($igwCheck && $ip)
		{
            $igw1resp = $this->file_get_contents_curl('http://10.107.0.1:8080/ip4info/'.$ip->ip_adresa);
            $igw2resp = $this->file_get_contents_curl('http://10.107.0.2:8080/ip4info/'.$ip->ip_adresa);
            if (strpos($igw1resp, $ip->ip_adresa.' 1') !== false || strpos($igw2resp, $ip->ip_adresa.' 1') !== false) {
                $attr = $tr->create('td');
    			$attr->create('span')
                    ->setClass('glyphicon glyphicon-ok')
                    ->setTitle('IP je povolená do internetu na IGW')
                    ->addAttributes($tooltips);
            }
            else
            {
                $attr = $tr->create('td');
    			$attr->create('span')
                    ->setClass('glyphicon glyphicon-remove')
                    ->setTitle('IP není povolená do internetu na IGW')
                    ->addAttributes($tooltips);
            }
        }

		if ($ip)
		{
			// action buttons
			$buttons = array();
			// web button
			if (isset($ip->TypZarizeni->text) && $ip->TypZarizeni->id != 1) {
				$webButton = Html::el('a')
					->setHref('http://' . $ip->ip_adresa)
					->setTarget('_blank')
					->setClass('btn btn-default btn-xs btn-in-table')
					->setTitle('Otevřít web')
					->addAttributes($tooltips)
					->add(Html::el('span')
						->setClass('glyphicon glyphicon-globe')); // web button
				if (($canViewCredentialsOrEdit || ($subnetModeInfo && $subnetModeInfo['canViewOrEdit']))
						&& isset($ip->TypZarizeni->text) && isset($ip->heslo) && preg_match('/routerboard/i', $ip->TypZarizeni->text)) {
					// routerboard, smim videt heslo a heslo je vyplneno
					// -> otevrit primo zalogovany webfig
					$webButton->setOnclick('return openMikrotikWebfig('.json_encode($ip->ip_adresa).','.json_encode($ip->login).','.json_encode($ip->heslo).')');
					$webButton->setTitle('Otevřít Mikrotik WebFig');
				}
				$buttons[]= $webButton;
			}
			// winbox button
			if (isset($ip->TypZarizeni->text) && preg_match('/routerboard/i', $ip->TypZarizeni->text)) {
				$link = 'winbox:'.$ip->ip_adresa;
				if ($canViewCredentialsOrEdit) {
					$link .= ';'.$ip->login.';'.$ip->heslo;
				}
				$winboxButton = Html::el('a')
					->setHref($link)
					->setTarget('_blank')
					->setTitle('Otevřít Mikrotik Winbox')
					->addAttributes($tooltips)
					->setClass('btn btn-default btn-xs btn-in-table')
					->add(Html::el('span')
						->setClass('glyphicon glyphicon-cog')); // winbox button
				$buttons[]= $winboxButton;
				$buttons[] = Html::el('a')
								->add(Html::el('sup')->setText('?'))
								->setTarget('_blank')
								->setTitle('Jak zprovoznit otevření Winboxu z prohlížeče?')
								->addAttributes($tooltips)
								->setHref('http://wiki.hkfree.org/Winbox_URI');
			}
			// edit button, etc.
			if ($subnetModeInfo) {
				if ($subnetModeInfo['type'] == 'Uzivatel')
				{
					if ($subnetModeInfo['canViewOrEdit']) {
						array_unshift($buttons,
							Html::el('a')
								->setHref($subnetModeInfo['editLink'])
								->setClass('btn btn-default btn-xs btn-in-table')
								->setTitle('Editovat')
								->addAttributes($tooltips)
								->add(Html::el('span')
									->setClass('glyphicon glyphicon-pencil'))
						); // edit button
					} // jinak nema edit button
					$this->addActionButtonsTd($tr, $buttons);
					$tr->create('td')->create('a')->setHref($subnetModeInfo['link'])->setText($subnetModeInfo['id']); // UID
					$tr->create('td')->setText($subnetModeInfo['nick']); // nick
				}
				elseif ($subnetModeInfo['type'] == 'Ap')
				{
					if ($subnetModeInfo['canViewOrEdit']) {
						array_unshift($buttons,
							Html::el('a')
							->setHref($subnetModeInfo['editLink'])
							->setClass('btn btn-default btn-xs btn-in-table')
							->setTitle('Editovat')
							->addAttributes($tooltips)
							->add(Html::el('span')
								->setClass('glyphicon glyphicon-pencil'))
						); // edit button
					} // jinak nema edit button
					$this->addActionButtonsTd($tr, $buttons);
					// nazev AP + cislo (pres 2 bunky, aby se to nepletlo s UID+nick)
					$tr->create('td')->setColspan(2)->create('a')->setHref($subnetModeInfo['link'])->setText($subnetModeInfo['jmeno'] . ' (' . $subnetModeInfo['id'] . ')');
				}
			} else {
				if ($canViewCredentialsOrEdit && $editLink) {
					array_unshift($buttons,
						Html::el('a')
						->setHref($editLink . '#ip' . $ip->ip_adresa)
						->setClass('btn btn-default btn-xs btn-in-table')
						->setTitle('Editovat')
						->addAttributes($tooltips)
						->add(Html::el('span')
							->setClass('glyphicon glyphicon-pencil'))
					); // edit button
				}
				$this->addActionButtonsTd($tr, $buttons);
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
						->setClass('glyphicon glyphicon-filter')
						->setTitle('IP exportuje do MAC filteru')
						->addAttributes($tooltips);
					$attr->add(' ');
				}

				if ($ip->wewimo) {
					$attr->create('span')
						->setClass('glyphicon glyphicon-signal')
						->setTitle('Zobrazovat signály klientů ve Wewimo')
						->addAttributes($tooltips);
					$attr->add(' ');
				}

				$tr->create('td')->setText($ip->popis); // popis
				if ($canViewCredentialsOrEdit) {
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
				if ($canViewCredentialsOrEdit) {
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
			if ($canViewCredentialsOrEdit) {
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

	/**
	 * @param string[] $macs
	 * @return object[] asic. pole mac => zaznam pro danou mac adresu
	 */
	public function getIpsByMacsMap(array $macs) {
		return $this->getTable()->where('mac_adresa', $macs)->fetchPairs('mac_adresa');
	}

	public function getIpsMap(array $ips) {
		return $this->getTable()->where('ip_adresa', $ips)->fetchPairs('ip_adresa');
	}

}