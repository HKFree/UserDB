<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/** @testCase */
final class NullableApFormsPresenterTest extends DatabaseTestCase
{
    /** @return list<array{string, string}> */
    public function relatedRowCases(): array {
        $cases = [];
        foreach (['ip', 'subnet', 'apiKlic'] as $container) {
            foreach (['missing', 'foreign', 'new'] as $mode) {
                $cases[] = [$container, $mode];
            }
        }
        return $cases;
    }

    /** @dataProvider relatedRowCases */
    public function testInvalidRelatedRowPreventsAllApChanges(string $container, string $mode): void {
        $s = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $row = match ($container) {
            'ip' => $this->fixtures->insert('IPAdresa', ['Ap_id' => $s->otherAp->id, 'ip_adresa' => '192.0.2.191']),
            'subnet' => $this->fixtures->subnet($s->otherAp->id, '192.0.2.0/28', '192.0.2.1'),
            'apiKlic' => $this->fixtures->apiKey(apId: $s->otherAp->id),
            default => throw new \LogicException($container),
        };
        $values = match ($container) {
            'ip' => ['ip_adresa' => '198.51.100.191', 'hostname' => 'changed-device'],
            'subnet' => ['subnet' => '198.51.100.0/28', 'gateway' => '198.51.100.1', 'popis' => 'Changed subnet'],
            default => ['klic' => $row->klic, 'plati_do' => '', 'poznamka' => 'Changed key'],
        };
        $values['id'] = $mode === 'missing' ? '-1' : (string) $row->id;
        $post = [
            '_do' => 'apForm-submit', 'id' => $mode === 'new' ? '' : (string) $s->ap->id,
            'jmeno' => $this->fixtures->name('ChangedAP'), 'Oblast_id' => (string) $s->area->id, 'aktivni' => 'on',
            'gps' => '50.209722,15.832778', 'ulice_cp' => 'Testovací 1', 'mesto' => 'Hradec Králové', 'psc' => '50002',
            $container => ['n1' => $values], 'save' => 'Uložit',
        ];
        $request = fn () => $this->presenters->ui($s->actors[$mode === 'new' ? 'vv' : 'ownSo']->id, 'Ap', ['action' => 'edit', 'id' => $mode === 'new' ? null : $s->ap->id], 'POST', $post);
        if ($mode !== 'missing') {
            $s->assertDenied($request);
            return;
        }
        $before = $s->snapshot();
        Assert::exception($request, BadRequestException::class, 'Podřízený záznam AP nebyl nalezen.', 404);
        Assert::same($before, $s->snapshot());
    }
}

(new NullableApFormsPresenterTest())->run();
