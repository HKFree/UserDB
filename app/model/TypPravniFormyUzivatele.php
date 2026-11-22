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

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTypyPravniFormyUzivatele(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
