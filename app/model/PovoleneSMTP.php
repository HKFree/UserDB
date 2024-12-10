<?php

namespace App\Model;

use Nette;
use Nette\Utils\Html;

/**
 * @author
 */
class PovoleneSMTP extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'PovoleneSMTP';

    public function getIP($ip_id) {
        return $this->findAll()->where("IPAdresa_id = ?", $ip_id)->fetch();
    }

    public function deleteIPs(array $ips) {
        if (count($ips) > 0) {
            return ($this->delete(array('id' => $ips)));
        } else {
            return true;
        }
    }
}
