<?php

declare(strict_types=1);

namespace App\Model;

use Grido\Components\Filters\Condition;
use Grido\DataSources\NetteDatabase;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class DatabazovyZdrojGridu extends NetteDatabase
{
    /** @param Selection<ActiveRow>|null $selection */
    protected function makeWhere(Condition $condition, ?Selection $selection = null): void {
        // Textový filtr i jeho našeptávač. Filtr s vlastní obsluhou (setWhere) má seznam podmínek prázdný.
        if ($condition->getCondition() !== ['LIKE ?']) {
            parent::makeWhere($condition, $selection);
            return;
        }

        ($selection ?? $this->selection)->where(...$condition->__toArray(
            HledaniBezDiakritiky::PRED_SLOUPCEM,
            HledaniBezDiakritiky::ZA_SLOUPCEM,
        ));
    }
}
