<?php

declare(strict_types=1);

/**
 * The switching page. Self-contained on purpose: it has to work when the application cannot start,
 * so it uses no template, stylesheet or script of the application.
 */
final class UserdbDemoPersonaPage
{
    private const STYLE = <<<'CSS'
        *{box-sizing:border-box}
        body{margin:0;background:#f5f5f5;color:#333;font:14px/1.5 "Helvetica Neue",Helvetica,Arial,sans-serif}
        a{color:#337ab7}
        .bar{background:#222;color:#9d9d9d;padding:0 20px;display:flex;align-items:center;gap:16px;min-height:50px}
        .bar strong{color:#fff;font-size:18px;font-weight:500}
        .bar span{flex:1}
        .bar a{color:#fff;text-decoration:none;border:1px solid #555;border-radius:4px;padding:6px 12px}
        .bar a:hover,.bar a:focus{background:#333}
        main{max-width:1180px;margin:0 auto;padding:24px 20px 48px}
        h1{font-size:28px;font-weight:500;margin:0 0 6px}
        .lead{margin:0 0 20px;color:#555;max-width:70ch}
        .now{background:#fff;border:1px solid #ddd;border-left:4px solid #337ab7;border-radius:4px;padding:12px 16px;margin:0 0 8px}
        .now b{font-size:16px}
        .now .tag{margin-left:4px}
        h2{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#777;margin:28px 0 10px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px}
        .persona{display:flex;flex-direction:column;gap:8px;background:#fff;border:1px solid #ddd;border-radius:4px;
            padding:14px 16px;color:inherit;text-decoration:none;transition:border-color .12s,box-shadow .12s}
        .persona:hover,.persona:focus{border-color:#337ab7;box-shadow:0 2px 8px rgba(0,0,0,.12);outline:0}
        .persona[aria-current]{border-color:#337ab7;box-shadow:inset 0 0 0 1px #337ab7}
        .head{display:flex;align-items:baseline;gap:8px}
        .name{font-size:16px;font-weight:500;color:#222;flex:1}
        .id{font:12px/1 Menlo,Monaco,Consolas,monospace;color:#777}
        .tags{display:flex;flex-wrap:wrap;gap:4px}
        .tag{font-size:11px;font-weight:700;line-height:1;padding:4px 7px;border-radius:3px;color:#fff;background:#777}
        .tag.all{background:#337ab7}.tag.area{background:#5cb85c}.tag.support{background:#5bc0de}
        .tag.off{background:#fff;color:#a94442;box-shadow:inset 0 0 0 1px #ebccd1;font-weight:500}
        .tag.active{background:#f0ad4e}
        .note{margin:0;color:#555}
        footer{margin-top:32px;color:#777;font-size:13px}
        @media (prefers-reduced-motion:reduce){.persona{transition:none}}
        CSS;

    public function __construct(private readonly UserdbDemoPersonas $active) {
    }

    /**
     * Answers GET /demo-osoba: the list, or with `id` the cookie of the chosen persona and a redirect home.
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $headers
     */
    public function serve(string $cookieName, array $query, array $server, array $headers): void {
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        if (($server['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }
        if (!array_key_exists('id', $query) || self::isFromAnotherSite($headers)) {
            echo $this->render();
            return;
        }
        setcookie($cookieName, (string) (new UserdbDemoPersonas($query['id']))->id(), [
            'path' => '/',
            'secure' => self::isHttps($server, $headers),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: /', true, 302);
    }

    /**
     * The testing reverse proxy connects over loopback and supplies X-Forwarded-Proto.
     * Read the actual header name, like UserdbTestingLogin, to distinguish underscores from hyphens.
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $headers
     */
    public static function isHttps(array $server, array $headers): bool {
        $https = $server['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && $https !== '0' && strcasecmp($https, 'off') !== 0) {
            return true;
        }
        return in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && strcasecmp(self::header($headers, 'X-Forwarded-Proto'), 'https') === 0;
    }

    /**
     * A link on another site must not switch the persona behind the tester's back. Browsers that do not send
     * Sec-Fetch-Site, and addresses typed or bookmarked (`none`), are let through.
     * @param array<array-key, mixed> $headers
     */
    private static function isFromAnotherSite(array $headers): bool {
        return !in_array(strtolower(self::header($headers, 'Sec-Fetch-Site')), ['', 'same-origin', 'none'], true);
    }

    /** @param array<array-key, mixed> $headers */
    private static function header(array $headers, string $wanted): string {
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value) && strcasecmp($name, $wanted) === 0) {
                return $value;
            }
        }
        return '';
    }

    private function render(): string {
        return '<!doctype html><html lang="cs"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Demo osoby | HKFree UserDB</title><style>' . self::STYLE . '</style></head><body>'
            . '<div class="bar"><strong>HKFree UserDB</strong><span>demo osoby</span><a href="/">Zpět do aplikace</a></div>'
            . '<main><h1>Za koho se chceš dívat?</h1>'
            . '<p class="lead">Výběr platí pro tento prohlížeč a tuto instanci. Oprávnění se při každém požadavku'
            . ' načítají z demo databáze, takže po kliknutí rovnou vidíš aplikaci očima vybrané osoby.</p>'
            . $this->current() . $this->groups()
            . '<footer>Přepínání existuje jen na lokálním webu a na testovacích instancích.'
            . ' Kdyby aplikace po přepnutí hlásila chybu přihlášení, vrať se sem a vyber jinou osobu.</footer>'
            . '</main></body></html>';
    }

    private function current(): string {
        $persona = UserdbDemoPersonas::all()[$this->active->id()];
        return '<p class="now">Teď jsi <b>' . self::escape($persona['name']) . '</b> '
            . '<span class="id">' . $this->active->id() . '</span> ' . $this->tags($persona) . '</p>';
    }

    private function groups(): string {
        $html = '';
        foreach (UserdbDemoPersonas::GROUPS as $group => $heading) {
            $cards = '';
            foreach (UserdbDemoPersonas::all() as $id => $persona) {
                if ($persona['group'] === $group) {
                    $cards .= $this->card($id, $persona);
                }
            }
            $html .= '<h2>' . self::escape($heading) . '</h2><div class="grid">' . $cards . '</div>';
        }
        return $html;
    }

    /** @param array{name: string, group: string, roles: list<string>, inactive?: list<string>, note: string} $persona */
    private function card(int $id, array $persona): string {
        $active = $id === $this->active->id();
        return '<a class="persona" href="' . self::escape(UserdbDemoPersonas::PATH . '?id=' . $id) . '"'
            . ($active ? ' aria-current="true"' : '') . '>'
            . '<span class="head"><span class="name">' . self::escape($persona['name']) . '</span>'
            . '<span class="id">' . $id . '</span></span>'
            . '<span class="tags">' . $this->tags($persona) . ($active ? '<span class="tag active">aktivní</span>' : '') . '</span>'
            . '<span class="note">' . self::escape($persona['note']) . '</span></a>';
    }

    /** @param array{name: string, group: string, roles: list<string>, inactive?: list<string>, note: string} $persona */
    private function tags(array $persona): string {
        $html = '';
        foreach ($persona['roles'] as $role) {
            $html .= '<span class="tag ' . self::roleClass($role) . '">' . self::escape($role) . '</span>';
        }
        foreach ($persona['inactive'] ?? [] as $role) {
            $html .= '<span class="tag off">' . self::escape($role) . '</span>';
        }
        return $html;
    }

    /** Blue for roles that open everything, green for roles of an area, grey for roles that only allow signing in. */
    private static function roleClass(string $role): string {
        return match (true) {
            in_array($role, ['VV', 'TECH', 'KONTROLA'], true) => 'all',
            preg_match('~^Z?SO-~', $role) === 1 => 'area',
            $role === 'EXTSUPPORT' => 'support',
            default => 'plain',
        };
    }

    private static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
