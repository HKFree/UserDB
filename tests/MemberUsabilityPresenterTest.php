<?php

declare(strict_types=1);

/** @testCase */

namespace Test;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class MemberUsabilityPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->scenario->member->update([
            'jmeno' => 'SoukroméJméno', 'prijmeni' => 'SoukroméPříjmení', 'nick' => 'verejny-nick',
            'email2' => 'secondary@example.invalid', 'cislo_clenske_karty' => '123456',
            'regform_downloaded_password_sent' => 0,
        ]);
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $this->scenario->member->id, 'TypPohybuNaUctu_id' => 4,
            'castka' => -987654, 'datum' => '2026-10-01', 'druzstvo' => 1, 'spolek' => 0,
        ]);
    }

    /** @return list<array{string, string, string}> */
    public function endpointCases(): array {
        return [['', 'Uzivatel', 'show'], ['', 'UzivatelList', 'list'], ['/userdb', 'Uzivatel', 'show']];
    }

    /** @dataProvider endpointCases */
    public function testRoutedLabelEndpoints(string $prefix, string $presenter, string $action): void {
        $s = $this->scenario;
        $runner = new PresenterRunner($this->database, $this->container, urlPrefix: $prefix);
        $result = $runner->ui($s->actors['vv']->id, $presenter, [
            'action' => $action, 'id' => $presenter === 'Uzivatel' ? $s->member->id : $s->ap->id,
        ]);
        $document = $this->document($result->html);
        $components = $this->elements($document, '//*[contains(concat(" ", @class, " "), " user-labels-component ")]');
        Assert::true(count($components) > 0);
        foreach ($components as $component) {
            Assert::same('Přidat štítek', $this->child($component, 'button', -1)->getAttribute('title'));
        }
        $body = $this->elements($document, '//body')[0];
        Assert::same($prefix . '/stitky/saveLabel', $body->getAttribute('data-stitky-save-url'));
        Assert::same($prefix . '/stitky/deleteLabel', $body->getAttribute('data-stitky-delete-url'));
        Assert::count(0, $this->elements($document, '//*[@id="dropdownMenu"]'));
        Assert::count(count($components), $this->elements($document, '//*[contains(@class,"user-labels-component")]//ul[@role="menu"]'));
    }

    /** @return list<array{string, int|null, string}> */
    public function missingPageCases(): array {
        return [['Uzivatel', 99999999, 'Člen'], ['Uzivatel', null, 'Člen'], ['Ap', 99999999, 'Přístupový bod']];
    }

    /** @dataProvider missingPageCases */
    public function testMissingDetail(string $presenter, ?int $id, string $title): void {
        $result = $this->presenters->ui($this->scenario->actors['vv']->id, $presenter, ['action' => 'show', 'id' => $id]);
        Assert::same(200, $result->status);
        $document = $this->document($result->html);
        Assert::same($title . ' | HKFree UserDB', $this->text($document, '//title'));
        Assert::count(0, $this->elements($document, '//h1'));
        if ($presenter === 'Ap') {
            $ap = $this->scenario->ap;
            $document = $this->document($this->presenters->ui($this->scenario->actors['vv']->id, 'Ap', ['action' => 'show', 'id' => $ap->id])->html);
            Assert::same($ap->jmeno . ' - přístupový bod | HKFree UserDB', $this->text($document, '//title'));
            Assert::same($ap->jmeno, $this->text($document, '//ol[contains(@class,"page-crumbs")]/li[last()]'));
        }
    }

    public function testAuthorizedDetail(): void {
        $document = $this->detail('ownSo');
        Assert::same('SoukroméJméno SoukroméPříjmení | HKFree UserDB', $this->text($document, '//title'));
        Assert::contains('SoukroméJméno SoukroméPříjmení', $this->text($document, '//h1'));
        Assert::same('SoukroméJméno SoukroméPříjmení', $this->text($document, '//ol[contains(@class,"page-crumbs")]/li[last()]'));
        $tiles = $this->elements($document, '//*[@class="fact-strip"]/a');
        Assert::same(['#member-internet', '#member-payments', '#member-payments', '#member-tv', '#member-contracts', '#member-ip'], array_map(static fn (DOMElement $tile): string => $tile->getAttribute('href'), $tiles));
        Assert::same('group', $this->elements($document, '//*[@class="fact-strip"]')[0]->getAttribute('role'));
        Assert::count(1, $this->elements($document, '//*[contains(@class,"page-actions")]//ul[@role="menu"]'));
        Assert::count(4, $this->elements($document, '//button[@data-copy-value]'));
        // The class copy-value is taken by the login and password values of the IP table and their handlers.
        Assert::count(4, $this->elements($document, '//button[@data-copy-value][@class="copy-button"]'));
        Assert::same('Zatím nic.', $this->text($document, '//*[@id="member-contracts"]//*[contains(@class,"section-empty")]'));
        Assert::count(0, $this->elements($document, '//*[@id="member-contracts"]//table'));
        Assert::same('NE', $this->text($document, '//*[@id="member-internet"]//span[contains(@class,"label")]'));
        Assert::contains('-987654', $this->text($document, '//*[@id="member-internet"]//tr[th="Poslední aktivace"]/td'));
        Assert::count(0, $this->elements($document, '//a[contains(@href,"editcc")]'));
        $this->scenario->member->update(['spolek' => 1]);
        Assert::count(1, $this->elements($this->detail('ownSo'), '//a[contains(@href,"editcc")]'));
    }

    public function testRestrictedDetail(): void {
        $document = $this->detail('otherSo');
        Assert::same('verejny-nick | HKFree UserDB', $this->text($document, '//title'));
        Assert::same('verejny-nick', $this->text($document, '//ol[contains(@class,"page-crumbs")]/li[last()]'));
        foreach (['//title', '//h1', '//ol[contains(@class,"page-crumbs")]'] as $path) {
            $text = $this->text($document, $path);
            Assert::notContains('SoukroméJméno', $text);
            Assert::notContains('SoukroméPříjmení', $text);
        }
        Assert::count(0, $this->elements($document, '//*[contains(@class,"fact-strip")]'));
        Assert::count(0, $this->elements($document, '//button[@data-copy-value]'));
        Assert::count(0, $this->elements($document, '//*[@data-copy-ips]'));
        Assert::count(0, $this->elements($document, '//*[@id="member-payments"]'));
        Assert::count(0, $this->elements($document, '//a[contains(@href,"uzivatel/edit/")]'));
        Assert::count(1, $this->elements($document, '//*[contains(@class,"page-actions")]//ul/li/a'));
        Assert::contains('Odeslat znovu aktivační link', $this->text($document, '//*[contains(@class,"page-actions")]//ul/li/a'));
        $this->scenario->member->update(['regform_downloaded_password_sent' => 1]);
        Assert::count(0, $this->elements($this->detail('otherSo'), '//*[contains(@class,"page-actions")]//ul'));
    }

    public function testSidebarGroupsManagedApsUnderTheirArea(): void {
        $s = $this->scenario;
        $s->ap->update(['jmeno' => 'B původní']);
        $second = $this->fixtures->ap($s->area->id);
        $second->update(['jmeno' => 'A přidané']);
        $this->fixtures->ap($s->area->id)->update(['aktivni' => 0]);
        // A second role in the same area must not print the area twice.
        $this->fixtures->role($s->actors['ownSo']->id, 'ZSO', $s->area->id);
        $document = $this->document($this->presenters->ui($s->actors['ownSo']->id, 'Homepage')->html);
        $mine = '//ul[contains(@class,"sidebar-mine")]';
        // The area name stands once, as a link to the area; its active APs follow by name, without the area prefix.
        Assert::same([$s->area->jmeno], $this->texts($document, $mine . '/li/a'));
        Assert::same(['A přidané (' . $second->id . ')', 'B původní (' . $s->ap->id . ')'], $this->texts($document, $mine . '/li/ul/li/a'));
        Assert::contains('/ap/list/' . $s->area->id, $this->elements($document, $mine . '/li/a')[0]->getAttribute('href'));

        // An area with a single active AP stays on one line, labelled by the AP.
        $this->fixtures->ap($s->otherArea->id)->update(['aktivni' => 0]);
        $other = $this->document($this->presenters->ui($s->actors['otherSo']->id, 'Homepage')->html);
        Assert::same([$s->otherAp->jmeno . ' (' . $s->otherAp->id . ')'], $this->texts($other, $mine . '/li/a'));
        Assert::count(0, $this->elements($other, $mine . '/li/ul'));
        Assert::contains('/uzivatel-list/list/' . $s->otherAp->id, $this->elements($other, $mine . '/li/a')[0]->getAttribute('href'));
    }

    /** @return list<array{int}> */
    public function moneyCases(): array {
        return [[0], [1]];
    }

    /** @dataProvider moneyCases */
    public function testListStatistics(int $money): void {
        $s = $this->scenario;
        foreach ([1, 2, 3, 3] as $index => $type) {
            $this->fixtures->member($s->ap->id, ['TypClenstvi_id' => $type, 'money_aktivni' => $index === 2 ? 1 : 0]);
        }
        $result = $this->presenters->ui($s->actors['vv']->id, 'UzivatelList', ['action' => 'list', 'id' => $s->ap->id, 'money' => $money]);
        $document = $this->document($result->html);
        $expected = ['Počet zrušených členů: 1', 'Počet primárních členů: 1', 'Počet řádných členů: 2', 'Počet čestných členů: 0'];
        if ($money === 1) {
            $expected[] = 'Počet aktivních členů: 1';
        }
        $expected[] = 'Počet všech členů: 3';
        $expected[] = 'Počet všech členů včetně zrušených: 4';
        Assert::same($expected, $this->countsBelowTable($document));
        $all = $this->document($this->presenters->ui($s->actors['vv']->id, 'UzivatelList', ['action' => 'listall', 'money' => $money])->html);
        Assert::count(count($expected), $this->countsBelowTable($all));
        Assert::same($money === 1 ? 'true' : 'false', $this->elements($document, '//a[contains(@class,"money-toggle")]')[0]->getAttribute('aria-pressed'));
        Assert::count(1, $this->elements($document, '//input[@id="member-quick-filter"]'));
        $search = $this->document($this->presenters->ui($s->actors['vv']->id, 'UzivatelList', ['action' => 'listall', 'search' => 'verejny-nick', 'money' => $money])->html);
        Assert::same('Kompletní seznam členů | HKFree UserDB', $this->text($search, '//title'));
        Assert::count(0, $this->elements($search, '//*[@class="member-counts"]'));
        Assert::count(1, $this->elements($search, '//input[@id="member-quick-filter"]'));
        Assert::count(1, $this->elements($search, '//*[@class="member-legend"]'));
    }

    private function detail(string $actor): DOMXPath {
        $s = $this->scenario;
        return $this->document($this->presenters->ui($s->actors[$actor]->id, 'Uzivatel', ['action' => 'show', 'id' => $s->member->id])->html);
    }

    /**
     * The counts stay right below the member table, where they were before the redesign.
     *
     * @return list<string>
     */
    private function countsBelowTable(DOMXPath $document): array {
        $above = '(//*[contains(@class,"grido")])[1]/preceding::*[contains(@class,"member-counts") or contains(@class,"stat-strip")]';
        Assert::count(0, $this->elements($document, $above));
        $counts = [];
        $below = '//ul[@class="member-counts"][preceding::*[contains(@class,"grido")]][not(preceding::h4[contains(.,"mimo AP")])]/li';
        foreach ($this->elements($document, $below) as $item) {
            $counts[] = trim($item->textContent);
        }
        return $counts;
    }

    /** @return list<string> */
    private function texts(DOMXPath $document, string $path): array {
        return array_map(static fn (DOMElement $element): string => trim($element->textContent), $this->elements($document, $path));
    }

    private function document(string $html): DOMXPath {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new DOMXPath($document);
    }

    /** @return list<DOMElement> */
    private function elements(DOMXPath $document, string $path): array {
        $nodes = $document->query($path);
        if ($nodes === false) {
            throw new \LogicException('Invalid XPath: ' . $path);
        }
        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }
        return $elements;
    }

    /** Negative index counts from the end. */
    private function child(DOMElement $parent, string $tag, int $index = 0): DOMElement {
        $children = $parent->getElementsByTagName($tag);
        $child = $children->item($index < 0 ? $children->length + $index : $index);
        if (!$child instanceof DOMElement) {
            throw new \LogicException("Missing <$tag> element.");
        }
        return $child;
    }

    private function text(DOMXPath $document, string $path): string {
        $elements = $this->elements($document, $path);
        Assert::count(1, $elements);
        return trim((string) preg_replace('/\s+/u', ' ', $elements[0]->textContent));
    }
}

(new MemberUsabilityPresenterTest())->run();
