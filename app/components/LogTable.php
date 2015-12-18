<?php

namespace App\Components;

use Nette\Application\UI,
    Nette\Utils\Html,
    App\Model;

class LogTable extends UI\Control
{
    private $parentPresenter;
    private $subnet;
    private $ipAdresa;
    private $log;
    
    const UZIVATEL = 1;
    const IPADRESA = 2;
    const PRAVO = 3;
    const AP = 4;
    const SUBNET = 5;
    
    const INSERT = "I";
    const UPDATE = "U";
    const DELETE = "D";
    
    public function __construct($parentPresenter, Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\Log $log)
    {
        //parent::__construct();
        $this->parentPresenter = $parentPresenter;
        $this->ipAdresa = $ipAdresa;
        $this->subnet = $subnet;
        $this->log = $log;
    }
    
    /**
     * Funkce parsující věci, co se vyskytují v poli "sloupec" v DB.
     * 
     * V případě Uživatele vrací "typ"=>UZIVATEL a "sloupec"
     * V případě IPAdresy vrací "typ"=>IPADRESA, "ipId" a "sloupec"
     * 
     * @param string $sloupec IPAdresa.sloupec z DB
     * @return array (typ, sloupec, ipId)
     */
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
        elseif (preg_match("/^pravo\[(\d+)\]\.(.+)/i", $sloupec, $matches))
        {
          $out["typ"] = self::PRAVO;
          $out["pravoId"] = $matches[1];
          $out["sloupec"] = $matches[2];
        }
        if (preg_match("/^ap\.(.+)/i", $sloupec, $matches))
        {
          $out["typ"] = self::AP;
          $out["sloupec"] = $matches[1];
        }
        elseif (preg_match("/^subnet\[(\d+)\]\.(.+)/i", $sloupec, $matches))
        {
          $out["typ"] = self::SUBNET;
          $out["subnetId"] = $matches[1];
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
        $ipZLogu = $this->log->getAdvancedzLogu(array_keys($ipsKProhlednuti));
        // Bacha na poradi!
        return($ipZDB + $ipZLogu + $ipsKProhlednuti);
    }
    
    /**
     * Funkce která projde logy a pro všechny Subnety udělá seznam subnetId - subnet
     * 
     * @param array $logy pole subnetId
     * @return array pole subnetId=>subnet
     */
    private function getSubnetMapping($logy)
    {
        $subnetsKProhlednuti = array();
        foreach ($logy as $line)
        {
            if(($sloupec = $this->parseSloupec($line->sloupec)) && ($sloupec["typ"] == self::SUBNET))
            {
                $subnetsKProhlednuti[$sloupec["subnetId"]] = "Nenalezeno";
            }
        }
        
        $subnetZDB = $this->subnet->getSubnetzDB(array_keys($subnetsKProhlednuti));
        $subnetZLogu = $this->log->getAdvancedzLogu(array_keys($subnetsKProhlednuti), 'subnet');
        // Bacha na poradi!
        return($subnetZDB + $subnetZLogu + $subnetsKProhlednuti);
    }
    
    private function tableGetHeader()
    {
        $logyTab = Html::el('table')->setClass('table table-striped logstable');
		$tr = $logyTab->create('tr');
        
        $sloupce = array('Kdy', 'Kdo', 'Co', 'Z', 'Na');
        foreach ($sloupce as $sloupec) {
            $tr->create('th')->setText($sloupec);
        }
        
        return($logyTab);
    }
    
    private function tableGetFooter(&$table)
    {
        $table->create('script')->setHTML('$(\'a\').tooltip();');
        return($table);
    }
    
    private function tableGetLineBegin(&$table, $line)
    {
        $tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');
        
        $tr = $table->create('tr');
        $tr->create('td')->setText($line->datum);

        $uzivatelUrl = $this->parentPresenter->link('Uzivatel:show', array('id' => $line->Uzivatel->id));
        $uzivatelA = Html::el('a')->href($uzivatelUrl)
                                  ->setText($line->Uzivatel->nick)
                                  ->setTitle('editoval z IP '.$line->ip_adresa)
                                  ->addAttributes($tooltips);
        $tr->create('td')->setHtml($uzivatelA);
        return($tr);
    }
    
    private function tableGetLineEndSimple(&$tr, $line, $rozparsovano)
    {
        $sloupec = $this->log->translateJmeno($rozparsovano["sloupec"]);
        $tr->create('td')->setText($sloupec);
        
        if($line->puvodni_hodnota == null)
            $tr->create('td')->setText('----');
        else
            $tr->create('td')->setText($line->puvodni_hodnota);
        
        if($line->nova_hodnota == null)
            $tr->create('td')->setText('----');
        else
            $tr->create('td')->setText($line->nova_hodnota);
    }
    
    
    private function tableProcessLines(&$table, $logy)
    {
        $ipVsechny = $this->getIPMapping($logy);
        $subnetyVsechny = $this->getSubnetMapping($logy);

        $last = false;
        
        $toSkip = array("uzivatel_id", "ap_id");      
        $toSkipString = implode("|", $toSkip);
        
        foreach ($logy as $key => $line) {
            if(($rozparsovano = $this->parseSloupec($line->sloupec)) === false)
                continue;
            
            if($rozparsovano["typ"] == self::UZIVATEL) {
                $tr = $this->tableGetLineBegin($table, $line);
                $this->tableGetLineEndSimple($tr, $line, $rozparsovano);
            }
            elseif($rozparsovano["typ"] == self::AP) {
                $tr = $this->tableGetLineBegin($table, $line);
                $this->tableGetLineEndSimple($tr, $line, $rozparsovano);
            }
            elseif($rozparsovano["typ"] == self::IPADRESA) {
                $id = $rozparsovano["ipId"];
                $sloupec = $rozparsovano["sloupec"];
                if(preg_match("/(".$toSkipString.")/i", $sloupec))
                    continue;
                $sloupec = $this->log->translateJmeno($sloupec);

                $datum = $line->datum;
                $titulek = "IP ".$ipVsechny[$id];
                
                if($last !== false && $last[0]->getText() == $datum && $last[2]->getText() == $titulek)
                {
                    $text = "";
                    if($line->akce === self::INSERT)                 
                        $text = $sloupec." = ".$line->nova_hodnota.", ";
                    elseif($line->akce === self::DELETE && $line->puvodni_hodnota!=null)
                        $text = $sloupec." = ".$line->puvodni_hodnota.", ";
                    elseif($line->akce === self::UPDATE)
                        $text = $sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    $last[3]->setText($last[3]->getText().$text);
                }
                else
                {
                    $last = $tr = $this->tableGetLineBegin($table, $line);

                    $tr->create('td')->setText($titulek);
                    
                    $text = "";
                    if($line->akce === self::INSERT)                 
                        $text = "Založení IP Adresy s parametry ".$sloupec." = ".$line->nova_hodnota.", ";
                    elseif($line->akce === self::DELETE)
                        $text = "IP Adresa byla smazána. Parametry byly ".$sloupec." = ".$line->puvodni_hodnota.", ";
                    elseif($line->akce === self::UPDATE)
                        $text = "Změna ".$sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                        
                    $tr->create('td')->setText($text)->setColspan(2);
                }
            }
            elseif($rozparsovano["typ"] == self::SUBNET) {
                $id = $rozparsovano["subnetId"];
                $sloupec = $rozparsovano["sloupec"];
                if(preg_match("/(".$toSkipString.")/i", $sloupec))
                    continue;
                $sloupec = $this->log->translateJmeno($sloupec);

                $datum = $line->datum;
                $titulek = "Subnet ".$subnetyVsechny[$id];
                
                if($last !== false && $last[0]->getText() == $datum && $last[2]->getText() == $titulek)
                {
                    $text = "";
                    if($line->akce === self::INSERT)                 
                        $text = $sloupec." = ".$line->nova_hodnota.", ";
                    elseif($line->akce === self::DELETE && $line->puvodni_hodnota!=null)
                        $text = $sloupec." = ".$line->puvodni_hodnota.", ";
                    elseif($line->akce === self::UPDATE)
                        $text = $sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    $last[3]->setText($last[3]->getText().$text);
                }
                else
                {
                    $last = $tr = $this->tableGetLineBegin($table, $line);

                    $tr->create('td')->setText($titulek);
                    
                    $text = "";
                    if($line->akce === self::INSERT)                 
                        $text = "Založení subnetu s parametry ".$sloupec." = ".$line->nova_hodnota.", ";
                    elseif($line->akce === self::DELETE)
                        $text = "Subnet byl smazán. Parametry byly ".$sloupec." = ".$line->puvodni_hodnota.", ";
                    elseif($line->akce === self::UPDATE)
                        $text = "Změna ".$sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                        
                    $tr->create('td')->setText($text)->setColspan(2);
                }
            }
        }
    }
    
    public function render($id, $type = "user")
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/LogTable.latte');

        $tabulka = $this->tableGetHeader();
        if($type == "user") {
          $logy = $this->log->getLogyUzivatele($id); 
        } elseif($type == "ap") {
          $logy = $this->log->getLogyAP($id); 
        } else {
            die('Spatne pouziti komponenty LogTable.');
        }
        
        $this->tableProcessLines($tabulka, $logy);
        $this->tableGetFooter($tabulka);
        
        // vložíme do šablony nějaké parametry        
        $template->tabulka = $tabulka;
        
        // a vykreslíme ji
        $template->render();
    }
}

