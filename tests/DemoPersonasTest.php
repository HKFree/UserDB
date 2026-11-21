<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\TestCase;
use UserdbDemoPersonas;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/UserdbDemoPersonas.php';
require_once __DIR__ . '/../scripts/UserdbDemoPersonaPage.php';
\Tester\Environment::setup();

final class DemoPersonasTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    protected function tearDown(): void {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
    }

    public function testOnlyDocumentedPersonasCanBeSelected(): void {
        foreach ([1, ...range(20000, 20015)] as $id) {
            $persona = new UserdbDemoPersonas((string) $id);
            Assert::same($id, $persona->fakeUser()['userID']);
            Assert::same($id === 1 ? 'tester' : 'demo' . $id, $persona->fakeUser()['userName']);
        }
        foreach ([null, '', 20002, ['20002'], '0', '20016', '999999', '-20002', '+20002', '20002.0', '2e4', ' 20002', "20002\n", str_repeat('9', 100)] as $value) {
            Assert::same(1, (new UserdbDemoPersonas($value))->fakeUser()['userID']);
        }
        Assert::contains('Vyžaduje objemová data', UserdbDemoPersonas::all()[20003]['note']);
        Assert::same('20010: Demo Prošlárole (DRUŽSTEVNÍK)', (new UserdbDemoPersonas('20010'))->label());
        Assert::notSame(UserdbDemoPersonas::cookieName('/one'), UserdbDemoPersonas::cookieName('/two'));
    }

    public function testHttpsUsesTheOriginalHeaderFromTheLocalProxy(): void {
        Assert::true(\UserdbDemoPersonaPage::isHttps(['HTTPS' => 'on'], []));
        Assert::false(\UserdbDemoPersonaPage::isHttps(['HTTPS' => 'off'], []));
        foreach (['127.0.0.1', '::1'] as $address) {
            Assert::true(\UserdbDemoPersonaPage::isHttps(['REMOTE_ADDR' => $address], ['x-forwarded-proto' => 'https']));
            Assert::false(\UserdbDemoPersonaPage::isHttps(['REMOTE_ADDR' => $address], ['X_Forwarded_Proto' => 'https']));
        }
        Assert::false(\UserdbDemoPersonaPage::isHttps(['REMOTE_ADDR' => '192.0.2.1'], ['X-Forwarded-Proto' => 'https']));
    }

    public function testRouterWorksWithoutApplicationBootstrap(): void {
        $port = $this->startServer();
        $cookieName = UserdbDemoPersonas::cookieName(dirname(__DIR__));
        [$status, $headers, $body] = $this->request($port, '/demo-osoba');
        Assert::same(200, $status);
        Assert::contains('Content-Type: text/html; charset=utf-8', $headers);
        Assert::contains('Cache-Control: no-store', $headers);
        Assert::same([], preg_grep('~^Set-Cookie:~i', $headers));
        Assert::same(17, substr_count($body, '<a class="persona"'));
        foreach ([1, ...range(20000, 20015)] as $id) {
            Assert::contains('href="/demo-osoba?id=' . $id . '"', $body);
            $persona = UserdbDemoPersonas::all()[$id];
            Assert::contains('<span class="name">' . htmlspecialchars($persona['name'], ENT_QUOTES, 'UTF-8') . '</span><span class="id">' . $id . '</span>', $body);
            foreach ([...$persona['roles'], ...($persona['inactive'] ?? []), $persona['note']] as $text) {
                Assert::contains(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), $body);
            }
        }
        $this->assertActive(1, $body);

        // 20003 may have no login role. Even with an unusable bootstrap it must be possible to switch back.
        foreach (['20003' => 20003, '20005' => 20005, '999999' => 1, '20002.0' => 1, '%3Cscript%3E' => 1] as $value => $id) {
            [$status, , $body] = $this->request($port, '/demo-osoba', ["Cookie: $cookieName=$value"]);
            Assert::same(200, $status);
            $this->assertActive($id, $body);
            Assert::notContains('<script>', $body);
        }
        [$status, , $body] = $this->request($port, '/demo-osoba', ["Cookie: {$cookieName}[]=20005"]);
        Assert::same(200, $status);
        $this->assertActive(1, $body);

        foreach (['20002', '20005', '20006', '1'] as $id) {
            [$status, $headers] = $this->request($port, '/demo-osoba?id=' . $id);
            $cookie = $this->assertSwitch($status, $headers, $cookieName, (int) $id);
            Assert::notContains('secure', strtolower($cookie));
            [$status, , $body] = $this->request($port, '/demo-osoba', ['Cookie: ' . explode(';', substr($cookie, 12))[0]]);
            Assert::same(200, $status);
            $this->assertActive((int) $id, $body);
        }

        foreach (['999999', '20016', '20002.0', '2e4', '%2B20002', '20002%0A', '%3Cscript%3E', '', str_repeat('9', 100)] as $value) {
            [$status, $headers, $body] = $this->request($port, '/demo-osoba?id=' . $value . '&back=https://example.invalid/', ["Cookie: $cookieName=20005"]);
            $this->assertSwitch($status, $headers, $cookieName, 1);
            Assert::same('', $body);
        }
        [$status, $headers] = $this->request($port, '/demo-osoba?id[]=20002');
        $this->assertSwitch($status, $headers, $cookieName, 1);

        [$status, $headers] = $this->request($port, '/demo-osoba?id=20002', ['X-Forwarded-Proto: https', 'X_Forwarded_Proto: http']);
        Assert::contains('; secure;', strtolower($this->assertSwitch($status, $headers, $cookieName, 20002)));
        [$status, $headers] = $this->request($port, '/demo-osoba?id=20002', ['X_Forwarded_Proto: https']);
        Assert::notContains('secure', strtolower($this->assertSwitch($status, $headers, $cookieName, 20002)));

        // A link on another site only shows the list; the same link inside the instance or typed by hand switches.
        foreach (['cross-site', 'same-site'] as $site) {
            [$status, $headers, $body] = $this->request($port, '/demo-osoba?id=20005', ["Sec-Fetch-Site: $site"]);
            Assert::same(200, $status);
            Assert::same([], preg_grep('~^Set-Cookie:~i', $headers));
            $this->assertActive(1, $body);
        }
        foreach (['same-origin', 'none'] as $site) {
            [$status, $headers] = $this->request($port, '/demo-osoba?id=20005', ["Sec-Fetch-Site: $site"]);
            $this->assertSwitch($status, $headers, $cookieName, 20005);
        }
        // PHP stores a header with a numeric name under an integer key.
        [$status, $headers] = $this->request($port, '/demo-osoba?id=20005', ['123: x']);
        $this->assertSwitch($status, $headers, $cookieName, 20005);

        [$status, $headers] = $this->request($port, '/demo-osoba?id=20005', method: 'POST');
        Assert::same(405, $status);
        Assert::contains('Allow: GET', $headers);
        Assert::same([], preg_grep('~^Set-Cookie:~i', $headers));
    }

    private function assertActive(int $id, string $body): void {
        Assert::contains('href="/demo-osoba?id=' . $id . '" aria-current="true"', $body);
        Assert::same(1, substr_count($body, 'aria-current="true"'));
        Assert::same(1, substr_count($body, '<span class="tag active">aktivní</span>'));
    }

    /** @param list<string> $headers */
    private function assertSwitch(int $status, array $headers, string $cookieName, int $id): string {
        Assert::same(302, $status);
        Assert::contains('Location: /', $headers);
        Assert::contains('Cache-Control: no-store', $headers);
        $matches = preg_grep('~^Set-Cookie:~i', $headers);
        Assert::true(is_array($matches));
        $cookies = array_values($matches);
        Assert::count(1, $cookies);
        Assert::contains("Set-Cookie: $cookieName=$id;", $cookies[0]);
        Assert::contains('; path=/', strtolower($cookies[0]));
        Assert::contains('; httponly', strtolower($cookies[0]));
        Assert::contains('; samesite=lax', strtolower($cookies[0]));
        Assert::notContains('domain=', strtolower($cookies[0]));
        return $cookies[0];
    }

    private function startServer(): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        Assert::true(is_resource($socket));
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':') ?: '', 1);
        fclose($socket);
        $command = [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-S', "127.0.0.1:$port", '-t', __DIR__ . '/../www', __DIR__ . '/../scripts/userdb_dev_router.php'];
        // Missing Flox setup would make the application bootstrap throw. The router must never reach it.
        $environment = ['FLOX_ENV_CACHE' => '', 'USERDB_MARIADB_SOCKET' => '', 'PHP_CLI_SERVER_WORKERS' => ''] + getenv();
        $null = ['file', '/dev/null', 'r+'];
        $server = proc_open($command, [$null, $null, $null], $pipes, null, $environment);
        Assert::true(is_resource($server));
        $this->server = $server;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port, $code, $message, 0.1);
            if ($connection !== false) {
                fclose($connection);
                return $port;
            }
            usleep(50_000);
        }
        throw new \RuntimeException("The demo router did not start on port $port.");
    }

    /**
     * @param list<string> $headers
     * @return array{int, list<string>, string}
     */
    private function request(int $port, string $path, array $headers = [], string $method = 'GET'): array {
        $context = stream_context_create(['http' => ['method' => $method, 'header' => $headers, 'follow_location' => 0, 'ignore_errors' => true]]);
        $body = file_get_contents("http://127.0.0.1:$port$path", false, $context);
        Assert::true(is_string($body));
        $responseHeaders = $http_response_header;
        Assert::true(preg_match('~^HTTP/\S+ (\d{3})~', $responseHeaders[0], $status) === 1);
        return [(int) $status[1], $responseHeaders, $body];
    }
}

(new DemoPersonasTest())->run();
