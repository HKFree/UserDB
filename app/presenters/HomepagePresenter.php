<?php

namespace App\Presenters;

use App\Services\Novinky;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
    private const POCET_NOVINEK = 5;

    public function __construct(private readonly Novinky $novinky) {
        parent::__construct();
    }

    public function renderDefault(): void {
        $this->template->posledniNovinky = $this->novinky->posledni(self::POCET_NOVINEK);
    }
}
