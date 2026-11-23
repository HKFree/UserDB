<?php

namespace App\Model;

use Nette;
use Nette\Utils\Html;

/**
 * @author bkralik
 * Pozor, tabulka se jmenuje AwegUserS, ale naming convention v userdb velí,
 * aby to bylo jednotné číslo...
 */
class AwegUser extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'AwegUsers';

    /** @param int|string $uid */
    public function getAwegUser($uid): Nette\Database\Table\ActiveRow|false {
        $r = $this->findOneBy(["hkfree_uid" => $uid]);

        if ($r !== null) {
            return $r;
        } else {
            return false;
        }
    }
}
