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
    private $prichoziPlatba;
    private $tabulkaUzivatelu;
    private $parameters;
    private $accountActivation;
    private $povoleneSMTP;
    private $cryptosvc;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;
    function __construct(Model\CryptoSluzba $cryptosvc, Model\PovoleneSMTP $alowedSMTP, Model\Parameters $parameters, Model\AccountActivation $accActivation, Model\UzivatelListGrid $ULGrid, Model\PrichoziPlatba $platba, Model\SloucenyUzivatel $slUzivatel, Model\Subnet $subnet, Model\SpravceOblasti $prava, Model\CestneClenstviUzivatele $cc, Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\TechnologiePripojeni $technologiePripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni, Model\Log $log) {
        $this->cryptosvc = $cryptosvc;
        $this->spravceOblasti = $prava;
        $this->cestneClenstviUzivatele = $cc;
        $this->typClenstvi = $typClenstvi;
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
        $this->prichoziPlatba = $platba;
        $this->tabulkaUzivatelu = $ULGrid;
        $this->parameters = $parameters;
        $this->accountActivation = $accActivation;
        $this->povoleneSMTP = $alowedSMTP;
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
        $template->ico = $uzivatel->firma_ico;
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
            $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
            $apcko = $this->ap->getAP($so->Ap_id);
            $subnety = $apcko->related('Subnet.Ap_id');
            $seznamUzivatelu = $this->uzivatel->findUsersIdsFromOtherAreasByAreaId($so->Ap_id, $subnety);
            //\Tracy\Dumper::dump($seznamUzivatelu);

            $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT') 
                                                || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser())
                                                || in_array($uzivatel->id,$seznamUzivatelu);
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
    	$form->addSelect('TypClenstvi_id', 'Členství', $typClenstvi)->addRule(Form::FILLED, 'Vyberte typ členství');
        $form->addTextArea('poznamka', 'Poznámka', 50, 12);
    	$form->addSelect('TechnologiePripojeni_id', 'Technologie připojení', $technologiePripojeni)->addRule(Form::FILLED, 'Vyberte technologii připojení');
        $form->addSelect('index_potizisty', 'Index spokojenosti člena', array(0=>0,1=>1,2=>2,3=>3,4=>4,5=>5))->setDefaultValue(0);
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
                    if($ip_data->heslo_sifrovane == 1)
					{
                        $decrypted = $this->cryptosvc->decrypt($ip_data->heslo);
                        $ipdata = $ip_data->toArray();
                        $ipdata['heslo'] = $decrypted;
                        $form["ip"][$ip_id]->setValues($ipdata);
					}
					else {
						$form["ip"][$ip_id]->setValues($ip_data);
					}
                }
                $form->setValues($values);
    	    }
    	}

    	return $form;
    }

    public function validateUzivatelForm($form) {
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
        }
    }

    public function sendNotificationEmail($idUzivatele) {
        
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);

        $mailer = new SendmailMailer;
        
        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());

        $mailso = new Message;
        $mailso->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($so->email)
            ->setSubject('NOTIFIKACE - Nový plánovaný člen - UID '.$idUzivatele)
            ->setHTMLBody('V DB je zadán nový plánovaný člen ve Vaší oblasti s UID '.$idUzivatele.'<br><br>'.
                            'AP: '.$newUser->Ap->jmeno.'<br><br>'.
                            'Jméno: '.$newUser->jmeno.' '.$newUser->prijmeni.'<br><br>'.
                            'Adresa: '.$newUser->ulice_cp.', '.$newUser->psc.' '.$newUser->mesto.'<br><br>'.
                            'https://userdb.hkfree.org/userdb/uzivatel/show/'.$idUzivatele.'<br><br>'.
                            'Bude pravděpodobně následovat připojení od techniků<br><br>'.
                            'Prosím zkontrolujte si adresu přípojného místa a pokud máte pro techniky nějaké informace tak je kontaktujte.<br><br>'.
                            'S pozdravem UserDB');
        if (!empty($so->email2))
        {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($idUzivatele);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        $mailer->send($mailso);

        $this->flashMessage('E-mail s notifikací správcům byl odeslán.');
        
    }

    public function sendRegistrationEmail($idUzivatele) {

        $newUser = $this->uzivatel->getUzivatel($idUzivatele);

        $hash = base64_encode($idUzivatele.'-'.md5($this->context->parameters["salt"].$newUser->zalozen));
        $link = "https://moje.hkfree.org/uzivatel/confirm/".$hash;

        $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());
        $mail = new Message;
        $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($newUser->email)
            ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s. - UID '.$idUzivatele)
            ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '.
                          'následující odkaz:<br><br><a href="'.$link.'">'.$link.'</a><br><br>'.
                          'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                          'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                          'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                          'S pozdravem hkfree.org z.s.');
        if (!empty($newUser->email2))
        {
           $mail->addTo($newUser->email2);
        }
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
        if (!empty($so->email2))
        {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($idUzivatele);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        $mailer->send($mailso);

        $this->flashMessage('E-mail s žádostí o potvrzení registrace byl odeslán. INTERNET BUDE FUNGOVAT DO 15 MINUT.');

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
        $smtpIPIDs = array();

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

        // pri kazde editaci uzivatele nastavime znovu geocoding na pending, aby se znovu dohledaly geofraf. souradnice
        $values->location_status = 'pending';

    	// Zpracujeme nejdriv uzivatele
    	if(empty($values->id)) {
            $values->regform_downloaded_password_sent = 0;
            if($values->TypClenstvi_id > 0)
            {
                $values->money_aktivni = 1;
            }
            else{
                $values->money_aktivni = 0;
            }
            $values->zalozen = new Nette\Utils\DateTime;
            $values->heslo = $this->uzivatel->generateStrongPassword();
            $values->heslo_hash = crypt($values->heslo, 'hk');
            $values->heslo_strong_hash = hash('sha256', $values->heslo);
            $values->id = $this->uzivatel->getNewID();
            $idUzivatele = $this->uzivatel->insert($values)->id;
            $this->log->logujInsert($values, 'Uzivatel', $log);

            if($values->TypClenstvi_id > 0)
            {
                $this->sendRegistrationEmail($idUzivatele);
            }
            else{
                $this->sendNotificationEmail($idUzivatele);
            }
            
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);

            if($olduzivatel->email != $values->email || $olduzivatel->email2 != $values->email2)
            {
                $values->email_invalid=0;
            }

            if($olduzivatel->TypClenstvi_id == 0 && $values->TypClenstvi_id == 1)
            {
                $this->povoleneSMTP->deleteIPs($smtpIPIDs);
                $existinguserIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
                $this->ipAdresa->deleteIPAdresy($existinguserIPIDs);
                $this->uzivatel->delete(array('id' => array($idUzivatele)));
                $this->redirect('Uzivatel:list', array('id'=>$olduzivatel->Ap_id));
                return true;
            }

            if($olduzivatel->TypClenstvi_id == 0 && $values->TypClenstvi_id != 0)
            {
                $values->zalozen = new Nette\Utils\DateTime;
                $values->money_aktivni = 1;
            }

    	    $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($olduzivatel, $values, 'Uzivatel', $log);

            if($olduzivatel->TypClenstvi_id == 0 && $values->TypClenstvi_id != 0)
            {
                $this->sendRegistrationEmail($idUzivatele);
            }
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

            $ip->heslo = $this->cryptosvc->encrypt($ip->heslo);
            $ip->heslo_sifrovane = 1;

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
                $isSMTP = $this->povoleneSMTP->getIP($oldip->id);
                if($isSMTP)
                {
                    $smtpIPIDs[] = intval($isSMTP->id);
                }
            }
        }
        $this->povoleneSMTP->deleteIPs($smtpIPIDs);

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

    protected function createComponentOthersGrid($name)
    {
        $this->tabulkaUzivatelu->getListOfOtherUsersGrid($this,
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
            $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
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

                $stavUctuDph = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where("datum>='2017-11-01'")->where('castka>0')->sum('castka');
                if(!$stavUctuDph || $stavUctuDph=='') $stavUctuDph=0;
                $this->template->money_dph = $stavUctuDph;

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
                $wewimoLinks = $this->getWewimoLinksFromIPs($ipAdresy);

                $uzivatelEditLink = $this->link('Uzivatel:edit', array('id' => $uid));
    		    $this->template->adresy = $this->ipAdresa->getIPTable($ipAdresy, true, $subnetLinks, $wewimoLinks, $uzivatelEditLink, $this->getParameter('igw', false), Array($this, "linker"));

                if($ipAdresy->count() > 0)
                {
                    $this->template->adresyline = join(", ",array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
                }
                else
                {
                    $this->template->adresyline = null;
                }

                $apcko = $this->ap->getAP($so->Ap_id);
                $subnety = $apcko->related('Subnet.Ap_id');
                $seznamUzivatelu = $this->uzivatel->findUsersIdsFromOtherAreasByAreaId($so->Ap_id, $subnety);
                //\Tracy\Dumper::dump($seznamUzivatelu);

                $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT') 
                                                    || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser())
                                                    || in_array($uid,$seznamUzivatelu);
                $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uzivatel->id);

                $this->template->activaceVisible = $uzivatel->money_aktivni == 0 && $uzivatel->money_deaktivace == 0 && ($stavUctu - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku();
                $this->template->reactivaceVisible = ($uzivatel->money_aktivni == 0 && $uzivatel->money_deaktivace == 1 && ($stavUctu - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku())
                                                        || ($uzivatel->money_aktivni == 1 && $uzivatel->money_deaktivace == 1);
                $this->template->deactivaceVisible = $uzivatel->money_aktivni == 1 && $uzivatel->money_deaktivace == 0;

                $this->template->igw = $this->getParameter("igw", false);
    	    }
    	}
    }

    public function createComponentLogTable() {
        return $this->logTableFactory->create($this);
    }
}
