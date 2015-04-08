<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Nette\Mail\Message,
    Nette\Mail\SendmailMailer,
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
    public $oblast;

    function __construct(Model\Oblast $oblast, Model\CestneClenstviUzivatele $cc, Model\cc $actualCC, Model\Uzivatel $uzivatel, Model\Log $log, Model\AP $ap) {
        $this->cestneClenstviUzivatele = $cc;
        $this->platneCC = $actualCC;
    	$this->uzivatel = $uzivatel;
        $this->log = $log;
        $this->ap = $ap;
        $this->oblast = $oblast;
    }

    public function renderNastroje()
    {
    	$this->template->canApproveCC = $this->getUser()->isInRole('VV');
    }

    public function renderSchvalovanicc()
    {
        /*** clear old registration files ***/
        foreach (glob(sys_get_temp_dir()."/registrace*") as $file) {
        /*** if file is 7 days old then delete it ***/
        if (filemtime($file) < time() - 604800) {
            unlink($file);
            }
        }
        
        $this->template->canApproveCC = $this->getUser()->isInRole('VV');
        $uzivatele = array();
        foreach($this->cestneClenstviUzivatele->getNeschvalene() as $cc_id => $cc_data) {
            $uzivatele[] = $cc_data->Uzivatel_id;
            $uzivatele[] = $cc_data->zadost_podal;
        }
        $uzivatele = array_unique($uzivatele);
        $this->template->uzivatele = $this->uzivatel->findBy(array("id" => $uzivatele));
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
                 
            $right->addTextArea('poznamka', 'Poznámka:', 72, 5)
                 ->setAttribute('class', 'note ip');
                 
            $schvalenoStates = array(
                0 => 'Nerozhodnuto',
                1 => 'Schváleno',
                2 => 'Zamítnuto');
            $right->addRadioList('schvaleno', 'Stav schválení: ', $schvalenoStates)
                  ->getSeparatorPrototype()->setName("span")->style('margin-right', '7px');

            $right->addHidden('zadost_podal');
            $right->addHidden('zadost_podana');

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
    
    /**
    * Schválení čestného členství
    */
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
                
                if($starePravo->schvaleno != $pravo->schvaleno && ($pravo->schvaleno == 1 || $pravo->schvaleno == 2))
                {
                    $navrhovatel = $this->uzivatel->getUzivatel($pravo->zadost_podal);
                    $schvaleny = $this->uzivatel->getUzivatel($pravo->Uzivatel_id);

                    $stav = $pravo->schvaleno == 1 ? "schválena" : "zamítnuta";

                    $mail = new Message;
                    $mail->setFrom('UserDB <userdb@hkfree.org>')
                        ->addTo($navrhovatel->email)
                        ->addTo($schvaleny->email)
                        ->setSubject('Žádost o čestné členství')
                        ->setBody("Dobrý den,\nbyla $stav žádost o čestné členství na dobu $pravo->plati_od - $pravo->plati_do.\nID:$pravo->Uzivatel_id\nPoznámka: $pravo->poznamka\n\n");

                    $mailer = new SendmailMailer;
                    $mailer->send($mail);
                }
            }
    	}
    	
        //$this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Sprava:schvalovanicc'); 
    	return true;
    }
    
    public function renderNovaoblast()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
    }

    protected function createComponentNovaoblastForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "novaoblastForm");
    	$form->addHidden('id');

        $form->addText('jmeno', 'Název oblasti', 50)->setRequired('Zadejte název oblasti');

    	$form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'novaoblastFormSucceded');

    	// pokud editujeme, nacteme existujici
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $existujiciOblast = $this->oblast->getOblast($this->getParam('id'));
            if($existujiciOblast) {
                $form->setValues($existujiciOblast);
    	    }
    	}                
    
    	return $form;
    }
    
    public function novaoblastFormSucceded($form, $values) {

        $idOblasti = $values->id;
        
        if(empty($values->id)) {
            $values->datum_zalozeni = new Nette\Utils\DateTime;
            $this->oblast->insert($values);
            $this->flashMessage('Oblast byla vytvořena.');            
        } else {
    	    $this->oblast->update($idOblasti, $values);
        }
            	
    	$this->redirect('Sprava:nastroje'); 
    	return true;
    }
    
    public function renderNoveap()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
    }

    protected function createComponentNoveapForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "noveapForm");
    	$form->addHidden('id');

        $aps = $this->oblast->formatujOblasti($this->oblast->getSeznamOblasti());
        
        $form->addSelect('Oblast_id', 'Oblast', $aps);
        
        $form->addText('jmeno', 'Název AP', 50)->setRequired('Zadejte název AP');
        $form->addTextArea('poznamka', 'Poznámka', 72, 10);

    	$form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'noveapFormSucceded');

    	// pokud editujeme, nacteme existujici
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $existujiciAp = $this->ap->getAP($this->getParam('id'));
            if($existujiciAp) {
                $form->setValues($existujiciAp);
    	    }
    	}                
    
    	return $form;
    }
    
    public function noveapFormSucceded($form, $values) {

        $idAp = $values->id;
        
        if(empty($values->id)) {
            $this->ap->insert($values);
            $this->flashMessage('AP bylo vytvořeno.');            
        } else {
    	    $this->ap->update($idAp, $values);
        }
            	
    	$this->redirect('Sprava:nastroje'); 
    	return true;
    }
}
