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

    public function __construct()
    {
    }

    public function getStav(Nette\Database\Table\ActiveRow $smlouva):string
    {
        $sid=$smlouva->id;
        $konec=$smlouva->kdy_ukonceno;

        $podpisy=$smlouva->related('Podpis.Smlouva_id');

        $pocetPodpisu=0;
        $pocetPodepsano=0;

        $datum=null;


        if($konec != null){
            return "ukončená dne ".$konec->format('d.m.Y');
        }

        foreach($podpisy as $podpis){
            $pocetPodpisu++;
            if($podpis->kdy_podepsano != null)
            {
                $pocetPodepsano++;
                if($datum == null || $datum < $podpis->kdy_podepsano)
                {
                    $datum=$podpis->kdy_podepsano;
                }
            }
            if($podpis->kdy_odmitnuto != null){
                return "odmítnutá dne ".$konec->format('d.m.Y');
            }
            \Tracy\Debugger::dump($podpis->kdy_podepsano);
            \Tracy\Debugger::dump($podpis->kdy_odmitnuto);
        }

        if($pocetPodpisu==$pocetPodepsano){
            return "platná od ".$datum->format('d.m.Y');
        }
        else{
            return "čeká na podpis";
        }
    }
}
