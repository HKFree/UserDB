<?php

namespace App\Presenters;

use App\Model;
use App\Services;

class PaymentSchedulePresenter extends BasePresenter
{
    private $uzivatel;
    private $parameters;
    private $mailService;
    private $cestneClenstviUzivatele;
    public function __construct(
        Services\MailService $mailsvc,
        Model\Parameters $parameters,
        Model\Uzivatel $uzivatel,
        Model\CestneClenstviUzivatele $cc,
    ) {
        $this->uzivatel = $uzivatel;
        $this->parameters = $parameters;
        $this->mailService = $mailsvc;
        $this->cestneClenstviUzivatele = $cc;
    }

    private function computeTemplateParams() {
        $uid = $this->getParameter('id');
        $uzivatel = $this->uzivatel->getUzivatel($uid);
        $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->sum('castka');

        $this->template->u = $uzivatel;
        $this->template->stavUctu = $stavUctu;
        $this->template->today = new \DateTime();

        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();

        $pravidelne_mesicni_platby = [
            [
                'Pevný přístup k internetu',
                $this->cestneClenstviUzivatele->getHasCC($uid) ? 0 : $this->parameters->getVyseClenskehoPrispevku()
            ]
        ];
        if ($televizeRow?->objednana == 1) {
            array_push($pravidelne_mesicni_platby, [
                'Televize - START balíček SledovaniTV',
                $televizeRow ? $televizeRow->cena : $this->parameters->getCenaSledovaniTV()
            ]);
        }

        $this->template->pravidelne_mesicni_platby = $pravidelne_mesicni_platby;
        $platby_celkem = array_sum(array_map(fn($a)=>$a[1], $pravidelne_mesicni_platby));
        $this->template->platby_celkem = $platby_celkem;

        $nazev_uzivatele = $this->uzivatel->nazevUzivatele($uzivatel->id);
        $this->template->nazev_uzivatele = $nazev_uzivatele;

        $cisloUctu = '107207255/2010';
        $cisloUctuIBAN = 'CZ0820100000000107207255';
        $this->template->cisloUctu = $cisloUctu;

        $today = new \DateTime();
        $datumPlatby = new \DateTime($today->format('Y-m-25')); // 25. den tento mesic
        if ($stavUctu >= $platby_celkem) {
            $datumPlatby = (clone $today)->modify('+1 month');
            $datumPlatby = new \DateTime($datumPlatby->format('Y-m-25')); // 25. den pristi mesic
        } else {
            if ((int)$today->format('j') > 25) {
                $datumPlatby = (clone $today); // dnes
            }
        }
        $this->template->datumPlatby = $datumPlatby;

        $poznamka = "QR-rozpis";

        $spayd = sprintf('SPD*1.0*ACC:%s*AM:%.2f*CC:CZK*MSG:%s*X-VS:%u', $cisloUctuIBAN, $platby_celkem, $poznamka, $uzivatel->id);
        if ($datumPlatby) {
            $spayd .= sprintf('*DT:%s', $datumPlatby->format("Ymd"));
        }

        $this->template->spayd = $spayd;

        $qrImagePngBase64 = \App\Services\QrCodeGenerator::renderPngBase64($spayd);
        $this->template->qrImagePngBase64 = $qrImagePngBase64;
    }

    public function renderShow() {
        $this->computeTemplateParams();
    }

    public function actionSendPaymentScheduleEmail() {
        $uid = $this->getParameter('id');

        $uzivatel = $this->uzivatel->getUzivatel($uid);
        if ($uzivatel->druzstvo != 1) {
            $this->error('Jen pro družstvo');
            return;
        }
        $this->computeTemplateParams();

        $template = $this->mailService->addLinkGeneratorToTemplate($this->template);

        // TODO tady be se dalo ušetřit ~120kB velikosti e-mailu kdyby se poslaly jen ty potřebný styly
        $this->template->copy_of_bootstrap_css = file_get_contents("../www/css/bootstrap.min.css");

        $subject = "Rozpis plateb pro UID {$uzivatel->id} - {$this->uzivatel->nazevUzivatele($uzivatel->id)}";
        $template->setFile(__DIR__ . '/../templates/PaymentSchedule/paymentSchedule.latte');

        $this->mailService->sendEmailFromTemplate($uzivatel, $subject, $template);

        $this->flashMessage(sprintf('E-mail s rozpisem plateb odeslán na %s.', $uzivatel->email));

        $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
    }
}
