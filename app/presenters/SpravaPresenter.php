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
    private $cacheMoney;
    private $ipAdresa;
    private $sloucenyUzivatel;

    function __construct(Model\SloucenyUzivatel $slUzivatel, Model\CacheMoney $cacheMoney, Model\Oblast $ob, Model\CestneClenstviUzivatele $cc, Model\cc $actualCC, Model\Uzivatel $uzivatel, Model\Log $log, Model\AP $ap, Model\IPAdresa $ipAdresa) {
        $this->cestneClenstviUzivatele = $cc;
        $this->platneCC = $actualCC;
    	$this->uzivatel = $uzivatel;
        $this->log = $log;
        $this->ap = $ap;
        $this->oblast = $ob;
        $this->cacheMoney = $cacheMoney;
        $this->ipAdresa = $ipAdresa; 
        $this->sloucenyUzivatel = $slUzivatel; 
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
    
    public function renderSlouceni()
    {
        //$this->template->canApproveCC = $this->getUser()->isInRole('VV');
    }
    
    protected function createComponentSlouceniGrid($name)
    {
        //$canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
        
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('slouceni_export');
        
        $grid->setModel($this->sloucenyUzivatel->getAll());
        
    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('Uzivatel_id' => 'ASC'));
         
    	$grid->addColumnText('Uzivatel_id', 'Sloučeno pod')->setSortable()->setFilterText();
        $grid->addColumnText('slouceny_uzivatel', 'Sloučený uživatel')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('datum_slouceni', 'Datum sloučení')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('sloucil', 'Sloučil')->setSortable()->setFilterText()->setSuggestion();
        
        /*$grid->addActionHref('show', 'Zobrazit')
                ->setIcon('eye-open');*/
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
                 
            $right->addText('plati_do', 'Platnost do:')
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
    
    public function renderSlucovani()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
        
        $u1id = 1;
        $u2id = 1;
        if($this->getParam('u1'))
        {
            $u1id = $this->getParam('u1');
        }
        if($this->getParam('u2'))
        {
            $u2id = $this->getParam('u2');
        }
        
        $money_uid = $this->context->parameters["money"]["login"];
        $money_heslo = $this->context->parameters["money"]["password"];
        $money_client = new \SoapClient(
            'https://' . $money_uid . ':' . $money_heslo . '@money.hkfree.org/wsdl/moneyAPI.wsdl',
            array(
                'login'         => $money_uid,
                'password'      => $money_heslo,
                'trace'         => 0,
                'exceptions'    => 0,
                'connection_timeout'=> 15
            )
        );

        $u1 = $this->uzivatel->getUzivatel($u1id);
        $this->template->u1 = $u1;
        $this->template->u1hasCC = $this->cestneClenstviUzivatele->getHasCC($u1id);
        $ipAdresyu1 = $u1->related('IPAdresa.Uzivatel_id');
        if($ipAdresyu1->count() > 0)
        {
            $this->template->u1adresyline = join(", ",array_values($ipAdresyu1->fetchPairs('id', 'ip_adresa')));
        }
        else
        {
            $this->template->u1adresyline = null;
        }
                if(!$this->cacheMoney->getIsCacheValid($u1id))
                {                    
                    $money_callresult = $money_client->hkfree_money_userGetInfo($u1id);   
                    if (!is_soap_fault($money_callresult)) {                        
                 
                        $moneyResult = array(
                            'cache_date' => new Nette\Utils\DateTime,
                            'active' => ($money_callresult[$u1id]->userIsActive->isActive == 1) ? 1 : (($money_callresult[$u1id]->userIsActive->isActive == 0) ? 0 : null),
                            'disabled' => ($money_callresult[$u1id]->userIsDisabled->isDisabled == 1) ? 1 : (($money_callresult[$u1id]->userIsDisabled->isDisabled == 0) ? 0 : null),
                            'last_payment' => ($money_callresult[$u1id]->GetLastPayment->LastPaymentDate == "null") ? null : date("Y-m-d",strtotime($money_callresult[$u1id]->GetLastPayment->LastPaymentDate)),
                            'last_payment_amount' => ($money_callresult[$u1id]->GetLastPayment->LastPaymentAmount == "null") ? null : $money_callresult[$u1id]->GetLastPayment->LastPaymentAmount,
                            'last_activation' => ($money_callresult[$u1id]->GetLastActivation->LastActivationDate == "null") ? null : date("Y-m-d",strtotime($money_callresult[$u1id]->GetLastActivation->LastActivationDate)),
                            'last_activation_amount' => ($money_callresult[$u1id]->GetLastActivation->LastActivationAmount == "null") ? null : $money_callresult[$u1id]->GetLastActivation->LastActivationAmount,
                            'account_balance' => ($money_callresult[$u1id]->GetAccountBalance->GetAccountBalance >= 0) ? $money_callresult[$u1id]->GetAccountBalance->GetAccountBalance : null
                        );  
                        
                        if(!$this->cacheMoney->getIsCached($u1id))
                        {
                            $userarr = array('Uzivatel_id' => $u1id);
                            $toInsert[] = array_merge($moneyResult, $userarr);
                            $this->cacheMoney->insert($toInsert);
                        }
                        else {
                            $expired = $this->cacheMoney->getCacheItem($u1id);
                            $this->cacheMoney->update($expired->id, $moneyResult);
                        }
                    }
                }
                                
                if($this->cacheMoney->getIsCached($u1id))
                {
                    $cachedItem = $this->cacheMoney->getCacheItem($u1id);
                    $this->template->u1money_act = ($cachedItem->active == 1) ? "ANO" : (($cachedItem->active == 0) ? "NE" : "?");
                    $this->template->u1money_dis = ($cachedItem->disabled == 1) ? "ANO" : (($cachedItem->disabled == 0) ? "NE" : "?");
                    $this->template->u1money_lastpay = ($cachedItem->last_payment == null) ? "NIKDY" : ($cachedItem->last_payment->format('d.m.Y') . " (" . $cachedItem->last_payment_amount . ")");
                    $this->template->u1money_lastact = ($cachedItem->last_activation == null) ? "NIKDY" : ($cachedItem->last_activation->format('d.m.Y') . " (" . $cachedItem->last_activation_amount . ")");
                    if($u1->kauce_mobil > 0)
                    {
                        $this->template->u1money_bal = ($cachedItem->account_balance >= 0) ? ($cachedItem->account_balance - $u1->kauce_mobil) . ' (kauce: ' . $u1->kauce_mobil . ')' : "?";
                    }
                    else{
                        $this->template->u1money_bal = ($cachedItem->account_balance >= 0) ? $cachedItem->account_balance : "?";
                    }
                }
                else
                {
                    $this->flashMessage('MONEY JSOU OFFLINE');
                    $this->template->u1money_act = "MONEY OFFLINE";
                    $this->template->u1money_dis = "MONEY OFFLINE";
                    $this->template->u1money_lastpay = "MONEY OFFLINE";
                    $this->template->u1money_lastact = "MONEY OFFLINE";
                    $this->template->u1money_bal = "MONEY OFFLINE"; 
                }

        $u2 = $this->uzivatel->getUzivatel($u2id);
        $this->template->u2 = $u2;
        $this->template->u2hasCC = $this->cestneClenstviUzivatele->getHasCC($u2id);
        $ipAdresyu2 = $u2->related('IPAdresa.Uzivatel_id');
        if($ipAdresyu2->count() > 0)
        {
            $this->template->u2adresyline = join(", ",array_values($ipAdresyu2->fetchPairs('id', 'ip_adresa')));
        }
        else
        {
            $this->template->u2adresyline = null;
        }
                if(!$this->cacheMoney->getIsCacheValid($u2id))
                {                    
                    $money_callresult = $money_client->hkfree_money_userGetInfo($u2id);   
                    if (!is_soap_fault($money_callresult)) {                        
                 
                        $moneyResult = array(
                            'cache_date' => new Nette\Utils\DateTime,
                            'active' => ($money_callresult[$u2id]->userIsActive->isActive == 1) ? 1 : (($money_callresult[$u2id]->userIsActive->isActive == 0) ? 0 : null),
                            'disabled' => ($money_callresult[$u2id]->userIsDisabled->isDisabled == 1) ? 1 : (($money_callresult[$u2id]->userIsDisabled->isDisabled == 0) ? 0 : null),
                            'last_payment' => ($money_callresult[$u2id]->GetLastPayment->LastPaymentDate == "null") ? null : date("Y-m-d",strtotime($money_callresult[$u2id]->GetLastPayment->LastPaymentDate)),
                            'last_payment_amount' => ($money_callresult[$u2id]->GetLastPayment->LastPaymentAmount == "null") ? null : $money_callresult[$u2id]->GetLastPayment->LastPaymentAmount,
                            'last_activation' => ($money_callresult[$u2id]->GetLastActivation->LastActivationDate == "null") ? null : date("Y-m-d",strtotime($money_callresult[$u2id]->GetLastActivation->LastActivationDate)),
                            'last_activation_amount' => ($money_callresult[$u2id]->GetLastActivation->LastActivationAmount == "null") ? null : $money_callresult[$u2id]->GetLastActivation->LastActivationAmount,
                            'account_balance' => ($money_callresult[$u2id]->GetAccountBalance->GetAccountBalance >= 0) ? $money_callresult[$u2id]->GetAccountBalance->GetAccountBalance : null
                        );  
                        
                        if(!$this->cacheMoney->getIsCached($u2id))
                        {
                            $userarr = array('Uzivatel_id' => $u2id);
                            $toInsert[] = array_merge($moneyResult, $userarr);
                            $this->cacheMoney->insert($toInsert);
                        }
                        else {
                            $expired = $this->cacheMoney->getCacheItem($u2id);
                            $this->cacheMoney->update($expired->id, $moneyResult);
                        }
                    }
                }
                                
                if($this->cacheMoney->getIsCached($u2id))
                {
                    $cachedItem = $this->cacheMoney->getCacheItem($u2id);
                    $this->template->u2money_act = ($cachedItem->active == 1) ? "ANO" : (($cachedItem->active == 0) ? "NE" : "?");
                    $this->template->u2money_dis = ($cachedItem->disabled == 1) ? "ANO" : (($cachedItem->disabled == 0) ? "NE" : "?");
                    $this->template->u2money_lastpay = ($cachedItem->last_payment == null) ? "NIKDY" : ($cachedItem->last_payment->format('d.m.Y') . " (" . $cachedItem->last_payment_amount . ")");
                    $this->template->u2money_lastact = ($cachedItem->last_activation == null) ? "NIKDY" : ($cachedItem->last_activation->format('d.m.Y') . " (" . $cachedItem->last_activation_amount . ")");
                    if($u2->kauce_mobil > 0)
                    {
                        $this->template->u2money_bal = ($cachedItem->account_balance >= 0) ? ($cachedItem->account_balance - $u2->kauce_mobil) . ' (kauce: ' . $u2->kauce_mobil . ')' : "?";
                    }
                    else{
                        $this->template->u2money_bal = ($cachedItem->account_balance >= 0) ? $cachedItem->account_balance : "?";
                    }
                }
                else
                {
                    $this->flashMessage('MONEY JSOU OFFLINE');
                    $this->template->u2money_act = "MONEY OFFLINE";
                    $this->template->u2money_dis = "MONEY OFFLINE";
                    $this->template->u2money_lastpay = "MONEY OFFLINE";
                    $this->template->u2money_lastact = "MONEY OFFLINE";
                    $this->template->u2money_bal = "MONEY OFFLINE"; 
                }
    }
    
    protected function createComponentSlucovaniForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "slucovaniForm");
    	$form->addHidden('id');

        $users = $this->uzivatel->getFormatovanySeznamNezrusenychUzivatelu();
        
        $form->addSelect('Uzivatel_id', 'Ponechaný uživatel (zůstane aktivní)', $users);
        $form->addSelect('slouceny_uzivatel', 'Sloučený uživatel (bude zrušen a jeho IP budou převedeny aktivnímu)', $users);

    	$form->addSubmit('nahled', 'Náhled')->setAttribute('class', 'btn btn-success btn-xs btn-white');
        $form->addSubmit('slouceni', 'Sloučit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->setDefaults(array(
                        'Uzivatel_id' => $this->getParam('u1'),
                        'slouceny_uzivatel' => $this->getParam('u2')
                    ));
        
    	$form->onSuccess[] = array($this, 'slucovaniFormSucceded');
        
        //TODO: udelat confirmation dialog na slouceni

    	// pokud editujeme, nacteme existujici
        /*$submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $existujiciAp = $this->ap->getAP($this->getParam('id'));
            if($existujiciAp) {
                $form->setValues($existujiciAp);
    	    }
    	} */               
    
    	return $form;
    }
    
    public function slucovaniFormSucceded($form, $values) {
        
        //\Tracy\Dumper::dump($form->isSubmitted()->name);
        
        if($form->isSubmitted()->name == "nahled")
        {
            if($this->sloucenyUzivatel->getSlouceniExists($values->Uzivatel_id,$values->slouceny_uzivatel))
            {
                $this->flashMessage('Takové sloučení již existuje.');
            }
            if($this->sloucenyUzivatel->getIsAlreadyMaster($values->slouceny_uzivatel))
            {
                $this->flashMessage('Uživatel ke zrušení již figuruje jako hlavní uživatel v jiném sloučení.'); 
            }
            if($this->sloucenyUzivatel->getIsAlreadySlave($values->Uzivatel_id))
            {
                $this->flashMessage('Uživatel který má zůstat aktivní již figuruje jako sloučený uživatel v jiném sloučení.'); 
            }
            $this->redirect('Sprava:slucovani', array('u1'=>$values->Uzivatel_id, 'u2'=>$values->slouceny_uzivatel)); 
        }
        
        if($form->isSubmitted()->name == "slouceni")
        {
            //$u1 = $this->uzivatel->getUzivatel($values->Uzivatel_id);
            $u2 = $this->uzivatel->getUzivatel($values->slouceny_uzivatel);
            
            
            if($this->sloucenyUzivatel->getSlouceniExists($values->Uzivatel_id,$values->slouceny_uzivatel))
            {
                $this->flashMessage('Takové sloučení již existuje.'); 
                return true;
            }
            if($this->sloucenyUzivatel->getIsAlreadyMaster($values->slouceny_uzivatel))
            {
                $this->flashMessage('Uživatel ke zrušení již figuruje jako hlavní uživatel v jiném sloučení.'); 
                return true;
            }
            if($this->sloucenyUzivatel->getIsAlreadySlave($values->Uzivatel_id))
            {
                $this->flashMessage('Uživatel který má zůstat aktivní již figuruje jako sloučený uživatel v jiném sloučení.'); 
                return true;
            }
            
            $idSlouceni = $values->id;
            
            if(empty($values->id)) {
                $values->datum_slouceni = new Nette\Utils\DateTime;
                $values->sloucil = $this->getUser()->getIdentity()->getId();
                $this->sloucenyUzivatel->insert($values);
            } else {
                //$this->sloucenyUzivatel->update($idSlouceni, $values);
            }
            
            //prevest IP z $values->slouceny_uzivatel do $values->Uzivatel_id
            $ipAdresyu2 = $u2->related('IPAdresa.Uzivatel_id');
            foreach ($ipAdresyu2 as $ip) {
                $this->ipAdresa->update($ip->id, array('Uzivatel_id'=>$values->Uzivatel_id));
            }
            //zrusit slouceneho 
            $this->uzivatel->update($values->slouceny_uzivatel, array('TypClenstvi_id'=>1));
            
            //zalogovat udalost ke sloucenemu
            $log = array();
            $log[] = array(
                    'sloupec'=>'Uzivatel.id',
                    'puvodni_hodnota'=>NULL,
                    'nova_hodnota'=>'sloučen pod '. $values->Uzivatel_id,
                    'akce'=>'U'
                );
            $this->log->loguj('Uzivatel', $values->slouceny_uzivatel, $log);
            
            $this->flashMessage('Uživatelé byli sloučeni.');  
            
            $this->redirect('Uzivatel:edit', array('id'=>$values->Uzivatel_id)); 
            
        }       
        
    	return true;
    }
}
