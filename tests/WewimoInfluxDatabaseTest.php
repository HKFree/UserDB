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

final class WewimoInfluxDatabaseTest extends DatabaseTestCase
{
    public function testCommandKeepsFieldMappingSkipsMissingValuesAndContinuesAfterWriteFailure(): void {
        $area = $this->fixtures->area();
        $first = $this->fixtures->ap($area->id);
        $empty = $this->fixtures->ap($area->id);
        $last = $this->fixtures->ap($area->id);
        $ap = $this->container->getByType(AP::class);
        $wewimo = new class ($this->container->getByType(CryptoSluzba::class), $this->container->getByType(IPAdresa::class), $ap, $empty->id) extends Wewimo {
            /** @var list<int> */
            public array $calls = [];

            public function __construct(CryptoSluzba $crypto, IPAdresa $ip, AP $ap, private int $emptyId) {
                parent::__construct($crypto, $ip, $ap);
            }

            /**
             * @param int|string|null $apId
             * @return array{devices: list<array<string, mixed>>}
             */
            public function getWewimoFullData($apId, string $invokerStr, ?string $ip = null): array {
                Assert::same('SignalPlotter', $invokerStr);
                $this->calls[] = (int) $apId;
                if ($apId === $this->emptyId) {
                    return ['devices' => []];
                }
                return ['devices' => [['ip' => '192.0.2.1', 'interfaces' => ['wlan1' => [
                    'mac-address' => '02:00:00:00:00:02', 'stations' => [[
                        'mac-address' => '02:00:00:00:00:01',
                        'x-signal-strength' => '-65.5', 'signal-strength-ch0' => '-66', 'signal-strength-ch1' => '-67',
                        'x-tx-signal-strength' => '-68', 'tx-signal-strength-ch0' => '-69', 'tx-signal-strength-ch1' => '-70',
                        'x-signal-to-noise' => '25', 'rx-ccq' => '0', 'tx-ccq' => '99', 'x-rx-rate' => '123.5', 'x-tx-rate' => '456',
                    ], ['mac-address' => '02:00:00:00:00:03', 'x-signal-strength' => null]],
                ]]]]];
            }
        };
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(503), new Response(204), new Response(204)]));
        $stack->push(Middleware::history($history));
        $writer = new InfluxWriter('influxdb://localhost:8086/wewimo', new Client(['handler' => $stack]));
        ob_start();
        try {
            Assert::same(0, (new CommandTester(new Wewimo2InfluxCommand($writer, $ap, $wewimo)))->execute([]));
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        Assert::same([$first->id, $empty->id, $last->id], $wewimo->calls);
        Assert::same("Processing Wewimo data for AP id=$first->id ERROR: InfluxDB write failed: HTTP 503.\n"
            . "Processing Wewimo data for AP id=$empty->id 0 station MACs stored  DONE.\n"
            . "Processing Wewimo data for AP id=$last->id 2 station MACs stored  DONE.\n", $output);
        Assert::count(3, $history);
        $fullPoint = 'station,macStationTag=02:00:00:00:00:01,interfaceMacTag=02:00:00:00:00:02,deviceIPTag=192.0.2.1 '
            . 'macField="02:00:00:00:00:01",rx_signal_strength=-65.5,rx_signal_strength_ch0=-66,rx_signal_strength_ch1=-67,'
            . 'tx_signal_strength=-68,tx_signal_strength_ch0=-69,tx_signal_strength_ch1=-70,signal_to_noise=25,rx_CCQ=0,tx_CCQ=99,rx_rate=123.5,tx_rate=456';
        Assert::same($fullPoint, (string) $history[0]['request']->getBody());
        Assert::same($fullPoint, (string) $history[1]['request']->getBody());
        Assert::same('station,macStationTag=02:00:00:00:00:03,interfaceMacTag=02:00:00:00:00:02,deviceIPTag=192.0.2.1 macField="02:00:00:00:00:03"', (string) $history[2]['request']->getBody());
    }
}

(new WewimoInfluxDatabaseTest())->run();
