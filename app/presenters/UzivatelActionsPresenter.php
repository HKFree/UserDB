<?php

namespace App\Presenters;

use Nette,
    App\Model,
    App\Services,
    Tracy\Debugger;

/**
 * Uzivatel actions presenter.
 */
class UzivatelActionsPresenter extends UzivatelPresenter
{
    private $accountActivation;
    private $uzivatel;
    private $pdfGenerator;
    private $mailService;

    function __construct(Services\MailService $mailsvc, Services\PdfGenerator $pdf, Model\AccountActivation $accActivation, Model\Uzivatel $uzivatel) {
        $this->pdfGenerator = $pdf;
        $this->accountActivation = $accActivation;
        $this->uzivatel = $uzivatel;
        $this->mailService = $mailsvc;
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
        if($this->getParameter('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id')))
            {
                $pdftemplate = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
                $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);
                $this->sendResponse($pdf);
            }
        }
    }
    public function actionSendRegActivation() {
        if($this->getParameter('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id')))
    	    {
                $hash = base64_encode($uzivatel->id.'-'.md5($this->context->parameters["salt"].$uzivatel->zalozen));
                $link = "https://moje.hkfree.org/uzivatel/confirm/".$hash;
                //\Tracy\Debugger::barDump($link);exit();
                $so = $this->uzivatel->getUzivatel($this->getIdentity()->getUid());

                $this->mailService->sendConfirmationRequest($uzivatel, $so, $link);
                $this->mailService->sendConfirmationRequestCopy($uzivatel, $so);

                $this->flashMessage('E-mail s žádostí o potvrzení registrace byl odeslán.');

                $this->redirect('Uzivatel:show', array('id'=>$uzivatel->id));
            }
        }
    }

    public function actionExportAndSendRegForm() {
        if($this->getParameter('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id')))
    	    {
                $pdftemplate = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
                $pdf = $this->pdfGenerator->generatePdf($uzivatel, $pdftemplate);

                $this->mailService->mailPdf($pdf, $uzivatel, $this->getHttpRequest(), $this->getHttpResponse(), $this->getIdentity()->getUid());

                $this->flashMessage('E-mail byl odeslán.');

                $this->redirect('Uzivatel:show', array('id'=>$uzivatel->id));
            }
        }
    }
}
