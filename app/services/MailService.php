<?php

namespace App\Services;

use Nette;
use App\Model;
use PdfResponse\PdfResponse;
use Tracy\Debugger;
use Nette\Mail\Message;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Validators;

/**
 * @phpstan-import-type PreskocenyPrijemce from VysledekOdeslaniEmailu
 */
class MailService
{
    private Model\Uzivatel $uzivatel;
    private Nette\Mail\Mailer $mailer;
    private Nette\Application\LinkGenerator $linkGenerator;

    public function __construct(
        Nette\Mail\Mailer $mailer,
        Model\Uzivatel $uzivatel,
        Nette\Application\LinkGenerator $linkGenerator
    ) {
        $this->uzivatel = $uzivatel;
        $this->mailer = $mailer;
        $this->linkGenerator = $linkGenerator;
    }

    public function sendSpolekConfirmationRequest(ActiveRow $uzivatel, ?ActiveRow $so, string $link): VysledekOdeslaniEmailu {
        $fromAddress = 'spolek hkfree.org oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mail = new Message();
        $mail->setFrom($fromAddress)
            ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s. - UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na ' .
                'následující odkaz:<br><br><a href="' . $link . '">' . $link . '</a><br><br>' .
                'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, ' .
                'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. ' .
                'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org z.s.');
        $preskoceni = $this->pridejObeAdresy($mail, $uzivatel);
        return $this->odesli($mail, $preskoceni);
    }

    public function sendSpolekConfirmationRequestCopy(ActiveRow $uzivatel, ActiveRow $so): VysledekOdeslaniEmailu {
        $fromAddress = 'spolek hkfree.org oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mailso = new Message();
        $mailso->setFrom($fromAddress)
            ->setSubject('kopie - Žádost o potvrzení registrace člena hkfree.org z.s. - UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na ' .
                'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel UID ' . $uzivatel->id . '<br><br>' .
                'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, ' .
                'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. ' .
                'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org z.s.');
        $preskoceni = $this->pridejAdresySpravcu($mailso, $uzivatel, $so);
        return $this->odesli($mailso, $preskoceni);
    }

    public function sendDruzstvoConfirmationRequest(ActiveRow $uzivatel, ?ActiveRow $so, string $link): VysledekOdeslaniEmailu {
        $fromAddress = 'hkfree.org internetové družstvo, oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mail = new Message();
        $mail->setFrom($fromAddress)
            ->setSubject('Ověření adresy, potvrďte prosím, UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro ověření platnosti Vaší e-mailové adresy je nutné kliknout na ' .
                'následující odkaz:<br><br><a href="' . $link . '">' . $link . '</a><br><br>' .
                'Veškeré relevantní dokumenty naleznete na stránkách <a href="https://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org internetové družstvo');
        $preskoceni = $this->pridejObeAdresy($mail, $uzivatel);
        return $this->odesli($mail, $preskoceni);
    }

    public function sendDruzstvoConfirmationRequestCopy(ActiveRow $uzivatel, ActiveRow $so): VysledekOdeslaniEmailu {
        $fromAddress = 'hkfree.org internetové družstvo, oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mailso = new Message();
        $mailso->setFrom($fromAddress)
            ->setSubject('kopie - Ověření adresy, potvrďte prosím, UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro ověření platnosti Vaší e-mailové adresy je nutné kliknout na ' .
                'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel UID ' . $uzivatel->id . '<br><br>' .
                'Veškeré relevantní dokumenty naleznete na stránkách <a href="https://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org internetové družstvo');
        $preskoceni = $this->pridejAdresySpravcu($mailso, $uzivatel, $so);
        return $this->odesli($mailso, $preskoceni);
    }

    public function sendEmailFromTemplate(ActiveRow $uzivatel, string $subject, Nette\Bridges\ApplicationLatte\Template $renderovatTemplate): VysledekOdeslaniEmailu {
        $mail = new Message();
        $preskoceni = $this->pridejObeAdresy($mail, $uzivatel);
        if ($mail->getHeader('To') === null) {
            return new VysledekOdeslaniEmailu([], array_values($preskoceni));
        }
        $fromAddress = 'hkfree.org internetové družstvo <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $htmlContent = $renderovatTemplate->renderToString();

        $mail->setFrom($fromAddress)
            ->addReplyTo('podpora@hkfree.org')
            ->setSubject($subject)
            ->setHtmlBody($htmlContent);
        return $this->odesli($mail, $preskoceni);
    }

    public function mailPdf(PdfResponse $pdf, ActiveRow $uzivatel, Nette\Http\IRequest $request, Nette\Http\IResponse $response, int|string $userid): VysledekOdeslaniEmailu {
        $so = $this->uzivatel->getUzivatel($userid);
        if ($so === null) {
            throw new \InvalidArgumentException('Odesílající uživatel nebyl nalezen.');
        }

        $fromAddress = 'hkfree.org oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';
        //\Tracy\Debugger::barDump($fromAddress);

        $mail = new Message();
        $preskoceni = [];
        $this->pridejAdresu($mail, $uzivatel, false, $preskoceni);
        if ($mail->getHeader('To') === null) {
            return new VysledekOdeslaniEmailu([], array_values($preskoceni));
        }
        $this->pridejAdresu($mail, $so, false, $preskoceni);
        $mail->setFrom($fromAddress)
            ->setSubject('Registrační formulář člena hkfree.org z.s.')
            ->setBody('Dobrý den, zasíláme Vám registrační formulář. S pozdravem hkfree.org z.s.');

        $temp_file = tempnam(sys_get_temp_dir(), 'registrace');
        if ($temp_file === false) {
            throw new Nette\IOException('Nelze vytvořit dočasný soubor pro registrační formulář.');
        }
        $pdf->outputName = $temp_file;
        $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
        $pdf->send($request, $response);
        $mail->addAttachment('hkfree-registrace-' . $uzivatel->id . '.pdf', Nette\Utils\FileSystem::read($temp_file));

        return $this->odesli($mail, $preskoceni);
    }

    public function sendPlannedUserNotificationEmail(int|string $idUzivatele, int|string $actuser): VysledekOdeslaniEmailu {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        $so = $this->uzivatel->getUzivatel($actuser);
        if ($newUser === null || $so === null) {
            throw new \InvalidArgumentException('Uživatel pro odeslání e-mailu nebyl nalezen.');
        }

        $fromAddress = 'hkfree.org oblast ' . $newUser->Ap->Oblast->jmeno . ' <oblast' . $newUser->Ap->Oblast->id . '@hkfree.org>';

        $mailso = new Message();
        $mailso->setFrom($fromAddress)
            ->setSubject('NOTIFIKACE - Nový plánovaný člen - UID ' . $newUser->id)
            ->setHtmlBody('V DB je zadán nový plánovaný člen ve Vaší oblasti s UID ' . $newUser->id . '<br><br>' .
                'AP: ' . $newUser->Ap->jmeno . '<br><br>' .
                'Jméno: ' . $newUser->jmeno . ' ' . $newUser->prijmeni . '<br><br>' .
                'Adresa: ' . $newUser->ulice_cp . ', ' . $newUser->psc . ' ' . $newUser->mesto . '<br><br>' .
                'https://userdb.hkfree.org/userdb/uzivatel/show/' . $newUser->id . '<br><br>' .
                'Bude pravděpodobně následovat připojení od techniků<br><br>' .
                'Prosím zkontrolujte si adresu přípojného místa a pokud máte pro techniky nějaké informace tak je kontaktujte.<br><br>' .
                'S pozdravem UserDB');
        $preskoceni = $this->pridejAdresySpravcu($mailso, $newUser, $so);
        return $this->odesli($mailso, $preskoceni);
    }

    /** @return array<string, PreskocenyPrijemce> */
    private function pridejObeAdresy(Message $mail, ActiveRow $uzivatel): array {
        $preskoceni = [];
        $this->pridejAdresu($mail, $uzivatel, false, $preskoceni);
        $this->pridejAdresu($mail, $uzivatel, true, $preskoceni);
        return $preskoceni;
    }

    /** @return array<string, PreskocenyPrijemce> */
    private function pridejAdresySpravcu(Message $mail, ActiveRow $uzivatel, ActiveRow $so): array {
        $preskoceni = $this->pridejObeAdresy($mail, $so);
        foreach ($this->uzivatel->getSeznamSpravcuUzivatele($uzivatel->id) as $spravce) {
            $this->pridejAdresu($mail, $spravce, false, $preskoceni);
        }
        return $preskoceni;
    }

    /** @param array<string, PreskocenyPrijemce> $preskoceni */
    private function pridejAdresu(Message $mail, ActiveRow|Row $prijemce, bool $druhaAdresa, array &$preskoceni): void {
        $email = $druhaAdresa ? $prijemce->email2 : $prijemce->email;
        if ($druhaAdresa && in_array($email, [null, '', '0'], true)) {
            return;
        }
        if (is_string($email) && Validators::isEmail($email)) {
            $mail->addTo($email);
            return;
        }
        $uid = (int) $prijemce->id;
        $preskoceni[$uid . ':' . (int) $druhaAdresa] = [
            'uid' => $uid,
            'jmeno' => (string) $prijemce->jmeno,
            'prijmeni' => (string) $prijemce->prijmeni,
            'druhaAdresa' => $druhaAdresa,
        ];
    }

    /** Adresy, na které by sendEmailFromTemplate() zprávu členovi poslala, a jeho přeskočené adresy; nic neodesílá. */
    public function prijemciZpravyZeSablony(ActiveRow $uzivatel): VysledekOdeslaniEmailu {
        $mail = new Message();
        $preskoceni = $this->pridejObeAdresy($mail, $uzivatel);
        return new VysledekOdeslaniEmailu($this->adresyPrijemcu($mail), array_values($preskoceni));
    }

    /** @return list<string> */
    private function adresyPrijemcu(Message $mail): array {
        return array_map('strval', array_keys($mail->getHeader('To') ?? []));
    }

    /** @param array<string, PreskocenyPrijemce> $preskoceni */
    private function odesli(Message $mail, array $preskoceni): VysledekOdeslaniEmailu {
        $adresy = $this->adresyPrijemcu($mail);
        if ($adresy !== []) {
            $this->mailer->send($mail);
        }
        return new VysledekOdeslaniEmailu($adresy, array_values($preskoceni));
    }

    /**
     * @param Nette\Bridges\ApplicationLatte\Template $t
     * @return Nette\Bridges\ApplicationLatte\Template
     */
    public function addLinkGeneratorToTemplate($t): Nette\Application\UI\Template {
        $t->getLatte()->addProvider('uiControl', $this->linkGenerator);
        return $t;
    }
}
