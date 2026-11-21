<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Sprava oblasti presenter.
 */
class SpravaOblastiPresenter extends SpravaPresenter
{
    private Model\AP $ap;
    /** @var Model\Oblast */
    public $oblast;

    public function __construct(Model\Oblast $ob, Model\AP $ap, Model\Uzivatel $uzivatel) {
        parent::__construct($uzivatel, $ap);
        $this->ap = $ap;
        $this->oblast = $ob;
    }

    public function renderNovaoblast(): void {
        $this->template->canViewOrEdit = $this->jeVVneboTECH();
    }

    protected function createComponentNovaoblastForm(): Form {
        $form = new Form($this, "novaoblastForm");
        $form->addHidden('id');

        $form->addText('jmeno', 'Název oblasti', 50)->setRequired('Zadejte název oblasti');

        $form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'novaoblastFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $existujiciOblast = $this->oblast->getOblast($this->getParameter('id'));
            if ($existujiciOblast !== null) {
                $form->setValues($existujiciOblast);
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function novaoblastFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->jeVVneboTECH());

        $idOblasti = $values->id;

        if (empty($values->id)) {
            $values->datum_zalozeni = new Nette\Utils\DateTime();
            $this->oblast->insert($values);
            $this->flashMessage('Oblast byla vytvořena.');
        } else {
            $this->oblast->update($idOblasti, $values);
        }

        $this->redirect('Sprava:nastroje');
    }

    public function renderNoveap(): void {
        $this->template->canViewOrEdit = $this->jeVVneboTECH();
    }

    protected function createComponentNoveapForm(): Form {
        $form = new Form($this, "noveapForm");
        $form->addHidden('id');

        $aps = $this->oblast->formatujOblasti($this->oblast->getSeznamOblasti());

        $form->addSelect('Oblast_id', 'Oblast', $aps);

        $form->addText('jmeno', 'Název AP', 50)->setRequired('Zadejte název AP');
        $form->addTextArea('poznamka', 'Poznámka', 72, 10);

        $form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'noveapFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if ($this->getParameter('id') && !$submitujeSe) {
            $existujiciAp = $this->ap->getAP($this->getParameter('id'));
            if ($existujiciAp !== null) {
                $form->setValues($existujiciAp);
            }
        }

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function noveapFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->jeVVneboTECH());

        $idAp = $values->id;

        if (empty($values->id)) {
            $this->ap->insert($values);
            $this->flashMessage('AP bylo vytvořeno.');
        } else {
            $this->ap->update($idAp, $values);
        }

        $this->redirect('Sprava:nastroje');
    }
}
