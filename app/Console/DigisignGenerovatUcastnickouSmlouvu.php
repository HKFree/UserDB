<?php

namespace App\Console;

use App\Model\Uzivatel;
use App\Model\Smlouva;
use App\Model\PodpisSmlouvy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DigitalCz\DigiSign\DigiSign;

#[AsCommand(
    name: 'app:digisign_generovat_ucastnickou_smlouvu',
    description: 'Digisign API. Vytvoření tzv. obálky a odeslání smlouvy k podpisu pro připojence hkfree.org.'
)]
class DigisignGenerovatUcastnickouSmlouvu extends Command
{
    private $templateId = "0193b32a-d60f-7077-9fae-123a91d1a308"; # účastnická smlouva v7 (bez PDFka)
    private $FILE_STORAGE_PATH = "/opt/userdb/smlouvy/ucastnickeSmlouvy/"; # sem se ukládají PDFka - smlouvy

    private $uzivatelModel;
    private $smlouvaModel;
    private $podpisSmlouvyModel;

    public function __construct(Uzivatel $uzivatelModel, Smlouva $smlouvaModel, PodpisSmlouvy $podpisSmlouvyModel) {
        parent::__construct();
        $this->uzivatelModel = $uzivatelModel;
        $this->smlouvaModel = $smlouvaModel;
        $this->podpisSmlouvyModel = $podpisSmlouvyModel;
    }

    protected function configure() {
        $this->addArgument('smlouva_id', InputArgument::REQUIRED, 'ID smlouvy (index do tabulky Smlouva)');

        if (!getenv('DIGISIGN_ACCESS_KEY') || !getenv('DIGISIGN_SECRET_KEY')) {
            throw new \Exception("Missing DIGISIGN_ACCESS_KEY or DIGISIGN_SECRET_KEY environment variables\n");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $smlouva_id = $input->getArgument('smlouva_id');

        $smlouva = $this->smlouvaModel->find($smlouva_id);
        if (!$smlouva) {
            throw new \Exception("Smlouva cislo [$smlouva_id] neni v DB\n");
        }

        is_dir($this->FILE_STORAGE_PATH) || mkdir($this->FILE_STORAGE_PATH);

        $uzivatel = $this->uzivatelModel->find($smlouva->uzivatel_id);

        $parametry = \App\Services\GeneratorSmlouvy::parametryUcastnickeSmlouvy($uzivatel, $smlouva_id);

        $smlouva->update(['parametry_smlouvy' => json_encode($parametry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]);

        print("Generovat ucastnickou smlouvu smlouva_id $smlouva_id uid $uzivatel->id ($uzivatel->jmeno $uzivatel->prijmeni \"$uzivatel->nick\") $uzivatel->email\n");

        $dgs = new DigiSign([
          'access_key' => getenv('DIGISIGN_ACCESS_KEY'),
          'secret_key' => getenv('DIGISIGN_SECRET_KEY')
        ]);

        $ENVELOPES = $dgs->envelopes();

        $krok = 0;

        printf("Krok %u: check template\n", ++$krok);
        $template = $dgs->envelopeTemplates()->get($this->templateId);
        printf("Template %s name: \"%s\"\n", $template->id, $template->title);
        $smlouva->update(['sablona' => $template->title]);

        printf("Krok %u: create envelope from template\n", ++$krok);
        $envelope = $dgs->envelopeTemplates()->use($this->templateId);
        $envelopeId = $envelope->id;

        printf("Envelope: https://app.digisign.org/selfcare/envelopes/%s/detail\n", $envelopeId);
        printf("Krok %u: UPDATE Smlouva: externi_id=%s\n", ++$krok, $envelopeId);
        $smlouva->update(['externi_id' => $envelopeId]);

        $envelope = $ENVELOPES->get($envelopeId);
        // $this::trace_to_file('envelope', $envelope);

        $documentName = "SmlouvaUcastnicka_{$smlouva_id}_uid{$uzivatel->id}.pdf";
        printf("Krok %u: vygenerovat a predvyplnit PDF podle sablony\n", ++$krok);
        $documentFullName = "{$this->FILE_STORAGE_PATH}/$documentName";
        $pdfData = \App\Services\GeneratorSmlouvy::nahledUcastnickeSmlouvy($uzivatel, $smlouva_id);
        file_put_contents($documentFullName, $pdfData);
        $stream = \DigitalCz\DigiSign\Stream\FileStream::open($documentFullName);
        $file = $dgs->files()->upload($stream);

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
        $smlouva->update(['podepsany_dokument_path' => $documentFullName]);

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
        // Podpisy za družstvo jsou hardcoded v šabloně
        $this->podpisSmlouvyModel->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Vojtěch Pithart', 'kdy_podepsano' => new \DateTime()]);
        $this->podpisSmlouvyModel->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Petr Mikeš', 'kdy_podepsano' => new \DateTime()]);
        // Podpis účastníka
        $this->podpisSmlouvyModel->insert(['Smlouva_id' => $smlouva_id, 'smluvni_strana' => 'ucastnik', 'jmeno' => $parametry['jmeno_prijmeni'], 'kdy_podepsano' => null]);

        printf("Krok %u: validate\n", ++$krok);
        $ENVELOPES->validate($envelopeId);

        printf("Krok %u: send ($uzivatel->email)\n", ++$krok);
        $ENVELOPES->send($envelopeId);

        return 0;
    }

    protected static function trace_to_file($what, $payload = null) {
        global $debugCounter;
        file_put_contents(
            "trace" . (++$debugCounter) . "-" . $what . ".json",
            json_encode($payload, JSON_PRETTY_PRINT)
        );
    }

}
