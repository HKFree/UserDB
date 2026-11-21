<?php

namespace App;

class Settings
{
    /**
     * @param false|array{userID: int|string, userName: string, passwordHash?: string} $fakeUser
     * @param string|null $demoPersonaUrl stránka pro přepnutí demo osoby; jen lokální web a testovací instance, jinde null
     * @param string|null $demoPersonaName jméno právě vybrané demo osoby
     */
    public function __construct(
        public bool|array $fakeUser,
        public ?string $demoPersonaUrl = null,
        public ?string $demoPersonaName = null,
    ) {
    }
}
