<?php

namespace App\Model;

use App\Services\Push\PushPrijemci;
use Nette\Database\Explorer;
use Nette\Security\AuthenticationException;
use Nette\Security\SimpleIdentity;

/** Identita člena pro moje.hkfree.org podle Shibboleth UID; nevyžaduje roli správce (R5). */
class MemberAuthenticator
{
    public function __construct(private Explorer $db) {
    }

    public function authenticate(string $uid): SimpleIdentity {
        $u = ctype_digit($uid) ? $this->db->query('SELECT u.id, u.nick FROM Uzivatel u WHERE u.id = ? AND '
            . PushPrijemci::AKTIVNI_CLEN, (int) $uid)->fetch() : null;
        if (!$u) {
            throw new AuthenticationException('Přístup jen pro aktivní členy.');
        }
        return new SimpleIdentity($u->id, ['clen'], ['nick' => $u->nick]);
    }
}
