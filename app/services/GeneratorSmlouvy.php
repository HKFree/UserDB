<?php

namespace App\Services;

use DateTime;
use App\Model;

class GeneratorSmlouvy
{
    /**
     * Náhled smlouvy - rovnou vrací PDFko
     * @param \Nette\Database\Table\ActiveRow $uzivatel
     * @param int|string|null $cislo_smlouvy
     */
    public static function nahledUcastnickeSmlouvy($uzivatel, $cislo_smlouvy = null): string|false {

        $parametry = self::parametryUcastnickeSmlouvy($uzivatel, $cislo_smlouvy);

        if (getenv('USERDB_LOCAL_PDF') === '1') {
            require_once __DIR__ . '/../../pdfGenerator/www/ContractPdf.php';
            return \ContractPdf::generate(
                $parametry,
                __DIR__ . '/../../pdfGenerator/templates/SmlouvaUcastnicka_v7_template.odt',
                getenv('USERDB_LIBREOFFICE') ?: 'libreoffice',
                getenv('FLOX_ENV_CACHE') . '/pdf'
            );
        }

        if (in_array(getenv('PDF_GENERATOR_URL'), [false, '', '0'], true)) {
            throw new \Exception("Missing PDF_GENERATOR_URL environment variable\n");
        }

        $url = getenv('PDF_GENERATOR_URL')."/smlouvaUcastnicka.php";
        $params = http_build_query($parametry);
        return file_get_contents("$url?$params");
    }

    /**
     * @param \Nette\Database\Table\ActiveRow $uzivatel
     * @param int|string|null $cislo_smlouvy
     * @return array<string, string|int|null>
     */
    public static function parametryUcastnickeSmlouvy($uzivatel, $cislo_smlouvy = null): array {
        global $container;

        $cestneClenstviUzivateleModel = $container->getByType('App\Model\CestneClenstviUzivatele');

        $jmenoString = sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni);
        $adresaString = sprintf("%s, %s %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto);
        $cenaString = '290 Kč za započatý kalendářní měsíc';
        $cenaString .= ' (včetně DPH)'; // tohle odkomentovat až bude družstvo plátcem DPH
        if ($cestneClenstviUzivateleModel->getHasCC($uzivatel->id)) {
            $cenaString = '0 Kč (zdarma)';
        }

        $subjectPrefix = getenv('AGREEMENT_NAME_PREFIX');
        if ($subjectPrefix !== false && $subjectPrefix !== '' && $subjectPrefix !== '0') {
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
            if (isset($subnet["error"]) === false) {
                $gateway = $subnet["gateway"];
                $adresaLomenoMaska .= "/" . $subnet["cidr"];
            }

            array_push($ip4Adresy, [$adresaLomenoMaska, $gateway]);
        }

        $parametrySmlouvy = [
          'cislo' => $cislo_smlouvy ?? '0',
          'ze_dne' => (new DateTime())->format('d.m.Y'),
          'uid' => (string) $uzivatel->id,
          'jmeno_prijmeni' => $jmenoString,
          'upresneni' => 'Datum narození',
          'datum_narozeni' => $uzivatel->datum_narozeni !== null ? $uzivatel->datum_narozeni->format('d.m.Y') : '',
          'email' => $uzivatel->email,
          'telefon' => $uzivatel->telefon,
          'adresa' => $adresaString,
          'email_spravce_oblasti' => sprintf('oblast%u@hkfree.org', $uzivatel->Ap->Oblast->id),
          'cena' => $cenaString
        ];
        if ($uzivatel->TypPravniFormyUzivatele->text === "PO") {
            $parametrySmlouvy['upresneni'] = 'Firma';
            $parametrySmlouvy['datum_narozeni'] = '';
            $parametrySmlouvy['firma'] = sprintf("%s, IČO: %s", $uzivatel->firma_nazev, $uzivatel->firma_ico);
        }
        for ($i = 1; $i <= count($ip4Adresy); ++$i) {
            $parametrySmlouvy["IPv4-$i"] = $ip4Adresy[$i - 1][0];
            $parametrySmlouvy["GW-$i"] = $ip4Adresy[$i - 1][1];
            if ($i === 4) {
                $parametrySmlouvy["IPv4-more"] = sprintf('... a další (%u)', count($ip4Adresy) - 4);
                break;
            }
        }

        return $parametrySmlouvy;
    }

}
