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
use Nette\Utils\ArrayHash;
use App\Components;

/**
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{
    private Services\PresunIpSluzba $presunIpSluzba;
    private Model\SpravceOblasti $spravceOblasti;
    private Model\CestneClenstviUzivatele $cestneClenstviUzivatele;
    private Model\TypClenstvi $typClenstvi;
    private Model\TypPravniFormyUzivatele $typPravniFormyUzivatele;
    private Model\ZpusobPripojeni $zpusobPripojeni;
    private Model\TechnologiePripojeni $technologiePripojeni;
    private Model\Uzivatel $uzivatel;
    private Model\IPAdresa $ipAdresa;
    private Model\TypZarizeni $typZarizeni;
    private Model\Log $log;
    private Model\Smlouva $smlouva;
    private SmlouvaStavSluzba $smlouvaStavSluzba;
    private Model\Parameters $parameters;
    private Model\PovoleneSMTP $povoleneSMTP;
    private Model\DNat $dnat;
    protected CryptoSluzba $cryptosvc;
    private Services\MailService $mailService;
    private Model\IdsConnector $idsConnector;
    private Model\AplikaceToken $aplikaceToken;
    private Model\Stitek $stitek;

    private Model\StitekUzivatele $stitkyUzivatele;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;

    public function __construct(
        Services\MailService $mailsvc,
        CryptoSluzba $cryptosvc,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\Parameters $parameters,
        Model\Smlouva $smlouva,
        SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\SpravceOblasti $prava,
        Model\CestneClenstviUzivatele $cc,
        Model\TypPravniFormyUzivatele $typPravniFormyUzivatele,
        Model\TypClenstvi $typClenstvi,
        Model\ZpusobPripojeni $zpusobPripojeni,
        Model\TechnologiePripojeni $technologiePripojeni,
        Model\Uzivatel $uzivatel,
        Model\IPAdresa $ipAdresa,
        Model\TypZarizeni $typZarizeni,
        Model\Log $log,
        Model\IdsConnector $idsConnector,
        Model\AplikaceToken $aplikaceToken,
        Model\Stitek $stitek,
        Model\StitekUzivatele $stitkyUzivatele,
    ) {
        parent::__construct();
        $this->cryptosvc = $cryptosvc;
        $this->spravceOblasti = $prava;
        $this->cestneClenstviUzivatele = $cc;
        $this->typClenstvi = $typClenstvi;
        $this->typPravniFormyUzivatele = $typPravniFormyUzivatele;
        $this->zpusobPripojeni = $zpusobPripojeni;
        $this->technologiePripojeni = $technologiePripojeni;
        $this->uzivatel = $uzivatel;
        $this->ipAdresa = $ipAdresa;
        $this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->smlouva = $smlouva;
        $this->smlouvaStavSluzba = $smlouvaStavSluzba;
        $this->parameters = $parameters;
        $this->povoleneSMTP = $alowedSMTP;
        $this->dnat = $dnat;
        $this->mailService = $mailsvc;
        $this->idsConnector = $idsConnector;
        $this->aplikaceToken = $aplikaceToken;
        $this->stitek = $stitek;
        $this->stitkyUzivatele = $stitkyUzivatele;
    }

    protected function createComponentUserLabels(): UserLabelsComponent {
        // Předej do komponenty aktuální user ID
        $userId = $this->getParameter('id'); // Nebo jiný způsob získání ID uživatele
        $ulc = new UserLabelsComponent($this->stitek, $this->stitkyUzivatele);
        $ulc->setUserId($userId);
        return $ulc;
    }

    public function injectPresunIpSluzba(Services\PresunIpSluzba $presunIpSluzba): void {
        $this->presunIpSluzba = $presunIpSluzba;
    }

    /** @param int|string $idUzivatele */
    public function sendSpolekNotificationEmail($idUzivatele): void {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        if ($newUser === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        try {
            $vysledek = $this->mailService->sendPlannedUserNotificationEmail($idUzivatele, $this->getIdentity()->getUid());
            if ($vysledek->odeslano()) {
                $this->flashMessage('E-mail s notifikací správcům byl odeslán.');
            }
            $this->upozorniNaPreskocenePrijemce($this->smiUpravitUzivatele($newUser), $vysledek->odeslano() ? '' : 'Notifikace správcům nebyla odeslána, žádný ze správců nemá platnou e-mailovou adresu.', $vysledek);
        } catch (Nette\Mail\SmtpException $e) {
            $this->flashMessage('Odeslání e-mailu s notifikací správcům se nezdařilo. '.$e->getMessage(), 'danger');
        }
    }

    /** @param int|string $idUzivatele */
    public function sendSpolekRegistrationEmail($idUzivatele): void {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        if ($newUser === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        $hash = base64_encode($idUzivatele.'-'.md5($this->parameters->salt . $newUser->zalozen));
        $link = 'https://moje.hkfree.org/uzivatel/confirm/'.$hash;

        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        if ($so === null) {
            $this->error('Uživatel nebyl nalezen.');
        }

        try {
            $vysledek = $this->mailService->sendSpolekConfirmationRequest($newUser, $so, $link);
            $kopie = $vysledek->odeslano() ? $this->mailService->sendSpolekConfirmationRequestCopy($newUser, $so) : null;
            $this->oznamVysledekEmailu($vysledek, $newUser, 'E-mail s žádostí o potvrzení e-mailu byl odeslán.', $kopie);
        } catch (Nette\Mail\SmtpException $e) {
            $this->flashMessage('Odeslání e-mailu s žádostí o potvrzení e-mailu se nezdařilo! Napište userdb teamu o pomoc. '.$hash.$e->getMessage(), 'danger');
        }
    }

    /** @param int|string $idUzivatele */
    public function sendDruzstvoRegistrationEmail($idUzivatele): void {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        if ($newUser === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        $hash = base64_encode($idUzivatele.'-'.md5($this->parameters->salt . $newUser->zalozen));
        $link = $this->link('//SelfService:confirmEmail', $hash);

        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
        if ($so === null) {
            $this->error('Uživatel nebyl nalezen.');
        }

        try {
            $vysledek = $this->mailService->sendDruzstvoConfirmationRequest($newUser, $so, $link);
            $kopie = $vysledek->odeslano() ? $this->mailService->sendDruzstvoConfirmationRequestCopy($newUser, $so) : null;
            $this->oznamVysledekEmailu($vysledek, $newUser, 'E-mail s žádostí o potvrzení registrace do družstva byl odeslán.', $kopie);
        } catch (Nette\Mail\SmtpException $e) {
            $this->flashMessage('Odeslání e-mailu s žádostí o potvrzení registrace do družstva se nezdařilo! Napište userdb teamu o pomoc. '.$hash.$e->getMessage(), 'danger');
        }
    }

    /** Text dotazu před odesláním rozpisu plateb; null, když člen nemá platnou adresu a server odeslání rovnou odmítne. */
    private function dotazPredOdeslanimRozpisu(Nette\Database\Table\ActiveRow $uzivatel): ?string {
        $prijemci = $this->mailService->prijemciZpravyZeSablony($uzivatel);
        if ($prijemci->odeslano() === false) {
            return null;
        }
        $dotaz = sprintf('Rozpis plateb bude odeslán na %s %s.', count($prijemci->adresy) === 1 ? 'e-mail' : 'e-maily', implode(', ', $prijemci->adresy));
        foreach ($prijemci->preskoceni as $prijemce) {
            $dotaz .= sprintf("\n\n%s e-mail člena je neplatný, rozpis na něj neodejde.", $prijemce['druhaAdresa'] ? 'Sekundární' : 'Hlavní');
        }
        return $dotaz . "\n\nPokračovat?";
    }

    public function renderShow(): void {
        if ($this->maVyplnenyParametr('id')) {
            $uid = $this->getParameter('id');
            if (($uzivatel = $this->uzivatel->getUzivatel($uid)) !== null) {
                $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
                $this->template->stitky = $this->stitek->getSeznamStitku();
                $this->template->stitkyUzivatele = $this->stitkyUzivatele->getStitekByUserId($uid);

                $this->template->money_act = (1 === (int) $uzivatel->money_aktivni) ? 'ANO' : 'NE';
                $this->template->money_dis = (1 === (int) $uzivatel->money_deaktivace) ? 'ANO' : 'NE';

                $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();
                $this->template->televize_objednana = (int) $televizeRow?->objednana === 1;
                $this->template->televize_cena = $televizeRow !== null ? $televizeRow->cena : $this->parameters->getCenaSledovaniTV();
                $televizeAktivniRow = $uzivatel->related('UzivatelTelevizeAktivni')
                    ->where(['datum_od <= curdate()', 'datum_do >= curdate()'])
                    ->order('datum_od DESC')
                    ->limit(1)
                    ->fetch();
                $this->template->televize_aktivni = $televizeAktivniRow !== null;
                $this->template->televize_aktivni_poznamka = $televizeAktivniRow?->poznamka;
                $this->template->televize_aktivni_do = $televizeAktivniRow?->datum_do;

                $posledniPlatbaSpolek = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->where('spolek', 1)->order('id DESC')->limit(1);
                $posledniPlatbaDruzstvo = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->where('druzstvo', 1)->order('id DESC')->limit(1);
                $posledniPlatbaData = $posledniPlatbaSpolek->fetch();
                if ($posledniPlatbaData !== null) {
                    $this->template->money_lastpay_spolek = (null === $posledniPlatbaData->datum) ? 'NIKDY' : ($posledniPlatbaData->datum->format('d.m.Y').' ('.$posledniPlatbaData->castka.')');
                } else {
                    $this->template->money_lastpay_spolek = '?';
                }
                $posledniPlatbaData = $posledniPlatbaDruzstvo->fetch();
                if ($posledniPlatbaData !== null) {
                    $this->template->money_lastpay_druzstvo = (null === $posledniPlatbaData->datum) ? 'NIKDY' : ($posledniPlatbaData->datum->format('d.m.Y').' ('.$posledniPlatbaData->castka.')');
                } else {
                    $this->template->money_lastpay_druzstvo = '?';
                }
                $posledniAktivace = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', [4, 5])->order('id DESC')->limit(1);
                $posledniAktivaceData = $posledniAktivace->fetch();
                if ($posledniAktivaceData !== null) {
                    $this->template->money_lastact = (null === $posledniAktivaceData->datum) ? 'NIKDY' : ($posledniAktivaceData->datum->format('d.m.Y').' ('.$posledniAktivaceData->castka.')');
                } else {
                    $this->template->money_lastact = '?';
                }
                $stavUctuSpolek = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('spolek', 1)->sum('castka') ?: 0;
                $stavUctuDruzstvo = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->sum('castka') ?: 0;

                $this->template->money_bal_druzstvo = $stavUctuDruzstvo;
                if ($uzivatel->kauce_mobil > 0) {
                    $this->template->money_bal_spolek = ($stavUctuSpolek - $uzivatel->kauce_mobil).' (kauce: '.$uzivatel->kauce_mobil.')';
                    // $this->template->money_bal_druzstvo = ($stavUctuDruzstvo - $uzivatel->kauce_mobil).' (kauce: '.$uzivatel->kauce_mobil.')';
                } else {
                    $this->template->money_bal_spolek = $stavUctuSpolek;
                }

                $stavUctuDph = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('spolek', 1)->where("datum>='2017-11-01'")->where('castka>0')->sum('castka') ?: 0;
                $this->template->money_dph = $stavUctuDph;

                $this->template->u = $uzivatel;

                $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)');

                $subnetLinks = $this->getSubnetLinksFromIPs($ipAdresy);
                $wewimoLinks = $this->getWewimoLinksFromIPs($ipAdresy);

                $uzivatelEditLink = $this->link('Uzivatel:edit', ['id' => $uid]);
                $this->template->adresy = $this->ipAdresa->getIPTable($ipAdresy, true, $subnetLinks, $wewimoLinks, $uzivatelEditLink, $this->getParameter('igw', false), [$this, 'linker']);

                if ($ipAdresy->count() > 0) {
                    $this->template->adresyline = join(', ', array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
                } else {
                    $this->template->adresyline = null;
                }

                $this->template->canViewOrEdit = $this->smiUpravitUzivatele($uzivatel);
                $this->template->dotazRozpisPlateb = $this->template->canViewOrEdit ? $this->dotazPredOdeslanimRozpisu($uzivatel) : null;
                $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uzivatel->id);

                $this->template->activaceVisible = false;
                $this->template->reactivaceVisible = false;
                if ((int) $uzivatel->money_aktivni === 0
                    && (
                        ($stavUctuSpolek - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku()
                        || ($stavUctuDruzstvo - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku()
                    )
                ) {
                    if ((int) $uzivatel->money_deaktivace === 0) {
                        $this->template->activaceVisible = true;
                    }
                    if ((int) $uzivatel->money_deaktivace === 1) {
                        $this->template->reactivaceVisible = true;
                    }
                }
                $this->template->deactivaceVisible =
                    1 === (int) $uzivatel->money_aktivni
                    && 0 === (int) $uzivatel->money_deaktivace;

                $this->template->igw = $this->getParameter('igw', false);
                $this->template->smlouvaStavSluzba = $this->smlouvaStavSluzba;
                $this->template->smlouvy = $this->smlouva->getByUzivatelId($uid);
            }
        }
    }

    public function renderPaymentSummary(): void {
        $uid = $this->getParameter('id');
        $uzivatel = $this->uzivatel->getUzivatel($uid);
        if ($uzivatel === null) {
            $this->error('Uživatel nebyl nalezen.');
        }
        $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->sum('castka');

        $this->template->u = $uzivatel;
        $this->template->stavUctu = $stavUctu;
        $this->template->today = new \DateTime();

        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();

        $pravidelne_mesicni_platby = [['Pevný přístup k internetu', $this->parameters->getVyseClenskehoPrispevku()]];
        if ($televizeRow !== null && (int) $televizeRow->objednana === 1) {
            array_push($pravidelne_mesicni_platby, ['Televize - START balíček SledovaniTV', $televizeRow->cena]);
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
        $datumPlatby = new \DateTime($today->format('Y-m-25')); // 25. den v mesici
        if ((int)$today->format('j') > 25) {
            $datumPlatby = (clone $today)->modify('+7 days'); // dnes + 7 dni
        }
        $this->template->datumPlatby = $datumPlatby;

        $poznamka = "{$nazev_uzivatele} UID{$uzivatel->id} QR1";

        $spayd = sprintf('SPD*1.0*ACC:%s*AM:%.2f*CC:CZK*MSG:%s*X-VS:%u', $cisloUctuIBAN, $platby_celkem, $poznamka, $uzivatel->id);
        $spayd .= sprintf('*DT:%s', $datumPlatby->format("Ymd"));

        $this->template->spayd = $spayd;
    }

    public function createComponentLogTable(): Components\LogTable {
        return $this->logTableFactory->create($this);
    }

    public function renderEdit(): void {
        if (($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) !== null) {
            $this->template->canViewOrEdit = $this->smiUpravitUzivatele($uzivatel);
        } else {
            $this->template->canViewOrEdit = true;
        }
    }

    protected function createComponentUzivatelForm(): Form {
        $typClenstvi = [-1 => '-'] + $this->typClenstvi->getTypyClenstvi()->fetchPairs('id', 'text');
        $typPravniFormy = $this->typPravniFormyUzivatele->getTypyPravniFormyUzivatele()->fetchPairs('id', 'text');
        $zpusobPripojeni = $this->zpusobPripojeni->getZpusobyPripojeni()->fetchPairs('id', 'text');
        $technologiePripojeni = $this->technologiePripojeni->getTechnologiePripojeni()->fetchPairs('id', 'text');

        $aps = $this->oblast->formatujOblastiSAP($this->oblast->getSeznamOblasti(), false);

        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid());
        if (count($oblastiSpravce) > 0) {
            $aps0 = $this->oblast->formatujOblastiSAP($oblastiSpravce);
            $aps = $aps0 + $aps;
        }
        //\Tracy\Debugger::barDump($aps);

        $form = new Form($this, 'uzivatelForm');
        $form->addHidden('id');
        $ipOtisk = $form->addHidden('ipOtisk');
        if ($this->getHttpRequest()->isMethod('POST') === false) {
            $ipOtisk->setDefaultValue($this->presunIpSluzba->otiskIp((int) $this->getParameter('id')));
        }
        $form->addSelect('Ap_id', 'Oblast - AP', $aps);
        $pravniForma = $form->addSelect('TypPravniFormyUzivatele_id', 'Právní forma', $typPravniFormy)->addRule(Form::Filled, 'Vyberte typ právní formy');
        $form->addText('firma_nazev', 'Název firmy', 30)->addConditionOn($pravniForma, Form::Equal, Model\TypPravniFormyUzivatele::PRAVNICKA_OSOBA)->setRequired('Zadejte název firmy');
        $form->addText('firma_ico', 'IČO', 8)->addConditionOn($pravniForma, Form::Equal, Model\TypPravniFormyUzivatele::PRAVNICKA_OSOBA)->setRequired('Zadejte IČ');
        //http://phpfashion.com/jak-overit-platne-ic-a-rodne-cislo
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno');
        $form->addText('prijmeni', 'Přijmení', 30)->setRequired('Zadejte příjmení');
        $form->addDate('datum_narozeni', 'Datum narození:');
        // ->addRule($form::Max, 'Nesmí být v budoucnu', new \Nette\Utils\DateTime('-1 hours'));
        $form->addText('nick', 'Nick (přezdívka)', 30)->setRequired('Zadejte nickname');
        $form->addText('email', 'Email', 30)->setRequired('Zadejte email')->addRule(Form::Email, 'Musíte zadat platný email');
        $form->addText('email2', 'Sekundární email', 30)->addCondition(Form::Filled)->addRule(Form::Email, 'Musíte zadat platný email');
        $form->addText('telefon', 'Telefon', 30)->setRequired('Zadejte telefon');
        if (count($this->spravceOblasti->getOblastiSpravce($this->getParameter('id'))) > 0) {
            $form->addCheckbox('publicPhone', 'Telefon je viditelný pro členy')->setDefaultValue(true);
        }
        $form->addText('cislo_clenske_karty', 'Číslo členské karty', 30);
        $form->addText('kauce_mobil', 'Kauce na mobilní tarify', 30);
        $form->addText('ulice_cp', 'Adresa (ulice a čp)', 30)->setRequired('Zadejte ulici a čp');
        $form->addText('mesto', 'Adresa (obec)', 30)->setRequired('Zadejte město');
        $form->addText('psc', 'Adresa (psč)', 5)->setRequired('Zadejte psč')->addRule(Form::Integer, 'PSČ musí být číslo');
        $form->addSelect('druzstvo', 'Právní vztah k družstvu', [1 => 'Ano',0 => 'Ne'])->setDefaultValue(1)->addRule(Form::Filled, 'Vyberte Právní vztah k družstvu');
        $form->addSelect('smazano', 'Smazán v družstvu', [1 => 'Ano',0 => 'Ne'])->setDefaultValue(0)->addRule(Form::Filled, 'Vyberte Smazán v družstvu');
        $form->addSelect('spolek', 'Právní vztah ke spolku', [1 => 'Ano',0 => 'Ne'])->setDefaultValue(0)->addRule(Form::Filled, 'Vyberte Právní vztah ke spolku');
        $form->addSelect('TypClenstvi_id', 'Typ členství ve spolku', $typClenstvi);
        $form->addTextArea('poznamka', 'Poznámka', 50, 12);
        $form->addTextArea('gpg', 'GPG klíč', 50, 12);
        $form->addSelect('TechnologiePripojeni_id', 'Technologie připojení', $technologiePripojeni)->addRule(Form::Filled, 'Vyberte technologii připojení');
        $form->addSelect('ZpusobPripojeni_id', 'Způsob připojení', $zpusobPripojeni)->addRule(Form::Filled, 'Vyberte způsob připojení');

        $form->addText('ipsubnet', 'Přidat všechny ip ze subnetu (x.y.z.w/c)', 20);
        $form->addText('iprange', 'Přidat rozsah ip (x.y.z.w-x.y.z.w)', 32);

        $typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
        $data = $this->ipAdresa;
        $ips = $form->addDynamic('ip', function (Container $ip) use ($data, $typyZarizeni) {
            $data->getIPForm($ip, $typyZarizeni);

            $ip->addSubmit('remove', '– Odstranit IP')
                ->setHtmlAttribute('class', 'btn btn-danger btn-xs btn-white')
                ->setValidationScope(null)
                ->addRemoveOnClick();
        }, $this->getParameter('id') > 0 ? 0 : 1);

        $ips->addSubmit('add', '+ Přidat další IP')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope([])
            ->addCreateOnClick(true, function (Container $replicator, Container $ip) {
                $ip->setValues(array('internet' => 1));
                //\Tracy\Debugger::barDump($ip);
            });

        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-success btn-white default btn-edit-save');
        $form->onSuccess[] = array($this, 'uzivatelFormSucceded');
        $form->onValidate[] = array($this, 'validateUzivatelForm');

        $form->setDefaults(array(
            'TypClenstvi_id' => -1,
            'TypPravniFormyUzivatele_id' => Model\TypPravniFormyUzivatele::FYZICKA_OSOBA,
        ));

        // pokud editujeme, nacteme existujici ipadresy
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && $submitujeSe === false) {
            $values = $this->uzivatel->getUzivatel($this->getParameter('id'));
            if ($values !== null) {
                foreach ($values->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)') as $ip_id => $ip_data) {
                    if ($ip_data->heslo_sifrovane === 1) {
                        $decrypted = $this->cryptosvc->decrypt($ip_data->heslo);
                        $ipdata = $ip_data->toArray();
                        $ipdata['heslo'] = $decrypted;
                        $ips->createOne($ip_id)->setValues($ipdata);
                    } else {
                        $ips->createOne($ip_id)->setValues($ip_data);
                    }
                }
                $form->setValues($values);
            }
        }

        return $form;
    }

    public function validateUzivatelForm(Nette\Forms\Form $form): void {
        $data = $form->getHttpData();
        if (is_array($data) === false) {
            throw new Nette\UnexpectedValueException('Data celého formuláře musí být pole.');
        }

        // Validujeme jenom při uložení formuláře
        if ($this->odeslanoTlacitkemUlozit($form) === false) {
            return;
        }

        $uzivatelBeforeSave = $this->uzivatel->getUzivatel($data['id']);

        if ($uzivatelBeforeSave !== null) {
            if ((int) $uzivatelBeforeSave->druzstvo === 1 && $data['druzstvo'] !== '1') {
                $form->addError('Právní vztah k družstvu již nelze nikdy změnit na Ne.');
            }

            if ((int) $uzivatelBeforeSave->spolek === 1 && $data['spolek'] !== '1') {
                $form->addError('Právní vztah ke spolku již nelze nikdy změnit na Ne.');
            }
        } else {
            if ($data['druzstvo'] === '1' && $data['TypPravniFormyUzivatele_id'] === (string) Model\TypPravniFormyUzivatele::FYZICKA_OSOBA) {
                if (($data['datum_narozeni'] ?? "") === "") {
                    $form->addError('Právní vztah k družstvu vyžaduje zadání data narození.');
                }
            }

            if ($data['smazano'] !== '0') {
                $form->addError('Nelze založit uživatele s atributem Smazáno.');
            }

            if ($data['spolek'] === '1' && $data['druzstvo'] === '1') {
                $form->addError('Založení nového uživatele NEPODPORUJE právní vztahy k družstvu a ke spolku najednou, založte uživatele jen s jedním právním vztahem.');
            }
        }

        if ($data['spolek'] === '1') {
            if (($data['TypClenstvi_id'] ?? '-1') === '-1') {
                $form->addError('Právní vztah ke spolku vyžaduje vyplnění Typu členství.');
            }
        }

        if ($data['spolek'] === '0') {
            if (($data['TypClenstvi_id'] ?? -1) >= 0) {
                $form->addError('Právní vztah ke spolku Ne vyžaduje nevyplnění Typu členství.');
            }
        }

        $zapisSubnetu = self::oriznoutRadek($data['ipsubnet'] ?? null);
        if ($zapisSubnetu !== '' && $zapisSubnetu !== '0') {
            if (preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))$/i', $zapisSubnetu) !== 1) {
                $form->addError('IP subnet není validní!');
            }
        }
        $zapisRozsahu = self::oriznoutRadek($data['iprange'] ?? null);
        if ($zapisRozsahu !== '' && $zapisRozsahu !== '0') {
            if (preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])-(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $zapisRozsahu) !== 1) {
                $form->addError('IP rozsah není validní!');
            }
        }

        if (isset($data['ip'])) {
            $formIPs = [];
            foreach ($data['ip'] as $ip) {
                $zapisIp = self::oriznoutRadek($ip['ip_adresa'] ?? null);

                if ($this->ipAdresa->validateIP($zapisIp) === false) {
                    $form->addError('IP adresa '.$zapisIp.' není validní IPv4 adresa!');
                }

                $duplIp = $this->ipAdresa->getDuplicateIP($zapisIp, $ip['id']);
                if (in_array($duplIp, [null, '', '0'], true) === false) {
                    $form->addError('IP adresa '.$duplIp.' již  v databázi existuje!');
                }

                $formIPs[] = $zapisIp;
            }

            // Tohle prohledá duplikátní IP přímo v formuláři
            // protože na ty se nepřijde pomocí getDuplicateIP
            $formDuplicates = [];
            foreach (array_count_values($formIPs) as $val => $c) {
                if ($c > 1) {
                    $formDuplicates[] = $val;
                }
            }

            if (0 !== count($formDuplicates)) {
                $formDuplicatesReadible = implode(', ', $formDuplicates);
                $form->addError('IP adresa '.$formDuplicatesReadible.' je v tomto formuláři vícekrát!');
            }
        }

        $values = $form->getUntrustedValues();

        // if (($values->spolek == 1 && $values->TypClenstvi_id > 1) || ($values->druzstvo == 1 && $values->smazano == 0)) {
        //     $duplMail = $this->uzivatel->getDuplicateEmailArea($values->email, $values->id);

        //     if ($duplMail) {
        //         $form->addError('Tento email již v DB existuje v oblasti: '.$duplMail);
        //     }

        //     if (!empty($values->email2)) {
        //         $duplMail2 = $this->uzivatel->getDuplicateEmailArea($values->email2, $values->id);

        //         if ($duplMail2) {
        //             $form->addError('Tento email již v DB existuje v oblasti: '.$duplMail2);
        //         }
        //     }
        // }
    }

    /** @param ArrayHash<mixed>&object{ip: ArrayHash<ArrayHash<mixed>>} $values */
    public function uzivatelFormSucceded(Form $form, ArrayHash $values): void {
        // upravuje se člen ze skrytého pole id; nového člena šablona dovolí založit každému
        if (in_array($values->id, [null, '', 0, '0'], true) === false) {
            $uzivatel = $this->uzivatel->getUzivatel($values->id);
            $this->vyzadujOpravneni($uzivatel !== null && $this->smiUpravitUzivatele($uzivatel));
        }

        if ($this->odeslanoTlacitkemUlozit($form) === false) {
            return; // bez tlačítka Uložit neproběhly kontroly formuláře
        }

        $odeslanaId = [];
        foreach ($values->ip as $ip) {
            if (in_array($ip->id, [null, '', 0, '0'], true) === false) {
                $odeslanaId[] = (int) $ip->id;
            }
        }
        try {
            if (in_array($values->id, [null, '', 0, '0'], true) === false) {
                $cil = $this->presunIpSluzba->priEditaci((int) $values->id, (string) $values->ipOtisk, $odeslanaId, function () use ($values): array {
                    $uzivatel = $this->uzivatel->getUzivatel($values->id);
                    $this->vyzadujOpravneni($uzivatel !== null && $this->smiUpravitUzivatele($uzivatel));
                    return $this->ulozUzivatele($values, $uzivatel);
                });
            } else {
                if ($odeslanaId !== []) {
                    throw new \DomainException('Nový člen nemůže převzít existující IP přes běžnou editaci.');
                }
                $cil = $this->ulozUzivatele($values, null);
            }
        } catch (\DomainException $e) {
            $form->addError($e->getMessage());
            return;
        }
        if ($cil['poslatRegistraciSpolku']) {
            $this->sendSpolekRegistrationEmail($cil['id']);
        }
        $this->redirect($cil['stranka'], ['id' => $cil['id']]);
    }

    /**
     * @param ArrayHash<mixed>&object{ip: ArrayHash<ArrayHash<mixed>>} $values
     * @return array{stranka: string, id: int, poslatRegistraciSpolku: bool}
     */
    private function ulozUzivatele(Nette\Utils\ArrayHash $values, ?Nette\Database\Table\ActiveRow $uzivatel): array {
        $poslatRegistraciSpolku = false;
        $log = array();
        $idUzivatele = $values->id;
        $ips = $values->ip;
        $existingIPs = [];
        foreach ($ips as $ip) {
            if (in_array($ip->id, [null, '', 0, '0'], true) === false) {
                $oldip = $this->ipAdresa->getIPAdresa($ip->id);
                if ($oldip === null) {
                    $this->error('IP adresa nebyla nalezena.');
                }
                $this->vyzadujOpravneni($uzivatel !== null && $oldip->Uzivatel_id === $uzivatel->id);
                $existingIPs[$ip->id] = $oldip->toArray();
            }
        }
        $ipsubnet = $values->ipsubnet;
        $iprange = $values->iprange;
        // \Tracy\Debugger::barDump($ips);exit;
        unset($values['ip'], $values['ipsubnet'], $values['iprange'], $values['ipOtisk']);

        $genaddresses = [];
        $newUserIPIDs = [];
        $smtpIPIDs = [];
        $dnatIPIDs = [];

        // generovani ip pro vlozeni ze subnetu
        $genaddresses = $this->ipAdresa->getListOfIPFromSubnet($ipsubnet);
        // generovani ip pro vlozeni z rozsahu
        $genaddresses = array_merge($genaddresses, $this->ipAdresa->getListOfIPFromRange($iprange));

        if (in_array($values->cislo_clenske_karty, [null, '', '0'], true)) {
            $values->cislo_clenske_karty = null;
        }
        if (in_array($values->firma_nazev, [null, '', '0'], true)) {
            $values->firma_nazev = null;
        }
        if (in_array($values->firma_ico, [null, '', '0'], true)) {
            $values->firma_ico = null;
        }
        if (in_array($values->email2, [null, '', '0'], true)) {
            $values->email2 = null;
        }
        if (in_array($values->poznamka, [null, '', '0'], true)) {
            $values->poznamka = null;
        }
        if (($values->TypClenstvi_id ?? -1) < 0) {
            $values->TypClenstvi_id = null;
        }

        // pri kazde editaci uzivatele nastavime znovu geocoding na pending, aby se znovu dohledaly geofraf. souradnice
        $values->location_status = 'pending';

        // Zpracujeme nejdriv uzivatele
        if (in_array($values->id, [null, '', 0, '0'], true)) {
            $values->regform_downloaded_password_sent = 0;
            if (($values->spolek === 1 && $values->TypClenstvi_id > 0) || ($values->druzstvo === 1 && $values->smazano === 0)) {
                $values->money_aktivni = 1;
            } else {
                $values->money_aktivni = 0;
            }
            $values->zalozen = new \DateTime();
            $values->heslo = $this->uzivatel->generateStrongPassword();
            $values->heslo_hash = $this->uzivatel->generateWeakHash($values->heslo);
            $values->heslo_strong_hash = $this->uzivatel->generateStrongHash($values->heslo);
            $values->id = $this->uzivatel->getNewID();

            $uzivatel = $this->uzivatel->insertRow($values);

            $idUzivatele = $uzivatel->id;
            $this->log->logujInsert($values, 'Uzivatel', $log);

            if ($values->spolek === 1) {
                if ($values->TypClenstvi_id > 0) {
                    $this->sendSpolekRegistrationEmail($idUzivatele);
                } else {
                    $this->sendSpolekNotificationEmail($idUzivatele);
                }
            }

            if ($values->druzstvo === 1) {
                $this->sendDruzstvoRegistrationEmail($idUzivatele);
            }
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);
            if ($olduzivatel === null) {
                $this->error('Uživatel nebyl nalezen.');
            }
            // Snímek před uložením: líně načítaný řádek by se po update() při čtení chybějícího sloupce načetl celý znovu, už s novými hodnotami.
            $puvodniUzivatel = $olduzivatel->toArray();
            $bylPlanovany = 0 === $puvodniUzivatel['TypClenstvi_id'];

            if (($puvodniUzivatel['email'] ?? '') !== ($values->email ?? '') || ($puvodniUzivatel['email2'] ?? '') !== ($values->email2 ?? '')) {
                $values->email_invalid = 0;
            }

            // pokud neni aktivni ani ve spolku ani v druzstvu
            if (Model\Uzivatel::jeAktivniClen($values->spolek, $values->TypClenstvi_id, $values->druzstvo, $values->smazano) === false) {
                $this->aplikaceToken->deleteTokensForUID($idUzivatele);
            }

            // TODO: spolek / druzstvo
            if ($bylPlanovany && 1 === $values->TypClenstvi_id) {
                $this->povoleneSMTP->deleteIPs($smtpIPIDs);
                $existinguserIPIDs = array_keys($olduzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
                $this->ipAdresa->deleteIPAdresy($existinguserIPIDs);
                $this->uzivatel->delete(['id' => [$idUzivatele]]);
                return ['stranka' => 'UzivatelList:list', 'id' => (int) $puvodniUzivatel['Ap_id'], 'poslatRegistraciSpolku' => false];
            }

            // TODO: spolek / druzstvo
            if ($bylPlanovany && $values->TypClenstvi_id !== null && 0 !== $values->TypClenstvi_id) {
                $values->zalozen = new \DateTime();
                $values->money_aktivni = 1;
            }

            $uzivatel = $olduzivatel;
            $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($puvodniUzivatel, $values, 'Uzivatel', $log);

            // TODO: spolek / druzstvo
            if ($bylPlanovany && $values->TypClenstvi_id !== null && 0 !== $values->TypClenstvi_id && $values->spolek === 1) {
                $poslatRegistraciSpolku = true;
            }
        }

        // Potom zpracujeme IPcka
        foreach ($ips as $ip) {
            $ip->Uzivatel_id = $idUzivatele;
            $idIp = $ip->id;

            if (in_array($ip->ip_adresa, [null, '', '0'], true)) {
                $ip->ip_adresa = null;
            }
            if (in_array($ip->hostname, [null, '', '0'], true)) {
                $ip->hostname = null;
            }
            if (in_array($ip->mac_adresa, [null, '', '0'], true)) {
                $ip->mac_adresa = null;
            }
            if (in_array($ip->popis, [null, '', '0'], true)) {
                $ip->popis = null;
            }
            if (in_array($ip->login, [null, '', '0'], true)) {
                $ip->login = null;
            }
            if (in_array($ip->heslo, [null, '', '0'], true)) {
                $ip->heslo = null;
            }

            if ($ip->heslo && strlen($ip->heslo) > 0) {
                $ip->heslo = $this->cryptosvc->encrypt($ip->heslo);
                $ip->heslo_sifrovane = 1;
            } else {
                $ip->heslo_sifrovane = 0;
            }

            if (in_array($ip->id, [null, '', 0, '0'], true)) {
                $idIp = $this->ipAdresa->insertRow($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa['.$idIp.']', $log);
            } else {
                $oldip = $existingIPs[$idIp];
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa['.$idIp.']', $log);
            }
            $newUserIPIDs[] = intval($idIp);
        }
        // rozsahy ip adres
        foreach ($genaddresses as $gi) {
            $duplIp = $this->ipAdresa->getDuplicateIP($gi, 0);
            if (in_array($duplIp, [null, '', '0'], true)) {
                $rngip = [
                    'ip_adresa' => $gi,
                    'internet' => true,
                    'smokeping' => false,
                    'mac_filter' => false,
                    'dhcp' => false,
                    'Uzivatel_id' => $idUzivatele,
                ];
                $idrngip = $this->ipAdresa->insertRow($rngip)->id;
                $this->log->logujInsert($rngip, 'IPAdresa['.$idrngip.']', $log);
                $newUserIPIDs[] = intval($idrngip);
            }
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $userIPIDs = array_keys($uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
        $toDelete = array_values(array_diff($userIPIDs, $newUserIPIDs));
        if ($toDelete !== []) {
            foreach ($toDelete as $idIp) {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                if ($oldip === null) {
                    continue;
                }
                $this->log->logujDelete($oldip, 'IPAdresa['.$idIp.']', $log);
                $isSMTP = $this->povoleneSMTP->getIP($oldip->id);
                if ($isSMTP !== null) {
                    $smtpIPIDs[] = intval($isSMTP->id);
                }
                $isDNAT = $this->dnat->getIP($oldip->id);
                if ($isDNAT !== null) {
                    $dnatIPIDs[] = intval($isDNAT->ip);
                }
            }
        }
        $this->povoleneSMTP->deleteIPs($smtpIPIDs);

        $this->dnat->deleteIPs($dnatIPIDs);

        $this->ipAdresa->deleteIPAdresy($toDelete);

        $this->log->loguj('Uzivatel', $idUzivatele, $log);

        return ['stranka' => 'Uzivatel:show', 'id' => (int) $idUzivatele, 'poslatRegistraciSpolku' => $poslatRegistraciSpolku];
    }

    public function actionPresunIp(int $id, int $ipId): void {
        $zdroj = $this->zdrojPresunu();
        $ip = $this->ipAdresa->findOneBy(['id' => $ipId, 'Uzivatel_id' => $id, 'Ap_id' => null]);
        $this->template->presunZdroj = $zdroj;
        $this->template->presunIp = $ip;
        $this->template->pocetDnat = $ip !== null ? $this->dnat->findBy(['ip' => $ipId])->count('*') : 0;
        $this->template->pocetSmtp = $ip !== null ? $this->povoleneSMTP->findBy(['IPAdresa_id' => $ipId])->count('*') : 0;
        if ($ip === null) {
            $this['presunIpForm']->addError(Services\PresunIpSluzba::ZMENENY_VLASTNIK);
        }
    }

    private function zdrojPresunu(): Nette\Database\Table\ActiveRow {
        if ($this->getAction() !== 'presunIp') {
            $this->error('Stránka neexistuje.');
        }
        $zdroj = $this->uzivatel->getUzivatel((int) $this->getParameter('id'));
        $this->vyzadujOpravneni($zdroj !== null && $this->smiUpravitUzivatele($zdroj));
        return $zdroj;
    }

    protected function createComponentPresunIpForm(): Form {
        $zdroj = $this->zdrojPresunu();
        $moznosti = [];
        $cile = $this->presunIpSluzba->cileNaAp((int) $zdroj->Ap_id, (int) $zdroj->id);
        foreach ($this->omezNaUpravitelneUzivateleNaAp($cile, (int) $zdroj->Ap_id) as $clen) {
            $moznosti[$clen->id] = $clen->id . ' - ' . $clen->nick . ' - ' . $clen->jmeno . ' ' . $clen->prijmeni
                . ((bool) $clen->money_aktivni ? '' : ' (účet není aktivní)');
        }
        $form = new Form();
        $form->addSelect('cil', 'Nový člen', $moznosti)->setPrompt('Vyberte člena stejného AP')->setRequired('Vyberte nového člena.');
        $form->addSubmit('potvrdit', 'Přesunout IP')->setHtmlAttribute('class', 'btn btn-primary');
        $form->onSuccess[] = [$this, 'presunIpFormSucceded'];
        if ($moznosti === []) {
            $form->addError('Na tomto AP není jiný vhodný člen, kterého smíte upravovat.');
        }
        return $form;
    }

    public function presunIpFormSucceded(Form $form, Nette\Utils\ArrayHash $values): void {
        $zdroj = $this->zdrojPresunu();
        try {
            $this->presunIpSluzba->presun(
                (int) $this->getParameter('ipId'),
                (int) $zdroj->id,
                (int) $values->cil,
                $this->getIdentity()->getUid(),
                function (Nette\Database\Row $clen): void {
                    $this->vyzadujOpravneni($this->smiUpravitUzivatele($clen));
                },
            );
        } catch (\DomainException $e) {
            $form->addError($e->getMessage());
            return;
        }
        $this->flashMessage('IP adresa byla přesunuta. Členství a účty obou členů zůstaly beze změny.');
        $this->redirect('Uzivatel:show', ['id' => (int) $values->cil]);
    }

    /** @param int|string|null $id */
    public function actionIds($id): void {
        if (in_array($id, [null, 0, '', '0'], true) === false) {
            if (($uzivatel = $this->uzivatel->getUzivatel($id)) !== null) {
                $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)');
                $ips = array_values($ipAdresy->fetchPairs('id', 'ip_adresa'));

                try {
                    $this->template->idsEvents = $this->idsConnector->getEventsForIps($ips);
                } catch (\Exception $ex) {
                    if (Debugger::$productionMode === true) {
                        throw $ex;
                    }
                    $this->template->idsEvents = []; // silently ignore in non-prod environment

                }
            }
        }
    }
}
