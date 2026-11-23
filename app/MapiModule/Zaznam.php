<?php

declare(strict_types=1);

namespace App\MapiModule;

/**
 * Záznam o přístupu k datům přes /userdb/mapi/ a o nečekaných chybách.
 * Text dotazu ani jeho proměnné sem nepatří: mohou obsahovat hledaná jména.
 */
interface Zaznam
{
    /**
     * Jeden řádek na požadavek, i na odmítnutý.
     * @param int|null $uid null, když přihlášení neproběhlo
     * @param string|null $operace jméno operace GraphQL; null, když není známé nebo neodpovídá gramatice jména
     * @param string $vysledek OK, nebo kódy chyb oddělené čárkou
     */
    public function pristup(?int $uid, ?string $operace, string $vysledek): void;

    public function vyjimka(\Throwable $vyjimka): void;
}
