<?php

declare(strict_types=1);

namespace App\Components;

use Grido\Components\Filters\Filter;
use Grido\Grid;
use Nette\ComponentModel\IContainer;

/**
 * Tabulka Grido, jak ji používá celá aplikace: česky a s tlačítky Vyhledat a Resetovat nahoře u filtrů.
 */
final class AppGrid extends Grid
{
    private const TEMPLATE_FILE = __DIR__ . '/../templates/components/grid.latte';

    public function __construct(IContainer $parent, string $name) {
        parent::__construct($parent, $name);
        $this->getTranslator()->setLang('cs');
        $this->setTemplateFile(self::TEMPLATE_FILE);
        // Tabulce s akcemi by Grido kreslilo filtry do záhlaví sloupců, kde šablona tlačítka nemá.
        $this->setFilterRenderType(Filter::RENDER_OUTER);
    }
}
