<?php

namespace App\Model;

use Nette;
use Nette\Utils\Html;

/**
 * @author
 */
class DNat extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'DNat';

    public function getIP($ip_id) {
        return $this->findAll()->where("ip = ?", $ip_id)->fetch();
    }

    public function deleteIPs(array $ips) {
        if (count($ips) > 0) {
            return ($this->delete(array('ip' => $ips)));
        } else {
            return true;
        }
    }
}
