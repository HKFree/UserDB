<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\InvalidPresenterException;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

/** #339: tlačítko Winbox u IP adres RouterBoardů a stránka s návodem k handleru odkazu winbox:. */
final class WinboxPresenterTest extends DatabaseTestCase
{
    private const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';
    private const MACOS = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';
    private const LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:145.0) Gecko/20100101 Firefox/145.0';
    private const ANDROID = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36';
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 19_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/19.0 Mobile/15E148 Safari/604.1';
    private const IPAD = 'Mozilla/5.0 (iPad; CPU OS 19_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/19.0 Mobile/15E148 Safari/604.1';
    private const HELP_URL = '/userdb/winbox/';

    private ActiveRow $area;
    private ActiveRow $ap;
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $this->area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($this->area->id);
        $this->actor = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->actor->id, 'SO', $this->area->id);
        $this->member = $this->fixtures->member($this->ap->id);
    }

    public function testDesktopSystemsGetOneWinboxButtonWithEncodedCredentials(): void {
        $this->routerboard('192.0.2.42', ['login' => 'admin', 'heslo' => 'a b;c%d#e"f<gá/']);
        foreach (['Windows' => self::WINDOWS, 'macOS' => self::MACOS, 'Linux' => self::LINUX, 'neznámý' => PresenterRunner::USER_AGENT] as $system => $userAgent) {
            $dom = $this->memberPage($userAgent);
            $buttons = $dom->find('a[href^="winbox:"]');
            Assert::count(1, $buttons, $system);
            // Každá část je zakódovaná zvlášť, takže středník, mezera ani % v hesle odkaz nerozbijí.
            Assert::same('winbox:192.0.2.42;admin;a%20b%3Bc%25d%23e%22f%3Cg%C3%A1%2F', (string) $buttons[0]['href'], $system);
            Assert::same('Otevřít Winbox', (string) $buttons[0]['title'], $system);
            Assert::same('Otevřít Winbox', (string) $buttons[0]['aria-label'], $system);
            Assert::count(1, $dom->find('a[href="' . self::HELP_URL . '"][title="Jak zprovoznit otevírání Winboxu z prohlížeče?"]'), $system);
            // Tlačítko CMD se staženým winbox.bat zmizelo, nápověda už nevede na wiki.
            Assert::count(0, $dom->find('[href*="downloadWinboxCmd"]'), $system);
            Assert::count(0, $dom->find('[href*="wiki.hkfree.org"]'), $system);
            Assert::count(0, $dom->find('a[href^="winbox://"]'), $system);
        }
    }

    public function testLinkLeavesOutMissingLoginAndPassword(): void {
        $this->routerboard('192.0.2.43', ['login' => 'admin', 'heslo' => '']);
        $this->routerboard('192.0.2.44', ['login' => '', 'heslo' => 'tajne']);
        $this->routerboard('192.0.2.45', ['login' => null, 'heslo' => null]);
        $hrefs = array_map(static fn (DomQuery $a): string => (string) $a['href'], $this->memberPage(self::WINDOWS)->find('a[href^="winbox:"]'));
        Assert::same(['winbox:192.0.2.43;admin', 'winbox:192.0.2.44', 'winbox:192.0.2.45'], $hrefs);
    }

    public function testLinkKeepsSubDelimitersAndEncodesColon(): void {
        // Nezakódované smějí zůstat jen znaky z README handleru; dvojtečka odděluje login a heslo v jeho URL formátu.
        $this->routerboard('192.0.2.54', ['login' => 'admin', 'heslo' => "p!\$&'()*+,=@:x~._-"]);
        $buttons = $this->memberPage(self::MACOS)->find('a[href^="winbox:"]');
        Assert::same(["winbox:192.0.2.54;admin;p!\$&'()*+,=@%3Ax~._-"], array_map(static fn (DomQuery $a): string => (string) $a['href'], $buttons));
    }

    public function testLinkLeavesOutLoginAndPasswordStartingWithDash(): void {
        // Winbox by je vzal jako přepínač, handler takový odkaz odmítne. Bez nich Winbox přihlášení předvyplní a čeká.
        $this->routerboard('192.0.2.51', ['login' => 'admin', 'heslo' => '-tajne']);
        $this->routerboard('192.0.2.52', ['login' => '-platform', 'heslo' => 'tajne']);
        $this->routerboard('192.0.2.53', ['login' => 'admin', 'heslo' => 'a-b']);
        $hrefs = array_map(static fn (DomQuery $a): string => (string) $a['href'], $this->memberPage(self::LINUX)->find('a[href^="winbox:"]'));
        Assert::same(['winbox:192.0.2.51;admin', 'winbox:192.0.2.52', 'winbox:192.0.2.53;admin;a-b'], $hrefs);
    }

    public function testWithoutCredentialsRightsLinkHasOnlyAddress(): void {
        $this->fixtures->insert('IPAdresa', [
            'Ap_id' => $this->ap->id, 'ip_adresa' => '192.0.2.50', 'login' => 'admin', 'heslo' => 'tajne-heslo',
            'TypZarizeni_id' => $this->fixtures->lookup('TypZarizeni', 'RouterBoard'),
        ]);
        $otherArea = $this->fixtures->area();
        $other = $this->fixtures->member($this->fixtures->ap($otherArea->id)->id);
        $this->fixtures->role($other->id, 'SO', $otherArea->id);
        $result = $this->presenters->ui($other->id, 'Ap', ['action' => 'show', 'id' => $this->ap->id], userAgent: self::LINUX);
        $buttons = self::ipRows($result->html)->find('a[href^="winbox:"]');
        Assert::count(1, $buttons);
        Assert::same('winbox:192.0.2.50', (string) $buttons[0]['href']);
        Assert::notContains('tajne-heslo', $result->html);
    }

    public function testAndroidKeepsMikrotikAppButton(): void {
        $this->routerboard('192.0.2.46', ['login' => 'admin', 'heslo' => 'tajne']);
        $dom = $this->memberPage(self::ANDROID);
        Assert::count(0, $dom->find('a[href^="winbox:1"]'));
        $app = $dom->find('a[href^="winbox://"]');
        Assert::count(1, $app);
        Assert::same('winbox://192.0.2.46/admin/tajne', (string) $app[0]['href']);
        Assert::count(1, $dom->find('a[href="' . self::HELP_URL . '?system=android"]'));
        Assert::count(0, $dom->find('[href*="wiki.hkfree.org"]'));
    }

    public function testIosHasNoWinboxButton(): void {
        $this->routerboard('192.0.2.47', ['login' => 'admin', 'heslo' => 'tajne']);
        foreach ([self::IPHONE, self::IPAD] as $userAgent) {
            $dom = $this->memberPage($userAgent);
            Assert::count(0, $dom->find('a[href^="winbox:"]'), $userAgent);
            Assert::count(0, $dom->find('a[href^="' . self::HELP_URL . '"]'), $userAgent);
        }
    }

    public function testOtherDevicesHaveNoWinboxButton(): void {
        $this->fixtures->ipAddress($this->member->id, '192.0.2.48', [
            'login' => 'admin', 'heslo' => 'tajne', 'TypZarizeni_id' => $this->fixtures->lookup('TypZarizeni', 'Ubiquiti'),
        ]);
        Assert::count(0, $this->memberPage(self::WINDOWS)->find('a[href^="winbox:"]'));
    }

    public function testWinboxCmdDownloadIsGone(): void {
        $ip = $this->routerboard('192.0.2.49', ['login' => 'admin']);
        Assert::exception(
            fn () => $this->presenters->ui($this->actor->id, 'File', ['action' => 'downloadWinboxCmd', 'id' => (string) $ip->id]),
            InvalidPresenterException::class,
        );
    }

    public function testHelpPageIsOpenToEveryRoleAndLinkedFromMenu(): void {
        $roles = $this->database->table('TypSpravceOblasti')->fetchAll();
        Assert::true(count($roles) > 0);
        foreach ($roles as $role) {
            $reader = $this->fixtures->member($this->ap->id);
            $this->fixtures->role($reader->id, $role->text);
            $result = $this->presenters->ui($reader->id, 'Winbox');
            Assert::same(200, $result->status, $role->text);
            $dom = DomQuery::fromHtml($result->html);
            Assert::same('Otevírání Winboxu z prohlížeče', trim((string) $dom->find('h1')[0]), $role->text);
            Assert::count(1, $dom->find('.navbar li.active > #navbar-tools a[href="' . self::HELP_URL . '"]'), $role->text);
        }
    }

    public function testHelpPageShowsOneInstallCommandPerSystemAndTestLink(): void {
        $dom = DomQuery::fromHtml($this->presenters->ui($this->actor->id, 'Winbox')->html);
        foreach (['windows', 'macos', 'linux', 'android'] as $system) {
            Assert::count(1, $dom->find('.tab-content > section.tab-pane#' . $system), $system);
            Assert::count(1, $dom->find('.nav-tabs a[href="#' . $system . '"][data-toggle="tab"]'), $system);
        }
        // Jeden řádek ke zkopírování do terminálu, jako u ploi-connect; stahuje poslední vydání handleru.
        $release = 'https://github.com/HKFree/winbox-uri-handler/releases/latest/download/';
        Assert::same([
            'irm ' . $release . 'winbox-uri-handler.ps1 -OutFile $env:TEMP\\winbox-uri-handler.ps1; powershell -ExecutionPolicy Bypass -File $env:TEMP\\winbox-uri-handler.ps1',
            'powershell -ExecutionPolicy Bypass -File "$env:LOCALAPPDATA\\Winbox URI Handler\\winbox-uri-handler.ps1" -Uninstall',
        ], self::commands($dom, '#windows'));
        foreach (['#macos', '#linux'] as $tab) {
            Assert::same([
                'curl -fsSL ' . $release . 'winbox-uri-handler.sh | bash',
                'curl -fsSL ' . $release . 'winbox-uri-handler.sh | bash -s -- --uninstall',
            ], self::commands($dom, $tab), $tab);
        }
        foreach (['#windows', '#macos', '#linux'] as $tab) {
            // Každý příkaz (instalace i odinstalace) má své tlačítko kopírování.
            // Přístupný název obsahuje viditelný text tlačítka a liší se podle příkazu.
            Assert::same(['Kopírovat příkaz k instalaci', 'Kopírovat příkaz k odinstalaci'], array_map(
                static fn (DomQuery $button): string => (string) $button['aria-label'],
                $dom->find($tab . ' .winbox-prikaz .copy-value-button'),
            ), $tab);
            Assert::count(1, $dom->find($tab . ' a.winbox-zkouska[href="winbox:192.0.2.1"]'), $tab);
        }
        Assert::count(1, $dom->find('a[href="https://github.com/HKFree/winbox-uri-handler"]'));
    }

    public function testHelpPageOpensTabOfVisitorsSystem(): void {
        $cases = [
            'Windows' => [self::WINDOWS, [], 'windows'],
            'macOS' => [self::MACOS, [], 'macos'],
            'Linux' => [self::LINUX, [], 'linux'],
            'Android' => [self::ANDROID, [], 'android'],
            'neznámý systém' => [PresenterRunner::USER_AGENT, [], 'windows'],
            'iPhone' => [self::IPHONE, [], 'windows'],
            // Odkaz z tlačítka MikroTik app vybere záložku sám, i na počítači.
            'parametr system' => [self::WINDOWS, ['system' => 'android'], 'android'],
            'neplatný parametr' => [self::LINUX, ['system' => 'ios'], 'linux'],
        ];
        foreach ($cases as $name => [$userAgent, $parameters, $tab]) {
            $dom = DomQuery::fromHtml($this->presenters->ui($this->actor->id, 'Winbox', $parameters, userAgent: $userAgent)->html);
            Assert::same([$tab], array_map(static fn (DomQuery $pane): string => (string) $pane['id'], $dom->find('.tab-pane.active')), $name);
            Assert::count(1, $dom->find('.nav-tabs li.active a[href="#' . $tab . '"][aria-selected="true"]'), $name);
        }
    }

    /** @return list<string> */
    private static function commands(DomQuery $dom, string $section): array {
        return array_map(static fn (DomQuery $code): string => (string) $code, $dom->find($section . ' .winbox-prikaz .copy-value'));
    }

    /** @param array<string, mixed> $values */
    private function routerboard(string $address, array $values = []): ActiveRow {
        return $this->fixtures->ipAddress($this->member->id, $address, $values + [
            'TypZarizeni_id' => $this->fixtures->lookup('TypZarizeni', 'RouterBoard'),
        ]);
    }

    private function memberPage(string $userAgent): DomQuery {
        $result = $this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'show', 'id' => $this->member->id], userAgent: $userAgent);
        Assert::same(200, $result->status);
        return self::ipRows($result->html);
    }

    /** Jen řádky tabulky IP adres: celá stránka detailu má duplicitní id, se kterými DomQuery skončí chybou. */
    private static function ipRows(string $html): DomQuery {
        preg_match_all('~<tr id="highlightable-ip.*?</tr>~s', $html, $rows);
        Assert::true($rows[0] !== []);
        return DomQuery::fromHtml('<table>' . implode('', $rows[0]) . '</table>');
    }
}

(new WinboxPresenterTest())->run();
