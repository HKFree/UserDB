<?php

namespace App\Presenters;

use App\Model;
use App\Services;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
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
    // Hodnoty pole Odesílatel ve formulářích e-mailu.
    private const ODESILATEL_SPRAVCE = 0;
    private const ODESILATEL_OBLAST = 1;

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
        return $uzivatel !== null && $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser());
    }

    /** E-mail a SMS všem členům AP posílá správce AP */
    private function smiPsatCelemuAp(): bool {
        return $this->ap->getAP($this->getParameter('id')) !== null && $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
    }

    private function prihlasenySpravce(): ActiveRow {
        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        if ($so === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        return $so;
    }

    /**
     * Nabídka pole Odesílatel. Vlastní adresu správce obsahuje, jen když je platná.
     * @return array<int, string|null>
     */
    private function odesilatele(?string $adresaOblasti): array {
        $so = $this->prihlasenySpravce();
        $adresy = [self::ODESILATEL_OBLAST => $adresaOblasti];
        if ($this->maPlatnyEmail($so)) {
            $adresy = [self::ODESILATEL_SPRAVCE => $so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>'] + $adresy;
        }
        return $adresy;
    }

    /** Volbu mimo nabídku formulář odmítne, vlastní adresa správce sem proto dojde jen platná. */
    private function nastavOdesilatele(Message $mail, int|string|null $volba, string $adresaOblasti): void {
        if ($volba === self::ODESILATEL_SPRAVCE) {
            $so = $this->prihlasenySpravce();
            $mail->setFrom($so->email, $so->jmeno.' '.$so->prijmeni);
        } else {
            $mail->setFrom($adresaOblasti);
        }
    }

    /** Šablona správci vysvětlí, proč v poli Odesílatel chybí jeho vlastní adresa, a kde ji opravit. */
    private function predejStavOdesilatele(): void {
        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        $neplatny = $so !== null && $this->maPlatnyEmail($so) === false;
        $this->template->odesilatelNeplatny = $neplatny;
        $this->template->editaceOdesilatele = $neplatny && $this->smiUpravitUzivatele($so) ? $so->id : null;
    }

    public function renderEmail(): void {
        $uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'));
        $this->template->canViewOrEdit = $this->smiPsatClenovi();
        $this->template->u = $uzivatel;
        $this->template->emailPlatny = $uzivatel !== null && $this->maPlatnyEmail($uzivatel);
        $this->predejStavOdesilatele();
    }

    protected function createComponentEmailForm(): Form {
        $form = new Form($this, "emailForm");
        $form->addHidden('id');

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
        if ($user === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        $odesilatele = $this->odesilatele('oblast'.$user->Ap->Oblast_id.'@hkfree.org');
        $form->addSelect('from', 'Odesílatel', $odesilatele)->setDefaultValue(array_key_first($odesilatele));

        // Zakázané pole prohlížeč neposílá; hodnotu proto dostane i formulář vrácený s chybou.
        $form->addText('email', 'Příjemce', 70)->setDisabled(true)->setDefaultValue($user->email);
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'emailFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && $submitujeSe === false) {
            $form->setValues($user);
            $form->setDefaults(array(
                    'from' => array_key_first($odesilatele),
                    'subject' => 'Zpráva od správce sítě hkfree.org',
                ));
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function emailFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatClenovi());
        $idUzivatele = $values->id;

        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
        if ($user === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        if ($this->maPlatnyEmail($user) === false) {
            // Stránka místo formuláře vypíše, že člen platnou adresu nemá.
            return;
        }

        $mail = new Message();
        $this->nastavOdesilatele($mail, $values->from, 'oblast'.$user->Ap->Oblast_id.'@hkfree.org');
        $mail->addTo($user->email)
            ->setSubject($values->subject)
            ->setBody($values->message);

        $this->mailer->send($mail);

        $this->flashMessage('E-mail byl odeslán.');

        $this->redirect('Uzivatel:show', array('id' => $idUzivatele));
    }

    public function renderEmailall(): void {
        $this->template->canViewOrEdit = $this->smiPsatCelemuAp();
        $this->template->ap = $this->ap->getAP($this->getParameter('id'));
        $this->predejStavOdesilatele();
    }

    protected function createComponentEmailallForm(): Form {
        $form = new Form($this, "emailallForm");
        $form->addHidden('id');

        $oblastMail = null;
        $ap = null;
        if ($this->maVyplnenyParametr('id')) {
            $ap = $this->ap->getAP($this->getParameter('id'));
            if ($ap === null) {
                $this->error('AP nebylo nalezeno.');
            }
            $oblastMail = 'oblast'.$ap->Oblast_id.'@hkfree.org';
        }
        $odesilatele = $this->odesilatele($oblastMail);
        $form->addSelect('from', 'Odesílatel', $odesilatele)->setDefaultValue(array_key_first($odesilatele));

        // Zakázané pole prohlížeč neposílá; hodnotu proto dostane i formulář vrácený s chybou.
        $form->addTextArea('email', 'Příjemce', 72, 20)->setDisabled(true)
            ->setDefaultValue($ap === null ? null : $this->adresyClenuAp($ap));
        $form->addText('subject', 'Předmět', 70)->setRequired('Zadejte předmět');
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'emailallFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($ap !== null && $submitujeSe === false) {
            $form->setValues($ap);
            $form->setDefaults(array(
                    'from' => array_key_first($odesilatele),
                    'subject' => 'Zpráva od správce sítě hkfree.org',
                ));
        }

        return $form;
    }

    /** Platné adresy aktivních členů AP oddělené středníkem, jak je ukazuje pole Příjemce. */
    private function adresyClenuAp(ActiveRow $ap): string {
        $emaily = $ap->related('Uzivatel.Ap_id')->where(Model\Uzivatel::AKTIVNI_CLEN)->fetchPairs('id', 'email');
        return join(';', array_filter($emaily, static fn (mixed $email): bool => Validators::isEmail($email)));
    }

    /** @param ArrayHash<mixed> $values */
    public function emailallFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatCelemuAp());
        $idUzivatele = $values->id;

        $ap = $this->ap->getAP($this->getParameter('id'));
        if ($ap === null) {
            $this->error('AP nebylo nalezeno.');
        }
        $emaily = $ap->related('Uzivatel.Ap_id')->where(Model\Uzivatel::AKTIVNI_CLEN)->fetchPairs('id', 'email');

        $mail = new Message();
        $this->nastavOdesilatele($mail, $values->from, 'oblast'.$ap->Oblast_id.'@hkfree.org');
        $mail->setSubject($values->subject)
            ->setBody($values->message);

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
        if ($user === null) {
            $this->error('Uživatel nebyl nalezen.');
        }

        if (in_array($user->telefon ?? null, [null, '', '0'], true) === false && $user->telefon !== 'missing') {
            $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');
            $form->onSuccess[] = array($this, 'smsFormSucceded');
        }

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && $submitujeSe === false) {
            $form->setValues($user);
            $form->setDefaults(array(
                    'komu' => $user->telefon
                ));
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function smsFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatClenovi());
        $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
        if ($user === null) {
            $this->error('Uživatel nebyl nalezen.');
        }

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

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && $submitujeSe === false) {
            $ap = $this->ap->getAP($this->getParameter('id'));
            if ($ap === null) {
                $this->error('AP nebylo nalezeno.');
            }
            $telefony = $ap->related('Uzivatel.Ap_id')->where(Model\Uzivatel::AKTIVNI_CLEN)->fetchPairs('id', 'telefon');
            $validni = [];
            foreach ($telefony as $tl) {
                if (in_array($tl, [null, '', '0'], true) === false && $tl !== 'missing') {
                    $validni[] = $tl;
                }
            }
            $tls = join(",", $validni);

            $form->setValues($ap);
            $form->setDefaults(array(
                    'komu' => $tls
                ));
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function smsallFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiPsatCelemuAp());
        $ap = $this->ap->getAP($this->getParameter('id'));
        if ($ap === null) {
            $this->error('AP nebylo nalezeno.');
        }

        $telefony = $ap->related('Uzivatel.Ap_id')->where(Model\Uzivatel::AKTIVNI_CLEN)->fetchPairs('id', 'telefon');

        $validni = [];
        foreach ($telefony as $uid => $tl) {
            if (in_array($tl, [null, '', '0'], true) === false && $tl !== 'missing') {
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
