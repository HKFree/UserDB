<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Services;
use DateInterval;
use DateTime;
use Tracy\Debugger;

/** @property-read Nette\Bridges\ApplicationLatte\Template $template */
class MailingPresenter extends UzivatelPresenter
{
    private Model\Parameters $parameters;
    private Model\Uzivatel $uzivatel;
    private Services\MailService $mailService;
    private Model\CestneClenstviUzivatele $cestneClenstviUzivatele;

    private Services\Stitkovac $stitkovac;

    public function __construct(
        Model\Parameters $parameters,
        Services\MailService $mailsvc,
        Model\Uzivatel $uzivatel,
        Services\CryptoSluzba $cryptosvc,
        Model\CestneClenstviUzivatele $cestneClenstviUzivatele,
        Services\Stitkovac $stitkovac,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\SloucenyUzivatel $slUzivatel,
        Model\Smlouva $smlouva,
        Services\SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\SpravceOblasti $prava,
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
            cc: $cestneClenstviUzivatele,
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
        $this->parameters = $parameters;
        $this->uzivatel = $uzivatel;
        $this->mailService = $mailsvc;
        $this->cryptosvc = $cryptosvc;
        $this->cestneClenstviUzivatele = $cestneClenstviUzivatele;
        $this->stitkovac = $stitkovac;
    }

    // generate auth code and encrypt it to DB if not already generated
    private function oneclickAuthCode(Nette\Database\Table\ActiveRow $uzivatel): string {
        if (in_array($uzivatel->oneclick_auth, [null, '', '0'], true)) {
            $code_length = 32;
            $oneclick_auth_code = substr(str_shuffle(str_repeat($x = '23456789abcdefghijmnopqrstuvwxyzABCDEFGHJMNPQRSTUVWXYZ', (int) ceil($code_length / strlen($x)))), 1, $code_length);
            $oneclick_auth_code_encrypted = $this->cryptosvc->encrypt($oneclick_auth_code);
            $this->uzivatel->update($uzivatel->id, [
                'oneclick_auth' => $oneclick_auth_code_encrypted,
            ]);
        } else {
            $oneclick_auth_code = $this->cryptosvc->decrypt($uzivatel->oneclick_auth);
            $this->uzivatel->update($uzivatel->id, ['oneclick_auth_used_at' => null]);
        }

        return $oneclick_auth_code;
    }

    private function setCommonTemplateParams(): void {
        $uid = $this->getParameter('id');
        $uzivatel =  $this->uzivatel->find($uid);
        if ($uzivatel === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();

        $this->template->UID = $uid;
        $this->template->nazevUzivatele = $this->uzivatel->nazevUzivatele($uzivatel->id);
        $this->template->oneclick_auth_code = $this->oneclickAuthCode($uzivatel);
        $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uid);
        $this->template->televize_cena = $televizeRow !== null ? $televizeRow->cena : $this->parameters->getCenaSledovaniTV();
    }

    /** @param string|null $variant */
    private function loadTemplateAndSubject($variant): string {
        $subject = Nette\Utils\FileSystem::read(__DIR__ . "/../templates/email/{$variant}_subject.latte");
        $this->template->subject = $subject;

        $this->template->setFile(__DIR__ . "/../templates/email/{$variant}_body.latte");

        return $subject;
    }

    public function renderPreviewUserEmail(): void {
        $variant = $this->getParameter('variant');

        $this->loadTemplateAndSubject($variant);
        $this->setCommonTemplateParams();
    }

    public function actionSendUserEmail(): void {
        $uid = $this->getParameter('id');
        $variant = $this->getParameter('variant');
        $uzivatel =  $this->uzivatel->find($uid);
        if ($uzivatel === null) {
            $this->error('Uživatel nebyl nalezen.');
        }

        $subject = $this->loadTemplateAndSubject($variant);
        $this->setCommonTemplateParams();

        $this->mailService->sendEmailFromTemplate($uzivatel, $subject, $this->template);

        $this->flashMessage(sprintf('E-mail %s odeslán na %s.', $variant, $uzivatel->email));

        $this->stitkovac->addStitek($uzivatel, 'Odesláno:' . $variant);

        $this->redirect('Uzivatel:show', ['id' => $uid]);
    }
}
