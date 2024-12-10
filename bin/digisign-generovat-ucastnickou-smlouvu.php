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

$ip4Adresy = [];
$IPAdresa = $container->getByType('App\Model\IPAdresa');

foreach (
    $IPAdresa->findAll()->where(['Uzivatel_id' => $smlouva->uzivatel_id])->fetchPairs('ip_adresa') as $adresa => $record
) {
    $adresaLomenoMaska = "$adresa/24"; // TODO /24
    $gateway = ""; // TODO: k IP adresám dotáhnout a zobrazovat gatewaye
    array_push($ip4Adresy, [$adresaLomenoMaska, $gateway]);
}

sleep(1); // to trochu zpřehlední logy

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

    if ($tag) {
        $ENVELOPES->tags($envelope)->update($tag->id, [
          'value' => $newValue,
          'height' => 13
        ]);
    }
}

$jmenoString = sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni);
$adresaString = sprintf("%s, %s %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto);
$cenaString = sprintf('290 Kč za měsíc včetně DPH.');
if ($cestneClenstviUzivatele->getHasCC($uzivatel->id)) {
    $cenaString = sprintf('0 Kč (zdarma)');
}

$parametrySmlouvy = [
  'nazev1' => $jmenoString,
  'datum_narozeni' => $uzivatel->datum_narozeni,
  'email' => $uzivatel->email,
  'telefon' => $uzivatel->telefon,
  'adresa' => $adresaString,
  'uid' => (string) $uzivatel->id,
  'ip4Adresy' => $ip4Adresy,
  'emailSpravceOblasti' => sprintf('oblast%u@hkfree.org', $uzivatel->Ap->Oblast->id)
];
if ($uzivatel->TypPravniFormyUzivatele->text == "PO") {
    $parametrySmlouvy['nazev2'] = sprintf("%s, IČO: %s", $uzivatel->firma_nazev, $uzivatel->firma_ico);
}

$smlouva->update(['parametry_smlouvy' => json_encode($parametrySmlouvy, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]);

$krok = 0;

printf("Krok %u: check template\n", ++$krok);
$template = $dgs->envelopeTemplates()->get($templateId);
printf("Template %s name: \"%s\" file: %s\n", $template->id, $template->title, $template->documents[0]->name);
$smlouva->update(['sablona' => $template->title]);
trace_to_file('ttt', $template);

printf("Krok %u: create envelope from template\n", ++$krok);
$envelope = $dgs->envelopeTemplates()->use($templateId);
$envelopeId = $envelope->id;
printf("Envelope: https://app.digisign.org/selfcare/envelopes/%s/detail\n", $envelopeId);

printf("Krok %u: UPDATE Smlouva: externi_id=%s\n", ++$krok, $envelopeId);
$smlouva->update(['externi_id' => $envelopeId]);

$envelope = $ENVELOPES->get($envelopeId);

$doc1 = $ENVELOPES->documents($envelope)->get($envelope->documents[0]->id);
$documentName = str_replace('template', "{$smlouva_id}_uid{$uzivatel->id}", $doc1->name);

printf("Krok %u: document name: %s\n", ++$krok, $documentName);
$ENVELOPES->documents($envelope)->update($doc1->id, [  'name' => $documentName]);

printf("Krok %u: UPDATE Smlouva: podepsany_dokument=%s\n", ++$krok, $documentName);
$smlouva->update(['podepsany_dokument_nazev' => $documentName]);
$smlouva->update(['podepsany_dokument_content_type' => 'application/pdf']);

$emailSubject = $envelope->emailSubject . ' ' . $jmenoString . ' ' . $uzivatel->firma_nazev;
printf("Krok %u: envelope subject: \"%s\"\n", ++$krok, $emailSubject);
$ENVELOPES->update($envelopeId, [
  'emailSubject' => $emailSubject,
]);

$recipient1 = $ENVELOPES->recipients($envelope)->get($envelope->recipients[0]->id);
printf("Krok %u: recipient details: %s=\"%s\"\n", ++$krok, 'name', $parametrySmlouvy['nazev1']);
printf("Krok %u: recipient details: %s=\"%s\"\n", ++$krok, 'birthdate', $parametrySmlouvy['datum_narozeni']);
printf("Krok %u: recipient details: %s=\"%s\"\n", ++$krok, 'addresa', $parametrySmlouvy['adresa']);
printf("Krok %u: recipient details: %s=\"%s\"\n", ++$krok, 'address', $adresaString);
printf("Krok %u: recipient details: %s=\"%s\"\n", ++$krok, 'contractingParty', $parametrySmlouvy['uid']);
$recipient2 = $ENVELOPES->recipients($envelope)->update(
    $recipient1->id,
    [
      'name' => $parametrySmlouvy['nazev1'],
      'birthdate' => $parametrySmlouvy['datum_narozeni'],
      'email' => $parametrySmlouvy['email'],
      'address' => $parametrySmlouvy['adresa'],
      'contractingParty' => $parametrySmlouvy['uid'],
      'emailBody' => str_replace(
          ['{UID}',        '{cena}',   '{adresa}'],
          [$uzivatel->id, $cenaString, $adresaString],
          $envelope->emailBody
      ),
    ]
);

printf("Krok %u: INSERT INTO PodmisSmlouvy\n", ++$krok);
$Podpis = $container->getByType('\App\Model\PodpisSmlouvy');
// Podpisy za družstvo jsou hardcoded v šabloně
$Podpis->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Vojtěch Pithart', 'kdy_podepsano' => new DateTime()]);
$Podpis->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Petr Mikeš', 'kdy_podepsano' => new DateTime()]);
// Podpis účastníka
$Podpis->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'ucastnik', 'jmeno' => $jmenoString, 'kdy_podepsano' => null]);

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
    set_tag_value($envelope, 'datum_narozeni_nebo_firma', $parametrySmlouvy['firma']);
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
  'label' => 'telefon',
  // "recipientClaim" => "mobile",
  "type" => "text",
]);
set_tag_value($envelope, 'telefon', $parametrySmlouvy['telefon']);
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

// TODO oblast1234@hkfree.org
printf("Krok %u: tags - oblast-adresa\n", ++$krok);
set_tag_value($envelope, 'oblast-adresa', $parametrySmlouvy['emailSpravceOblasti']);

printf("Krok %u: tags - IP adresy\n", ++$krok);
// V šabloně:
// {IPv4-1}			{GW-1}			10.107.4.100, 10.107.4.129
// {IPv4-2}			{GW-2}
// {IPv4-3}			{GW-3}
// {IPv4-4}			{GW-4}
// {IPv4-more}
for ($i = 1; $i <= count($parametrySmlouvy['ip4Adresy']); ++$i) {
    printf("Krok %u: create tags {IPv4-$i}\n", ++$krok);
    $ENVELOPES->tags($envelope)->create([
      'document' => $doc1,
      'recipient' => $recipient1,
      "placeholder" => "{IPv4-$i}",
      "label" => "IPv4-$i",
      "width" => 120,
      "type" => "text",
      "readonly" => true,
      "required" => false
    ]);
    set_tag_value($envelope, "IPv4-$i", $parametrySmlouvy['ip4Adresy'][$i - 1][0]);
    printf("Krok %u: create tags {GW-$i}\n", ++$krok);
    $ENVELOPES->tags($envelope)->create([
      'document' => $doc1,
      'recipient' => $recipient1,
      "placeholder" => "{GW-$i}",
      "label" => "GW-$i",
      "width" => 100,
      "type" => "text",
      "readonly" => true,
      "required" => false
    ]);
    set_tag_value($envelope, "GW-$i", $parametrySmlouvy['ip4Adresy'][$i - 1][1]);  // TODO
    if ($i == 4) {
        $ENVELOPES->tags($envelope)->create([
          'document' => $doc1,
          'recipient' => $recipient1,
          "placeholder" => "{IPv4-more}",
          "label" => "IPv4-more",
          "width" => 100,
          "type" => "text",
          "readonly" => true,
          "required" => false
            ]);
        set_tag_value($envelope, "IPv4-more", sprintf('... a další (%u)', count($parametrySmlouvy['ip4Adresy']) - 4));
        break;
    }
}

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
