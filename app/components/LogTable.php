<?php

namespace App\Components;

use Nette\Application\UI,
    Nette\Utils\Html;

class LogTable extends UI\Control
{
    private $parentPresenter;
    private $ipAdresa;
    private $log;
    
    const UZIVATEL = 1;
    const IPADRESA = 2;
    
    public function __construct($parentPresenter, \App\Model\IPAdresa $ipAdresa, \App\Model\Log $log)
    {
        parent::__construct();
        $this->parentPresenter = $parentPresenter;
        $this->ipAdresa = $ipAdresa;
        $this->log = $log;
    }
    
    private function parseSloupec($sloupec)
    {
        $out = false;
        if (preg_match("/^uzivatel\.(.+)/i", $sloupec, $matches))
        {
          $out["typ"] = self::UZIVATEL;
          $out["sloupec"] = $matches[1];
        }
        elseif (preg_match("/^ipadresa\[(\d+)\]\.(.+)/i", $sloupec, $matches))
        {
          $out["typ"] = self::IPADRESA;
          $out["ipId"] = $matches[1];
          $out["sloupec"] = $matches[2];
        }
        return($out);
    }
        
    /**
     * Funkce která projde logy a pro všechny IPčka udělá seznam ipId - ipAdresa
     * 
     * @param array $logy pole ipId
     * @return array pole ipId=>ipAdresa
     */
    private function getIPMapping($logy)
    {
        $ipsKProhlednuti = array();
        foreach ($logy as $line)
        {
            if(($sloupec = $this->parseSloupec($line->sloupec)) && ($sloupec["typ"] == self::IPADRESA))
            {
                $ipsKProhlednuti[$sloupec["ipId"]] = "Nenalezeno";
            }
        }
        
        $ipZDB = $this->ipAdresa->getIPzDB(array_keys($ipsKProhlednuti));
        $ipZLogu = $this->log->getIPzLogu(array_keys($ipsKProhlednuti));
        // Bacha na poradi!
        return($ipZDB + $ipZLogu + $ipsKProhlednuti);
    }
    
    private function tableGetHeader()
    {
        $logyTab = Html::el('table')->setClass('table table-striped');
		$tr = $logyTab->create('tr');
        
        $sloupce = array('Kdy', 'Kdo', 'Co', 'Z', 'Na');
        foreach ($sloupce as $sloupec) 
            $tr->create('th')->setText($sloupec);
        
        return($logyTab);
    }
    
    private function tableProcessLine($table, $logy)
    {
        $ipVsechny = $this->getIPMapping($logy);

        $last = false;
        
        $toSkip = array("uzivatel_id", "ap_id");      
        $toSkipString = implode("|", $toSkip);
        
        foreach ($logy as $key => $line) {
            if(preg_match("/^uzivatel\.(.+)/i", $line->sloupec, $matches))
            {
                $sloupec = $matches[1];
                $tr = $table->create('tr');
                $tr->create('td')->setText($line->datum);

                $uzivatelUrl = $this->parentPresenter->link('Uzivatel:show', array('id' => $line->Uzivatel->id));
                $uzivatelA = Html::el('a')->href($uzivatelUrl)
                                          ->setText($line->Uzivatel->nick);
                $tr->create('td')->setHtml($uzivatelA);
                $tr->create('td')->setText($sloupec);
                if(empty($line->puvodni_hodnota))
                    $tr->create('td')->setText('----');
                else
                    $tr->create('td')->setText($line->puvodni_hodnota);
                if(empty($line->nova_hodnota))
                    $tr->create('td')->setText('----');
                else
                    $tr->create('td')->setText($line->nova_hodnota);
            }

            if(preg_match("/^ipadresa\[(\d+)\]\.(.+)/i", $line->sloupec, $matches))
            {
                
                $id = $matches[1];
                $sloupec = $matches[2];
                if(preg_match("/(".$toSkipString.")/i", $sloupec))
                    continue;

                $datum = $line->datum;
                $titulek = "IP ".$ipVsechny[$id];
                
                if($last !== false && $last[0]->getText() == $datum && $last[2]->getText() == $titulek)
                {
                    $text = "";
                    if($line->puvodni_hodnota === null)                 
                        $text = $sloupec."=".$line->nova_hodnota.", ";
                    elseif($line->nova_hodnota === null)
                        $text2 = "IP Adresa byla smazána.";
                    else
                        $text = $sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    $last[3]->setText($last[3]->getText().$text);
                }
                else
                {
                    $last = $tr = $table->create('tr');
                    
                    $tr->create('td')->setText($datum);

                    $uzivatelUrl = $this->parentPresenter->link('Uzivatel:show', array('id' => $line->Uzivatel->id));
                    $uzivatelA = Html::el('a')->href($uzivatelUrl)
                                              ->setText($line->Uzivatel->nick);
                    $tr->create('td')->setHtml($uzivatelA);

                    
                    $tr->create('td')->setText($titulek);
                    
                    $text = "";
                    if($line->puvodni_hodnota === null)                 
                        $text = "Založení IP Adresy s parametry ".$sloupec."=".$line->nova_hodnota.", ";
                    elseif($line->nova_hodnota === null)
                        $text = "IP Adresa byla smazána.";
                    else
                        $text = "Změna ".$sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                        
                    $tr->create('td')->setText($text)->setColspan(2);
                }
            }
        }
    }
    
    public function render($uid)
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/LogTable.latte');

        $tabulka = $this->tableGetHeader();

        $this->tableProcessLine($tabulka, $this->log->getLogyUzivatele($uid)); 
        
        // vložíme do šablony nějaké parametry        
        $template->tabulka = $tabulka;
        // a vykreslíme ji
        $template->render();
    }
}

