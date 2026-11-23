<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionApEditPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    /** @return list<array{string, string, bool}> */
    public function editCases(): array {
        return [
            ['cooperative', 'edit', false], ['support', 'edit', false], ['otherSo', 'edit', false],
            ['ownSo', 'forged', false], ['cooperative', 'new', false], ['ownSo', 'new', false],
            ['ownSo', 'edit', true], ['vv', 'edit', true], ['tech', 'edit', true], ['tech', 'new', true],
        ];
    }

    /** @dataProvider editCases */
    public function testApSubmission(string $actor, string $mode, bool $allowed): void {
        $s = $this->scenario;
        $target = $mode === 'forged' ? $s->otherAp : $s->ap;
        $name = $this->fixtures->name('EditedAP');
        $post = [
            '_do' => 'apForm-submit', 'id' => $mode === 'new' ? '' : (string) $target->id, 'aktivni' => 'on',
            'jmeno' => $name, 'Oblast_id' => (string) $target->Oblast_id, 'gps' => '50.209722,15.832778',
            'ulice_cp' => 'Test street 1', 'mesto' => 'Test city', 'psc' => '50002', 'save' => 'Uložit',
        ];
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, 'Ap', ['action' => 'edit', 'id' => $mode === 'new' ? null : $s->ap->id], 'POST', $post);
        if ($allowed === false) {
            $s->assertDenied($request);
            return;
        }
        $result = $request();
        $ap = $this->database->table('Ap')->where('jmeno', $name)->fetch();
        Assert::true($ap !== null);
        Assert::same($target->Oblast_id, $ap->Oblast_id);
        Assert::same('50.209722,15.832778', $ap->gps);
        if ($mode !== 'new') {
            Assert::same($target->id, $ap->id);
        }
        Assert::same($mode === 'new' ? 3 : 2, $this->database->table('Ap')->count('*'));
        PermissionScenario::assertRedirect($result, 'ap/show/' . $ap->id);
    }

    public function testEditFormVisibility(): void {
        $s = $this->scenario;
        $missing = $this->fixtures->ap($s->area->id);
        $missingId = $missing->id;
        $missing->delete();
        $allowed = $this->presenters->ui($s->actors['vv']->id, 'Ap', ['action' => 'edit', 'id' => $missingId]);
        Assert::same(200, $allowed->status);
        Assert::contains('id="frm-apForm"', $allowed->html);
        $before = $s->snapshot();
        $hidden = $this->presenters->ui($s->actors['ownSo']->id, 'Ap', ['action' => 'edit', 'id' => $s->otherAp->id]);
        Assert::same(200, $hidden->status);
        Assert::notContains('id="frm-apForm"', $hidden->html);
        Assert::contains('Na tuto operaci nemáte oprávnění!', $hidden->html);
        Assert::same($before, $s->snapshot());
    }
}

(new PermissionApEditPresenterTest())->run();
