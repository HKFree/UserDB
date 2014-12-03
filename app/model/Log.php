<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;
 
/**
 * @author 
 */
class Log extends Table
{
    private $typZarizeni;
    
    public function __construct(Nette\Database\Connection $db, Nette\Security\User $user, TypZarizeni $t) {
        parent::__construct($db, $user);
    }

    /**
    * @var string
    */
    protected $tableName = 'Log';
               
    public function getLogyUzivatele($uid)
    {
        $logy = $this->findAll()->where("tabulka = ?", "uzivatel")->where("tabulka_id = ?", $uid)->order("datum DESC, sloupec DESC");
        return($logy);
    }
    
    public function translateJmeno($jmeno)
    {
        $slovnikUzivatel = array(
            "Ap_id" => "AP",
            "jmeno" => "jméno",
            "prijmeni" => "příjmení",
            "nick" => "přezdívka",
            "heslo" => "heslo",
            "email" => "email",
            "adresa" => "adresa",
            "rok_narozeni" => "rok narození",
            "telefon" => "telefon",
            "poznamka" => "poznámka",
            "index_potizisty" => "index potížisty",
            "zalozen" => "založen",
            "TypClenstvi_id" => "typ členství",
            "ZpusobPripojeni_id" => "způsob připojení",
            "TypPravniFormyUzivatele_id" => "právní forma",
            "firma_nazev" => "název firmy",
            "firma_ico" => "IČO firmy"            
        ); 
        
        $slovnikIpAdresa = array(    
            "hostname" => "hostname",
            "mac_adresa" => "MAC adresa",
            "mac_filter" => "povoleno v MAC filteru",
            "internet" => "povoleno do internetu",
            "smokeping" => "monitoring ve smokepingu",
            "dhcp" => "povoleno v DHCP",
            "TypZarizeni_id" => "typ zařízení",
            "popis" => "popis",
            "login" => "login",
            "heslo" => "heslo"
        );
         
        $slovnik = array_merge($slovnikUzivatel, $slovnikIpAdresa);
        
        if(isset($slovnik[$jmeno])) {
            return($slovnik[$jmeno]);
        } else {
            return($jmeno);
        }
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
    
    public function logujInsert($data, $sloupecPrefix, &$log)
    {
        foreach($data as $key => $value) {
            if(!empty($value)) {
                $log[] = array(
                    'sloupec'=>$sloupecPrefix.'.'.$key,
                    'puvodni_hodnota'=>NULL,
                    'nova_hodnota'=>$value,
                    'akce'=>'I'
                );
            }
        }
    }
    
    public function logujUpdate($staraData, $novaData, $sloupecPrefix, &$log)
    {
        foreach($novaData as $key => $value) {
            if(!(isset($staraData[$key]) && $value == $staraData[$key])) {
                $log[] = array(
                    'sloupec'=>$sloupecPrefix.'.'.$key,
                    'puvodni_hodnota'=>isset($staraData[$key])?$staraData[$key]:NULL,
                    'nova_hodnota'=>$value,
                    'akce'=>'U'
                );
            }
        }
    }
    
    public function logujDelete($staraData, $sloupecPrefix, &$log)
    {
        foreach($staraData as $key => $value) {
            if(!empty($value)) {
                $log[] = array(
                    'sloupec'=>$sloupecPrefix.'.'.$key,
                    'puvodni_hodnota'=>$value,
                    'nova_hodnota'=>NULL,
                    'akce'=>'D'
                );
            }
        }
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
