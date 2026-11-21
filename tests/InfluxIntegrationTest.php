<?php

declare(strict_types=1);

namespace Test;

use App\Services\InfluxWriter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class InfluxIntegrationTest extends TestCase
{
    private Client $client;
    private string $address;
    private string $token;

    protected function setUp(): void {
        Environment::lock('influxdb', TestEnvironment::TEMP_DIR);
        $cache = realpath(__DIR__ . '/../.flox/cache');
        if ($cache === false || realpath((string) getenv('FLOX_ENV_CACHE')) !== $cache) {
            throw new \RuntimeException('Run InfluxDB integration tests in this checkout with flox activate -s.');
        }
        $this->token = hash('sha256', 'userdb-influx-test-only:' . $cache);
        $this->waitForServer($cache . '/logs/influxdb.log');
        $setup = $this->json('GET', '/api/v2/setup');
        if ($setup['allowed'] === true) {
            $this->json('POST', '/api/v2/setup', ['json' => [
                'username' => 'userdb_test', 'password' => 'local-test-only',
                'org' => 'userdb_test', 'bucket' => 'userdb_test', 'token' => $this->token,
            ]]);
        }
        // Refuse any server that is not storing data inside this worktree.
        $config = $this->json('GET', '/api/v2/config')['config'];
        Assert::same($cache . '/influxdb/engine', $config['engine-path']);
        Assert::same($cache . '/influxdb/influxd.bolt', $config['bolt-path']);
        $bucket = $this->json('GET', '/api/v2/buckets', ['query' => ['name' => 'userdb_test']])['buckets'][0];
        $mappings = $this->json('GET', '/api/v2/dbrps', ['query' => ['orgID' => $bucket['orgID'], 'db' => 'userdb_test']]);
        if ($mappings['content'] === []) {
            $this->json('POST', '/api/v2/dbrps', ['json' => [
                'orgID' => $bucket['orgID'], 'bucketID' => $bucket['id'],
                'database' => 'userdb_test', 'retention_policy' => 'autogen', 'default' => true,
            ]]);
        }
        $this->prepareLegacyAuthentication($bucket['orgID'], $bucket['id']);
    }

    private function prepareLegacyAuthentication(string $orgId, string $bucketId): void {
        // These are also the endpoints used by `influx v1 auth create`.
        $authorizations = $this->json('GET', '/private/legacy/authorizations')['authorizations'];
        foreach ($authorizations as $authorization) {
            if ($authorization['token'] === 'wewimo_test') {
                return;
            }
        }
        $resource = ['type' => 'buckets', 'orgID' => $orgId, 'id' => $bucketId];
        $authorization = $this->json('POST', '/private/legacy/authorizations', ['json' => [
            'orgID' => $orgId, 'token' => 'wewimo_test',
            'permissions' => [['action' => 'read', 'resource' => $resource], ['action' => 'write', 'resource' => $resource]],
        ]]);
        $this->client->post('/private/legacy/authorizations/' . $authorization['id'] . '/password', ['json' => ['password' => 'local-wewimo-only']]);
    }

    public function testV1WriteAndReadKeepTagsFieldTypesAndServerTimestamp(): void {
        $id = bin2hex(random_bytes(8));
        $writer = $this->writer();
        $before = time();
        $writer->writePoint('station', [
            'macStationTag' => $id, 'interfaceMacTag' => '02:00:00:00:00:02', 'deviceIPTag' => '192.0.2.1',
        ], ['macField' => '0123 "quoted"\\end', 'rx_signal_strength' => -65.5, 'rx_rate' => 0.0]);
        $response = $this->json('GET', '/query', ['query' => [
            'db' => 'userdb_test', 'q' => 'SELECT * FROM station WHERE "macStationTag" = \'' . $id . '\'', 'epoch' => 's',
        ]]);
        $series = $response['results'][0]['series'][0];
        Assert::count(1, $series['values']);
        $row = array_combine($series['columns'], $series['values'][0]);
        Assert::true($row['time'] >= $before && $row['time'] <= time());
        unset($row['time']);
        Assert::same([
            'deviceIPTag' => '192.0.2.1', 'interfaceMacTag' => '02:00:00:00:00:02',
            'macField' => '0123 "quoted"\\end', 'macStationTag' => $id,
            'rx_rate' => 0, 'rx_signal_strength' => -65.5,
        ], $row);
        $types = $this->json('GET', '/query', ['query' => ['db' => 'userdb_test', 'q' => 'SHOW FIELD KEYS FROM station']]);
        Assert::same([['macField', 'string'], ['rx_rate', 'float'], ['rx_signal_strength', 'float']], $types['results'][0]['series'][0]['values']);
    }

    public function testServerRejectsInvalidAuthenticationAndMissingDatabase(): void {
        Assert::exception(fn () => $this->writer('wrong-token')->writePoint('station', [], ['rx_rate' => 1.0]), \RuntimeException::class, 'InfluxDB write failed: HTTP 401.');
        Assert::exception(fn () => $this->writer(database: 'missing_database')->writePoint('station', [], ['rx_rate' => 1.0]), \RuntimeException::class, 'InfluxDB write failed: HTTP 404.');
    }

    private function writer(string $password = 'local-wewimo-only', string $database = 'userdb_test'): InfluxWriter {
        return new InfluxWriter('influxdb://wewimo_test:' . $password . '@' . $this->address . '/' . $database);
    }

    public function testEscapedNamesAndTagsRoundTrip(): void {
        $id = bin2hex(random_bytes(8));
        $this->writer()->writePoint('station data,1', ['id' => $id, 'tag key' => 'a b,c=d\\e'], ['field key' => '']);
        $response = $this->json('GET', '/query', ['query' => [
            'db' => 'userdb_test', 'q' => 'SELECT * FROM "station data,1" WHERE id = \'' . $id . '\'',
        ]]);
        $series = $response['results'][0]['series'][0];
        Assert::count(1, $series['values']);
        $row = array_combine($series['columns'], $series['values'][0]);
        unset($row['time']);
        Assert::same(['field key' => '', 'id' => $id, 'tag key' => 'a b,c=d\\e'], $row);
    }

    private function waitForServer(string $logFile): void {
        $deadline = microtime(true) + 30;
        do {
            $log = is_file($logFile) ? (string) file_get_contents($logFile) : '';
            if (preg_match('~msg=Listening[^\n]*transport=http addr=127\.0\.0\.1:0 port=(\d+)~', $log, $match) === 1) {
                $port = (int) $match[1];
                if ($port < 4500 || $port > 65535) {
                    throw new \RuntimeException('Invalid local InfluxDB port.');
                }
                $this->address = '127.0.0.1:' . $port;
                $this->client = new Client([
                    'base_uri' => 'http://' . $this->address,
                    'headers' => ['Authorization' => 'Token ' . $this->token],
                    'timeout' => 2, 'connect_timeout' => 1, 'allow_redirects' => false,
                ]);
                try {
                    if ($this->json('GET', '/health')['status'] === 'pass') {
                        return;
                    }
                } catch (TransferException) {
                    // Flox starts services asynchronously; reread the port after a restart.
                }
            }
            usleep(100000);
        } while (microtime(true) < $deadline);
        throw new \RuntimeException('Local InfluxDB did not start; use flox activate -s and inspect .flox/cache/logs/influxdb.log.');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $options = []): array {
        return json_decode((string) $this->client->request($method, $path, $options)->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

(new InfluxIntegrationTest())->run();
