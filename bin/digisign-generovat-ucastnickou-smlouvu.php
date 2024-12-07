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

$templateId = getenv('DIGISIGN_UCASTNICKA_SABLONA_ID');
if (!$templateId) {
    print("Missing DIGISIGN_UCASTNICKA_SABLONA_ID environment variable\n");
    die();
}

if (!isset($argv[1])) {
    print("Use: {$argv[0]} <smlouva_id>\n");
    die();
}

$smlouva_id = $argv[1];

/*
$smlouva = $container->getByType('\App\Model\Smlouva')->find($smlouva_id);
if ($smlouva) {
  print("Smlouva id [$smlouva_id] neni v DB\n");
  die();
}
*/

// $uzivatel = $container->getByType('\App\Model\Uzivatel')->find($smlouva->uzivatel_id);
$uzivatel = $container->getByType('\App\Model\Uzivatel')->find(1002);

print("Generovat ucastnickou smlouvu smlouva_id $smlouva_id uid $uzivatel->id ($uzivatel->jmeno $uzivatel->prijmeni \"$uzivatel->nick\") $uzivatel->email\n");

$dgs = new DigiSign([
  'access_key' => getenv('DIGISIGN_ACCESS_KEY'),
  'secret_key' => getenv('DIGISIGN_SECRET_KEY')
]);

$ENVELOPES = $dgs->envelopes();

function trace_to_file($what, $payload = null)
{
    global $debugCounter;
    file_put_contents(
        "trace" . (++$debugCounter) . "-" . $what . ".json",
        json_encode($payload, JSON_PRETTY_PRINT)
    );
}
function set_tag_value($envelope, $tagLabel, $newValue)
{
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

// print("TODO tady bude generování smlouvy - START\n");
// sleep(15);
// print("TODO tady bude generování smlouvy - DONE\n");
// exit;

$krok = 0;
printf("Krok %u: create envelope from template\n", ++$krok);
$envelope = $dgs->envelopeTemplates()->use($templateId);
$envelopeId = $envelope->id;

$envelope = $ENVELOPES->get($envelopeId);
// trace_to_file("envelope1", $envelope);

printf("Krok %u: document name\n", ++$krok);
$doc1 = $ENVELOPES->documents($envelope)->get($envelope->documents[0]->id);
$ENVELOPES->documents($envelope)->update($doc1->id, [
  'name' => str_replace('template', "uid{$uzivatel->id}", $doc1->name)
]);

printf("Krok %u: recipient details\n", ++$krok);
$recipient1 = $ENVELOPES->recipients($envelope)->get($envelope->recipients[0]->id);
$recipient2 = $ENVELOPES->recipients($envelope)->update(
    $recipient1->id,
    [
    'name' => sprintf("%s %s", $uzivatel->jmeno, $uzivatel->prijmeni),
    'birthdate' => $uzivatel->datum_narozeni,
    'email' => $uzivatel->email,
    'mobile' => $uzivatel->telefon,
    'address' => sprintf("%s, %s", $uzivatel->ulice_cp, $uzivatel->psc, $uzivatel->mesto),
    'contractingParty' => $uzivatel->id,
    'emailSubject' => $envelope->emailSubject,
    'emailBody' => str_replace('{UID}', $uzivatel->id, $envelope->emailBody),
  ]
);

printf("Krok %u: create tags\n", ++$krok);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{jmeno_prijmeni}',
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "name",
  "type" => "text",
]);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{datum_narozeni}',
  'readonly' => false,
  'required' => true,
  'label' => 'Datum narození',
  "recipientClaim" => "birthdate",
  "type" => "text",
]);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{email}',
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "email",
  "type" => "text",
]);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{telefon}',
  'readonly' => false,
  'required' => true,
  'label' => 'Telefon',
  "recipientClaim" => "mobile",
  "type" => "text",
]);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{adresa}',
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "address",
  "type" => "text",
]);
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
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{IPv4-1}',
  "label" => 'IPv4-1',
  "width" => 150,
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{GW-1}',
  "label" => 'GW-1',
  "width" => 120,
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);
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
trace_to_file("envelope2", $envelope);

printf("Krok %u: tags - IP adresy\n", ++$krok);
set_tag_value($envelope, 'oblast-adresa', 'oblast0@hkfree.org');
set_tag_value($envelope, 'IPv4-1', '10.107.99.99/24');
set_tag_value($envelope, 'GW-1', '10.107.99.1');

printf("Krok %u: validate\n", ++$krok);
$ENVELOPES->validate($envelopeId);

// printf("Krok %u: send\n", ++$krok);
// $ENVELOPES->send($envelopeId);
