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

    public function __construct(Nette\Database\Context $db, Nette\Security\User $user, TypZarizeni $t) {
        parent::__construct($db, $user);
    }

    /**
    * @var string
    */
    protected $tableName = 'Log';

    public function getLogyUzivatele($uid)
    {
        $logy = $this->findAll()->where("tabulka = ?", "Uzivatel")->where("tabulka_id = ?", $uid)->order("datum DESC, sloupec DESC");
        return($logy);
    }

    public function getLogyAP($Apid)
    {
        $logy = $this->findAll()->where("tabulka = ?", "Ap")->where("tabulka_id = ?", $Apid)->order("datum DESC, sloupec DESC");
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
            "firma_ico" => "IČO firmy",
            "oldlog" => "importované logy",
            "TechnologiePripojeni_id" => "technologie připojení",
            "cislo_clenske_karty" => "číslo členské karty",
            "email2" => "sekundární email",
            "ulice_cp" => "ulice a č.p.",
            "psc" => "PSČ",
            "mesto" => "město"
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
     * Tahle funkce má za úkol získat už neexistující ip adresy / subnety podle
     * jejich starého ID
     *
     * takhle blbě je to řešené, protože mysql neumí sort-before-groupby
     *
     * @param int[] $ids ipId pro které chceme zjistit ipAdresy / subnety
     * @param string $type Typ objektu pro který zjišťujeme - ip / subnet
     * @return array pole ipId=>ipAdresa
     */
    public function getAdvancedzLogu(array $ids, $type = "ip")
    {
        $names = array();
        foreach ($ids as $id) {
            if($type == "ip") {
                $names[] = "IPAdresa[".$id."].ip_adresa";
            } elseif ($type == "subnet") {
                $names[] = "Subnet[".$id."].subnet";
            }
        }

        $logy = $this->findAll()->where("sloupec", $names)->order("datum ASC");

        $out = array();
        foreach($logy as $log)
        {
            if($type == "ip") {
                preg_match("/^ipadresa\[(\d+)\]\.ip_adresa/i", $log->sloupec, $matches);
            } elseif ($type == "subnet") {
                preg_match("/^subnet\[(\d+)\]\.subnet/i", $log->sloupec, $matches);
            }

            $id = $matches[1];

            if($log->puvodni_hodnota !== null) {
                $out[$id] = $log->puvodni_hodnota;
            }

            if($log->nova_hodnota !== null) {
                $out[$id] = $log->nova_hodnota;
            }
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
            $isSet = isset($staraData[$key]) || ($staraData[$key] == NULL);
            if(!($isSet && $value == $staraData[$key])) {
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
        if(!is_array($data) || count($data) == 0)
            return(true);

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
