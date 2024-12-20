<?php

namespace App;

class Settings
{
    public function __construct(
        public bool|array $fakeUser,
    ) {
    }
}
