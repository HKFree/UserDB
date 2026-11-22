<?php

namespace App\Presenters;

use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use App\Services\SmsSenderException;

/**
 * Sprava presenter.
 */
class SpravaSmsPresenter extends SpravaPresenter
{
    private Model\SpravceOblasti $spravceOblasti;

    private Model\Komunikace $komunikace;

    public function __construct(Model\SpravceOblasti $sob, Model\Komunikace $k, Model\Uzivatel $uzivatel, Model\AP $ap) {
        parent::__construct($uzivatel, $ap);
        $this->spravceOblasti = $sob;
        $this->komunikace = $k;
    }

    public function renderSms(): void {
        $this->template->canViewOrEdit = $this->jeVVneboTECH();
    }

    protected function createComponentSmsForm(): Form {
        $form = new Form($this, "smsForm");
        $form->addHidden('id');

        $form->addSelect('komu', 'Příjemce', array(0 => 'SO',1 => 'ZSO'))->setDefaultValue(0);
        $form->addTextArea('message', 'Text', 72, 10);

        $form->addSubmit('send', 'Odeslat')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'smsFormSucceded');

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function smsFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->jeVVneboTECH());

        if ($values->komu === 0) {
            $sos = $this->spravceOblasti->getSO();
        } else {
            $sos = $this->spravceOblasti->getZSO();
        }

        $validni = [];
        foreach ($sos as $so) {
            $tl = $so->Uzivatel->telefon;
            if (!in_array($tl, [null, '', '0'], true) && $tl !== 'missing') {
                $validni[] = $so->Uzivatel->id;
            }
        }

        $this->sendSMSAndValidate($validni, $values->message);

        $this->redirect('Sprava:nastroje', array('id' => null));
    }

    /** @param array<array-key, int|string> $uzivateleID */
    private function sendSMSAndValidate(array $uzivateleID, string $message): void {
        try {
            $pocetCisel = $this->komunikace->posliSMS($uzivateleID, $message);
            $this->flashMessage('SMS byla odeslána na ' . $pocetCisel . ' čísel.');
        } catch (SmsSenderException $e) {
            $this->flashMessage('SMS nebyla odeslána. ' . $e->getMessage(), "danger");
        }
    }
}
