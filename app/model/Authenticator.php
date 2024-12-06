<?php

namespace App\Model;

use Nette;
use Nette\Utils\DateTime;
use App\Model;
use Nette\Security;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Users authenticator.
 */
class Authenticator implements Security\IAuthenticator
{
    protected $context;
    private $fakeUser;
    private $spravceOb;

    public function __construct($fakeUser, Nette\Database\Context $ctx)
    {
        $this->context = $ctx;
        $this->fakeUser = $fakeUser;
    }

    /**
     * Performs an authentication.
     * @return Nette\Security\Identity
     * @throws Nette\Security\AuthenticationException
     */
    public function authenticate(array $credentials)
    {
        list($userID, $password) = $credentials;
        if (!$userID) {
            throw new Nette\Security\AuthenticationException('User not found.');
        }
        if ($this->fakeUser != false) {            /// debuging identity
            $userID = $this->fakeUser["userID"];
            $nick = $this->fakeUser["userName"];
            $passwordHash = $this->fakeUser["passwordHash"] ?? 'dummy';  // optional
            //crypted hash from database
            //$passwordHash = $this->context->table("Uzivatel")->where('id', $userID)->fetchField('heslo_hash');
        } elseif (array_key_exists('HTTP_GIVENNAME', $_SERVER)) {
            $nick = $_SERVER['HTTP_GIVENNAME'];
            //plaintext password from shibboleth
            $passwordHash = $_SERVER['HTTP_INITIALS'];
            //TODO: switch to below when ready on AWEG side
            //crypted hash from database
            //$passwordHash = $this->context->table("Uzivatel")->where('id', $userID)->fetchField('heslo_hash');
        } else {
            $args = array('nick' => 'NoNickFound');
        }
        $date = new DateTime();
        $spravcepro = $this->context->table("SpravceOblasti")->where('Uzivatel_id', $userID)->fetchAll();
        $roles = [];
        foreach ($spravcepro as $key => $value) {
            if ($value->od->getTimestamp() < $date->getTimestamp() && (!$value->do || $value->do->getTimestamp() > $date->getTimestamp())) {
                if ($value->Oblast) {
                    $roles[] = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text."-".$value->Oblast;
                } else {
                    $roles[] = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text;
                }
            }
        }

        if (count($roles) < 1) {
            throw new Nette\Security\AuthenticationException('User not allowed.');
        }

        return new HkfIdentity($userID, $roles, $nick, $passwordHash);
    }
}
