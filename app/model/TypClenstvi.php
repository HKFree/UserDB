<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypClenstvi extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypClenstvi';

    public function getTypyClenstvi()
    {
        return ($this->findAll());
    }
}
