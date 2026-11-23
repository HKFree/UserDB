<?php

declare(strict_types=1);

/**
 * Sign-in form in front of a pull request preview, see "Pull request previews" in readme.md.
 *
 * scripts/userdb_preview_router.php asks guard() about every request before the application sees it. Visitors
 * from the open networks come in as they are; anyone else needs the shared password and gets a signed cookie
 * that is valid for all previews. The form uses nothing but PHP itself, so it answers before Composer's autoloader.
 */
final class UserdbTestingLogin
{
    public const LOGIN_PATH = '/testing-login';
    public const SIGN_OUT_PATH = self::LOGIN_PATH . '/sign-out';
    public const COOKIE = 'userdb_testing_login';
    public const SESSION_SECONDS = 30 * 24 * 3600;
    public const MAX_FAILURES = 5;
    public const FAILURE_WINDOW_SECONDS = 15 * 60;

    private const VARIABLES = [
        'USERDB_TESTING_LOGIN_PASSWORD_HASH',
        'USERDB_TESTING_LOGIN_COOKIE_DOMAIN',
        'USERDB_TESTING_LOGIN_STATE',
        'USERDB_TESTING_LOGIN_OPEN_NETWORKS',
    ];

    /**
     * @param string $passwordHash bcrypt hash of the shared password; it also signs the cookies, so a new password signs everyone out
     * @param string $cookieDomain domain shared by the instances, or '' for a cookie of the one visited host
     * @param string $stateDirectory writable directory for the counters of failed attempts
     * @param list<string> $openNetworks networks whose visitors come in without signing in, as address/prefix length
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $passwordHash,
        private readonly string $cookieDomain,
        private readonly string $stateDirectory,
        private readonly array $openNetworks = [],
    ) {
        if (self::isPasswordHash($passwordHash) === false) {
            throw new InvalidArgumentException('The password must be given as a bcrypt hash made by password_hash().');
        }
        if (preg_match('~^[a-z0-9.-]*$~D', $cookieDomain) !== 1) {
            throw new InvalidArgumentException('The cookie domain is not a host name.');
        }
        if (is_dir($stateDirectory) === false || is_writable($stateDirectory) === false) {
            throw new InvalidArgumentException('The state directory is missing or not writable.');
        }
        foreach ($openNetworks as $network) {
            if (self::networkRange($network) === null) {
                throw new InvalidArgumentException('An open network must be written as address/prefix length.');
            }
        }
    }

    /**
     * The form set up by the variables of the preview. They are taken out of the environment of the request:
     * the local web shows its environment to every visitor in the Tracy bar, and the hash signs the cookies.
     */
    public static function fromEnvironment(): self {
        [$passwordHash, $cookieDomain, $stateDirectory, $openNetworks] = array_map(
            static fn (string $name): string => (string) getenv($name),
            self::VARIABLES,
        );
        foreach (self::VARIABLES as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
        $networks = preg_split('~\s*,\s*~', trim($openNetworks), -1, PREG_SPLIT_NO_EMPTY);
        return new self($passwordHash, $cookieDomain, $stateDirectory, $networks === false ? [] : $networks);
    }

    /**
     * Decides about one request. The parameters are the PHP superglobals of that request and its getallheaders().
     *
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $headers
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     * @param array<array-key, mixed> $cookies
     * @return UserdbTestingLoginResponse|null the answer to send instead of the application, null when the request goes on to it
     */
    public function guard(array $server, array $headers, array $query, array $post, array $cookies, int $now): ?UserdbTestingLoginResponse {
        $method = self::text($server, 'REQUEST_METHOD');
        $uri = self::text($server, 'REQUEST_URI');
        $path = parse_url($uri, PHP_URL_PATH);
        $signedIn = $this->isSignedIn(self::text($cookies, self::COOKIE), $now);
        $client = self::clientAddress($server, $headers);
        if ($path === self::LOGIN_PATH || (is_string($path) && str_starts_with($path, self::LOGIN_PATH . '/'))) {
            return $this->form($method, $path, $headers, $query, $post, $signedIn, $client, $now);
        }
        if ($signedIn || $this->isFromOpenNetwork($client)) {
            return null;
        }
        // Only a page that was asked for can be asked for again after signing in.
        if (in_array($method, ['GET', 'HEAD'], true) === false) {
            return new UserdbTestingLoginResponse(
                403,
                ['Content-Type: text/plain; charset=utf-8'],
                'Nejste přihlášeni. Přihlaste se na ' . self::LOGIN_PATH . " a požadavek zopakujte.\n",
            );
        }
        return new UserdbTestingLoginResponse(302, ['Location: ' . self::LOGIN_PATH . '?back=' . rawurlencode(self::localPath($uri))]);
    }

    /**
     * @param array<array-key, mixed> $headers
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     */
    private function form(string $method, string $path, array $headers, array $query, array $post, bool $signedIn, string $client, int $now): UserdbTestingLoginResponse {
        $isRead = in_array($method, ['GET', 'HEAD'], true);

        return match (true) {
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
                $client,
                $now,
            ),
            $path === self::SIGN_OUT_PATH && $method === 'POST' => new UserdbTestingLoginResponse(303, ['Location: ' . self::LOGIN_PATH, $this->cookie('', 0)]),
            default => new UserdbTestingLoginResponse(404, ['Content-Type: text/plain; charset=utf-8'], "Stránka neexistuje.\n"),
        };
    }

    /**
     * The address the visitor comes from. A proxy in front of the preview connects from a private address
     * and appends the address of its visitor to X-Forwarded-For, so only the last address there is the proxy's
     * word; the ones before it are whatever the visitor sent. Whoever connects from a public address is the
     * visitor itself, and the header is then only its claim.
     *
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $headers
     */
    private static function clientAddress(array $server, array $headers): string {
        $connecting = self::text($server, 'REMOTE_ADDR');
        $forwardedFor = self::header($headers, 'X-Forwarded-For');
        $isPublic = filter_var($connecting, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        if ($forwardedFor === '' || $isPublic) {
            return $connecting;
        }
        $addresses = explode(',', $forwardedFor);
        return trim($addresses[count($addresses) - 1]);
    }

    private function isFromOpenNetwork(string $client): bool {
        $address = filter_var($client, FILTER_VALIDATE_IP) === false ? false : inet_pton($client);
        if ($address === false) {
            return false;
        }
        foreach ($this->openNetworks as $network) {
            $range = self::networkRange($network);
            if ($range !== null && strlen($address) === strlen($range[0]) && ($address & $range[1]) === $range[0]) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{string, string}|null packed first address of the network and its mask, null for anything
     * but address/prefix length; an address with bits set behind the prefix is taken as the network it lies in
     */
    private static function networkRange(string $network): ?array {
        if (preg_match('~^([0-9a-f.:]+)/(\d{1,3})$~iD', $network, $match) !== 1) {
            return null;
        }
        $address = filter_var($match[1], FILTER_VALIDATE_IP) === false ? false : inet_pton($match[1]);
        $prefix = (int) $match[2];
        if ($address === false || $prefix > 8 * strlen($address)) {
            return null;
        }
        $mask = str_pad(str_repeat("\xff", intdiv($prefix, 8)) . ($prefix % 8 === 0 ? '' : chr(0xff << (8 - $prefix % 8) & 0xff)), strlen($address), "\x00");
        return [$address & $mask, $mask];
    }

    private function signIn(#[\SensitiveParameter] string $password, string $back, string $client, int $now): UserdbTestingLoginResponse {
        if ($this->countAttempt($client, $now) === false) {
            $minutes = intdiv(self::FAILURE_WINDOW_SECONDS, 60);
            return $this->formPage(429, $back, "Příliš mnoho neúspěšných pokusů. Zkuste to znovu za $minutes minut.");
        }
        if (password_verify($password, $this->passwordHash) === false) {
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
            if (file_put_contents($file, $counter) !== strlen($counter) || touch($file, $now) === false) {
                throw new RuntimeException('The counter of sign-in attempts cannot be written.');
            }
            return true;
        });
    }

    private function clearAttempts(string $client): void {
        $this->withCountersLocked(function () use ($client): bool {
            $file = $this->counterFile($client);
            return is_file($file) === false || unlink($file);
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
        if ($lock === false || flock($lock, LOCK_EX) === false) {
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
            <p>Jste přihlášeni. Přihlášení platí pro všechny testovací instance.</p>
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
                <title>UserDB: testovací instance</title>
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
                    <h1>UserDB: testovací instance</h1>
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
