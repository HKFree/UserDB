<?php

namespace App\Model;

use Nette;
use Nette\Utils\Html;

/**
 * @author
 * @phpstan-type LogEntry array{sloupec: string, puvodni_hodnota: mixed, nova_hodnota: mixed, akce: string}
 */
class Log extends Table
{
    private Nette\Http\Request $request;

    public function __construct(
        Nette\Database\Context $db,
        Nette\Security\User $user,
        Nette\Http\Request $request
    ) {
        parent::__construct($db, $user);
        $this->request = $request;
    }

    /**
    * @var string
    */
    protected $tableName = 'Log';

    /**
     * @param int|string $uid
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getLogyUzivatele($uid): Nette\Database\Table\Selection {
        $logy = $this->findAll()->where("tabulka = ?", "Uzivatel")->where("tabulka_id = ?", $uid)->order("datum DESC, sloupec DESC");
        return ($logy);
    }

    /**
     * @param int|string $Apid
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getLogyAP($Apid): Nette\Database\Table\Selection {
        $logy = $this->findAll()->where("tabulka = ?", "Ap")->where("tabulka_id = ?", $Apid)->order("datum DESC, sloupec DESC");
        return ($logy);
    }

    public function translateJmeno(string $jmeno): string {
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

        if (isset($slovnik[$jmeno])) {
            return ($slovnik[$jmeno]);
        } else {
            return ($jmeno);
        }
    }

    /**
     * Tahle funkce má za úkol získat už neexistující ip adresy / subnety podle
     * jejich starého ID
     *
     * takhle blbě je to řešené, protože mysql neumí sort-before-groupby
     *
     * @param array<array-key, int|string> $ids ipId pro které chceme zjistit ipAdresy / subnety
     * @param string $type Typ objektu pro který zjišťujeme - ip / subnet
     * @return array<int|string, string> pole ipId=>ipAdresa
     */
    public function getAdvancedzLogu(array $ids, string $type = "ip"): array {
        if ($type != "ip" && $type != "subnet") {
            throw new \InvalidArgumentException("Neznámý typ objektu: ".$type);
        }

        $names = array();
        foreach ($ids as $id) {
            if ($type == "ip") {
                $names[] = "IPAdresa[".$id."].ip_adresa";
            } elseif ($type == "subnet") {
                $names[] = "Subnet[".$id."].subnet";
            }
        }

        $logy = $this->findAll()->where("sloupec", $names)->order("datum ASC");

        $vzor = $type == "ip" ? "/^ipadresa\[(\d+)\]\.ip_adresa/i" : "/^subnet\[(\d+)\]\.subnet/i";

        $out = array();
        foreach ($logy as $log) {
            // SQL porovnává sloupec podle kolace, vzor po bajtech; řádek bez čitelného ID nelze přiřadit.
            if (preg_match($vzor, $log->sloupec, $matches) !== 1) {
                continue;
            }

            $id = $matches[1];

            if ($log->puvodni_hodnota !== null) {
                $out[$id] = $log->puvodni_hodnota;
            }

            if ($log->nova_hodnota !== null) {
                $out[$id] = $log->nova_hodnota;
            }
        }

        return ($out);
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @param list<LogEntry> $log
     */
    public function logujInsert(iterable $data, string $sloupecPrefix, array &$log): void {
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $log[] = array(
                    'sloupec' => $sloupecPrefix.'.'.$key,
                    'puvodni_hodnota' => null,
                    'nova_hodnota' => $value,
                    'akce' => 'I'
                );
            }
        }
    }

    /**
     * @param array<array-key, mixed>|\ArrayAccess<array-key, mixed> $staraData
     * @param iterable<array-key, mixed> $novaData
     * @param list<LogEntry> $log
     */
    public function logujUpdate($staraData, iterable $novaData, string $sloupecPrefix, array &$log): void {
        foreach ($novaData as $key => $value) {
            $isSet = isset($staraData[$key]) || ($staraData[$key] == null);
            if (!($isSet && $value == $staraData[$key])) {
                $log[] = array(
                    'sloupec' => $sloupecPrefix.'.'.$key,
                    'puvodni_hodnota' => isset($staraData[$key]) ? $staraData[$key] : null,
                    'nova_hodnota' => $value,
                    'akce' => 'U'
                );
            }
        }
    }

    /**
     * @param iterable<array-key, mixed> $staraData
     * @param list<LogEntry> $log
     */
    public function logujDelete(iterable $staraData, string $sloupecPrefix, array &$log): void {
        foreach ($staraData as $key => $value) {
            if (!empty($value)) {
                $log[] = array(
                    'sloupec' => $sloupecPrefix.'.'.$key,
                    'puvodni_hodnota' => $value,
                    'nova_hodnota' => null,
                    'akce' => 'D'
                );
            }
        }
    }

    // Uzivatel_id je ten, kdo zmenu provedl
    /**
     * @param int|string $tabulka_id
     * @param mixed $data Log entries; non-array input is ignored.
     * @param int|string|null $uzivatel_id
     * @return Nette\Database\Table\ActiveRow|array<array-key, mixed>|int|bool
     */
    public function loguj(string $tabulka, $tabulka_id, $data, $uzivatel_id = null): Nette\Database\Table\ActiveRow|array|int|bool {
        if (!is_array($data) || count($data) == 0) {
            return true;
        }

        // Je bezpodminecne nutne mit stejny cas pro vsechny polozky, proto se
        // vytvari uz tady a ne az triggerem v DB!
        $ted = new Nette\Utils\DateTime();

        // Zmenu udelal prihlaseny uzivatel
        if (empty($uzivatel_id)) {
            $uzivatel_id = $this->userService->getId();
        }

        // Pokud neni zadny uzivatel prihlaseny (napr verejny callback) a logujeme neco u uzivatele, vem jeho
        if (empty($uzivatel_id) && $tabulka == 'Uzivatel') {
            $uzivatel_id = $tabulka_id;
        }

        // A pokud ani to nevyjde, zaloguj to pod systemovym uzivatelem
        if (empty($uzivatel_id)) {
            $uzivatel_id = 1;
        }

        $spolecne = array(
            'Uzivatel_id' => $uzivatel_id,
            'ip_adresa' => $this->request->getRemoteAddress() ?? '',
            'tabulka' => $tabulka,
            'tabulka_id' => $tabulka_id,
            'datum' => $ted
        );

        $toInsert = array();
        foreach ($data as $radek) {
            $toInsert[] = array_merge($radek, $spolecne);
        }

        return ($this->insert($toInsert));
    }
}
