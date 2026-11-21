<?php

namespace App;

class Settings
{
    /** @param bool|array{userID: int|string, userName: string, passwordHash?: string} $fakeUser */
    public function __construct(
        public bool|array $fakeUser,
    ) {
    }
}
