<?php

namespace App\Model;

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
        try {
            $plaintext = Crypto::decrypt($cyphered, $this->passPhrase);
            return($plaintext);
        } catch (\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
            // An attack! Either the wrong key was loaded, or the ciphertext has
            // changed since it was created -- either corrupted in the database or
            // intentionally modified by sysadmin trying to carry out an attack.
        
            // ... handle this case in a way that's suitable to your application ...
        }
        
    }
}
