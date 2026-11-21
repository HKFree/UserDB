<?php

namespace App\Presenters;

use App\Model;
use App\Services;
use Nette\Application\UI\Form;
use Nette\Mail\Message;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use App\Components;
use App\Services\SmsSenderException;

/**
 * Uzivatel presenter.
 */
class UzivatelMailSmsPresenter extends UzivatelPresenter
{
    private Model\Uzivatel $uzivatel;
    private Model\AP $ap;
    private Model\Komunikace $komunikace;

    public function __construct(
        Model\Uzivatel $uzivatel,
        Model\AP $ap,
        Model\Komunikace $k,
        Services\MailService $mailsvc,
        Services\CryptoSluzba $cryptosvc,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\Parameters $parameters,
        Model\SloucenyUzivatel $slUzivatel,
        Model\Smlouva $smlouva,
        Services\SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\SpravceOblasti $prava,
        Model\CestneClenstviUzivatele $cc,
        Model\TypPravniFormyUzivatele $typPravniFormyUzivatele,
        Model\TypClenstvi $typClenstvi,
        Model\ZpusobPripojeni $zpusobPripojeni,
        Model\TechnologiePripojeni $technologiePripojeni,
        Model\IPAdresa $ipAdresa,
        Model\TypZarizeni $typZarizeni,
        Model\Log $log,
        Model\IdsConnector $idsConnector,
        Model\AplikaceToken $aplikaceToken,
        Model\Stitek $stitek,
        Model\StitekUzivatele $stitkyUzivatele,
    ) {
        parent::__construct(
            mailsvc: $mailsvc,
            cryptosvc: $cryptosvc,
            alowedSMTP: $alowedSMTP,
            dnat: $dnat,
            parameters: $parameters,
            slUzivatel: $slUzivatel,
            smlouva: $smlouva,
            smlouvaStavSluzba: $smlouvaStavSluzba,
            prava: $prava,
            cc: $cc,
            typPravniFormyUzivatele: $typPravniFormyUzivatele,
            typClenstvi: $typClenstvi,
            zpusobPripojeni: $zpusobPripojeni,
            technologiePripojeni: $technologiePripojeni,
            uzivatel: $uzivatel,
            ipAdresa: $ipAdresa,
            typZarizeni: $typZarizeni,
            log: $log,
            idsConnector: $idsConnector,
            aplikaceToken: $aplikaceToken,
            stitek: $stitek,
            stitkyUzivatele: $stitkyUzivatele,
        );
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->komunikace = $k;
    }

    /** E-mail a SMS členovi posílá správce jeho AP */
    private function smiPsatClenovi(): bool {
        $uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'));
        return $uzivatel && $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser());
    }

    /** E-mail a SMS všem členům AP posílá správce AP */
    private function smiPsatCelemuAp(): bool {
        return $this->ap->getAP($this->getParameter('id')) && $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
    }

    public function renderEmail(): void {
        $this->template->canViewOrEdit = $this->smiPsatClenovi();
        $this->template->u = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentEmailForm(): Form {
        $form = new Form($this, "emailForm");
        $form->addHidden('id');

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        $form->addSelect('from', 'Odesílatel', array(0 => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',1 => 'oblast'.$user->Ap->Oblast_id.'@hkfree.org'))->setDefaultValue(0);

        $form->addText('email', 'Příjemce', 70)->setDisabled(true);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'emailFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            if ($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        'from' => 0,
                        'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function emailFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatClenovi());
        $idUzivatele = $values->id;

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));

        $mail = new Message();

        if ($values->from == 0) {
            $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
            $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
             ->addTo($user->email)
             ->setSubject($values->subject)
             ->setBody($values->message);
        } else {
            $mail->setFrom('oblast'.$user->Ap->Oblast_id.'@hkfree.org')
              ->addTo($user->email)
              ->setSubject($values->subject)
              ->setBody($values->message);
        }

        $this->mailer->send($mail);

        $this->flashMessage('E-mail byl odeslán.');

        $this->redirect('Uzivatel:show', array('id' => $idUzivatele));
    }

    public function renderEmailall(): void {
        $this->template->canViewOrEdit = $this->smiPsatCelemuAp();
        $this->template->ap = $this->ap->getAP($this->getParameter('id'));
    }

    protected function createComponentEmailallForm(): Form {
        $form = new Form($this, "emailallForm");
        $form->addHidden('id');

        $oblastMail = null;
        if ($this->getParameter('id')) {
            $ap = $this->ap->getAP($this->getParameter('id'));
            $oblastMail = 'oblast'.$ap->Oblast_id.'@hkfree.org';
        }
        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        $form->addSelect('from', 'Odesílatel', array(0 => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>',1 => $oblastMail))->setDefaultValue(0);

        $form->addTextArea('email', 'Příjemce', 72, 20)->setDisabled(true);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'emailallFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $emaily = $ap->related('Uzivatel.Ap_id')->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->fetchPairs('id', 'email');
            $validni = [];
            foreach ($emaily as $email) {
                if (Validators::isEmail($email)) {
                    $validni[] = $email;
                }
            }
            $tolist = join(";", $validni);

            if ($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        'from' => 0,
                        'email' => $tolist,
                        'subject' => 'Zpráva od správce sítě hkfree.org',
                    ));
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function emailallFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatCelemuAp());
        $idUzivatele = $values->id;

        $ap = $this->ap->getAP($this->getParameter('id'));
        $emaily = $ap->related('Uzivatel.Ap_id')->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->fetchPairs('id', 'email');

        $mail = new Message();
        if ($values->from == 0) {
            $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
            $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
             ->setSubject($values->subject)
             ->setBody($values->message);
        } else {
            $mail->setFrom('oblast'.$ap->Oblast_id.'@hkfree.org')
              ->setSubject($values->subject)
              ->setBody($values->message);
        }

        //TODO: check if mail is valid
        foreach ($emaily as $email) {
            if (Validators::isEmail($email)) {
                $mail->addBcc($email);
            }
        }

        $this->mailer->send($mail);

        $this->flashMessage('E-mail byl odeslán.');

        $this->redirect('UzivatelList:list', array('id' => $this->getParameter('id')));
    }

    public function renderSms(): void {
        $this->template->canViewOrEdit = $this->smiPsatClenovi();
        $this->template->uziv = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentSmsForm(): Form {
        $form = new Form($this, "smsForm");
        $form->addHidden('id');

        $form->addText('komu', 'Příjemce', 20)->setDisabled(true);
        $form->addTextArea('message', 'Text', 72, 10);

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));

        if (!empty($user->telefon) && $user->telefon != 'missing') {
            $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');
            $form->onSuccess[] = array($this, 'smsFormSucceded');
        }

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            if ($user) {
                $form->setValues($user);
                $form->setDefaults(array(
                        'komu' => $user->telefon
                    ));
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function smsFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatClenovi());
        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));

        $this->sendSMSAndValidate([ $user->id ], $values->message);

        $this->redirect('Uzivatel:show', array('id' => $this->getParameter('id')));
    }

    public function renderSmsall(): void {
        $this->template->canViewOrEdit = $this->smiPsatCelemuAp();
        $this->template->ap = $this->ap->getAP($this->getParameter('id'));
    }

    protected function createComponentSmsallForm(): Form {
        $form = new Form($this, "smsallForm");
        $form->addHidden('id');

        $form->addTextArea('komu', 'Příjemce', 72, 20)->setDisabled(true);
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'smsallFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $ap = $this->ap->getAP($this->getParameter('id'));
            $telefony = $ap->related('Uzivatel.Ap_id')->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->fetchPairs('id', 'telefon');
            $validni = [];
            foreach ($telefony as $tl) {
                if (!empty($tl) && $tl != 'missing') {
                    $validni[] = $tl;
                }
            }
            $tls = join(",", $validni);

            if ($ap) {
                $form->setValues($ap);
                $form->setDefaults(array(
                        'komu' => $tls
                    ));
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function smsallFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatCelemuAp());
        $ap = $this->ap->getAP($this->getParameter('id'));

        $telefony = $ap->related('Uzivatel.Ap_id')->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->fetchPairs('id', 'telefon');

        $validni = [];
        foreach ($telefony as $uid => $tl) {
            if (!empty($tl) && $tl != 'missing') {
                $validni[] = $uid;
            }
        }

        $this->sendSMSAndValidate($validni, $values->message);

        $this->redirect('UzivatelList:list', array('id' => $this->getParameter('id')));
    }

    /** @param array<array-key, int|string> $uzivateleID */
    private function sendSMSAndValidate(array $uzivateleID, string $message): void {
        try {
            $pocetCisel = $this->komunikace->posliSMS($uzivateleID, $message);
            $this->flashMessage('SMS byla odeslána na ' . $pocetCisel . ' čísel.');
        } catch (SmsSenderException $e) {
            $this->flashMessage('SMS nebyla odeslána. ' . $e->getMessage(), "danger");
        }
    }
}
