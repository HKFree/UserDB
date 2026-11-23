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

    /** @param int|string $ip_id */
    public function getIP($ip_id): ?Nette\Database\Table\ActiveRow {
        return $this->findAll()->where("ip = ?", $ip_id)->fetch();
    }

    /** @param array<array-key, int|string> $ips */
    public function deleteIPs(array $ips): int|true {
        if (count($ips) > 0) {
            return $this->delete(array('ip' => $ips));
        } else {
            return true;
        }
    }
}
