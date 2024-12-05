<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Utils\DateTime,
    Tracy\Debugger;

/**
 * Sprava oblasti presenter.
 */
class SpravaOblastiPresenter extends SpravaPresenter
{
    private $ap;
    public $oblast;

    function __construct(Model\Oblast $ob, Model\AP $ap) {
        $this->ap = $ap;
        $this->oblast = $ob;
    }

    public function renderNovaoblast()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentNovaoblastForm() {
    	$form = new Form($this, "novaoblastForm");
    	$form->addHidden('id');

        $form->addText('jmeno', 'Název oblasti', 50)->setRequired('Zadejte název oblasti');

    	$form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'novaoblastFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {
            $existujiciOblast = $this->oblast->getOblast($this->getParameter('id'));
            if($existujiciOblast) {
                $form->setValues($existujiciOblast);
    	    }
    	}

    	return $form;
    }

    public function novaoblastFormSucceded($form, $values) {

        $idOblasti = $values->id;

        if(empty($values->id)) {
            $values->datum_zalozeni = new Nette\Utils\DateTime;
            $this->oblast->insert($values);
            $this->flashMessage('Oblast byla vytvořena.');
        } else {
    	    $this->oblast->update($idOblasti, $values);
        }

    	$this->redirect('Sprava:nastroje');
    	return true;
    }

    public function renderNoveap()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentNoveapForm() {
    	$form = new Form($this, "noveapForm");
    	$form->addHidden('id');

        $aps = $this->oblast->formatujOblasti($this->oblast->getSeznamOblasti());

        $form->addSelect('Oblast_id', 'Oblast', $aps);

        $form->addText('jmeno', 'Název AP', 50)->setRequired('Zadejte název AP');
        $form->addTextArea('poznamka', 'Poznámka', 72, 10);

    	$form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'noveapFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {
            $existujiciAp = $this->ap->getAP($this->getParameter('id'));
            if($existujiciAp) {
                $form->setValues($existujiciAp);
    	    }
    	}

    	return $form;
    }

    public function noveapFormSucceded($form, $values) {

        $idAp = $values->id;

        if(empty($values->id)) {
            $this->ap->insert($values);
            $this->flashMessage('AP bylo vytvořeno.');
        } else {
    	    $this->ap->update($idAp, $values);
        }

    	$this->redirect('Sprava:nastroje');
    	return true;
    }

}
