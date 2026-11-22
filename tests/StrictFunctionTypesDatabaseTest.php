<?php

declare(strict_types=1);

namespace Test;

use App\Model\CestneClenstviUzivatele;
use App\Model\SpravceOblasti;
use App\Model\Uzivatel;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class StrictFunctionTypesDatabaseTest extends DatabaseTestCase
{
    public function testRoleLabelsKeepEmptyAndSpecifiedArea(): void {
        $model = $this->container->getByType(SpravceOblasti::class);
        foreach ([null, 0, '0', ''] as $area) {
            Assert::same('VV', $model->getTypPravaPopisek('VV', $area));
        }
        Assert::same('SO-42', $model->getTypPravaPopisek('SO', 42));
        Assert::same('SO-0042', $model->getTypPravaPopisek('SO', '0042'));
    }

    public function testDatabaseIdsHaveTheSameTypeInSelectionsPairsAndArrayKeys(): void {
        $area = $this->fixtures->area();
        $otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($otherAp->id);
        $this->fixtures->role($member->id, 'SO', $area->id);
        $cc = $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $member->id, 'plati_od' => '2000-01-01', 'schvaleno' => 1,
            'TypCestnehoClenstvi_id' => $this->requireRow($this->database->table('TypCestnehoClenstvi')->fetch())->id,
            'zadost_podal' => $member->id,
        ]);

        $id = $this->requireRow($this->container->getByType(Uzivatel::class)->getUzivatel((string) $member->id))->id;
        Assert::type('int', $id);
        Assert::same([$cc->id => $id], $this->container->getByType(CestneClenstviUzivatele::class)->getListCC());
        Assert::same([$cc->id => $id], $this->container->getByType(CestneClenstviUzivatele::class)->getListCCOfAP($otherAp->id));
        Assert::same([$id], array_keys($this->container->getByType(SpravceOblasti::class)->getSO()->fetchPairs('Uzivatel_id', 'Uzivatel_id')));
    }
}

(new StrictFunctionTypesDatabaseTest())->run();
