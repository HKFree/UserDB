<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypPravniFormyUzivatele extends Table
{
    public const FYZICKA_OSOBA = 1;
    public const PRAVNICKA_OSOBA = 2;

    /**
    * @var string
    */
    protected $tableName = 'TypPravniFormyUzivatele';

    public function getTypyPravniFormyUzivatele() {
        return ($this->findAll());
    }
}
