<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Services;
use DateInterval;
use DateTime;
use Tracy\Debugger;

class MailingPresenter extends UzivatelPresenter
{
    private $parameters;
    private $accountActivation;
    private $uzivatel;
    private $pdfGenerator;
    private $mailService;
    private $smlouva;

    private Services\RequestDruzstvoContract $requestDruzstvoContract;
    private Services\Stitkovac $stitkovac;
    private $cestneClenstviUzivatele;

    public function __construct(
        Model\Parameters $parameters,
        Services\MailService $mailsvc,
        Services\PdfGenerator $pdf,
        Model\AccountActivation $accActivation,
        Model\Uzivatel $uzivatel,
        Model\Smlouva $smlouva,
        Services\RequestDruzstvoContract $requestDruzstvoContract,
        Services\Stitkovac $stitkovac,
        Services\CryptoSluzba $cryptosvc,
        Model\CestneClenstviUzivatele $cestneClenstviUzivatele
    ) {
        $this->parameters = $parameters;
        $this->pdfGenerator = $pdf;
        $this->accountActivation = $accActivation;
        $this->uzivatel = $uzivatel;
        $this->mailService = $mailsvc;
        $this->smlouva = $smlouva;
        $this->requestDruzstvoContract = $requestDruzstvoContract;
        $this->stitkovac = $stitkovac;
        $this->cryptosvc = $cryptosvc;
        $this->cestneClenstviUzivatele = $cestneClenstviUzivatele;
    }

    // generate auth code and encrypt it to DB if not already generated
    private function oneclickAuthCode($uzivatel) {
        if (!$uzivatel->oneclick_auth) {
            $code_length = 32;
            $oneclick_auth_code = substr(str_shuffle(str_repeat($x = '23456789abcdefghijmnopqrstuvwxyzABCDEFGHJMNPQRSTUVWXYZ', ceil($code_length / strlen($x)))), 1, $code_length);
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

    private function setCommonTemplateParams() {
        $uid = $this->getParameter('id');
        $uzivatel =  $this->uzivatel->find($uid);

        $this->template->UID = $uid;
        $this->template->nazevUzivatele = $this->uzivatel->nazevUzivatele($uzivatel->id);
        $this->template->oneclick_auth_code = $this->oneclickAuthCode($uzivatel);
    }

    private function loadTemplateAndSubject($variant) {
        $subject = file_get_contents(__DIR__ . "/../templates/email/{$variant}_subject.latte");
        $this->template->subject = $subject;

        $this->template->setFile(__DIR__ . "/../templates/email/{$variant}_body.latte");

        return $subject;
    }

    public function renderPreviewUserEmail() {
        $variant = $this->getParameter('variant');

        $this->loadTemplateAndSubject($variant);
        $this->setCommonTemplateParams();
    }

    public function actionSendUserEmail() {
        $uid = $this->getParameter('id');
        $variant = $this->getParameter('variant');
        $uzivatel =  $this->uzivatel->find($uid);

        $subject = $this->loadTemplateAndSubject($variant);
        $this->setCommonTemplateParams();

        $this->mailService->sendEmailFromTemplate($uzivatel, $subject, $this->template);

        $this->flashMessage(sprintf('E-mail %s odeslán na %s.', $variant, $uzivatel->email));

        $this->redirect('Uzivatel:show', ['id' => $uid]);
    }
}
