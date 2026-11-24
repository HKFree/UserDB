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
        return $this->popiskyApVeSkupinach($this->seskupOblastiSAP($oblasti, $pouzeAktivni));
    }

    /**
     * Oblasti s jejich AP seřazenými podle jména, v pořadí názvů, pod kterými se vypisují, bez ohledu
     * na pořadí oblastí na vstupu. Oblast bez AP ve výsledku není; oblast zadaná vícekrát (správce
     * s více rolemi v jedné oblasti) je v něm jednou.
     *
     * @param iterable<Nette\Database\Table\ActiveRow> $oblasti
     * @return list<array{id: int, jmeno: string, aps: array<int, string>}>
     */
    public function seskupOblastiSAP(iterable $oblasti, bool $pouzeAktivni = true): array {
        $idOblasti = [];
        foreach ($oblasti as $oblast) {
            $idOblasti[] = $oblast->id;
        }
        if ($idOblasti === []) {
            return [];
        }

        $skupiny = [];
        foreach ($this->findAll()->where('id', $idOblasti)->order(self::razeniPodlePopisku($pouzeAktivni)) as $oblast) {
            $id = (int) $oblast->id;
            $apcka = $oblast->related('Ap.Oblast_id');
            if ($pouzeAktivni) {
                $apcka = $apcka->where('aktivni');
            }
            $aps = [];
            foreach ($apcka->order('jmeno') as $ap) {
                $aps[(int) $ap->id] = $ap->jmeno . ' (' . $ap->id . ')';
            }
            if ($aps !== []) {
                $skupiny[] = ['id' => $id, 'jmeno' => (string) $oblast->jmeno, 'aps' => $aps];
            }
        }
        return $skupiny;
    }

    /**
     * Popisky AP pro výběr a přehled: AP z oblasti s více AP nese před jménem jméno oblasti.
     *
     * @param list<array{id: int, jmeno: string, aps: array<int, string>}> $skupiny
     * @return array<int, string>
     */
    public function popiskyApVeSkupinach(array $skupiny): array {
        $popisky = [];
        foreach ($skupiny as $skupina) {
            $predpona = count($skupina['aps']) === 1 ? '' : $skupina['jmeno'] . ' - ';
            foreach ($skupina['aps'] as $id => $popisek) {
                $popisky[$id] = $predpona . $popisek;
            }
        }
        return $popisky;
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
