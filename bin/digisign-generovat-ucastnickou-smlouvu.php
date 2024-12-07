#!/usr/bin/env php

<?php
/**
 * Digisign API
 * Vytvoření tzv. obálky a odeslání smlouvy k pordpisu pro připojence hkfree.org
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

$smlouva_id = $argv[1];

$uzivatelModel = $container->getByType('\App\Model\Smlouva');
$smlouva = $uzivatelModel->find($smlouva_id);

if ($smlouva) {
    print("Smlouva id [$smlouva_id] neni v DB\n");
    die();
}

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

print("TODO tady bude generování smlouvy - START\n");
sleep(15);
print("TODO tady bude generování smlouvy - DONE\n");
exit;

// $templateId = "0193683c-2123-7025-b014-e42fb5aa5f46"; // účastnická smlouva v3
// $templateId = "01936d33-6eb7-735f-af92-0cd924135cc6"; // migrace + účastnická smlouva v4
$templateId = "01938b74-b86e-7361-9ab9-17e7b2671225"; // migrace + účastnická smlouva v5

$envelope = $dgs->envelopeTemplates()->use($templateId);
$envelopeId = $envelope->id;
print("envelopeId: $envelopeId\n");

// $envelopeId = '01936d47-edfe-7024-a184-b84e690b1cfb';

$envelope = $ENVELOPES->get($envelopeId);
trace_to_file("envelope1", $envelope);

$UID = '10009';

$doc1 = $ENVELOPES->documents($envelope)->get($envelope->documents[0]->id);
$doc2 = $ENVELOPES->documents($envelope)->update($doc1->id, [
  'name' => str_replace('template', "uid{$UID}", $doc1->name)
]);

$recipient1 = $ENVELOPES->recipients($envelope)->get($envelope->recipients[0]->id);
$recipient2 = $ENVELOPES->recipients($envelope)->update(
    $recipient1->id,
    [
    'name' => 'Josef Skočdopole IX',
    'birthdate' => "11.12.2003",
    'email' => 'vpithart+test9@lhota.hkfree.org',
    'mobile' => '720300409',
    'address' => 'Pražská 987, 50002 Hradec Králové',
    'contractingParty' => $UID,
    'emailSubject' => $envelope->emailSubject,
    'emailBody' => str_replace('{UID}', $UID, $envelope->emailBody),
  ]
);
print("A\n");
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{jmeno_prijmeni}',
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "name",
  "type" => "text",
]);
print("A2\n");
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
print("B\n");
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{email}',
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "email",
  "type" => "text",
]);
print("C\n");
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
print("D\n");
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{adresa}',
  'readonly' => true,
  'required' => false,
  "recipientClaim" => "address",
  "type" => "text",
]);
print("E\n");
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
print("E2\n");
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
print("F\n");
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
print("G\n");
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
print("H\n");
$ENVELOPES->tags($envelope)->create([
  'document' => $doc1,
  'recipient' => $recipient1,
  "placeholder" => '{oblast-adresa}',
  "label" => 'oblast-adresa',
  'readonly' => true,
  'required' => false,
  "type" => "text",
]);

print("I\n");
$envelope = $ENVELOPES->get($envelopeId);
trace_to_file("envelope2", $envelope);

set_tag_value($envelope, 'oblast-adresa', 'oblast0@hkfree.org');
set_tag_value($envelope, 'IPv4-1', '10.107.99.99/24');
set_tag_value($envelope, 'GW-1', '10.107.99.1');

$ENVELOPES->validate($envelopeId);

$ENVELOPES->send($envelopeId);
