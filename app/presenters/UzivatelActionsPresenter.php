<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Tracy\Debugger;

/**
 * Uzivatel actions presenter.
 */
class UzivatelActionsPresenter extends UzivatelPresenter
{
    private $accountActivation;
    private $uzivatel;
    private $pdfGenerator;

    function __construct(Model\PdfGenerator $pdf, Model\AccountActivation $accActivation, Model\Uzivatel $uzivatel) {
        $this->pdfGenerator = $pdf;
        $this->accountActivation = $accActivation;
        $this->uzivatel = $uzivatel;
    }

    public function actionMoneyActivate() {
        $id = $this->getParameter('id');
        if($id)
        {
            if($this->accountActivation->activateAccount($this->getUser(), $id))
            {
                $this->flashMessage('Účet byl aktivován.');
            }

            $this->redirect('Uzivatel:show', array('id'=>$id));
        }
    }

    public function actionMoneyReactivate() {
        $id = $this->getParameter('id');
        if($id)
        {
            $result = $this->accountActivation->reactivateAccount($this->getUser(), $id);
            if($result != '')
            {
                $this->flashMessage($result);
            }

            $this->redirect('Uzivatel:show', array('id'=>$id));
        }
    }

    public function actionMoneyDeactivate() {
        $id = $this->getParameter('id');
        if($id)
        {
            if($this->accountActivation->deactivateAccount($this->getUser(), $id))
            {
                $this->flashMessage('Účet byl deaktivován.');
            }

            $this->redirect('Uzivatel:show', array('id'=>$id));
        }
    }

    public function actionExportPdf() {
        if($this->getParam('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
            {
                $pdftemplate = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
                $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);
                $this->sendResponse($pdf);
            }
        }
    }
}
