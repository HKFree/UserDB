<?php

namespace App;

class Settings
{
    public function __construct(
        public bool|array $fakeUser,
        public string $memberHost = '',
        public string|false $vapidPublicKey = false,
    ) {
    }
}
