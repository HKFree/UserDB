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
    protected $tableName = 'ipAdresa';

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
	    $tr->create('td')->setText($ip->ipAdresa);
	    $tr->create('td')->setText($ip->hostname);
	    $tr->create('td')->setText($ip->macAdresa);
	    $tr->create('td')->setText((isset($ip->typZarizeni->text))?$ip->typZarizeni->text:"");
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