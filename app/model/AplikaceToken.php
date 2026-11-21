<?php

namespace App\Model;

use Nette;
use DateInterval;
use Nette\Database\Context;
use Nette\Utils\Random;

/**
 * @author
 */
class AplikaceToken extends Table
{
    /**
     * @var string
     */
    protected $tableName = 'AplikaceToken';

    /**
     * @param int|string $uid
     * @return Nette\Database\Table\ActiveRow|array<array-key, mixed>|int|bool
     */
    public function createAplikaceToken($uid): Nette\Database\Table\ActiveRow|array|int|bool {
        return ($this->insert(array(
            'token' => Random::generate(64),
            'Uzivatel_id' => $uid,
            'pouzit_poprve' => new Nette\Utils\DateTime(),
            'pouzit_naposledy' => new Nette\Utils\DateTime()
        )));
    }

    /**
     * @param int|string $uid
     * @param string|null $token
     */
    public function verifyToken($uid, $token): bool {
        $token = $this->findAll()->where('Uzivatel_id', $uid)->where('token', $token)->fetch();
        if ($token !== null) {
            $token->update(array('pouzit_naposledy' => new Nette\Utils\DateTime()));
            return (true);
        } else {
            return (false);
        }
    }

    /** @param int|string $uid */
    public function deleteTokensForUID($uid): void {
        $this->findAll()->where('Uzivatel_id', $uid)->delete();
    }
}
