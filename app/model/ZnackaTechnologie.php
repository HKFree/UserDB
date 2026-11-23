<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Utils\Html;

/**
 * Štítek technologie připojení ve sloupci Tech: ikona a krátký text, celý název v tooltipu.
 * Technologie bez krátkého textu ukáže svůj název z tabulky TechnologiePripojeni.
 */
final class ZnackaTechnologie
{
    private const NEZJISTENO = 0;

    /** @var array<int, array{string, string}> id technologie => [ikona Font Awesome, krátký text] */
    private const ZNACKY = [
        self::NEZJISTENO => ['fa-question', ''],
        1 => ['fa-wifi', 'Wi-Fi 2,4'],
        2 => ['fa-exchange', 'P2P 2,4'],
        3 => ['fa-wifi', 'Wi-Fi 5'],
        4 => ['fa-exchange', 'P2P 5'],
        5 => ['fa-plug', 'LAN'],
        6 => ['fa-bolt', 'Optika'],
        7 => ['fa-plug', 'LAN páteř'],
        8 => ['fa-plug', 'LAN 5'],
        9 => ['fa-plug', 'LAN PtP 5'],
    ];

    public static function html(int $id, string $nazev): Html {
        [$ikona, $text] = self::ZNACKY[$id] ?? ['fa-question', $nazev];
        $znacka = Html::el('span')
            ->setClass('conn-tech' . ($id === self::NEZJISTENO ? ' conn-tech-unknown' : ''))
            ->setTitle($nazev)
            ->data('toggle', 'tooltip')
            ->data('placement', 'right');
        $znacka->addHtml(Html::el('i')->setClass('fa ' . $ikona)->setAttribute('aria-hidden', 'true'));
        if ($text !== '') {
            $znacka->addText(' ' . $text);
        } else {
            // samotna ikona by ctecce nic nerekla
            $znacka->addHtml(Html::el('span')->setClass('sr-only')->setText('nezjištěno'));
        }
        return $znacka;
    }
}
