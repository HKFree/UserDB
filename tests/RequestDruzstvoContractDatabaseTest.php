<?php

declare(strict_types=1);

namespace App\Services {
    /**
     * Intercept only this service's subprocess; never launch the real console worker.
     * @param array<array-key, mixed> $descriptors
     * @param array<array-key, mixed>|null $pipes
     * @param-out array{} $pipes
     * @return resource|false
     */
    function proc_open(string $command, array $descriptors, ?array &$pipes) {
        \Test\RequestDruzstvoContractDatabaseTest::$commands[] = $command;
        $pipes = [];
        return fopen('php://memory', 'r+');
    }

    /** @param resource $process */
    function proc_close($process): int {
        fclose($process);
        return 0;
    }
}

namespace Test {
    use App\Model\Log;
    use App\Services\RequestDruzstvoContract;
    use Nette\Security\SimpleIdentity;
    use Nette\Security\User;
    use Tester\Assert;

    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/ContractAuditTestHelpers.php';

    final class RequestDruzstvoContractDatabaseTest extends DatabaseTestCase
    {
        /** @var list<string> */
        public static array $commands = [];

        protected function setUp(): void {
            parent::setUp();
            self::$commands = [];
        }

        public function testNewContractIsLoggedUnderMemberWithRequestingAuthor(): void {
            $ap = $this->fixtures->ap($this->fixtures->area()->id);
            $author = $this->fixtures->member($ap->id);
            $member = $this->fixtures->member($ap->id);
            $this->container->getByType(User::class)->login(new SimpleIdentity($author->id));
            $service = new RequestDruzstvoContract($this->database->getConnection(), $this->container->getByType(Log::class));
            $id = $service->execute($member->id);
            $contract = $this->requireRow($this->database->table('Smlouva')->get($id));
            Assert::same($member->id, $contract->uzivatel_id);
            Assert::same('ucastnicka', $contract->typ);
            ContractAuditTestHelpers::assertEntries($this->database, $contract, $author->id, [
                'typ' => [null, 'ucastnicka', 'I'],
                'kdy_vygenerovano' => [null, $contract->kdy_vygenerovano->format('Y-m-d H:i:s'), 'I'],
            ]);
            Assert::same(2, $this->database->table('Log')->count('*'));
            Assert::count(1, self::$commands);
            Assert::contains('app:digisign_generovat_ucastnickou_smlouvu ' . $id . ' |', self::$commands[0]);
        }

        public function testExplicitAuthorOverridesLoggedInUser(): void {
            $ap = $this->fixtures->ap($this->fixtures->area()->id);
            $admin = $this->fixtures->member($ap->id);
            $member = $this->fixtures->member($ap->id);
            $this->container->getByType(User::class)->login(new SimpleIdentity($admin->id));
            $service = new RequestDruzstvoContract($this->database->getConnection(), $this->container->getByType(Log::class));
            $id = $service->execute($member->id, $member->id);
            $contract = $this->requireRow($this->database->table('Smlouva')->get($id));
            ContractAuditTestHelpers::assertEntries($this->database, $contract, $member->id, [
                'typ' => [null, 'ucastnicka', 'I'],
                'kdy_vygenerovano' => [null, $contract->kdy_vygenerovano->format('Y-m-d H:i:s'), 'I'],
            ]);
        }
    }

    (new RequestDruzstvoContractDatabaseTest())->run();
}
