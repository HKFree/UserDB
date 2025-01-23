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
use Nette\Forms\Controls\SubmitButton;

/**
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{
    private $spravceOblasti;
    private $cestneClenstviUzivatele;
    private $typClenstvi;
    private $typPravniFormyUzivatele;
    private $typSpravceOblasti;
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
        $this->cryptosvc = $cryptosvc;
        $this->spravceOblasti = $prava;
        $this->cestneClenstviUzivatele = $cc;
        $this->typClenstvi = $typClenstvi;
        $this->typPravniFormyUzivatele = $typPravniFormyUzivatele;
        $this->zpusobPripojeni = $zpusobPripojeni;
        $this->technologiePripojeni = $technologiePripojeni;
        $this->uzivatel = $uzivatel;
        $this->ipAdresa = $ipAdresa;
        $this->ap = $ap;
        $this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->subnet = $subnet;
        $this->sloucenyUzivatel = $slUzivatel;
        $this->smlouva = $smlouva;
        $this->smlouvaStavSluzba = $smlouvaStavSluzba;
        $this->parameters = $parameters;
        $this->povoleneSMTP = $alowedSMTP;
        $this->dnat = $dnat;
        $this->pdfGenerator = $pdf;
        $this->mailService = $mailsvc;
        $this->idsConnector = $idsConnector;
        $this->aplikaceToken = $aplikaceToken;
        $this->stitek = $stitek;
        $this->stitkyUzivatele = $stitkyUzivatele;
        $this->stitkovac = $stitkovac;
        $this->databaseExplorer = $databaseExplorer;
        $this->requestDruzstvoContract = $requestDruzstvoContract;
    }

    protected function createComponentUserLabels(): UserLabelsComponent {
        // Předej do komponenty databázi a aktuální user ID
        $userId = $this->getParameter('id'); // Nebo jiný způsob získání ID uživatele
        $ulc = new UserLabelsComponent($this->databaseExplorer, $this->stitek, $this->stitkyUzivatele);
        $ulc->setUserId($userId);
        return $ulc;
    }

    public function sendSpolekNotificationEmail($idUzivatele) {
        try {
            $this->mailService->sendPlannedUserNotificationEmail($idUzivatele, $this->getIdentity()->getUid());
            $this->flashMessage('E-mail s notifikací správcům byl odeslán.');
        } catch (Nette\Mail\SmtpException $e) {
            $this->flashMessage('Odeslání e-mailu s notifikací správcům se nezdařilo. '.$e->getMessage(), 'danger');
        }
    }

    public function sendSpolekRegistrationEmail($idUzivatele) {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        $hash = base64_encode($idUzivatele.'-'.md5($this->parameters->salt . $newUser->zalozen));
        $link = 'https://moje.hkfree.org/uzivatel/confirm/'.$hash;

        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());

        try {
            $this->mailService->sendSpolekConfirmationRequest($newUser, $so, $link);
            $this->mailService->sendSpolekConfirmationRequestCopy($newUser, $so);

            $this->flashMessage('E-mail s žádostí o potvrzení e-mailu byl odeslán.');
        } catch (Nette\Mail\SmtpException $e) {
            $this->flashMessage('Odeslání e-mailu s žádostí o potvrzení e-mailu se nezdařilo! Napište userdb teamu o pomoc. '.$hash.$e->getMessage(), 'danger');
        }
    }

    public function sendDruzstvoRegistrationEmail($idUzivatele) {
        $newUser = $this->uzivatel->getUzivatel($idUzivatele);
        $hash = base64_encode($idUzivatele.'-'.md5($this->parameters->salt . $newUser->zalozen));
        $link = $this->link('//SelfService:confirmEmail', $hash);

        $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());

        try {
            $this->mailService->sendDruzstvoConfirmationRequest($newUser, $so, $link);
            $this->mailService->sendDruzstvoConfirmationRequestCopy($newUser, $so);

            $this->flashMessage('E-mail s žádostí o potvrzení registrace do družstva byl odeslán.');
        } catch (Nette\Mail\SmtpException $e) {
            $this->flashMessage('Odeslání e-mailu s žádostí o potvrzení registrace do družstva se nezdařilo! Napište userdb teamu o pomoc. '.$hash.$e->getMessage(), 'danger');
        }
    }

    public function renderShow() {
        if ($this->getParameter('id')) {
            $uid = $this->getParameter('id');
            if ($uzivatel = $this->uzivatel->getUzivatel($uid)) {
                $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
                $this->template->stitky = $this->stitek->getSeznamStitku();
                $this->template->stitkyUzivatele = $this->stitkyUzivatele->getStitekByUserId($uid);

                $this->template->money_act = (1 == $uzivatel->money_aktivni) ? 'ANO' : 'NE';
                $this->template->money_dis = (1 == $uzivatel->money_deaktivace) ? 'ANO' : 'NE';
                $posledniPlatbaSpolek = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->where('spolek', 1)->order('id DESC')->limit(1);
                $posledniPlatbaDruzstvo = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->where('druzstvo', 1)->order('id DESC')->limit(1);
                if ($posledniPlatbaSpolek->count() > 0) {
                    $posledniPlatbaData = $posledniPlatbaSpolek->fetch();
                    $this->template->money_lastpay_spolek = (null == $posledniPlatbaData->datum) ? 'NIKDY' : ($posledniPlatbaData->datum->format('d.m.Y').' ('.$posledniPlatbaData->castka.')');
                } else {
                    $this->template->money_lastpay_spolek = '?';
                }
                if ($posledniPlatbaDruzstvo->count() > 0) {
                    $posledniPlatbaData = $posledniPlatbaDruzstvo->fetch();
                    $this->template->money_lastpay_druzstvo = (null == $posledniPlatbaData->datum) ? 'NIKDY' : ($posledniPlatbaData->datum->format('d.m.Y').' ('.$posledniPlatbaData->castka.')');
                } else {
                    $this->template->money_lastpay_druzstvo = '?';
                }
                $posledniAktivace = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', [4, 5])->order('id DESC')->limit(1);
                if ($posledniAktivace->count() > 0) {
                    $posledniAktivaceData = $posledniAktivace->fetch();
                    $this->template->money_lastact = (null == $posledniAktivaceData->datum) ? 'NIKDY' : ($posledniAktivaceData->datum->format('d.m.Y').' ('.$posledniAktivaceData->castka.')');
                } else {
                    $this->template->money_lastact = '?';
                }
                $stavUctuSpolek = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('spolek', 1)->sum('castka');
                if (!$stavUctuSpolek || '' == $stavUctuSpolek) {
                    $stavUctuSpolek = 0;
                }
                $stavUctuDruzstvo = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->sum('castka');
                if (!$stavUctuDruzstvo || '' == $stavUctuDruzstvo) {
                    $stavUctuDruzstvo = 0;
                }

                $this->template->money_bal_druzstvo = $stavUctuDruzstvo;
                if ($uzivatel->kauce_mobil > 0) {
                    $this->template->money_bal_spolek = ($stavUctuSpolek - $uzivatel->kauce_mobil).' (kauce: '.$uzivatel->kauce_mobil.')';
                    // $this->template->money_bal_druzstvo = ($stavUctuDruzstvo - $uzivatel->kauce_mobil).' (kauce: '.$uzivatel->kauce_mobil.')';
                } else {
                    $this->template->money_bal_spolek = $stavUctuSpolek;
                }

                $stavUctuDph = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('spolek', 1)->where("datum>='2017-11-01'")->where('castka>0')->sum('castka');
                if (!$stavUctuDph || '' == $stavUctuDph) {
                    $stavUctuDph = 0;
                }
                $this->template->money_dph = $stavUctuDph;

                if ($this->sloucenyUzivatel->getIsAlreadyMaster($uid)) {
                    $this->flashMessage('Uživatel má pod sebou sloučené uživatele.');
                    $this->template->slaves = $this->sloucenyUzivatel->getSlaves($uid);
                } else {
                    $this->template->slaves = null;
                }
                if ($this->sloucenyUzivatel->getIsAlreadySlave($uid)) {
                    $this->flashMessage('Uživatel byl sloučen pod jiného uživatele.');
                    $this->template->master = $this->sloucenyUzivatel->getMaster($uid);
                    // \Tracy\Debugger::barDump($this->sloucenyUzivatel->getMaster($uid));
                } else {
                    $this->template->master = null;
                }

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

                $seznamUzivatelu = [];
                $oblastiAktualnihoUzivatele = $this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid());
                foreach ($oblastiAktualnihoUzivatele as $oblast) {
                    foreach ($oblast->related('Ap.Oblast_id') as $apid => $ap) {
                        // \Tracy\Debugger::barDump($ap->id);
                        $apcko = $this->ap->getAP($ap->id);
                        $subnety = $apcko->related('Subnet.Ap_id');
                        $seznamUzivatelu = array_merge($seznamUzivatelu, $this->uzivatel->findUsersIdsFromOtherAreasByAreaId($ap->id, $subnety));
                    }
                }
                // \Tracy\Debugger::barDump($seznamUzivatelu);

                $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT')
                                                    || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser())
                                                    || in_array($uid, $seznamUzivatelu);
                $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uzivatel->id);

                $this->template->activaceVisible = 0 == $uzivatel->money_aktivni && 0 == $uzivatel->money_deaktivace && ($stavUctuSpolek - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku() && ($stavUctuDruzstvo - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku();
                $this->template->reactivaceVisible = (0 == $uzivatel->money_aktivni && 1 == $uzivatel->money_deaktivace && ($stavUctuSpolek - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku() && ($stavUctuDruzstvo - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku())
                                                        || (1 == $uzivatel->money_aktivni && 1 == $uzivatel->money_deaktivace);
                $this->template->deactivaceVisible = 1 == $uzivatel->money_aktivni && 0 == $uzivatel->money_deaktivace;

                $this->template->igw = $this->getParameter('igw', false);
                $this->template->smlouvaStavSluzba = $this->smlouvaStavSluzba;
                $this->template->smlouvy = $this->smlouva->getByUzivatelId($uid);

                /* migrace 2025 temporary */
                $this->template->migrace2025Stitek1 = $this->parameters->migrace2025Stitek1;
                $this->template->maStitek = $this->stitkovac->hasStitekByText($uzivatel, $this->parameters->migrace2025Stitek1);
            }
        }
    }

    public function createComponentLogTable() {
        return $this->logTableFactory->create($this);
    }

    public function renderEdit() {
        if ($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'))) {
            $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());
            $seznamUzivatelu = [];
            $oblastiAktualnihoUzivatele = $this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid());
            foreach ($oblastiAktualnihoUzivatele as $oblast) {
                foreach ($oblast->related('Ap.Oblast_id') as $apid => $ap) {
                    // \Tracy\Debugger::barDump($ap->id);
                    $apcko = $this->ap->getAP($ap->id);
                    $subnety = $apcko->related('Subnet.Ap_id');
                    $seznamUzivatelu = array_merge($seznamUzivatelu, $this->uzivatel->findUsersIdsFromOtherAreasByAreaId($ap->id, $subnety));
                }
            }

            $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT')
                                                || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser())
                                                || in_array($uzivatel->id, $seznamUzivatelu);
        } else {
            $this->template->canViewOrEdit = true;
        }
    }

    protected function createComponentUzivatelForm() {
        $typClenstvi = [-1 => '-'] + $this->typClenstvi->getTypyClenstvi()->fetchPairs('id', 'text');
        $typPravniFormy = $this->typPravniFormyUzivatele->getTypyPravniFormyUzivatele()->fetchPairs('id', 'text');
        $zpusobPripojeni = $this->zpusobPripojeni->getZpusobyPripojeni()->fetchPairs('id', 'text');
        $technologiePripojeni = $this->technologiePripojeni->getTechnologiePripojeni()->fetchPairs('id', 'text');

        $aps = $this->oblast->formatujOblastiSAP($this->oblast->getSeznamOblasti());

        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid());
        if (count($oblastiSpravce) > 0) {
            $aps0 = $this->oblast->formatujOblastiSAP($oblastiSpravce);
            $aps = $aps0 + $aps;
        }
        //\Tracy\Debugger::barDump($aps);

        $form = new Form($this, 'uzivatelForm');
        $form->addHidden('id');
        $form->addSelect('Ap_id', 'Oblast - AP', $aps);
        $form->addSelect('TypPravniFormyUzivatele_id', 'Právní forma', $typPravniFormy)->addRule(Form::Filled, 'Vyberte typ právní formy');
        $form->addText('firma_nazev', 'Název firmy', 30)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::Equal, 2)->setRequired('Zadejte název firmy');
        $form->addText('firma_ico', 'IČO', 8)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::Equal, 2)->setRequired('Zadejte IČ');
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
            $form->addCheckBox('publicPhone', 'Telefon je viditelný pro členy', 30)->setDefaultValue(true);
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
        $ips = $form->addDynamic('ip', function (Container $ip) use ($data, $typyZarizeni, $form) {
            $data->getIPForm($ip, $typyZarizeni);

            $ip->addSubmit('remove', '– Odstranit IP')
                ->setHtmlAttribute('class', 'btn btn-danger btn-xs btn-white')
                ->setValidationScope(null)
                ->addRemoveOnClick();
        }, ($this->getParameter('id') > 0 ? 0 : 1));

        $ips->addSubmit('add', '+ Přidat další IP')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope(null)
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
            'TypPravniFormyUzivatele_id' => 1,
        ));

        // pokud editujeme, nacteme existujici ipadresy
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $values = $this->uzivatel->getUzivatel($this->getParameter('id'));
            if ($values) {
                foreach ($values->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)') as $ip_id => $ip_data) {
                    if ($ip_data->heslo_sifrovane == 1) {
                        $decrypted = $this->cryptosvc->decrypt($ip_data->heslo);
                        $ipdata = $ip_data->toArray();
                        $ipdata['heslo'] = $decrypted;
                        $form["ip"][$ip_id]->setValues($ipdata);
                    } else {
                        $form["ip"][$ip_id]->setValues($ip_data);
                    }
                }
                $form->setValues($values);
            }
        }

        return $form;
    }

    public function validateUzivatelForm($form) {
        $data = $form->getHttpData();

        // Validujeme jenom při uložení formuláře
        if (!isset($data['save'])) {
            return 0;
        }

        $uzivatelBeforeSave = $this->uzivatel->getUzivatel($data['id']);

        if ($uzivatelBeforeSave) {
            if ($uzivatelBeforeSave->druzstvo == 1 && $data['druzstvo'] != 1) {
                $form->addError('Právní vztah k družstvu již nelze nikdy změnit na Ne.');
            }

            if ($uzivatelBeforeSave->spolek == 1 && $data['spolek'] != 1) {
                $form->addError('Právní vztah ke spolku již nelze nikdy změnit na Ne.');
            }
        } else {
            if ($data['druzstvo'] == 1) {
                if (($data['datum_narozeni'] ?? "") == "") {
                    $form->addError('Právní vztah k družstvu vyžaduje zadání data narození.');
                }
            }

            if ($data['smazano'] != 0) {
                $form->addError('Nelze založit uživatele s atributem Smazáno.');
            }

            if ($data['spolek'] == 1 && $data['druzstvo'] == 1) {
                $form->addError('Založení nového uživatele NEPODPORUJE právní vztahy k družstvu a ke spolku najednou, založte uživatele jen s jedním právním vztahem.');
            }
        }

        if ($data['spolek'] == 1) {
            if (($data['TypClenstvi_id'] ?? -1) == -1) {
                $form->addError('Právní vztah ke spolku vyžaduje vyplnění Typu členství.');
            }
        }

        if ($data['spolek'] == 0) {
            if (($data['TypClenstvi_id'] ?? -1) >= 0) {
                $form->addError('Právní vztah ke spolku Ne vyžaduje nevyplnění Typu členství.');
            }
        }

        if (isset($data['ipsubnet']) && !empty($data['ipsubnet'])) {
            if (!preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))$/i', $data['ipsubnet'])) {
                $form->addError('IP subnet není validní!');
            }
        }
        if (isset($data['iprange']) && !empty($data['iprange'])) {
            if (!preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])-(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $data['iprange'])) {
                $form->addError('IP rozsah není validní!');
            }
        }

        if (isset($data['ip'])) {
            $formIPs = [];
            foreach ($data['ip'] as $ip) {
                if (!$this->ipAdresa->validateIP($ip['ip_adresa'])) {
                    $form->addError('IP adresa '.$ip['ip_adresa'].' není validní IPv4 adresa!');
                }

                $duplIp = $this->ipAdresa->getDuplicateIP($ip['ip_adresa'], $ip['id']);
                if ($duplIp) {
                    $form->addError('IP adresa '.$duplIp.' již  v databázi existuje!');
                }

                $formIPs[] = $ip['ip_adresa'];
            }

            // Tohle prohledá duplikátní IP přímo v formuláři
            // protože na ty se nepřijde pomocí getDuplicateIP
            $formDuplicates = [];
            foreach (array_count_values($formIPs) as $val => $c) {
                if ($c > 1) {
                    $formDuplicates[] = $val;
                }
            }

            if (0 != count($formDuplicates)) {
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

    public function uzivatelFormSucceded($form, $values) {
        $log = array();
        $idUzivatele = $values->id;
        $ips = $values->ip;
        $ipsubnet = $values->ipsubnet;
        $iprange = $values->iprange;
        // \Tracy\Debugger::barDump($ips);exit;
        unset($values['ip'], $values['ipsubnet'], $values['iprange']);

        $genaddresses = [];
        $newUserIPIDs = [];
        $smtpIPIDs = [];
        $dnatIPIDs = [];

        // generovani ip pro vlozeni ze subnetu
        $genaddresses = $this->ipAdresa->getListOfIPFromSubnet($ipsubnet);
        // generovani ip pro vlozeni z rozsahu
        $genaddresses = array_merge($genaddresses, $this->ipAdresa->getListOfIPFromRange($iprange));

        if (empty($values->cislo_clenske_karty)) {
            $values->cislo_clenske_karty = null;
        }
        if (empty($values->firma_nazev)) {
            $values->firma_nazev = null;
        }
        if (empty($values->firma_ico)) {
            $values->firma_ico = null;
        }
        if (empty($values->email2)) {
            $values->email2 = null;
        }
        if (empty($values->poznamka)) {
            $values->poznamka = null;
        }
        if (($values->TypClenstvi_id ?? -1) < 0) {
            $values->TypClenstvi_id = null;
        }

        // pri kazde editaci uzivatele nastavime znovu geocoding na pending, aby se znovu dohledaly geofraf. souradnice
        $values->location_status = 'pending';

        // Zpracujeme nejdriv uzivatele
        if (empty($values->id)) {
            $values->regform_downloaded_password_sent = 0;
            if (($values->spolek == 1 && $values->TypClenstvi_id > 0) || ($values->druzstvo == 1 && $values->smazano == 0)) {
                $values->money_aktivni = 1;
            } else {
                $values->money_aktivni = 0;
            }
            $values->zalozen = new \DateTime();
            $values->heslo = $this->uzivatel->generateStrongPassword();
            $values->heslo_hash = $this->uzivatel->generateWeakHash($values->heslo);
            $values->heslo_strong_hash = $this->uzivatel->generateStrongHash($values->heslo);
            $values->id = $this->uzivatel->getNewID();

            $uzivatel = $this->uzivatel->insert($values);

            $idUzivatele = $uzivatel->id;
            $this->log->logujInsert($values, 'Uzivatel', $log);

            if ($values->spolek == 1) {
                if ($values->TypClenstvi_id > 0) {
                    $this->sendSpolekRegistrationEmail($idUzivatele);
                } else {
                    $this->sendSpolekNotificationEmail($idUzivatele);
                }
            }

            if ($values->druzstvo == 1) {
                $this->sendDruzstvoRegistrationEmail($idUzivatele);
            }
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);

            if ($olduzivatel->email != $values->email || $olduzivatel->email2 != $values->email2) {
                $values->email_invalid = 0;
            }

            // pokud neni aktivni ani ve spolku ani v druzstvu
            if (!(($values->spolek == 1 && $values->TypClenstvi_id > 1) || ($values->druzstvo == 1 && $values->smazano == 0))) {
                $this->aplikaceToken->deleteTokensForUID($idUzivatele);
            }

            // TODO: spolek / druzstvo
            if (0 == $olduzivatel->TypClenstvi_id && 1 == $values->TypClenstvi_id) {
                $this->povoleneSMTP->deleteIPs($smtpIPIDs);
                $existinguserIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
                $this->ipAdresa->deleteIPAdresy($existinguserIPIDs);
                $this->uzivatel->delete(['id' => [$idUzivatele]]);
                $this->redirect('UzivatelList:list', ['id' => $olduzivatel->Ap_id]);

                return true;
            }

            // TODO: spolek / druzstvo
            if (0 == $olduzivatel->TypClenstvi_id && 0 != $values->TypClenstvi_id) {
                $values->zalozen = new \DateTime();
                $values->money_aktivni = 1;
            }

            $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($olduzivatel, $values, 'Uzivatel', $log);

            // TODO: spolek / druzstvo
            if (0 == $olduzivatel->TypClenstvi_id && 0 != $values->TypClenstvi_id && $values->spolek == 1) {
                $this->sendSpolekRegistrationEmail($idUzivatele);
            }
        }

        // Potom zpracujeme IPcka
        foreach ($ips as $ip) {
            $ip->Uzivatel_id = $idUzivatele;
            $idIp = $ip->id;

            if (empty($ip->ip_adresa)) {
                $ip->ip_adresa = null;
            }
            if (empty($ip->hostname)) {
                $ip->hostname = null;
            }
            if (empty($ip->mac_adresa)) {
                $ip->mac_adresa = null;
            }
            if (empty($ip->popis)) {
                $ip->popis = null;
            }
            if (empty($ip->login)) {
                $ip->login = null;
            }
            if (empty($ip->heslo)) {
                $ip->heslo = null;
            }

            if ($ip->heslo && strlen($ip->heslo) > 0) {
                $ip->heslo = $this->cryptosvc->encrypt($ip->heslo);
                $ip->heslo_sifrovane = 1;
            } else {
                $ip->heslo_sifrovane = 0;
            }

            if (empty($ip->id)) {
                $idIp = $this->ipAdresa->insert($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa['.$idIp.']', $log);
            } else {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa['.$idIp.']', $log);
            }
            $newUserIPIDs[] = intval($idIp);
        }
        // rozsahy ip adres
        foreach ($genaddresses as $gi) {
            $duplIp = $this->ipAdresa->getDuplicateIP($gi, 0);
            if (!$duplIp) {
                $rngip = [
                    'ip_adresa' => $gi,
                    'internet' => true,
                    'smokeping' => false,
                    'mac_filter' => false,
                    'dhcp' => false,
                    'Uzivatel_id' => $idUzivatele,
                ];
                $idrngip = $this->ipAdresa->insert($rngip)->id;
                $this->log->logujInsert($rngip, 'IPAdresa['.$idrngip.']', $log);
                $newUserIPIDs[] = intval($idrngip);
            }
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $userIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
        $toDelete = array_values(array_diff($userIPIDs, $newUserIPIDs));
        if (!empty($toDelete)) {
            foreach ($toDelete as $idIp) {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->log->logujDelete($oldip, 'IPAdresa['.$idIp.']', $log);
                $isSMTP = $this->povoleneSMTP->getIP($oldip->id);
                if ($isSMTP) {
                    $smtpIPIDs[] = intval($isSMTP->id);
                }
                $isDNAT = $this->dnat->getIP($oldip->id);
                if ($isDNAT) {
                    $dnatIPIDs[] = intval($isDNAT->ip);
                }
            }
        }
        $this->povoleneSMTP->deleteIPs($smtpIPIDs);

        $this->dnat->deleteIPs($dnatIPIDs);

        $this->ipAdresa->deleteIPAdresy($toDelete);

        $this->log->loguj('Uzivatel', $idUzivatele, $log);

        $this->redirect('Uzivatel:show', ['id' => $idUzivatele]);

        return true;
    }

    public function actionIds($id) {
        if ($id) {
            if ($uzivatel = $this->uzivatel->getUzivatel($id)) {
                $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id')->order('INET_ATON(ip_adresa)');
                $ips = array_values($ipAdresy->fetchPairs('id', 'ip_adresa'));

                try {
                    $this->template->idsEvents = $this->idsConnector->getEventsForIps($ips);
                } catch (\Exception $ex) {
                    if (Debugger::$productionMode) {
                        throw $ex;
                    }
                    $this->template->idsEvents = []; // silently ignore in non-prod environment

                }
            }
        }
    }
}
