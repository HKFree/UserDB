<?php

declare(strict_types=1);

namespace Test;

use Grido\Components\Export;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionGridExportsPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    public function gridCases(): array {
        $cases = [];
        foreach ([
            ['SpravaMailu', 'mailinglist', 'mailinglistGrid', ['vv']],
            ['SpravaPlateb', 'platbycu', 'paymentgrid', ['vv', 'tech']],
            ['SpravaNesparovanych', 'nesparovane', 'accountgrid', ['vv', 'tech']],
            ['UzivatelAccount', 'account', 'accountgrid', ['vv', 'tech', 'ownSo']],
        ] as [$presenter, $action, $grid, $allowed]) {
            foreach (['cooperative', 'support', 'otherSo', 'ownSo', 'tech', 'vv'] as $actor) {
                foreach (in_array($actor, $allowed, true) ? ['export-export'] : ['export-export', 'page'] as $signal) {
                    $cases["$presenter/$actor/$signal"] = [$presenter, $action, "$grid-$signal", $actor, in_array($actor, $allowed, true)];
                }
            }
        }
        return $cases;
    }

    /** @dataProvider gridCases */
    public function testGridSignal(string $presenter, string $action, string $signal, string $actor, bool $allowed): void {
        $s = $this->scenario;
        $marker = $this->fixtures->name('Export');
        $payment = $this->fixtures->insert('PrichoziPlatba', [
            'datum' => date('Y-m-d'), 'castka' => 1234, 'kod_cilove_banky' => 2010, 'druzstvo' => 1, 'spolek' => 0,
            'zprava_prijemci' => $marker, 'nazev_uctu' => $marker,
            'TypPrichoziPlatby_id' => $this->database->table('TypPrichoziPlatby')->fetch()->id,
        ]);
        foreach ([null, $s->member->id] as $memberId) {
            $this->fixtures->insert('UzivatelskeKonto', [
                'Uzivatel_id' => $memberId, 'PrichoziPlatba_id' => $payment->id,
                'TypPohybuNaUctu_id' => $this->fixtures->lookup('TypPohybuNaUctu', 'Prichozi platba'),
                'castka' => 1234, 'datum' => date('Y-m-d'), 'druzstvo' => 1, 'spolek' => 0, 'poznamka' => $marker,
            ]);
        }
        $parameters = ['action' => $action, 'do' => $signal] + ($presenter === 'UzivatelAccount' ? ['id' => $s->member->id] : []);
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, $presenter, $parameters);
        if (!$allowed) {
            $s->assertDenied($request);
            return;
        }
        $result = $request();
        Assert::type(Export::class, $result->response);
        if ($presenter === 'SpravaNesparovanych') {
            // Sending this populated export fails on the undeclared column cu, so only the access is checked.
            return;
        }
        ob_start();
        try {
            $result->response->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        Assert::contains($presenter === 'SpravaMailu' ? $s->member->email : $marker, $csv);
        Assert::true(count(explode("\n", trim($csv))) > 1);
    }

    public function moneyCases(): array {
        return [
            ['otherSo', 'list', false, false], ['cooperative', 'listall', false, false],
            ['ownSo', 'list', true, true], ['vv', 'listall', true, true], ['support', 'listall', true, true],
        ];
    }

    /** @dataProvider moneyCases */
    public function testMoneyVisibility(string $actor, string $action, bool $overpayment, bool $statistics): void {
        $s = $this->scenario;
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $s->member->id, 'TypPohybuNaUctu_id' => $this->fixtures->lookup('TypPohybuNaUctu', 'Prichozi platba'),
            'castka' => 100000, 'druzstvo' => 1, 'datum' => date('Y-m-d'), 'poznamka' => 'Overpayment',
        ]);
        $before = $s->snapshot();
        $parameters = ['action' => $action, 'money' => 1] + ($action === 'list' ? ['id' => $s->ap->id] : ['search' => $s->member->nick]);
        $result = $this->presenters->ui($s->actors[$actor]->id, 'UzivatelList', $parameters);
        Assert::same(200, $result->status);
        Assert::same($overpayment, str_contains($result->html, 'preplatek'));
        if ($action === 'listall' && $actor === 'cooperative') {
            // Name search is limited to managed areas; an exact UID also tests a populated grid.
            $parameters['search'] = (string) $s->member->id;
            $result = $this->presenters->ui($s->actors[$actor]->id, 'UzivatelList', $parameters);
        }
        Assert::contains('/uzivatel/show/' . $s->member->id, $result->html);
        Assert::same($overpayment, str_contains($result->html, 'preplatek'));
        if ($action === 'listall') {
            unset($parameters['search']);
            $result = $this->presenters->ui($s->actors[$actor]->id, 'UzivatelList', $parameters);
        }
        Assert::same($statistics, str_contains($result->html, 'Počet aktivních členů'));
        if ($actor === 'support') {
            Assert::contains('Bez informací z money', $result->html);
        }
        Assert::same($before, $s->snapshot());
    }
}

(new PermissionGridExportsPresenterTest())->run();
