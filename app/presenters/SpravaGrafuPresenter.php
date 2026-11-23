<?php

namespace App\Presenters;

use App\Model;
use App\Model\AktivniClenoveMesic;
use App\Model\ObdobiGrafuClenu;
use Nette\Utils\Json;

/**
 * Graf počtu aktivních členů po měsících.
 * @property-read \Nette\Bridges\ApplicationLatte\Template $template
 */
class SpravaGrafuPresenter extends SpravaPresenter
{
    /** Způsoby měsíční aktivace v pořadí legendy: vlastnost AktivniClenoveMesic => popisek. */
    private const ZPUSOBY_AKTIVACE = ['automatem' => 'Automatem', 'rucne' => 'Ručně', 'castecne' => 'Částečně'];

    public function __construct(Model\Uzivatel $uzivatel, Model\AP $ap, private Model\GrafAktivnichClenu $grafAktivnichClenu) {
        parent::__construct($uzivatel, $ap);
    }

    /** @param string $obdobi neznámé období se zobrazí jako výchozí */
    public function renderUsersgraph(string $obdobi = ObdobiGrafuClenu::PetLet->value): void {
        $vybraneObdobi = ObdobiGrafuClenu::tryFrom($obdobi) ?? ObdobiGrafuClenu::PetLet;
        $dnes = new \DateTimeImmutable();
        $mesice = $this->grafAktivnichClenu->mesice($vybraneObdobi->od($dnes), $dnes);

        $this->template->obdobi = $vybraneObdobi;
        $this->template->vsechnaObdobi = ObdobiGrafuClenu::cases();
        $this->template->mesice = $mesice;
        $this->template->zpusoby = self::ZPUSOBY_AKTIVACE;
        $this->template->jsouRucniAktivace = array_filter($mesice, static fn (AktivniClenoveMesic $mesic): bool => $mesic->rucne + $mesic->castecne > 0) !== [];
        $this->template->grafJson = Json::encode(array_map(self::bodGrafu(...), $mesice));
        $this->template->addFilter('cislo', self::cislo(...));
    }

    /** @return array{x: int, nazev: string, automatem: int, rucne: int, castecne: int, celkem: int} */
    private static function bodGrafu(AktivniClenoveMesic $mesic): array {
        return [
            'x' => $mesic->zacatek(),
            'nazev' => $mesic->nazev(),
            'automatem' => $mesic->automatem,
            'rucne' => $mesic->rucne,
            'castecne' => $mesic->castecne,
            'celkem' => $mesic->celkem(),
        ];
    }

    /** Číslo v českém zápisu: desetinná čárka a nezlomitelná mezera mezi tisíci. */
    private static function cislo(int|float $cislo, int $desetinnaMista = 0): string {
        return number_format($cislo, $desetinnaMista, ',', "\u{a0}");
    }
}
