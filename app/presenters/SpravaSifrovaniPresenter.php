<?php

namespace App\Presenters;

use App\Services\CryptoSluzba;
use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

/**
 * Sprava sifrovani presenter.
 */
class SpravaSifrovaniPresenter extends SpravaPresenter
{
    private Model\IPAdresa $ipAdresa;
    private CryptoSluzba $cryptosvc;

    public function __construct(CryptoSluzba $cryptosvc, Model\IPAdresa $ipAdresa) {
        $this->cryptosvc = $cryptosvc;
        $this->ipAdresa = $ipAdresa;
    }

    public function renderPresifrovani(): void {
        $this->template->canViewOrEdit = $this->jeVVneboTECH();
    }

    protected function createComponentPresifrovaniForm(): Form {
        // Tohle je nutne abychom mohli zjistit isSubmited
        $form = new Form($this, "presifrovaniForm");
        $form->addHidden('id');

        $form->addSubmit('send', 'Přešifrovat zatím nezašifrovaná hesla ip adres')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'presifrovaniFormSucceded');

        return $form;
    }

    /** @param ArrayHash<mixed> $values */
    public function presifrovaniFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->jeVVneboTECH());

        $nesifrovane = $this->ipAdresa->findBy(array('heslo_sifrovane' => 0));

        foreach ($nesifrovane as $ip) {
            if ($ip->heslo && strlen($ip->heslo) > 0) {
                $encrypted = $this->cryptosvc->encrypt($ip->heslo);
                $this->ipAdresa->update($ip->id, array('heslo' => $encrypted, 'heslo_sifrovane' => 1));
            }
        }

        $this->flashMessage('Hesla ip adres jsou přešifrovány.');

        $this->redirect('Sprava:nastroje', array('id' => null));
    }
}
