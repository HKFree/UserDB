<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Row;

/**
 * Počty aktivních členů po měsících pro graf ve Správě.
 *
 * Člen je v měsíci aktivní, když má na kontě aspoň jednu měsíční aktivaci: automatem, ruční nebo částečnou. Počítá
 * se jednou, i když má aktivací víc (spolek i družstvo, televize), a patří k prvnímu způsobu v tomto pořadí.
 * Vratky a dočasná aktivace přes SMS člena aktivním nedělají.
 */
final class GrafAktivnichClenu
{
    public function __construct(private UzivatelskeKonto $uzivatelskeKonto) {
    }

    /**
     * Měsíce od první aktivace (nejdřív od začátku období) do aktuálního měsíce, případně do pozdější aktivace.
     * Měsíc bez aktivace má nuly, aby graf nespojil sousední měsíce čarou ani neskončil dřív, než skončila data.
     * Bez jediné aktivace v období je seznam prázdný.
     *
     * @return list<AktivniClenoveMesic>
     */
    public function mesice(?\DateTimeImmutable $od, \DateTimeImmutable $dnes): array {
        $podleMesice = [];
        foreach ($this->uzivatelskeKonto->getAktivniClenovePoMesicich($od) as $radek) {
            $mesic = self::mesic($radek);
            $podleMesice[self::poradi($mesic->rok, $mesic->mesic)] = $mesic;
        }
        if ($podleMesice === []) {
            return [];
        }
        $posledni = max(array_key_last($podleMesice), self::poradi((int) $dnes->format('Y'), (int) $dnes->format('n')));
        return $this->doplnChybejici($podleMesice, array_key_first($podleMesice), $posledni);
    }

    private static function mesic(Row $radek): AktivniClenoveMesic {
        return new AktivniClenoveMesic((int) $radek->rok, (int) $radek->mesic, (int) $radek->automatem, (int) $radek->rucne, (int) $radek->castecne);
    }

    /**
     * @param array<int, AktivniClenoveMesic> $podleMesice podle pořadí měsíce
     * @return list<AktivniClenoveMesic>
     */
    private function doplnChybejici(array $podleMesice, int $prvni, int $posledni): array {
        $vysledek = [];
        for ($poradi = $prvni; $poradi <= $posledni; $poradi++) {
            $vysledek[] = $podleMesice[$poradi] ?? new AktivniClenoveMesic(intdiv($poradi, 12), $poradi % 12 + 1, 0, 0, 0);
        }
        return $vysledek;
    }

    /** Pořadové číslo měsíce od roku 0; po sobě jdoucí měsíce mají po sobě jdoucí čísla. */
    private static function poradi(int $rok, int $mesic): int {
        return $rok * 12 + $mesic - 1;
    }
}
