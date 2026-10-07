<?php

namespace App\Services\Push;

/** Chyba vstupu nebo oprávnění; kód odpovídá HTTP stavu (400, 403, 404, 429). */
class PushException extends \RuntimeException
{
}
