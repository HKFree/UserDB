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
    * @var string
    */
    protected $passPhrase;
    
    public function __construct($passPhrase)
    {
        $this->passPhrase = Key::loadFromAsciiSafeString($passPhrase);
    }   
    
    public function encrypt($plaintext)
    {
        $ciphertext = Crypto::encrypt($plaintext, $this->passPhrase);
        return($ciphertext);
    }
    
    public function decrypt($cyphered)
    {
        $plaintext = Crypto::decrypt($cyphered, $this->passPhrase);
        return($plaintext);
    }
}
