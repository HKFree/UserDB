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

    /**
     * Poslední pohyb (s nejvyšším id) daných typů každého z uživatelů. Uživatel bez takového pohybu ve výsledku chybí.
     *
     * @param list<int> $idUzivatelu
     * @param list<int> $typyPohybu
     * @return array<int, Nette\Database\Row> podle id uživatele, sloupce Uzivatel_id, datum, castka a druzstvo
     */
    public function getPosledniPohyby(array $idUzivatelu, array $typyPohybu): array {
        if ($idUzivatelu === [] || $typyPohybu === []) {
            return [];
        }
        return $this->getConnection()->query(
            'SELECT K.Uzivatel_id, K.datum, K.castka, K.druzstvo
            FROM UzivatelskeKonto K
            JOIN (
                SELECT MAX(id) AS id FROM UzivatelskeKonto
                WHERE Uzivatel_id IN (?) AND TypPohybuNaUctu_id IN (?)
                GROUP BY Uzivatel_id
            ) posledni ON posledni.id = K.id',
            $idUzivatelu,
            $typyPohybu
        )->fetchPairs('Uzivatel_id');
    }

    /** @param array<array-key, mixed> $by */
    public function findPohyb(array $by): ?Nette\Database\Table\ActiveRow {
        return ($this->findOneBy($by));
    }
}
