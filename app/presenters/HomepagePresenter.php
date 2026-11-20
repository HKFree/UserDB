<?php

namespace App\Presenters;

use Nette;
use App\Model;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
    /** @var Model\Uzivatel */
    private $uzivatel;

    public function __construct(Model\Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
    }

    public function renderDefault() {
        $this->template->anyVariable = 'any value';
    }
}
