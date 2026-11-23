<?php

declare(strict_types=1);

namespace App\Model;

use Grido\DataSources\ArraySource;

final class PoleZdrojGridu extends ArraySource
{
    /**
     * @param mixed $actual hodnota buňky; Grido ji popisuje jako text, řádek z databáze ale může nést i NULL
     * @param string $condition
     */
    public function compare(mixed $actual, mixed $condition, mixed $expected): bool {
        // Prázdná hodnota textovému filtru nevyhovuje, stejně jako NULL v SQL; Grido by na ní skončilo chybou.
        if ($actual === null && $condition === 'LIKE ?') {
            return false;
        }

        return parent::compare($actual, $condition, $expected);
    }
}
