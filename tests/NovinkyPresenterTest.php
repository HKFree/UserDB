<?php

declare(strict_types=1);

namespace Test;

use App\Services\Novinky;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class NovinkyPresenterTest extends DatabaseTestCase
{
    private string $directory;
    private ActiveRow $ap;

    protected function setUp(): void {
        parent::setUp();
        $this->directory = TestEnvironment::TEMP_DIR . '/novinky-presenter-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
        $this->presenters = new PresenterRunner($this->database, $this->container, $this->directory);
        $area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($area->id);
    }

    protected function tearDown(): void {
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testEveryRoleCanReadFixtureRelease(): void {
        file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- Změna dostupná každé roli");
        $roles = $this->database->table('TypSpravceOblasti')->fetchAll();
        Assert::true(count($roles) > 0);
        foreach ($roles as $role) {
            $member = $this->fixtures->member($this->ap->id);
            $this->fixtures->role($member->id, $role->text);
            $result = $this->presenters->ui($member->id, 'Novinky');
            Assert::same(200, $result->status, $role->text);
            $dom = DomQuery::fromHtml($result->html);
            Assert::same(['Změna dostupná každé roli'], $this->texts($dom, '.novinky-polozka-text'));
            Assert::same(['22. 11. 2026'], $this->texts($dom, '.novinky-vydani time'));
        }
    }

    public function testMemberWithoutRoleIsStillRejected(): void {
        $member = $this->fixtures->member($this->ap->id);
        Assert::exception(fn () => $this->presenters->ui($member->id, 'Novinky'), AuthenticationException::class, 'User not allowed.');
    }

    public function testEmptyDirectoryAndConfiguredService(): void {
        $result = $this->presenters->ui($this->reader(), 'Novinky');
        Assert::same(200, $result->status);
        $dom = DomQuery::fromHtml($result->html);
        Assert::same(['Zatím tu nejsou žádné novinky.'], $this->texts($dom, '.novinky .alert'));
        Assert::count(0, $dom->find('.novinky-vydani'));

        file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- Pouze z testovacího adresáře");
        $container = TestEnvironment::container($this->database, novinkyAdresar: $this->directory);
        Assert::same('Pouze z testovacího adresáře', $container->getByType(Novinky::class)->nacti()[0]->novinky[0]->text);
    }

    public function testPlainTextIsEscapedAndLongDetailIsComplete(): void {
        $text = '<script>alert("text")</script> **text**';
        $detail = '<script>alert("detail")</script> & ' . str_repeat('DlouhýDetail', 1000);
        file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- $text\n  $detail");
        $result = $this->presenters->ui($this->reader(), 'Novinky');
        $dom = DomQuery::fromHtml($result->html);
        Assert::count(0, $dom->find('.novinky script'));
        Assert::same([$text], $this->texts($dom, '.novinky-polozka-text'));
        Assert::same([$detail], $this->texts($dom, '.novinky-detail'));
        Assert::contains('&lt;script&gt;alert("text")&lt;/script&gt;', $result->html);
        Assert::contains('&lt;script&gt;alert("detail")&lt;/script&gt; &amp;', $result->html);
        Assert::count(0, $dom->find('.novinky-cislo'));
    }

    public function testReleaseAndSectionOrderEmptySectionsAndNumberLink(): void {
        file_put_contents($this->directory . '/2026-11-21.md', "## Opravy\n- Starší oprava\n## Novinky\n");
        file_put_contents($this->directory . '/2026-11-22.md', "## Opravy\n- Novější oprava\n## Novinky\n- #266 - Novější novinka\n  Podrobnosti");
        file_put_contents($this->directory . '/2026-11-20.md', "## Novinky\n- Nejstarší novinka\n## Opravy\n");
        $result = $this->presenters->ui($this->reader(), 'Novinky');
        $dom = DomQuery::fromHtml($result->html);
        Assert::same(['Novinky'], $this->texts($dom, '.novinky h1'));
        Assert::same(['22. 11. 2026', '21. 11. 2026', '20. 11. 2026'], $this->texts($dom, '.novinky-vydani time'));
        Assert::same(['Novinky', 'Opravy', 'Opravy', 'Novinky'], $this->texts($dom, '.novinky-vydani h3'));
        Assert::same(['Novější novinka', 'Novější oprava', 'Starší oprava', 'Nejstarší novinka'], $this->texts($dom, '.novinky-polozka-text'));
        Assert::same(['Podrobnosti'], $this->texts($dom, '.novinky-detail'));
        Assert::same(['#266'], $this->texts($dom, '.novinky-cislo'));
        $links = $dom->find('.novinky-cislo');
        Assert::same('https://github.com/HKFree/UserDB/issues/266', (string) $links[0]['href']);
    }

    public function testToolsMenuLinksToNewsForLoginOnlyRole(): void {
        $result = $this->presenters->ui($this->reader(), 'Homepage');
        Assert::same(200, $result->status);
        $dom = DomQuery::fromHtml($result->html);
        $links = [];
        foreach ($dom->find('.navbar .dropdown-menu a') as $link) {
            $links[trim((string) $link)] = (string) $link['href'];
        }
        Assert::same('/userdb/novinky/', $links['Novinky'] ?? null);
        // The link lives only in the Nástroje menu, not next to it in the bar.
        Assert::count(1, array_keys($this->texts($dom, '.navbar a'), 'Novinky', true));
    }

    private function reader(): int {
        $member = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($member->id, 'DRUŽSTEVNÍK');
        return $member->id;
    }

    /** @return list<string> */
    private function texts(DomQuery $dom, string $selector): array {
        return array_map(static fn (\SimpleXMLElement $element): string => trim((string) $element), $dom->find($selector));
    }
}

(new NovinkyPresenterTest())->run();
