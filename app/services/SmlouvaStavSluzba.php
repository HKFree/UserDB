<?php

namespace App\Services;

use Nette;

/**
 * App parameters.
 */
class SmlouvaStavSluzba
{
    /**
     * @var string
     */

    public function __construct() {
    }

    public function getStav(Nette\Database\Table\ActiveRow $smlouva): string {
        $sid = $smlouva->id;
        $konec = $smlouva->kdy_ukonceno;

        $podpisy = $smlouva->related('PodpisSmlouvy.Smlouva_id');

        $pocetPodpisu = 0;
        $pocetPodepsano = 0;

        $datum = null;

        if ($konec != null) {
            return "❌ ukončená dne ".$konec->format('d.m.Y');
        }

        foreach ($podpisy as $podpis) {
            $pocetPodpisu++;
            if ($podpis->kdy_podepsano != null) {
                $pocetPodepsano++;
                if ($datum == null || $datum < $podpis->kdy_podepsano) {
                    $datum = $podpis->kdy_podepsano;
                }
            }
            if ($podpis->kdy_odmitnuto != null) {
                return "🚫 odmítnutá dne ".$podpis->kdy_odmitnuto->format('d.m.Y');
            }
        }

        if ($pocetPodpisu == $pocetPodepsano) {
            return isset($datum) ? "✅ platná od ".$datum->format('d.m.Y') : '---';
        } else {
            return "⏳ čeká na podpis";
        }
    }
}
