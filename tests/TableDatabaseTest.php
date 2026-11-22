<?php

declare(strict_types=1);

namespace Test;

use App\Model\Oblast;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class TableDatabaseTest extends DatabaseTestCase
{
    public function testDatabaseConnectionIsReused(): void {
        $database = TestEnvironment::database();
        Assert::same($this->database, $database);
        Assert::same($database, TestEnvironment::database());
    }

    public function testFindByIntegerAndStringId(): void {
        $area = $this->fixtures->area();
        $model = $this->container->getByType(Oblast::class);

        foreach ([$area->id, (string) $area->id] as $id) {
            $found = $model->find($id);
            Assert::true($found instanceof ActiveRow);
            Assert::same($area->id, $found->id);
            Assert::same($area->jmeno, $found->jmeno);
        }
    }

    public function testMissingRowsReturnNull(): void {
        $area = $this->fixtures->area();
        $model = $this->container->getByType(Oblast::class);
        Assert::same(1, $model->delete(['id' => $area->id]));

        Assert::null($model->find($area->id));
        Assert::null($model->findOneBy(['id' => $area->id]));
        Assert::same([], $model->findBy(['id' => $area->id])->fetchAll());
    }

    public function testInsertAndUpdateFormValues(): void {
        $model = $this->container->getByType(Oblast::class);
        $name = $this->fixtures->name('Table');
        $inserted = $model->insert(ArrayHash::from(['jmeno' => $name, 'datum_zalozeni' => '2000-01-01 00:00:00']));
        Assert::type(ActiveRow::class, $inserted);
        if (!$inserted instanceof ActiveRow) {
            throw new \LogicException('An area insert must return its row.');
        }
        Assert::same($name, $inserted->jmeno);

        $updated = $model->update((string) $inserted->id, ArrayHash::from(['jmeno' => $name . '-updated']));
        Assert::same($inserted->id, $updated->id);
        Assert::same($name . '-updated', $this->requireRow($this->database->table('Oblast')->get($inserted->id))->jmeno);
        Assert::same(1, $model->delete(['id' => $inserted->id]));
        Assert::null($this->database->table('Oblast')->get($inserted->id));
    }
}

(new TableDatabaseTest())->run();
