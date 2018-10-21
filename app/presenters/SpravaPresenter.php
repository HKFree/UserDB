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
    Nette\Utils\Json,
    Nette\Utils\DateTime,
    Tracy\Debugger;

use Nette\Forms\Controls\SubmitButton;
/**
 * Sprava presenter.
 */
class SpravaPresenter extends BasePresenter
{
    private $spravceOblasti;
    private $cestneClenstviUzivatele;
    
    private $uzivatel;
    private $log;
    private $ap;
    private $ipAdresa;
    private $uzivatelskeKonto;
    private $prichoziPlatba;
    private $odchoziPlatba;
    private $stavBankovnihoUctu;
    private $googleMapsApiKey;

    function __construct(Model\SpravceOblasti $sob, Model\StavBankovnihoUctu $stavuctu, Model\PrichoziPlatba $platba, Model\OdchoziPlatba $odchplatba, Model\UzivatelskeKonto $konto, Model\Uzivatel $uzivatel, Model\Log $log, Model\AP $ap, Model\IPAdresa $ipAdresa) {
    	$this->uzivatel = $uzivatel;
        $this->log = $log;
        $this->ap = $ap;
        $this->ipAdresa = $ipAdresa;
        $this->uzivatelskeKonto = $konto;
        $this->prichoziPlatba = $platba;
        $this->odchoziPlatba = $odchplatba;
        $this->stavBankovnihoUctu = $stavuctu;
        $this->spravceOblasti = $sob;
    }

    public function setGoogleMapsApiKey($googleMapsApiKey)
    {
        $this->googleMapsApiKey = $googleMapsApiKey;
    }

    public function actionLogout() {
        $this->getUser()->logout();
        header("Location: https://userdb.hkfree.org/Shibboleth.sso/Logout?return=https://idp.hkfree.org/idp/logout?return=http://www.hkfree.org");
        die();
    }

    public function renderNastroje()
    {
    	$this->template->canApproveCC = $this->getUser()->isInRole('VV');
        $this->template->canSeeMailList = $this->getUser()->isInRole('VV');
        $this->template->canCreateArea = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->canSeePayments = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    public function renderUsersgraph()
    {
        $activationsData = $this->uzivatel->getNumberOfActivations();
        //\Tracy\Dumper::dump($activationsData);

        $graphdata = array();
        foreach($activationsData as $ad) {
            $dt = Nette\Utils\DateTime::from($ad->year."-".str_pad($ad->month, 2, "0", STR_PAD_LEFT)."-01");
            $graphdata[] = [ "x" => $dt->getTimestamp(), "y" => $ad->users ];
        }

        $actDataJson = Json::encode($graphdata);
        $this->template->actdata = $actDataJson;
    }

    protected function createComponentMailinglistGrid($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('mailinglist_export');

        $grid->setModel($this->uzivatel->getUsersForMailingList());

    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('id' => 'ASC'));

    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('email', 'Email')->setFilterText();
    }

    public function actionShow($id) {
        $this->redirect('Uzivatel:show', array('id'=>$id));
    }

    public function renderPlatbycu()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->cu = "";
    }

    public function renderMailinglist()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
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

        $grid->setRowCallback(function ($item, $tr){

            if($item->datum_platby == null)
                {
                  $tr->class[] = 'primarni';
                }

                return $tr;
            });

    	$grid->addColumnDate('datum', 'Datum dokladu')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText();
        $grid->getColumn('datum')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('firma', 'Firma')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('popis', 'Popis')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('typ', 'Typ')->setSortable()->setFilterText()->setSuggestion();
        $grid->getColumn('typ')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('kategorie', 'Kategorie')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ')->setSortable()->setFilterText()->setSuggestion();
        $grid->getColumn('castka')->headerPrototype->style['width'] = '10%';
        $grid->addColumnDate('datum_platby', 'Datum platby')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText()->setSuggestion();
        $grid->getColumn('datum_platby')->headerPrototype->style['width'] = '10%';
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
        $grid->addColumnText('text', 'Bankovní účet');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ');

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

    public function renderMapa()
    {
        $aps = $this->ap->findAll();
        $povoleneAp = [];
        foreach ($aps as $ap) {
            if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($ap->id, $this->getUser())) {
                $povoleneAp[] = $ap->id;
            }
        }
        $uzivatele = $this->uzivatel->findAll()->where('location_status IN (?, ?)', 'valid', 'approx')->where('TypClenstvi_id > ?', 1);
        $uzivatele = $uzivatele->where('Ap_id', $povoleneAp); // Ap_id IN (..., ..., ...)
        $uzivatele = $uzivatele->fetchAll();
        $output = []; // klic = kombinace latitude + longitude
        foreach($uzivatele as $uzivatel) {
            $key = "{$uzivatel->latitude},{$uzivatel->longitude}";
            if (!isset($output[$key])) {
                // na danych souradnicich jeste zadny bod v poli $output neni
                $output[$key] = [
                    'lat' => $uzivatel->latitude,
                    'lon' => $uzivatel->longitude,
                    'us' => [], // users
                    'ax' => 0 // approximate flag
                ];
            }
            $output[$key]['us'][] = [
                'id' => $uzivatel->id,
                'ni' => $uzivatel->nick,
                'jm' => $uzivatel->jmeno,
                'pr' => $uzivatel->prijmeni,
                'li' => $this->link('Uzivatel:show', array('id'=>$uzivatel->id))
            ];
            if ($uzivatel->location_status === 'approx') {
                $output[$key]['ax'] = 1;
            }
        }
        $this->template->data = json_encode(array_values($output));
        $this->template->googleMapsApiKey = $this->googleMapsApiKey;
    }
}
