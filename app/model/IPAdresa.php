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

    public function deleteIPAdresy(array $ips)
    {
	if(count($ips)>0)
	    return($this->delete(array('id' => $ips)));
	else
	    return true;
    }
    
    public function getIPForm(&$ip, $typyZarizeni) {	
	$ip->addHidden('id')->setAttribute('class', 'ip');
	$ip->addText('ip_adresa', 'IP Adresa',10)->setAttribute('class', 'ip')->setAttribute('placeholder', 'IP Adresa');
	$ip->addText('hostname', 'Hostname',9)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Hostname');
	$ip->addText('mac_adresa', 'MAC Adresa',18)->setAttribute('class', 'ip')->setAttribute('placeholder', 'MAC Adresa');
	$ip->addSelect('TypZarizeni_id', 'Typ Zařízení', $typyZarizeni)->setAttribute('class', 'ip');
	$ip->addCheckbox('internet', 'Internet')->setAttribute('class', 'ip');
	$ip->addCheckbox('smokeping', 'Smokeping')->setAttribute('class', 'ip');
	$ip->addText('login', 'Login',8)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Login');
	$ip->addText('heslo', 'Heslo',8)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Heslo');
	$ip->addText('popis', 'Popis', 30)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Popis');
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
	    if($ip->internet)
		$attr->create('span')->setClass('glyphicon glyphicon-transfer')->setTitle('IP je povolená do internetu')
		->addAttributes($tooltips);
	    $attr->add(' ');
	    if($ip->smokeping)
		$attr->create('span')->setClass('glyphicon glyphicon-eye-open')->setTitle('IP je sledovaná ve smokepingu');
	    $tr->create('td')->setText($ip->popis);
	    $tr->create('td')->setText($ip->login);
	    $tr->create('td')->setText($ip->heslo);
	} 
	$adresyTab->create('script')->setHTML('$(\'span\').tooltip();');
	return($adresyTab);
    }
}