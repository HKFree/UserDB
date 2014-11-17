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
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{     
    private $typClenstvi;
    private $typPravniFormyUzivatele;
    private $zpusobPripojeni;
    private $uzivatel;
    private $ipAdresa;
    private $ap;
    private $typZarizeni;

    function __construct(Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni) {
    	$this->typClenstvi = $typClenstvi;
      $this->typPravniFormyUzivatele = $typPravniFormyUzivatele;
    	$this->zpusobPripojeni = $zpusobPripojeni;
    	$this->uzivatel = $uzivatel;
    	$this->ipAdresa = $ipAdresa;  
    	$this->ap = $ap;
    	$this->typZarizeni = $typZarizeni;
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
            $rtfdata = str_replace("--adresa1--", iconv("UTF-8","windows-1250",$uzivatel->adresa), $rtfdata);
            $rtfdata = str_replace("--typ--", iconv("UTF-8","windows-1250",$uzivatel->TypClenstvi->text), $rtfdata);
            $rtfdata = str_replace("--ip4--", join(",",array_values($uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'))), $rtfdata);
            $rtfdata = str_replace("--oblast--", $uzivatel->Ap->Oblast->jmeno, $rtfdata);
            $oblastid = $uzivatel->Ap->Oblast->id; 
            $rtfdata = str_replace("--emailoblasti--", "oblast$oblastid@hkfree.org", $rtfdata);
            $rtfdata = str_replace("--pristimesic--", iconv("UTF-8","windows-1250",$pristimesic), $rtfdata);
            $rtfdata = str_replace("--prvniplatba--", iconv("UTF-8","windows-1250",$prvniplatba), $rtfdata);
            
            $this->sendResponse(new Model\ContentDownloadResponse($rtfdata, "hkfree-registrace-$uzivatel->id.rtf"));
    	    }
    	}
    }

    public function renderEdit()
    {
	   $this->template->anyVariable = 'any value';
    }

    protected function createComponentUzivatelForm() {
    	$typClenstvi = $this->typClenstvi->getTypyClenstvi()->fetchPairs('id','text');
      $typPravniFormy = $this->typPravniFormyUzivatele->getTypyPravniFormyUzivatele()->fetchPairs('id','text');
    	$zpusobPripojeni = $this->zpusobPripojeni->getZpusobyPripojeni()->fetchPairs('id','text');
    	$aps = $this->oblast->formatujOblastiSAP($this->oblast->getSeznamOblasti());
    
    	$form = new Form;
    	$form->addHidden('id');
      $form->addSelect('Ap_id', 'Oblast - AP', $aps);
    	$form->addRadioList('TypPravniFormyUzivatele_id', 'Právní forma', $typPravniFormy)->addRule(Form::FILLED, 'Vyberte typ právní formy');
      $form->addText('firma_nazev', 'Název firmy', 30)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::EQUAL, 2)->setRequired('Zadejte název firmy');
      $form->addText('firma_ico', 'IČO', 8)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::EQUAL, 2)->setRequired('Zadejte IČ');
      //http://phpfashion.com/jak-overit-platne-ic-a-rodne-cislo
      $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno');
    	$form->addText('prijmeni', 'Přijmení', 30)->setRequired('Zadejte příjmení');
    	$form->addText('nick', 'Nick (přezdívka)', 30)->setRequired('Zadejte nickname');
    	$form->addText('email', 'Email', 30)->setRequired('Zadejte email')->addRule(Form::EMAIL, 'Musíte zadat platný email');;
    	$form->addText('telefon', 'Telefon', 30)->setRequired('Zadejte telefon');
    	$form->addTextArea('adresa', 'Adresa (ulice čp, psč město)', 24)->setRequired('Zadejte adresu');
    	$form->addText('rok_narozeni', 'Rok narození',30);	
    	$form->addRadioList('TypClenstvi_id', 'Členství', $typClenstvi)->addRule(Form::FILLED, 'Vyberte typ členství');
      $form->addTextArea('poznamka', 'Poznámka', 24, 10);	
    	$form->addSelect('index_potizisty', 'Index potížisty', array(0=>0,10=>10,20=>20,30=>30,40=>40,50=>50,60=>60,70=>70,80=>80,90=>90,100=>100))->setDefaultValue(50);
    	$form->addRadioList('ZpusobPripojeni_id', 'Způsob připojení', $zpusobPripojeni)->addRule(Form::FILLED, 'Vyberte způsob připojení');
    
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
    
        $form->setDefaults(array(
            'TypClenstvi_id' => 3,
            'TypPravniFormyUzivatele_id' => 1,
        ));
    
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
    	
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }

    protected function createComponentMoneygrid($name)
    {
    	$id = $this->getParam('id');
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
    	$grid->setModel($this->uzivatel->getSeznamUzivateluZAP($id));
    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('zalozen' => 'ASC'));
    
    	$list = array('active' => 'bez zrušených', 'all' => 'včetně zrušených');
    	$grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)->setDefaultValue('active')->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));
    
      //Debugger::dump();
      
      $uid = '58';
      $heslo = 'mUKZ6vJJ';
 
      $client = new \SoapClient(
        'https://' . $uid . ':' . $heslo . '@money.hkfree.org/wsdl/moneyAPI.wsdl',
        array(
                'login'         => $uid,
                'password'      => $heslo,
                'trace'         => 1,
                )
        );

      $moneycallresult = $client->hkfree_money_userGetInfo(implode(",", $this->uzivatel->getSeznamUIDUzivateluZAP($id)));

      //Debugger::dump( $moneycallresult );
      
    	if($this->ap->canViewOrEditAP($id, $this->getUser()->getIdentity()->getId()))
    	{
    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
    	$grid->addColumnText('jmeno', 'Jméno')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('prijmeni', 'Příjmení')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('nick', 'Nickname')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('adresa', 'Adresa')->setSortable()->setFilterText();
    	$grid->addColumnEmail('email', 'E-mail')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('telefon', 'Telefon')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function($item){
            return join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function($item){
            return "<span title=".join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'))).">".$item->related('IPAdresa.Uzivatel_id')->fetch()->ip_adresa."</span>";
        });
        
      $grid->addColumnText('act', 'Aktivní')->setColumn(function($item) use ($moneycallresult){
            return ($moneycallresult[$item->id]->userIsActive->isActive == 1) ? "ANO" : (($moneycallresult[$item->id]->userIsActive->isActive == 0) ? "NE" : "?");
        })->setCustomRender(function($item) use ($moneycallresult){
            return ($moneycallresult[$item->id]->userIsActive->isActive == 1) ? "ANO" : (($moneycallresult[$item->id]->userIsActive->isActive == 0) ? "NE" : "?");
        });        
      $grid->addColumnText('deact', 'Deaktivace')->setColumn(function($item) use ($moneycallresult){
            return ($moneycallresult[$item->id]->userIsDisabled->isDisabled == 1) ? "ANO" : (($moneycallresult[$item->id]->userIsDisabled->isDisabled == 0) ? "NE" : "?");
        })->setCustomRender(function($item) use ($moneycallresult){
            return ($moneycallresult[$item->id]->userIsDisabled->isDisabled == 1) ? "ANO" : (($moneycallresult[$item->id]->userIsDisabled->isDisabled == 0) ? "NE" : "?");
        });        
      $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function($item) use ($moneycallresult){
            return "TODO";
        })->setCustomRender(function($item) use ($moneycallresult){
            return "TODO";
        });        
      $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function($item) use ($moneycallresult){
            return "TODO";
        })->setCustomRender(function($item) use ($moneycallresult){
            return "TODO";
        });        
      $grid->addColumnText('acc', 'Stav účtu')->setColumn(function($item) use ($moneycallresult){
            return ($moneycallresult[$item->id]->GetAccountBalance->GetAccountBalance > 0) ? $moneycallresult[$item->id]->GetAccountBalance->GetAccountBalance : "?";
        })->setCustomRender(function($item) use ($moneycallresult){
            return ($moneycallresult[$item->id]->GetAccountBalance->GetAccountBalance > 0) ? $moneycallresult[$item->id]->GetAccountBalance->GetAccountBalance : "?";
        });
    	    
    	$grid->addActionHref('show', 'Zobrazit')
    	    ->setIcon('eye-open');
    	$grid->addActionHref('edit', 'Editovat')
    	    ->setIcon('pencil');
    	}
    	else
    	{
    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
    	$grid->addColumnText('nick', 'Nickname')->setSortable()->setFilterText()->setSuggestion();
	    } 
    }

    protected function createComponentGrid($name)
    {
    	$id = $this->getParam('id');
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
    	$grid->setModel($this->uzivatel->getSeznamUzivateluZAP($id));
    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('zalozen' => 'ASC'));
    
    	$list = array('active' => 'bez zrušených', 'all' => 'včetně zrušených');
    	$grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)->setDefaultValue('active')->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));
    
      //Debugger::dump();
      
    	if($this->ap->canViewOrEditAP($id, $this->getUser()->getIdentity()->getId()))
    	{
    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
      $grid->addColumnText('TypPravniFormyUzivatele_id', 'Právní forma')->setCustomRender(function($item){
            return $item->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id')->text;
        })->setSortable()->setFilterSelect(array(
                        "" => "",
                        "1" => "Fyzická os.",
                        "2" => "Právnická os.",
                    ));
    	$grid->addColumnText('jmeno', 'Jméno')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('prijmeni', 'Příjmení')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('nick', 'Nickname')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('adresa', 'Adresa')->setSortable()->setFilterText();
    	$grid->addColumnEmail('email', 'E-mail')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('telefon', 'Telefon')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function($item){
            return join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function($item){
            return "<span title=".join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'))).">".$item->related('IPAdresa.Uzivatel_id')->fetch()->ip_adresa."</span>";
        });
    	//$grid->addColumnText('wifi_user', 'Vlastní WI-FI')->setSortable()->setReplacement(array('2' => Html::el('b')->setText('ANO'),'1' => Html::el('b')->setText('NE')));
    	$grid->addColumnText('poznamka', 'Poznámka')->setSortable()->setFilterText();
    	    
    	$grid->addActionHref('show', 'Zobrazit')
    	    ->setIcon('eye-open');
    	$grid->addActionHref('edit', 'Editovat')
    	    ->setIcon('pencil');
    	}
    	else
    	{
    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
    	$grid->addColumnText('nick', 'Nickname')->setSortable()->setFilterText()->setSuggestion();
    	$grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function($item){
            return join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function($item){
            return "<span title=".join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'))).">".$item->related('IPAdresa.Uzivatel_id')->fetch()->ip_adresa."</span>";
        });
	    } 
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
    
    public function renderMoney()
    {
    	if($this->getParam('id'))
    	{
    	    $ob = $this->ap->getAP($this->getParam('id'));
    	    $this->template->ap = $ob;
    	}
    	else {
    	   $this->template->table = 'Chyba, AP nenalezeno.'; 
    	}
    }
    
    public function renderShow()
    {
    	if($this->getParam('id'))
    	{
    	    if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
    		$this->template->u = $uzivatel;
    		$this->template->adresy = $this->ipAdresa->getIPTable($uzivatel->related('IPAdresa.Uzivatel_id'));
    	    }
    	}
    }

}
