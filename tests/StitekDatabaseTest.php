<?php

declare(strict_types=1);

namespace Test;

use App\Model\Stitek;
use Nette\Database\DriverException;
use Nette\Database\ForeignKeyConstraintViolationException;
use Nette\Database\UniqueConstraintViolationException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

$database = TestEnvironment::database();
$connection = $database->getConnection();

Assert::match('10.11.%a%', $connection->fetchField('SELECT VERSION()'));
Assert::same(1, $connection->fetchField('SELECT @@skip_networking'));
$modes = explode(',', $connection->fetchField('SELECT @@SESSION.sql_mode'));
sort($modes);
Assert::same(['ERROR_FOR_DIVISION_BY_ZERO', 'NO_AUTO_CREATE_USER', 'NO_ENGINE_SUBSTITUTION'], $modes);
Assert::exception(fn () => $connection->query('USE userdb_dev'), DriverException::class);

$database->beginTransaction();
try {
    $area1 = $database->table('Oblast')->insert(['jmeno' => 'Testovací oblast A', 'datum_zalozeni' => '2026-01-01 00:00:00']);
    $area2 = $database->table('Oblast')->insert(['jmeno' => 'Testovací oblast B', 'datum_zalozeni' => '2026-01-01 00:00:00']);
    $stitky = new Stitek($database);
    Assert::count(0, $stitky->getStitkyByOblast($area1->id)->fetchAll());

    $tag = $stitky->createStitek(['Oblast_id' => $area1->id, 'text' => 'Příliš žluťoučký štítek', 'poznamka' => null]);
    $other = $stitky->createStitek(['Oblast_id' => $area2->id, 'text' => 'Příliš žluťoučký štítek']);
    $global = $stitky->createStitek(['Oblast_id' => null, 'text' => 'Globální štítek']);
    Assert::same('Příliš žluťoučký štítek', $stitky->getStitekById($tag->id)->text);
    Assert::same([$tag->id], array_keys($stitky->getStitkyByOblast($area1->id)->fetchAll()));
    Assert::same([$other->id], array_keys($stitky->getStitkyByOblast($area2->id)->fetchAll()));
    $globalIds = array_keys($stitky->getStitkyByOblast(null)->fetchAll());
    Assert::contains($global->id, $globalIds);
    Assert::notContains($tag->id, $globalIds);
    Assert::notContains($other->id, $globalIds);
    Assert::count(0, $stitky->getStitkyByOblast(0)->fetchAll());

    $stitky->updateStitek($tag->id, ['text' => 'Upravený štítek', 'poznamka' => 'Poznámka s diakritikou']);
    Assert::same('Poznámka s diakritikou', $stitky->getStitekByText('Upravený štítek')->poznamka);
    Assert::exception(fn () => $stitky->createStitek(['Oblast_id' => $area1->id, 'text' => 'Upravený štítek']), UniqueConstraintViolationException::class);
    Assert::exception(fn () => $stitky->createStitek(['Oblast_id' => 0, 'text' => 'Neplatná oblast']), ForeignKeyConstraintViolationException::class);
} finally {
    $database->rollBack();
}

Assert::null($database->table('Stitek')->get($tag->id));
Assert::null($database->table('Oblast')->get($area1->id));
