<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid;
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
    private $typZarizeni;

    function __construct(Model\TypClenstvi $typClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni) {
	$this->typClenstvi = $typClenstvi;
	$this->zpusobPripojeni = $zpusobPripojeni;
	$this->uzivatel = $uzivatel;
	$this->ipAdresa = $ipAdresa;  
	$this->ap = $ap;
	$this->typZarizeni = $typZarizeni;
    }
  
    
    public function actionExportregform() {
      $myfile = fopen("/template/evidence.rtf", "r") or die("Unable to open file!");
      $rtfdata = fread($myfile,filesize("/template/evidence.rtf"));
      fclose($myfile);
      $this->terminate(new ContentDownloadResponse($rtfdata, 'registrace.rtf'));
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
	$form->addText('rok_narozeni', 'Rok narození',30);
	$form->addSelect('Ap_id', 'Oblast - AP', $aps);
	$form->addRadioList('TypClenstvi_id', 'Členství', $typClenstvi)->addRule(Form::FILLED, 'Vyberte typ členství');
	$form->addRadioList('ZpusobPripojeni_id', 'Způsob připojení', $zpusobPripojeni)->addRule(Form::FILLED, 'Vyberte způsob připojení');
	$form->addSelect('index_potizisty', 'Index potížisty', array(0=>0,10=>10,20=>20,30=>30,40=>40,50=>50,60=>60,70=>70,80=>80,90=>90,100=>100))->setDefaultValue(50);
	$form->addTextArea('poznamka', 'Poznámka', 24, 10);

	$typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
	$data = $this->ipAdresa;
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
	$form->onSuccess[] = $this->uzivatelFormSucceded;

	// pokud editujeme, nacteme existujici ipadresy
	if($this->getParam('id')) {
	    $values = $this->uzivatel->getUzivatel($this->getParam('id'));
	    if($values) {
		foreach($values->related('IPAdresa.Uzivatel_id') as $ip_id => $ip_data) {
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
	$userIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
	$toDelete = array_values(array_diff($userIPIDs, $newUserIPIDs));
	$this->ipAdresa->deleteIPAdresy($toDelete);
	
	$this->redirect('Uzivatel:edit', array('id'=>$idUzivatele)); 
	return true;
    }


  protected function createComponentGrid($name)
  {
      $id = $this->getParam('id');
      $grid = new \Grido\Grid($this, $name);
      $grid->setModel($this->uzivatel->getSeznamUzivateluZAP($id));
      $grid->setDefaultPerPage(100);
      $grid->setDefaultSort(array('zalozen' => 'ASC'));
      
      $list = array('active' => 'bez zrušených', 'all' => 'včetně zrušených');
      $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)->setDefaultValue('active')->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));

      /*if($canseedetails)*/
      {
      $grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
      $grid->addColumnText('jmeno', 'Jméno')->setSortable()->setFilterText()->setSuggestion();
      $grid->addColumnText('prijmeni', 'Příjmení')->setSortable()->setFilterText()->setSuggestion();
      $grid->addColumnText('nick', 'Nickname')->setSortable()->setFilterText()->setSuggestion();
      $grid->addColumnText('adresa', 'Adresa')->setSortable()->setFilterText();
      $grid->addColumnText('email', 'E-mail')->setSortable()->setFilterText()->setSuggestion();
      $grid->addColumnText('telefon', 'Telefon')->setSortable()->setFilterText()->setSuggestion();
      //$grid->addColumnText('ip4', 'IP adresy')->setSortable()->setFilterText();
      //$grid->addColumnText('wifi_user', 'Vlastní WI-FI')->setSortable()->setReplacement(array('2' => Html::el('b')->setText('ANO'),'1' => Html::el('b')->setText('NE')));
      $grid->addColumnText('poznamka', 'Poznámka')->setSortable()->setFilterText();
      }
      /*else
      {
        $grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('nick', 'Nickname')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('ip4', 'IP adresy')->setSortable()->setFilterText();
      } */

  }
	
    public function renderList()
    {
	if($this->getParam('id'))
	{
	    $ob = $this->ap->getAP($this->getParam('id'));
	    $this->template->ap = $ob;
      
      //$form->addHidden("id", $this->getParam('id'));
      
	    //$this->template->lokace["ap"] = $ob->jmeno;
	    //$this->template->lokace["oblast"] = $ob->oblast->jmeno;

	    /*$tr->create('th')->setText('Akce');
	    $barvy = array(
	      1 => 'danger',
	      2 => 'success',
	      3 => '',
	      4 => 'info',
	    );
	    while($uzivatel = $uzivatele->fetch()) {
		$tr = $table->create('tr')->setClass($barvy[$uzivatel->TypClenstvi_id]);

		$tr->create('td')->create('a')->href($this->link('Uzivatel:edit', array('id'=>$uzivatel->id)))->setText('Editovat');
	    }    */

	}
	else {
	   $this->template->table = 'Chyba, AP nenalezeno.'; 
	}
    }

}
