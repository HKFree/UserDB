<?php

namespace App\Services;

use DateTime;
use App\Model;

class GeneratorSmlouvy
{
    /**
     * Náhled smlouvy - rovnou vrací PDFko
     */
    public static function nahledUcastnickeSmlouvy($uzivatel, $cislo_smlouvy = null) {

        $starsi_smlouva_id = self::posledniPlatnaSmlouva($uzivatel);
        $parametry = self::parametryUcastnickeSmlouvy($uzivatel, $cislo_smlouvy, $starsi_smlouva_id);

        if (!getenv('PDF_GENERATOR_URL')) {
            throw new \Exception("Missing PDF_GENERATOR_URL environment variable\n");
        }

        $url = getenv('PDF_GENERATOR_URL')."/smlouvaUcastnicka.php";
        $params = http_build_query($parametry);
        return file_get_contents("$url?$params");
    }

    public static function posledniPlatnaSmlouva($uzivatel) {
        global $container;

        // autodetekce puvodni smlouvy, kterou nahrazujeme
        $PodpisSmlouvyModel = $container->getByType('App\Model\PodpisSmlouvy');
        $podpis = $PodpisSmlouvyModel->findOneBy([
            'Smlouva.uzivatel_id' => $uzivatel->id,
            'Smlouva.typ' => 'ucastnicka',
            'PodpisSmlouvy.smluvni_strana' => 'ucastnik',
            'Smlouva.kdy_ukonceno' => null,
            'PodpisSmlouvy.kdy_odmitnuto' => null,
            'PodpisSmlouvy.kdy_podepsano NOT' => null,
        ]);

        \Tracy\Debugger::barDump($podpis);

        if ($podpis) {
            return $podpis->smlouva_id;
        }

        return null;
    }

    public static function parametryUcastnickeSmlouvy($uzivatel, $cislo_smlouvy = null, $nahrazuje_smlouvu_id = null) {
        global $container;

        $cestneClenstviUzivateleModel = $container->getByType('App\Model\CestneClenstviUzivatele');
        $smlouvaModel = $container->getByType('App\Model\Smlouva');

        $jmenoString = sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni);
        $adresaString = sprintf("%s, %s %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto);
        $cena1 = 290;
        $cena1poznamka = '';
        if ($cestneClenstviUzivateleModel->getHasCC($uzivatel->id)) {
            $cena1 = 0;
            $cena1poznamka = '(zdarma)';
        }

        // TODO

        // $sluzba2 = '';
        // $cena2 = null;
        $sluzba2 = "Televize - START balíček SledovaniTV";
        $cena2 = 190;

        $subjectPrefix = getenv('AGREEMENT_NAME_PREFIX');
        if (!empty($subjectPrefix)) {
            $jmenoString = "($subjectPrefix) $jmenoString";
        }

        $ip4Adresy = [];
        $IPAdresaModel = $container->getByType('App\Model\IPAdresa');
        $subnetModel =  $container->getByType('App\Model\Subnet');

        foreach (
            $IPAdresaModel->findAll()->where(['Uzivatel_id' => $uzivatel->id])->fetchPairs('ip_adresa') as $adresa => $record
        ) {
            $adresaLomenoMaska = "$adresa";
            $gateway = "";

            $subnet = $subnetModel->getSubnetOfIP($adresa);
            if (!isset($subnet["error"])) {
                $gateway = $subnet["gateway"];
                $adresaLomenoMaska .= "/" . $subnet["cidr"];
            }

            array_push($ip4Adresy, [$adresaLomenoMaska, $gateway]);
        }

        $nahrazuje_smlouvu_text = '';
        if ($nahrazuje_smlouvu_id) {
            $puvodni_smlouva = $smlouvaModel->find($nahrazuje_smlouvu_id);
            $nahrazuje_smlouvu_text = sprintf('která nahrazuje předchozí smlouvu č. %u ze dne %s',
                $puvodni_smlouva->id,
                $puvodni_smlouva->kdy_vygenerovano->format('d.m.Y') // TODO tady by asi melo bejt datum kdy podepsano
            );
        }

        $parametrySmlouvy = [
          'cislo' => $cislo_smlouvy ?? '0',
          'ze_dne' => (new DateTime())->format('d.m.Y'),
          'uid' => (string) $uzivatel->id,
          'jmeno_prijmeni' => $jmenoString,
          'upresneni' => 'Datum narození',
          'datum_narozeni' => $uzivatel->datum_narozeni ? $uzivatel->datum_narozeni->format('d.m.Y') : '',
          'email' => $uzivatel->email,
          'telefon' => $uzivatel->telefon,
          'adresa' => $adresaString,
          'email_spravce_oblasti' => sprintf('oblast%u@hkfree.org', $uzivatel->Ap->Oblast->id),
          'cena1' => sprintf('%u Kč', $cena1),
          'cena1poznamka' => $cena1poznamka,
          'sluzba2' => $sluzba2,
          'cena2' => $cena2 ? sprintf('%u Kč', $cena2) : '',
          'cena_celkem' => sprintf('%u Kč', $cena1 + $cena2),
          'nahrazuje_smlouvu' => $nahrazuje_smlouvu_text
        ];
        if ($uzivatel->TypPravniFormyUzivatele->text == "PO") {
            $parametrySmlouvy['upresneni'] = 'Firma';
            $parametrySmlouvy['datum_narozeni'] = '';
            $parametrySmlouvy['firma'] = sprintf("%s, IČO: %s", $uzivatel->firma_nazev, $uzivatel->firma_ico);
        }
        for ($i = 1; $i <= count($ip4Adresy); ++$i) {
            $parametrySmlouvy["IPv4-$i"] = $ip4Adresy[$i - 1][0];
            $parametrySmlouvy["GW-$i"] = $ip4Adresy[$i - 1][1];
            if ($i == 4) {
                $parametrySmlouvy["IPv4-more"] = sprintf('... a další (%u)', count($ip4Adresy) - 4);
                break;
            }
        }

        return $parametrySmlouvy;
    }

}
