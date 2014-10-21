<?php

namespace App\Presenters;

use Nette,
	App\Model,
        Nette\Application\UI\Form,
        Nette\Forms\Container,
	Nette\Utils\Html;
use Nette\Forms\Controls\SubmitButton;
/**
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{       
    private $typClenstvi;
    private $zpusobPripojeni;
    private $uzivatel;
    private $ipAdresa;
    private $ap;

    function __construct(Model\TypClenstvi $typClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap) {
	    $this->typClenstvi = $typClenstvi;
	    $this->zpusobPripojeni = $zpusobPripojeni;
	    $this->uzivatel = $uzivatel;
	    $this->ipAdresa = $ipAdresa;  
	    $this->ap = $ap;
    }

    public function renderEdit()
    {
	    $this->template->anyVariable = 'any value';
    }

    protected function createComponentUzivatelForm() {
	    $typClenstvi = $this->typClenstvi->getTypyClenstvi()->fetchPairs('id','text');
	    $zpusobPripojeni = $this->zpusobPripojeni->getZpusobyPripojeni()->fetchPairs('id','text');
	    $aps = $this->oblast->getSeznamOblastiSAP();

	    $form = new Form;
            $form->addHidden('id');
	    $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno');
	    $form->addText('prijmeni', 'Přijmení', 30)->setRequired('Zadejte příjmení');
	    $form->addText('nick', 'Nick (přezdívka)', 30)->setRequired('Zadejte nickname');
	    $form->addText('email', 'Email', 30)->setRequired('Zadejte email')->addRule(Form::EMAIL, 'Musíte zadat platný email');;
	    $form->addText('telefon', 'Telefon', 30)->setRequired('Zadejte telefon');
	    $form->addTextArea('adresa', 'Adresa (ulice čp, psč město)', 24)->setRequired('Zadejte adresu');
	    $form->addText('rokNarozeni', 'Rok narození',30);
	    $form->addSelect('ap_id', 'Oblast - AP', $aps);
	    $form->addRadioList('typClenstvi_id', 'Členství', $typClenstvi)->addRule(Form::FILLED, 'Vyberte typ členství');
	    $form->addRadioList('zpusobPripojeni_id', 'Způsob připojení', $zpusobPripojeni)->addRule(Form::FILLED, 'Vyberte způsob připojení');
	    $form->addSelect('indexPotizisty', 'Index potížisty', array(10=>10,20=>20,30=>30,40=>40,50=>50,60=>60,70=>70,80=>80,90=>90,100=>100))->setDefaultValue(50);
	    $form->addTextArea('poznamka', 'Poznámka', 24, 10);

            $ips = $form->addDynamic('ip', function (Container $ip) {
		    //$ip->addHidden('uzivatel_id')->setValue($this->getParam('id'));
                    $ip->addHidden('id')->setAttribute('class', 'ip');
		    $ip->addText('ipAdresa', 'IP Adresa',10)->setAttribute('class', 'ip')->setAttribute('placeholder', 'IP Adresa');
		    $ip->addText('hostname', 'Hostname',9)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Hostname');
		    $ip->addText('macAdresa', 'MAC Adresa',18)->setAttribute('class', 'ip')->setAttribute('placeholder', 'MAC Adresa');
		    $ip->addCheckbox('internet', 'Internet')->setAttribute('class', 'ip');
                    $ip->addCheckbox('smokeping', 'Smokeping')->setAttribute('class', 'ip');
		    $ip->addText('login', 'Login',8)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Login');
		    $ip->addText('heslo', 'Heslo',8)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Heslo');
		    $ip->addText('popis', 'Popis', 30)->setAttribute('class', 'ip')->setAttribute('placeholder', 'Popis');

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
	    $form->onSuccess[] = $this->uzivatelFormSucceded;

	    // pokud editujeme, nacteme existujici ipadresy
	    if($this->getParam('id')) {
		$values = $this->uzivatel->getUzivatel($this->getParam('id'));
		if($values) {
		    foreach($values->related('ipAdresa.uzivatel_id') as $ip_id => $ip_data) {
			$form["ip"][$ip_id]->setValues($ip_data);
		    }
		    $form->setValues($values);
		}
	    }                
/*
            $renderer = $form->getRenderer();
            $renderer->wrappers['controls']['container'] = NULL;
            $renderer->wrappers['pair']['container'] = 'div class=form-group';
            $renderer->wrappers['pair']['.error'] = 'has-error';
            $renderer->wrappers['control']['container'] = 'div class=col-sm-9';
            $renderer->wrappers['label']['container'] = 'div class="col-sm-3 control-label"';
            $renderer->wrappers['control']['description'] = 'span class=help-block';
            $renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';

            // make form and controls compatible with Twitter Bootstrap
            $form->getElementPrototype()->class('form-horizontal');

            foreach ($form->getControls() as $control) {
                    if ($control instanceof Controls\Button) {
                            $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-default');
                            $usedPrimary = TRUE;

                    } elseif ($control instanceof Controls\TextBase || $control instanceof Controls\SelectBox || $control instanceof Controls\MultiSelectBox) {
                            $control->getControlPrototype()->addClass('form-control');

                    } elseif ($control instanceof Controls\Checkbox || $control instanceof Controls\CheckboxList || $control instanceof Controls\RadioList) {
                            $control->getSeparatorPrototype()->setName('div')->addClass($control->getControlPrototype()->type);
                    }
            }
*/
	    return $form;
    }
    public function uzivatelFormSucceded($form, $values) {
	$idUzivatele = $values->id;
	$ips = $values->ip;
	unset($values["ip"]);

	// Zpracujeme nejdriv uzivatele
	if(empty($values->id))
	    $idUzivatele = $this->uzivatel->insert($values)->id;
	else
	    $this->uzivatel->update($idUzivatele, $values);

	// Potom zpracujeme IPcka
	$newUserIPIDs = array();
	foreach($ips as $ip)
	{
	    $ip->uzivatel_id = $idUzivatele;
	    $idIp = $ip->id;
	    if(empty($ip->id))
		$idIp = $this->ipAdresa->insert($ip)->id;
	    else
		$this->ipAdresa->update($idIp, $ip);

	    $newUserIPIDs[] = intval($idIp);
	}

	// A tady smazeme v DB ty ipcka co jsme smazali
	$userIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('ipAdresa.uzivatel_id')->fetchPairs('id', 'ipAdresa'));
	$toDelete = array_values(array_diff($userIPIDs, $newUserIPIDs));
	$this->ipAdresa->deleteIPAdresy($toDelete);
	
	$this->redirect('Uzivatel:edit', array('id'=>$idUzivatele)); 
	return true;
    }

	
    public function renderList()
    {
	if($this->getParam('id'))
	{
	    $ob = $this->ap->getAP($this->getParam('id'));
	    $this->template->ap = $ob;
	    //$this->template->lokace["ap"] = $ob->jmeno;
	    //$this->template->lokace["oblast"] = $ob->oblast->jmeno;
	    $uzivatele = $this->uzivatel->getSeznamUzivateluZAP($this->getParam('id'));
	    $table = Html::el('table')->setClass('table table-striped table-condensed');
	    $tr = $table->create('tr');
	    $tr->create('th')->setText('UID');
	    $tr->create('th')->setText('Jméno');
	    $tr->create('th')->setText('Přijmení');
	    $tr->create('th')->setText('Email');
	    $tr->create('th')->setText('Telefon');
	    $tr->create('th')->setText('Akce');
	    $barvy = array(
	      1 => 'danger',
	      2 => 'success',
	      3 => '',
	      4 => 'info',
	    );
	    while($uzivatel = $uzivatele->fetch()) {
		$tr = $table->create('tr')->setClass($barvy[$uzivatel->typClenstvi_id]);
		$tr->create('td')->setText($uzivatel->id);
		$tr->create('td')->setText($uzivatel->jmeno);
		$tr->create('td')->setText($uzivatel->prijmeni);
		$tr->create('td')->setText($uzivatel->email);
		$tr->create('td')->setText($uzivatel->telefon);
		$tr->create('td')->create('a')->href($this->link('Uzivatel:edit', array('id'=>$uzivatel->id)))->setText('Editovat');
	    }

	    $this->template->table = $table;
	}
	else {
	   $this->template->table = 'Chyba, AP nenalezeno.'; 
	}
    }

}
