#!/usr/bin/env php
<?php

/**
 * Digisign API
 * Vytvoření tzv. obálky a odeslání smlouvy k podpisu pro připojence hkfree.org
 */
use DigitalCz\DigiSign\DigiSign;

$container =  require __DIR__ . '/../app/bootstrap.php';

if (!getenv('DIGISIGN_ACCESS_KEY') || !getenv('DIGISIGN_SECRET_KEY')) {
    print("Missing DIGISIGN_ACCESS_KEY or DIGISIGN_SECRET_KEY environment variables\n");
    die();
}

if (!isset($argv[1])) {
    print("Use: {$argv[0]} <smlouva_id>\n");
    die();
}

$templateId = "0193a831-9ccd-7152-9797-752fa40014f6"; # účastnická smlouva v6

$smlouva_id = $argv[1];

$smlouva = $container->getByType('\App\Model\Smlouva')->find($smlouva_id);
if (!$smlouva) {
    print("Smlouva id [$smlouva_id] neni v DB\n");
    die();
}

$uzivatel = $container->getByType('\App\Model\Uzivatel')->find($smlouva->uzivatel_id);
$cestneClenstviUzivatele = $container->getByType('App\Model\CestneClenstviUzivatele');

sleep(2); // to trochu zpřehlední logy

// $uzivatel = $container->getByType('\App\Model\Uzivatel')->find($smlouva->uzivatel_id);
$uzivatel = $container->getByType('\App\Model\Uzivatel')->find(1001);
$cestneClenstviUzivatele = $container->getByType('App\Model\CestneClenstviUzivatele');

print("Generovat ucastnickou smlouvu smlouva_id $smlouva_id uid $uzivatel->id ($uzivatel->jmeno $uzivatel->prijmeni \"$uzivatel->nick\") $uzivatel->email\n");

$dgs = new DigiSign([
  'access_key' => getenv('DIGISIGN_ACCESS_KEY'),
  'secret_key' => getenv('DIGISIGN_SECRET_KEY')
]);

$ENVELOPES = $dgs->envelopes();

function trace_to_file($what, $payload = null) {
    global $debugCounter;
    file_put_contents(
        "trace" . (++$debugCounter) . "-" . $what . ".json",
        json_encode($payload, JSON_PRETTY_PRINT)
    );
}
function set_tag_value($envelope, $tagLabel, $newValue) {
    global $ENVELOPES;

    print("set_tag_value $tagLabel $newValue\n");
    $tags = $ENVELOPES->tags($envelope)->list()->items;

    $tag = current(array_filter($tags, function ($t) use ($tagLabel) {
        return $t->label == $tagLabel;
    }));

    $ENVELOPES->tags($envelope)->update($tag->id, [
      'value' => $newValue,
      'height' => 13
    ]);
}

$jmenoString = sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni);
$adresaString = sprintf("%s, %s %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto);
$cenaString = sprintf('290 Kč za měsíc včetně DPH.');
if ($cestneClenstviUzivatele->getHasCC($uzivatel->id)) {
    $cenaString = sprintf('0 Kč (zdarma)');
}

$krok = 0;

printf("Krok %u: check template\n", ++$krok);
$template = $dgs->envelopeTemplates()->get($templateId);
printf("Template %s name: \"%s\" file: %s\n", $template->id, $template->name, $template->documents[0]->name);

printf("Krok %u: create envelope from template\n", ++$krok);
$envelope = $dgs->envelopeTemplates()->use($templateId);
$envelopeId = $envelope->id;
printf("Envelope: https://app.digisign.org/selfcare/envelopes/%s/detail", $envelopeId);

$envelope = $ENVELOPES->get($envelopeId);

printf("Krok %u: document name obsahuje UID\n", ++$krok);
$doc1 = $ENVELOPES->documents($envelope)->get($envelope->documents[0]->id);
$ENVELOPES->documents($envelope)->update($doc1->id, [
  'name' => str_replace('template', "uid{$uzivatel->id}", $doc1->name)
]);

printf("Krok %u: envelope subject obsahuje jmeno+prijmeni\n", ++$krok);
$ENVELOPES->update($envelopeId, [
  'emailSubject' => $envelope->emailSubject . ' ' . $jmenoString . ' ' . $uzivatel->firma_nazev,
]);

printf("Krok %u: recipient details obsahuje adresu, cenu, uid\n", ++$krok);
$recipient_details = [
  'name' => $jmenoString,
  'birthdate' => $uzivatel->datum_narozeni,
  'email' => $uzivatel->email,
  'mobile' => $uzivatel->telefon,
  'address' => $adresaString,
  'contractingParty' => (string) $uzivatel->id,
  'emailBody' => str_replace(
      ['{UID}',        '{cena}',   '{adresa}'],
      [$uzivatel->id, $cenaString, $adresaString],
      $envelope->emailBody
  ),
];
// print_r($recipient_details);
$recipient1 = $ENVELOPES->recipients($envelope)->get($envelope->recipients[0]->id);
$recipient2 = $ENVELOPES->recipients($envelope)->update(
    $recipient1->id,
    $recipient_details
);

printf("Krok %u: create tags {jmeno_prijmeni}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{jmeno_prijmeni}',
  "width" => 250,
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "name",
  "type" => "text",
]);
printf("Krok %u: create tags {2_radek}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{2_radek}',
  "label" => '2_radek',
  "width" => 100,
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);
printf("Krok %u: create tags {dnnf}\n", ++$krok);
if ($uzivatel->TypPravniFormyUzivatele->text == "FO") {
    set_tag_value($envelope, '2_radek', 'Datum narození:');
    printf("Krok %u: FO datum narození\n", ++$krok);
    $ENVELOPES->tags($envelope)->create([
      'document' => $doc1,
      'recipient' => $recipient1,
      "placeholder" => '{dnnf}',
      'readonly' => false,
      'required' => true,
      'label' => 'Datum narození',
      "recipientClaim" => "birthdate",
      "type" => "text",
    ]);
} elseif ($uzivatel->TypPravniFormyUzivatele->text == "PO") {
    set_tag_value($envelope, '2_radek', 'Firma:');
    printf("Krok %u: PO název firmy, IČO\n", ++$krok);
    $ENVELOPES->tags($envelope)->create([
      'document' => $doc1,
      'recipient' => $recipient1,
      "placeholder" => '{dnnf}',
      "width" => 400,
      'readonly' => true,
      'required' => false,
      'label' => 'datum_narozeni_nebo_firma',
      "type" => "text",
    ]);
    $firma = sprintf("%s, IČO: %s", $uzivatel->firma_nazev, $uzivatel->firma_ico);
    set_tag_value($envelope, 'datum_narozeni_nebo_firma', $firma);
}
printf("Krok %u: create tags {email}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{email}',
  "width" => 400,
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "email",
  "type" => "text",
]);
printf("Krok %u: create tags {telefon}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{telefon}',
  'readonly' => true,
  'required' => false,
  'label' => 'Telefon',
  "recipientClaim" => "mobile",
  "type" => "text",
]);
printf("Krok %u: create tags {adresa}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{adresa}',
  "width" => 400,
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "address",
  "type" => "text",
]);
printf("Krok %u: create tags {uid}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{uid}',
  "width" => 100,
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "contractingParty",
  "type" => "text",
]);
printf("Krok %u: create tags {uid2}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{uid2}',
  "width" => 100,
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "contractingParty",
  "type" => "text",
]);
printf("Krok %u: create tags {IPv4-1}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{IPv4-1}',
  "label" => 'IPv4-1',
  "width" => 120,
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);
printf("Krok %u: create tags {GW-1}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{GW-1}',
  "label" => 'GW-1',
  "width" => 100,
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);
printf("Krok %u: create tags {oblast-adresa}\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{oblast-adresa}',
  "label" => 'oblast-adresa',
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);

$envelope = $ENVELOPES->get($envelopeId);
// trace_to_file("envelope2", $envelope);

printf("Krok %u: tags - IP adresy\n", ++$krok);
// TODO oblast1234@hkfree.org
set_tag_value($envelope, 'oblast-adresa', sprintf('oblast0@hkfree.org'));
set_tag_value($envelope, 'IPv4-1', '10.107.99.99/24');
set_tag_value($envelope, 'GW-1', '10.107.99.1');

printf("Krok %u: tags - cena $cenaString\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{cena1}',
  "label" => 'cena1',
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{cena2}',
  "label" => 'cena2',
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);

set_tag_value($envelope, 'cena1', $cenaString);
set_tag_value($envelope, 'cena2', $cenaString);

printf("Krok %u: validate\n", ++$krok);
$ENVELOPES->validate($envelopeId);

printf("Krok %u: send ($uzivatel->email)\n", ++$krok);
$ENVELOPES->send($envelopeId);
