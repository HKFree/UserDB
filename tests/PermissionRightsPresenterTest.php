<?php

declare(strict_types=1);

namespace Test;

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

    public function rightsCases(): array {
        return [['cooperative'], ['ownSo'], ['otherSo'], ['support'], ['tech'], ['vv']];
    }

    /** @dataProvider rightsCases */
    public function testRoleAssignment(string $actor): void {
        $s = $this->scenario;
        $target = $actor === 'vv' ? $s->member : $s->actors[$actor];
        $roleId = $this->fixtures->lookup('TypSpravceOblasti', 'VV');
        $post = ['_do' => 'uzivatelRightsForm-submit', 'id' => (string) $target->id, 'rights' => ['n1' => [
            'id' => '', 'Uzivatel_id' => '', 'TypSpravceOblasti_id' => (string) $roleId, 'Oblast_id' => '', 'od' => date('Y-m-d'),
        ]], 'save' => 'Uložit'];
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, 'UzivatelRightsCc', ['action' => 'editrights', 'id' => $target->id], 'POST', $post);
        if ($actor !== 'vv') {
            $s->assertDenied($request);
            return;
        }
        PermissionScenario::assertRedirect($request(), 'uzivatel/show/' . $target->id);
        Assert::same(1, $this->database->table('SpravceOblasti')->where(['Uzivatel_id' => $target->id, 'TypSpravceOblasti_id' => $roleId])->count('*'));
    }

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
        Assert::notNull($cc);
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
