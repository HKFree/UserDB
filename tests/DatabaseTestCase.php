<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Explorer;
use Nette\DI\Container;
use Tester\Assert;
use Tester\TestCase;

/**
 * Require bootstrap.php and extend this class for transactional model/presenter tests.
 * Use $fixtures for rows, $container for models, and $presenters->ui()/api() for requests.
 * Override setUp/tearDown only with parent calls; rollback also verifies fixture removal.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Explorer $database;
    protected Container $container;
    protected Fixtures $fixtures;
    protected PresenterRunner $presenters;

    protected function setUp(): void {
        $this->database = TestEnvironment::database();
        if ($this->database->getConnection()->getPdo()->inTransaction()) {
            $this->database->rollBack();
        }
        $this->container = TestEnvironment::container($this->database);
        $this->fixtures = new Fixtures($this->database);
        $this->presenters = new PresenterRunner($this->database, $this->container);
        $this->database->beginTransaction();
    }

    protected function tearDown(): void {
        if (!$this->database->getConnection()->getPdo()->inTransaction()) {
            TestEnvironment::invalidateSchema();
            Assert::fail('The test committed implicitly; the test database will be rebuilt on the next run.');
        }
        $this->database->rollBack();
        $this->fixtures->assertRolledBack();
    }
}
