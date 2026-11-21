<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Services;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Mail\Message;
use Nette\Utils\ArrayHash;
use App\Components;

/**
 * Uzivatel presenter.
 */
class UzivatelRightsCcPresenter extends UzivatelPresenter
{
    private Model\Uzivatel $uzivatel;
    private Model\AP $ap;
    private Model\SpravceOblasti $spravceOblasti;
    private Model\TypSpravceOblasti $typSpravceOblasti;
    private Model\CestneClenstviUzivatele $cestneClenstviUzivatele;
    private Model\TypCestnehoClenstvi $typCestnehoClenstvi;
    private Model\Log $log;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;
    public function __construct(
        Model\AP $ap,
        Model\Uzivatel $uzivatel,
        Model\TypCestnehoClenstvi $typCestnehoClenstvi,
        Model\TypSpravceOblasti $typSpravce,
        Model\CestneClenstviUzivatele $cc,
        Model\SpravceOblasti $prava,
        Model\Log $log,
        Services\MailService $mailsvc,
        Services\CryptoSluzba $cryptosvc,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\Parameters $parameters,
        Model\SloucenyUzivatel $slUzivatel,
        Model\Smlouva $smlouva,
        Services\SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\TypPravniFormyUzivatele $typPravniFormyUzivatele,
        Model\TypClenstvi $typClenstvi,
        Model\ZpusobPripojeni $zpusobPripojeni,
        Model\TechnologiePripojeni $technologiePripojeni,
        Model\IPAdresa $ipAdresa,
        Model\TypZarizeni $typZarizeni,
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
        $this->spravceOblasti = $prava;
        $this->typSpravceOblasti = $typSpravce;
        $this->cestneClenstviUzivatele = $cc;
        $this->typCestnehoClenstvi = $typCestnehoClenstvi;
        $this->log = $log;
        $this->ap = $ap;
    }

    /** Oprávnění správců přiděluje jen VV */
    private function smiMenitPrava(): bool {
        return $this->getUser()->isInRole('VV');
    }

    public function renderEditrights(): void {
        $this->template->canViewOrEdit = $this->smiMenitPrava();
        $this->template->u = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentUzivatelRightsForm(): Form {
        $typRole = $this->typSpravceOblasti->getTypySpravcuOblasti()->fetchPairs('id', 'text');
        $obl = $this->oblast->getSeznamOblasti()->fetchPairs('id', 'jmeno');

        // Tohle je nutne abychom mohli zjistit isSubmited
        $form = new Form($this, "uzivatelRightsForm");
        $form->addHidden('id');

        $data = $this->spravceOblasti;
        $rights = $form->addDynamic('rights', function (Container $right) use ($data, $typRole, $obl) {
            $data->getRightsForm($right, $typRole, $obl);
        }, 0, false);

        $rights->addSubmit('add', '+ Přidat další oprávnění')
               ->setAttribute('class', 'btn btn-success btn-xs btn-white')
               ->setValidationScope(null)
               ->addCreateOnClick(true);

        $form->addSubmit('save', 'Uložit')
             ->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'uzivatelRightsFormSucceded');
        $form->onValidate[] = array($this, 'validateRightsForm');

        // pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
            foreach ($user->related("SpravceOblasti.Uzivatel_id") as $rights_id => $rights_data) {
                $rights->createOne($rights_id)->setValues($rights_data);
            }
            if ($user !== null) {
                $form->setValues($user);
            }
        }

        return $form;
    }

    public function validateRightsForm(Form $form): void {
        $data = $form->getHttpData();
        if (!is_array($data)) {
            throw new Nette\UnexpectedValueException('Data celého formuláře musí být pole.');
        }

        // Validujeme jenom při uložení formuláře
        if (!isset($data["save"])) {
            return;
        }

        $values = $form->getUntrustedValues();

        foreach ($values->rights as $pravo) {
            if (!in_array($pravo->id, [null, '', 0, '0'], true) && !$pravo->override) {
                $starePravo = $this->spravceOblasti->getPravo($pravo->id);
                if (($starePravo->od !== null && $starePravo->od->format('Y-m-d') !== $pravo->od?->format('Y-m-d'))
                    || ($starePravo->do !== null && $starePravo->do->format('Y-m-d') !== $pravo->do?->format('Y-m-d'))
                    || $starePravo->Oblast_id !== $pravo->Oblast_id || $starePravo->TypSpravceOblasti_id !== $pravo->TypSpravceOblasti_id) {
                    $form->addError('NERECYKLUJTE. Upravili jste starší právo a nezaškrtli jste tlačítko OPRAVA. Mysleli jste to vážně?');
                }
            }
        }
    }

    /** @param ArrayHash<mixed>&object{rights: ArrayHash<ArrayHash<mixed>>} $values */
    public function uzivatelRightsFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiMenitPrava());

        $log = array();
        $idUzivatele = $values->id;
        $prava = $values->rights;

        $typRole = $this->typSpravceOblasti->getTypySpravcuOblasti()->fetchPairs('id', 'text');

        // Zpracujeme prava
        foreach ($prava as $pravo) {
            unset($pravo['override']);

            $pravo->Uzivatel_id = $idUzivatele;
            $pravoId = $pravo->id;

            if (in_array($pravo->id, [null, '', 0, '0'], true)) {
                $pravoId = $this->spravceOblasti->insertRow($pravo)->id;
                $novePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->log->logujInsert($novePravo, 'SpravceOblasti['.$pravoId.']', $log);
            } else {
                $starePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->spravceOblasti->update($pravoId, $pravo);
                $novePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->log->logujUpdate($starePravo, $novePravo, 'SpravceOblasti['.$pravoId.']', $log);
            }
        }

        $this->log->loguj('Uzivatel', $idUzivatele, $log);

        $this->redirect('Uzivatel:show', array('id' => $idUzivatele));
    }

    /**
     * Čestné členství člena upravuje EXTSUPPORT nebo správce jeho AP
     * @param int|string|null $idUzivatele
     */
    private function smiUpravitCc($idUzivatele): bool {
        $uzivatel = $this->uzivatel->getUzivatel($idUzivatele);
        return $uzivatel !== null && ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser()));
    }

    /** Žádost o ČČ schvaluje nebo zamítá jen VV */
    private function smiSchvalitCc(): bool {
        return $this->getUser()->isInRole('VV');
    }

    public function renderEditcc(): void {
        $this->template->canViewOrEdit = $this->smiUpravitCc($this->getParameter('id'));
        $this->template->canApprove = $this->smiSchvalitCc();
        $this->template->u = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentUzivatelCCForm(): Form {
        $form = new Form($this, "uzivatelCCForm");
        $form->addHidden('id');

        $typCC = $this->typCestnehoClenstvi->getTypCestnehoClenstvi()->fetchPairs('id', 'text');

        $rights = $form->addDynamic('rights', function (Container $right) use ($typCC) {
            $right->addHidden('zadost_podal')->setAttribute('class', 'id ip');
            $right->addHidden('zadost_podana')->setAttribute('class', 'id ip');
            $right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
            $right->addHidden('id')->setAttribute('class', 'id ip');

            $right->addSelect('TypCestnehoClenstvi_id', 'Typ čestného členství', $typCC)->addRule(Form::FILLED, 'Vyberte typ čestného členství');

            $right->addDate('plati_od', 'Platnost od:')
            ->addRule(Form::FILLED, 'Vyberte datum');

            $right->addDate('plati_do', 'Platnost do:');

            $right->addTextArea('poznamka', 'Poznámka:', 72, 5)
            ->setAttribute('class', 'note ip');

            $schvalenoStates = array(
               0 => 'Nerozhodnuto',
               1 => 'Schváleno',
               2 => 'Zamítnuto');
            $right->addRadioList('schvaleno', 'Stav schválení: ', $schvalenoStates)
                  ->getSeparatorPrototype()->setName("span")->style('margin-right', '7px');

            $right->setDefaults(array(
                   'TypCestnehoClenstvi_id' => 0,
               ));
        }, 0, false);

        $rights->addSubmit('add', '+ Přidat další období ČČ')
               ->setAttribute('class', 'btn btn-success btn-xs btn-white')
               ->setValidationScope(null)
               ->addCreateOnClick(true);

        $form->addSubmit('save', 'Uložit')
             ->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'uzivatelCCFormSucceded');

        // pokud editujeme, nacteme existujici opravneni
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
            foreach ($user->related("CestneClenstviUzivatele.Uzivatel_id") as $rights_id => $rights_data) {
                $rights->createOne($rights_id)->setValues($rights_data);
            }
            if ($user !== null) {
                $form->setValues($user);
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed>&object{rights: ArrayHash<ArrayHash<mixed>>} $values */
    public function uzivatelCCFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiUpravitCc($values->id));

        $log = array();
        $idUzivatele = $values->id;
        $prava = $values->rights;

        // Zpracujeme prava
        $newUserIPIDs = array();
        foreach ($prava as $pravo) {
            $pravo->Uzivatel_id = $idUzivatele;
            $pravo->zadost_podal = $this->getIdentity()->getUid();
            $pravo->zadost_podana = new Nette\Utils\DateTime();
            $pravoId = $pravo->id;

            if (in_array($pravo->schvaleno ?? null, [null, 0, '0'], true) || !$this->smiSchvalitCc()) {
                // ostatním šablona pole stavu nezobrazí, takže ho formulář ani neposílá
                $pravo->schvaleno = 0;
            }

            if (in_array($pravo->id, [null, '', 0, '0'], true)) {
                $pravoId = $this->cestneClenstviUzivatele->insertRow($pravo)->id;

                $mail = new Message();
                $mail->setFrom('UserDB <userdb@hkfree.org>')
                    ->addTo('vv@hkfree.org')
                    ->setSubject('Nová žádost o ČČ')
                    ->setBody("Dobrý den,\nbyla vytvořena nová žádost o ČČ.\nID:$pravo->Uzivatel_id\nPoznámka: $pravo->poznamka\n\nhttps://userdb.hkfree.org/userdb/sprava/schvalovanicc");

                $this->mailer->send($mail);

                $this->flashMessage('E-mail VV byl odeslán. Vyčkejte, než VV žádost potvrdí.');
            } else {
                $this->cestneClenstviUzivatele->update($pravoId, $pravo);
            }
        }

        $this->redirect('Uzivatel:show', array('id' => $idUzivatele));
    }
}
