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

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getUzivatelskeKonto(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamNesparovanych(): Nette\Database\Table\Selection {
        return ($this->findBy(array('Uzivatel_id IS NULL AND PrichoziPlatba_id NOT IN (SELECT PrichoziPlatba_id FROM `UzivatelskeKonto` WHERE `Uzivatel_id` IS NULL AND PrichoziPlatba_id IS NOT NULL GROUP BY PrichoziPlatba_id HAVING Count(id)>1)')));
    }

    /**
     * @param int|string $idUzivatele
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getUzivatelskeKontoUzivatele($idUzivatele): Nette\Database\Table\Selection {
        return ($this->findBy(array('Uzivatel_id' => $idUzivatele)));
    }

    /** @param int|string|null $idPlatby */
    public function getUzivatelskeKontoByPrichoziPlatba($idPlatby): ?Nette\Database\Table\ActiveRow {
        return ($this->findBy(array('PrichoziPlatba_id' => $idPlatby))->order('id DESC')->fetch());
    }

    /** @param array<array-key, mixed> $by */
    public function findPohyb(array $by): ?Nette\Database\Table\ActiveRow {
        return ($this->findOneBy($by));
    }
}
