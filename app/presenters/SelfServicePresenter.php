<?php

namespace App\Presenters;

use App\Model\Uzivatel;
use App\Services\CryptoSluzba;
use App\Services\RequestDruzstvoContract;
use App\Model\Parameters;
use App\Model;
use App\Services;
use Nette;

class SelfServicePresenter extends \Nette\Application\UI\Presenter
{
    public function __construct(
        private RequestDruzstvoContract $requestDruzstvoContract,
        private Uzivatel $uzivatelModel,
        private CryptoSluzba $cryptosvc,
        private Parameters $parameters,
    ) {
        parent::__construct();
    }

    /** @param string|null $key */
    public function renderConfirmEmail($key): void {
        $this->setLayout('pub');
        $this->template->stav = false;
        if (in_array($key, [null, '', '0'], true) === false) {

            $decodedKey = base64_decode($key, true);
            if ($decodedKey === false) {
                return;
            }
            $parts = explode('-', $decodedKey, 2);
            if (count($parts) !== 2) {
                return;
            }
            [$uid, $hash] = $parts;

            $uzivatel = $this->uzivatelModel->getUzivatel($uid);
            if ($uzivatel !== null) {
                $this->template->uzivatel = $uzivatel;

                if (hash_equals(md5($this->parameters->salt . $uzivatel->zalozen), $hash) === false) {
                    return;
                }

                if ($uzivatel->regform_downloaded_password_sent === 0) {
                    $uzivatel->update(['regform_downloaded_password_sent' => 1]);

                    if (in_array($uzivatel->spolek, [0, false], true) === false) {
                        /*
                         * TODO předělat registrační formulář spolku podle nových stanov od 1.2.2025
                         */
                        // $pdftemplate = $this->createTemplate()->setFile(__DIR__.'/../templates/Uzivatel/pdf-form.latte');
                        // $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);
                        // $this->mailService->mailPdf($pdf, $uzivatel, $this->getHttpRequest(), $this->getHttpResponse(), $this->getIdentity()->getUid());
                    }

                    if (in_array($uzivatel->druzstvo, [0, false], true) === false) {
                        // Smlouvu si vyžádal člen odkazem z e-mailu, ne případně přihlášený správce.
                        $this->requestDruzstvoContract->execute($uzivatel->id, $uzivatel->id);
                    }
                }

                $this->template->stav = true;
            }
        }
    }

    /**
     * @param int|string|null $uid
     * @param string|null $hash
     * @param string|null $variant
     * @param string|null $nextStep
     */
    public function renderUserEmailFeedback($uid, $hash, $variant, $nextStep): void {
        $this->setLayout('pub');
        $this->template->error = null;

        $uzivatel = $uid !== null ? $this->uzivatelModel->getUzivatel($uid) : null;
        if ($uzivatel === null) {
            $this->error('neplatný odkaz');
        }
        $oneclick_auth_code = $this->cryptosvc->decrypt($uzivatel->oneclick_auth);
        if ($oneclick_auth_code !== $hash) {
            $this->error('neplatný odkaz');
        }
        $uzivatel->update(['oneclick_auth_used_at' => new \DateTime()]);

        switch ($variant) {
            case 'ZpoplatneniTelevize2026':
                $this->template->error = 'Odkaz pro aktivaci/deaktivaci televize již neplatí. Kontaktujte svého Správce nebo infolinku.';
                break;
        }
    }

    /*
    public function handleZpoplatneniTelevize2026($uid, $nextStep) {
        $uzivatel = $this->uzivatelModel->find($uid);

        if ($nextStep == 'ANO') {
            $cena = $this->parameters->getCenaSledovaniTV();
            $this->connection->query(
                sprintf('INSERT INTO %s (id,objednana,cena) VALUES (%u,1,%u) ON DUPLICATE KEY UPDATE objednana=1',
                $this->uzivatelTelevize->tableName, $uid, $cena )
            );

            $this->stitkovac->addStitek($uzivatel, 'TV-platit');

            $this->template->feedbackText = sprintf('Objednána služba Televize za cenu %u Kč/měsíc.', $cena);
        }

        if ($nextStep == 'NE') {
            $this->connection->query(
                sprintf('INSERT INTO %s (id,objednana) VALUES (%u,0) ON DUPLICATE KEY UPDATE objednana=0',
                $this->uzivatelTelevize->tableName, $uid )
            );

            $this->stitkovac->addStitek($uzivatel, 'TV-zrušit');

            $this->template->feedbackText = 'Služba Televize zrušena. Bude deaktivována 1. den v příštím měsíci.';
        }
    }
    */
}
