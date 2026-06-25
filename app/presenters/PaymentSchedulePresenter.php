<?php

namespace App\Presenters;

use App\Components\UserLabelsComponent;
use App\Services\CryptoSluzba;
use App\Services\SmlouvaStavSluzba;
use Nette;
use App\Model;
use App\Services;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\Forms\Container;
use Nette\Utils\Html;
use Tracy\Debugger;
use Nette\Utils\Validators;
use Nette\Utils\Strings;
use App\Components;

/**
 * Uzivatel presenter.
 */
class PaymentSchedulePresenter extends BasePresenter
{
    private $spravceOblasti;
    private $cestneClenstviUzivatele;
    private $typClenstvi;
    private $typPravniFormyUzivatele;
    private $zpusobPripojeni;
    private $technologiePripojeni;
    private $uzivatel;
    private $ipAdresa;
    private $ap;
    private $typZarizeni;
    private $log;
    private $subnet;
    private $sloucenyUzivatel;
    private $smlouva;
    private $smlouvaStavSluzba;
    private $parameters;
    private $povoleneSMTP;
    private $dnat;
    protected $cryptosvc;
    private $pdfGenerator;
    private $mailService;
    private $idsConnector;
    private $aplikaceToken;
    private $stitek;

    private Services\Stitkovac $stitkovac;

    private $stitkyUzivatele;
    private Explorer $databaseExplorer;
    private Services\RequestDruzstvoContract $requestDruzstvoContract;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;

    public function __construct(
        Services\MailService $mailsvc,
        Services\PdfGenerator $pdf,
        CryptoSluzba $cryptosvc,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\Parameters $parameters,
        Model\SloucenyUzivatel $slUzivatel,
        Model\Smlouva $smlouva,
        SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\Subnet $subnet,
        Model\SpravceOblasti $prava,
        Model\CestneClenstviUzivatele $cc,
        Model\TypPravniFormyUzivatele $typPravniFormyUzivatele,
        Model\TypClenstvi $typClenstvi,
        Model\ZpusobPripojeni $zpusobPripojeni,
        Model\TechnologiePripojeni $technologiePripojeni,
        Model\Uzivatel $uzivatel,
        Model\IPAdresa $ipAdresa,
        Model\AP $ap,
        Model\TypZarizeni $typZarizeni,
        Model\Log $log,
        Model\IdsConnector $idsConnector,
        Model\AplikaceToken $aplikaceToken,
        Model\Stitek $stitek,
        Model\StitekUzivatele $stitkyUzivatele,
        Services\Stitkovac $stitkovac,
        Explorer $databaseExplorer,
        Services\RequestDruzstvoContract $requestDruzstvoContract,
    ) {
        $this->uzivatel = $uzivatel;
        $this->parameters = $parameters;
        $this->mailService = $mailsvc;
    }

    private function computeTemplateParams() {
      $uid = $this->getParameter('id');
      $uzivatel = $this->uzivatel->getUzivatel($uid);
      $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->sum('castka');

      $this->template->u = $uzivatel;
      $this->template->stavUctu = $stavUctu;
      $this->template->today = new \DateTime();

      $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();

      $pravidelne_mesicni_platby = [['Pevný přístup k internetu', $this->parameters->getVyseClenskehoPrispevku()]];
      if ($televizeRow?->objednana == 1) {
          array_push($pravidelne_mesicni_platby, ['Televize - START balíček SledovaniTV', $televizeRow?->cena ?: $this->parameters->getCenaSledovaniTV()]);
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
      $datumPlatby = new \DateTime($today->format('Y-m-25')); // 25. den v mesici
      if ((int)$today->format('j') > 25) {
          $datumPlatby = (clone $today)->modify('+7 days'); // dnes + 7 dni
      }
      $this->template->datumPlatby = $datumPlatby;

      $poznamka = "{$nazev_uzivatele} UID{$uzivatel->id} QR1";

      $spayd = sprintf('SPD*1.0*ACC:%s*AM:%.2f*CC:CZK*MSG:%s*X-VS:%u', $cisloUctuIBAN, $platby_celkem, $poznamka, $uzivatel->id);
      if ($datumPlatby) {
          $spayd .= sprintf('*DT:%s', $datumPlatby->format("Ymd"));
      }

      $this->template->spayd = $spayd;
    }

    private function getTemplateParams() {
      $uid = $this->getParameter('id');
      $uzivatel = $this->uzivatel->getUzivatel($uid);
      $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->sum('castka');

      $params = [];
      $params['u'] = $uzivatel;
      $params['stavUctu'] = $stavUctu;
      $params['today'] = new \DateTime();

      $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();

      $pravidelne_mesicni_platby = [['Pevný přístup k internetu', $this->parameters->getVyseClenskehoPrispevku()]];
      if ($televizeRow?->objednana == 1) {
          array_push($pravidelne_mesicni_platby, ['Televize - START balíček SledovaniTV', $televizeRow?->cena ?: $this->parameters->getCenaSledovaniTV()]);
      }

      $params['pravidelne_mesicni_platby'] = $pravidelne_mesicni_platby;
      $platby_celkem = array_sum(array_map(fn($a)=>$a[1], $pravidelne_mesicni_platby));
      $params['platby_celkem'] = $platby_celkem;

      $nazev_uzivatele = $this->uzivatel->nazevUzivatele($uzivatel->id);
      $params['nazev_uzivatele'] = $nazev_uzivatele;

      $cisloUctu = '107207255/2010';
      $cisloUctuIBAN = 'CZ0820100000000107207255';
      $params['cisloUctu'] = $cisloUctu;

      $today = new \DateTime();
      $datumPlatby = new \DateTime($today->format('Y-m-25')); // 25. den v mesici
      if ((int)$today->format('j') > 25) {
          $datumPlatby = (clone $today)->modify('+7 days'); // dnes + 7 dni
      }
      $params['datumPlatby'] = $datumPlatby;

      $poznamka = "{$nazev_uzivatele} UID{$uzivatel->id} QR1";

      $spayd = sprintf('SPD*1.0*ACC:%s*AM:%.2f*CC:CZK*MSG:%s*X-VS:%u', $cisloUctuIBAN, $platby_celkem, $poznamka, $uzivatel->id);
      if ($datumPlatby) {
          $spayd .= sprintf('*DT:%s', $datumPlatby->format("Ymd"));
      }

      $params['spayd'] = $spayd;

      return $params;
    }

    public function renderShow() {
        $this->computeTemplateParams();
    }

    public function actionSendPaymentScheduleEmail() {
      $uid = $this->getParameter('id');

      $uzivatel = $this->uzivatel->getUzivatel($uid);
      if ($uzivatel->druzstvo != 1) return;

      $this->computeTemplateParams();

      $template = $this->mailService->addLinkGeneratorToTemplate($this->template);

      $subject = "Rozpis plateb pro UID {$uzivatel->id} - {$this->uzivatel->nazevUzivatele($uzivatel->id)}";
      $template->setFile(__DIR__ . '/../templates/PaymentSchedule/paymentSchedule.latte');

      $this->mailService->sendEmailFromTemplate($uzivatel, $subject, $template);

      $this->flashMessage('E-mail s rozpisem plateb odeslán.');

      $this->redirect('Uzivatel:show', array('id' => $uzivatel->id));
  }

}
