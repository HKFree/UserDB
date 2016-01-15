<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\Html;

/**
 * @author 
 */
class UzivatelListGrid extends Nette\Object
{
    private $uzivatel;
    private $ap;
    private $cestneClenstviUzivatele;
    private $parameters;

    function __construct(Parameters $parameters, AP $ap, CestneClenstviUzivatele $cc, Uzivatel $uzivatel) {
    	
    	$this->uzivatel = $uzivatel;       
        $this->ap = $ap;
        $this->cestneClenstviUzivatele = $cc; 
        $this->parameters = $parameters;
    }
    
    public function getListOfUsersGrid($presenter, $name, $loggedUser, $id, $money, $fullnotes, $search) {
        //\Tracy\Dumper::dump($search);
        
        $canViewOrEdit = false;
        
    	$grid = new \Grido\Grid($presenter, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('user_export');
        
        if($id){  
            $seznamUzivatelu = $this->uzivatel->getSeznamUzivateluZAP($id);
            $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCCOfAP($id);
            $canViewOrEdit = $this->ap->canViewOrEditAP($id, $loggedUser);
        } else {
            
            if($search)
            {
                $seznamUzivatelu = $this->uzivatel->findUserByFulltext($search,$loggedUser);
                $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCC(); //TODO
                $canViewOrEdit = $this->ap->canViewOrEditAll($loggedUser);
            }
            else
            {
                $seznamUzivatelu = $this->uzivatel->getSeznamUzivatelu();
                $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCC();
                $canViewOrEdit = $this->ap->canViewOrEditAll($loggedUser);
            }
                        
            $grid->addColumnText('Ap_id', 'AP')->setCustomRender(function($item){
                  return $item->ref('Ap', 'Ap_id')->jmeno;
              })->setSortable();
        }
        
        $grid->setModel($seznamUzivatelu);
        
    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
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
        
        if($money)
        {
            $thisparams = $this->parameters;
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter, $thisparams){
                
                $tr->onclick = "window.location='".$presenter->link('Uzivatel:show', array('id'=>$item->id))."'";
                                
                $konto = $item->related('UzivatelskeKonto.Uzivatel_id');
                if($item->money_aktivni != 1)
                {
                  $tr->class[] = 'neaktivni';
                }
                if(($konto->sum('castka') - $item->kauce_mobil) > ($thisparams->getVyseClenskehoPrispevku()*12))
                {
                  $tr->class[] = 'preplatek';
                }
                if(in_array($item->id, $seznamUzivateluCC)){
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if($item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }            
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter){
                
                $tr->onclick = "window.location='".$presenter->link('Uzivatel:show', array('id'=>$item->id))."'";
                
                if(in_array($item->id, $seznamUzivateluCC)){
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
        
    	$grid->addColumnText('id', 'UID')->setCustomRender(function($item) use ($presenter, $canViewOrEdit)
        {
            $uidLink = Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id'=>$item->id)))
            ->title($item->id)
            ->setText($item->id);

            if ($canViewOrEdit)
            {
                // edit button
                $btn = Html::el('span')->setClass('glyphicon glyphicon-pencil');
                $anchor = Html::el('a', $btn)
                            ->setHref($presenter->link('Uzivatel:edit', array('id'=>$item->id)))
                            ->setTitle('Editovat')
                            ->setClass('btn btn-default btn-xs btn-in-table pull-right');
                $uidLink .= $anchor;
            }

            return $uidLink;
        })->setSortable();
        $grid->addColumnText('nick', 'Nick')->setSortable();

        if($canViewOrEdit) {
            $grid->addColumnText('jmeno', 'Jméno a příjmení')->setCustomRender(function($item){                
                return $item->jmeno . ' '. $item->prijmeni;
            })->setSortable();
            if($fullnotes)   
            {
                $grid->addColumnText('ulice_cp', 'Ulice')->setSortable()->setFilterText();
                $grid->addColumnText('mesto', 'Obec')->setSortable()->setFilterText();
                $grid->addColumnText('psc', 'PSČ')->setSortable()->setFilterText();
            }
            else{
                $grid->addColumnText('ulice_cp', 'Ulice')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->ulice_cp;
                $el->setText(Strings::truncate($item->ulice_cp, 50, $append='…'));
                return $el;
            })->setSortable()->setFilterText();
            }
            
            $grid->addColumnEmail('email', 'E-mail')->setSortable();
            $grid->addColumnText('telefon', 'Telefon')->setSortable();
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
                $grid->addColumnText('money_aktivni', 'Aktivní')->setSortable()->setCustomRender(function($item){                    
                    return ($item->money_aktivni == 1) ? "ANO" : "NE";
                }); 
                
                $grid->addColumnText('money_deaktivace', 'Deaktivace')->setSortable()->setCustomRender(function($item){                    
                    return ($item->money_deaktivace == 1) ? "ANO" : "NE";
                });
                                
                
                $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function($item){
                    $posledniPlatba = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
                    if($posledniPlatba->count() > 0)
                    {
                      $posledniPlatbaData = $posledniPlatba->fetch();
                      return ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
                    }
                    return "?";                    
                })->setCustomRender(function($item){
                    $posledniPlatba = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
                    if($posledniPlatba->count() > 0)
                    {
                      $posledniPlatbaData = $posledniPlatba->fetch();
                      return ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
                    }
                    return "?";
                });   
                
                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function($item){
                    $posledniAktivace = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
                    if($posledniAktivace->count() > 0)
                    {
                      $posledniAktivaceData = $posledniAktivace->fetch();
                      return ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
                    }
                    return "?";
                })->setCustomRender(function($item){
                    $posledniAktivace = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',4)->order('id DESC')->limit(1);
                    if($posledniAktivace->count() > 0)
                    {
                      $posledniAktivaceData = $posledniAktivace->fetch();
                      return ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
                    }
                    return "?";
                }); 
                
                $grid->addColumnText('acc', 'Stav účtu')->setColumn(function($item){
                    $stavUctu = $item->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
                    if($item->kauce_mobil > 0)
                        return ($stavUctu - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')';
                    else
                        return $stavUctu;                    
                })->setCustomRender(function($item){
                    $stavUctu = $item->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
                    if($item->kauce_mobil > 0)
                        return ($stavUctu - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')';
                    else
                        return $stavUctu;
                });
            }

            $grid->addColumnText('TechnologiePripojeni_id', 'Tech')->setCustomRender(function($item) {
            return Html::el('span')
                    ->setClass('conntype'.$item->TechnologiePripojeni_id)
                    ->alt($item->TechnologiePripojeni_id)
                    ->setTitle($item->TechnologiePripojeni->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
            
            if($fullnotes)   
            {
                $grid->addColumnText('poznamka', 'Dlouhá poznámka')->setSortable()->setFilterText();            
            }
            else
            {
                $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->poznamka;
                $el->setText(Strings::truncate($item->poznamka, 20, $append='…'));
                return $el;
                })->setSortable()->setFilterText();
            } 
    	}
        
        return $grid;
    }
}
