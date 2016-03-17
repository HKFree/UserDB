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
    Nette\Utils\Strings,
    PdfResponse\PdfResponse,
    App\Components;
    
use Nette\Forms\Controls\SubmitButton;
/**
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{  
    /** @persistent */
    public $money = 0;
    
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
    private $sloucenyUzivatel;
    private $uzivatelskeKonto;
    private $prichoziPlatba;
    private $tabulkaUzivatelu;
    private $parameters;
    private $accountActivation;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;
    
    function __construct(Model\Parameters $parameters, Model\AccountActivation $accActivation, Model\UzivatelListGrid $ULGrid, Model\PrichoziPlatba $platba, Model\UzivatelskeKonto $konto, Model\SloucenyUzivatel $slUzivatel, Model\Subnet $subnet, Model\SpravceOblasti $prava, Model\CestneClenstviUzivatele $cc, Model\TypSpravceOblasti $typSpravce, Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\TypCestnehoClenstvi $typCestnehoClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\TechnologiePripojeni $technologiePripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni, Model\Log $log) {
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
        $this->sloucenyUzivatel = $slUzivatel; 
        $this->uzivatelskeKonto = $konto; 
        $this->prichoziPlatba = $platba;        
        $this->tabulkaUzivatelu = $ULGrid; 
        $this->parameters = $parameters;
        $this->accountActivation = $accActivation;
    }
    
    public function actionMoneyActivate() {
        $id = $this->getParameter('id');
        if($id)
        {
            if($this->accountActivation->activateAccount($this->getUser(), $id))
            {
                $this->flashMessage('Účet byl aktivován.');
            }
            
            $this->redirect('Uzivatel:show', array('id'=>$id));            
        }
    }
    
    public function actionMoneyReactivate() {
        $id = $this->getParameter('id');
        if($id)
        {
            $result = $this->accountActivation->reactivateAccount($this->getUser(), $id);
            if($result != '')
            {
                $this->flashMessage($result);
            }
            
            $this->redirect('Uzivatel:show', array('id'=>$id));  
        }
    }
    
    public function actionMoneyDeactivate() {
        $id = $this->getParameter('id');
        if($id)
        {
            if($this->accountActivation->deactivateAccount($this->getUser(), $id))
            {
                $this->flashMessage('Účet byl deaktivován.'); 
            }
            
            $this->redirect('Uzivatel:show', array('id'=>$id));  
        }
    }
    
    public function generatePdf($uzivatel)
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
        $template->heslo = $uzivatel->regform_downloaded_password_sent==0 ? $uzivatel->heslo : "-- nelze zpětně zjistit --";
        $template->email = $uzivatel->email;
        $template->telefon = $uzivatel->telefon;
        $template->ulice = $uzivatel->ulice_cp;
        $template->mesto = $uzivatel->mesto;
        $template->psc = $uzivatel->psc;
        $template->clenstvi = $uzivatel->TypClenstvi->text;
        $template->nthmesic = $uzivatel->ZpusobPripojeni_id==2 ? "třetího" : "prvního";
        $template->nthmesicname = $uzivatel->ZpusobPripojeni_id==2 ? $this->uzivatel->mesicName($uzivatel->zalozen,3) : $this->uzivatel->mesicName($uzivatel->zalozen,1);
        $template->nthmesicdate = $uzivatel->ZpusobPripojeni_id==2 ? $this->uzivatel->mesicDate($uzivatel->zalozen,2) : $this->uzivatel->mesicDate($uzivatel->zalozen,0);
        $ipadrs = $uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa');
        foreach($ipadrs as $ip) {
            $subnet = $this->subnet->getSubnetOfIP($ip);
            
            if(isset($subnet["error"])) {
                $errorText = 'subnet není v databázi';
                $out[] = array('ip' => $ip, 'subnet' => $errorText, 'gateway' => $errorText, 'mask' => $errorText); 
            } else {
                $out[] = array('ip' => $ip, 'subnet' => $subnet["subnet"], 'gateway' => $subnet["gateway"], 'mask' => $subnet["mask"]);
            }
        }
        
        if(count($ipadrs) == 0) {
            $out[] = array('ip' => 'není přidána žádná ip', 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi');                
        }
        $template->ips = $out;
        
        $pdf = new PDFResponse($template);
        $pdf->pageOrientation = PDFResponse::ORIENTATION_PORTRAIT;
        $pdf->pageFormat = "A4";
        $pdf->pageMargins = "5,5,5,5,20,60";
        $pdf->documentTitle = "hkfree-registrace-".$this->getParam('id');
        $pdf->documentAuthor = "hkfree.org z.s.";

        return $pdf;
    }
    
    public function mailPdf($pdf, $uzivatel)
    {
        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
        
        $mail = new Message;
        $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($uzivatel->email)
            ->addTo($so->email)
            ->setSubject('Registrační formulář člena hkfree.org z.s.')
            ->setBody('Dobrý den, zasíláme Vám registrační formulář. S pozdravem hkfree.org z.s.');

        $temp_file = tempnam(sys_get_temp_dir(), 'registrace');                
        $pdf->outputName = $temp_file;
        $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
        $pdf->send($this->getHttpRequest(), $this->getHttpResponse());
        $mail->addAttachment('hkfree-registrace-'.$uzivatel->id.'.pdf', file_get_contents($temp_file));

        $mailer = new SendmailMailer;
        $mailer->send($mail);
    }

    public function actionSendRegActivation() {
        if($this->getParam('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
                $hash = base64_encode($uzivatel->id.'-'.md5($this->context->parameters["salt"].$uzivatel->zalozen));
                $link = "https://moje.hkfree.org/uzivatel/confirm/".$hash;

                $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());        
                $mail = new Message;
                $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
                    ->addTo($uzivatel->email)
                    ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s.')
                    ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '. 
                                  'následující odkaz:<br><br><a href="'.$link.'">'.$link.'</a><br><br>'.
                                  'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                                  'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                                  'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                                  'S pozdravem hkfree.org z.s.');
                $mailer = new SendmailMailer;
                $mailer->send($mail);

                $mailso = new Message;
                $mailso->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
                    ->addTo($so->email)
                    ->setSubject('kopie - Žádost o potvrzení registrace člena hkfree.org z.s.')
                    ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '. 
                                  'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel.....<br><br>'.
                                  'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                                  'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                                  'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                                  'S pozdravem hkfree.org z.s.');
                $mailer->send($mailso);

                $this->flashMessage('E-mail s žádostí o potvrzení registrace byl odeslán.');

                $this->redirect('Uzivatel:show', array('id'=>$uzivatel->id));  	  
            }
        }
    }
    
    public function actionExportAndSendRegForm() {
        if($this->getParam('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
                $pdf = $this->generatePdf($uzivatel);

                $this->mailPdf($pdf, $uzivatel);
                
                $this->flashMessage('E-mail byl odeslán.');

                $this->redirect('Uzivatel:show', array('id'=>$uzivatel->id));  	  
            }
        }
    }
        
    public function actionExportPdf() {
      if($this->getParam('id'))
    	{
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
                $pdf = $this->generatePdf($uzivatel);
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
    
    public function renderConfirm()
    {
        if($this->getParam('id'))
        {
            list($uid, $hash) = explode('-', base64_decode($this->getParam('id')));
            
            if($uzivatel = $this->uzivatel->getUzivatel($uid))
    	    {
                if($uzivatel->regform_downloaded_password_sent==0 && $hash == md5($this->context->parameters["salt"].$uzivatel->zalozen))
                {
                    $pdf = $this->generatePdf($uzivatel);

                    $this->mailPdf($pdf, $uzivatel);
                }
    		    $this->template->stav = true;
    	    }
	        else
            {
              $this->template->stav = false;
            }
        }
        else {
            $this->template->stav = false;
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
        if(count($this->spravceOblasti->getOblastiSpravce($this->getParam('id'))) > 0)
        {
            $form->addCheckBox('publicPhone', 'Telefon je viditelný pro členy', 30)->setDefaultValue(true);
        }
        $form->addText('cislo_clenske_karty', 'Číslo členské karty', 30);
        $form->addText('kauce_mobil', 'Kauce na mobilní tarify', 30);
    	$form->addText('ulice_cp', 'Adresa (ulice a čp)', 30)->setRequired('Zadejte ulici a čp');
        $form->addText('mesto', 'Adresa (obec)', 30)->setRequired('Zadejte město');
        $form->addText('psc', 'Adresa (psč)', 5)->setRequired('Zadejte psč')->addRule(Form::INTEGER, 'PSČ musí být číslo');
    	$form->addText('rok_narozeni', 'Rok narození',30);	
    	$form->addSelect('TypClenstvi_id', 'Členství', $typClenstvi)->addRule(Form::FILLED, 'Vyberte typ členství');
        $form->addTextArea('poznamka', 'Poznámka', 50, 12);	
    	$form->addSelect('TechnologiePripojeni_id', 'Technologie připojení', $technologiePripojeni)->addRule(Form::FILLED, 'Vyberte technologii připojení');
        $form->addSelect('index_potizisty', 'Index potížisty', array(0=>0,1=>1,2=>2,3=>3,4=>4,5=>5))->setDefaultValue(0);
    	$form->addSelect('ZpusobPripojeni_id', 'Způsob připojení', $zpusobPripojeni)->addRule(Form::FILLED, 'Vyberte způsob připojení');

        $form->addText('ipsubnet', 'Přidat všechny ip ze subnetu (x.y.z.w/c)',20);
        $form->addText('iprange', 'Přidat rozsah ip (x.y.z.w-x.y.z.w)',32);
        
    	$typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
    	$data = $this->ipAdresa;
    	$ips = $form->addDynamic('ip', function (Container $ip) use ($data,$typyZarizeni,$form) {
    	    $data->getIPForm($ip, $typyZarizeni);

    	    $ip->addSubmit('remove', '– Odstranit IP')
    		    ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
    		    ->setValidationScope(FALSE)
    		    ->addRemoveOnClick();
    	}, ($this->getParam('id')>0?0:1));
    
    	$ips->addSubmit('add', '+ Přidat další IP')
    		->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
    		->setValidationScope(FALSE)
    		->addCreateOnClick(TRUE, function (Container $replicator, Container $ip) {
                        $ip->setValues(array('internet'=>1));
						//\Tracy\Dumper::dump($ip);
				  });
    
    	$form->addSubmit('save', 'Uložit')
    		->setAttribute('class', 'btn btn-success btn-white default btn-edit-save');
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
                foreach($values->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)') as $ip_id => $ip_data) {
                    $form["ip"][$ip_id]->setValues($ip_data);
                }
                $form->setValues($values);
    	    }
    	}                
    
    	return $form;
    }
    
    public function validateUzivatelForm($form)
    {
        $data = $form->getHttpData();

        // Validujeme jenom při uložení formuláře
        if(!isset($data["save"])) {
            return(0);
        }
        
        if(isset($data['ipsubnet']) && !empty($data['ipsubnet']))
        {
            if (!preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))$/i", $data['ipsubnet'])) {
                $form->addError('IP subnet není validní!');
            }
        }
        if(isset($data['iprange']) && !empty($data['iprange']))
        {
            if (!preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])-(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $data['iprange'])) {
                $form->addError('IP rozsah není validní!');
            }
        }
        
        if(isset($data['ip'])) {
            $formIPs = array();
            foreach($data['ip'] as $ip) {
                if(!$this->ipAdresa->validateIP($ip['ip_adresa'])) {
                    $form->addError('IP adresa '.$ip['ip_adresa'].' není validní IPv4 adresa!');
                }
                
                $duplIp = $this->ipAdresa->getDuplicateIP($ip['ip_adresa'], $ip['id']);
                if ($duplIp) {
                    $form->addError('IP adresa '.$duplIp.' již  v databázi existuje!');
                }
                
                $formIPs[] = $ip['ip_adresa'];
            }

            // Tohle prohledá duplikátní IP přímo v formuláři
            // protože na ty se nepřijde pomocí getDuplicateIP
            $formDuplicates = array();
            foreach(array_count_values($formIPs) as $val => $c) {
                if($c > 1) {
                    $formDuplicates[] = $val;
                }
            }
            
            if(count($formDuplicates) != 0) {
                $formDuplicatesReadible = implode(", ", $formDuplicates);
                $form->addError('IP adresa '.$formDuplicatesReadible.' je v tomto formuláři vícekrát!');
            }
        }
        
        $values = $form->getValues();
        
        if($values->TypClenstvi_id > 1)
        {
            $duplMail = $this->uzivatel->getDuplicateEmailArea($values->email, $values->id);

            if ($duplMail) {
                $form->addError('Tento email již v DB existuje v oblasti: ' . $duplMail);
            }

            if (!empty($values->email2)) {
                $duplMail2 = $this->uzivatel->getDuplicateEmailArea($values->email2, $values->id);

                if ($duplMail2) {
                    $form->addError('Tento email již v DB existuje v oblasti: ' . $duplMail2);
                }
            }

            /*$duplPhone = $this->uzivatel->getDuplicatePhoneArea($values->telefon, $values->id);

            if ($duplPhone) {
                $form->addError('Tento telefon již v DB existuje v oblasti: ' . $duplPhone);
            }*/
        }
    }
    
    public function uzivatelFormSucceded($form, $values) {
        $log = array();
    	$idUzivatele = $values->id;
    	$ips = $values->ip;
        $ipsubnet = $values->ipsubnet;
        $iprange = $values->iprange;
        //\Tracy\Dumper::dump($ips);exit;
    	unset($values["ip"]);
        unset($values["ipsubnet"]);
        unset($values["iprange"]);
        
        $genaddresses = array();        
        $newUserIPIDs = array();
    	
        //generovani ip pro vlozeni ze subnetu
        $genaddresses = $this->ipAdresa->getListOfIPFromSubnet($ipsubnet);
        //generovani ip pro vlozeni z rozsahu        
        $genaddresses = array_merge($genaddresses,$this->ipAdresa->getListOfIPFromRange($iprange));

            
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
            $values->regform_downloaded_password_sent = 0;
            $values->money_aktivni = 1;
            $values->zalozen = new Nette\Utils\DateTime;
            $values->heslo = $this->uzivatel->generateStrongPassword();
            $values->id = $this->uzivatel->getNewID();
            $idUzivatele = $this->uzivatel->insert($values)->id;
            $this->log->logujInsert($values, 'Uzivatel', $log);
            
            $hash = base64_encode($values->id.'-'.md5($this->context->parameters["salt"].$values->zalozen));
            $link = "https://moje.hkfree.org/uzivatel/confirm/".$hash;
            
            $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());        
            $mail = new Message;
            $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
                ->addTo($values->email)
                ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s. - UID '.$idUzivatele)
                ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '. 
                              'následující odkaz:<br><br><a href="'.$link.'">'.$link.'</a><br><br>'.
                              'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                              'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                              'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                              'S pozdravem hkfree.org z.s.');
            $mailer = new SendmailMailer;
            $mailer->send($mail);
            
            $mailso = new Message;
            $mailso->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
                ->addTo($so->email)
                ->setSubject('kopie - Žádost o potvrzení registrace člena hkfree.org z.s. - UID '.$idUzivatele)
                ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '. 
                              'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel  UID '.$idUzivatele.'<br><br>'.
                              'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                              'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                              'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                              'S pozdravem hkfree.org z.s.');
            $mailer->send($mailso);

            $this->flashMessage('E-mail s žádostí o potvrzení registrace byl odeslán. INTERNET BUDE FUNGOVAT DO 15 MINUT.');
            
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);
    	    $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($olduzivatel, $values, 'Uzivatel', $log);
        }

    	// Potom zpracujeme IPcka
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
        // rozsahy ip adres
        foreach ($genaddresses as $gi)
        {          
            $duplIp = $this->ipAdresa->getDuplicateIP($gi, 0);
            if (!$duplIp) {       
                $rngip = array(
                'ip_adresa'=>$gi,
                'internet'=>TRUE,
                'smokeping'=>FALSE,
                'mac_filter'=>FALSE,
                'dhcp'=>FALSE,
                'Uzivatel_id'=>$idUzivatele
                ); 
               $idrngip = $this->ipAdresa->insert($rngip)->id;                      
               $this->log->logujInsert($rngip, 'IPAdresa['.$idrngip.']', $log);
               $newUserIPIDs[] = intval($idrngip);
            }                    
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
        $this->tabulkaUzivatelu->getListOfUsersGrid($this,
                                            $name,
                                            $this->getUser(),
                                            $this->getParameter('id'),                 
                                            $this->getParameter('money', false), 
                                            $this->getParameter('fullnotes', false),
                                            $this->getParameter('search', false)
                                            );       
        
    }
    
    public function renderListall()
    {
        $search = $this->getParameter('search', false);
        if(!$search)
            {
            $cestnych = count($this->cestneClenstviUzivatele->getListCC());
                $this->template->u_celkem = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id>?",1)->count("*");
                $this->template->u_celkemz = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id>?",0)->count("*");
                $this->template->u_aktivnich = $this->uzivatel->getSeznamUzivatelu()->where("money_aktivni=?",1)->count("*");
                $this->template->u_zrusenych = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id=?",1)->count("*");        
                $this->template->u_primarnich = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id=?",2)->count("*");
                $this->template->u_radnych = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id=?",3)->count("*")-$cestnych;
                $this->template->u_cestnych = $cestnych;
            } 
        
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
        $this->template->money = $this->getParameter("money", false);
        $this->template->search = $this->getParameter('search', false);
    }
    
    public function renderList()
    {
        // otestujeme, jestli máme id APčka a ono existuje
    	if($this->getParameter('id') && $apt = $this->ap->getAP($this->getParameter('id')))
    	{
            $id=$this->getParameter('id');
            $cestnych = count($this->cestneClenstviUzivatele->getListCCOfAP($id));
            $this->template->u_celkem = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id>?",1)->count("*");
            $this->template->u_celkemz = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id>?",0)->count("*");
            $this->template->u_aktivnich = $this->uzivatel->getSeznamUzivateluZAP($id)->where("money_aktivni=?",1)->count("*");
            $this->template->u_zrusenych = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id=?",1)->count("*");        
            $this->template->u_primarnich = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id=?",2)->count("*");
            $this->template->u_radnych = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id=?",3)->count("*")-$cestnych;
            $this->template->u_cestnych = $cestnych;

            
    	    $this->template->ap = $apt;
            $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());  
    	} else {            
            $this->flashMessage("Chyba, AP s tímto ID neexistuje.", "danger");
            $this->redirect("Homepage:default", array("id"=>null)); // a přesměrujeme
    	}
        
        $this->template->money = $this->getParameter("money", false);
        $this->template->fullnotes = $this->getParameter("fullnotes", false);
    }
    
    public function renderShow()
    {
    	if($this->getParam('id'))
    	{
            $uid = $this->getParam('id');
    	    if($uzivatel = $this->uzivatel->getUzivatel($uid))
    	    {
                $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
                $this->template->heslo = base64_decode($_SERVER['initials']);

                $this->template->money_act = ($uzivatel->money_aktivni == 1) ? "ANO" : "NE";
                $this->template->money_dis = ($uzivatel->money_deaktivace == 1) ? "ANO" : "NE";
                $posledniPlatba = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
                if($posledniPlatba->count() > 0)
                    {
                        $posledniPlatbaData = $posledniPlatba->fetch();
                        $this->template->money_lastpay = ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
                    }
                    else
                    {
                        $this->template->money_lastpay = "?";
                    }
                $posledniAktivace = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
                if($posledniAktivace->count() > 0)
                    {
                        $posledniAktivaceData = $posledniAktivace->fetch();
                        $this->template->money_lastact = ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
                    }
                    else
                    {
                        $this->template->money_lastact = "?";
                    }
                $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
                if(!$stavUctu || $stavUctu=='') $stavUctu=0;
                
                if($uzivatel->kauce_mobil > 0)
                {
                    $this->template->money_bal = ($stavUctu - $uzivatel->kauce_mobil) . ' (kauce: ' . $uzivatel->kauce_mobil . ')';
                }
                else{
                    $this->template->money_bal = $stavUctu;
                }

                if($this->sloucenyUzivatel->getIsAlreadyMaster($uid))
                {
                    $this->flashMessage('Uživatel má pod sebou sloučené uživatele.');
                    $this->template->slaves = $this->sloucenyUzivatel->getSlaves($uid);
                }
                else 
                {
                    $this->template->slaves = null;
                }
                if($this->sloucenyUzivatel->getIsAlreadySlave($uid))
                {
                    $this->flashMessage('Uživatel byl sloučen pod jiného uživatele.');
                    $this->template->master = $this->sloucenyUzivatel->getMaster($uid);
                    //\Tracy\Dumper::dump($this->sloucenyUzivatel->getMaster($uid));
                }
                else 
                {
                    $this->template->master = null;
                }
                
    		    $this->template->u = $uzivatel;
                
                $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)');

                $subnetLinks = $this->getSubnetLinksFromIPs($ipAdresy);

                $uzivatelEditLink = $this->link('Uzivatel:edit', array('id' => $uid));
    		    $this->template->adresy = $this->ipAdresa->getIPTable($ipAdresy, true, $subnetLinks, $uzivatelEditLink);
                                
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
                
                $this->template->activaceVisible = $uzivatel->money_aktivni == 0 && $uzivatel->money_deaktivace == 0 && ($stavUctu - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku();
                $this->template->reactivaceVisible = ($uzivatel->money_aktivni == 0 && $uzivatel->money_deaktivace == 1 && ($stavUctu - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku())
                                                        || ($uzivatel->money_aktivni == 1 && $uzivatel->money_deaktivace == 1);
                $this->template->deactivaceVisible = $uzivatel->money_aktivni == 1 && $uzivatel->money_deaktivace == 0;
    	    }
    	}
    }
    
    public function createComponentLogTable() {
        return $this->logTableFactory->create($this);
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
      $novaPravaID = array();
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
            $right->addHidden('zadost_podana')->setAttribute('class', 'id ip');
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
                 
            $right->addText('plati_do', 'Platnost do:')
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
                       ->getSeparatorPrototype()->setName("span")->style('margin-right', '7px');
                 
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
            $pravo->zadost_podana = new Nette\Utils\DateTime;
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
                
                $this->flashMessage('E-mail VV byl odeslán. Vyčkejte, než VV žádost potvrdí.');
            } else {
                //$starePravo = $this->cestneClenstviUzivatele->getCC($pravoId);
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
        
        $user = $this->uzivatel->getUzivatel($this->getParam('id'));
        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
        $form->addSelect('from', 'Odesílatel', array(0=>$so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',1=>'oblast'.$user->Ap->Oblast_id.'@hkfree.org'))->setDefaultValue(0);
        
        $form->addText('email', 'Příjemce', 70)->setDisabled(TRUE);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'emailFormSucceded');

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            
            
            if($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        'from' => 0,
                        'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function emailFormSucceded($form, $values) {
    	$idUzivatele = $values->id;
        
        $user = $this->uzivatel->getUzivatel($this->getParam('id'));
    
        $mail = new Message;
        
        if($values->from == 0)
        {
           $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId()); 
           $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($user->email)
            ->setSubject($values->subject)
            ->setBody($values->message);
        }
        else
        {
          $mail->setFrom('oblast'.$user->Ap->Oblast_id.'@hkfree.org')
            ->addTo($user->email)
            ->setSubject($values->subject)
            ->setBody($values->message);   
        }
        

        $mailer = new SendmailMailer;
        $mailer->send($mail);
        
        $this->flashMessage('E-mail byl odeslán.');
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }
    
    public function renderSms()
    {
        $user = $this->uzivatel->getUzivatel($this->getParam('id'));
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($user->Ap_id, $this->getUser());
        $this->template->uziv = $this->uzivatel->getUzivatel($this->getParam('id'));
    }
    
    protected function createComponentSmsForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
        $form = new Form($this, "smsForm");
    	$form->addHidden('id');

        //$form->addText('from', 'Odesílatel', 70)->setDisabled(TRUE);
        $form->addText('komu', 'Příjemce', 20)->setDisabled(TRUE);
        $form->addTextArea('message', 'Text', 72, 10);

        $user = $this->uzivatel->getUzivatel($this->getParam('id'));
        
        if(!empty($user->telefon) && $user->telefon!='missing')
        {
            $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');
            $form->onSuccess[] = array($this, 'smsFormSucceded');
        }
    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            

            //$so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
            if($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        //'from' => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',
                        'komu' => $user->telefon,
                        //'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function smsFormSucceded($form, $values) {
    	$user = $this->uzivatel->getUzivatel($this->getParam('id'));

        $locale = 'cs_CZ.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
        $command = escapeshellcmd('python /var/www/cgi/smsbackend.py -a https://aweg3.maternacz.com -l hkf'.$this->getUser()->getIdentity()->getId().'-'.$this->getUser()->getIdentity()->nick.':'.base64_decode($_SERVER['initials']).' -d '.$user->telefon.' "'.$values->message.'"');
        $output = shell_exec($command);
        
        $this->flashMessage('SMS byla odeslána. Output: ' . $output);
        
    	$this->redirect('Uzivatel:show', array('id'=>$this->getParam('id'))); 
    	return true;
    }
    
    public function renderSmsall()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
        $this->template->ap = $this->ap->getAP($this->getParam('id'));
    }
    
    protected function createComponentSmsallForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "smsallForm");
    	$form->addHidden('id');

        //$form->addText('from', 'Odesílatel', 70)->setDisabled(TRUE);
        $form->addTextArea('komu', 'Příjemce', 72, 20)->setDisabled(TRUE);
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'smsallFormSucceded');

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $ap = $this->ap->getAP($this->getParam('id'));
            $telefony = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'telefon');
            foreach($telefony as $tl)
            {
                if(!empty($tl) && $tl!='missing')
                {
                    $validni[]=$tl; 
                }
            }
            $tls = join(",",array_values($validni));
            
            //$so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
            if($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        //'from' => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',
                        'komu' => $tls,
                        //'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function smsallFormSucceded($form, $values) {
    	$ap = $this->ap->getAP($this->getParam('id'));        
        
        $telefony = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'telefon');
        foreach($telefony as $tl)
        {
            if(!empty($tl) && $tl!='missing')
            {
                $validni[]=$tl; 
            }
        }
        $tls = join(",",array_values($validni));
        //$tls="+420608214292";
        
        $locale = 'cs_CZ.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
        $command = escapeshellcmd('python /var/www/cgi/smsbackend.py -a https://aweg3.maternacz.com -l hkf'.$this->getUser()->getIdentity()->getId().'-'.$this->getUser()->getIdentity()->nick.':'.base64_decode($_SERVER['initials']).' -d '.$tls.' "'.$values->message.'"');
        $output = shell_exec($command);
        
        $this->flashMessage('SMS byly odeslány. Output: ' . $output);
        
    	$this->redirect('Uzivatel:list', array('id'=>$this->getParam('id'))); 
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

        if($this->getParam('id')) {
            $ap = $this->ap->getAP($this->getParam('id'));
            $oblastMail='oblast'.$ap->Oblast_id.'@hkfree.org';
        }
        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
        $form->addSelect('from', 'Odesílatel', array(0=>$so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',1=>$oblastMail))->setDefaultValue(0);
                
        //$form->addText('from', 'Odesílatel', 70)->setDisabled(TRUE);
        $form->addTextArea('email', 'Příjemce', 72, 20)->setDisabled(TRUE);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

    	$form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'emailallFormSucceded');

    	// pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $emaily = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'email');            
            foreach($emaily as $email)
            {
                if(Validators::isEmail($email)){
                    $validni[]=$email;            
                }
            }
            $tolist = join(";",array_values($validni));
                        
            if($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        'from' => 0,
                        'email' => $tolist,
                        'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
    	    }
    	}                
    
    	return $form;
    }
    
    public function emailallFormSucceded($form, $values) {
    	$idUzivatele = $values->id;
        
        $ap = $this->ap->getAP($this->getParam('id'));
        $emaily = $ap->related('Uzivatel.Ap_id')->where('TypClenstvi_id>1')->fetchPairs('id', 'email');
        
        
        $mail = new Message;
        if($values->from == 0)
        {
           $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
           $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->setSubject($values->subject)
            ->setBody($values->message);
        }
        else
        {
          $mail->setFrom('oblast'.$ap->Oblast_id.'@hkfree.org')
            ->setSubject($values->subject)
            ->setBody($values->message);
        }
        
        
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
        
    	$this->redirect('Uzivatel:list', array('id'=>$this->getParam('id'))); 
    	return true;
    }
    
    public function renderPlatba()
    {
        $id = $this->getParameter('id');
        $pohyb = $this->uzivatelskeKonto->findPohyb(array('PrichoziPlatba_id' => intval($id)));
        if($pohyb->Uzivatel_id)
        {
            $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($pohyb->Uzivatel_id)->Ap_id, $this->getUser());
        }
        else
        {
            $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        }
        $this->template->canViewOrEditCU = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->canTransfer = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->u = $pohyb->Uzivatel;
        $this->template->p = $this->prichoziPlatba->getPrichoziPlatba($this->getParam('id'));
    }
    
    public function renderAccount()
    {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParam('id'))->Ap_id, $this->getUser());
        $this->template->u = $this->uzivatel->getUzivatel($this->getParam('id'));
    }
    
    protected function createComponentAccountgrid($name)
    {
        $canViewOrEdit = false;
    	$id = $this->getParameter('id');
        
        //\Tracy\Dumper::dump($search);

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('account_export');
        
        if($id){  
            $seznamTransakci = $this->uzivatelskeKonto->getUzivatelskeKontoUzivatele($id);

            $canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParam('id'))->Ap_id, $this->getUser());
            
        } else {
            
            /*if($search)
            {
                $seznamTransakci = $this->uzivatel->findUserByFulltext($search,$this->getUser());
                $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
            }
            else
            {
                $seznamUzivatelu = $this->uzivatel->getSeznamUzivatelu();
                $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
            }
                        
            $grid->addColumnText('Ap_id', 'AP')->setCustomRender(function($item){
                  return $item->ref('Ap', 'Ap_id')->jmeno;
              })->setSortable();*/
        }
        
        $grid->setModel($seznamTransakci);
        
    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum_cas' => 'DESC'));
        
        $presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter){  
                if($item->PrichoziPlatba_id)
                {
                    $tr->onclick = "window.location='".$presenter->link('Uzivatel:platba', array('id'=>$item->PrichoziPlatba_id))."'";
                }
                return $tr;
            });
                        
    	/*$grid->addColumnText('Uzivatel_id', 'UID')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id'=>$item->Uzivatel_id)))
            ->title($item->Uzivatel_id)
            ->setText($item->Uzivatel_id);})->setSortable();*/
            
        /*$grid->addColumnText('PrichoziPlatba_id', 'Příchozí platba')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:platba', array('id'=>$item->PrichoziPlatba_id)))
            ->title($item->PrichoziPlatba_id)
            ->setText($item->PrichoziPlatba_id);})->setSortable();*/
            
        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();
        
        $grid->addColumnDate('datum_cas', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATETIME)->setSortable()->setFilterText();
        
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
                $el->setText(Strings::truncate($item->poznamka, 100, $append='…'));
                return $el;
                })->setSortable()->setFilterText();
    }
}
