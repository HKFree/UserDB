<?php

namespace App\Model;

use Nette;

/**
 * App parameters.
 */
class Parameters
{
    /**
    * @var int
    */
    protected $clenskyPrispevek;
    
    public function __construct($clenskyPrispevek)
    {
        $this->clenskyPrispevek = $clenskyPrispevek;
    }   
    
    public function getVyseClenskehoPrispevku()
    {
        return($this->clenskyPrispevek);
    }
}
