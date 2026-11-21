<?php

declare(strict_types=1);

namespace Test;

use App\Model\Stitek;
use Nette\Database\Explorer;
use Nette\Database\Table\Selection;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestDoubles.php';
\Tester\Environment::setup();

$selection = new class () extends Selection {
    /** @var list<array{string|array<array-key, mixed>, array<array-key, mixed>}> */
    public array $conditions = [];

    public function __construct() {
        $database = new OfflineDatabase();
        parent::__construct($database, $database->getConventions(), 'Stitek');
    }

    public function where(string|array $condition, ...$params): static {
        $this->conditions[] = [$condition, $params];
        return $this;
    }
};

$database = new class ($selection) extends Explorer {
    /** @var list<string> */
    public array $tables = [];

    /** @param Selection<\Nette\Database\Table\ActiveRow> $selection */
    public function __construct(private Selection $selection) {
        $database = new OfflineDatabase();
        parent::__construct($database->getConnection(), $database->getStructure());
    }

    /** @return Selection<\Nette\Database\Table\ActiveRow> */
    public function table(string $table): Selection {
        $this->tables[] = $table;
        return $this->selection;
    }
};

$stitky = new Stitek($database);
foreach ([42, 0] as $oblastId) {
    Assert::same($selection, $stitky->getStitkyByOblast($oblastId));
}
Assert::same(['Stitek', 'Stitek'], $database->tables);
Assert::same([['Oblast_id', [42]], ['Oblast_id', [0]]], $selection->conditions);
