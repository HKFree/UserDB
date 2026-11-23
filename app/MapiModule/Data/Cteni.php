<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

/**
 * Čtecí vrstva: jediné místo, které pro GraphQL čte data. Každá veřejná metoda nejdřív rozhodne,
 * jestli přihlášený smí, a vrací jen neměnné objekty s vyjmenovanými vlastnostmi.
 */
final class Cteni
{
    /** Aktivní AP oblasti z vnějšího dotazu. */
    private const AKTIVNI_AP_OBLASTI = 'FROM Ap WHERE Oblast_id = Oblast.id AND aktivni <> 0';

    /**
     * Pořadí oblastí jako v menu webu (Oblast::formatujOblastiSAP): oblast s jediným aktivním AP stojí
     * pod názvem toho AP, ostatní pod svým názvem. Řadí databáze, aby zůstalo české abecední pořadí.
     */
    private const PORADI_OBLASTI = 'IF((SELECT COUNT(id) ' . self::AKTIVNI_AP_OBLASTI . ') = 1,'
        . ' (SELECT MIN(jmeno) ' . self::AKTIVNI_AP_OBLASTI . '), Oblast.jmeno), Oblast.jmeno';

    public function __construct(
        private readonly CteciSpojeni $spojeni,
    ) {
    }

    /**
     * Kdo jsem: role z přihlášení a k nim oblasti s aktivními AP, jako postranní panel webu.
     */
    public function ja(Prihlaseny $kdo): Ja {
        if (PovoleneRole::maNekterou($kdo) === false) {
            throw new PristupOdepren('Přihlášený nemá roli povolenou pro toto API.');
        }
        $oblasti = $this->oblasti($this->idOblasti($kdo));
        $role = [];
        foreach ($kdo->role as $rolePrihlaseneho) {
            if ($rolePrihlaseneho->oblastId === null) {
                $role[] = new Role($rolePrihlaseneho->typ, null);
            } elseif (isset($oblasti[$rolePrihlaseneho->oblastId])) {
                $role[] = new Role($rolePrihlaseneho->typ, $oblasti[$rolePrihlaseneho->oblastId]);
            }
            // Role vázaná na oblast, která mezitím zmizela, by bez oblasti vypadala jako globální.
        }
        return new Ja($kdo->uid, $kdo->nick, $role, array_values($oblasti));
    }

    /** @return list<int> */
    private function idOblasti(Prihlaseny $kdo): array {
        $ids = [];
        foreach ($kdo->role as $role) {
            if ($role->oblastId !== null) {
                $ids[$role->oblastId] = $role->oblastId;
            }
        }
        return array_values($ids);
    }

    /**
     * Oblasti i s jejich aktivními AP dvěma dotazy, ať je oblastí kolik chce.
     * @param list<int> $ids
     * @return array<int, Oblast> podle id, v pořadí menu webu
     */
    private function oblasti(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $apPodleOblasti = [];
        foreach ($this->spojeni->vyber('SELECT id, jmeno, Oblast_id FROM Ap WHERE Oblast_id IN (?) AND aktivni <> 0 ORDER BY jmeno', $ids) as $radek) {
            $apPodleOblasti[(int) $radek['Oblast_id']][] = new Ap((int) $radek['id'], (string) $radek['jmeno']);
        }
        $oblasti = [];
        foreach ($this->spojeni->vyber('SELECT id, jmeno FROM Oblast WHERE id IN (?) ORDER BY ' . self::PORADI_OBLASTI, $ids) as $radek) {
            $id = (int) $radek['id'];
            $oblasti[$id] = new Oblast($id, (string) $radek['jmeno'], $apPodleOblasti[$id] ?? []);
        }
        return $oblasti;
    }
}
