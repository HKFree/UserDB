<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;

 
/**
 * @author 
 */
class Log extends Table
{

    /**
    * @var string
    */
    protected $tableName = 'Log';
               
    public function getLogyUzivatele($uid)
    {
        $logy = $this->findAll()->where("tabulka = ?", "uzivatel")->where("tabulka_id = ?", $uid)->order("datum DESC, sloupec DESC");
        return($logy);
    }
    
    /**
     * Tahle funkce má za úkol získat už neexistující ip adresy podle jejich
     * starého ID
     * 
     * takhle blbě je to řešené, protože mysql neumí sort-before-groupby
     * 
     * @param array $ipId ipId pro které chceme zjistit ipAdresy
     * @return array pole ipId=>ipAdresa
     */
    public function getIPzLogu(array $ipIds)
    {
        $ipNames = array();
        foreach ($ipIds as $ipId) {
            $ipNames[] = "IPAdresa[".$ipId."].ip_adresa";
        }
        
        $logy = $this->findAll()->where("sloupec", $ipNames)->order("datum ASC");
        
        $out = array();
        foreach($logy as $log)
        {
            preg_match("/^ipadresa\[(\d+)\]\.ip_adresa/i", $log->sloupec, $matches);
            $ipId = $matches[1];
            
            if($log->puvodni_hodnota !== null)
                $out[$ipId] = $log->puvodni_hodnota;
            
            if($log->nova_hodnota !== null)
                $out[$ipId] = $log->nova_hodnota;
        }
       
        return($out);
    }
    
    public function loguj($tabulka, $tabulka_id, $data)
    {
        $out = true;
        
        // Je bezpodminecne nutne mit stejny cas pro vsechny polozky, proto se 
        // vytvari uz tady a ne az triggerem v DB!
        $ted = new Nette\Utils\DateTime;
        
        $spolecne = array(
            'Uzivatel_id' => $this->userService->getId(),
            'ip_adresa' => $_SERVER['REMOTE_ADDR'],
            'tabulka' => $tabulka,
            'tabulka_id' => $tabulka_id,
            'datum' => $ted
        );

        $toInsert = array();
        foreach($data as $radek)
        {
           $toInsert[] = array_merge($radek, $spolecne);
        }
        
        return($this->insert($toInsert));
    }
}
