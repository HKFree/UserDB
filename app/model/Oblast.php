<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class Oblast extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Oblast';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamOblasti(): Nette\Database\Table\Selection {
        return $this->findAll()->order("jmeno");
    }

    /**
     * Oblasti v pořadí, v jakém je vypisuje menu: oblast s jediným AP stojí pod názvem toho AP.
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getSeznamOblastiProMenu(): Nette\Database\Table\Selection {
        return $this->findAll()->order(self::razeniPodlePopisku(false));
    }

    /** @param int|string|null $id */
    public function getOblast($id): ?Nette\Database\Table\ActiveRow {
        return $id === null ? null : $this->find($id);
    }

    /** @return array<int, string> */
    public function getSeznamOblastiBezAP(): array {
        $oblasti = $this->getSeznamOblasti();
        return $oblasti->fetchPairs('id', 'jmeno');
    }

    /**
     * @param int|string $IDoblasti
     * @return array<int, Nette\Database\Table\ActiveRow>
     */
    public function getSeznamSpravcu($IDoblasti): array {
        $oblast = $this->find($IDoblasti);
        if ($oblast === null) {
            return [];
        }
        return $oblast->related("SpravceOblasti.Oblast_id")->where('SpravceOblasti.od < NOW() AND (SpravceOblasti.do IS NULL OR SpravceOblasti.do > NOW())')->fetchPairs('Uzivatel_id', 'Uzivatel');
    }

    /**
     * Popisky AP daných oblastí, seřazené podle popisku bez ohledu na pořadí oblastí na vstupu.
     * @param iterable<Nette\Database\Table\ActiveRow> $oblasti
     * @return array<int, string>
     */
    public function formatujOblastiSAP($oblasti, bool $pouzeAktivni = true): array {
        $idOblasti = array();
        foreach ($oblasti as $oblast) {
            $idOblasti[] = $oblast->id;
        }

        $aps = array();
        foreach ($this->findAll()->where('id', $idOblasti)->order(self::razeniPodlePopisku($pouzeAktivni)) as $oblast) {
            $apcka_oblasti = $oblast->related('Ap.Oblast_id');
            if ($pouzeAktivni) {
                $apcka_oblasti = $apcka_oblasti->where('aktivni');
            }

            foreach ($apcka_oblasti->order("jmeno") as $ap) {
                if (count($apcka_oblasti) === 1) {
                    $aps[$ap->id] = $ap->jmeno . ' (' . $ap->id . ')';
                } else {
                    $aps[$ap->id] = $oblast->jmeno . ' - ' . $ap->jmeno . ' (' . $ap->id . ')';
                }
            }
        }
        return $aps;
    }

    /**
     * Výraz pro ORDER BY nad tabulkou Oblast: název, pod kterým se oblast vypisuje. Oblast s jediným AP
     * se vypisuje názvem toho AP, ostatní svým názvem. Řadí databáze, aby zůstalo české abecední pořadí.
     * Sloupce v poddotazech jsou bez názvu tabulky: zápis s tečkou by Nette Database vyložila jako spojení tabulek.
     */
    private static function razeniPodlePopisku(bool $pouzeAktivniAp): string {
        $zApOblasti = 'FROM Ap WHERE Oblast_id = Oblast.id' . ($pouzeAktivniAp ? ' AND aktivni' : '');
        return "IF((SELECT COUNT(*) $zApOblasti) = 1, (SELECT MIN(jmeno) $zApOblasti), Oblast.jmeno), Oblast.jmeno";
    }

    /**
    * seznam oblasti pro vytvareni noveho AP
    * @param iterable<Nette\Database\Table\ActiveRow> $oblasti
    * @return array<int, string>
    */
    public function formatujOblasti($oblasti): array {
        $aps = array();
        foreach ($oblasti as $oblast) {
            $aps[$oblast->id] = $oblast->jmeno . ' (' . $oblast->id . ')';
        }
        return $aps;
    }
}
