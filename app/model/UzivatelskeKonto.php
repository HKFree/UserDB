<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class UzivatelskeKonto extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'UzivatelskeKonto';

    public function getUzivatelskeKonto() {
        return ($this->findAll());
    }

    public function getSeznamNesparovanych() {
        return ($this->findBy(array('Uzivatel_id IS NULL AND PrichoziPlatba_id NOT IN (SELECT PrichoziPlatba_id FROM `UzivatelskeKonto` WHERE `Uzivatel_id` IS NULL AND PrichoziPlatba_id IS NOT NULL GROUP BY PrichoziPlatba_id HAVING Count(id)>1)')));
    }

    public function getUzivatelskeKontoUzivatele($idUzivatele) {
        return ($this->findBy(array('Uzivatel_id' => $idUzivatele))->fetchAll());
    }

    public function getUzivatelskeKontoByPrichoziPlatba($idPlatby) {
        return ($this->findBy(array('PrichoziPlatba_id' => $idPlatby))->order('id DESC')->fetch());
    }

    public function findPohyb(array $by) {
        return ($this->findOneBy($by));
    }
}
