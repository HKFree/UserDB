<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

use Nette\Database\Connection;

/**
 * Spojení, kterým čtecí vrstva čte. Dnes obaluje hlavní spojení aplikace; omezený databázový účet
 * se později zapojí jen výměnou spojení v konfiguraci (issue #264, rozhodnutí 17).
 */
final class CteciSpojeni
{
    public function __construct(
        private readonly Connection $spojeni,
    ) {
    }

    /**
     * @param literal-string $sql jen text napsaný v kódu; hodnoty patří do parametrů
     * @return list<array<string, mixed>> řádky jako pole sloupec => hodnota
     */
    public function vyber(string $sql, mixed ...$parametry): array {
        if (preg_match('~^SELECT\s[^;]*$~D', $sql) !== 1) {
            throw new \LogicException('Čtecí vrstva smí jen jednoduchý SELECT.');
        }
        $radky = [];
        foreach ($this->spojeni->query($sql, ...$parametry) as $radek) {
            $radky[] = iterator_to_array($radek);
        }
        return $radky;
    }
}
