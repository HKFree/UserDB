<?php

namespace App\Presenters;

use Nette,
	App\Model,
        Nette\Application\UI\Form,
        Nette\Forms\Container,
        Nette\Utils\Html;
use Nette\Forms\Controls\SubmitButton;
/**
 * Ap presenter.
 */
class ApPresenter extends BasePresenter {       
    private $uzivatel;
    private $ap;
    private $ipAdresa;
    private $subnet;
    private $typZarizeni;

    function __construct(Model\Uzivatel $uzivatel, Model\AP $ap, Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\TypZarizeni $typZarizeni) {
	$this->uzivatel = $uzivatel;       
	$this->ap = $ap;
	$this->ipAdresa = $ipAdresa;
	$this->subnet = $subnet;
	$this->typZarizeni = $typZarizeni;
	//$this->oblast = $oblast;
	
    }

    public function renderList() {
	if($this->getParam('id'))
	{
	    $apcka = $this->ap->findAP(array('Oblast_id' => intval($this->getParam('id'))));
	    if($apcka->count() == 0) {
		$this->template->table = 'Chyba, zadaná oblast neexistuje nebo nemá žádná AP.';
		return true;
	    }
	    $this->template->oblast = $this->oblast->find($this->getParam('id'))->jmeno;
		
	    $table = Html::el('table')->setClass('table table-striped');
	    $tr = $table->create('tr');
	    $tr->create('th')->setText('ID AP');
	    $tr->create('th')->setText('Jméno AP');
	    $tr->create('th')->setText('Poznámka');
	    $tr->create('th')->setText('Akce')->setColspan('2');

	    while($ap = $apcka->fetch()) {
		$tr = $table->create('tr');
		$tr->create('td')->setText($ap->id);
		$tr->create('td')->setText($ap->jmeno);
		$tr->create('td')->setText($ap->poznamka);
		$tdAkce = $tr->create('td');
		
		
		$tdAkce->create('a')->href($this->link('Ap:show', array('id'=>$ap->id)))->setText('Zobrazit podrobnosti');
		$tdAkce->add(' - ');
		$tdAkce->create('a')->href($this->link('Ap:edit', array('id'=>$ap->id)))->setText('Editovat');
		$tdAkce->add(' - ');
		$tdAkce->create('a')->href($this->link('Uzivatel:list', array('id'=>$ap->id)))->setText('Zobrazit uživatele');
	    }

	    $this->template->table = $table;
	    
	    
	    $spravciTab = Html::el('table')->setClass('table table-striped');
	    $tr = $spravciTab->create('tr');
	    $tr->create('th')->setText('Jméno');
	    $tr->create('th')->setText('Nickname');
	    $tr->create('th')->setText('Funkce');
	    
	    $spravci = $this->oblast->getSeznamSpravcu($this->getParam('id'));
	    foreach($spravci as $spravce) {
		$tr = $spravciTab->create('tr');
		$tr->create('td')->setText($spravce->jmeno.' '.$spravce->prijmeni);
		$tr->create('td')->setText($spravce->nick);
		$tr->create('td')->setText('TODO');
		//$tr->create('td')->setText($spravce->ref('jeSpravce', 'uzivatel_id'));
		//\Tracy\Dumper::dump($spravce->ref('jeSpravce', 'uzivatel_id'));
	    }
	    $this->template->spravci = $spravciTab;
	}
	else {
	   $this->template->table = 'Prosím, zadejte oblast.'; 
	}
    }
    
    public function renderShow() {
	if($this->getParam('id') && $ap = $this->ap->getAP($this->getParam('id'))) {
	    $this->template->ap = $ap;
	    $this->template->adresy = $this->ipAdresa->getIPTable($ap->related('IPAdresa.Ap_id'));
	    $this->template->subnety = $this->subnet->getSubnetTable($ap->related('Subnet.Ap_id'));
	    $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser()->getIdentity()->getId());
	}
    }

    public function renderEdit() {
    
      $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser()->getIdentity()->getId());
	//Render Edit
    }
    
    protected function createComponentApForm() {
	$form = new Form;
        $form->addHidden('id');
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno oblasti');
	$form->addSelect('Oblast_id', 'Oblast', $this->oblast->getSeznamOblastiBezAP())->setRequired('Zadejte jméno oblasti');;
	$form->addTextArea('poznamka', 'Poznámka', 24, 10);
	$data = $this->ipAdresa;
	$typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
	$ips = $form->addDynamic('ip', function (Container $ip) use ($data,$typyZarizeni) {
		$data->getIPForm($ip, $typyZarizeni);

                $ip->addSubmit('remove', '– Odstranit IP')
                        ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                        ->setValidationScope(FALSE)
                        ->addRemoveOnClick();
        }, ($this->getParam('id')>0?0:1));

        $ips->addSubmit('add', '+ Přidat další IP')
                ->setAttribute('class', 'btn btn-success btn-xs btn-white')
                ->setValidationScope(FALSE)
                ->addCreateOnClick(TRUE);
	
	$form->addSubmit('save', 'Uložit')
		->setAttribute('class', 'btn btn-success btn-xs btn-white');
	
	$form->onSuccess[] = $this->apFormSucceded;
	
	if($this->getParam('id')) {
	    $values = $this->ap->getAP($this->getParam('id'));
	    if($values) {
		foreach($values->related('IPAdresa.Ap_id') as $ip_id => $ip_data) {
		    $form["ip"][$ip_id]->setValues($ip_data);
		}
		$form->setValues($values);
	    }
	} 	
	return($form);
    }
    
    public function apFormSucceded($form, $values) {
	$idAP = $values->id;
	$ips = $values->ip;
	unset($values["ip"]);
	
	//\Tracy\Dumper::dump($values);
	//return(true);

	// Zpracujeme nejdriv APcka
	if(empty($values->id))
	    $idAP = $this->ap->insert($values)->id;
	else
	    $this->ap->update($idAP, $values);

	//return(true);
	
	// Potom zpracujeme IPcka
	$newAPIPIDs = array();
	foreach($ips as $ip)
	{
	    $ip->Ap_id = $idAP;
	    $idIp = $ip->id;
            if(!empty($ip->ip_adresa)) {
                if(empty($ip->id))
                    $idIp = $this->ipAdresa->insert($ip)->id;
                else
                    $this->ipAdresa->update($idIp, $ip);

                $newAPIPIDs[] = intval($idIp);
            }
	}
	
	// A tady smazeme v DB ty ipcka co jsme smazali
	$APIPIDs = array_keys($this->ap->getAP($idAP)->related('IPAdresa.Ap_id')->fetchPairs('id', 'ip_adresa'));
	$toDelete = array_values(array_diff($APIPIDs, $newAPIPIDs));
	$this->ipAdresa->deleteIPAdresy($toDelete);
	
	$this->redirect('Ap:show', array('id'=>$idAP)); 
	return true;
    }
}
