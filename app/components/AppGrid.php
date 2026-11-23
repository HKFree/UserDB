<?php

declare(strict_types=1);

namespace App\Components;

use App\Model\DatabazovyZdrojGridu;
use App\Model\PoleZdrojGridu;
use Grido\Components\Filters\Filter;
use Grido\Grid;
use Nette\ComponentModel\IContainer;
use Nette\Database\Table\Selection;

/**
 * Tabulka Grido, jak ji používá celá aplikace: česky, s tlačítky Vyhledat a Resetovat nahoře u filtrů
 * a s textovými filtry, které nerozlišují diakritiku.
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

    /**
     * Obal Model zůstává stejný, jako když si zdroj dat vybírá Grido samo.
     * @param bool $forceWrapper
     */
    public function setModel(mixed $model, mixed $forceWrapper = false): Grid {
        if ($model instanceof Selection) {
            return parent::setModel(new DatabazovyZdrojGridu($model), true);
        }
        if (is_array($model)) {
            return parent::setModel(new PoleZdrojGridu($model), true);
        }

        return parent::setModel($model, $forceWrapper);
    }
}
