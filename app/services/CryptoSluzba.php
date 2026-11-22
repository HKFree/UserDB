<?php

namespace App\Services;

use Nette;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

/**
 * App parameters.
 */
class CryptoSluzba
{
    /**
     * @var Key
     */
    protected $passPhrase;

    /** @param string $passPhrase */
    public function __construct($passPhrase) {
        $this->passPhrase = Key::loadFromAsciiSafeString($passPhrase);
    }

    /** @param string $plaintext */
    public function encrypt($plaintext): string {
        $ciphertext = Crypto::encrypt($plaintext, $this->passPhrase);
        return ($ciphertext);
    }

    /** @param string $cyphered */
    public function decrypt($cyphered): string {
        $plaintext = Crypto::decrypt($cyphered, $this->passPhrase);
        return ($plaintext);
    }
}
