<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionRightsPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    /** @return list<array{string, string}> */
    public function rightsCases(): array {
        return [['cooperative', ''], ['ownSo', ''], ['otherSo', ''], ['support', ''], ['tech', ''], ['vv', ''], ['vv', '0']];
    }

    /** @dataProvider rightsCases */
    public function testRoleAssignment(string $actor, string $id): void {
        $s = $this->scenario;
        $target = $actor === 'vv' ? $s->member : $s->actors[$actor];
        $roleId = $this->fixtures->lookup('TypSpravceOblasti', 'VV');
        $post = ['_do' => 'uzivatelRightsForm-submit', 'id' => (string) $target->id, 'rights' => ['n1' => [
            'id' => $id, 'Uzivatel_id' => '', 'TypSpravceOblasti_id' => (string) $roleId, 'Oblast_id' => '', 'od' => date('Y-m-d'),
        ]], 'save' => 'Uložit'];
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, 'UzivatelRightsCc', ['action' => 'editrights', 'id' => $target->id], 'POST', $post);
        if ($actor !== 'vv') {
            $s->assertDenied($request);
            return;
        }
        PermissionScenario::assertRedirect($request(), 'uzivatel/show/' . $target->id);
        Assert::same(1, $this->database->table('SpravceOblasti')->where(['Uzivatel_id' => $target->id, 'TypSpravceOblasti_id' => $roleId])->count('*'));
    }

    /** @return list<array{string, bool, bool}> */
    public function existingRightsCases(): array {
        return [
            ['unchanged', false, true], ['unlimited', false, true], ['global', false, true], ['setEnd', false, true],
            ['od', false, false], ['do', false, false], ['clearEnd', false, false],
            ['area', false, false], ['role', false, false], ['do', true, true],
        ];
    }

    /** @dataProvider existingRightsCases */
    public function testExistingRightKeepsHistoricalEditGuard(string $change, bool $override, bool $allowed): void {
        $s = $this->scenario;
        $areaId = $change === 'global' ? null : $s->area->id;
        $oldEnd = in_array($change, ['unlimited', 'setEnd'], true) ? null : '2099-12-31';
        $right = $this->fixtures->role($s->member->id, $change === 'global' ? 'VV' : 'SO', $areaId, '2000-01-01', $oldEnd);
        $values = [
            'id' => (string) $right->id, 'Uzivatel_id' => (string) $s->member->id,
            'TypSpravceOblasti_id' => (string) $right->TypSpravceOblasti_id, 'Oblast_id' => (string) $areaId,
            'od' => '2000-01-01', 'do' => $change === 'unlimited' ? '' : '2099-12-31',
        ];
        match ($change) {
            'od' => $values['od'] = '2001-01-01',
            'do' => $values['do'] = '2098-12-31',
            'setEnd' => $values['do'] = '2098-12-31',
            'clearEnd' => $values['do'] = '',
            'area' => $values['Oblast_id'] = (string) $s->otherArea->id,
            'role' => $values['TypSpravceOblasti_id'] = (string) $this->fixtures->lookup('TypSpravceOblasti', 'ZSO'),
            default => null,
        };
        if ($override) {
            $values['override'] = '1';
        }
        $before = $s->snapshot();
        $result = $this->presenters->ui($s->actors['vv']->id, 'UzivatelRightsCc', [
            'action' => 'editrights', 'id' => $s->member->id,
        ], 'POST', [
            '_do' => 'uzivatelRightsForm-submit', 'id' => (string) $s->member->id,
            'rights' => [$right->id => $values], 'save' => 'Uložit',
        ]);
        if (!$allowed) {
            Assert::same(200, $result->status);
            Assert::contains('<li>NERECYKLUJTE. Upravili jste starší právo a nezaškrtli jste tlačítko OPRAVA. Mysleli jste to vážně?</li>', $result->html);
            Assert::same($before, $s->snapshot());
            return;
        }
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $s->member->id);
        $saved = $this->database->table('SpravceOblasti')->get($right->id);
        Assert::true($saved !== null);
        Assert::same('2000-01-01', $saved->od->format('Y-m-d'));
        Assert::same($values['do'] === '' ? null : $values['do'], $saved->do?->format('Y-m-d'));
        Assert::same($areaId, $saved->Oblast_id);
        Assert::same($right->TypSpravceOblasti_id, $saved->TypSpravceOblasti_id);
        $logs = $this->database->table('Log')->where('tabulka_id', $s->member->id);
        $changed = $override || $change === 'setEnd';
        Assert::same($changed ? 1 : 0, $logs->count('*'));
        if ($changed) {
            $entry = $logs->fetch();
            Assert::true($entry !== null);
            Assert::same('SpravceOblasti[' . $right->id . '].do', $entry->sloupec);
            Assert::same($oldEnd === null ? null : '2099-12-31 00:00:00', $entry->puvodni_hodnota);
            Assert::same('2098-12-31 00:00:00', $entry->nova_hodnota);
        }
    }

    /** @return list<array{string, bool, bool}> */
    public function honoraryCases(): array {
        return [
            ['cooperative', false, false], ['otherSo', false, false], ['ownSo', true, false],
            ['ownSo', false, true], ['tech', false, true], ['support', false, true], ['vv', false, true],
        ];
    }

    /** @dataProvider honoraryCases */
    public function testHonoraryMembership(string $actor, bool $forgedId, bool $allowed): void {
        $s = $this->scenario;
        $target = $forgedId ? $s->otherMember : $s->member;
        $note = $this->fixtures->name('Honorary');
        $request = fn () => $this->submitHonorary($actor, $target, $note);
        if (!$allowed) {
            $s->assertDenied($request);
            return;
        }
        PermissionScenario::assertRedirect($request(), 'uzivatel/show/' . $target->id);
        $cc = $this->database->table('CestneClenstviUzivatele')->where('Uzivatel_id', $target->id)->fetch();
        Assert::true($cc !== null);
        Assert::same($note, $cc->poznamka);
        Assert::same($actor === 'vv' ? 1 : 0, $cc->schvaleno);
        Assert::same($s->actors[$actor]->id, $cc->zadost_podal);
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(1, $messages);
        Assert::same(['vv@hkfree.org' => null], $messages[0]->getHeader('To'));
        Assert::contains($note, $messages[0]->getBody());
    }

    public function testHonoraryMembershipsAccumulate(): void {
        $s = $this->scenario;
        $expected = [];
        foreach (['ownSo', 'tech', 'support', 'vv'] as $actor) {
            PermissionScenario::assertRedirect($this->submitHonorary($actor, $s->member, 'Note ' . $actor), 'uzivatel/show/' . $s->member->id);
            // Only VV may approve; requests of the other actors stay pending.
            $expected['Note ' . $actor] = $actor === 'vv' ? 1 : 0;
        }
        $stored = $this->database->table('CestneClenstviUzivatele')->where('Uzivatel_id', $s->member->id)->order('id')->fetchPairs('poznamka', 'schvaleno');
        Assert::same($expected, $stored);
    }

    /** @return list<array{string, ?string, string}> */
    public function honoraryFormCases(): array {
        return [['', null, ''], ['0', '0', ''], ['', '1', '2027-12-31'], ['0', '2', '2027-12-31']];
    }

    /** @dataProvider honoraryFormCases */
    public function testHonoraryFormCreatesThenUpdatesWithoutAnotherEmail(string $id, ?string $state, string $until): void {
        $s = $this->scenario;
        $row = [
            'id' => $id, 'TypCestnehoClenstvi_id' => (string) $this->fixtures->lookup('TypCestnehoClenstvi', 'HKFree do škol'),
            'plati_od' => '2026-01-01', 'plati_do' => $until, 'poznamka' => 'Nová žádost',
        ] + ($state === null ? [] : ['schvaleno' => $state]);
        $submit = fn (array $rights) => $this->presenters->ui($s->actors['vv']->id, 'UzivatelRightsCc', ['action' => 'editcc', 'id' => $s->member->id], 'POST', [
            '_do' => 'uzivatelCCForm-submit', 'id' => (string) $s->member->id, 'rights' => ['n1' => $rights], 'save' => 'Uložit',
        ]);
        PermissionScenario::assertRedirect($submit($row), 'uzivatel/show/' . $s->member->id);
        $stored = $this->database->table('CestneClenstviUzivatele')->where('Uzivatel_id', $s->member->id)->fetch();
        Assert::true($stored !== null);
        Assert::same((int) ($state ?? 0), $stored->schvaleno);
        Assert::same('2026-01-01', $stored->plati_od->format('Y-m-d'));
        Assert::same($until === '' ? null : $until, $stored->plati_do?->format('Y-m-d'));
        $row['id'] = (string) $stored->id;
        $row['poznamka'] = 'Upravená žádost';
        PermissionScenario::assertRedirect($submit($row), 'uzivatel/show/' . $s->member->id);
        Assert::same(1, $this->database->table('CestneClenstviUzivatele')->where('Uzivatel_id', $s->member->id)->count('*'));
        Assert::same('Upravená žádost', $this->requireRow($this->database->table('CestneClenstviUzivatele')->get($stored->id))->poznamka);
        Assert::count(1, $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testExistingRightRequiresOverrideAndKeepsOptionalEndDate(): void {
        $s = $this->scenario;
        $right = $this->fixtures->role($s->member->id, 'SO', $s->area->id, until: null);
        $row = ['id' => (string) $right->id, 'TypSpravceOblasti_id' => (string) $right->TypSpravceOblasti_id,
            'Oblast_id' => (string) $s->area->id, 'od' => '2026-01-01', 'do' => ''];
        $submit = fn (array $rights) => $this->presenters->ui($s->actors['vv']->id, 'UzivatelRightsCc', ['action' => 'editrights', 'id' => $s->member->id], 'POST', [
            '_do' => 'uzivatelRightsForm-submit', 'id' => (string) $s->member->id, 'rights' => [$right->id => $rights], 'save' => 'Uložit',
        ]);
        $denied = $submit($row);
        Assert::same(200, $denied->status);
        Assert::contains('NERECYKLUJTE.', $denied->html);
        Assert::same('2000-01-01', $this->requireRow($this->database->table('SpravceOblasti')->get($right->id))->od->format('Y-m-d'));
        PermissionScenario::assertRedirect($submit($row + ['override' => 'on']), 'uzivatel/show/' . $s->member->id);
        $updated = $this->database->table('SpravceOblasti')->get($right->id);
        Assert::true($updated !== null);
        Assert::same('2026-01-01', $updated->od->format('Y-m-d'));
        Assert::null($updated->do);
        Assert::same(1, $this->database->table('SpravceOblasti')->where('Uzivatel_id', $s->member->id)->count('*'));
        Assert::same(1, $this->database->table('Log')->where([
            'tabulka_id' => $s->member->id, 'sloupec' => 'SpravceOblasti[' . $right->id . '].od', 'akce' => 'U',
        ])->count('*'));
    }

    public function testEditAfterRejectedRequestLogsOriginalRightWithColumnCache(): void {
        $s = $this->scenario;
        // Tests read whole rows by default; a cache storage makes Nette Database remember the columns read at each call site.
        $cached = new Explorer($this->database->getConnection(), $this->database->getStructure(), $this->database->getConventions(), new MemoryStorage());
        $runner = new PresenterRunner($cached, TestEnvironment::container($cached));
        $right = $this->fixtures->role($s->member->id, 'SO', $s->area->id, '2000-01-01', null);
        $row = [
            'id' => (string) $right->id, 'TypSpravceOblasti_id' => (string) $right->TypSpravceOblasti_id,
            'Oblast_id' => (string) $s->area->id, 'od' => '2000-01-01', 'do' => '2098-12-31',
        ];
        // The override skips the validation lookup, so the unknown right is rejected only after the existing one was loaded.
        $unknown = ['id' => '-1', 'override' => 'on'] + $row;

        // The column cache key includes the full call stack: both requests must use the same call site.
        foreach ([[$right->id => $row, 'n1' => $unknown], [$right->id => $row]] as $index => $rights) {
            try {
                $result = $runner->ui($s->actors['vv']->id, 'UzivatelRightsCc', ['action' => 'editrights', 'id' => $s->member->id], 'POST', [
                    '_do' => 'uzivatelRightsForm-submit', 'id' => (string) $s->member->id, 'rights' => $rights, 'save' => 'Uložit',
                ]);
                Assert::same(1, $index, 'The request with the unknown right must be rejected.');
                PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $s->member->id);
            } catch (BadRequestException $e) {
                Assert::same(0, $index, 'The valid request must succeed.');
                Assert::same([404, 'Oprávnění nebylo nalezeno.'], [$e->getCode(), $e->getMessage()]);
                // The validation throws the same error; only a rejection in the success handler leaves the existing right loaded but unread.
                Assert::contains('uzivatelRightsFormSucceded', array_column($e->getTrace(), 'function'));
                Assert::same(0, $this->database->table('Log')->count('*'));
                Assert::null($this->requireRow($this->database->table('SpravceOblasti')->get($right->id))->do);
            }
            unset($e, $result);
            // Columns read by the rejected request are remembered when its selection is destroyed;
            // a row loaded lazily with only those would be refetched after the update.
            gc_collect_cycles();
        }

        Assert::same('2098-12-31', $this->requireRow($this->database->table('SpravceOblasti')->get($right->id))->do?->format('Y-m-d'));
        $logs = $this->database->table('Log')->where('tabulka_id', $s->member->id);
        Assert::same(1, $logs->count('*'));
        $entry = $this->requireRow($logs->fetch());
        Assert::same(
            ['Uzivatel', 'SpravceOblasti[' . $right->id . '].do', null, '2098-12-31 00:00:00', 'U'],
            [$entry->tabulka, $entry->sloupec, $entry->puvodni_hodnota, $entry->nova_hodnota, $entry->akce],
        );
    }

    private function submitHonorary(string $actor, ActiveRow $target, string $note): PresenterResult {
        $s = $this->scenario;
        $post = ['_do' => 'uzivatelCCForm-submit', 'id' => (string) $target->id, 'rights' => ['n1' => [
            'id' => '', 'TypCestnehoClenstvi_id' => (string) $this->fixtures->lookup('TypCestnehoClenstvi', 'HKFree do škol'),
            'plati_od' => date('Y-m-d'), 'schvaleno' => '1', 'poznamka' => $note,
        ]], 'save' => 'Uložit'];
        return $this->presenters->ui($s->actors[$actor]->id, 'UzivatelRightsCc', ['action' => 'editcc', 'id' => $s->member->id], 'POST', $post);
    }
}

(new PermissionRightsPresenterTest())->run();
