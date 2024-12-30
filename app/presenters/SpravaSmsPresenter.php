<?php

namespace App\Presenters;

use App\Model;
use Nette\Application\UI\Form;
use App\Services\SmsSenderException;

/**
 * Sprava presenter.
 */
class SpravaSmsPresenter extends SpravaPresenter
{
    private $spravceOblasti;

    private $komunikace;

    public function __construct(Model\SpravceOblasti $sob, Model\Komunikace $k) {
        $this->spravceOblasti = $sob;
        $this->komunikace = $k;
    }

    public function renderSms() {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentSmsForm() {
        $form = new Form($this, "smsForm");
        $form->addHidden('id');

        $form->addSelect('komu', 'Příjemce', array(0 => 'SO',1 => 'ZSO'))->setDefaultValue(0);
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'smsFormSucceded');

        return $form;
    }

    public function smsFormSucceded($form, $values) {
        if ($values->komu == 0) {
            $sos = $this->spravceOblasti->getSO();
        } else {
            $sos = $this->spravceOblasti->getZSO();
        }

        $validni = [];
        foreach ($sos as $so) {
            $tl = $so->Uzivatel->telefon;
            if (!empty($tl) && $tl != 'missing') {
                $validni[] = $so->Uzivatel->id;
            }
        }

        $this->sendSMSAndValidate($validni, $values->message);

        $this->redirect('Sprava:nastroje', array('id' => null));
        return true;
    }

    private function sendSMSAndValidate($uzivateleID, $message) {
        try {
            $this->komunikace->posliSMS($uzivateleID, $message);
            $this->flashMessage('SMS byla odeslána na ' . count($uzivateleID) . ' čísel.');
        } catch (SmsSenderException $e) {
            $this->flashMessage('SMS nebyla odeslána. ' . $e, "danger");
        }
    }
}
