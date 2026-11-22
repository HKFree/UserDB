<?php

declare(strict_types=1);

namespace Test;

use App\Console\Wewimo2InfluxCommand;
use App\Model\AP;
use App\Model\IPAdresa;
use App\Model\Wewimo;
use App\Services\CryptoSluzba;
use App\Services\InfluxWriter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Tester\CommandTester;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

// Wewimo returning prepared devices per AP instead of asking RouterOS.
final class PreparedWewimo extends Wewimo
{
    /** @var list<int> */
    public array $calls = [];

    /** @param array<int, list<array<string, mixed>>> $devicesByAp */
    public function __construct(CryptoSluzba $crypto, IPAdresa $ip, AP $ap, private array $devicesByAp) {
        parent::__construct($crypto, $ip, $ap);
    }

    /**
     * @param int|string|null $apId
     * @return array{devices: list<array<string, mixed>>}
     */
    public function getWewimoFullData($apId, string $invokerStr, ?string $ip = null): array {
        Assert::same('SignalPlotter', $invokerStr);
        $this->calls[] = (int) $apId;
        return ['devices' => $this->devicesByAp[(int) $apId]];
    }
}

final class WewimoInfluxDatabaseTest extends DatabaseTestCase
{
    private const STATION = [
        'mac-address' => '02:00:00:00:00:01',
        'x-signal-strength' => '-65.5', 'signal-strength-ch0' => '-66', 'signal-strength-ch1' => '-67',
        'x-tx-signal-strength' => '-68', 'tx-signal-strength-ch0' => '-69', 'tx-signal-strength-ch1' => '-70',
        'x-signal-to-noise' => '25', 'rx-ccq' => '0', 'tx-ccq' => '99', 'x-rx-rate' => '123.5', 'x-tx-rate' => '456',
    ];
    private const STATION_POINT = 'station,macStationTag=02:00:00:00:00:01,interfaceMacTag=02:00:00:00:00:02,deviceIPTag=192.0.2.1 '
        . 'macField="02:00:00:00:00:01",rx_signal_strength=-65.5,rx_signal_strength_ch0=-66,rx_signal_strength_ch1=-67,'
        . 'tx_signal_strength=-68,tx_signal_strength_ch0=-69,tx_signal_strength_ch1=-70,signal_to_noise=25,rx_CCQ=0,tx_CCQ=99,rx_rate=123.5,tx_rate=456';

    /** @var \ArrayObject<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private \ArrayObject $history;

    public function testCommandKeepsFieldMappingSkipsMissingValuesAndContinuesAfterWriteFailure(): void {
        $area = $this->fixtures->area();
        $first = $this->fixtures->ap($area->id);
        $empty = $this->fixtures->ap($area->id);
        $last = $this->fixtures->ap($area->id);
        $stations = [self::STATION, ['mac-address' => '02:00:00:00:00:03', 'x-signal-strength' => null]];
        $devices = [$this->device(['mac-address' => '02:00:00:00:00:02', 'stations' => $stations])];
        $wewimo = $this->wewimo([$first->id => $devices, $empty->id => [], $last->id => $devices]);

        $output = $this->execute($wewimo, new Response(503), new Response(204), new Response(204));

        Assert::same([$first->id, $empty->id, $last->id], $wewimo->calls);
        Assert::same("Processing Wewimo data for AP id=$first->id ERROR: InfluxDB write failed: HTTP 503.\n"
            . "Processing Wewimo data for AP id=$empty->id 0 station MACs stored  DONE.\n"
            . "Processing Wewimo data for AP id=$last->id 2 station MACs stored  DONE.\n", $output);
        Assert::same([
            self::STATION_POINT,
            self::STATION_POINT,
            'station,macStationTag=02:00:00:00:00:03,interfaceMacTag=02:00:00:00:00:02,deviceIPTag=192.0.2.1 macField="02:00:00:00:00:03"',
        ], $this->writtenPoints());
    }

    public function testInvalidMacOfOneApIsReportedAndFollowingApsAreStillWritten(): void {
        $area = $this->fixtures->area();
        $unknownInterface = $this->fixtures->ap($area->id);
        $numericMac = $this->fixtures->ap($area->id);
        $valid = $this->fixtures->ap($area->id);
        $wewimo = $this->wewimo([
            // Wewimo::getWewimoData() creates such an interface for a station of an interface missing from the wireless table.
            $unknownInterface->id => [$this->device(['stations' => [self::STATION]])],
            $numericMac->id => [$this->device(['mac-address' => '02:00:00:00:00:02', 'stations' => [['mac-address' => 20000000001]]])],
            $valid->id => [$this->device(['mac-address' => '02:00:00:00:00:02', 'stations' => [self::STATION]])],
        ]);

        $output = $this->execute($wewimo, new Response(204));

        Assert::same([$unknownInterface->id, $numericMac->id, $valid->id], $wewimo->calls);
        Assert::same("Processing Wewimo data for AP id=$unknownInterface->id ERROR: InfluxDB tag \"interfaceMacTag\" must be a non-empty string, null given.\n"
            . "Processing Wewimo data for AP id=$numericMac->id ERROR: InfluxDB tag \"macStationTag\" must be a non-empty string, int given.\n"
            . "Processing Wewimo data for AP id=$valid->id 1 station MACs stored  DONE.\n", $output);
        Assert::same([self::STATION_POINT], $this->writtenPoints());
    }

    /**
     * @param array<string, mixed> $interface
     * @return array<string, mixed>
     */
    private function device(array $interface): array {
        return ['ip' => '192.0.2.1', 'interfaces' => ['wlan1' => $interface]];
    }

    /** @param array<int, list<array<string, mixed>>> $devicesByAp */
    private function wewimo(array $devicesByAp): PreparedWewimo {
        return new PreparedWewimo(
            $this->container->getByType(CryptoSluzba::class),
            $this->container->getByType(IPAdresa::class),
            $this->container->getByType(AP::class),
            $devicesByAp,
        );
    }

    /** Runs the command against InfluxDB answering with the given responses and returns what the command printed. */
    private function execute(Wewimo $wewimo, Response ...$responses): string {
        $this->history = new \ArrayObject();
        $history = $this->history;
        $stack = HandlerStack::create(new MockHandler(array_values($responses)));
        $stack->push(Middleware::history($history));
        $writer = new InfluxWriter('influxdb://localhost:8086/wewimo', new Client(['handler' => $stack]));
        $command = new Wewimo2InfluxCommand($writer, $this->container->getByType(AP::class), $wewimo);
        ob_start();
        try {
            Assert::same(0, (new CommandTester($command))->execute([]));
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** @return list<string> */
    private function writtenPoints(): array {
        $points = [];
        foreach ($this->history as $transaction) {
            $points[] = (string) $transaction['request']->getBody();
        }
        return $points;
    }
}

(new WewimoInfluxDatabaseTest())->run();
