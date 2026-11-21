<?php

declare(strict_types=1);

namespace Test;

use App\Model\Stitek;
use App\Model\StitekUzivatele;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class StitekUzivateleDatabaseTest extends DatabaseTestCase
{
    public function testAssignmentRemovalAndAuditLog(): void {
        $area = $this->fixtures->area();
        $member = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        $tags = $this->container->getByType(Stitek::class);
        $assignments = $this->container->getByType(StitekUzivatele::class);
        $tag = $tags->createStitek(['Oblast_id' => $area->id, 'text' => 'Testovací štítek']);

        Assert::same([], $assignments->getStitekByUserId($member->id));
        Assert::null($assignments->uzivatelHasStitek($member->id, $tag->id));

        $assignments->createStitekUzivatele([
            'Uzivatel_id' => $member->id,
            'Stitek_id' => $tag->id,
            'kdy_vytvoreno' => '2026-01-02 03:04:05',
        ]);
        $assignment = $assignments->uzivatelHasStitek($member->id, $tag->id);
        Assert::same($member->id, $assignment->Uzivatel_id);
        Assert::same($tag->id, $assignment->Stitek_id);
        $assigned = $assignments->getStitekByUserId($member->id);
        Assert::count(1, $assigned);
        Assert::same($tag->id, $assigned[0]->id);
        Assert::same('Testovací štítek', $assigned[0]->text);

        Assert::same('2026-01-02 03:04:05', $assignments->getStitekByUserId($member->id)[0]->kdy_vytvoreno->format('Y-m-d H:i:s'));

        Assert::same(1, $assignments->odstranStitek($tag->id, $member->id));
        Assert::null($assignments->uzivatelHasStitek($member->id, $tag->id));
        Assert::same([], $assignments->getStitekByUserId($member->id));
        $log = $this->database->table('Log')->where('tabulka', 'Uzivatel')->where('tabulka_id', $member->id)->order('id')->fetchAll();
        Assert::same([
            [$member->id, 'Uzivatel.stitek', null, 'Testovací štítek', 'I'],
            [$member->id, 'Uzivatel.stitek', 'Testovací štítek', null, 'D'],
        ], array_values(array_map(
            fn (ActiveRow $row): array => [$row->Uzivatel_id, $row->sloupec, $row->puvodni_hodnota, $row->nova_hodnota, $row->akce],
            $log,
        )));
    }
}

(new StitekUzivateleDatabaseTest())->run();
