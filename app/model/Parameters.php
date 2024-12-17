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
    public $salt;

    /* migrace 2025 temporary */
    public $migrace2025Stitek1 = 'DEML';

    public function __construct($clenskyPrispevek, $salt) {
        $this->clenskyPrispevek = $clenskyPrispevek;
        $this->salt = $salt;
    }

    public function getVyseClenskehoPrispevku() {
        return ($this->clenskyPrispevek);
    }
}
