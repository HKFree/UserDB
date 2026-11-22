<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

/**
 * Čtecí vrstva: jediné místo, které pro GraphQL čte data. Každá veřejná metoda nejdřív rozhodne,
 * jestli přihlášený smí, a vrací jen neměnné objekty s vyjmenovanými vlastnostmi.
 */
final class Cteni
{
    public function __construct(
        private readonly CteciSpojeni $spojeni,
    ) {
    }

    /**
     * Kdo jsem: role z přihlášení a k nim oblasti s aktivními AP, jako postranní panel webu.
     */
    public function ja(Prihlaseny $kdo): Ja {
        if (!PovoleneRole::maNekterou($kdo)) {
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
     * @return array<int, Oblast> podle id, v pořadí podle jména
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
        foreach ($this->spojeni->vyber('SELECT id, jmeno FROM Oblast WHERE id IN (?) ORDER BY jmeno', $ids) as $radek) {
            $id = (int) $radek['id'];
            $oblasti[$id] = new Oblast($id, (string) $radek['jmeno'], $apPodleOblasti[$id] ?? []);
        }
        return $oblasti;
    }
}
