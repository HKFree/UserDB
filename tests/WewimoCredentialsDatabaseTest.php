<?php

declare(strict_types=1);

namespace Test;

use App\Model\AP;
use App\Model\IPAdresa;
use App\Model\Wewimo;
use App\Services\CryptoSluzba;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class RouterConnectionAttempt extends \RuntimeException
{
}

final class RecordingRouterClient
{
    /** @var list<array{string, ?string, ?string}> */
    public static array $credentials = [];

    public function __construct(string $ip, ?string $username, ?string $password) {
        self::$credentials[] = [$ip, $username, $password];
        throw new RouterConnectionAttempt();
    }
}

Assert::false(class_exists(\PEAR2\Net\RouterOS\Client::class, false));
class_alias(RecordingRouterClient::class, \PEAR2\Net\RouterOS\Client::class);

final class WewimoCredentialsDatabaseTest extends DatabaseTestCase
{
    public function testMissingCredentialsFailBeforeConnection(): void {
        $model = new Wewimo($this->container->getByType(CryptoSluzba::class), $this->container->getByType(IPAdresa::class), $this->container->getByType(AP::class));
        RecordingRouterClient::$credentials = [];
        foreach ([[null, 'test'], ['test', null], [null, null]] as [$username, $password]) {
            Assert::exception(fn () => $model->getWewimoData('192.0.2.1', $username, $password, 'Test'), \InvalidArgumentException::class, 'Chybí přihlašovací údaje zařízení.');
        }
        Assert::same([], RecordingRouterClient::$credentials);
        foreach ([['test', 'test'], ['test', '']] as [$username, $password]) {
            Assert::exception(fn () => $model->getWewimoData('192.0.2.1', $username, $password, 'Test'), RouterConnectionAttempt::class);
        }
        Assert::same([['192.0.2.1', 'test', 'test'], ['192.0.2.1', 'test', '']], RecordingRouterClient::$credentials);
    }
}

(new WewimoCredentialsDatabaseTest())->run();
