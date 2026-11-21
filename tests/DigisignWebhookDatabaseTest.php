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

    public function __construct(array $options) {
        Assert::same(['access_key' => 'test-only', 'secret_key' => 'test-only'], $options);
    }

    public function envelopes(): WebhookEnvelopes {
        return self::$envelopes;
    }
}

final class WebhookEnvelopes
{
    /** @var list<string> */
    public array $calls = [];
    public ?string $savedPath = null;

    public function get(string $id): \stdClass {
        $this->calls[] = 'get:' . $id;
        return (object) ['id' => $id, 'status' => 'completed', 'documents' => [(object) ['name' => 'fixture.pdf']]];
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
        Assert::type('int', $updated->id);
        Assert::same($preserved ? $originalMembership : 1, $updated->TypClenstvi_id);
        Assert::same(1, $updated->druzstvo);
        Assert::same('2026-01-01 12:00:00', $this->database->table('PodpisSmlouvy')->get($signature->id)->kdy_podepsano->format('Y-m-d H:i:s'));
        Assert::same(['get:' . $contract->externi_id, 'download:' . $contract->externi_id], WebhookDigiSign::$envelopes->calls);
        Assert::same(getenv('FILE_STORAGE_PATH') . '/ucastnickeSmlouvy/fixture.pdf', WebhookDigiSign::$envelopes->savedPath);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }
}

(new DigisignWebhookDatabaseTest())->run();
