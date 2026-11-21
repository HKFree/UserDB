<?php

namespace App\Presenters;

use Nette;
use App\Model;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
    public function __construct() {
        parent::__construct();
    }

    public function renderDefault(): void {
        $this->template->anyVariable = 'any value';
    }
}
