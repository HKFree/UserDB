<?php

namespace App\Presenters;

use App\Services\SmsSender;
use App\Model;
use Nette\Application\UI\Form;

/**
 * Sprava presenter.
 */
class SpravaSmsPresenter extends SpravaPresenter
{
    private $spravceOblasti;

    private $smsSender;

    public function __construct(Model\SpravceOblasti $sob, SmsSender $smsSender)
    {
        $this->spravceOblasti = $sob;
        $this->smsSender = $smsSender;
    }

    public function renderSms()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentSmsForm()
    {
        $form = new Form($this, "smsForm");
        $form->addHidden('id');

        $form->addSelect('komu', 'Příjemce', array(0 => 'SO',1 => 'ZSO'))->setDefaultValue(0);
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'smsFormSucceded');

        return $form;
    }

    public function smsFormSucceded($form, $values)
    {
        if ($values->komu == 0) {
            $sos = $this->spravceOblasti->getSO();
        } else {
            $sos = $this->spravceOblasti->getZSO();
        }

        $validni = [];
        foreach ($sos as $so) {
            $tl = $so->Uzivatel->telefon;
            if (!empty($tl) && $tl != 'missing') {
                $validni[] = $tl;
            }
        }

        $output = $this->smsSender->sendSms($this->getIdentity(), $validni, $values->message);

        $this->flashMessage('SMS byly odeslány. Output: ' . $output);

        $this->redirect('Sprava:nastroje', array('id' => null));
        return true;
    }
}
