<?php

namespace App\Services;

use Nette;
use App\Model;
use PdfResponse\PdfResponse;
use Tracy\Debugger;
use Nette\Mail\Message;

/**
 * @author
 */
class MailService
{
    private $templateDir;
    private $uzivatel;
    private $mailer;
    private $linkGenerator;
    private $templateFactory;
    private $cestneClenstviUzivatele;

    public function __construct(
        string $templateDir,
        Nette\Mail\IMailer $mailer,
        Model\Uzivatel $uzivatel,
        Nette\Application\LinkGenerator $linkGenerator,
        Nette\Bridges\ApplicationLatte\TemplateFactory $templateFactory,
        Model\CestneClenstviUzivatele $cestneClenstviUzivatele
    ) {
        $this->templateDir = $templateDir;
        $this->uzivatel = $uzivatel;
        $this->mailer = $mailer;
        $this->linkGenerator = $linkGenerator;
        $this->templateFactory = $templateFactory;
        $this->cestneClenstviUzivatele = $cestneClenstviUzivatele;
    }

    public function sendSpolekConfirmationRequest($uzivatel, $so, $link): void {
        $fromAddress = 'spolek hkfree.org oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mail = new Message();
        $mail->setFrom($fromAddress)
            ->addTo($uzivatel->email)
            ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s. - UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na ' .
                'následující odkaz:<br><br><a href="' . $link . '">' . $link . '</a><br><br>' .
                'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, ' .
                'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. ' .
                'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org z.s.');
        if (!empty($uzivatel->email2)) {
            $mail->addTo($uzivatel->email2);
        }
        $this->mailer->send($mail);
    }

    public function sendSpolekConfirmationRequestCopy($uzivatel, $so): void {
        $fromAddress = 'spolek hkfree.org oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mailso = new Message();
        $mailso->setFrom($fromAddress)
            ->addTo($so->email)
            ->setSubject('kopie - Žádost o potvrzení registrace člena hkfree.org z.s. - UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na ' .
                'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel UID ' . $uzivatel->id . '<br><br>' .
                'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, ' .
                'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. ' .
                'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org z.s.');
        if (!empty($so->email2)) {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($uzivatel->id);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        //\Tracy\Debugger::barDump($mailso);exit();
        $this->mailer->send($mailso);
    }

    public function sendDruzstvoConfirmationRequest($uzivatel, $so, $link): void {
        $fromAddress = 'hkfree.org internetové družstvo, oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mail = new Message();
        $mail->setFrom($fromAddress)
            ->addTo($uzivatel->email)
            ->setSubject('Ověření adresy, potvrďte prosím, UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro ověření platnosti Vaší e-mailové adresy je nutné kliknout na ' .
                'následující odkaz:<br><br><a href="' . $link . '">' . $link . '</a><br><br>' .
                'Veškeré relevantní dokumenty naleznete na stránkách <a href="https://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org internetové družstvo');
        if (!empty($uzivatel->email2)) {
            $mail->addTo($uzivatel->email2);
        }
        $this->mailer->send($mail);
    }

    public function sendDruzstvoConfirmationRequestCopy($uzivatel, $so): void {
        $fromAddress = 'hkfree.org internetové družstvo, oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';

        $mailso = new Message();
        $mailso->setFrom($fromAddress)
            ->addTo($so->email)
            ->setSubject('kopie - Ověření adresy, potvrďte prosím, UID ' . $uzivatel->id)
            ->setHtmlBody('Dobrý den,<br><br>pro ověření platnosti Vaší e-mailové adresy je nutné kliknout na ' .
                'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel UID ' . $uzivatel->id . '<br><br>' .
                'Veškeré relevantní dokumenty naleznete na stránkách <a href="https://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>' .
                'S pozdravem hkfree.org internetové družstvo');
        if (!empty($so->email2)) {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($uzivatel->id);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        //\Tracy\Debugger::barDump($mailso);exit();
        $this->mailer->send($mailso);
    }

    public function mailPdf(PdfResponse $pdf, $uzivatel, $request, $response, $userid): void {
        $so = $this->uzivatel->getUzivatel($userid);

        $fromAddress = 'hkfree.org oblast ' . $uzivatel->Ap->Oblast->jmeno . ' <oblast' . $uzivatel->Ap->Oblast->id . '@hkfree.org>';
        //\Tracy\Debugger::barDump($fromAddress);

        $mail = new Message();
        $mail->setFrom($fromAddress)
            ->addTo($uzivatel->email)
            ->addTo($so->email)
            ->setSubject('Registrační formulář člena hkfree.org z.s.')
            ->setBody('Dobrý den, zasíláme Vám registrační formulář. S pozdravem hkfree.org z.s.');

        $temp_file = tempnam(sys_get_temp_dir(), 'registrace');
        $pdf->outputName = $temp_file;
        $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
        $pdf->send($request, $response);
        $mail->addAttachment('hkfree-registrace-' . $uzivatel->id . '.pdf', file_get_contents($temp_file));

        $this->mailer->send($mail);
    }

    public function sendPlannedUserNotificationEmail($idUzivatele, $actuser): void {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        $so = $this->uzivatel->getUzivatel($actuser);

        $fromAddress = 'hkfree.org oblast ' . $newUser->Ap->Oblast->jmeno . ' <oblast' . $newUser->Ap->Oblast->id . '@hkfree.org>';

        $mailso = new Message();
        $mailso->setFrom($fromAddress)
            ->addTo($so->email)
            ->setSubject('NOTIFIKACE - Nový plánovaný člen - UID ' . $newUser->id)
            ->setHtmlBody('V DB je zadán nový plánovaný člen ve Vaší oblasti s UID ' . $newUser->id . '<br><br>' .
                'AP: ' . $newUser->Ap->jmeno . '<br><br>' .
                'Jméno: ' . $newUser->jmeno . ' ' . $newUser->prijmeni . '<br><br>' .
                'Adresa: ' . $newUser->ulice_cp . ', ' . $newUser->psc . ' ' . $newUser->mesto . '<br><br>' .
                'https://userdb.hkfree.org/userdb/uzivatel/show/' . $newUser->id . '<br><br>' .
                'Bude pravděpodobně následovat připojení od techniků<br><br>' .
                'Prosím zkontrolujte si adresu přípojného místa a pokud máte pro techniky nějaké informace tak je kontaktujte.<br><br>' .
                'S pozdravem UserDB');
        if (!empty($so->email2)) {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($idUzivatele);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        $this->mailer->send($mailso);
    }

    private function createTemplate(): Nette\Application\UI\Template {
        $template = $this->templateFactory->createTemplate();
        $template->getLatte()->addProvider('uiControl', $this->linkGenerator);
        return $template;
    }
}
