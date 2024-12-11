#!/usr/bin/env php
<?php

/**
 * Digisign API
 * Vytvoření tzv. obálky a odeslání smlouvy k podpisu pro připojence hkfree.org
 */
use DigitalCz\DigiSign\DigiSign;

require __DIR__ . '/parametrySmlouvy.php';

$container =  require __DIR__ . '/../app/bootstrap.php';

if (!getenv('DIGISIGN_ACCESS_KEY') || !getenv('DIGISIGN_SECRET_KEY')) {
    print("Missing DIGISIGN_ACCESS_KEY or DIGISIGN_SECRET_KEY environment variables\n");
    die();
}

if (!getenv('PDF_GENERATOR_URL')) {
    print("Missing PDF_GENERATOR_URL environment variable\n");
    die();
}

if (!isset($argv[1])) {
    print("Use: {$argv[0]} <smlouva_id>\n");
    die();
}

$templateId = "0193b32a-d60f-7077-9fae-123a91d1a308"; # účastnická smlouva v7 (bez PDFka)

$smlouva_id = $argv[1];

$smlouva = $container->getByType('\App\Model\Smlouva')->find($smlouva_id);
if (!$smlouva) {
    print("Smlouva id [$smlouva_id] neni v DB\n");
    die();
}

function trace_to_file($what, $payload = null) {
    global $debugCounter;
    file_put_contents(
        "trace" . (++$debugCounter) . "-" . $what . ".json",
        json_encode($payload, JSON_PRETTY_PRINT)
    );
}

$uzivatel = $container->getByType('\App\Model\Uzivatel')->find($smlouva->uzivatel_id);

$parametry = parametryUcastnickeSmlouvy($uzivatel->id);
$parametry['cislo'] = $smlouva_id;

$smlouva->update(['parametry_smlouvy' => json_encode($parametry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]);

sleep(1); // to trochu zpřehlední logy

print("Generovat ucastnickou smlouvu smlouva_id $smlouva_id uid $uzivatel->id ($uzivatel->jmeno $uzivatel->prijmeni \"$uzivatel->nick\") $uzivatel->email\n");

$dgs = new DigiSign([
  'access_key' => getenv('DIGISIGN_ACCESS_KEY'),
  'secret_key' => getenv('DIGISIGN_SECRET_KEY')
]);

$ENVELOPES = $dgs->envelopes();

$krok = 0;

printf("Krok %u: check template\n", ++$krok);
$template = $dgs->envelopeTemplates()->get($templateId);
printf("Template %s name: \"%s\"\n", $template->id, $template->title);
$smlouva->update(['sablona' => $template->title]);

printf("Krok %u: create envelope from template\n", ++$krok);
$envelope = $dgs->envelopeTemplates()->use($templateId);
$envelopeId = $envelope->id;

printf("Envelope: https://app.digisign.org/selfcare/envelopes/%s/detail\n", $envelopeId);
printf("Krok %u: UPDATE Smlouva: externi_id=%s\n", ++$krok, $envelopeId);
$smlouva->update(['externi_id' => $envelopeId]);

$envelope = $ENVELOPES->get($envelopeId);

printf("Krok %u: vygenerovat a predvyplnit PDF podle sablony\n", ++$krok);
$tmpname = sprintf('/dev/shm/document_%u.pdf', rand(1, 1e9));
exec(__DIR__."/nahled-ucastnicke-smlouvy.php {$uzivatel->id} {$smlouva_id} > $tmpname", );
$stream = DigitalCz\DigiSign\Stream\FileStream::open($tmpname);
$file = $dgs->files()->upload($stream);
unlink($tmpname);

$documentName = "SmlouvaUcastnicka_{$smlouva_id}_uid{$uzivatel->id}.pdf";
$document = $ENVELOPES->documents($envelope)->create([
  'name' => $documentName,
  'file' => $file->self()
]);

$recipient1 = $ENVELOPES->recipients($envelope)->get($envelope->recipients[0]->id);

printf("Krok %u: podpisovy tag\n", ++$krok);
$tag = $ENVELOPES->tags($envelope)->create([
  'type' => 'signature',
  'document' => $document,
  'recipient' => $recipient1,
  'page' => 1,
  'xPosition' => 300,
  'yPosition' => 675,
  'scale' => 152
]);

printf("Krok %u: UPDATE Smlouva: podepsany_dokument=%s\n", ++$krok, $documentName);
$smlouva->update(['podepsany_dokument_nazev' => $documentName]);
$smlouva->update(['podepsany_dokument_content_type' => 'application/pdf']);

$emailSubject = $envelope->emailSubject . ' ' . $parametry['jmeno_prijmeni'] . ' ' . $uzivatel->firma_nazev;
printf("Krok %u: envelope subject: \"%s\"\n", ++$krok, $emailSubject);
$ENVELOPES->update($envelopeId, [
  'emailSubject' => $emailSubject,
]);

printf("Krok %u: recipient details\n", ++$krok);
$recipient2 = $ENVELOPES->recipients($envelope)->update(
    $recipient1->id,
    [
      'name' => trim($parametry['jmeno_prijmeni'] . ' ' . $uzivatel->firma_nazev),
      'email' => $parametry['email'],
      'address' => $parametry['adresa'],
      'emailBody' => str_replace(
          ['{UID}',        '{cena}',   '{adresa}'],
          [$uzivatel->id, $parametry['cena'], $parametry['adresa']],
          $envelope->emailBody
      ),
    ]
);

// Nemame datum narozeni? -> vlozit editovatelny policko
if ($uzivatel->TypPravniFormyUzivatele->text == "FO" && !$uzivatel->datum_narozeni) {
    printf("Krok %u: Nemame datum narozeni -> vlozit editovatelny policko\n", ++$krok);
    $parametry['datum_narozeni'] = '';
    $parametry['firma'] = '';
    $ENVELOPES->tags($envelope)->create([
      'document' => $document,
      'recipient' => $recipient1,
      "placeholder" => '[datum_narozeni_input]',
      "width" => 100,
      'readonly' => false,
      'required' => true,
      'label' => 'Datum narození',
      "recipientClaim" => "birthdate",
      "type" => "text",
    ]);
}

printf("Krok %u: INSERT INTO PodmisSmlouvy\n", ++$krok);
$Podpis = $container->getByType('\App\Model\PodpisSmlouvy');
// Podpisy za družstvo jsou hardcoded v šabloně
$Podpis->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Vojtěch Pithart', 'kdy_podepsano' => new DateTime()]);
$Podpis->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Petr Mikeš', 'kdy_podepsano' => new DateTime()]);
// Podpis účastníka
$Podpis->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'ucastnik', 'jmeno' => $parametry['jmeno_prijmeni'], 'kdy_podepsano' => null]);

printf("Krok %u: validate\n", ++$krok);
$ENVELOPES->validate($envelopeId);

printf("Krok %u: send ($uzivatel->email)\n", ++$krok);
$ENVELOPES->send($envelopeId);
