<?php

declare(strict_types=1);

namespace Test;

use App\Console\DigisignGenerovatUcastnickouSmlouvu;
use App\Model\PodpisSmlouvy;
use App\Model\Smlouva;
use App\Model\Uzivatel;
use DigitalCz\DigiSign\Resource\Envelope;
use Nette\Database\Table\ActiveRow;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class WorkerReachedPdfGeneration extends \RuntimeException
{
}

final class WorkerPdfGenerator
{
    /** @var list<string> */
    public static array $calls = [];

    /** @return array<string, string> */
    public static function parametryUcastnickeSmlouvy(ActiveRow $member, int|string $contract): array {
        self::$calls[] = 'parameters';
        return ['jmeno_prijmeni' => 'Test Member', 'email' => 'test@example.invalid', 'adresa' => 'Testovací 1'];
    }

    public static function nahledUcastnickeSmlouvy(ActiveRow $member, int|string $contract): never {
        self::$calls[] = 'pdf';
        throw new WorkerReachedPdfGeneration('Test stops before generating or uploading a PDF.');
    }
}

final class WorkerDigiSign
{
    /** @var list<string> */
    public static array $calls = [];
    public static bool $withRecipient = true;

    /** @param array{access_key: string, secret_key: string} $options */
    public function __construct(array $options) {
        Assert::same(['access_key' => 'test-only', 'secret_key' => 'test-only'], $options);
        self::$calls[] = 'client';
    }

    public function envelopes(): WorkerEnvelopes {
        return new WorkerEnvelopes();
    }

    public function envelopeTemplates(): WorkerTemplates {
        return new WorkerTemplates();
    }
}

final class WorkerTemplates
{
    public function get(string $id): \stdClass {
        WorkerDigiSign::$calls[] = 'template';
        return (object) ['id' => $id, 'title' => 'Testovací šablona'];
    }

    public function use(string $id): Envelope {
        WorkerDigiSign::$calls[] = 'create';
        return new Envelope(['id' => 'fixture-envelope']);
    }
}

final class WorkerEnvelopes
{
    public function get(string $id): Envelope {
        WorkerDigiSign::$calls[] = 'get';
        return new Envelope(['id' => $id, 'recipients' => WorkerDigiSign::$withRecipient ? [['id' => 'fixture-recipient']] : []]);
    }
}

Assert::false(class_exists(\DigitalCz\DigiSign\DigiSign::class, false));
Assert::false(class_exists(\App\Services\GeneratorSmlouvy::class, false));
class_alias(WorkerDigiSign::class, \DigitalCz\DigiSign\DigiSign::class);
class_alias(WorkerPdfGenerator::class, \App\Services\GeneratorSmlouvy::class);

final class DigisignWorkerDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        WorkerDigiSign::$calls = [];
        WorkerDigiSign::$withRecipient = true;
        WorkerPdfGenerator::$calls = [];
    }

    public function testMissingMemberDoesNotWriteOrCallExternalServices(): void {
        $contract = $this->contract();
        $before = serialize($contract->toArray());
        $members = new class () extends Uzivatel {
            public function __construct() {
                parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
            }

            public function find($id): ?ActiveRow {
                return null;
            }
        };
        Assert::exception(fn () => $this->runCommand($contract, $members), \RuntimeException::class, 'Člen smlouvy nebyl nalezen.');
        Assert::same($before, serialize($this->requireRow($this->database->table('Smlouva')->get($contract->id))->toArray()));
        Assert::same([], WorkerDigiSign::$calls);
        Assert::same([], WorkerPdfGenerator::$calls);
        Assert::same(0, $this->database->table('PodpisSmlouvy')->count('*'));
    }

    public function testMissingRecipientStopsBeforePdfAndSignatures(): void {
        $contract = $this->contract();
        WorkerDigiSign::$withRecipient = false;
        Assert::exception(fn () => $this->runCommand($contract), \RuntimeException::class, 'DigiSign nevrátil příjemce obálky.');
        Assert::same(['client', 'template', 'create', 'get'], WorkerDigiSign::$calls);
        Assert::same(['parameters'], WorkerPdfGenerator::$calls);
        Assert::same(0, $this->database->table('PodpisSmlouvy')->count('*'));
        Assert::null($this->requireRow($this->database->table('Smlouva')->get($contract->id))->podepsany_dokument_path);
    }

    public function testExistingRecipientContinuesToPdfGeneration(): void {
        Assert::exception(fn () => $this->runCommand($this->contract()), WorkerReachedPdfGeneration::class);
        Assert::same(['client', 'template', 'create', 'get'], WorkerDigiSign::$calls);
        Assert::same(['parameters', 'pdf'], WorkerPdfGenerator::$calls);
    }

    private function contract(): ActiveRow {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        return $this->fixtures->insert('Smlouva', ['uzivatel_id' => $member->id, 'typ' => 'ucastnicka']);
    }

    private function runCommand(ActiveRow $contract, ?Uzivatel $members = null): int {
        $command = new DigisignGenerovatUcastnickouSmlouvu(
            $members ?? $this->container->getByType(Uzivatel::class),
            $this->container->getByType(Smlouva::class),
            $this->container->getByType(PodpisSmlouvy::class),
        );
        ob_start();
        try {
            return $command->run(new ArrayInput(['smlouva_id' => (string) $contract->id]), new BufferedOutput());
        } finally {
            ob_end_clean();
        }
    }
}

(new DigisignWorkerDatabaseTest())->run();
