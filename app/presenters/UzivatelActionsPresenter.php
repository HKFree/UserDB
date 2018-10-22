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

    function __construct(Model\AccountActivation $accActivation) {
        $this->accountActivation = $accActivation;
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

}
