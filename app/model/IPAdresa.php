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

    public function getIPAdresa($id)
    {
        return($this->find($id));
    }
    
    
    /**
     * Párová metoda k \App\Model\Log::getIPzLogu(), Vrati seznam idIp -> ipAdresa
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
    
    public function getIPForm(&$ip, $typyZarizeni) {	
		$ip->addHidden('id')->setAttribute('class', 'id ip');
		$ip->addText('ip_adresa', 'IP Adresa', 11)->setAttribute('class', 'ip_adresa ip')->setAttribute('placeholder', 'IP Adresa');
		$ip->addText('hostname', 'Hostname', 11)->setAttribute('class', 'hostname ip')->setAttribute('placeholder', 'Hostname');
		$ip->addSelect('TypZarizeni_id', 'Typ Zařízení', $typyZarizeni)->setAttribute('class', 'TypZarizeni_id ip')->setPrompt('--Vyberte--');;
		$ip->addCheckbox('internet', 'Internet')->setAttribute('class', 'internet ip');
		$ip->addCheckbox('smokeping', 'Smokeping')->setAttribute('class', 'smokeping ip');
		$ip->addText('login', 'Login', 11)->setAttribute('class', 'login ip')->setAttribute('placeholder', 'Login');
		$ip->addText('heslo', 'Heslo', 11)->setAttribute('class', 'heslo ip')->setAttribute('placeholder', 'Heslo');
		$ip->addText('mac_adresa', 'MAC Adresa', 24)->setAttribute('class', 'mac_adresa ip')->setAttribute('placeholder', 'MAC Adresa');
		$ip->addCheckbox('mac_filter', 'MAC Filtr')->setAttribute('class', 'mac_filter ip');
		$ip->addCheckbox('dhcp', 'DHCP')->setAttribute('class', 'dhcp ip');
		$ip->addText('popis', 'Popis')->setAttribute('class', 'popis ip')->setAttribute('placeholder', 'Popis');
    }
    
    public function getIPTable($ips) {
		$tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

		$adresyTab = Html::el('table')->setClass('table table-striped');
		$tr = $adresyTab->create('tr');
		$tr->create('th')->setText('IP');
		$tr->create('th')->setText('Hostname');
		$tr->create('th')->setText('MAC Adresa');
		$tr->create('th')->setText('Zařízení');
		$tr->create('th')->setText('Atributy');
		$tr->create('th')->setText('Popis');
		$tr->create('th')->setText('Login');
		$tr->create('th')->setText('Heslo');

		foreach ($ips as $ip) {
			$tr = $adresyTab->create('tr');
			$tr->create('td')->setText($ip->ip_adresa);
			$tr->create('td')->setText($ip->hostname);
			$tr->create('td')->setText($ip->mac_adresa);
			$tr->create('td')->setText((isset($ip->TypZarizeni->text))?$ip->TypZarizeni->text:"");
			$attr = $tr->create('td');
			if($ip->internet) {
				$attr->create('span')
					 ->setClass('glyphicon glyphicon-transfer')
					 ->setTitle('IP je povolená do internetu')
					 ->addAttributes($tooltips);	
				$attr->add(' ');
			}

			if($ip->smokeping) {
				$attr->create('span')
					 ->setClass('glyphicon glyphicon-eye-open')
					 ->setTitle('IP je sledovaná ve smokepingu')
					 ->addAttributes($tooltips);
				$attr->add(' ');
			}

			if($ip->dhcp) {
				$attr->create('span')
					 ->setClass('glyphicon glyphicon-open')
					 ->setTitle('IP se exportuje do DHCP')
					 ->addAttributes($tooltips);	
				$attr->add(' ');
			}

			if($ip->mac_filter) {
				$attr->create('span')
					 ->setClass('glyphicon glyphicon glyphicon-filter')
					 ->setTitle('IP exportuje do MAC filteru')
					 ->addAttributes($tooltips);	
				$attr->add(' ');
			}

			$tr->create('td')->setText($ip->popis);
			$tr->create('td')->setText($ip->login);
			$tr->create('td')->setText($ip->heslo);
		} 
		$adresyTab->create('script')->setHTML('$(\'span\').tooltip();');
		return($adresyTab);
    }
}