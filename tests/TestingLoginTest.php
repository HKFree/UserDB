<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;
use Tester\TestCase;
use UserdbTestingLogin;
use UserdbTestingLoginResponse;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/UserdbTestingLogin.php';
\Tester\Environment::setup();

/**
 * The sign-in form of the shared testing server. The reverse proxy in front of it is not part of the tests.
 */
final class TestingLoginTest extends TestCase
{
    private const PASSWORD = 'správné heslo';
    private const NOW = 1_800_000_000;
    private const VISITOR = '192.0.2.10';
    private const HOST = 'master.testing.example';

    private string $directory;
    private string $passwordHash;
    private UserdbTestingLogin $login;

    /** @var list<resource> services started by startService() */
    private array $services = [];

    protected function setUp(): void {
        $this->directory = __DIR__ . '/output/testing-login-' . bin2hex(random_bytes(8));
        FileSystem::createDir($this->directory);
        // The lowest cost keeps the tests fast; the server uses the default one.
        $this->passwordHash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->login = new UserdbTestingLogin($this->passwordHash, 'testing.example', $this->directory);
    }

    protected function tearDown(): void {
        $this->stopServices();
        FileSystem::delete($this->directory);
    }

    public function testVisitorWithoutCookieIsSentToTheFormAndBack(): void {
        foreach (['GET', 'HEAD'] as $method) {
            $response = $this->verify($method, '/sprava/nastroje?od=1&do=2', '');
            Assert::same(302, $response->status);
            Assert::same(['Location: /testing-login?back=%2Fsprava%2Fnastroje%3Fod%3D1%26do%3D2'], $response->headers);
        }

        foreach (['GET', 'HEAD'] as $method) {
            $form = $this->send($method, '/testing-login', query: ['back' => '/sprava/nastroje?od=1&do=2']);
            Assert::same(200, $form->status);
            Assert::contains('<input type="hidden" name="back" value="/sprava/nastroje?od=1&amp;do=2">', $form->body);
            Assert::contains('<input type="password" id="password" name="password"', $form->body);
            Assert::notContains('class="error"', $form->body);
        }
    }

    public function testRequestThatCannotBeRedirectedIsRefused(): void {
        foreach (['POST', 'OPTIONS', ''] as $method) {
            $response = $this->verify($method, '/uzivatel/edit/1', '');
            Assert::same(403, $response->status);
            Assert::contains('/testing-login', $response->body);
            Assert::same([], $this->headers($response, 'Location'));
        }
    }

    public function testRightPasswordSignsInForAllInstances(): void {
        $response = $this->signIn(self::PASSWORD, '/uzivatel/show/1');
        Assert::same(303, $response->status);
        Assert::same('Location: /uzivatel/show/1', $response->headers[0]);
        $maxAge = UserdbTestingLogin::SESSION_SECONDS;
        Assert::match(
            "~^Set-Cookie: userdb_testing_login=\\d+\\.[0-9a-f]{64}; Max-Age=$maxAge; Path=/; Domain=testing\\.example; Secure; HttpOnly; SameSite=Lax$~",
            $response->headers[1],
        );

        $cookie = $this->cookieValue($response);
        Assert::same(204, $this->verify('GET', '/', $cookie)->status);
        Assert::same(204, $this->verify('POST', '/uzivatel/edit/1', $cookie)->status);
    }

    public function testHashWrittenByCaddyIsAccepted(): void {
        $this->login = new UserdbTestingLogin('$2a$' . substr($this->passwordHash, 4), 'testing.example', $this->directory);
        Assert::same(303, $this->signIn(self::PASSWORD, '/')->status);
        Assert::same(403, $this->signIn('špatné heslo', '/')->status);
    }

    public function testCookieEndsAfterItsLifetime(): void {
        $cookie = $this->cookieValue($this->signIn(self::PASSWORD, '/'));
        $lastSecond = self::NOW + UserdbTestingLogin::SESSION_SECONDS - 1;
        Assert::same(204, $this->verify('GET', '/', $cookie, $lastSecond)->status);
        Assert::same(302, $this->verify('GET', '/', $cookie, $lastSecond + 1)->status);
    }

    public function testForgedCookieIsRefused(): void {
        [$expires, $signature] = explode('.', $this->cookieValue($this->signIn(self::PASSWORD, '/')));
        $later = (string) ((int) $expires + 1);
        foreach (["$later.$signature", "$expires." . strrev($signature), "$expires.$signature\n", $expires, "$expires.", ''] as $forged) {
            Assert::same(302, $this->verify('GET', '/', $forged)->status, "cookie '$forged'");
        }
    }

    public function testNewPasswordSignsEveryoneOut(): void {
        $cookie = $this->cookieValue($this->signIn(self::PASSWORD, '/'));
        $newHash = password_hash('jiné heslo', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->login = new UserdbTestingLogin($newHash, 'testing.example', $this->directory);
        Assert::same(302, $this->verify('GET', '/', $cookie)->status);
    }

    public function testWrongPasswordShowsTheFormAgain(): void {
        $response = $this->signIn('špatné heslo', '/sprava?a="b"');
        Assert::same(403, $response->status);
        Assert::contains('<p class="error" role="alert">Nesprávné heslo.</p>', $response->body);
        Assert::contains('name="back" value="/sprava?a=&quot;b&quot;"', $response->body);
        Assert::same([], $this->headers($response, 'Set-Cookie'));
    }

    public function testRepeatedFailuresBlockOnlyThatVisitorAndOnlyForAWhile(): void {
        for ($attempt = 1; $attempt <= UserdbTestingLogin::MAX_FAILURES; $attempt++) {
            Assert::same(403, $this->signIn('špatné heslo', '/', self::NOW + $attempt)->status);
        }
        $blockedAt = self::NOW + UserdbTestingLogin::MAX_FAILURES + 1;
        $blocked = $this->signIn(self::PASSWORD, '/', $blockedAt);
        Assert::same(429, $blocked->status);
        Assert::contains('Příliš mnoho neúspěšných pokusů. Zkuste to znovu za 15 minut.', $blocked->body);
        Assert::same([], $this->headers($blocked, 'Set-Cookie'));
        // A refused attempt is not counted, otherwise a visitor who keeps trying would stay blocked for ever.
        Assert::same(429, $this->signIn(self::PASSWORD, '/', $blockedAt + 1)->status);

        Assert::same(303, $this->signIn(self::PASSWORD, '/', $blockedAt, '192.0.2.11')->status);
        // The window is counted from each failure: the first one drops out, the visitor has one attempt again.
        $firstFailureExpired = self::NOW + 1 + UserdbTestingLogin::FAILURE_WINDOW_SECONDS;
        Assert::same(303, $this->signIn(self::PASSWORD, '/', $firstFailureExpired)->status);
    }

    public function testRightPasswordClearsTheFailures(): void {
        for ($round = 0; $round < 2; $round++) {
            for ($attempt = 1; $attempt < UserdbTestingLogin::MAX_FAILURES; $attempt++) {
                Assert::same(403, $this->signIn('špatné heslo', '/')->status);
            }
            Assert::same(303, $this->signIn(self::PASSWORD, '/')->status);
        }
    }

    /**
     * PHP files X_Forwarded_For of a client under the same $_SERVER key as X-Forwarded-For of the proxy.
     */
    public function testVisitorCannotChooseTheAddressItIsCountedUnder(): void {
        for ($attempt = 1; $attempt <= UserdbTestingLogin::MAX_FAILURES; $attempt++) {
            $response = $this->send('POST', '/testing-login', ['X-Forwarded-For' => self::VISITOR, 'X_Forwarded_For' => "198.51.100.$attempt"], post: ['password' => 'špatné heslo']);
            Assert::same(403, $response->status);
        }
        Assert::same(429, $this->signIn(self::PASSWORD, '/')->status);
    }

    public function testCountersOfVisitorsWhoNeverComeBackAreDeleted(): void {
        $this->signIn('špatné heslo', '/', self::NOW, '192.0.2.20');
        $attemptFiles = glob($this->directory . '/*.attempts');
        Assert::true(is_array($attemptFiles));
        Assert::count(1, $attemptFiles);
        $this->signIn('špatné heslo', '/', self::NOW + UserdbTestingLogin::FAILURE_WINDOW_SECONDS, '192.0.2.21');
        $attemptFiles = glob($this->directory . '/*.attempts');
        Assert::true(is_array($attemptFiles));
        Assert::count(1, $attemptFiles);
    }

    /** @return list<array{string}> */
    public function foreignTargets(): array {
        return [['//evil.example/'], ['/\\evil.example/'], ['https://evil.example/'], ['evil.example'], ["/a\r\nSet-Cookie: x=1"], ["/sprava\n"], ['']];
    }

    /** @dataProvider foreignTargets */
    public function testWayBackNeverLeavesTheInstance(string $target): void {
        $signIn = $this->signIn(self::PASSWORD, $target);
        Assert::same('Location: /', $signIn->headers[0]);
        Assert::same(['Location: /testing-login?back=%2F'], $this->verify('GET', $target, '')->headers);
        Assert::contains('name="back" value="/"', $this->send('GET', '/testing-login', query: ['back' => $target])->body);
        $signedInPage = $this->send('GET', '/testing-login', query: ['back' => $target], cookie: $this->cookieValue($signIn));
        Assert::contains('<a href="/">', $signedInPage->body);
    }

    public function testSignedInVisitorCanSignOut(): void {
        $cookie = $this->cookieValue($this->signIn(self::PASSWORD, '/'));
        $page = $this->send('GET', '/testing-login', query: ['back' => '/sprava?a="b"'], cookie: $cookie);
        Assert::same(200, $page->status);
        Assert::contains('Jste přihlášeni.', $page->body);
        Assert::contains('<a href="/sprava?a=&quot;b&quot;">', $page->body);
        Assert::contains('action="/testing-login/sign-out"', $page->body);
        Assert::notContains('name="password"', $page->body);

        $signOut = $this->send('POST', '/testing-login/sign-out', ['Origin' => 'https://' . self::HOST], cookie: $cookie);
        Assert::same(303, $signOut->status);
        Assert::same(
            ['Location: /testing-login', 'Set-Cookie: userdb_testing_login=; Max-Age=0; Path=/; Domain=testing.example; Secure; HttpOnly; SameSite=Lax'],
            $signOut->headers,
        );
    }

    public function testFormOfAnotherSiteCanNeitherSignOutNorUseUpTheAttempts(): void {
        foreach (['https://evil.example', 'https://testing.example', 'http://' . self::HOST . ':8080', 'null'] as $origin) {
            $signOut = $this->send('POST', '/testing-login/sign-out', ['Origin' => $origin]);
            Assert::same(403, $signOut->status, $origin);
            Assert::same([], $this->headers($signOut, 'Set-Cookie'), $origin);

            $headers = ['Origin' => $origin, 'X-Forwarded-For' => self::VISITOR];
            Assert::same(403, $this->send('POST', '/testing-login', $headers, post: ['password' => self::PASSWORD])->status, $origin);
            Assert::same([], glob($this->directory . '/*.attempts'), $origin);
        }
        // A browser names the instance itself as the origin of its form; a client like curl names none.
        $own = $this->send('POST', '/testing-login', ['Origin' => 'https://' . self::HOST], post: ['password' => self::PASSWORD]);
        Assert::same(303, $own->status);
    }

    public function testWithoutSharedDomainTheCookieBelongsToTheVisitedHost(): void {
        $this->login = new UserdbTestingLogin($this->passwordHash, '', $this->directory);
        Assert::notContains('Domain=', $this->signIn(self::PASSWORD, '/')->headers[1]);
    }

    public function testOtherPathsAndMethodsDoNotExist(): void {
        foreach ([['GET', '/'], ['GET', '/testing-login/sign-out'], ['PUT', '/testing-login'], ['GET', '/testing-login/']] as [$method, $uri]) {
            Assert::same(404, $this->send($method, $uri)->status, "$method $uri");
        }
    }

    public function testInputOfAnotherTypeIsTreatedAsMissing(): void {
        $response = $this->send('POST', '/testing-login', post: ['password' => [self::PASSWORD], 'back' => ['/x']]);
        Assert::same(403, $response->status);
        Assert::contains('name="back" value="/"', $response->body);
    }

    public function testBrokenSetupIsRefused(): void {
        foreach (['', '$', self::PASSWORD, '$2a$04$short', '$argon2id$', substr($this->passwordHash, 0, -1), $this->passwordHash . "\n"] as $notAHash) {
            Assert::exception(fn () => new UserdbTestingLogin($notAHash, 'testing.example', $this->directory), \InvalidArgumentException::class, '%a%hash%a%');
        }
        foreach (['testing.example; Path=/x', "testing.example\n"] as $notADomain) {
            Assert::exception(fn () => new UserdbTestingLogin($this->passwordHash, $notADomain, $this->directory), \InvalidArgumentException::class, '%a%domain%a%');
        }
        Assert::exception(fn () => new UserdbTestingLogin($this->passwordHash, 'testing.example', $this->directory . '/missing'), \InvalidArgumentException::class, '%a%directory%a%');
    }

    /**
     * The entry script under PHP's own server, started with the options of the systemd unit.
     */
    public function testServiceAnswersOverHttp(): void {
        $port = $this->startService($this->unitOptions(), ['USERDB_TESTING_LOGIN_COOKIE_DOMAIN' => 'testing.example']);
        [$status, $headers] = $this->http($port, 'GET', '/verify', ['X-Forwarded-Method: GET', 'X-Forwarded-Uri: /sprava']);
        Assert::same(302, $status);
        Assert::contains('Location: /testing-login?back=%2Fsprava', $headers);
        Assert::same([], preg_grep('~^X-Powered-By:~i', $headers));

        $form = ['Content-Type: application/x-www-form-urlencoded'];
        [$status, $headers] = $this->http($port, 'POST', '/testing-login', $form, http_build_query(['password' => self::PASSWORD, 'back' => '/sprava']));
        Assert::same(303, $status);
        Assert::contains('Location: /sprava', $headers);
        $cookies = preg_grep('~^Set-Cookie: userdb_testing_login=.*; Domain=testing\.example;~', $headers);
        Assert::true(is_array($cookies));
        Assert::count(1, $cookies);
        $cookie = explode(';', substr((string) reset($cookies), strlen('Set-Cookie: ')))[0];

        [$status] = $this->http($port, 'GET', '/verify', ["Cookie: $cookie", 'X-Forwarded-Method: GET', 'X-Forwarded-Uri: /sprava']);
        Assert::same(204, $status);
    }

    public function testBrokenSetupOverHttpLetsNobodyInAndTellsNothing(): void {
        $port = $this->startService($this->unitOptions(), ['STATE_DIRECTORY' => $this->directory . '/missing']);
        [$status, , $body] = $this->http($port, 'GET', '/verify', ['X-Forwarded-Method: GET', 'X-Forwarded-Uri: /']);
        Assert::same(500, $status);
        Assert::same('', $body);
    }

    /**
     * PHP warns about more than max_input_vars cookies before the script starts. Printed, the warning would
     * turn the answer into 200, which the reverse proxy takes for a signed-in visitor.
     */
    public function testWarningOfPhpDoesNotOpenTheGate(): void {
        $port = $this->startService($this->unitOptions());
        $cookies = implode('; ', array_map(static fn (int $number): string => "c$number=1", range(1, (int) ini_get('max_input_vars') + 2)));
        [$status, , $body] = $this->http($port, 'GET', '/verify', ["Cookie: $cookies", 'X-Forwarded-Method: GET', 'X-Forwarded-Uri: /']);
        Assert::same(302, $status);
        Assert::same('', $body);
    }

    public function testServiceStartedWithoutTheOptionsOfTheUnitLetsNobodyIn(): void {
        $cookie = UserdbTestingLogin::COOKIE . '=' . $this->cookieValue($this->signIn(self::PASSWORD, '/'));
        foreach ([['-d', 'display_errors=1', '-d', 'display_startup_errors=0'], ['-d', 'display_errors=0', '-d', 'display_startup_errors=1']] as $options) {
            $port = $this->startService($options);
            [$status] = $this->http($port, 'GET', '/verify', ["Cookie: $cookie", 'X-Forwarded-Method: GET', 'X-Forwarded-Uri: /']);
            Assert::same(500, $status, implode(' ', $options));
        }
    }

    public function testServiceCountsAttemptsUnderTheAddressNamedByTheProxy(): void {
        $port = $this->startService($this->unitOptions());
        $body = http_build_query(['password' => 'špatné heslo']);
        for ($attempt = 1; $attempt <= UserdbTestingLogin::MAX_FAILURES + 1; $attempt++) {
            $headers = ['Content-Type: application/x-www-form-urlencoded', 'X-Forwarded-For: ' . self::VISITOR, "X_Forwarded_For: 198.51.100.$attempt"];
            [$status] = $this->http($port, 'POST', '/testing-login', $headers, $body);
            Assert::same($attempt <= UserdbTestingLogin::MAX_FAILURES ? 403 : 429, $status, "attempt $attempt");
        }
    }

    /**
     * Several workers, as on the server: attempts that arrive together must not each see a free place.
     */
    public function testAttemptsSentAtOnceCannotPassTheLimit(): void {
        $port = $this->startService($this->unitOptions(), ['PHP_CLI_SERVER_WORKERS' => '4']);
        $body = http_build_query(['password' => 'špatné heslo']);
        $request = "POST /testing-login HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\nX-Forwarded-For: " . self::VISITOR . "\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n\r\n$body";
        $connections = [];
        for ($number = 0; $number < 4 * UserdbTestingLogin::MAX_FAILURES; $number++) {
            $connection = stream_socket_client("tcp://127.0.0.1:$port");
            Assert::true(is_resource($connection));
            fwrite($connection, $request);
            $connections[] = $connection;
        }
        $statuses = array_map(static fn ($connection): string => substr((string) stream_get_contents($connection), 9, 3), $connections);
        // equal(), not same(): which status comes first depends on the order in which the workers answer.
        Assert::equal(
            ['403' => UserdbTestingLogin::MAX_FAILURES, '429' => 3 * UserdbTestingLogin::MAX_FAILURES],
            array_count_values($statuses),
        );
    }

    /**
     * Terminated, PHP's server leaves its workers running. Every run of the tests would leave them behind.
     */
    public function testStoppedServiceLeavesNoProcessBehind(): void {
        $this->startService($this->unitOptions(), ['PHP_CLI_SERVER_WORKERS' => '4']);
        $server = proc_get_status($this->services[0])['pid'];
        $workers = [];
        for ($attempt = 0; $attempt < 100 && count($workers) < 4; $attempt++) {
            usleep(50_000);
            $workers = $this->childProcesses($server);
        }
        Assert::count(4, $workers);

        $this->stopServices();
        $left = [$server, ...$workers];
        for ($attempt = 0; $attempt < 100 && $left !== []; $attempt++) {
            usleep(50_000);
            $left = array_values(array_intersect($left, array_keys($this->processParents())));
        }
        Assert::same([], $left);
    }

    private function verify(string $method, string $uri, string $cookie, int $now = self::NOW): UserdbTestingLoginResponse {
        return $this->send('GET', '/verify', ['X-Forwarded-Method' => $method, 'X-Forwarded-Uri' => $uri], cookie: $cookie, now: $now);
    }

    private function signIn(string $password, string $back, int $now = self::NOW, string $visitor = self::VISITOR): UserdbTestingLoginResponse {
        return $this->send('POST', '/testing-login', ['X-Forwarded-For' => $visitor], post: ['password' => $password, 'back' => $back], now: $now);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    private function send(
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array $post = [],
        string $cookie = '',
        int $now = self::NOW,
    ): UserdbTestingLoginResponse {
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1'];
        return $this->login->handle($server, $headers + ['Host' => self::HOST], $query, $post, [UserdbTestingLogin::COOKIE => $cookie], $now);
    }

    /** @return list<string> */
    private function headers(UserdbTestingLoginResponse $response, string $name): array {
        return array_values(array_filter($response->headers, static fn (string $header): bool => str_starts_with($header, "$name:")));
    }

    private function cookieValue(UserdbTestingLoginResponse $response): string {
        Assert::match('~^Set-Cookie: userdb_testing_login=([^;]+);~', $response->headers[1]);
        return explode(';', substr($response->headers[1], strlen('Set-Cookie: userdb_testing_login=')))[0];
    }

    /** @return list<string> options of PHP from ExecStart of the systemd unit */
    private function unitOptions(): array {
        $unit = (string) file_get_contents(__DIR__ . '/../scripts/userdb_testing_login.service');
        Assert::true(preg_match('~^ExecStart=/usr/bin/php (-d .+) -S 127\.0\.0\.1:\d+ \S+/userdb_testing_login\.php$~m', $unit, $match) === 1);
        return explode(' ', $match[1]);
    }

    /**
     * @param list<string> $phpOptions
     * @param array<string, string> $environment
     * @return int port the service listens on; tearDown() stops it
     */
    private function startService(array $phpOptions, array $environment = []): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        Assert::true(is_resource($socket));
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':') ?: '', 1);
        fclose($socket);

        $command = [PHP_BINARY, ...$phpOptions, '-S', "127.0.0.1:$port", __DIR__ . '/../scripts/userdb_testing_login.php'];
        $environment += ['USERDB_TESTING_LOGIN_PASSWORD_HASH' => $this->passwordHash, 'STATE_DIRECTORY' => $this->directory] + getenv();
        $null = ['file', '/dev/null', 'r+'];
        $service = proc_open($command, [$null, $null, $null], $pipes, null, $environment);
        Assert::true(is_resource($service));
        $this->services[] = $service;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port, $code, $message, 0.1);
            if ($connection !== false) {
                fclose($connection);
                return $port;
            }
            usleep(50_000);
        }
        throw new \RuntimeException("The service did not start on port $port.");
    }

    /** PHP's server does not stop its workers when it is terminated, so each of them is terminated too. */
    private function stopServices(): void {
        foreach ($this->services as $service) {
            $workers = $this->childProcesses(proc_get_status($service)['pid']);
            proc_terminate($service);
            proc_close($service);
            foreach ($workers as $worker) {
                posix_kill($worker, SIGTERM);
            }
        }
        $this->services = [];
    }

    /** @return list<int> */
    private function childProcesses(int $parent): array {
        return array_keys($this->processParents(), $parent, true);
    }

    /**
     * @return array<int, int> parent by process, for every process of the machine. Left out are processes that
     * have ended and only wait to be collected by their parent, which in a container may never happen.
     */
    private function processParents(): array {
        exec('ps -axo pid=,ppid=,stat=', $lines, $exitCode);
        Assert::same(0, $exitCode);
        $parents = [];
        foreach ($lines as $line) {
            $fields = sscanf($line, '%d %d %s');
            if ($fields === null) {
                continue;
            }
            [$process, $parent, $state] = $fields;
            if (str_starts_with((string) $state, 'Z') === false) {
                $parents[(int) $process] = (int) $parent;
            }
        }
        return $parents;
    }

    /**
     * @param list<string> $headers
     * @return array{int, list<string>, string} status, header lines and body
     */
    private function http(int $port, string $method, string $path, array $headers, string $body = ''): array {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body,
            'follow_location' => 0,
            'ignore_errors' => true,
        ]]);
        $responseBody = file_get_contents("http://127.0.0.1:$port$path", false, $context);
        Assert::true(is_string($responseBody));
        // Filled in by the HTTP wrapper of file_get_contents().
        $responseHeaders = $http_response_header;
        Assert::true(preg_match('~^HTTP/\S+ (\d{3})~', $responseHeaders[0], $status) === 1);
        return [(int) $status[1], $responseHeaders, $responseBody];
    }
}

(new TestingLoginTest())->run();
