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
    protected Nette\Database\Explorer $context;
    /** @var false|array{userID: int|string, userName: string, passwordHash?: string} */
    private bool|array $fakeUser;

    /** @param false|array{userID: int|string, userName: string, passwordHash?: string} $fakeUser */
    public function __construct(bool|array $fakeUser, Nette\Database\Explorer $ctx) {
        $this->context = $ctx;
        $this->fakeUser = $fakeUser;
    }

    /**
     * Performs an authentication.
     * @param array{int|string|null, string|null} $credentials
     * @throws Nette\Security\AuthenticationException
     */
    public function authenticate(array $credentials): HkfIdentity {
        list($userID, $password) = $credentials;
        if (in_array($userID, [null, 0, '', '0'], true)) {
            throw new Nette\Security\AuthenticationException('User not found.', Security\Authenticator::IdentityNotFound);
        }
        if ((bool) $this->fakeUser) {            /// debuging identity
            $userID = $this->fakeUser["userID"];
            $nick = $this->fakeUser["userName"];
            $passwordHash = $this->fakeUser["passwordHash"] ?? 'dummy';  // optional
            //crypted hash from database
            //$passwordHash = $this->context->table("Uzivatel")->where('id', $userID)->fetchField('heslo_hash');
        } elseif (isset($_SERVER['HTTP_GIVENNAME'], $_SERVER['HTTP_INITIALS'])) {
            $nick = $_SERVER['HTTP_GIVENNAME'];
            //plaintext password from shibboleth
            $passwordHash = $_SERVER['HTTP_INITIALS'];
            //TODO: switch to below when ready on AWEG side
            //crypted hash from database
            //$passwordHash = $this->context->table("Uzivatel")->where('id', $userID)->fetchField('heslo_hash');
        } else {
            throw new Nette\Security\AuthenticationException('User attributes not provided.', Security\Authenticator::Failure);
        }
        $date = new DateTime();
        $spravcepro = $this->context->table("SpravceOblasti")->where('Uzivatel_id', $userID)->fetchAll();
        $roles = [];
        foreach ($spravcepro as $key => $value) {
            if ($value->od->getTimestamp() < $date->getTimestamp() && ($value->do === null || $value->do->getTimestamp() > $date->getTimestamp())) {
                $typ = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id');
                $oblast = $value->ref('Oblast', 'Oblast_id');
                if ($typ === null || ($value->Oblast_id !== null && $oblast === null)) {
                    continue;
                }
                $roles[] = $oblast !== null ? $typ->text."-".$oblast->id : $typ->text;
            }
        }

        if (count($roles) < 1) {
            throw new Nette\Security\AuthenticationException('User not allowed.', Security\Authenticator::NotApproved);
        }

        return new HkfIdentity($userID, $roles, $nick, $passwordHash);
    }
}
