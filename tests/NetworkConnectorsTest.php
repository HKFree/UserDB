<?php

declare(strict_types=1);

namespace Test;

use App\Model\IdsConnector;
use App\Model\Sojka;
use App\Model\VnejsiScanner;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class NetworkConnectorsTest extends TestCase
{
    public function testIdsQueriesKeepRawEventsAndWhitelistKeys(): void {
        $handler = new MockHandler([
            new Response(200, [], '{"hits":{"hits":[{"_id":"event-1"}]}}'),
            new Response(200, [], '{"aggregations":{"uniq_ip":{"buckets":[{"key":"10.107.1.1","doc_count":2},{"key":"10.107.1.2","doc_count":3}]}}}'),
            new Response(200, [], '{}'),
        ]);
        $model = $this->connector($handler);

        Assert::same([['_id' => 'event-1']], $model->getEventsForIps(['192.0.2.42'], 3, 12));
        $request = $handler->getLastRequest();
        Assert::true($request !== null);
        Assert::same('POST', $request->getMethod());
        $query = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        Assert::same(12, $query['size']);
        Assert::same([
            ['terms' => ['src_ip.raw' => ['192.0.2.42']]],
            ['range' => ['@timestamp' => ['gte' => 'now-3d', 'lte' => 'now']]],
        ], $query['query']['bool']['must']);
        Assert::same([1 => ['key' => '10.107.1.2', 'doc_count' => 3]], $model->getUniqueIpsFromPrivateSubnets());
        Assert::exception(fn () => $model->getEventsForIps([]), \RuntimeException::class, 'Empty response from IDS, maybe wrong IDS username/password?');
        Assert::same(0, $handler->count());
    }

    public function testIdsRejectsEmptyResponsesButAcceptsEmptyHits(): void {
        foreach (['', 'not json', 'null', 'false', '0', '0.0', '""', '"0"', '[]', '{}'] as $body) {
            $model = $this->connector(new MockHandler([new Response(200, [], $body)]));
            Assert::exception(fn () => $model->getEventsForIps(['192.0.2.42']), \RuntimeException::class, 'Empty response from IDS, maybe wrong IDS username/password?');
        }
        $model = $this->connector(new MockHandler([
            new Response(200, [], '{"hits":{"hits":[]}}'),
            new Response(200, [], '{"aggregations":{"uniq_ip":{"buckets":[]}}}'),
        ]));
        Assert::same([], $model->getEventsForIps(['192.0.2.42']));
        Assert::same([], $model->getUniqueIpsFromPrivateSubnets());
    }

    public function testEmptyScanIsRetriedAndNonemptyScanIsCached(): void {
        $scanner = new class ('http://127.0.0.1:0/') extends VnejsiScanner {
            public int $downloads = 0;

            public function downloadScan(): void {
                $this->downloads++;
                $data = $this->downloads === 1 ? [] : ['192.0.2.42' => ['443']];
                (new \ReflectionProperty(VnejsiScanner::class, 'scanData'))->setValue($this, $data);
            }
        };
        Assert::same([], $scanner->getScan());
        Assert::same(['192.0.2.42' => ['443']], $scanner->getScan());
        Assert::same(['192.0.2.42' => ['443']], $scanner->getScan());
        Assert::same(2, $scanner->downloads);
    }

    public function testUnavailableScanKeepsNullResults(): void {
        $scanner = new class ('http://127.0.0.1:0/') extends VnejsiScanner {
            /** @return array{} */
            public function downloadScan(): array {
                return [];
            }
        };
        Assert::null($scanner->getScan());
        Assert::null($scanner->getScanDate());
        Assert::same([], $scanner->getScanNaPortech([443]));
        Assert::same([], (new Sojka('http://127.0.0.1:0/'))->pingIPS([]));
    }

    private function connector(MockHandler $handler): IdsConnector {
        return new class (new Client(['handler' => HandlerStack::create($handler)])) extends IdsConnector {
            public function __construct(private Client $client) {
                parent::__construct('https://ids.example.invalid', 'test', 'test', '10.107.1.1');
            }

            protected function getElasticHttpClient(): Client {
                return $this->client;
            }
        };
    }
}

(new NetworkConnectorsTest())->run();
