<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class UzivatelskeKonto extends Table
{
    /** Typy pohybů (TypPohybuNaUctu), kterými je člen v daném měsíci aktivní. */
    public const AKTIVACE_AUTOMATEM = 4;
    public const AKTIVACE_RUCNI = 8;
    public const AKTIVACE_CASTECNA = 15;

    /**
    * @var string
    */
    protected $tableName = 'UzivatelskeKonto';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getUzivatelskeKonto(): Nette\Database\Table\Selection {
        return $this->findAll();
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamNesparovanych(): Nette\Database\Table\Selection {
        return $this->findBy(array('Uzivatel_id IS NULL AND PrichoziPlatba_id NOT IN (SELECT PrichoziPlatba_id FROM `UzivatelskeKonto` WHERE `Uzivatel_id` IS NULL AND PrichoziPlatba_id IS NOT NULL GROUP BY PrichoziPlatba_id HAVING Count(id)>1)'));
    }

    /**
     * @param int|string $idUzivatele
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getUzivatelskeKontoUzivatele($idUzivatele): Nette\Database\Table\Selection {
        return $this->findBy(array('Uzivatel_id' => $idUzivatele));
    }

    /** @param int|string|null $idPlatby */
    public function getUzivatelskeKontoByPrichoziPlatba($idPlatby): ?Nette\Database\Table\ActiveRow {
        return $this->findBy(array('PrichoziPlatba_id' => $idPlatby))->order('id DESC')->fetch();
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

    /**
     * Počet členů s měsíční aktivací v každém měsíci, kdy nějaká byla. Člen se v měsíci počítá jednou: pod aktivací
     * automatem, jinak ruční, jinak částečnou. Pohyb bez člena nebo bez data se nepočítá.
     *
     * @return list<Nette\Database\Row> vzestupně podle měsíce, sloupce rok, mesic, automatem, rucne a castecne
     */
    public function getAktivniClenovePoMesicich(?\DateTimeImmutable $od): array {
        $podminky = [
            'TypPohybuNaUctu_id' => [self::AKTIVACE_AUTOMATEM, self::AKTIVACE_RUCNI, self::AKTIVACE_CASTECNA],
            'Uzivatel_id NOT' => null,
            'datum NOT' => null,
        ];
        if ($od !== null) {
            $podminky['datum >='] = $od;
        }
        // FIELD() vrací pořadí typu v seznamu, MIN() tak vybere první způsob aktivace člena v měsíci.
        return array_values($this->getConnection()->query(
            'SELECT rok, mesic, SUM(zpusob = 1) AS automatem, SUM(zpusob = 2) AS rucne, SUM(zpusob = 3) AS castecne
            FROM (
                SELECT YEAR(datum) AS rok, MONTH(datum) AS mesic, MIN(FIELD(TypPohybuNaUctu_id, ?, ?, ?)) AS zpusob
                FROM UzivatelskeKonto
                WHERE ?and
                GROUP BY Uzivatel_id, rok, mesic
            ) clenove
            GROUP BY rok, mesic
            ORDER BY rok, mesic',
            self::AKTIVACE_AUTOMATEM,
            self::AKTIVACE_RUCNI,
            self::AKTIVACE_CASTECNA,
            $podminky,
        )->fetchAll());
    }

    /** @param array<array-key, mixed> $by */
    public function findPohyb(array $by): ?Nette\Database\Table\ActiveRow {
        return $this->findOneBy($by);
    }
}
