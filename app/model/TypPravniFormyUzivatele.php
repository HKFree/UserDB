<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypPravniFormyUzivatele extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypPravniFormyUzivatele';

    public function getTypyPravniFormyUzivatele() {
        return ($this->findAll());
    }
}
