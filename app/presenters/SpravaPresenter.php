<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Nette\Mail\Message,
    Nette\Utils\Strings,
    Nette\Mail\SendmailMailer,
    Tracy\Debugger;
    
use Nette\Forms\Controls\SubmitButton;
/**
 * Sprava presenter.
 */
class SpravaPresenter extends BasePresenter
{  
    private $spravceOblasti; 
    private $cestneClenstviUzivatele;  
    private $platneCC;
    private $uzivatel;
    private $log;
    private $ap;
    public $oblast;
    private $ipAdresa;
    private $sloucenyUzivatel;
    private $uzivatelskeKonto;
    private $prichoziPlatba;
    private $odchoziPlatba;
    private $stavBankovnihoUctu;

    function __construct(Model\SloucenyUzivatel $slUzivatel, Model\SpravceOblasti $sob, Model\StavBankovnihoUctu $stavuctu, Model\PrichoziPlatba $platba, Model\OdchoziPlatba $odchplatba, Model\UzivatelskeKonto $konto, Model\Oblast $ob, Model\CestneClenstviUzivatele $cc, Model\cc $actualCC, Model\Uzivatel $uzivatel, Model\Log $log, Model\AP $ap, Model\IPAdresa $ipAdresa) {
        $this->cestneClenstviUzivatele = $cc;
        $this->platneCC = $actualCC;
    	$this->uzivatel = $uzivatel;
        $this->log = $log;
        $this->ap = $ap;
        $this->oblast = $ob;
        $this->ipAdresa = $ipAdresa; 
        $this->sloucenyUzivatel = $slUzivatel; 
        $this->uzivatelskeKonto = $konto; 
        $this->prichoziPlatba = $platba;  
        $this->odchoziPlatba = $odchplatba; 
        $this->stavBankovnihoUctu = $stavuctu;
        $this->spravceOblasti = $sob;
    }
    
    public function actionLogout() {
        $this->getUser()->logout();
        header("Location: https://userdb.hkfree.org/Shibboleth.sso/Logout?return=https://idp.hkfree.org/idp/logout?return=http://www.hkfree.org");
        die();
    }

    public function renderNastroje()
    {
    	$this->template->canApproveCC = $this->getUser()->isInRole('VV');
        $this->template->canCreateArea = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->canSeePayments = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
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
        $grid->addColumnText('typcc', 'Typ CC')->setSortable()->setFilterText()->setSuggestion();
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
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
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
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
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
  
        $this->template->u1money_act = ($u1->money_aktivni == 1) ? "ANO" : "NE";
        $this->template->u1money_dis = ($u1->money_deaktivace == 1) ? "ANO" : "NE";
        $posledniPlatba = $u1->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
        if($posledniPlatba->count() > 0)
            {
                $posledniPlatbaData = $posledniPlatba->fetch();
                $this->template->u1money_lastpay = ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
            }
            else
            {
                $this->template->u1money_lastpay = "?";
            }
        $posledniAktivace = $u1->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
        if($posledniAktivace->count() > 0)
            {
                $posledniAktivaceData = $posledniAktivace->fetch();
                $this->template->u1money_lastact = ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
            }
            else
            {
                $this->template->u1money_lastact = "?";
            }
        $stavUctu = $u1->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
        if($u1->kauce_mobil > 0)
        {
            $this->template->u1money_bal = ($stavUctu - $uzivatel->kauce_mobil) . ' (kauce: ' . $uzivatel->kauce_mobil . ')';
        }
        else{
            $this->template->u1money_bal = $stavUctu;
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
             
        $this->template->u2money_act = ($u2->money_aktivni == 1) ? "ANO" : "NE";
        $this->template->u2money_dis = ($u2->money_deaktivace == 1) ? "ANO" : "NE";
        $posledniPlatba2 = $u2->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
        if($posledniPlatba2->count() > 0)
            {
                $posledniPlatbaData2 = $posledniPlatba2->fetch();
                $this->template->u2money_lastpay = ($posledniPlatbaData2->datum == null) ? "NIKDY" : ($posledniPlatbaData2->datum->format('d.m.Y') . " (" . $posledniPlatbaData2->castka . ")");
            }
            else
            {
                $this->template->u2money_lastpay = "?";
            }
        $posledniAktivace2 = $u2->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
        if($posledniAktivace2->count() > 0)
            {
                $posledniAktivaceData2 = $posledniAktivace2->fetch();
                $this->template->u2money_lastact = ($posledniAktivaceData2->datum == null) ? "NIKDY" : ($posledniAktivaceData2->datum->format('d.m.Y') . " (" . $posledniAktivaceData2->castka . ")");
            }
            else
            {
                $this->template->u2money_lastact = "?";
            }
        $stavUctu2 = $u2->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
        if($u2->kauce_mobil > 0)
        {
            $this->template->u2money_bal = ($stavUctu2 - $uzivatel->kauce_mobil) . ' (kauce: ' . $uzivatel->kauce_mobil . ')';
        }
        else{
            $this->template->u2money_bal = $stavUctu2;
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
    
    public function renderPlatbycu()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->cu = "";
    }
    
    public function renderNesparovane()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }
    
    protected function createComponentPaymentgrid($name)
    {
    	$id = $this->getParameter('type');
        
        //\Tracy\Dumper::dump($search);

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('payment_export');
        
        $prichoziPlatby = $this->prichoziPlatba->getPrichoziPlatby();
        
        $grid->setModel($prichoziPlatby);
        
    	$grid->setDefaultPerPage(25);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum' => 'DESC'));
        
        /*$presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter){  
                if($item->PrichoziPlatba_id)
                {
                    $tr->onclick = "window.location='".$presenter->link('Uzivatel:platba', array('id'=>$item->PrichoziPlatba_id))."'";
                }
                return $tr;
            });*/
                        
    	/*$grid->addColumnText('Uzivatel_id', 'UID')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id'=>$item->Uzivatel_id)))
            ->title($item->Uzivatel_id)
            ->setText($item->Uzivatel_id);})->setSortable();*/
          
        $grid->addColumnDate('datum', 'Datum')->setSortable()->setFilterText();
        $grid->addColumnText('cislo_uctu', 'Číslo účtu')->setSortable()->setFilterText();
        $grid->addColumnText('vs', 'VS')->setSortable()->setFilterText();
        $grid->addColumnText('ss', 'SS')->setSortable()->setFilterText();
        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();   
        $grid->addColumnText('nazev_uctu', 'Název účtu')->setSortable()->setFilterText();  
        $grid->addColumnText('zprava_prijemci', 'Zpráva pro příjemce')->setSortable()->setFilterText(); 
        $grid->addColumnText('kod_cilove_banky', 'Cílová banka')->setSortable()->setFilterText(); 
        $grid->addColumnText('identifikace_uzivatele', 'Identifikace')->setSortable()->setFilterText(); 
        $grid->addColumnText('info_od_banky', 'Info banky')->setSortable()->setFilterText(); 
        
        
        $grid->addColumnText('TypPrichoziPlatby_id', 'Typ')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->TypPrichoziPlatby_id)
                    ->setTitle($item->TypPrichoziPlatby->text)
                    ->setText($item->TypPrichoziPlatby->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
    }
    
    protected function createComponentAccountgrid($name)
    {
        //\Tracy\Dumper::dump($search);

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('account_export');
        
        $seznamTransakci = $this->uzivatelskeKonto->getSeznamNesparovanych();
        
        $grid->setModel($seznamTransakci);
        
    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum' => 'DESC', 'id' => 'DESC'));
        
        $presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter){  
                if($item->PrichoziPlatba_id)
                {
                    $tr->onclick = "window.location='".$presenter->link('Uzivatel:platba', array('id'=>$item->PrichoziPlatba_id))."'";
                }
                return $tr;
            });
   
        $grid->addColumnText('PrichoziPlatba_id', 'Akce')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Sprava:prevod', array('id'=>$item->PrichoziPlatba_id)))
            ->title("Převést")
            ->setText("Převést");});    
            
        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();
        
        $grid->addColumnDate('datum', 'Datum')->setSortable()->setFilterText();
        
        $grid->addColumnText('TypPohybuNaUctu_id', 'Typ')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->TypPohybuNaUctu_id)
                    ->setTitle($item->TypPohybuNaUctu->text)
                    ->setText($item->TypPohybuNaUctu->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
        
        $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->poznamka;
                $el->setText(Strings::truncate($item->poznamka, 20, $append='…'));
                return $el;
                })->setSortable()->setFilterText();
                
        $grid->addColumnText('cu', 'Číslo účtu')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->cislo_uctu)
                    ->setTitle($item->PrichoziPlatba->cislo_uctu)
                    ->setText($item->PrichoziPlatba->cislo_uctu)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
            
        $grid->addColumnText('vs', 'VS')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->vs)
                    ->setTitle($item->PrichoziPlatba->vs)
                    ->setText($item->PrichoziPlatba->vs)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
            
        $grid->addColumnText('ss', 'SS')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->ss)
                    ->setTitle($item->PrichoziPlatba->ss)
                    ->setText($item->PrichoziPlatba->ss)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
            
        $grid->addColumnText('nazev_uctu', 'Název účtu')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->nazev_uctu)
                    ->setTitle($item->PrichoziPlatba->nazev_uctu)
                    ->setText($item->PrichoziPlatba->nazev_uctu)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
        
        $grid->addColumnText('zprava_prijemci', 'Zpráva')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->zprava_prijemci)
                    ->setTitle($item->PrichoziPlatba->zprava_prijemci)
                    ->setText($item->PrichoziPlatba->zprava_prijemci)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
        
        $grid->addColumnText('info_od_banky', 'Info banky')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->info_od_banky)
                    ->setTitle($item->PrichoziPlatba->info_od_banky)
                    ->setText($item->PrichoziPlatba->info_od_banky)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
    }
    
    public function renderPrevod()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentPrevodForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "prevodForm");
    	$form->addHidden('id');

        $form->addText('PrichoziPlatba_id', 'ID příchozí platby', 70)->setDisabled(TRUE);
        $form->addText('PrichoziPlatba_cu', 'Z č.ú.', 70)->setDisabled(TRUE);
        $form->addText('PrichoziPlatba_vs', 'Příchozí VS', 70)->setDisabled(TRUE);
        $form->addText('PrichoziPlatba_ss', 'Příchozí SS', 70)->setDisabled(TRUE);
        $form->addText('castka', 'Příchozí částka k převodu', 70)->setDisabled(TRUE);
        
        $form->addText('Uzivatel_prev', 'ID původního uživatele', 50)->setDisabled(TRUE);
        
        $form->addText('Uzivatel_id', 'ID správného uživatele', 50)->setRequired('Zadejte UID cílového uživatele');
        $form->addTextArea('poznamka', 'Poznámka', 72, 10);

    	$form->addSubmit('send', 'Převést')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'prevodFormSucceded');

    	// pokud editujeme, nacteme existujici
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $id = $this->getParameter('id');
            $pPlatba = $this->prichoziPlatba->getPrichoziPlatba($id);
            $posledniPohyb = $this->uzivatelskeKonto->getUzivatelskeKontoByPrichoziPlatba($id);
            
            if($posledniPohyb) {
                $form->setDefaults(array(
                        'Uzivatel_prev' => $posledniPohyb->Uzivatel_id
                    ));
            }
            
            if($pPlatba) {
                //$form->setValues($user);
                $form->setDefaults(array(
                        'PrichoziPlatba_id' => $pPlatba->id,
                        'PrichoziPlatba_cu' => $pPlatba->cislo_uctu,
                        'PrichoziPlatba_vs' => $pPlatba->vs,
                        'PrichoziPlatba_ss' => $pPlatba->ss,
                        'castka' => $pPlatba->castka,
                        'poznamka' => 'Chybná platba.'
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function prevodFormSucceded($form, $values) {

        $id = $this->getParameter('id');
        $pPlatba = $this->prichoziPlatba->getPrichoziPlatba($id);
        $posledniPohyb = $this->uzivatelskeKonto->getUzivatelskeKontoByPrichoziPlatba($id);

        if($pPlatba) {
            
            $targetUID = $values->Uzivatel_id;
            
            $values->poznamka = $values->poznamka.' Převod na UID:['.$targetUID.']';
            if($posledniPohyb) {
                $values->Uzivatel_id = $posledniPohyb->Uzivatel_id;
                $values->TypPohybuNaUctu_id = 11;
            }
            else
            {
               $values->Uzivatel_id = NULL; 
               $values->TypPohybuNaUctu_id = 10;
            }
            $values->PrichoziPlatba_id = $pPlatba->id;
            $values->datum = new Nette\Utils\DateTime;
            $values->zmenu_provedl = $this->getUser()->getIdentity()->getId();            
            $values->castka = -$pPlatba->castka;

            if(empty($values->id)) {
                $this->uzivatelskeKonto->insert($values);
                $values->castka = $pPlatba->castka;
                $values->TypPohybuNaUctu_id = 1;
                $values->Uzivatel_id = $targetUID;
                $this->uzivatelskeKonto->insert($values);
                $this->flashMessage('Platba převedena.');            
            }

            $this->redirect('Sprava:nastroje'); 
        }
        else
        {
            $this->flashMessage('Nelze převést neexistující platbu!'); 
        }
        
    	return true;
    }
    
    public function renderOdchoziplatby()
    {
        
    }
    
    protected function createComponentOdchplatby($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('op_export');
        
        $grid->setModel($this->odchoziPlatba->getOdchoziPlatby());
        
    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('datum' => 'DESC'));
         
    	$grid->addColumnText('datum', 'Datum dokladu')->setSortable()->setFilterText();
        $grid->addColumnText('firma', 'Firma')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('popis', 'Popis')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('typ', 'Typ')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('kategorie', 'Kategorie')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('datum_platby', 'Datum platby')->setSortable()->setFilterText()->setSuggestion();

    }
    
    public function renderUcty()
    {
        
    }
    
    protected function createComponentStavyuctu($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        
        $grid->setModel($this->stavBankovnihoUctu->getAktualniStavyBankovnihoUctu());
        
    	$grid->setDefaultPerPage(10);
    	$grid->setDefaultSort(array('datum' => 'DESC'));
         
    	$grid->addColumnDate('datum', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE);   
        $grid->addColumnText('BankovniUcet_id', 'Bankovní účet')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->BankovniUcet->text)
                    ->setText($item->BankovniUcet->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            });
        $grid->addColumnText('popis', 'Popis')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->BankovniUcet->popis)
                    ->setText($item->BankovniUcet->popis)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            });
        $grid->addColumnNumber('castka', 'Částka', 2, '.', ' ');

    }
    
    public function renderSms()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }
    
    protected function createComponentSmsForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "smsForm");
    	$form->addHidden('id');

        $form->addSelect('komu', 'Příjemce', array(0=>'SO',1=>'ZSO'))->setDefaultValue(0);
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'smsFormSucceded');

    	return $form;
    }
    
    public function smsFormSucceded($form, $values) {

        if($values->komu == 0)
        {
           $sos = $this->spravceOblasti->getSO();
        }
        else
        {
          $sos = $this->spravceOblasti->getZSO();
        }
        
        foreach($sos as $so)
        {
            $tl = $so->Uzivatel->telefon;
            if(!empty($tl) && $tl!='missing')
            {
                $validni[]=$tl; 
            }
        }
        $tls = join(",",array_values($validni));
        
        $locale = 'cs_CZ.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
        $command = escapeshellcmd('python /var/www/cgi/smsbackend.py -a https://aweg3.maternacz.com -l hkf'.$this->getUser()->getIdentity()->getId().'-'.$this->getUser()->getIdentity()->nick.':'.base64_decode($_SERVER['initials']).' -d '.$tls.' "'.$values->message.'"');
        $output = shell_exec($command);
        
        $this->flashMessage('SMS byly odeslány. Output: ' . $output);
        
    	$this->redirect('Sprava:nastroje', array('id'=>null)); 
    	return true;
    }
}
