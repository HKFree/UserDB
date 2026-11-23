<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

/**
 * Přihlášený nesmí vidět, na co se ptá. Klient dostane kód FORBIDDEN.
 */
final class PristupOdepren extends \RuntimeException
{
}
