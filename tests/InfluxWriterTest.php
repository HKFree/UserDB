<?php

declare(strict_types=1);

namespace Test;

use App\Services\InfluxWriter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class InfluxWriterTest extends TestCase
{
    public function testWritesExistingV1SchemaAndBasicAuthentication(): void {
        $handler = new MockHandler([new Response(204)]);
        $writer = new InfluxWriter('influxdb://user:p%40ss%2Bword@localhost:8086/wewimo', new Client(['handler' => HandlerStack::create($handler)]));
        $writer->writePoint('station', [
            'macStationTag' => '02:00:00:00:00:01',
            'interfaceMacTag' => '02:00:00:00:00:02',
            'deviceIPTag' => '192.0.2.1',
        ], ['macField' => '02:00:00:00:00:01', 'rx_signal_strength' => -65.5, 'rx_rate' => 0.0]);

        $request = $handler->getLastRequest();
        Assert::same('POST', $request->getMethod());
        Assert::same('http://localhost:8086/write?db=wewimo&precision=n', (string) $request->getUri());
        Assert::same('Basic ' . base64_encode('user:p@ss+word'), $request->getHeaderLine('Authorization'));
        Assert::same('text/plain; charset=utf-8', $request->getHeaderLine('Content-Type'));
        Assert::same('station,macStationTag=02:00:00:00:00:01,interfaceMacTag=02:00:00:00:00:02,deviceIPTag=192.0.2.1 macField="02:00:00:00:00:01",rx_signal_strength=-65.5,rx_rate=0', (string) $request->getBody());
    }

    public function testEscapesLineProtocolAndPreservesStringFields(): void {
        $handler = new MockHandler([new Response(204)]);
        $writer = new InfluxWriter('https+influxdb://localhost/wewimo%20test', new Client(['handler' => HandlerStack::create($handler)]));
        $writer->writePoint('station data,1', ['a=b' => 'a b,c=d\\e'], ['string' => '0123 "a"\\b', 'number' => -1.25]);
        Assert::same('https://localhost:8086/write?db=wewimo%20test&precision=n', (string) $handler->getLastRequest()->getUri());
        Assert::same('', $handler->getLastRequest()->getHeaderLine('Authorization'));
        Assert::same('station\\ data\\,1,a\\=b=a\\ b\\,c\\=d\\e string="0123 \\"a\\"\\\\b",number=-1.25', (string) $handler->getLastRequest()->getBody());
        $options = $handler->getLastOptions();
        Assert::false($options['allow_redirects']);
        Assert::same(5, $options['connect_timeout']);
        Assert::same(15, $options['timeout']);
        Assert::true($options['verify']);
    }

    public function testRejectsInvalidConfigurationWithoutLeakingCredentials(): void {
        foreach (['', 'http://user:private@localhost/db', 'udp+influxdb://localhost/db', 'influxdb://localhost', 'influxdb://localhost/', 'influxdb://localhost/db?secret=private', 'influxdb://localhost:99999/db'] as $dsn) {
            Assert::exception(fn () => new InfluxWriter($dsn), \InvalidArgumentException::class, 'Invalid INFLUX_URL; expected influxdb://user:password@host:port/database.');
        }
    }

    public function testRejectsInvalidPointsBeforeSending(): void {
        $handler = new MockHandler([]);
        $writer = new InfluxWriter('influxdb://localhost/wewimo', new Client(['handler' => HandlerStack::create($handler)]));
        foreach ([INF, -INF, NAN] as $value) {
            Assert::exception(fn () => $writer->writePoint('station', [], ['signal' => $value]), \InvalidArgumentException::class, 'InfluxDB numeric fields must be finite.');
        }
        foreach ([["a\nb", [], ['value' => 1.0]], ['station', ['tag' => "a\rb"], ['value' => 1.0]], ['station', [], ['macField' => "a\nb"]]] as [$measurement, $tags, $fields]) {
            Assert::exception(fn () => $writer->writePoint($measurement, $tags, $fields), \InvalidArgumentException::class, 'InfluxDB values must not contain line breaks.');
        }
        Assert::exception(fn () => $writer->writePoint('station', [], []), \InvalidArgumentException::class, 'InfluxDB point needs at least one field.');
        Assert::exception(fn () => $writer->writePoint('', [], ['value' => 1.0]), \InvalidArgumentException::class, 'InfluxDB names and tags must not be empty.');
        Assert::null($handler->getLastRequest());
    }

    public function testRejectsTagsAndFieldsOfUnsupportedTypesByName(): void {
        $handler = new MockHandler([]);
        $writer = new InfluxWriter('influxdb://localhost/wewimo', new Client(['handler' => HandlerStack::create($handler)]));
        foreach ([[null, 'null'], [20000000001, 'int'], [1.5, 'float'], [false, 'bool'], ['', 'empty string']] as [$value, $given]) {
            Assert::exception(
                fn () => $writer->writePoint('station', ['macStationTag' => '02:00:00:00:00:01', 'interfaceMacTag' => $value], ['value' => 1.0]),
                \InvalidArgumentException::class,
                "InfluxDB tag \"interfaceMacTag\" must be a non-empty string, $given given.",
            );
        }
        foreach ([[null, 'null'], [1, 'int'], [true, 'bool'], [['a'], 'array']] as [$value, $given]) {
            Assert::exception(
                fn () => $writer->writePoint('station', [], ['value' => 1.0, 'macField' => $value]),
                \InvalidArgumentException::class,
                "InfluxDB field \"macField\" must be a string or float, $given given.",
            );
        }
        Assert::null($handler->getLastRequest());
    }

    public function testReportsServerAndConnectionFailuresWithoutResponseSecrets(): void {
        foreach ([301, 400, 401, 404, 422, 500, 503] as $status) {
            $handler = new MockHandler([new Response($status, ['Location' => 'https://elsewhere.invalid'], 'private details')]);
            $writer = new InfluxWriter('influxdb://user:private@localhost/wewimo', new Client(['handler' => HandlerStack::create($handler)]));
            Assert::exception(fn () => $writer->writePoint('station', [], ['value' => 1.0]), \RuntimeException::class, "InfluxDB write failed: HTTP $status.");
            Assert::same(0, $handler->count());
        }
        $handler = new MockHandler([new \GuzzleHttp\Exception\ConnectException('private details', new \GuzzleHttp\Psr7\Request('POST', 'http://localhost/write'))]);
        $writer = new InfluxWriter('influxdb://localhost/wewimo', new Client(['handler' => HandlerStack::create($handler)]));
        Assert::exception(fn () => $writer->writePoint('station', [], ['value' => 1.0]), \RuntimeException::class, 'InfluxDB write failed: connection error.');
    }
}

(new InfluxWriterTest())->run();
