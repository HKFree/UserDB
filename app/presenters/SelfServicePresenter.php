<?php

namespace App\Presenters;

use App\Model\Uzivatel;
use App\Services\CryptoSluzba;
use App\Services\RequestDruzstvoContract;
use App\Model\Parameters;

// use App\Services\PdfGenerator;

class SelfServicePresenter extends \Nette\Application\UI\Presenter
{
    public function __construct(
        private RequestDruzstvoContract $requestDruzstvoContract,
        private Uzivatel $uzivatelModel,
        private CryptoSluzba $cryptosvc,
        private Parameters $parameters,
        // private PdfGenerator $pdfGenerator,
    ) {
    }

    public function renderConfirmEmail($key) {
        $this->setLayout('pub');
        $this->template->stav = false;
        if ($key) {

            list($uid, $hash) = explode('-', base64_decode($key));

            $uzivatel = $this->uzivatelModel->getUzivatel($uid);
            if ($uzivatel) {
                $this->template->uzivatel = $uzivatel;

                if ($hash != md5($this->parameters->salt . $uzivatel->zalozen)) {
                    die('Incorrect request (invalid hash)');
                }

                if ($uzivatel->regform_downloaded_password_sent == 0) {
                    $uzivatel->update(['regform_downloaded_password_sent' => 1]);

                    if ($uzivatel->spolek) {
                        /*
                         * TODO předělat registrační formulář spolku podle nových stanov od 1.2.2025
                         */
                        // $pdftemplate = $this->createTemplate()->setFile(__DIR__.'/../templates/Uzivatel/pdf-form.latte');
                        // $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);
                        // $this->mailService->mailPdf($pdf, $uzivatel, $this->getHttpRequest(), $this->getHttpResponse(), $this->getIdentity()->getUid());
                    }

                    if ($uzivatel->druzstvo) {
                        $this->requestDruzstvoContract->execute($uzivatel->id);
                    }
                }

                $this->template->stav = true;
            }
        }
    }

    /* migrace 2025 temporary */
    public function renderRequestDruzstvoContract($id, $hash) {
        $this->setLayout('pub');
        $this->template->error = '';
        $uzivatel = $this->uzivatelModel->getUzivatel($id);
        if (!$uzivatel) {
            $this->template->error = 'Uživatel nenalezen';
        } else {
            if (!$uzivatel->oneclick_auth) {
                $this->template->error = 'Neplatný odkaz';
            } else {
                $oneclick_auth_code = $this->cryptosvc->decrypt($uzivatel->oneclick_auth);
                if ($oneclick_auth_code !== $hash) {
                    $this->template->error = 'Neplatný odkaz';
                } else {
                    if (!empty($uzivatel->oneclick_auth_used_at)) {
                        $this->template->error = 'Smlouva k podpisu již byla zaslána. Tento odkaz funguje pouze jednou.';
                    } else {
                        $this->requestDruzstvoContract->execute($uzivatel->id);
                        $uzivatel->update(['oneclick_auth_used_at' => new \DateTime()]);
                    }
                }
            }
        }
    }

}
