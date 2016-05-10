<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class StavBankovnihoUctu extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'StavBankovnihoUctu';

    public function getAktualniStavyBankovnihoUctu()
    {
        return($this->findAll()->group('BankovniUcet_id')->order('datum DESC')->fetchAll());
    }
}