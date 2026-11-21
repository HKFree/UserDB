<?php

declare(strict_types=1);

/**
 * Sign-in form in front of the instances of the shared testing server, see "Testing server" in readme.md.
 *
 * The reverse proxy asks VERIFY_PATH about every request from outside the HKFree network and serves LOGIN_PATH
 * from here. A visitor who knows the shared password gets a signed cookie that is valid for all instances.
 * The service runs on the server outside any checkout, so it uses nothing but PHP itself.
 */
final class UserdbTestingLogin
{
    public const VERIFY_PATH = '/verify';
    public const LOGIN_PATH = '/testing-login';
    public const SIGN_OUT_PATH = self::LOGIN_PATH . '/sign-out';
    public const COOKIE = 'userdb_testing_login';
    public const SESSION_SECONDS = 30 * 24 * 3600;
    public const MAX_FAILURES = 5;
    public const FAILURE_WINDOW_SECONDS = 15 * 60;

    /**
     * @param string $passwordHash bcrypt hash of the shared password; it also signs the cookies, so a new password signs everyone out
     * @param string $cookieDomain domain shared by the instances, or '' for a cookie of the one visited host
     * @param string $stateDirectory writable directory for the counters of failed attempts
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $passwordHash,
        private readonly string $cookieDomain,
        private readonly string $stateDirectory,
    ) {
        if (!self::isPasswordHash($passwordHash)) {
            throw new InvalidArgumentException('The password must be given as a bcrypt hash made by password_hash().');
        }
        if (preg_match('~^[a-z0-9.-]*$~D', $cookieDomain) !== 1) {
            throw new InvalidArgumentException('The cookie domain is not a host name.');
        }
        if (!is_dir($stateDirectory) || !is_writable($stateDirectory)) {
            throw new InvalidArgumentException('The state directory is missing or not writable.');
        }
    }

    /**
     * Answers one request. The parameters are the PHP superglobals of that request and its getallheaders().
     *
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $headers
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     * @param array<array-key, mixed> $cookies
     */
    public function handle(array $server, array $headers, array $query, array $post, array $cookies, int $now): UserdbTestingLoginResponse {
        $method = self::text($server, 'REQUEST_METHOD');
        $path = parse_url(self::text($server, 'REQUEST_URI'), PHP_URL_PATH);
        $signedIn = $this->isSignedIn(self::text($cookies, self::COOKIE), $now);
        $isRead = in_array($method, ['GET', 'HEAD'], true);

        return match (true) {
            $path === self::VERIFY_PATH => $this->verify($headers, $signedIn),
            $method === 'POST' && self::isFromAnotherSite($headers) => new UserdbTestingLoginResponse(
                403,
                ['Content-Type: text/plain; charset=utf-8'],
                "Formulář odeslala jiná stránka.\n",
            ),
            $path === self::LOGIN_PATH && $isRead && $signedIn => $this->signedInPage(self::localPath(self::text($query, 'back'))),
            $path === self::LOGIN_PATH && $isRead => $this->formPage(200, self::localPath(self::text($query, 'back')), ''),
            $path === self::LOGIN_PATH && $method === 'POST' => $this->signIn(
                self::text($post, 'password'),
                self::localPath(self::text($post, 'back')),
                self::header($headers, 'X-Forwarded-For') ?: self::text($server, 'REMOTE_ADDR'),
                $now,
            ),
            $path === self::SIGN_OUT_PATH && $method === 'POST' => new UserdbTestingLoginResponse(303, ['Location: ' . self::LOGIN_PATH, $this->cookie('', 0)]),
            default => new UserdbTestingLoginResponse(404, ['Content-Type: text/plain; charset=utf-8'], "Stránka neexistuje.\n"),
        };
    }

    /**
     * The proxy lets the request through to the instance on 2xx and returns any other answer to the visitor.
     * It describes the original request in the X-Forwarded-* headers.
     *
     * @param array<array-key, mixed> $headers
     */
    private function verify(array $headers, bool $signedIn): UserdbTestingLoginResponse {
        if ($signedIn) {
            return new UserdbTestingLoginResponse(204);
        }
        if (!in_array(self::header($headers, 'X-Forwarded-Method'), ['GET', 'HEAD'], true)) {
            return new UserdbTestingLoginResponse(
                403,
                ['Content-Type: text/plain; charset=utf-8'],
                'Nejste přihlášeni. Přihlaste se na ' . self::LOGIN_PATH . " a požadavek zopakujte.\n",
            );
        }
        $back = self::localPath(self::header($headers, 'X-Forwarded-Uri'));
        return new UserdbTestingLoginResponse(302, ['Location: ' . self::LOGIN_PATH . '?back=' . rawurlencode($back)]);
    }

    private function signIn(#[\SensitiveParameter] string $password, string $back, string $client, int $now): UserdbTestingLoginResponse {
        if (!$this->countAttempt($client, $now)) {
            $minutes = intdiv(self::FAILURE_WINDOW_SECONDS, 60);
            return $this->formPage(429, $back, "Příliš mnoho neúspěšných pokusů. Zkuste to znovu za $minutes minut.");
        }
        if (!password_verify($password, $this->passwordHash)) {
            return $this->formPage(403, $back, 'Nesprávné heslo.');
        }
        $this->clearAttempts($client);
        $expires = (string) ($now + self::SESSION_SECONDS);
        return new UserdbTestingLoginResponse(303, [
            'Location: ' . $back,
            $this->cookie($expires . '.' . $this->signature($expires), self::SESSION_SECONDS),
        ]);
    }

    private function isSignedIn(string $cookie, int $now): bool {
        if (preg_match('~^(\d{1,11})\.([0-9a-f]{64})$~D', $cookie, $match) !== 1) {
            return false;
        }
        return (int) $match[1] > $now && hash_equals($this->signature($match[1]), $match[2]);
    }

    private function signature(string $expires): string {
        return hash_hmac('sha256', self::COOKIE . '|' . $expires, $this->passwordHash);
    }

    private function cookie(string $value, int $maxAge): string {
        $domain = $this->cookieDomain === '' ? '' : '; Domain=' . $this->cookieDomain;
        return 'Set-Cookie: ' . self::COOKIE . "=$value; Max-Age=$maxAge; Path=/$domain; Secure; HttpOnly; SameSite=Lax";
    }

    /**
     * Counts the attempt as failed before the password is checked, so that attempts sent at the same moment
     * cannot all get past the limit; the right password then clears the counter.
     *
     * @return bool false when the client has used up its attempts, which is not counted as another one
     */
    private function countAttempt(string $client, int $now): bool {
        return $this->withCountersLocked(function () use ($client, $now): bool {
            $this->deleteExpiredCounters($now);
            $file = $this->counterFile($client);
            $times = array_map(intval(...), is_file($file) ? explode("\n", (string) file_get_contents($file)) : []);
            $recent = array_filter($times, static fn (int $time): bool => $time > $now - self::FAILURE_WINDOW_SECONDS);
            if (count($recent) >= self::MAX_FAILURES) {
                return false;
            }
            $counter = implode("\n", [...$recent, $now]);
            // A counter that cannot be kept, for example on a full disk, must not turn into unlimited attempts.
            if (file_put_contents($file, $counter) !== strlen($counter) || !touch($file, $now)) {
                throw new RuntimeException('The counter of sign-in attempts cannot be written.');
            }
            return true;
        });
    }

    private function clearAttempts(string $client): void {
        $this->withCountersLocked(function () use ($client): bool {
            $file = $this->counterFile($client);
            return !is_file($file) || unlink($file);
        });
    }

    /** Clients that never come back would otherwise leave their counters behind for ever. */
    private function deleteExpiredCounters(int $now): void {
        foreach (glob($this->stateDirectory . '/*.attempts') ?: [] as $file) {
            if (filemtime($file) <= $now - self::FAILURE_WINDOW_SECONDS) {
                unlink($file);
            }
        }
    }

    private function counterFile(string $client): string {
        return $this->stateDirectory . '/' . hash('sha256', $client) . '.attempts';
    }

    /**
     * One lock for all counters: the workers of PHP's server answer at the same time, and a lock on the counter
     * itself would be lost whenever the counter is deleted.
     *
     * @param Closure(): bool $change
     */
    private function withCountersLocked(Closure $change): bool {
        $lock = fopen($this->stateDirectory . '/lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('The counters of sign-in attempts cannot be locked.');
        }
        try {
            return $change();
        } finally {
            fclose($lock);
        }
    }

    private function formPage(int $status, string $back, string $error): UserdbTestingLoginResponse {
        $errorHtml = $error === '' ? '' : '<p class="error" role="alert">' . self::escape($error) . '</p>';
        $action = self::LOGIN_PATH;
        $backHtml = self::escape($back);
        // The fixed user name is there for password managers, which file a password under a name.
        return $this->page($status, <<<HTML
            <p>Testovací instance s demo daty. Ze sítě HKFree se otevírá bez přihlášení, odjinud heslem, které mají správci.</p>
            $errorHtml
            <form method="post" action="$action">
                <input type="hidden" name="back" value="$backHtml">
                <input type="text" name="username" value="hkfree" autocomplete="username" hidden>
                <label for="password">Heslo</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required autofocus>
                <button type="submit">Přihlásit</button>
            </form>
            HTML);
    }

    private function signedInPage(string $back): UserdbTestingLoginResponse {
        $action = self::SIGN_OUT_PATH;
        $backHtml = self::escape($back);
        return $this->page(200, <<<HTML
            <p>Jste přihlášeni. Přihlášení platí pro všechny instance testovacího serveru.</p>
            <p><a href="$backHtml">Pokračovat do aplikace</a></p>
            <form method="post" action="$action">
                <button type="submit" class="secondary">Odhlásit</button>
            </form>
            HTML);
    }

    private function page(int $status, string $content): UserdbTestingLoginResponse {
        $headers = [
            'Content-Type: text/html; charset=utf-8',
            'Cache-Control: no-store',
            "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'",
        ];
        return new UserdbTestingLoginResponse($status, $headers, <<<HTML
            <!DOCTYPE html>
            <html lang="cs">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>UserDB: testovací server</title>
                <style>
                    body { margin: 0; font: 16px/1.5 system-ui, sans-serif; color: #1f2933; background: #eef1f4; }
                    main { max-width: 24rem; margin: 12vh auto 0; padding: 2rem; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgb(0 0 0 / 15%); }
                    h1 { margin: 0 0 1rem; font-size: 1.25rem; }
                    label { display: block; margin-bottom: .25rem; font-weight: 600; }
                    input[type=password] { box-sizing: border-box; width: 100%; padding: .5rem; font: inherit; border: 1px solid #9aa5b1; border-radius: 4px; }
                    button { margin-top: 1rem; padding: .5rem 1.25rem; font: inherit; color: #fff; background: #0b5cad; border: 0; border-radius: 4px; cursor: pointer; }
                    button.secondary { margin-top: 0; color: #1f2933; background: #d9dee3; }
                    .error { padding: .5rem .75rem; color: #8a1c1c; background: #fde8e8; border-radius: 4px; }
                </style>
            </head>
            <body>
                <main>
                    <h1>UserDB: testovací server</h1>
                    $content
                </main>
            </body>
            </html>

            HTML);
    }

    /**
     * A complete bcrypt hash and nothing else: the hash also signs the cookies, so a shortened or mistyped one
     * would be a key that can be guessed. caddy hash-password writes the prefix $2a$, password_hash() $2y$.
     */
    private static function isPasswordHash(string $hash): bool {
        return preg_match('~^\$2[aby]\$\d\d\$[./A-Za-z0-9]{53}$~D', $hash) === 1;
    }

    /**
     * Browsers name the site that sent a form in Origin. Another site must not be able to sign the visitor out
     * or to use up the sign-in attempts of the visitor's address.
     *
     * @param array<array-key, mixed> $headers
     */
    private static function isFromAnotherSite(array $headers): bool {
        $origin = self::header($headers, 'Origin');
        return $origin !== '' && preg_replace('~^https?://~', '', $origin) !== self::header($headers, 'Host');
    }

    /** A path on the visited host, so that the way back cannot lead to another site. */
    private static function localPath(string $path): string {
        return preg_match('~^/(?![/\\\\])[^\x00-\x20\x7f]*$~D', $path) === 1 ? $path : '/';
    }

    /**
     * The header under exactly this name. $_SERVER cannot tell: PHP files X_Forwarded_For sent by a client
     * under the same key as the X-Forwarded-For set by the proxy.
     *
     * @param array<array-key, mixed> $headers
     */
    private static function header(array $headers, string $name): string {
        foreach ($headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0 && is_string($value)) {
                return $value;
            }
        }
        return '';
    }

    /** @param array<array-key, mixed> $values */
    private static function text(array $values, string $key): string {
        $value = $values[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    private static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

final class UserdbTestingLoginResponse
{
    /** @param list<string> $headers complete header lines */
    public function __construct(
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    public function send(): void {
        http_response_code($this->status);
        foreach ($this->headers as $header) {
            header($header);
        }
        echo $this->body;
    }
}
