<?php

declare(strict_types=1);

namespace App\MapiModule\Graphql;

/**
 * Požadavek nejde vykonat: neplatný JSON, tvar požadavku nebo tvar operace. Klient dostane 400 a BAD_INPUT.
 */
final class ChybaPozadavku extends \RuntimeException
{
}
