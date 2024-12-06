<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Security\Identity;

/**
 * Class HkfIdentity
 * @author Pavel Kriz <pavkriz@hkfree.org>
 *
 * Type-hinted non-magic API to logged user identity
 *
 * Direct properties for backward compatibility (may be removed later):
 * @property mixed $nick
 */
class HkfIdentity extends Identity
{
    public function __construct($id, array $roles, string $nick, string $passwordHash)
    {
        $data = [
            'nick' => $nick,
            'passwordHash' => $passwordHash,
        ];
        parent::__construct($id, $roles, $data);
    }

    public function getUid(): int
    {
        return (int)$this->getId();
    }

    public function getNick(): string
    {
        return $this->getData()['nick'];
    }

    public function getPasswordHash(): string
    {
        return $this->getData()['passwordHash'];
    }
}
