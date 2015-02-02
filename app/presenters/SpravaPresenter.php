<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Tracy\Debugger;
    
use Nette\Forms\Controls\SubmitButton;
/**
 * Sprava presenter.
 */
class SpravaPresenter extends BasePresenter
{  
    private $cestneClenstviUzivatele;  
    private $platneCC;
    private $uzivatel;
    private $log;
    private $ap;

    function __construct(Model\CestneClenstviUzivatele $cc, Model\cc $actualCC, Model\Uzivatel $uzivatel, Model\Log $log, Model\AP $ap) {
        $this->cestneClenstviUzivatele = $cc;
        $this->platneCC = $actualCC;
    	$this->uzivatel = $uzivatel;
        $this->log = $log;
        $this->ap = $ap;
    }

    public function renderNastroje()
    {
    	$this->template->canApproveCC = $this->getUser()->isInRole('VV');
    }

    public function renderSchvalovanicc()
    {
        $this->template->canApproveCC = $this->getUser()->isInRole('VV');
    }
    
    public function renderPrehledcc()
    {
        //$this->template->canApproveCC = $this->getUser()->isInRole('VV');
    }
    
    protected function createComponentGrid($name)
    {
        $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
        
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('cc_export');
        
        if($canViewOrEdit)
        {
            $grid->setModel($this->platneCC->getCCWithNamesVV());
        }
        else {
            $grid->setModel($this->platneCC->getCCWithNames());
        }
        
    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('plati_od' => 'DESC'));
         
    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('plati_od', 'Platnost od')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('plati_do', 'Platnost do')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('name', 'Jméno a příjmení')->setSortable()->setFilterText()->setSuggestion();
        
        $grid->addActionHref('show', 'Zobrazit')
                ->setIcon('eye-open');
    }
    
    public function actionShow($id) {
        $this->redirect('Uzivatel:show', array('id'=>$id)); 
    }

    protected function createComponentSpravaCCForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "spravaCCForm");
            
        $data = $this->cestneClenstviUzivatele;
    	$rights = $form->addDynamic('rights', function (Container $right) use ($data) {
    	    
            $right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
            $right->addHidden('id')->setAttribute('class', 'id ip');
                  
            $right->addText('plati_od', 'Platnost od:')
                 //->setType('date')
                 ->setAttribute('class', 'datepicker ip')
                 ->setAttribute('data-date-format', 'YYYY/MM/DD')
                 ->addRule(Form::FILLED, 'Vyberte datum')
                 ->addCondition(Form::FILLED)
                 ->addRule(Form::PATTERN, 'prosím zadejte datum ve formátu RRRR-MM-DD', '^\d{4}-\d{2}-\d{1,2}$');
                 
            $right->addText('plati_do', 'Platnost od:')
                 //->setType('date')
                 ->setAttribute('class', 'datepicker ip')
                 ->setAttribute('data-date-format', 'YYYY/MM/DD')
                 ->addCondition(Form::FILLED)
                 ->addRule(Form::PATTERN, 'prosím zadejte datum ve formátu RRRR-MM-DD', '^\d{4}-\d{2}-\d{1,2}$');
                 
                 $right->addText('poznamka', 'Poznámka:')
                 ->setAttribute('class', 'note ip');
                 
                 $schvalenoStates = array(
                    0 => 'Nerozhodnuto',
                    1 => 'Schváleno',
                    2 => 'Zamítnuto');
                 $right->addRadioList('schvaleno', 'Stav schválení: ', $schvalenoStates)
                         ->getSeparatorPrototype()->setName(NULL);
                 //$right->addCheckbox('schvaleno', 'Schváleno')->setAttribute('class', 'approve ip');

    	}, 0, false);
    
    	$form->addSubmit('save', 'Uložit')
    		 ->setAttribute('class', 'btn btn-success btn-xs btn-white');
        
    	$form->onSuccess[] = array($this, 'spravaCCFormSucceded');
    
    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if(!$submitujeSe) {
    		foreach($this->cestneClenstviUzivatele->getNeschvalene() as $rights_id => $rights_data) {
                $form["rights"][$rights_id]->setValues($rights_data);
    		}
    	}                
    
    	return $form;
    }
    public function spravaCCFormSucceded($form, $values) {
        $log = array();
    	$prava = $values->rights;

    	// Zpracujeme prava
    	foreach($prava as $pravo)
    	{
    	    $pravoId = $pravo->id;
            
            //osetreni aby prazdne pole od davalo null a ne 00-00-0000
            if(empty($pravo->plati_od)) $pravo->plati_od = null; 
            if(empty($pravo->plati_do)) $pravo->plati_do = null;
            if(empty($pravo->schvaleno)) $pravo->schvaleno = 0;
            
            if(!empty($pravo->id)) {                
                $starePravo = $this->cestneClenstviUzivatele->getCC($pravoId);
                $this->cestneClenstviUzivatele->update($pravoId, $pravo);
            }
    	}
    	
        //$this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Sprava:schvalovanicc'); 
    	return true;
    }
}
