<?php

declare(strict_types=1);

namespace App\Model;

/** Období grafu počtu aktivních členů ve Správě. */
enum ObdobiGrafuClenu: string
{
    case Rok = '12m';
    case PetLet = '5let';
    case Vse = 'vse';

    public function popisek(): string {
        return match ($this) {
            self::Rok => '12 měsíců',
            self::PetLet => '5 let',
            self::Vse => 'Vše',
        };
    }

    /**
     * Začátek prvního měsíce období. Období začíná stejným měsícem před rokem nebo pěti lety, aby souhrn porovnával
     * stejné roční období. Celá historie nemá začátek.
     */
    public function od(\DateTimeImmutable $dnes): ?\DateTimeImmutable {
        $mesicu = match ($this) {
            self::Rok => 12,
            self::PetLet => 60,
            self::Vse => null,
        };
        if ($mesicu === null) {
            return null;
        }
        return $dnes->modify('first day of this month midnight')->modify("-$mesicu months");
    }
}
