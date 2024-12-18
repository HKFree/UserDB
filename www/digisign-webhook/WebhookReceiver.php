<?php

include __DIR__ . '/../../vendor/autoload.php';

use DigitalCz\DigiSign\DigiSign;

$bootstrap = new App\Bootstrap();
$container = $bootstrap->boot();

\Tracy\Debugger::$showBar = false;

function process_digisign_webhook($hook) {
    global $container;

    $UzivatelModel = $container->getByType(\App\Model\Uzivatel::class);
    $SmlouvaModel = $container->getByType(\App\Model\Smlouva::class);
    $Logger = $container->getByType(\App\Model\Log::class);
    $PodpisSmlouvyModel = $container->getByType(\App\Model\PodpisSmlouvy::class);
    $Stitkovac = $container->getByType(\App\Services\Stitkovac::class);
    $log = [];

    $FILE_STORAGE_PATH = getenv('FILE_STORAGE_PATH') ?: '/tmp';
    $FILE_STORAGE_PATH .= '/ucastnickeSmlouvy';

    $dgs = new DigiSign([
        'access_key' => getenv('DIGISIGN_ACCESS_KEY'),
        'secret_key' => getenv('DIGISIGN_SECRET_KEY')
    ]);
    $ENVELOPES = $dgs->envelopes();

    if ($hook->entityName != "envelope") {
        return;
    }

    $smlouva = $SmlouvaModel->findOneBy(['externi_id' => $hook->entityId]);
    if (!$smlouva) {
        print_and_log("Smlouvu nemame v DB -> ignorovat");
        return;
    }

    if ($smlouva->typ != 'ucastnicka') {
        print_and_log(sprintf("Smlouva #%u typ %s tady neumime zpracovat", $smlouva->id, $smlouva->typ));
        return;
    }

    // zpetne volat API nacist detaily smlouvy:
    $envelope = $ENVELOPES->get($hook->entityId);

    if (!$smlouva) {
        print_and_log("Smlouvu nezna digisign API -> ignorovat");
        return;
    }

    switch ($hook->event) {
        case 'envelopeSent': // obálka odeslána
            /**
             *  {"id":"0193b7bc-3bee-7393-b19d-02c49b5781a9","event":"envelopeSent","name":"envelope.sent","time":"2024-12-11T22:59:46+01:00","entityName":"envelope","entityId":"0193b7bc-1797-70f6-a095-231c750d3487","data":{"status":"sent"},"envelope":{"id":"0193b7bc-1797-70f6-a095-231c750d3487","status":"sent"}}
             */
            $odeslano_kdy = $envelope->recipients[0]->sentAt;
            $odeslano_kam = $envelope->recipients[0]->email;

            // 1. uložit do DB že je smlouva odeslaná
            $novaPoznamka = sprintf(
                "%s%sSmlouva odeslána %s na adresu %s.",
                $smlouva->poznamka,
                empty($smlouva->poznamka) ? '' : "\n",
                $odeslano_kdy->format('d.m.Y H:i'),
                $odeslano_kam
            );
            $smlouva->update(['poznamka' => $novaPoznamka]);

            // 2. Uživatele označit štítkem /* migrace 2025 temporary */
            $uzivatel = $UzivatelModel->find($smlouva->uzivatel);
            $Stitkovac->addStitek($uzivatel, 'Mig2');

            break;
        case 'envelopeCompleted': // obálka dokončena (podepsána všemi podepisujícími)
            /**
             * {"id":"0193b7d7-4941-7217-ad91-f2cf0ca9a8e6","event":"envelopeCompleted","name":"envelope.completed","time":"2024-12-11T23:29:19+01:00","entityName":"envelope","entityId":"0193b1df-239d-7260-b479-111da9ddfbac","data":{"status":"completed"},"envelope":{"id":"0193b1df-239d-7260-b479-111da9ddfbac","status":"completed"}}
             */

            // ověřit skutečný stav
            if ($envelope->status !== 'completed') {
                print_and_log("Nesedi envelope status ({$envelope->status})");
                break;
            }

            // 1. uložit do DB že je smlouva podepsaná
            $podpis = $PodpisSmlouvyModel->findOneBy(['smlouva_id' => $smlouva->id, 'smluvni_strana' => 'ucastnik']);
            $podpis->update(['kdy_podepsano' => $hook->time]);
            print_and_log(sprintf("smlouva #%u podpis ucastnika \"%s\" datum/cas: %s", $smlouva->id, $podpis->jmeno, $hook->time));

            // 2. stáhnout podepsaný PDF a uložit
            $fileResponse = $ENVELOPES->download($envelope->id);
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

                        try {
                            $d = new DateTime(preg_replace('/\s+/', '', $birthdate_dirty) . " 00:00:00");
                            print_and_log(sprintf('datum narozeni [%s] parsovano jako %s', $birthdate_dirty, $d->format('Y-m-d')));
                            $uzivatel->update(['datum_narozeni' => $d->format('Y-m-d')]);
                        } catch (Exception $e) {
                            print_and_log(sprintf('datum narozeni [%s] neni jasny -> zapsat do poznamky', $birthdate_dirty));
                            $novaPoznamka = sprintf(
                                "%s%sNejasné datum narození [%s] (z digisign smlouvy #%u ze dne %s)",
                                $uzivatel->poznamka,
                                empty($uzivatel->poznamka) ? '' : "\n\n",
                                $birthdate_dirty,
                                $smlouva->id,
                                $podpis->kdy_podepsano->format("d.m.Y")
                            );
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

            // 6. zalogovat, že smlouva byla podepsána
            $Logger->logujInsert(['kdy_podepsano' => $hook->time], 'Smlouva', $log);
            $Logger->loguj('Smlouva', $smlouva->id, $log);

            // 7. Uživatele označit štítkem /* migrace 2025 temporary */
            $Stitkovac->addStitek($uzivatel, 'Mig3');

            // 8. Odstranit oneclick_auth (odkaz v e-mailu už nebude fungovat) /* migrace 2025 temporary */
            $uzivatel->update(['oneclick_auth' => null]);

            break;
        case 'envelopeDeclined': // obálka byla odmítnuta
            /**
             * {"id":"0193bc3c-08d2-73ef-b4e4-a4660319bf08","event":"envelopeDeclined","name":"envelope.declined","time":"2024-12-12T19:57:51+01:00","entityName":"envelope","entityId":"0193bc3b-46f0-73fa-8d73-c9f0452a08ca","data":{"status":"declined"},"envelope":{"id":"0193bc3b-46f0-73fa-8d73-c9f0452a08ca","status":"declined"}}
             */
            // ověřit skutečný stav
            if ($envelope->status !== 'declined') {
                print_and_log("Nesedi envelope status ({$envelope->status})");
                break;
            }

            // 1. uložit do DB že je smlouva odmítnuta
            $podpis = $PodpisSmlouvyModel->findOneBy(['smlouva_id' => $smlouva->id, 'smluvni_strana' => 'ucastnik']);
            $podpis->update(['kdy_odmitnuto' => $hook->time]);
            print_and_log(sprintf("smlouva #%u podpis \"%s\" odmitnut, datum/cas: %s", $smlouva->id, $podpis->jmeno, $hook->time));

            // 2. důvod odmítnutí uložit do poznámky
            if (!empty($envelope->recipients[0]->declineReason)) {
                $novaPoznamka = sprintf(
                    "%s%sSmlouva ODMÍTNUTA %s s důvodem: %s",
                    $smlouva->poznamka,
                    empty($smlouva->poznamka) ? '' : "\n",
                    $envelope->recipients[0]->declinedAt->format('d.m.Y H:i'),
                    $envelope->recipients[0]->declineReason
                );
                $smlouva->update(['poznamka' => $novaPoznamka]);
            }

            // 3. zalogovat, že smlouva byla odmítnnuta
            $new_data = [
                'kdy_odmitnuto' => $hook->time,
                'poznamka' => $novaPoznamka
            ];
            $Logger->logujInsert($new_data, 'Smlouva', $log);
            $Logger->loguj('Smlouva', $smlouva->id, $log);

            break;
        case 'envelopeExpired': // obálka expirovala
            // ověřit skutečný stav
            if ($envelope->status !== 'expired') {
                print_and_log("Nesedi envelope status ({$envelope->status})");
                break;
            }

            // 1. uložit do DB že smlouva vypršela
            print_and_log(sprintf("smlouva #%u vyexpirovala datum/cas: %s", $smlouva->id, $hook->time));
            $novaPoznamka = sprintf(
                "%s%sVYPRŠEL ČAS na podpis %s",
                $smlouva->poznamka,
                empty($smlouva->poznamka) ? '' : "\n",
                $envelope->recipients[0]->declinedAt->format('d.m.Y H:i'),
            );

            // 2. zalogovat že smlouva vypršela
            $Logger->logujUpdate(
                ['poznamka' => $smlouva->poznamka],
                ['poznamka' => $novaPoznamka],
                'Smlouva',
                $log
            );
            $Logger->loguj('Smlouva', $smlouva->id, $log);
            $smlouva->update(['poznamka' => $novaPoznamka]);
            break;
        case 'envelopeCancelled': // obálka byla zrušena

        case 'recipientSent': // obálka byla odeslána příjemci

        case 'recipientDelivered': // příjemce otevřel obálku (proklik odkaz z emailu)

        case 'recipientNonDelivered': // příjemci se nepodařilo obálku doručit (např. chybná adresa)

        case 'recipientAuthFailed': // příjemce vyčerpal 3 pokusy na autentizaci

        case 'recipientSigned': // příjemce podepsal všechny dokumenty v obálce

        case 'recipientDownloaded': // příjemce si stáhnul hotovou obálku
            print_and_log("this webhook is not implemented");
    }

}

function print_and_log($message) {
    error_log("digisign_webhook: $message.");
    print("$message.\n");
}
