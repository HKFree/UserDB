<?php

namespace Test;

use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class RouterTest extends TestCase
{
    public function matchingCases(): array {
        return [
            ['/userdb/', ['presenter' => 'Homepage', 'action' => 'default', 'id' => null]],
            ['/userdb/api/health-check', ['presenter' => 'Api:HealthCheck', 'action' => 'default', 'id' => null]],
            ['/userdb/api/uzivatel/show/17', ['presenter' => 'Api:Uzivatel', 'action' => 'show', 'id' => '17']],
            ['/userdb/uzivatel/list/42', ['presenter' => 'UzivatelList', 'action' => 'list', 'id' => '42']],
            ['/userdb/sprava/schvalovanicc', ['presenter' => 'SpravaCc', 'action' => 'schvalovanicc']],
            ['/userdb/uzivatel/email/17', ['presenter' => 'UzivatelMailSms', 'action' => 'email', 'id' => '17']],
            ['/userdb/pub/user-email-feedback/17/test-hash/interest/confirm', ['presenter' => 'SelfService', 'action' => 'userEmailFeedback', 'uid' => '17', 'hash' => 'test-hash', 'variant' => 'interest', 'nextStep' => 'confirm']],
            ['/userdb/pub/user-confirm-email/test-key', ['presenter' => 'SelfService', 'action' => 'confirmEmail', 'key' => 'test-key']],
            ['/userdb/stitky/saveLabel', ['presenter' => 'SpravaStitku', 'action' => 'saveLabel']],
            ['/userdb/stitky/deleteLabel', ['presenter' => 'SpravaStitku', 'action' => 'deleteLabel']],
            ['/outside/userdb', null],
        ];
    }

    /** @dataProvider matchingCases */
    public function testMatching(string $path, ?array $expected): void {
        $router = (new RouterFactory())->createRouter('/userdb');
        $actual = $router->match(new Request(new UrlScript('http://localhost' . $path)));
        if ($expected !== null) {
            ksort($expected);
        }
        if ($actual !== null) {
            ksort($actual);
        }
        Assert::same($expected, $actual);
    }

    public function constructionCases(): array {
        return [
            [['presenter' => 'Uzivatel', 'action' => 'show', 'id' => 17], '/userdb/uzivatel/show/17'],
            [['presenter' => 'Api:HealthCheck', 'action' => 'default'], '/userdb/api/health-check'],
            [['presenter' => 'SelfService', 'action' => 'confirmEmail', 'key' => 'fixture-key'], '/userdb/pub/user-confirm-email/fixture-key'],
        ];
    }

    /** @dataProvider constructionCases */
    public function testConstruction(array $parameters, string $path): void {
        $router = (new RouterFactory())->createRouter('/userdb');
        Assert::same('http://localhost' . $path, $router->constructUrl($parameters, new UrlScript('http://localhost/')));
    }
}

(new RouterTest())->run();
