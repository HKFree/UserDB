<?php

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Security\AuthenticationException;
use Nette\Security\SimpleIdentity;

/** Identita člena pro moje.hkfree.org podle Shibboleth UID; nevyžaduje roli správce (R5). */
class MemberAuthenticator
{
    public function __construct(private Explorer $db) {
    }

    public function authenticate(string $uid): SimpleIdentity {
        $u = ctype_digit($uid) ? $this->db->table('Uzivatel')->select('id, nick')
            ->where(Uzivatel::AKTIVNI_CLEN)->where('systemovy', 0)->get((int) $uid) : null;
        if (!$u) {
            throw new AuthenticationException('Přístup jen pro aktivní členy.');
        }
        return new SimpleIdentity($u->id, ['clen'], ['nick' => $u->nick]);
    }
}
