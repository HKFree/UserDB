<?php

namespace App\Services;

use Nette,
    App\Model,
    PdfResponse\PdfResponse,
    Nette\Mail\SendmailMailer,
    Nette\Mail\Message,
    Tracy\Debugger,
    DateInterval;

/**
 * @author 
 */
class MailService
{
    private $uzivatel;

    function __construct(Model\Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
    }
    
    public function sendConfirmationRequest($uzivatel, $so, $link)
    {
        $mail = new Message;
        $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($uzivatel->email)
            ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s. - UID '.$uzivatel->id)
            ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '.
                            'následující odkaz:<br><br><a href="'.$link.'">'.$link.'</a><br><br>'.
                            'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                            'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                            'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                            'S pozdravem hkfree.org z.s.');
        if (!empty($uzivatel->email2))
        {
            $mail->addTo($uzivatel->email2);
        }
        $mailer = new SendmailMailer;
        $mailer->send($mail);
    }

    public function sendConfirmationRequestCopy($uzivatel, $so)
    {
        $mailso = new Message;
        $mailso->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($so->email)
            ->setSubject('kopie - Žádost o potvrzení registrace člena hkfree.org z.s. - UID '.$uzivatel->id)
            ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '.
                            'následující odkaz:<br><br>.....odkaz má v emailu pouze uživatel  UID '.$uzivatel->id.'<br><br>'.
                            'Kliknutím vyjadřujete svůj souhlas se Stanovami zapsaného spolku v platném znění, '.
                            'souhlas s Pravidly sítě a souhlas se zpracováním osobních údajů pro potřeby evidence člena zapsaného spolku. '.
                            'Veškeré dokumenty naleznete na stránkách <a href="http://www.hkfree.org">www.hkfree.org</a> v sekci Základní dokumenty.<br><br>'.
                            'S pozdravem hkfree.org z.s.');
        if (!empty($so->email2))
        {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($uzivatel->id);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        //\Tracy\Dumper::dump($mailso);exit();
        $mailer = new SendmailMailer;
        $mailer->send($mailso);
    }
  
    public function mailPdf($pdf, $uzivatel, $request, $response, $userid)
    {
        $so = $this->uzivatel->getUzivatel($userid);

        $mail = new Message;
        $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($uzivatel->email)
            ->addTo($so->email)
            ->setSubject('Registrační formulář člena hkfree.org z.s.')
            ->setBody('Dobrý den, zasíláme Vám registrační formulář. S pozdravem hkfree.org z.s.');

        $temp_file = tempnam(sys_get_temp_dir(), 'registrace');
        $pdf->outputName = $temp_file;
        $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
        $pdf->send($request, $response);
        $mail->addAttachment('hkfree-registrace-'.$uzivatel->id.'.pdf', file_get_contents($temp_file));

        $mailer = new SendmailMailer;
        $mailer->send($mail);
    }

    public function sendPlannedUserNotificationEmail($idUzivatele, $actuser) {
        
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        $so = $this->uzivatel->getUzivatel($actuser);

        $mailso = new Message;
        $mailso->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
            ->addTo($so->email)
            ->setSubject('NOTIFIKACE - Nový plánovaný člen - UID '.$newUser->id)
            ->setHTMLBody('V DB je zadán nový plánovaný člen ve Vaší oblasti s UID '.$newUser->id.'<br><br>'.
                            'AP: '.$newUser->Ap->jmeno.'<br><br>'.
                            'Jméno: '.$newUser->jmeno.' '.$newUser->prijmeni.'<br><br>'.
                            'Adresa: '.$newUser->ulice_cp.', '.$newUser->psc.' '.$newUser->mesto.'<br><br>'.
                            'https://userdb.hkfree.org/userdb/uzivatel/show/'.$newUser->id.'<br><br>'.
                            'Bude pravděpodobně následovat připojení od techniků<br><br>'.
                            'Prosím zkontrolujte si adresu přípojného místa a pokud máte pro techniky nějaké informace tak je kontaktujte.<br><br>'.
                            'S pozdravem UserDB');
        if (!empty($so->email2))
        {
            $mailso->addTo($so->email2);
        }

        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($idUzivatele);
        foreach ($seznamSpravcu as $sou) {
            $mailso->addTo($sou->email);
        }
        $mailer = new SendmailMailer;
        $mailer->send($mailso);
    }
}
