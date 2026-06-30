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
    protected $cenaTelevize;
    public $salt;

    public function __construct($clenskyPrispevek, $cenaTelevize, $salt) {
        $this->clenskyPrispevek = $clenskyPrispevek;
        $this->cenaTelevize = $cenaTelevize;
        $this->salt = $salt;
    }

    public function getVyseClenskehoPrispevku() {
        return ($this->clenskyPrispevek);
    }

    public function getCenaSledovaniTV() {
        return $this->cenaTelevize;
    }

}
