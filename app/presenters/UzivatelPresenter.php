<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Tracy\Debugger,
    Nette\Mail\Message,
    Nette\Utils\Validators,
    Nette\Mail\SendmailMailer,
    PdfResponse\PdfResponse;
    
use Nette\Forms\Controls\SubmitButton;
/**
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{  
    private $spravceOblasti; 
    private $cestneClenstviUzivatele;  
    private $typClenstvi;
    private $typCestnehoClenstvi;
    private $typPravniFormyUzivatele;
    private $typSpravceOblasti;
    private $zpusobPripojeni;
    private $technologiePripojeni;
    private $uzivatel;
    private $ipAdresa;
    private $ap;
    private $typZarizeni;
    private $log;
    private $subnet;

    function __construct(Model\Subnet $subnet, Model\SpravceOblasti $prava, Model\CestneClenstviUzivatele $cc, Model\TypSpravceOblasti $typSpravce, Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\TypCestnehoClenstvi $typCestnehoClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\TechnologiePripojeni $technologiePripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni, Model\Log $log) {
    	$this->spravceOblasti = $prava;
        $this->cestneClenstviUzivatele = $cc;
        $this->typSpravceOblasti = $typSpravce;
        $this->typClenstvi = $typClenstvi;
        $this->typCestnehoClenstvi = $typCestnehoClenstvi;
        $this->typPravniFormyUzivatele = $typPravniFormyUzivatele;
    	$this->zpusobPripojeni = $zpusobPripojeni;
        $this->technologiePripojeni = $technologiePripojeni;
    	$this->uzivatel = $uzivatel;
    	$this->ipAdresa = $ipAdresa;  
    	$this->ap = $ap;
    	$this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->subnet = $subnet;
    }
  
    public function actionExportandsendregform() {
        if($this->getParam('id'))
        {
        
        }
    }
    
    public function actionExportregform() {
      if($this->getParam('id'))
    	{
    	    if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {

            $today = getdate();
            $dayinmonth = $today["mday"];
            $numofdaysinmonth = cal_days_in_month(CAL_GREGORIAN, $today["mon"], $today["year"]);
            
            if($dayinmonth < 17)
            {
                $prvniplatba="17.".$today["mon"].".".$today["year"];
            }
            else if ($dayinmonth <= ($numofdaysinmonth-7))
            {
              $prvniplatba="co nejdříve (do konce měsíce)";
            }
            else
            {
              $platit_d = $today["mday"]+7;
              $platit_m = $today["mon"];
              $platit_y = $today["year"];
              if ( $platit_d > $numofdaysinmonth ) { $platit_d-=$numofdaysinmonth; $platit_m++; }
              if ( $platit_m > 12 ) { $platit_m = 1; $platit_y++; }
              $prvniplatba="co nejdříve, vaše členství je bezplatné do $platit_d.$platit_m.$platit_y";
            }
            
            $aj = array("January","February","March","April","May","June","July","August","September","October","November","December");
            $cz = array("leden","únor","březen","duben","květen","červen","červenec","srpen","září","říjen","listopad","prosinec");
            $pristimesic = str_replace($aj, $cz, date("F", strtotime("+1 month")));

            $rtfdata = file_get_contents("./template/evidence.rtf", true);
            
            $rtfdata = str_replace("--forma--", iconv("UTF-8","windows-1250",$uzivatel->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id')->text), $rtfdata);
            $rtfdata = str_replace("--firma--", iconv("UTF-8","windows-1250",$uzivatel->firma_nazev), $rtfdata);
            $rtfdata = str_replace("--ico--", $uzivatel->firma_ico, $rtfdata);
                        
            $rtfdata = str_replace("--jmeno--", iconv("UTF-8","windows-1250",$uzivatel->jmeno . " " . $uzivatel->prijmeni), $rtfdata);
            $rtfdata = str_replace("--id--", $uzivatel->id, $rtfdata);
            $rtfdata = str_replace("--nick--", iconv("UTF-8","windows-1250",$uzivatel->nick), $rtfdata);
            $rtfdata = str_replace("--heslo--", iconv("UTF-8","windows-1250",$uzivatel->heslo), $rtfdata);
            $rtfdata = str_replace("--email--", iconv("UTF-8","windows-1250",$uzivatel->email), $rtfdata);
            $rtfdata = str_replace("--mobil--", $uzivatel->telefon, $rtfdata);
            $rtfdata = str_replace("--cisloclenskekarty--", $uzivatel->cislo_clenske_karty, $rtfdata);
            $rtfdata = str_replace("--adresa1--", iconv("UTF-8","windows-1250",$uzivatel->ulice_cp) . ", " . iconv("UTF-8","windows-1250",$uzivatel->mesto) . ", " . $uzivatel->psc, $rtfdata);
            $rtfdata = str_replace("--typ--", iconv("UTF-8","windows-1250",$uzivatel->TypClenstvi->text), $rtfdata);
            $rtfdata = str_replace("--ip4--", join(",",array_values($uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'))), $rtfdata);
            $rtfdata = str_replace("--oblast--", iconv("UTF-8","windows-1250",$uzivatel->Ap->Oblast->jmeno), $rtfdata);
            $oblastid = $uzivatel->Ap->Oblast->id; 
            $rtfdata = str_replace("--emailoblasti--", "oblast$oblastid@hkfree.org", $rtfdata);
            $rtfdata = str_replace("--pristimesic--", iconv("UTF-8","windows-1250",$pristimesic), $rtfdata);
            $rtfdata = str_replace("--prvniplatba--", iconv("UTF-8","windows-1250",$prvniplatba), $rtfdata);
            
            $this->sendResponse(new Model\ContentDownloadResponse($rtfdata, "hkfree-registrace-$uzivatel->id.rtf"));
    	    }
    	}
    }
    
    public function actionExportPdf() {
      if($this->getParam('id'))
    	{
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
                $template = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
                $template->oblast = $uzivatel->Ap->Oblast->jmeno;
                $oblastid = $uzivatel->Ap->Oblast->id; 
                $template->oblastemail = "oblast$oblastid@hkfree.org";
                $template->jmeno = $uzivatel->jmeno;
                $template->prijmeni = $uzivatel->prijmeni;
                $template->forma = $uzivatel->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id')->text;
                $template->firma = $uzivatel->firma_nazev;
                $template->nick = $uzivatel->nick;
                $template->uid = $uzivatel->id;
                $template->heslo = $uzivatel->heslo;
                $template->email = $uzivatel->email;
                $template->telefon = $uzivatel->telefon;
                $template->ulice = $uzivatel->ulice_cp;
                $template->mesto = $uzivatel->mesto;
                $template->psc = $uzivatel->psc;
                $template->clenstvi = $uzivatel->TypClenstvi->text;
                $ipadrs = $uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa');
                foreach($ipadrs as $ip)
                {
                    $subnets = $this->subnet->getSubnetOfIP($ip);
                    if(count($subnets) == 1) {
                        $subnet = $subnets->fetch();
                        if(empty($subnet->subnet)) {
                            $out[] = array('ip' => $ip, 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi'); 
                        } elseif( empty($subnet->gateway)) {
                            $out[] = array('ip' => $ip, 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi'); 
                        } else {
                            list($network, $cidr) = explode("/", $subnet->subnet);
                            $out[] = array('ip' => $ip, 'subnet' => $subnet->subnet, 'gateway' => $subnet->gateway, 'mask' => $this->subnet->CIDRToMask($cidr));  
                        }
                    } else {
                        $out[] = array('ip' => $ip, 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi'); 
                    }
                }

                $template->ips = $out;
                $pdf = new PDFResponse($template);
                $pdf->pageOrientaion = PDFResponse::ORIENTATION_PORTRAIT;
                $pdf->pageFormat = "A4";
                $pdf->pageMargins = "5,5,5,5,20,60";
                $pdf->documentTitle = "hkfree-registrace-".$this->getParam('id');
                $pdf->documentAuthor = "hkfree.org z.s.";

                $this->sendResponse($pdf);    	  
            }
        }
    }

    public function renderEdit()
    {
        if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
    		    $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser());
    	    }
	        else
          {
            $this->template->canViewOrEdit = true;
          }
    }

    protected function createComponentUzivatelForm() {
    	$typClenstvi = $this->typClenstvi->getTypyClenstvi()->fetchPairs('id','text');
        $typPravniFormy = $this->typPravniFormyUzivatele->getTypyPravniFormyUzivatele()->fetchPairs('id','text');
    	$zpusobPripojeni = $this->zpusobPripojeni->getZpusobyPripojeni()->fetchPairs('id','text');
        $technologiePripojeni = $this->technologiePripojeni->getTechnologiePripojeni()->fetchPairs('id','text');

    	$aps = $this->oblast->formatujOblastiSAP($this->oblast->getSeznamOblasti());
        
        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getUser()->getIdentity()->getId());
        if (count($oblastiSpravce) > 0) {
            $aps0 = $this->oblast->formatujOblastiSAP($oblastiSpravce);
            $aps = $aps0 + $aps;
        }
        //\Tracy\Dumper::dump($aps);
    
    	$form = new Form($this, 'uzivatelForm');
    	$form->addHidden('id');
        $form->addSelect('Ap_id', 'Oblast - AP', $aps);
    	$form->addSelect('TypPravniFormyUzivatele_id', 'Právní forma', $typPravniFormy)->addRule(Form::FILLED, 'Vyberte typ právní formy');
        $form->addText('firma_nazev', 'Název firmy', 30)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::EQUAL, 2)->setRequired('Zadejte název firmy');
        $form->addText('firma_ico', 'IČO', 8)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::EQUAL, 2)->setRequired('Zadejte IČ');
        //http://phpfashion.com/jak-overit-platne-ic-a-rodne-cislo
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno');
    	$form->addText('prijmeni', 'Přijmení', 30)->setRequired('Zadejte příjmení');
    	$form->addText('nick', 'Nick (přezdívka)', 30)->setRequired('Zadejte nickname');
    	$form->addText('email', 'Email', 30)->setRequired('Zadejte email')->addRule(Form::EMAIL, 'Musíte zadat platný email');
        $form->addText('email2', 'Sekundární email', 30)->addCondition(Form::FILLED)->addRule(Form::EMAIL, 'Musíte zadat platný email');
    	$form->addText('telefon', 'Telefon', 30)->setRequired('Zadejte telefon');
        $form->addText('cislo_clenske_karty', 'Číslo členské karty', 30);
    	$form->addText('ulice_cp', 'Adresa (ulice a čp)', 30)->setRequired('Zadejte ulici a čp');
        $form->addText('mesto', 'Adresa (město)', 30)->setRequired('Zadejte město');
        $form->addText('psc', 'Adresa (psč)', 5)->setRequired('Zadejte psč')->addRule(Form::INTEGER, 'PSČ musí být číslo');
    	$form->addText('rok_narozeni', 'Rok narození',30);	
    	$form->addSelect('TypClenstvi_id', 'Členství', $typClenstvi)->addRule(Form::FILLED, 'Vyberte typ členství');
        $form->addTextArea('poznamka', 'Poznámka', 50, 12);	
    	$form->addSelect('TechnologiePripojeni_id', 'Technologie připojení', $technologiePripojeni)->addRule(Form::FILLED, 'Vyberte technologii připojení');
        $form->addSelect('index_potizisty', 'Index potížisty', array(0=>0,1=>1,2=>2,3=>3,4=>4,5=>5))->setDefaultValue(0);
    	$form->addSelect('ZpusobPripojeni_id', 'Způsob připojení', $zpusobPripojeni)->addRule(Form::FILLED, 'Vyberte způsob připojení');
            
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
    	$form->onSuccess[] = array($this, 'uzivatelFormSucceded');
        $form->onValidate[] = array($this, 'validateUzivatelForm');
    
        $form->setDefaults(array(
            'TypClenstvi_id' => 3,
            'TypPravniFormyUzivatele_id' => 1,
        ));
    
    	// pokud editujeme, nacteme existujici ipadresy
    	$submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
    	    $values = $this->uzivatel->getUzivatel($this->getParam('id'));
    	    if($values) {
                foreach($values->related('IPAdresa.Uzivatel_id') as $ip_id => $ip_data) {
                    $form["ip"][$ip_id]->setValues($ip_data);
                }
                $form->setValues($values);
    	    }
    	}                
    
    	return $form;
    }
    
    public function validateUzivatelForm($form)
    {
        //pole uz nejsou UNIQUE ... postrada smysl ... leda formou varovani
        /*$values = $form->getValues();

        $duplMail = $this->uzivatel->getDuplicateEmailArea($values->email, $values->id);
        
        if ($duplMail) {
            $form->addError('Tento email již v DB existuje v oblasti: ' . $duplMail);
        }
        
        if(!empty($values->email2)) {
            $duplMail2 = $this->uzivatel->getDuplicateEmailArea($values->email2, $values->id);

            if ($duplMail2) {
                $form->addError('Tento email již v DB existuje v oblasti: ' . $duplMail2);
            }
        }
        
        $duplPhone = $this->uzivatel->getDuplicatePhoneArea($values->telefon, $values->id);
        
        if ($duplPhone) {
            $form->addError('Tento telefon již v DB existuje v oblasti: ' . $duplPhone);
        }*/
    }
    
    public function uzivatelFormSucceded($form, $values) {
        $log = array();
    	$idUzivatele = $values->id;
    	$ips = $values->ip;
    	unset($values["ip"]);
    
        if (empty($values->cislo_clenske_karty)) {
                $values->cislo_clenske_karty = null;
            }
        if (empty($values->firma_nazev)) {
                $values->firma_nazev = null;
            }
        if (empty($values->firma_ico)) {
                $values->firma_ico = null;
            }
        if (empty($values->email2)) {
                $values->email2 = null;
            }
        if (empty($values->poznamka)) {
                $values->poznamka = null;
            }
        
    	// Zpracujeme nejdriv uzivatele
    	if(empty($values->id)) {
            $values->zalozen = new Nette\Utils\DateTime;
            $values->heslo = $this->uzivatel->generateStrongPassword();
            $values->id = $this->uzivatel->getNewID();
            $idUzivatele = $this->uzivatel->insert($values)->id;
            $this->log->logujInsert($values, 'Uzivatel', $log);
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);
    	    $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($olduzivatel, $values, 'Uzivatel', $log);
        }
        
    	// Potom zpracujeme IPcka
    	$newUserIPIDs = array();
    	foreach($ips as $ip)
    	{
    	    $ip->Uzivatel_id = $idUzivatele;
    	    $idIp = $ip->id;
            
            if (empty($ip->ip_adresa)) {
                $ip->ip_adresa = null;
            }
            if (empty($ip->hostname)) {
                $ip->hostname = null;
            }
            if (empty($ip->mac_adresa)) {
                $ip->mac_adresa = null;
            }
            if (empty($ip->popis)) {
                $ip->popis = null;
            }
            if (empty($ip->login)) {
                $ip->login = null;
            }
            if (empty($ip->heslo)) {
                $ip->heslo = null;
            }

            if(empty($ip->id)) {
                $idIp = $this->ipAdresa->insert($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa['.$idIp.']', $log);               
            } else {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa['.$idIp.']', $log);
            }    
            $newUserIPIDs[] = intval($idIp);
    	}
    
    	// A tady smazeme v DB ty ipcka co jsme smazali
    	$userIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
    	$toDelete = array_values(array_diff($userIPIDs, $newUserIPIDs));
        if(!empty($toDelete)) {
            foreach($toDelete as $idIp) {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->log->logujDelete($oldip, 'IPAdresa['.$idIp.']', $log);
            }
        }
        $this->ipAdresa->deleteIPAdresy($toDelete);
    	
        $this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }

    protected function createComponentGrid($name)
    {
        $canViewOrEdit = false;
    	$id = $this->getParameter('id');
        $money = $this->getParameter('money', false);
        
        $search = $this->getParameter('search', false);
        //\Tracy\Dumper::dump($search);
        
        if($money) {
            $money_uid = $this->context->parameters["money"]["login"];
            $money_heslo = $this->context->parameters["money"]["password"];
            $money_client = new \SoapClient(
                'https://' . $money_uid . ':' . $money_heslo . '@money.hkfree.org/wsdl/moneyAPI.wsdl',
                array(
                    'login'         => $money_uid,
                    'password'      => $money_heslo,
                    'trace'         => 1,
                )
            );
        }
        
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('user_export');
        if($id){  
            $grid->setModel($this->uzivatel->getSeznamUzivateluZAP($id));
            $canViewOrEdit = $this->ap->canViewOrEditAP($id, $this->getUser());
            if ($money) {
                $money_callresult = $money_client->hkfree_money_userGetInfo(implode(",", $this->uzivatel->getSeznamUIDUzivateluZAP($id)));
            }
        } else {
            
            if($search)
            {
                $grid->setModel($this->uzivatel->findUserByFulltext($search,$this->getUser()));
            }
            else
            {
                $grid->setModel($this->uzivatel->getSeznamUzivatelu());
                $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
            }           
            
            if ($money) {
                $money_callresult = $money_client->hkfree_money_userGetInfo(implode(",", $this->uzivatel->getSeznamUIDUzivatelu()));
            }
            $grid->addColumnText('Ap_id', 'AP')->setCustomRender(function($item){
                  return $item->ref('Ap', 'Ap_id')->jmeno;
              })->setSortable();
        }
        
    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('zalozen' => 'ASC'));
    
    	$list = array('active' => 'bez zrušených', 'all' => 'včetně zrušených');
    	
        // pri fulltextu vyhledavat i ve zrusenych
        if($search)
        {
            $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
             ->setDefaultValue('all')
             ->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));
        }
        else
        {
          $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
             ->setDefaultValue('active')
             ->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));  
        }
        

        $ccref = $this->cestneClenstviUzivatele;
        if($money)
        {
            $grid->setRowCallback(function ($item, $tr) use ($ccref,$money_callresult){
                if($money_callresult[$item->id]->userIsActive->isActive != 1) {
                    $tr->class[] = 'neaktivni';
                    return $tr;
                }
                if ($ccref->getHasCC($item->id)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if($item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }            
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($ccref){
                if ($ccref->getHasCC($item->id)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if($item->TypClenstvi_id == 2)
                {
                    $tr->class[] = 'primarni';
                }
                if($item->TypClenstvi_id == 1)
                {
                    $tr->class[] = 'zrusene';
                }
                return $tr;
            });
        }
        
    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('nick', 'Nick')->setSortable()->setFilterText()->setSuggestion();
        
        if($canViewOrEdit) {
            $grid->addColumnText('TypPravniFormyUzivatele_id', 'PF')->setCustomRender(function($item){
                  return $item->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id')->text;
              })->setSortable()->setFilterSelect(array(
                              "" => "",
                              "1" => "FO",
                              "2" => "PO",
                          ));
            $grid->addColumnText('jmeno', 'Jméno')->setSortable()->setFilterText()->setSuggestion();
            $grid->addColumnText('prijmeni', 'Příjmení')->setSortable()->setFilterText()->setSuggestion();    	
            $grid->addColumnText('ulice_cp', 'Ulice')->setSortable()->setFilterText();
            $grid->addColumnEmail('email', 'E-mail')->setSortable()->setFilterText()->setSuggestion();
            $grid->addColumnText('telefon', 'Telefon')->setSortable()->setFilterText()->setSuggestion();
        }
        
    	$grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function($item){
            return join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function($item){
            $el = Html::el('span');
            $ipAdresy = $item->related('IPAdresa.Uzivatel_id');
            if($ipAdresy->count() > 0)
            {
              $el->title = join(", ",array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
              $el->setText($ipAdresy->fetch()->ip_adresa);
            }
            return $el;
        });
        
    	if($canViewOrEdit) {
            if($money) {
                $grid->addColumnText('act', 'Aktivní')->setColumn(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->userIsActive->isActive == 1) ? "ANO" : (($money_callresult[$item->id]->userIsActive->isActive == 0) ? "NE" : "?");
                })->setCustomRender(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->userIsActive->isActive == 1) ? "ANO" : (($money_callresult[$item->id]->userIsActive->isActive == 0) ? "NE" : "?");
                }); 
                
                $grid->addColumnText('deact', 'Deaktivace')->setColumn(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->userIsDisabled->isDisabled == 1) ? "ANO" : (($money_callresult[$item->id]->userIsDisabled->isDisabled == 0) ? "NE" : "?");
                })->setCustomRender(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->userIsDisabled->isDisabled == 1) ? "ANO" : (($money_callresult[$item->id]->userIsDisabled->isDisabled == 0) ? "NE" : "?");
                });  
                
                $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->GetLastPayment->LastPaymentDate == "null") ? "NIKDY" : (date("d.m.Y",strtotime($money_callresult[$item->id]->GetLastPayment->LastPaymentDate)) . " (" . $money_callresult[$item->id]->GetLastPayment->LastPaymentAmount . ")");
                })->setCustomRender(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->GetLastPayment->LastPaymentDate == "null") ? "NIKDY" : (date("d.m.Y",strtotime($money_callresult[$item->id]->GetLastPayment->LastPaymentDate)) . " (" . $money_callresult[$item->id]->GetLastPayment->LastPaymentAmount . ")");
                });   
                
                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->GetLastActivation->LastActivationDate == "null") ? "NIKDY" : (date("d.m.Y",strtotime($money_callresult[$item->id]->GetLastActivation->LastActivationDate)) . " (" . $money_callresult[$item->id]->GetLastActivation->LastActivationAmount . ")");
                })->setCustomRender(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->GetLastActivation->LastActivationDate == "null") ? "NIKDY" : (date("d.m.Y",strtotime($money_callresult[$item->id]->GetLastActivation->LastActivationDate)) . " (" . $money_callresult[$item->id]->GetLastActivation->LastActivationAmount . ")");
                }); 
                
                $grid->addColumnText('acc', 'Stav účtu')->setColumn(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->GetAccountBalance->GetAccountBalance > 0) ? $money_callresult[$item->id]->GetAccountBalance->GetAccountBalance : "?";
                })->setCustomRender(function($item) use ($money_callresult){
                    return ($money_callresult[$item->id]->GetAccountBalance->GetAccountBalance > 0) ? $money_callresult[$item->id]->GetAccountBalance->GetAccountBalance : "?";
                });
            }
            //$grid->addColumnText('wifi_user', 'Vlastní WI-FI')->setSortable()->setReplacement(array('2' => Html::el('b')->setText('ANO'),'1' => Html::el('b')->setText('NE')));
            $grid->addColumnText('poznamka', 'Poznámka')->setSortable()->setFilterText();
    	}
        
        $grid->addActionHref('show', 'Zobrazit')
                ->setIcon('eye-open');
            /*$grid->addActionHref('edit', 'Editovat')
                ->setIcon('pencil');*/
    }
    
    public function renderListall()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
        $this->template->money = $this->getParameter("money", false);
        $this->template->search = $this->getParameter('search', false);
    }
    
    public function renderList()
    {
        // otestujeme, jestli máme id APčka a ono existuje
    	if($this->getParameter('id') && $apt = $this->ap->getAP($this->getParameter('id')))
    	{
    	    $this->template->ap = $apt;
            $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());  
    	} else {
            $this->flashMessage("Chyba, AP s tímto ID neexistuje.", "danger");
            $this->redirect("Homepage:default", array("id"=>null)); // a přesměrujeme
    	}
        
        $this->template->money = $this->getParameter("money", false);
    }
    
    public function renderShow()
    {
    	if($this->getParam('id'))
    	{
            $uid = $this->getParam('id');
    	    if($uzivatel = $this->uzivatel->getUzivatel($uid))
    	    {
    		    $this->template->u = $uzivatel;
                
                $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id');
                
    		    $this->template->adresy = $this->ipAdresa->getIPTable($ipAdresy);
                                
                if($ipAdresy->count() > 0)
                {
                    $this->template->adresyline = join(", ",array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
                }
                else
                {
                    $this->template->adresyline = null;
                }
                $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser());
                $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uzivatel->id);
                //$this->template->logy = $this->log->getLogyUzivatele($uid);
    	    }
    	}
    }
    
    public function createComponentLogTable() {
        $control = new \App\Components\LogTable($this, $this->ipAdresa, $this->log);
        return $control;
    }
    
    public function renderEditrights()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
        $this->template->u = $this->uzivatel->getUzivatel($this->getParam('id'));
    }
    
    protected function createComponentUzivatelRightsForm() {
    	$typRole = $this->typSpravceOblasti->getTypySpravcuOblasti()->fetchPairs('id', 'text');
        $obl = $this->oblast->getSeznamOblasti()->fetchPairs('id', 'jmeno');
    
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "uzivatelRightsForm");
    	$form->addHidden('id');
            
        $data = $this->spravceOblasti;
    	$rights = $form->addDynamic('rights', function (Container $right) use ($data, $typRole, $obl) {
    	    $data->getRightsForm($right, $typRole, $obl);
    
    	    $right->addSubmit('remove', '– Odstranit oprávnění')
    		    ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
    		    ->setValidationScope(FALSE)
    		    ->addRemoveOnClick();
    	}, 0, false);
    
    	$rights->addSubmit('add', '+ Přidat další oprávnění')
    		   ->setAttribute('class', 'btn btn-success btn-xs btn-white')
    		   ->setValidationScope(FALSE)
    		   ->addCreateOnClick(TRUE);
    
    	$form->addSubmit('save', 'Uložit')
    		 ->setAttribute('class', 'btn btn-success btn-xs btn-white');
        
    	$form->onSuccess[] = array($this, 'uzivatelRightsFormSucceded');
    

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParam('id'));
    		foreach($user->related("SpravceOblasti.Uzivatel_id") as $rights_id => $rights_data) {
                $form["rights"][$rights_id]->setValues($rights_data);
    		}
            if($user) {
                $form->setValues($user);
    	    }
    	}                
    
    	return $form;
    }
    
    public function uzivatelRightsFormSucceded($form, $values) {
        $log = array();
    	$idUzivatele = $values->id;
    	$prava = $values->rights;
        
        $typRole = $this->typSpravceOblasti->getTypySpravcuOblasti()->fetchPairs('id', 'text');

    	// Zpracujeme prava
    	$newUserIPIDs = array();
    	foreach($prava as $pravo)
    	{
    	    $pravo->Uzivatel_id = $idUzivatele;
    	    $pravoId = $pravo->id;
            
            //osetreni aby prazdne pole od davalo null a ne 00-00-0000
            if(empty($pravo->od)) 
                $pravo->od = null; 
            if(empty($pravo->do)) 
                $pravo->do = null;
            
            $popisek = $this->spravceOblasti->getTypPravaPopisek($typRole[$pravo->TypSpravceOblasti_id], $pravo->Oblast_id);
            
            if(empty($pravo->id)) {
                $pravoId = $this->spravceOblasti->insert($pravo)->id;
                $novePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->log->logujInsert($novePravo, 'Pravo['.$popisek.']', $log);
            } else {
                $starePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->spravceOblasti->update($pravoId, $pravo);
                $novePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->log->logujUpdate($starePravo, $novePravo, 'Pravo['.$popisek.']', $log);
            }    
            $novaPravaID[] = intval($pravoId);
    	}
    
    	// A tady smazeme v DB ty prava co jsme smazali
    	$aktualniPravaID = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('SpravceOblasti.Uzivatel_id')->fetchPairs('id', 'id'));
    	$toDelete = array_values(array_diff($aktualniPravaID, $novaPravaID));
        if(!empty($toDelete)) {
            foreach($toDelete as $pravoId) {
                $starePravo = $this->spravceOblasti->getPravo($pravoId);
                $popisek = $this->spravceOblasti->getTypPravaPopisek($typRole[$starePravo->TypSpravceOblasti_id], $starePravo->Oblast_id);
                $this->log->logujDelete($starePravo, 'Pravo['.$popisek.']', $log);
            }
        }
        $this->spravceOblasti->deletePrava($toDelete);
    	
        $this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }

    public function renderEditcc()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParam('id'))->Ap_id, $this->getUser());
        $this->template->canApprove = $this->getUser()->isInRole('VV');
        $this->template->u = $this->uzivatel->getUzivatel($this->getParam('id'));
    }

    protected function createComponentUzivatelCCForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "uzivatelCCForm");
    	$form->addHidden('id');
        
        $typCC = $this->typCestnehoClenstvi->getTypCestnehoClenstvi()->fetchPairs('id','text');
        
        $data = $this->cestneClenstviUzivatele;
    	$rights = $form->addDynamic('rights', function (Container $right) use ($data, $typCC) {

            $right->addHidden('zadost_podal')->setAttribute('class', 'id ip');
            $right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
            $right->addHidden('id')->setAttribute('class', 'id ip');
            
            $right->addSelect('TypCestnehoClenstvi_id', 'Typ čestného členství', $typCC)->addRule(Form::FILLED, 'Vyberte typ čestného členství');
                  
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
                 
                 //$right->addCheckbox('schvaleno', 'Schváleno')->setAttribute('class', 'approve ip');
                 $schvalenoStates = array(
                    0 => 'Nerozhodnuto',
                    1 => 'Schváleno',
                    2 => 'Zamítnuto');
                 $right->addRadioList('schvaleno', 'Stav schválení: ', $schvalenoStates)
                         ->getSeparatorPrototype()->setName(NULL);
                 
                 $right->setDefaults(array(
                        'TypCestnehoClenstvi_id' => 0,
                    ));

    	}, 0, false);
    
        
        
    	$rights->addSubmit('add', '+ Přidat další období ČČ')
    		   ->setAttribute('class', 'btn btn-success btn-xs btn-white')
    		   ->setValidationScope(FALSE)
    		   ->addCreateOnClick(TRUE);
    
    	$form->addSubmit('save', 'Uložit')
    		 ->setAttribute('class', 'btn btn-success btn-xs btn-white');
        
    	$form->onSuccess[] = array($this, 'uzivatelCCFormSucceded');
    

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParam('id'));
    		foreach($user->related("CestneClenstviUzivatele.Uzivatel_id") as $rights_id => $rights_data) {
                $form["rights"][$rights_id]->setValues($rights_data);
    		}
            if($user) {
                $form->setValues($user);
    	    }
    	}                
    
    	return $form;
    }
    
    public function uzivatelCCFormSucceded($form, $values) {
        $log = array();
    	$idUzivatele = $values->id;
    	$prava = $values->rights;

    	// Zpracujeme prava
    	$newUserIPIDs = array();
    	foreach($prava as $pravo)
    	{
    	    $pravo->Uzivatel_id = $idUzivatele;
            $pravo->zadost_podal = $this->getUser()->getIdentity()->getId();
    	    $pravoId = $pravo->id;
            
            //osetreni aby prazdne pole od davalo null a ne 00-00-0000
            if(empty($pravo->plati_od)) $pravo->plati_od = null; 
            if(empty($pravo->plati_do)) $pravo->plati_do = null;
            if(empty($pravo->schvaleno)) $pravo->schvaleno = 0;
            
            if(empty($pravo->id)) {
                $pravoId = $this->cestneClenstviUzivatele->insert($pravo)->id;
                
                $mail = new Message;
                $mail->setFrom('UserDB <userdb@hkfree.org>')
                    ->addTo('vv@hkfree.org')
                    ->setSubject('Nová žádost o ČČ')
                    ->setBody("Dobrý den,\nbyla vytvořena nová žádost o ČČ.\nID:$pravo->Uzivatel_id\nPoznámka: $pravo->poznamka\n\nhttps://userdb.hkfree.org/userdb/sprava/schvalovanicc");

                $mailer = new SendmailMailer;
                $mailer->send($mail);
            } else {
                $starePravo = $this->cestneClenstviUzivatele->getCC($pravoId);
                $this->cestneClenstviUzivatele->update($pravoId, $pravo);
            }
    	}

        //$this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }
    
    public function renderEmail()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParam('id'))->Ap_id, $this->getUser());
        $this->template->u = $this->uzivatel->getUzivatel($this->getParam('id'));
    }

    protected function createComponentEmailForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "emailForm");
    	$form->addHidden('id');

        $form->addText('from', 'Odesílatel', 70)->setDisabled(TRUE);
        $form->addText('email', 'Příjemce', 70)->setDisabled(TRUE);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'emailFormSucceded');

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParam('id'));
            $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
            if($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        'from' => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',
                        'subject' => 'Zpráva od správce HKFree',
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function emailFormSucceded($form, $values) {
    	$idUzivatele = $values->id;
        
        $user = $this->uzivatel->getUzivatel($this->getParam('id'));
        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
        
        $mail = new Message;
        $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($user->email)
            ->setSubject($values->subject)
            ->setBody($values->message);

        $mailer = new SendmailMailer;
        $mailer->send($mail);
        
        $this->flashMessage('E-mail byl odeslán.');
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }
    
    public function renderEmailall()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
        $this->template->ap = $this->ap->getAP($this->getParam('id'));
    }

    protected function createComponentEmailallForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "emailallForm");
    	$form->addHidden('id');

        $form->addText('from', 'Odesílatel', 70)->setDisabled(TRUE);
        $form->addTextArea('email', 'Příjemce', 72, 20)->setDisabled(TRUE);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'emailallFormSucceded');

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $ap = $this->ap->getAP($this->getParam('id'));
            $emaily = $ap->related('Uzivatel.Ap_id')->fetchPairs('id', 'email');
            
            foreach($emaily as $email)
            {
                if(Validators::isEmail($email)){
                    $validni[]=$email;            
                }
            }
            $tolist = join(";",array_values($validni));
            
            $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
            if($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        'from' => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',
                        'email' => $tolist,
                        'subject' => 'HKFree - Zpráva od správce oblasti '.$ap->jmeno,
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function emailallFormSucceded($form, $values) {
    	$idUzivatele = $values->id;
        
        $ap = $this->ap->getAP($this->getParam('id'));
        $emaily = $ap->related('Uzivatel.Ap_id')->fetchPairs('id', 'email');
        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
        
        $mail = new Message;
        $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->setSubject($values->subject)
            ->setBody($values->message);
        
        //TODO: check if mail is valid
        foreach($emaily as $email)
        {
            if(Validators::isEmail($email)){
                $mail->addBcc($email);            
            }
        }
        
        $mailer = new SendmailMailer;
        $mailer->send($mail);
        
        $this->flashMessage('E-mail byl odeslán.');
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }
}
