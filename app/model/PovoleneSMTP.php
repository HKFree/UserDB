<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author
 */
class PovoleneSMTP extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'PovoleneSMTP';

    public function getIP($ip_id)
    {
        return $this->findAll()->where("IPAdresa_id = ?", $ip_id)->fetch();
    }

}