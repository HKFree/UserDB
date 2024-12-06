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

    public function createAplikaceToken($uid)
    {
        return ($this->insert(array(
            'token' => Random::generate(64),
            'Uzivatel_id' => $uid,
            'pouzit_poprve' => new Nette\Utils\DateTime(),
            'pouzit_naposledy' => new Nette\Utils\DateTime()
        )));
    }

    public function verifyToken($uid, $token)
    {
        $token = $this->findAll()->where('Uzivatel_id', $uid)->where('token', $token)->fetch();
        if ($token) {
            $token->update(array('pouzit_naposledy' => new Nette\Utils\DateTime()));
            return (true);
        } else {
            return (false);
        }
    }

    public function deleteTokensForUID($uid)
    {
        $this->findAll()->where('Uzivatel_id', $uid)->delete();
    }
}
