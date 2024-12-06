<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypPohybuNaUctu extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypPohybuNaUctu';

    public function getTypPohybuNaUctu()
    {
        return ($this->findAll());
    }
}
