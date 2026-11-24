<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Filtr nad seznamem členů: hledaný text a štítky, které člen má nebo nemá. Pracuje s dotazem do databáze,
 * takže najde členy na všech stránkách tabulky, ne jen na té načtené.
 */
final class FiltrSeznamuClenu
{
    /** Sloupce, které vidí každý, kdo seznam vidí. */
    private const VEREJNE_SLOUPCE = ['Uzivatel.id'];

    /** Sloupce s údaji člena; prohledávají se jen tomu, komu je seznam ukazuje. */
    private const SLOUPCE_UDAJU_CLENA = [
        'Uzivatel.jmeno',
        'Uzivatel.prijmeni',
        "CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni)",
        "CONCAT(Uzivatel.prijmeni, ' ', Uzivatel.jmeno)",
        'Uzivatel.nick',
        'Uzivatel.firma_nazev',
        'Uzivatel.email',
        'Uzivatel.email2',
        'Uzivatel.telefon',
        'Uzivatel.ulice_cp',
        'Uzivatel.mesto',
        'Uzivatel.psc',
        'Uzivatel.poznamka',
    ];

    /**
     * Členové, u kterých se text najde v UID, IP adrese, nebo (s $udajeClenu) v jejich údajích. Diakritika ani velikost
     * písmen nehrají roli; `%` a `_` mají význam jako v ostatních textových filtrech tabulek.
     * @param Selection<ActiveRow> $clenove
     * @return Selection<ActiveRow>
     */
    public static function hledej(Selection $clenove, string $text, bool $udajeClenu): Selection {
        $vzor = '%' . $text . '%';
        $sloupce = $udajeClenu ? [...self::VEREJNE_SLOUPCE, ...self::SLOUPCE_UDAJU_CLENA] : self::VEREJNE_SLOUPCE;
        $podminky = array_map(static fn (string $sloupec): string => HledaniBezDiakritiky::sloupec($sloupec) . ' LIKE ?', $sloupce);
        $podminky[] = 'Uzivatel.id IN (SELECT Uzivatel_id FROM IPAdresa WHERE ' . HledaniBezDiakritiky::sloupec('ip_adresa') . ' LIKE ?)';
        return $clenove->where('(' . implode(' OR ', $podminky) . ')', ...array_fill(0, count($podminky), $vzor));
    }

    /**
     * Členové s každým kladným a bez každého záporného štítku z hodnoty filtru ("5,-7").
     * @param Selection<ActiveRow> $clenove
     * @return Selection<ActiveRow>
     */
    public static function stitky(Selection $clenove, string $hodnota): Selection {
        foreach (self::stitkyZHodnoty($hodnota) as $stitek) {
            $clenove->where(
                'Uzivatel.id ' . ($stitek > 0 ? 'IN' : 'NOT IN') . ' (SELECT Uzivatel_id FROM StitekUzivatele WHERE Stitek_id = ?)',
                abs($stitek),
            );
        }
        return $clenove;
    }

    /**
     * Štítky z hodnoty filtru: kladné číslo člen má mít, záporné nemá. Neplatné části a opakování vynechá.
     * @return list<int>
     */
    public static function stitkyZHodnoty(string $hodnota): array {
        $stitky = [];
        foreach (explode(',', $hodnota) as $cast) {
            $cast = trim($cast);
            if (preg_match('/^-?[1-9]\d{0,9}$/', $cast) === 1) {
                $stitky[(int) $cast] = (int) $cast;
            }
        }
        return array_values($stitky);
    }
}
