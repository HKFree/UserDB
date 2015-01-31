<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class TypCestnehoClenstvi extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypCestnehoClenstvi';

    public function getTypCestnehoClenstvi()
    {
        return($this->findAll());
    }
}