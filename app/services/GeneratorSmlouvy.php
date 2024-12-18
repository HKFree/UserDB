<?php

namespace App\Services;
use DateTime;

class GeneratorSmlouvy
{
    /**
     * Náhled smlouvy - rovnou vrací PDFko
     */
    public static function nahledUcastnickeSmlouvy($uzivatel, $cislo_smlouvy = null) {

        $parametry = self::parametryUcastnickeSmlouvy($uzivatel, $cislo_smlouvy);

        if (!getenv('PDF_GENERATOR_URL')) {
            throw new \Exception("Missing PDF_GENERATOR_URL environment variable\n");
        }

        $url = getenv('PDF_GENERATOR_URL')."/smlouvaUcastnicka.php";
        $params = http_build_query($parametry);
        return file_get_contents("$url?$params");
    }

    public static function parametryUcastnickeSmlouvy($uzivatel, $cislo_smlouvy = null) {
        global $container;

        $cestneClenstviUzivateleModel = $container->getByType('App\Model\CestneClenstviUzivatele');

        $jmenoString = sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni);
        $adresaString = sprintf("%s, %s %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto);
        $cenaString = '290 Kč za měsíc';
        // $cenaString .= ' včetně DPH'; // tohle odkomentovat až bude družstvo plátcem DPH
        if ($cestneClenstviUzivateleModel->getHasCC($uzivatel->id)) {
            $cenaString = '0 Kč (zdarma)';
        }

        $subjectPrefix = getenv('AGREEMENT_NAME_PREFIX');
        if (!empty($subjectPrefix)) {
            $jmenoString = "($subjectPrefix) $jmenoString";
        }

        $ip4Adresy = [];
        $IPAdresa = $container->getByType('App\Model\IPAdresa');
        foreach (
            $IPAdresa->findAll()->where(['Uzivatel_id' => $uzivatel->id])->fetchPairs('ip_adresa') as $adresa => $record
        ) {
            $adresaLomenoMaska = "$adresa/24"; // TODO /24
            $gateway = ""; // TODO: k IP adresám dotáhnout a zobrazovat gatewaye
            array_push($ip4Adresy, [$adresaLomenoMaska, $gateway]);
        }

        $parametrySmlouvy = [
          'cislo' => $cislo_smlouvy ?? '0',
          'ze_dne' => new DateTime()->format('d.m.Y'),
          'uid' => (string) $uzivatel->id,
          'jmeno_prijmeni' => $jmenoString,
          'upresneni' => 'Datum narození',
          'datum_narozeni' => $uzivatel->datum_narozeni ? $uzivatel->datum_narozeni->format('d.m.Y') : '',
          'email' => $uzivatel->email,
          'telefon' => $uzivatel->telefon,
          'adresa' => $adresaString,
          'email_spravce_oblasti' => sprintf('oblast%u@hkfree.org', $uzivatel->Ap->Oblast->id),
          'cena' => $cenaString
        ];
        if ($uzivatel->TypPravniFormyUzivatele->text == "PO") {
            $parametrySmlouvy['upresneni'] = 'Firma';
            $parametrySmlouvy['datum_narozeni'] = '';
            $parametrySmlouvy['firma'] = sprintf("%s, IČO: %s", $uzivatel->firma_nazev, $uzivatel->firma_ico);
        }
        for ($i = 1; $i <= count($ip4Adresy); ++$i) {
            $parametrySmlouvy["IPv4-$i"] = $ip4Adresy[$i - 1][0];
            $parametrySmlouvy["GW-$i"] = $ip4Adresy[$i - 1][1];  // TODO
            if ($i == 4) {
                $parametrySmlouvy["IPv4-more"] = sprintf('... a další (%u)', count($ip4Adresy) - 4);
                break;
            }
        }

        return $parametrySmlouvy;
    }

}
