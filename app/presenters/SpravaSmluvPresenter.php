<?php

namespace App\Presenters;

use App\Model;
use Nette\Application\Attributes\Parameter;

class SpravaSmluvPresenter extends BasePresenter
{
    public string $smlouva_id;

    protected $smlouva;

    public function __construct(Model\Smlouva $smlouva) {
        $this->smlouva = $smlouva;
    }

    public function renderShow() {
        // TODO: ACCESS CONTROL NA SMLOUVY

        if (!$this->getParameter('id')) {
            $this->flashMessage('Nezname ID smlouvy.');
            $this->redirect('UzivatelList:listall');
        }
        $this->smlouva_id = $this->getParameter('id');

        $this->template->id = $this->smlouva_id;
        $this->template->smlouvy = $this->smlouva->findAll();
        $this->template->test = $this->smlouva->getByUzivatelId(1);
    }
}
