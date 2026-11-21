<?php

declare(strict_types=1);

namespace Test;

use App\Console\UpdateLocationsCommand;
use App\Model\Uzivatel;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class FixtureGeocodingCommand extends UpdateLocationsCommand
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(Uzivatel $users, private mixed $score) {
        parent::__construct('test-only', $users);
    }

    protected function googleMapsGeocode(string $adresa): ResponseInterface {
        $this->calls[] = 'google';
        return new Response(200, [], '{"status":"OK","results":[{"geometry":{"location":{"lat":50,"lng":15},"location_type":"APPROXIMATE"}}]}');
    }

    protected function vugtkGeocode(string $adresa): ResponseInterface {
        $this->calls[] = 'vugtk';
        return new Response(200, [], json_encode(['locations' => [['feature' => [
            'attributes' => ['Score' => $this->score], 'geometry' => ['x' => -650000, 'y' => -1050000],
        ]]]], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}

final class GeocodingDatabaseTest extends DatabaseTestCase
{
    /** @return list<array{mixed, string}> */
    public function scoreCases(): array {
        return [[true, 'approx'], [100, 'valid'], [100.0, 'valid'], ['100.0', 'valid'], [99.9, 'approx'], ['100foo', 'approx'], [null, 'approx']];
    }

    /** @dataProvider scoreCases */
    public function testOnlyNumericFullScoreReplacesApproximateCoordinates(mixed $score, string $status): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id, ['location_status' => 'pending']);
        $command = new FixtureGeocodingCommand($this->container->getByType(Uzivatel::class), $score);
        ob_start();
        try {
            Assert::same(0, (new CommandTester($command))->execute([]));
        } finally {
            ob_end_clean();
        }

        Assert::same(['google', 'vugtk'], $command->calls);
        $updated = $this->database->table('Uzivatel')->get($member->id);
        Assert::true($updated !== null);
        Assert::same($status, $updated->location_status);
        if ($status === 'approx') {
            Assert::same(50.0, (float) $updated->latitude);
            Assert::same(15.0, (float) $updated->longitude);
        } else {
            Assert::notSame(50.0, (float) $updated->latitude);
            Assert::notSame(15.0, (float) $updated->longitude);
        }
    }
}

(new GeocodingDatabaseTest())->run();
