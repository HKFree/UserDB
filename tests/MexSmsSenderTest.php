<?php

declare(strict_types=1);

namespace Test;

use App\Services\MexSmsSender;
use App\Services\SmsSenderException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class MexSmsSenderTest extends TestCase
{
    public function testInvalidLoginResponseNeverSendsSms(): void {
        foreach (['invalid JSON', 'null', 'false', '42', '"text"', '[]', '{}', '{"token":null}', '{"token":42}', '{"token":[]}', '{"token":""}'] as $body) {
            $history = $this->history();
            $sender = $this->sender(new MockHandler([new Response(200, [], $body)]), $history);
            Assert::exception(fn () => $sender->sendSMS(['777000000'], 'Test'), SmsSenderException::class, 'MEX API nevrátilo platný přihlašovací token.');
            Assert::count(1, $history);
            Assert::true(isset($history[0]['request']));
            Assert::same('/login', $history[0]['request']->getUri()->getPath());
        }
    }

    public function testValidTokenIsUsedAndCached(): void {
        $history = $this->history();
        $sender = $this->sender(new MockHandler([
            new Response(200, [], '{"token":"fixture-token"}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]), $history);
        $sender->sendSMS(['777000000'], 'První zpráva');
        $sender->sendSMS(['+420777000000'], 'Druhá zpráva');
        Assert::count(3, $history);
        Assert::true(isset($history[0]['request']));
        Assert::same('/login', $history[0]['request']->getUri()->getPath());
        foreach ([1 => 'První zpráva', 2 => 'Druhá zpráva'] as $index => $text) {
            Assert::true(isset($history[$index]['request']));
            $request = $history[$index]['request'];
            Assert::same('/sms/send', $request->getUri()->getPath());
            Assert::same('Bearer fixture-token', $request->getHeaderLine('Authorization'));
            Assert::same([['textId' => 'hkfree.org', 'bNumber' => '00420777000000', 'messageType' => 'SMS', 'text' => $text]], json_decode((string) $request->getBody(), true));
        }
    }

    /** @return \ArrayObject<int, array<array-key, mixed>> */
    private function history(): \ArrayObject {
        return new \ArrayObject();
    }

    /** @param \ArrayObject<int, array<array-key, mixed>> $history */
    private function sender(MockHandler $handler, \ArrayObject $history): MexSmsSender {
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        return new class (new Client(['handler' => $stack])) extends MexSmsSender {
            public function __construct(private Client $httpClient) {
                parent::__construct('http://mex.invalid', 'fixture', 'fixture-password', new Cache(new MemoryStorage(), 'mex-test'));
            }

            protected function createHttpClient(): Client {
                return $this->httpClient;
            }
        };
    }
}

(new MexSmsSenderTest())->run();
