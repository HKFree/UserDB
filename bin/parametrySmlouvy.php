#!/usr/bin/env php
<?php

$container =  require __DIR__ . '/../app/bootstrap.php';

function parametryUcastnickeSmlouvy($uid) {
    global $container;

    $uzivatel = $container->getByType('\App\Model\Uzivatel')->find($uid);
    $cestneClenstviUzivatele = $container->getByType('App\Model\CestneClenstviUzivatele');

    $jmenoString = sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni);
    $adresaString = sprintf("%s, %s %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto);
    $cenaString = sprintf('290 Kč za měsíc včetně DPH');
    if ($cestneClenstviUzivatele->getHasCC($uzivatel->id)) {
        $cenaString = sprintf('0 Kč (zdarma)');
    }

    $ip4Adresy = [];
    $IPAdresa = $container->getByType('App\Model\IPAdresa');
    foreach (
        $IPAdresa->findAll()->where(['Uzivatel_id' => $uid])->fetchPairs('ip_adresa') as $adresa => $record
    ) {
        $adresaLomenoMaska = "$adresa/24"; // TODO /24
        $gateway = ""; // TODO: k IP adresám dotáhnout a zobrazovat gatewaye
        array_push($ip4Adresy, [$adresaLomenoMaska, $gateway]);
    }

    $parametrySmlouvy = [
      'cislo' => '0',
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
