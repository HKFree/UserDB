<?php

namespace App\Presenters;

use App\Model;
use Nette\Application\Attributes\Parameter;

class SpravaSmluvPresenter extends BasePresenter
{
    // $id se doplni z request URL
    // viz https://doc.nette.org/en/application/presenters#toc-request-parameters
    #[Parameter]
    public string $id;

    protected $smlouva;

    public function __construct(Model\Smlouva $smlouva) {
        $this->smlouva = $smlouva;
    }

    public function renderShow() {
        $this->template->id = $this->id;
        $this->template->smlouvy = $this->smlouva->findAll();
    }
}
