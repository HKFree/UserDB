<?php

namespace App\Model;

use Nette;

/**
 * App parameters.
 */
class Parameters
{
    /**
    * @var int|float|numeric-string
    */
    protected $clenskyPrispevek;
    /** @var int|float|numeric-string */
    protected $cenaTelevize;
    /** @var string */
    public $salt;

    /**
     * @param int|float|numeric-string $clenskyPrispevek
     * @param int|float|numeric-string $cenaTelevize
     * @param string $salt
     */
    public function __construct($clenskyPrispevek, $cenaTelevize, $salt) {
        $this->clenskyPrispevek = $clenskyPrispevek;
        $this->cenaTelevize = $cenaTelevize;
        $this->salt = $salt;
    }

    /** @return int|float|numeric-string */
    public function getVyseClenskehoPrispevku() {
        return $this->clenskyPrispevek;
    }

    /** @return int|float|numeric-string */
    public function getCenaSledovaniTV() {
        return $this->cenaTelevize;
    }

}
