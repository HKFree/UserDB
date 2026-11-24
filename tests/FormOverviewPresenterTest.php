<?php

declare(strict_types=1);

/** @testCase */

namespace Test;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class FormOverviewPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->scenario->member->update(['jmeno' => 'SoukroméJméno', 'prijmeni' => 'SoukroméPříjmení', 'nick' => 'verejny-nick']);
    }

    public function testNewMemberGroupsAndEveryFieldOnce(): void {
        $this->assertMemberGroupsAndEveryFieldOnce(false, false);
    }

    public function testExistingMemberGroupsAndEveryFieldOnce(): void {
        $this->assertMemberGroupsAndEveryFieldOnce(true, false);
    }

    public function testPublicPhoneStaysInContactGroup(): void {
        $this->assertMemberGroupsAndEveryFieldOnce(true, true);
    }

    private function assertMemberGroupsAndEveryFieldOnce(bool $editing, bool $publicPhone): void {
        $s = $this->scenario;
        $ipIds = ['0'];
        if ($editing) {
            $ipIds = [];
            foreach (['10.107.99.10', '10.107.99.11'] as $address) {
                $ipIds[] = (string) $this->fixtures->ipAddress($s->member->id, $address)->id;
            }
        }
        if ($publicPhone) {
            $this->fixtures->role($s->member->id, 'SO', $s->area->id);
        }
        $document = $this->memberEdit($editing);
        $groups = [
            'Zařazení' => ['Ap_id'],
            'Osoba' => ['TypPravniFormyUzivatele_id', 'firma_nazev', 'firma_ico', 'jmeno', 'prijmeni', 'datum_narozeni', 'nick', 'cislo_clenske_karty'],
            'Kontakt a adresa' => array_merge(['email', 'email2', 'telefon'], $publicPhone ? ['publicPhone'] : [], ['ulice_cp', 'mesto', 'psc']),
            'Členství' => ['druzstvo', 'smazano', 'spolek', 'TypClenstvi_id', 'kauce_mobil'],
            'Připojení' => ['TechnologiePripojeni_id', 'ZpusobPripojeni_id'],
            'Poznámky' => ['poznamka', 'gpg'],
            'IP adresy' => ['ipsubnet', 'iprange'],
        ];
        $sections = $this->elements($document, '//form[@id="frm-uzivatelForm"]//section');
        Assert::same(array_keys($groups), array_map(fn (DOMElement $section): string => trim($this->child($section, 'h2')->textContent), $sections));
        $expected = ['id', 'ipOtisk', 'save', '_do', 'ip[add]'];
        foreach (array_values($groups) as $index => $fields) {
            $sectionId = $sections[$index]->getAttribute('id');
            $actual = $this->names($document, '//*[@id="' . $sectionId . '"]//*[self::input or self::select or self::textarea][@name and not(starts-with(@name,"ip["))]');
            Assert::same($fields, $actual);
            $expected = array_merge($expected, $fields);
            Assert::count(1, $this->elements($document, '//nav[@aria-label="Části formuláře"]/a[@href="#' . $sectionId . '"]'));
        }
        foreach ($ipIds as $ipId) {
            foreach (['id', 'ip_adresa', 'hostname', 'TypZarizeni_id', 'internet', 'smokeping', 'login', 'heslo', 'mac_adresa', 'mac_filter', 'dhcp', 'popis', 'remove'] as $field) {
                $expected[] = 'ip[' . $ipId . '][' . $field . ']';
            }
        }
        $actual = $this->names($document, '//form[@id="frm-uzivatelForm"]//*[self::input or self::select or self::textarea or self::button][@name]');
        sort($expected);
        sort($actual);
        Assert::same($expected, $actual, 'Every member field, hidden control and dynamic IP control must render exactly once.');
        Assert::count(1, $this->elements($document, '//*[contains(@class,"form-save-bar")]//input[@name="save"]'));
        // Each required field carries its own badge, so the bar needs no legend.
        Assert::notContains('povinné', $this->text($document, '//*[contains(@class,"form-save-bar")]'));
        Assert::same(['povinné'], array_values(array_unique(array_map(
            static fn (\DOMNode $node): string => trim($node->textContent),
            $this->elements($document, '//label[@for="frm-uzivatelForm-jmeno"]/following-sibling::span[@class="required-badge" and @aria-hidden="true"]'),
        ))));
        Assert::count($editing ? 1 : 0, $this->elements($document, '//*[contains(@class,"form-save-bar")]/a'));
    }

    public function testExistingMemberHeadingAndTitle(): void {
        $this->assertMemberHeadingAndTitle(true);
    }

    public function testNewMemberHeadingAndTitle(): void {
        $this->assertMemberHeadingAndTitle(false);
    }

    private function assertMemberHeadingAndTitle(bool $editing): void {
        $member = $this->scenario->member;
        $document = $this->memberEdit($editing);
        $heading = $editing ? 'Úprava člena ' . $member->jmeno . ' ' . $member->prijmeni . ' UID ' . $member->id : 'Nový člen';
        Assert::same($heading, $this->text($document, '//h1'));
        Assert::same($heading . ' | HKFree UserDB', $this->text($document, '//title'));
        Assert::same('Přehled', $this->text($document, '//ol[contains(@class,"page-crumbs")]/li[1]'));
        Assert::count($editing ? 1 : 0, $this->elements($document, '//*[contains(@class,"page-actions")]/a'));
    }

    public function testHomeManagedAreas(): void {
        $this->assertHomeAreas('ownSo', true);
    }

    public function testHomeEmptyState(): void {
        $this->assertHomeAreas('cooperative', false);
    }

    private function assertHomeAreas(string $actor, bool $hasAreas): void {
        $document = $this->home($actor);
        Assert::same('Přehled', $this->text($document, '//h1'));
        Assert::same('Přehled | HKFree UserDB', $this->text($document, '//title'));
        $cards = $this->elements($document, '//*[contains(@class,"home-areas")]/a');
        Assert::count($hasAreas ? 1 : 0, $cards);
        if ($hasAreas) {
            Assert::contains($this->scenario->ap->jmeno, $cards[0]->textContent);
            Assert::same('/userdb/uzivatel-list/list/' . $this->scenario->ap->id, $cards[0]->getAttribute('href'));
        } else {
            Assert::contains('Nespravujete žádnou oblast.', $this->text($document, '//*[contains(@class,"home-empty")]'));
        }
        Assert::count(2, $this->elements($document, '//*[contains(@class,"home-help")]/details/summary'));
        Assert::count(2, $this->elements($document, '//*[contains(@class,"home-help")]/details/img[@alt]'));
    }

    public function testHomeAndNavbarShareToolsForManager(): void {
        $this->assertHomeAndNavbarTools('ownSo');
    }

    public function testHomeAndNavbarShareToolsWithoutArea(): void {
        $this->assertHomeAndNavbarTools('cooperative');
    }

    private function assertHomeAndNavbarTools(string $actor): void {
        $document = $this->home($actor);
        $menu = $this->links($document, '//*[@id="navbar-tools"]//a');
        $home = $this->links($document, '//*[contains(@class,"home-tools")]//a[@data-tool]');
        Assert::count(10, $menu);
        Assert::same($menu, $home);
        Assert::same('Otevírání Winboxu z prohlížeče', $menu['/userdb/winbox/'] ?? null);
        Assert::count(1, $this->elements($document, '//*[contains(@class,"home-tools")]//a[@href="/userdb/uzivatel-list/listall"]'));
        Assert::count(1, $this->elements($document, '//*[contains(@class,"home-tools")]//a[@href="/userdb/sprava/mapa"]'));
        Assert::count(1, $this->elements($document, '//form[@role="search"]//input[@placeholder="Hledat člena, IP, oblast, AP…"]'));
    }

    public function testSearchHintIsAssociatedWithInput(): void {
        $document = $this->home('ownSo');
        Assert::count(1, $this->elements($document, '//form[@role="search"]//input[@aria-describedby="search-hint"]'));
        Assert::count(1, $this->elements($document, '//*[@id="search-hint" and @role="status"]'));
    }

    public function testDeniedEditDoesNotDiscloseMember(): void {
        $s = $this->scenario;
        $document = $this->document($this->presenters->ui($s->actors['otherSo']->id, 'Uzivatel', ['action' => 'edit', 'id' => $s->member->id])->html);
        Assert::same('Úprava člena | HKFree UserDB', $this->text($document, '//title'));
        Assert::count(0, $this->elements($document, '//form[@id="frm-uzivatelForm"]'));
        Assert::notContains($s->member->jmeno, $this->text($document, '//*[@id="obsah"]'));
        Assert::notContains($s->member->prijmeni, $this->text($document, '//title'));
    }

    public function testRemainingTitlesMatchVisibleHeadings(): void {
        foreach ([['Sprava', 'mapa'], ['Sprava', 'nastroje'], ['SpravaOblasti', 'novaoblast'], ['SpravaOblasti', 'noveap'], ['SpravaStitku', 'default'], ['SpravaCc', 'prehledcc'], ['Status', 'default']] as [$presenter, $action]) {
            $document = $this->document($this->presenters->ui($this->scenario->actors['vv']->id, $presenter, ['action' => $action])->html);
            Assert::same($this->text($document, '(//*[@id="obsah"]//*[self::h1 or self::h2 or self::h3])[1]') . ' | HKFree UserDB', $this->text($document, '//title'));
        }
    }

    public function testIpTransferTitleMatchesItsHeading(): void {
        $s = $this->scenario;
        $ip = $this->fixtures->ipAddress($s->member->id, '10.107.99.12');
        $document = $this->document($this->presenters->ui($s->actors['vv']->id, 'Uzivatel', ['action' => 'presunIp', 'id' => $s->member->id, 'ipId' => $ip->id])->html);
        Assert::same('Přesun IP k jinému členovi | HKFree UserDB', $this->text($document, '//title'));
        Assert::same('Přesun IP k jinému členovi', $this->text($document, '//*[@id="obsah"]//h1'));
    }

    public function testAddingIpKeepsUnsavedState(): void {
        $s = $this->scenario;
        $before = $s->snapshot();
        $result = $this->presenters->ui($s->actors['vv']->id, 'Uzivatel', ['action' => 'edit', 'id' => $s->member->id], 'POST', [
            '_do' => 'uzivatelForm-submit', 'id' => (string) $s->member->id,
            'jmeno' => 'NeuloženéJméno', 'ip' => ['add' => '+ Přidat další IP'],
            'Ap_id' => (string) $s->ap->id, 'TypPravniFormyUzivatele_id' => '1',
            'prijmeni' => $s->member->prijmeni, 'nick' => $s->member->nick,
            'email' => $s->member->email, 'telefon' => $s->member->telefon,
            'ulice_cp' => $s->member->ulice_cp, 'mesto' => $s->member->mesto, 'psc' => (string) $s->member->psc,
            'druzstvo' => '1', 'spolek' => '0', 'smazano' => '0', 'TypClenstvi_id' => '-1',
            'TechnologiePripojeni_id' => (string) $s->member->TechnologiePripojeni_id,
            'ZpusobPripojeni_id' => (string) $s->member->ZpusobPripojeni_id,
        ]);
        $document = $this->document($result->html);
        Assert::same(200, $result->status);
        Assert::count(1, $this->elements($document, '//form[@id="frm-uzivatelForm" and contains(@class,"form-dirty")]'));
        Assert::count(1, $this->elements($document, '//input[@name="jmeno" and @value="NeuloženéJméno"]'));
        // The heading names the member as saved, not as typed into the unsaved form.
        Assert::contains($s->member->jmeno . ' ' . $s->member->prijmeni, $this->text($document, '//h1'));
        foreach (['//h1', '//title', '//ol[contains(@class,"page-crumbs")]'] as $path) {
            Assert::notContains('NeuloženéJméno', $this->text($document, $path));
        }
        Assert::count(1, $this->elements($document, '//table[@class="ipform"]//input[contains(@name,"[ip_adresa]")]'));
        Assert::same($before, $s->snapshot());
    }

    public function testServerErrorStandsRedAtItsFieldOfTheMemberForm(): void {
        $s = $this->scenario;
        $before = $s->snapshot();
        $result = $this->presenters->ui($s->actors['vv']->id, 'Uzivatel', ['action' => 'edit', 'id' => $s->member->id], 'POST', [
            '_do' => 'uzivatelForm-submit', 'id' => (string) $s->member->id, 'save' => 'Uložit',
            'jmeno' => '', 'Ap_id' => (string) $s->ap->id, 'TypPravniFormyUzivatele_id' => '1',
            'prijmeni' => $s->member->prijmeni, 'nick' => $s->member->nick,
            'email' => $s->member->email, 'telefon' => $s->member->telefon,
            'ulice_cp' => $s->member->ulice_cp, 'mesto' => $s->member->mesto, 'psc' => (string) $s->member->psc,
            'druzstvo' => '1', 'spolek' => '0', 'smazano' => '0', 'TypClenstvi_id' => '-1',
            'TechnologiePripojeni_id' => (string) $s->member->TechnologiePripojeni_id,
            'ZpusobPripojeni_id' => (string) $s->member->ZpusobPripojeni_id,
        ]);
        $document = $this->document($result->html);
        Assert::same(200, $result->status);
        $errors = '//form[@id="frm-uzivatelForm"]//section[contains(@class,"form-section")]//td/span[@class="text-danger"]';
        Assert::count(1, $this->elements($document, $errors));
        Assert::same('Zadejte jméno', $this->text($document, $errors));
        Assert::count(1, $this->elements($document, '//td[input[@name="jmeno"]]/span[@class="text-danger"]'));
        Assert::same($before, $s->snapshot());
    }

    public function testMemberPageTitlesRespectHeadingVisibility(): void {
        $s = $this->scenario;
        foreach (['ownSo', 'otherSo'] as $actor) {
            foreach ([['UzivatelMailSms', 'email'], ['UzivatelRightsCc', 'editrights'], ['UzivatelRightsCc', 'editcc'], ['UzivatelAccount', 'account']] as [$presenter, $action]) {
                $document = $this->document($this->presenters->ui($s->actors[$actor]->id, $presenter, ['action' => $action, 'id' => $s->member->id])->html);
                Assert::same($this->text($document, '(//*[@id="obsah"]//*[self::h1 or self::h2 or self::h3])[1]') . ' | HKFree UserDB', $this->text($document, '//title'));
                if ($actor === 'otherSo') {
                    Assert::notContains($s->member->jmeno, $this->text($document, '//title'));
                }
            }
        }
    }

    private function memberEdit(bool $editing): DOMXPath {
        $s = $this->scenario;
        return $this->document($this->presenters->ui($s->actors['vv']->id, 'Uzivatel', ['action' => 'edit', 'id' => $editing ? $s->member->id : null])->html);
    }

    private function home(string $actor): DOMXPath {
        return $this->document($this->presenters->ui($this->scenario->actors[$actor]->id, 'Homepage')->html);
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
        if (($child instanceof DOMElement) === false) {
            throw new \LogicException("Missing <$tag> element.");
        }
        return $child;
    }

    private function text(DOMXPath $document, string $path): string {
        $elements = $this->elements($document, $path);
        Assert::count(1, $elements);
        return trim((string) preg_replace('/\s+/u', ' ', $elements[0]->textContent));
    }

    /** @return list<string> */
    private function names(DOMXPath $document, string $path): array {
        return array_map(static fn (DOMElement $element): string => $element->getAttribute('name'), $this->elements($document, $path));
    }

    /** @return array<string, string> */
    private function links(DOMXPath $document, string $path): array {
        $links = [];
        foreach ($this->elements($document, $path) as $link) {
            $links[$link->getAttribute('href')] = trim($link->textContent);
        }
        return $links;
    }
}

(new FormOverviewPresenterTest())->run();
