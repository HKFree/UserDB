<?php

include __DIR__ . '/../../vendor/autoload.php';

use DigitalCz\DigiSign\DigiSign;

$bootstrap = new App\Bootstrap();
$container = $bootstrap->boot();

\Tracy\Debugger::$showBar = false;

/** Decoded JSON payload. */
function process_digisign_webhook(mixed $hook): void {
    global $container;

    $UzivatelModel = $container->getByType(\App\Model\Uzivatel::class);
    $SmlouvaModel = $container->getByType(\App\Model\Smlouva::class);
    $PodpisSmlouvyModel = $container->getByType(\App\Model\PodpisSmlouvy::class);
    $Stitkovac = $container->getByType(\App\Services\Stitkovac::class);
    $SpravceOblasti = $container->getByType(\App\Model\SpravceOblasti::class);
    $AplikaceLog = $container->getByType(\App\Model\AplikaceLog::class);

    $FILE_STORAGE_PATH = getenv('FILE_STORAGE_PATH') ?: '/tmp';
    $FILE_STORAGE_PATH .= '/ucastnickeSmlouvy';

    $accessKey = getenv('DIGISIGN_ACCESS_KEY');
    $secretKey = getenv('DIGISIGN_SECRET_KEY');
    if (in_array($accessKey, [false, '', '0'], true) || in_array($secretKey, [false, '', '0'], true)) {
        throw new RuntimeException('Missing DIGISIGN_ACCESS_KEY or DIGISIGN_SECRET_KEY environment variables');
    }
    $dgs = new DigiSign([
        'access_key' => $accessKey,
        'secret_key' => $secretKey
    ]);
    $ENVELOPES = $dgs->envelopes();

    if ($hook->entityName !== "envelope") {
        return;
    }

    $smlouva = $SmlouvaModel->findOneBy(['externi_id' => $hook->entityId]);
    if (!$smlouva) {
        print_and_log("Smlouvu nemame v DB -> ignorovat");
        return;
    }

    if ($smlouva->typ !== 'ucastnicka') {
        print_and_log(sprintf("Smlouva #%u typ %s tady neumime zpracovat", $smlouva->id, $smlouva->typ));
        return;
    }

    // zpetne volat API nacist detaily smlouvy:
    // Neznámá obálka skončí výjimkou klienta DigiSign.
    $envelope = $ENVELOPES->get($hook->entityId);

    switch ($hook->event) {
        case 'envelopeSent': // obálka odeslána
            /**
             *  {"id":"0193b7bc-3bee-7393-b19d-02c49b5781a9","event":"envelopeSent","name":"envelope.sent","time":"2024-12-11T22:59:46+01:00","entityName":"envelope","entityId":"0193b7bc-1797-70f6-a095-231c750d3487","data":{"status":"sent"},"envelope":{"id":"0193b7bc-1797-70f6-a095-231c750d3487","status":"sent"}}
             */
            $prijemce = $envelope->recipients[0] ?? null;
            if ($prijemce === null) {
                throw new RuntimeException('DigiSign nevrátil příjemce obálky.');
            }
            $odeslano_kdy = $prijemce->sentAt;
            if ($odeslano_kdy === null) {
                throw new RuntimeException('DigiSign nevrátil čas odeslání obálky.');
            }
            $odeslano_kam = $prijemce->email;

            // 1. uložit do DB že je smlouva odeslaná
            $novaPoznamka = sprintf(
                "%s%sSmlouva odeslána %s na adresu %s.",
                $smlouva->poznamka,
                in_array($smlouva->poznamka, [null, '', '0'], true) ? '' : "\n",
                $odeslano_kdy->format('d.m.Y H:i'),
                $odeslano_kam
            );
            $smlouva->update(['poznamka' => $novaPoznamka]);

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

            // Neúplné podklady odmítnout před zápisem podpisu a stažením dokumentu.
            $podpis = $PodpisSmlouvyModel->findOneBy(['smlouva_id' => $smlouva->id, 'smluvni_strana' => 'ucastnik']);
            if ($podpis === null) {
                throw new RuntimeException('Podpis účastníka nebyl nalezen.');
            }
            $dokument = $envelope->documents[0] ?? null;
            if ($dokument === null) {
                throw new RuntimeException('DigiSign nevrátil podepsaný dokument.');
            }
            $uzivatel = $UzivatelModel->find($smlouva->uzivatel_id);
            if ($uzivatel === null) {
                throw new RuntimeException('Člen smlouvy nebyl nalezen.');
            }
            $tags = [];
            if ($uzivatel->datum_narozeni === null) {
                $prijemce = $envelope->recipients[0] ?? null;
                if ($prijemce === null) {
                    throw new RuntimeException('DigiSign nevrátil příjemce obálky.');
                }
                $recipient1 = $ENVELOPES->recipients($envelope)->get($prijemce->id);
                $tags = $recipient1->tags->toArray();
            }
            $podepsanoKdy = digisign_webhook_event_time($hook->time ?? null);

            // 1. uložit do DB že je smlouva podepsaná
            $podpis->update(['kdy_podepsano' => $podepsanoKdy]);
            print_and_log(sprintf("smlouva #%u podpis ucastnika \"%s\" datum/cas: %s", $smlouva->id, $podpis->jmeno, $hook->time));
            // 2. stáhnout podepsaný PDF a uložit
            $fileResponse = $ENVELOPES->download($envelope->id);
            $documentFullName = "{$FILE_STORAGE_PATH}/{$dokument->name}";
            $fileResponse->save($documentFullName);
            print_and_log(sprintf('Ulozeno do %s', $documentFullName));
            $smlouva->update(['podepsany_dokument_nazev' => $dokument->name]);
            $smlouva->update(['podepsany_dokument_content_type' => 'application/pdf']);
            $smlouva->update(['podepsany_dokument_path' => $documentFullName]);
            // 3. pokud nemáme datum narození, zkusíme parsovat vyplněný ze smlouvy
            if ($uzivatel->datum_narozeni === null) {
                foreach ($tags as $tag) {
                    if ($tag['recipientClaim'] === 'birthdate') {
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
                                in_array($uzivatel->poznamka, [null, '', '0'], true) ? '' : "\n\n",
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
                $soAndZso = array_unique(
                    array_keys($SpravceOblasti->getSO()->fetchPairs('Uzivatel_id', 'Uzivatel_id')) +
                    array_keys($SpravceOblasti->getZSO()->fetchPairs('Uzivatel_id', 'Uzivatel_id'))
                );

                $AplikaceLog->log("digisign-webhook", "Overime ze UID {$uzivatel->id} neni SO nebo ZSO, zde je jejich seznam (" . implode(', ', $soAndZso). ")");

                // 4.1. rozhodnutim SO rusime clenstvi ve spolku clenum, kteri nejsou SO ci ZSO
                if (in_array($uzivatel->id, $soAndZso, true) === false) {
                    $AplikaceLog->log("digisign-webhook", "Uzivatel UID {$uzivatel->id} neni SO ani ZSO, nastavujeme mu TypClenstvi_id = 1 (zrusene)");
                    $uzivatel->update(['TypClenstvi_id' => 1]); // zrušeno
                }
            }
            // 5. nastavit "vztah" s družstvem
            $uzivatel->update(['druzstvo' => 1]);

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

            $podpis = $PodpisSmlouvyModel->findOneBy(['smlouva_id' => $smlouva->id, 'smluvni_strana' => 'ucastnik']);
            if ($podpis === null) {
                throw new RuntimeException('Podpis účastníka nebyl nalezen.');
            }
            $prijemce = $envelope->recipients[0] ?? null;
            if ($prijemce === null) {
                throw new RuntimeException('DigiSign nevrátil příjemce obálky.');
            }
            $novaPoznamka = null;
            if (!in_array($prijemce->declineReason, [null, '', '0'], true)) {
                $odmitnutoKdy = $prijemce->declinedAt;
                if ($odmitnutoKdy === null) {
                    throw new RuntimeException('DigiSign nevrátil čas odmítnutí obálky.');
                }
                $novaPoznamka = sprintf(
                    "%s%sSmlouva ODMÍTNUTA %s s důvodem: %s",
                    $smlouva->poznamka,
                    in_array($smlouva->poznamka, [null, '', '0'], true) ? '' : "\n",
                    $odmitnutoKdy->format('d.m.Y H:i'),
                    $prijemce->declineReason
                );
            }
            // 1. uložit do DB že je smlouva odmítnuta
            $podpis->update(['kdy_odmitnuto' => digisign_webhook_event_time($hook->time ?? null)]);
            print_and_log(sprintf("smlouva #%u podpis \"%s\" odmitnut, datum/cas: %s", $smlouva->id, $podpis->jmeno, $hook->time));
            // 2. důvod odmítnutí uložit do poznámky
            if ($novaPoznamka !== null) {
                $smlouva->update(['poznamka' => $novaPoznamka]);
            }
            break;
        case 'envelopeExpired': // obálka expirovala
            // ověřit skutečný stav
            if ($envelope->status !== 'expired') {
                print_and_log("Nesedi envelope status ({$envelope->status})");
                break;
            }

            $vyprseloKdy = $envelope->expiredAt ?? digisign_webhook_event_time($hook->time ?? null);
            // 1. uložit do DB že smlouva vypršela
            print_and_log(sprintf("smlouva #%u vyexpirovala datum/cas: %s", $smlouva->id, $hook->time));
            $novaPoznamka = sprintf(
                "%s%sVYPRŠEL ČAS na podpis %s",
                $smlouva->poznamka,
                in_array($smlouva->poznamka, [null, '', '0'], true) ? '' : "\n",
                $vyprseloKdy->format('d.m.Y H:i'),
            );
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

function digisign_webhook_event_time(mixed $time): DateTimeImmutable {
    if (!is_string($time) || preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?$/D', $time) !== 1) {
        throw new RuntimeException('Čas události DigiSign chybí nebo není platný.');
    }
    try {
        $date = new DateTimeImmutable($time);
    } catch (Exception $e) {
        throw new RuntimeException('Čas události DigiSign chybí nebo není platný.', 0, $e);
    }
    $errors = DateTimeImmutable::getLastErrors();
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        throw new RuntimeException('Čas události DigiSign chybí nebo není platný.');
    }
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
}

function print_and_log(string $message): void {
    error_log("digisign_webhook: $message.");
    print "$message.\n";
}
