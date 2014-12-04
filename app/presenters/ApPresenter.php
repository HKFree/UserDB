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
    private $log;

    function __construct(Model\Uzivatel $uzivatel, Model\AP $ap, Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\TypZarizeni $typZarizeni, Model\Log $log) {
	$this->uzivatel = $uzivatel;       
	$this->ap = $ap;
	$this->ipAdresa = $ipAdresa;
	$this->subnet = $subnet;
	$this->typZarizeni = $typZarizeni;
        $this->log = $log;        
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
	    $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
	}
    }

    public function renderEdit() {
    
      $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
	//Render Edit
    }
    
    protected function createComponentApForm() {
	$form = new Form($this, 'apForm');
        $form->addHidden('id');
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno oblasti');
	$form->addSelect('Oblast_id', 'Oblast', $this->oblast->getSeznamOblastiBezAP())->setRequired('Zadejte jméno oblasti');;
	$form->addTextArea('poznamka', 'Poznámka', 24, 10);
	$dataIp = $this->ipAdresa;
	$typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
	$ips = $form->addDynamic('ip', function (Container $ip) use ($dataIp,$typyZarizeni) {
		$dataIp->getIPForm($ip, $typyZarizeni);

                $ip->addSubmit('remove', '– Odstranit IP')
                        ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                        ->setValidationScope(FALSE)
                        ->addRemoveOnClick();
        }, ($this->getParam('id')>0?0:1));

        $ips->addSubmit('add', '+ Přidat další IP')
                ->setAttribute('class', 'btn btn-success btn-xs btn-white')
                ->setValidationScope(FALSE)
                ->addCreateOnClick(TRUE);
        
        $dataSubnet = $this->subnet;
	$subnets = $form->addDynamic('subnet', function (Container $subnet) use ($dataSubnet) {
		$dataSubnet->getSubnetForm($subnet);

                $subnet->addSubmit('remove_subnet', '– Odstranit Subnet')
                        ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                        ->setValidationScope(FALSE)
                        ->addRemoveOnClick();
        }, ($this->getParam('id')>0?0:1));

        $subnets->addSubmit('add_subnet', '+ Přidat další Subnet')
                ->setAttribute('class', 'btn btn-success btn-xs btn-white')
                ->setValidationScope(FALSE)
                ->addCreateOnClick(TRUE);
        
	$form->addSubmit('save', 'Uložit')
		->setAttribute('class', 'btn btn-success btn-xs btn-white');
	
	$form->onSuccess[] = array($this, 'apFormSucceded');
    
	$submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
	    $values = $this->ap->getAP($this->getParam('id'));
	    if($values) {
		foreach($values->related('IPAdresa.Ap_id') as $ip_id => $ip_data) {
		    $form["ip"][$ip_id]->setValues($ip_data);
		}
		foreach($values->related('Subnet.Ap_id') as $subnet_id => $subnet_data) {
		    $form["subnet"][$subnet_id]->setValues($subnet_data);
		}
		$form->setValues($values);
	    }
	} 	
	return($form);
    }
    
    public function apFormSucceded($form, $values) {
        $log = array();
        $idAP = $values->id;
        $ips = $values->ip;
        $subnets = $values->subnet;
        unset($values["ip"]);
        unset($values["subnet"]);

        // Zpracujeme nejdriv APcko
        if(empty($values->id)) {
            $idAP = $this->ap->insert($values)->id;
            $this->log->logujInsert($values, 'Ap', $log);
           
        } else {
            $oldap = $this->ap->getAP($idAP);
            $this->ap->update($idAP, $values);
            $this->log->logujUpdate($oldap, $values, 'Ap', $log);           
        }

        // Potom zpracujeme IPcka
        $newAPIPIDs = array();
        foreach($ips as $ip)
        {
            $ip->Ap_id = $idAP;
            $idIp = $ip->id;
            if(empty($ip->id)) {
                $idIp = $this->ipAdresa->insert($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa['.$idIp.']', $log);                    
            } else {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa['.$idIp.']', $log);                  
            }
            $newAPIPIDs[] = intval($idIp);
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $APIPIDs = array_keys($this->ap->getAP($idAP)->related('IPAdresa.Ap_id')->fetchPairs('id', 'ip_adresa'));
        $toDelete = array_values(array_diff($APIPIDs, $newAPIPIDs));
            if(!empty($toDelete)) {
                foreach($toDelete as $idIp) {
                    $oldip = $this->ipAdresa->getIPAdresa($idIp);
                    $this->log->logujDelete($oldip, 'IPAdresa['.$idIp.']', $log);
                }
            }

        $this->ipAdresa->deleteIPAdresy($toDelete);
        unset($toDelete);
        // Potom zpracujeme Subnety
        $newAPSubnetIDs = array();
        foreach($subnets as $subnet)
        {
            $subnet->Ap_id = $idAP;
            $idSubnet = $subnet->id;
            if(empty($subnet->id)) {
                $idSubnet = $this->subnet->insert($subnet)->id;
                $this->log->logujInsert($ip, 'Subnet['.$idSubnet.']', $log);                    
            } else {
                $oldsubnet = $this->subnet->getSubnet($idSubnet);
                $this->subnet->update($idSubnet, $subnet);
                $this->log->logujUpdate($oldsubnet, $subnet, 'Subnet['.$idSubnet.']', $log);                  
            }
            $newAPSubnetIDs[] = intval($idSubnet);
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $APSubnetIDs = array_keys($this->ap->getAP($idAP)->related('Subnet.Ap_id')->fetchPairs('id', 'subnet'));
        $toDelete = array_values(array_diff($APSubnetIDs, $newAPSubnetIDs));
            if(!empty($toDelete)) {
                foreach($toDelete as $idSubnet) {
                    $oldsubnet = $this->subnet->getSubnet($idSubnet);
                    $this->log->logujDelete($oldsubnet, 'Subnet['.$idSubnet.']', $log);
                }
            }

        $this->subnet->deleteSubnet($toDelete);
        unset($toDelete);

        $this->log->loguj('Ap', $idAP, $log);


        $this->redirect('Ap:show', array('id'=>$idAP)); 
        return true;
    }
}
