<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Mail\Message;
use App\Components;

/**
 * Uzivatel presenter.
 */
class UzivatelRightsCcPresenter extends UzivatelPresenter
{
    private $uzivatel;
    private $ap;
    private $spravceOblasti;
    private $typSpravceOblasti;
    private $cestneClenstviUzivatele;
    private $typCestnehoClenstvi;
    private $log;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;
    public function __construct(Model\AP $ap, Model\Uzivatel $uzivatel, Model\TypCestnehoClenstvi $typCestnehoClenstvi, Model\TypSpravceOblasti $typSpravce, Model\CestneClenstviUzivatele $cc, Model\SpravceOblasti $prava, Model\Log $log)
    {
        $this->uzivatel = $uzivatel;
        $this->spravceOblasti = $prava;
        $this->typSpravceOblasti = $typSpravce;
        $this->cestneClenstviUzivatele = $cc;
        $this->typCestnehoClenstvi = $typCestnehoClenstvi;
        $this->log = $log;
        $this->ap = $ap;
    }

    public function renderEditrights()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
        $this->template->u = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentUzivatelRightsForm()
    {
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
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
            foreach ($user->related("SpravceOblasti.Uzivatel_id") as $rights_id => $rights_data) {
                $form["rights"][$rights_id]->setValues($rights_data);
            }
            if ($user) {
                $form->setValues($user);
            }
        }

        return $form;
    }

    public function validateRightsForm($form)
    {
        $data = $form->getHttpData();

        // Validujeme jenom při uložení formuláře
        if (!isset($data["save"])) {
            return (0);
        }

        $values = $form->getUntrustedValues();

        foreach ($values->rights as $pravo) {
            if (!empty($pravo->id) && !$pravo->override) {
                $starePravo = null;
                $starePravo = $this->spravceOblasti->getPravo($pravo->id);
                if (($starePravo->od != null && $starePravo->od->format('Y-m-d') != $pravo->od) || ($starePravo->do != null && $starePravo->do->format('Y-m-d') != $pravo->do)
                || $starePravo->Oblast_id != $pravo->Oblast_id || $starePravo->TypSpravceOblasti_id != $pravo->TypSpravceOblasti_id) {
                    $form->addError('NERECYKLUJTE. Práva slouží jako historický údaj např. pro hlasování. Pokud jde pouze o prodloužení, nebo opravu chyby použijte zaškrtávátko !!! OPRAVA !!!.');
                }
            }
        }
    }

    public function uzivatelRightsFormSucceded($form, $values)
    {
        $log = array();
        $idUzivatele = $values->id;
        $prava = $values->rights;

        $typRole = $this->typSpravceOblasti->getTypySpravcuOblasti()->fetchPairs('id', 'text');

        // Zpracujeme prava
        foreach ($prava as $pravo) {
            unset($pravo['override']);

            $pravo->Uzivatel_id = $idUzivatele;
            $pravoId = $pravo->id;

            //osetreni aby prazdne pole od davalo null a ne 00-00-0000
            if (empty($pravo->od)) {
                $pravo->od = null;
            }
            if (empty($pravo->do)) {
                $pravo->do = null;
            }

            $popisek = $this->spravceOblasti->getTypPravaPopisek($typRole[$pravo->TypSpravceOblasti_id], $pravo->Oblast_id);

            if (empty($pravo->id)) {
                $pravoId = $this->spravceOblasti->insert($pravo)->id;
                $novePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->log->logujInsert($novePravo, 'Pravo['.$popisek.']', $log);
            } else {
                $starePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->spravceOblasti->update($pravoId, $pravo);
                $novePravo = $this->spravceOblasti->getPravo($pravoId);
                $this->log->logujUpdate($starePravo, $novePravo, 'Pravo['.$popisek.']', $log);
            }
        }

        $this->log->loguj('Uzivatel', $idUzivatele, $log);

        $this->redirect('Uzivatel:show', array('id' => $idUzivatele));
        return true;
    }

    public function renderEditcc()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParameter('id'))->Ap_id, $this->getUser());
        $this->template->canApprove = $this->getUser()->isInRole('VV');
        $this->template->u = $this->uzivatel->getUzivatel($this->getParameter('id'));
    }

    protected function createComponentUzivatelCCForm()
    {
        $form = new Form($this, "uzivatelCCForm");
        $form->addHidden('id');

        $typCC = $this->typCestnehoClenstvi->getTypCestnehoClenstvi()->fetchPairs('id', 'text');

        $data = $this->cestneClenstviUzivatele;
        $rights = $form->addDynamic('rights', function (Container $right) use ($data, $typCC) {
            $right->addHidden('zadost_podal')->setAttribute('class', 'id ip');
            $right->addHidden('zadost_podana')->setAttribute('class', 'id ip');
            $right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
            $right->addHidden('id')->setAttribute('class', 'id ip');

            $right->addSelect('TypCestnehoClenstvi_id', 'Typ čestného členství', $typCC)->addRule(Form::FILLED, 'Vyberte typ čestného členství');

            $right->addText('plati_od', 'Platnost od:')
                 ->setAttribute('class', 'datepicker ip')
                 ->setAttribute('data-date-format', 'YYYY/MM/DD')
                 ->addRule(Form::FILLED, 'Vyberte datum')
                 ->addCondition(Form::FILLED)
                 ->addRule(Form::PATTERN, 'prosím zadejte datum ve formátu RRRR-MM-DD', '^\d{4}-\d{2}-\d{1,2}$');

            $right->addText('plati_do', 'Platnost do:')
                 ->setAttribute('class', 'datepicker ip')
                 ->setAttribute('data-date-format', 'YYYY/MM/DD')
                 ->addCondition(Form::FILLED)
                 ->addRule(Form::PATTERN, 'prosím zadejte datum ve formátu RRRR-MM-DD', '^\d{4}-\d{2}-\d{1,2}$');

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
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $user = $this->uzivatel->getUzivatel($this->getParameter('id'));
            foreach ($user->related("CestneClenstviUzivatele.Uzivatel_id") as $rights_id => $rights_data) {
                $form["rights"][$rights_id]->setValues($rights_data);
            }
            if ($user) {
                $form->setValues($user);
            }
        }

        return $form;
    }

    public function uzivatelCCFormSucceded($form, $values)
    {
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

            //osetreni aby prazdne pole od davalo null a ne 00-00-0000
            if (empty($pravo->plati_od)) {
                $pravo->plati_od = null;
            }
            if (empty($pravo->plati_do)) {
                $pravo->plati_do = null;
            }
            if (empty($pravo->schvaleno)) {
                $pravo->schvaleno = 0;
            }

            if (empty($pravo->id)) {
                $pravoId = $this->cestneClenstviUzivatele->insert($pravo)->id;

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
        return true;
    }
}
