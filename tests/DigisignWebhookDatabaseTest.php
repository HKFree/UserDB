<?php

declare(strict_types=1);

namespace Test;

use Nette\DI\Container;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

// The standalone entry point constructs both dependencies itself. Replace them before autoloading it.
final class WebhookBootstrap
{
    public static Container $container;

    public function boot(): Container {
        return self::$container;
    }
}

final class WebhookDigiSign
{
    public static WebhookEnvelopes $envelopes;

    /** @param array{access_key: string, secret_key: string} $options */
    public function __construct(array $options) {
        Assert::same(['access_key' => 'test-only', 'secret_key' => 'test-only'], $options);
    }

    public function envelopes(): WebhookEnvelopes {
        return self::$envelopes;
    }

    public function envelopeTemplates(): WebhookTemplates {
        return new WebhookTemplates();
    }
}

final class StopBeforeCreatingEnvelope extends \RuntimeException
{
}

final class WebhookTemplates
{
    public function get(string $id): \stdClass {
        return (object) ['id' => $id, 'title' => 'Testovací šablona'];
    }

    public function use(string $id): never {
        throw new StopBeforeCreatingEnvelope('Cancellation tested; do not create an envelope.');
    }
}

final class WebhookTags
{
    /** @return list<array{recipientClaim: string, value: string}> */
    public function toArray(): array {
        return [['recipientClaim' => 'birthdate', 'value' => 'invalid-date']];
    }
}

final class WebhookRecipients
{
    public function get(string $id): \stdClass {
        return (object) ['tags' => new WebhookTags()];
    }
}

final class WebhookEnvelopes
{
    /** @var list<string> */
    public array $calls = [];
    public ?string $savedPath = null;
    public string $status = 'completed';
    public ?string $declineReason = null;
    public bool $withRecipient = true;
    public bool $withDocument = true;
    public ?string $sentAt = '2026-01-01 12:00:00';
    public ?string $declinedAt = '2026-01-01 12:00:00';
    public ?string $expiredAt = null;

    public function get(string $id): \DigitalCz\DigiSign\Resource\Envelope {
        $this->calls[] = 'get:' . $id;
        return new \DigitalCz\DigiSign\Resource\Envelope([
            'id' => $id, 'status' => $this->status, 'expiredAt' => $this->expiredAt,
            'documents' => $this->withDocument ? [['name' => 'fixture.pdf']] : [],
            'recipients' => $this->withRecipient ? [[
                'id' => 'fixture-recipient', 'email' => 'recipient@example.invalid',
                'sentAt' => $this->sentAt, 'declinedAt' => $this->declinedAt,
                'declineReason' => $this->declineReason,
            ]] : [],
        ]);
    }

    public function recipients(\DigitalCz\DigiSign\Resource\Envelope $envelope): WebhookRecipients {
        return new WebhookRecipients();
    }

    public function discard(string $id): void {
        $this->calls[] = 'discard:' . $id;
    }

    public function download(string $id): self {
        $this->calls[] = 'download:' . $id;
        return $this;
    }

    public function save(string $path): void {
        $this->savedPath = $path;
    }
}

Assert::false(class_exists(\App\Bootstrap::class, false));
Assert::false(class_exists(\DigitalCz\DigiSign\DigiSign::class, false));
class_alias(WebhookBootstrap::class, \App\Bootstrap::class);
class_alias(WebhookDigiSign::class, \DigitalCz\DigiSign\DigiSign::class);

final class DigisignWebhookDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->container = TestEnvironment::container($this->database, new \Nette\Http\Request(new \Nette\Http\UrlScript('http://localhost/'), remoteAddress: '192.0.2.1'));
        WebhookBootstrap::$container = $this->container;
        WebhookDigiSign::$envelopes = new WebhookEnvelopes();
        require_once __DIR__ . '/../www/digisign-webhook/WebhookReceiver.php';
        $GLOBALS['container'] = $this->container;
    }

    public function testOtherEntityNamesDoNotProcessEnvelope(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id, ['druzstvo' => 0]);
        $contract = $this->fixtures->insert('Smlouva', ['uzivatel_id' => $member->id, 'externi_id' => 'fixture-envelope', 'typ' => 'ucastnicka']);
        foreach ([true, false, 0, null, '', 'recipient'] as $entityName) {
            ob_start();
            try {
                \process_digisign_webhook((object) ['entityName' => $entityName, 'entityId' => $contract->externi_id, 'event' => 'recipientSent']);
            } finally {
                ob_end_clean();
            }
            Assert::same([], WebhookDigiSign::$envelopes->calls);
            Assert::same(0, $this->requireRow($this->database->table('Uzivatel')->get($member->id))->druzstvo);
        }
    }

    /** @return list<array{string, string, string}> */
    public function missingEnvelopeData(): array {
        return [
            ['Sent', 'recipient', 'DigiSign nevrátil příjemce obálky.'],
            ['Sent', 'sentAt', 'DigiSign nevrátil čas odeslání obálky.'],
            ['Completed', 'document', 'DigiSign nevrátil podepsaný dokument.'],
            ['Completed', 'recipient', 'DigiSign nevrátil příjemce obálky.'],
            ['Completed', 'signature', 'Podpis účastníka nebyl nalezen.'],
            ['Completed', 'member', 'Člen smlouvy nebyl nalezen.'],
            ['Declined', 'recipient', 'DigiSign nevrátil příjemce obálky.'],
            ['Declined', 'declinedAt', 'DigiSign nevrátil čas odmítnutí obálky.'],
            ['Declined', 'signature', 'Podpis účastníka nebyl nalezen.'],
        ];
    }

    /** @dataProvider missingEnvelopeData */
    public function testIncompleteEnvelopeDoesNotChangeRecordsOrDownload(string $event, string $missing, string $message): void {
        [$member, $contract, $signature] = $this->contractFixture('Old note');
        $member->update(['datum_narozeni' => null, 'druzstvo' => 0]);
        $api = WebhookDigiSign::$envelopes;
        $api->status = strtolower($event);
        $api->declineReason = 'Test reason';
        if ($missing === 'recipient') {
            $api->withRecipient = false;
        } elseif ($missing === 'document') {
            $api->withDocument = false;
        } elseif ($missing === 'sentAt') {
            $api->sentAt = null;
        } elseif ($missing === 'declinedAt') {
            $api->declinedAt = null;
        } elseif ($missing === 'signature') {
            $signature->delete();
        } elseif ($missing === 'member') {
            $model = new class () extends \App\Model\Uzivatel {
                public function __construct() {
                    parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
                }

                public function find($id): ?\Nette\Database\Table\ActiveRow {
                    return null;
                }
            };
            foreach ($this->container->findByType(\App\Model\Uzivatel::class) as $name) {
                $this->container->removeService($name);
                $this->container->addService($name, $model);
            }
        }
        $before = $this->recordSnapshot();
        Assert::exception(fn () => $this->processHook($contract, 'envelope' . $event), \RuntimeException::class, $message);
        Assert::same($before, $this->recordSnapshot());
        Assert::same(['get:' . $contract->externi_id], $api->calls);
        Assert::null($api->savedPath);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testExpiryUsesEventTimeWithoutRecipient(): void {
        [, $contract] = $this->contractFixture(null);
        WebhookDigiSign::$envelopes->status = 'expired';
        WebhookDigiSign::$envelopes->withRecipient = false;
        $this->processHook($contract, 'envelopeExpired');
        Assert::same('VYPRŠEL ČAS na podpis 01.01.2026 12:00', $this->requireRow($this->database->table('Smlouva')->get($contract->id))->poznamka);
        $contract->update(['poznamka' => null]);
        WebhookDigiSign::$envelopes->expiredAt = '2026-01-01 13:15:00';
        $this->processHook($contract, 'envelopeExpired');
        Assert::same('VYPRŠEL ČAS na podpis 01.01.2026 13:15', $this->requireRow($this->database->table('Smlouva')->get($contract->id))->poznamka);
    }

    public function testInvalidEventTimesDoNotChangeRecords(): void {
        foreach (['Completed', 'Declined', 'Expired'] as $event) {
            [, $contract] = $this->contractFixture('Old note');
            WebhookDigiSign::$envelopes->status = strtolower($event);
            foreach ([null, '', 'now', '2026-02-30 12:00:00', '2026-01-01 99:00:00'] as $time) {
                $before = $this->recordSnapshot();
                Assert::exception(fn () => $this->processHook($contract, 'envelope' . $event, $time), \RuntimeException::class, 'Čas události DigiSign chybí nebo není platný.');
                Assert::same($before, $this->recordSnapshot());
                Assert::null(WebhookDigiSign::$envelopes->savedPath);
            }
        }
    }

    public function testEventTimeKeepsExplicitTimezone(): void {
        foreach (['2026-01-01T12:00:00+01:00', '2026-01-01T11:00:00Z', '2026-01-01T11:00:00.000000Z'] as $time) {
            Assert::same((new \DateTimeImmutable('2026-01-01 12:00:00 Europe/Prague'))->getTimestamp(), \digisign_webhook_event_time($time)->getTimestamp());
        }
    }

    public function testSignatureStoresEventTimeInApplicationTimezone(): void {
        [, $contract, $signature] = $this->contractFixture(null);
        $this->processHook($contract, 'envelopeCompleted', '2026-01-01T11:00:00Z');
        Assert::same('2026-01-01 12:00:00', $this->requireRow($this->database->table('PodpisSmlouvy')->get($signature->id))->kdy_podepsano->format('Y-m-d H:i:s'));
    }

    private function recordSnapshot(): string {
        $rows = [];
        foreach (['Uzivatel', 'Smlouva', 'PodpisSmlouvy'] as $table) {
            foreach ($this->database->table($table)->order('id') as $row) {
                $rows[$table][] = $row->toArray();
            }
        }
        return serialize($rows);
    }

    /** @return list<array{string|null, bool}> */
    public function membershipCases(): array {
        return [[null, false], ['SO', true], ['ZSO', true], ['expired', false]];
    }

    /** @dataProvider membershipCases */
    public function testCompletedEnvelopePreservesManagerMembership(?string $role, bool $preserved): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id, ['spolek' => 1, 'druzstvo' => 0, 'datum_narozeni' => '1980-01-01']);
        $originalMembership = $member->TypClenstvi_id;
        if ($role !== null) {
            $this->fixtures->role($member->id, $role === 'expired' ? 'SO' : $role, $area->id, until: $role === 'expired' ? '2001-01-01' : '2099-12-31');
        }
        $contract = $this->fixtures->insert('Smlouva', [
            'uzivatel_id' => $member->id, 'externi_id' => '00000000-0000-4000-8000-000000000001', 'typ' => 'ucastnicka',
        ]);
        $signature = $this->fixtures->insert('PodpisSmlouvy', ['smlouva_id' => $contract->id, 'smluvni_strana' => 'ucastnik', 'jmeno' => 'Fixture']);
        $hook = (object) ['entityName' => 'envelope', 'entityId' => $contract->externi_id, 'event' => 'envelopeCompleted', 'time' => '2026-01-01 12:00:00'];
        ob_start();
        try {
            \process_digisign_webhook($hook);
        } finally {
            ob_end_clean();
        }

        $updated = $this->database->table('Uzivatel')->get($member->id);
        Assert::true($updated !== null);
        Assert::type('int', $updated->id);
        Assert::same($preserved ? $originalMembership : 1, $updated->TypClenstvi_id);
        Assert::same(1, $updated->druzstvo);
        Assert::same('2026-01-01 12:00:00', $this->requireRow($this->database->table('PodpisSmlouvy')->get($signature->id))->kdy_podepsano->format('Y-m-d H:i:s'));
        Assert::same(['get:' . $contract->externi_id, 'download:' . $contract->externi_id], WebhookDigiSign::$envelopes->calls);
        Assert::same(getenv('FILE_STORAGE_PATH') . '/ucastnickeSmlouvy/fixture.pdf', WebhookDigiSign::$envelopes->savedPath);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }
    /** @return list<array{?string, string, string}> */
    public function noteCases(): array {
        return [[null, '', ''], ['', '', ''], ['0', '0', '0'], ['Old note', "Old note\n", "Old note\n\n"]];
    }

    /** @dataProvider noteCases */
    public function testEnvelopeNotes(?string $note, string $prefix, string $memberPrefix): void {
        foreach ([
            'Sent' => 'Smlouva odeslána 01.01.2026 12:00 na adresu recipient@example.invalid.',
            'Declined' => 'Smlouva ODMÍTNUTA 01.01.2026 12:00 s důvodem: Test reason',
            'Expired' => 'VYPRŠEL ČAS na podpis 01.01.2026 12:00',
        ] as $event => $expected) {
            [, $contract] = $this->contractFixture($note);
            WebhookDigiSign::$envelopes->status = strtolower($event);
            WebhookDigiSign::$envelopes->declineReason = 'Test reason';
            $this->processHook($contract, 'envelope' . $event);
            Assert::same($prefix . $expected, $this->requireRow($this->database->table('Smlouva')->get($contract->id))->poznamka);
        }
    }

    /** @return list<array{?string}> */
    public function emptyReasons(): array {
        return [[null], [''], ['0']];
    }

    /** @dataProvider emptyReasons */
    public function testEmptyDeclineReasonKeepsNote(?string $reason): void {
        [, $contract, $signature] = $this->contractFixture('Old note');
        WebhookDigiSign::$envelopes->status = 'declined';
        WebhookDigiSign::$envelopes->declineReason = $reason;
        $this->processHook($contract, 'envelopeDeclined');
        Assert::same('Old note', $this->requireRow($this->database->table('Smlouva')->get($contract->id))->poznamka);
        Assert::same('2026-01-01 12:00:00', $this->requireRow($this->database->table('PodpisSmlouvy')->get($signature->id))->kdy_odmitnuto->format('Y-m-d H:i:s'));
    }

    /** @dataProvider noteCases */
    public function testUnclearBirthdateAppendsMemberNote(?string $note, string $prefix, string $memberPrefix): void {
        [$member, $contract] = $this->contractFixture(null);
        $member->update(['poznamka' => $note, 'datum_narozeni' => null]);
        $this->processHook($contract, 'envelopeCompleted');
        Assert::same(
            $memberPrefix . 'Nejasné datum narození [invalid-date] (z digisign smlouvy #' . $contract->id . ' ze dne 01.01.2026)',
            $this->requireRow($this->database->table('Uzivatel')->get($member->id))->poznamka
        );
    }

    /** @dataProvider noteCases */
    public function testReplacingUnsignedContractKeepsNoteSeparator(?string $note, string $prefix, string $memberPrefix): void {
        [$member, $old] = $this->contractFixture($note);
        $new = $this->fixtures->insert('Smlouva', ['uzivatel_id' => $member->id, 'typ' => 'ucastnicka']);
        $command = new \App\Console\DigisignGenerovatUcastnickouSmlouvu(
            $this->container->getByType(\App\Model\Uzivatel::class),
            $this->container->getByType(\App\Model\Smlouva::class),
            $this->container->getByType(\App\Model\PodpisSmlouvy::class),
        );
        ob_start();
        try {
            Assert::exception(
                fn () => $command->run(new \Symfony\Component\Console\Input\ArrayInput(['smlouva_id' => (string) $new->id]), new \Symfony\Component\Console\Output\BufferedOutput()),
                StopBeforeCreatingEnvelope::class,
                'Cancellation tested; do not create an envelope.'
            );
        } finally {
            ob_end_clean();
        }
        $updated = $this->database->table('Smlouva')->get($old->id);
        Assert::true($updated !== null);
        Assert::match($prefix . 'Smlouva zrušena před podepsáním a nahrazena smlouvou č. ' . $new->id . ' (ze dne %d%.%d%.%d% %d%:%d%)', $updated->poznamka);
        Assert::notNull($updated->kdy_ukonceno);
        Assert::same(['discard:' . $old->externi_id], WebhookDigiSign::$envelopes->calls);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** @return array{\Nette\Database\Table\ActiveRow, \Nette\Database\Table\ActiveRow, \Nette\Database\Table\ActiveRow} */
    private function contractFixture(?string $note): array {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id, ['spolek' => 0, 'datum_narozeni' => '1980-01-01']);
        $contract = $this->fixtures->insert('Smlouva', [
            'uzivatel_id' => $member->id, 'externi_id' => $this->fixtures->name('envelope'), 'typ' => 'ucastnicka', 'poznamka' => $note,
        ]);
        $signature = $this->fixtures->insert('PodpisSmlouvy', ['smlouva_id' => $contract->id, 'smluvni_strana' => 'ucastnik', 'jmeno' => 'Fixture']);
        return [$member, $contract, $signature];
    }

    private function processHook(\Nette\Database\Table\ActiveRow $contract, string $event, ?string $time = '2026-01-01 12:00:00'): void {
        ob_start();
        try {
            \process_digisign_webhook((object) [
                'entityName' => 'envelope', 'entityId' => $contract->externi_id, 'event' => $event, 'time' => $time,
            ]);
        } finally {
            ob_end_clean();
        }
    }
}

(new DigisignWebhookDatabaseTest())->run();
