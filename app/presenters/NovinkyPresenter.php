<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Services\Novinky;

final class NovinkyPresenter extends BasePresenter
{
    public function __construct(private readonly Novinky $novinky) {
        parent::__construct();
    }

    /** Novinky jsou dostupné každé identitě přihlášené přes BasePresenter, bez dalšího omezení rolí. */
    public function renderDefault(): void {
        $this->template->vydani = $this->novinky->nacti();
    }
}
