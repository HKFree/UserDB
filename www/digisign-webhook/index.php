<?php

include __DIR__ . '/../../vendor/autoload.php';

$bootstrap = new App\Bootstrap();
$container = $bootstrap->boot();

use DigitalCz\DigiSign\DigiSign;

\Tracy\Debugger::$showBar = false;
\Tracy\Debugger::$logSeverity = E_ALL;

$FILE_STORAGE_PATH = getenv('FILE_STORAGE_PATH') ?: '/tmp';

function print_and_log($message) {
    error_log("digisign_webhook: $message.");
    print("$message.\n");
}

$body = file_get_contents('php://input');
// $body = '{"id":"0193b7d7-4941-7217-ad91-f2cf0ca9a8e6","event":"envelopeCompleted","name":"envelope.completed","time":"2024-12-11T23:29:19+01:00","entityName":"envelope","entityId":"0193b1df-239d-7260-b479-111da9ddfbac","data":{"status":"completed"},"envelope":{"id":"0193b1df-239d-7260-b479-111da9ddfbac","status":"completed"}}';

if (strlen($body) < 10000) {
    error_log("digisign_webhook: payload: " . print_r($body, true));
}

$hook = json_decode($body);

if (!$hook) {
    print_and_log("invalidni request body, asi neplatny JSON");
    http_response_code(400);
    return;
}

print_and_log(sprintf("%s %s %s", $hook->event, $hook->entityName, $hook->entityId));

$UzivatelModel = $container->getByType(\App\Model\Uzivatel::class);
$SmlouvaModel = $container->getByType(\App\Model\Smlouva::class);
$PodpisSmlouvyModel = $container->getByType(\App\Model\PodpisSmlouvy::class);

$dgs = new DigiSign([
    'access_key' => getenv('DIGISIGN_ACCESS_KEY'),
    'secret_key' => getenv('DIGISIGN_SECRET_KEY')
]);
$ENVELOPES = $dgs->envelopes();

switch ($hook->event) {
    case 'envelopeSent': // obálka odeslána
        /**
         *  {"id":"0193b7bc-3bee-7393-b19d-02c49b5781a9","event":"envelopeSent","name":"envelope.sent","time":"2024-12-11T22:59:46+01:00","entityName":"envelope","entityId":"0193b7bc-1797-70f6-a095-231c750d3487","data":{"status":"sent"},"envelope":{"id":"0193b7bc-1797-70f6-a095-231c750d3487","status":"sent"}}
         */

        break;
    case 'envelopeCompleted': // obálka dokončena (podepsána všemi podepisujícími)
        /**
         * {"id":"0193b7d7-4941-7217-ad91-f2cf0ca9a8e6","event":"envelopeCompleted","name":"envelope.completed","time":"2024-12-11T23:29:19+01:00","entityName":"envelope","entityId":"0193b1df-239d-7260-b479-111da9ddfbac","data":{"status":"completed"},"envelope":{"id":"0193b1df-239d-7260-b479-111da9ddfbac","status":"completed"}}
         */
        $smlouva = $SmlouvaModel->findOneBy(['externi_id' => $hook->entityId]);
        if (!$smlouva) {
            print_and_log("nemame takovou smlouvu -> ignorovat");
            break;
        }
        // zpetne volat API a overit stav:
        $envelope = $ENVELOPES->get($hook->entityId);
        if ($envelope->status !== 'completed') {
            print_and_log("Nesedi envelope status ({$envelope->status})");
            break;
        }
        if ($smlouva->typ == 'ucastnicka') {
            // 1. uložit do DB že je smlouva podepsaná
            $podpis = $PodpisSmlouvyModel->findOneBy(['smlouva_id' => $smlouva->id, 'smluvni_strana' => 'ucastnik']);
            $podpis->update(['kdy_podepsano' => $hook->time]);
            print_and_log(sprintf("smlouva #%u podpis ucastnika \"%s\" datum/cas: %s", $smlouva->id, $podpis->jmeno, $hook->time));
            // 2. stáhnout podepsaný PDF a uložit
            $doc1id = $envelope->documents[0]->id;
            $fileResponse = $ENVELOPES->documents($envelope)->download($doc1id);
            $documentFullName = "{$FILE_STORAGE_PATH}/{$envelope->documents[0]->name}";
            $fileResponse->save($documentFullName);
            print_and_log(sprintf('Ulozeno do %s', $documentFullName));
            $smlouva->update(['podepsany_dokument_nazev' => $envelope->documents[0]->name]);
            $smlouva->update(['podepsany_dokument_content_type' => 'application/pdf']);
            $smlouva->update(['podepsany_dokument_path' => $documentFullName]);
            // 3. pokud nemáme datum narození, zkusíme parsovat vyplněný ze smlouvy
            $uzivatel = $UzivatelModel->find($smlouva->uzivatel);
            if (!$uzivatel->datum_narozeni) {
                $recipient1 = $ENVELOPES->recipients($envelope)->get($envelope->recipients[0]->id);
                $tags = $recipient1->tags->toArray();
                foreach ($tags as $tag) {
                    if ($tag['recipientClaim'] == 'birthdate') {
                        $birthdate_dirty = $tag['value'];
                        $birthdate_dirty = preg_replace('/\s+/', '', $birthdate_dirty);
                        try {
                            $d = new DateTime($birthdate_dirty . " 00:00:00");
                            print_and_log(sprintf('datum narozeni [%s] parsovano jako %s', $birthdate_dirty, $d->format('Y-m-d')));
                            $uzivatel->update(['datum_narozeni' => $d->format('Y-m-d')]);
                        } catch (Exception $e) {
                            print_and_log(sprintf('datum narozeni [%s] neni jasny -> zapsat do poznamky', $birthdate_dirty));
                            $novaPoznamka = sprintf("%s\n\nNejasné datum narození [%s] (z digisign smlouvy #%u ze dne %s)", $uzivatel->poznamka, $birthdate_dirty, $smlouva->id, $podpis->kdy_podepsano->format("d.m.Y"));
                            $uzivatel->update(['poznamka' => $novaPoznamka]);
                        }
                    }
                }

            }
            // 4. zrušit členství ve spolku (pokud existuje)
            if ($uzivatel->spolek) {
                $uzivatel->update(['TypClenstvi_id' => 1]); // zrušeno
            }
            // 5. nastavit "vztah" s družstvem
            $uzivatel->update(['druzstvo' => 1]);
        } else {
            print_and_log(sprintf("smlouva #%u typ %s tady neumime zpracovat", $smlouva->id, $smlouva->typ));
        }
        break;
    case 'envelopeExpired': // obálka expirovala

    case 'envelopeCancelled': // obálka byla zrušena

    case 'recipientSent': // obálka byla odeslána příjemci

    case 'recipientDelivered': // příjemce otevřel obálku (proklik odkaz z emailu)

    case 'recipientNonDelivered': // příjemci se nepodařilo obálku doručit (např. chybná adresa)

    case 'recipientAuthFailed': // příjemce vyčerpal 3 pokusy na autentizaci

    case 'recipientSigned': // příjemce podepsal všechny dokumenty v obálce

    case 'recipientDownloaded': // příjemce si stáhnul hotovou obálku
        print_and_log("this webhook is not implemented");
}

http_response_code(200);
