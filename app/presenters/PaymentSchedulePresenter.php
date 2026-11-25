<?php

namespace App\Presenters;

use App\Model;
use App\Services;

/** @property-read \Nette\Bridges\ApplicationLatte\Template $template */
class PaymentSchedulePresenter extends BasePresenter
{
    private Model\Uzivatel $uzivatel;
    private Model\Parameters $parameters;
    private Services\MailService $mailService;
    private Model\CestneClenstviUzivatele $cestneClenstviUzivatele;
    public function __construct(
        Services\MailService $mailsvc,
        Model\Parameters $parameters,
        Model\Uzivatel $uzivatel,
        Model\CestneClenstviUzivatele $cc,
    ) {
        parent::__construct();
        $this->uzivatel = $uzivatel;
        $this->parameters = $parameters;
        $this->mailService = $mailsvc;
        $this->cestneClenstviUzivatele = $cc;
    }

    private function computeTemplateParams(\Nette\Database\Table\ActiveRow $uzivatel): void {
        $uid = $uzivatel->id;
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
        if ($televizeRow !== null && (int) $televizeRow->objednana === 1) {
            array_push($pravidelne_mesicni_platby, [
                'Televize - START balíček SledovaniTV',
                $televizeRow->cena
            ]);
        }

        $this->template->pravidelne_mesicni_platby = $pravidelne_mesicni_platby;
        $platby_celkem = array_sum(array_map(fn ($a) => $a[1], $pravidelne_mesicni_platby));
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
        $spayd .= sprintf('*DT:%s', $datumPlatby->format("Ymd"));

        $this->template->spayd = $spayd;

        $qrImagePngBase64 = \App\Services\QrCodeGenerator::renderPngBase64($spayd);
        $this->template->qrImagePngBase64 = $qrImagePngBase64;
    }

    /** Rozpis plateb ukazuje detail člena jen tomu, kdo smí člena upravovat */
    private function vyzadujOpravneniKClenovi(): \Nette\Database\Table\ActiveRow {
        $uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'));
        $this->vyzadujOpravneni($uzivatel !== null && $this->smiUpravitUzivatele($uzivatel));
        return $uzivatel;
    }

    public function renderShow(): void {
        $uzivatel = $this->vyzadujOpravneniKClenovi();
        $this->computeTemplateParams($uzivatel);
    }

    public function actionSendPaymentScheduleEmail(): void {
        $uzivatel = $this->vyzadujOpravneniKClenovi();
        if ((int) $uzivatel->druzstvo !== 1) {
            $this->error('Jen pro družstvo');
        }
        $this->computeTemplateParams($uzivatel);

        $template = $this->mailService->addLinkGeneratorToTemplate($this->template);

        // E-mail nemá styly aplikace; šablona mu přibalí jen ty několik pravidel, které rozpis potřebuje.
        $this->template->emailStyles = true;

        $subject = "Rozpis plateb pro UID {$uzivatel->id} - {$this->uzivatel->nazevUzivatele($uzivatel->id)}";
        $template->setFile(__DIR__ . '/../templates/PaymentSchedule/paymentSchedule.latte');

        $vysledek = $this->mailService->sendEmailFromTemplate($uzivatel, $subject, $template);
        $this->oznamVysledekEmailu($vysledek, $uzivatel, sprintf('E-mail s rozpisem plateb odeslán na %s.', implode(', ', $vysledek->adresy)));

        $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
    }
}
