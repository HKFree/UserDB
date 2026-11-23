<?php

declare(strict_types=1);

namespace Test;

use App\MapiModule\Odpoved;
use App\Router\RouterFactory;
use Nette\Application;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Http;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Tester\Environment;

final class PresenterResult
{
    /**
     * @param list<string> $flashes
     * @param list<string> $flashTypes
     */
    public function __construct(
        public readonly Application\Response $response,
        public readonly string $html,
        public readonly int $status,
        public readonly ?string $redirectUrl,
        public readonly mixed $json,
        public readonly ?IIdentity $identity,
        public readonly array $flashes,
        public readonly array $flashTypes,
    ) {
    }
}

/**
 * Denied or missing UI pages throw BadRequestException with the HTTP code; UI authentication
 * failures throw AuthenticationException. These exceptions do not become result statuses.
 * Recorded mail, SMS and contract calls are read from the test case's container.
 */
final class PresenterRunner
{
    private static ?string $activeRequest = null;
    private static bool $shutdownRegistered = false;

    /** @param (\Closure(Presenter): void)|null $preparePresenter */
    public function __construct(private Explorer $database, private Container $container, private ?string $novinkyAdresar = null, private ?\Closure $preparePresenter = null) {
        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(static function (): void {
                if (self::$activeRequest !== null) {
                    Environment::handleException(new \RuntimeException('Presenter request terminated before returning: ' . self::$activeRequest));
                }
            });
        }
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $post
     */
    public function ui(int $uid, string $presenter, array $parameters = [], string $method = 'GET', array $post = [], bool $sameSite = true, bool $ajax = false): PresenterResult {
        $member = $this->database->table('Uzivatel')->get($uid);
        return $this->run($presenter, $parameters, $method, $post, $sameSite, [
            'HTTP_UID' => (string) $uid,
            'HTTP_GIVENNAME' => $member->nick ?? 'Unknown test member',
            'HTTP_INITIALS' => 'test-only',
        ], $ajax);
    }

    /**
     * @param array{string, string}|null $credentials
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $post
     */
    public function api(string $presenter, ?array $credentials = null, array $parameters = [], string $method = 'GET', array $post = [], bool $sameSite = true): PresenterResult {
        return $this->run($presenter, $parameters, $method, $post, $sameSite, $credentials === null ? [] : [
            'PHP_AUTH_USER' => $credentials[0],
            'PHP_AUTH_PW' => $credentials[1],
            'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode(implode(':', $credentials)),
        ]);
    }

    /**
     * A request to /userdb/mapi/graphql. Without $uid nobody is signed in; headers are sent exactly as given.
     * Read response headers from the returned App\MapiModule\Odpoved, the CLI does not send them.
     * @param array<string, string> $headers
     * @param array<string, object> $services services replaced for this request only
     */
    public function mapi(?int $uid, ?string $body, array $headers = [], string $method = 'POST', array $services = []): PresenterResult {
        $server = [];
        if ($uid !== null) {
            $member = $this->database->table('Uzivatel')->get($uid);
            $server = [
                'HTTP_UID' => (string) $uid,
                'HTTP_GIVENNAME' => $member->nick ?? 'Unknown test member',
                'HTTP_INITIALS' => 'test-only',
            ];
        }
        return $this->run('Mapi:Graphql', [], $method, [], false, $server, headers: $headers, body: $body, services: $services);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $post
     * @param array<string, string> $server
     * @param array<string, string> $headers
     * @param array<string, object> $services
     */
    private function run(string $name, array $parameters, string $method, array $post, bool $sameSite, array $server, bool $ajax = false, array $headers = [], ?string $body = null, array $services = []): PresenterResult {
        if (self::$activeRequest !== null) {
            throw new \LogicException('Nested presenter runs are not supported.');
        }
        $parameters += ['action' => 'default'];
        $method = strtoupper($method);
        $url = (new RouterFactory())->createRouter('/userdb')->constructUrl(
            ['presenter' => $name] + $parameters,
            new Http\UrlScript('http://localhost/'),
        );
        if ($url === null) {
            throw new \InvalidArgumentException("No URL for $name.");
        }
        $requestUrl = new Http\UrlScript($url);
        $savedServer = $_SERVER;
        $savedSession = $_SESSION ?? null;
        $savedStatus = http_response_code();
        $bufferLevel = ob_get_level();
        self::$activeRequest = $name . ':' . $parameters['action'];
        try {
            foreach (['HTTP_UID', 'HTTP_GIVENNAME', 'HTTP_INITIALS', 'PHP_AUTH_USER', 'PHP_AUTH_PW', 'HTTP_AUTHORIZATION'] as $key) {
                unset($_SERVER[$key]);
            }
            $_SERVER = $server + [
                'HTTP_HOST' => 'localhost',
                'HTTP_USER_AGENT' => 'UserDB tests',
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $requestUrl->getPath() . ($requestUrl->getQuery() !== '' ? '?' . $requestUrl->getQuery() : ''),
            ] + $_SERVER;
            $_SESSION = [];
            http_response_code(200);
            $request = new Http\Request(
                $requestUrl,
                post: $post,
                cookies: $sameSite ? ['_nss' => '1'] : [],
                headers: $headers
                    + ['Host' => 'localhost', 'User-Agent' => 'UserDB tests']
                    + (isset($server['HTTP_AUTHORIZATION']) ? ['Authorization' => $server['HTTP_AUTHORIZATION']] : [])
                    + ($ajax ? ['X-Requested-With' => 'XMLHttpRequest'] : []),
                method: $method,
                remoteAddress: '127.0.0.1',
                rawBodyCallback: static fn (): ?string => $body,
            );
            // A new container also replaces the user, session and request-bound Latte factory.
            $container = TestEnvironment::container($this->database, $request, $this->container, $this->novinkyAdresar, $services);
            $presenter = $container->getByType(Application\IPresenterFactory::class)->createPresenter($name);
            if ($presenter instanceof Presenter) {
                $presenter->autoCanonicalize = false;
                if ($this->preparePresenter !== null) {
                    ($this->preparePresenter)($presenter);
                }
            }
            ob_start();
            $response = $presenter->run(new Application\Request($name, $method, $parameters, $post));
            $httpResponse = $container->getByType(Http\Response::class);
            if ($response instanceof TextResponse || $response instanceof Odpoved) {
                $response->send($request, $httpResponse);
            }
            $html = (string) ob_get_contents();
            $flashes = [];
            $flashTypes = [];
            if ($presenter instanceof Presenter && $presenter->hasFlashSession()) {
                foreach ($presenter->getFlashSession() as $messages) {
                    foreach ($messages as $flash) {
                        $flashes[] = (string) $flash->message;
                        $flashTypes[] = $flash->type;
                    }
                }
            }
            return new PresenterResult(
                $response,
                $html,
                $response instanceof RedirectResponse ? $response->getCode() : $httpResponse->getCode(),
                $response instanceof RedirectResponse ? $response->getUrl() : null,
                match (true) {
                    $response instanceof JsonResponse => $response->getPayload(),
                    $response instanceof Odpoved => $response->telo,
                    default => null,
                },
                $container->getByType(User::class)->getIdentity(),
                $flashes,
                $flashTypes,
            );
        } finally {
            self::$activeRequest = null;
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $_SERVER = $savedServer;
            if ($savedSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $savedSession;
            }
            http_response_code(is_int($savedStatus) ? $savedStatus : 200);
        }
    }
}
