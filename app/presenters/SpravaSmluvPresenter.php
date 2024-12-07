<?php

namespace App\Presenters;

use App\Model;
use DateTime;
use Nette\Application\Attributes\Parameter;

class SpravaSmluvPresenter extends BasePresenter
{
    public string $smlouva_id;

    protected $smlouva;
    protected $podpis;

    public function __construct(Model\Smlouva $smlouva, Model\Podpis $podpis) {
        $this->smlouva = $smlouva;
        $this->podpis = $podpis;
    }

    public function renderShow() {
        // TODO: ACCESS CONTROL NA SMLOUVY

        if (!$this->getParameter('id')) {
            $this->flashMessage('Nezname ID smlouvy.');
            $this->redirect('UzivatelList:listall');
        }

        $this->smlouva_id = $this->getParameter('id');

        $this->template->id = $this->smlouva_id;
        $this->template->smlouva = $this->smlouva->find($this->smlouva_id);

        $podpisy = $this->template->smlouva->related('Podpis', 'Smlouva_id');
        $this->template->podpisy = $podpisy;
    }

    public function parseDate(string $timestamp): DateTime {
        return \Nette\Utils\DateTime::from($timestamp);
    }
}
