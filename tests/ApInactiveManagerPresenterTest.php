<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class ApInactiveManagerPresenterTest extends DatabaseTestCase
{
    public function testNewApStartsWithTheFlagOff(): void {
        $area = $this->fixtures->area();
        $existingAp = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($existingAp->id);
        $this->fixtures->role($actor->id, 'VV');

        $this->assertCheckbox($actor->id, null, false);
        $name = $this->fixtures->name('New AP');
        $result = $this->presenters->ui($actor->id, 'Ap', ['action' => 'edit'], 'POST', $this->apFields($area->id, $name));
        $ap = $this->database->table('Ap')->where('jmeno', $name)->fetch();
        Assert::true($ap !== null);
        PermissionScenario::assertRedirect($result, 'ap/show/' . $ap->id);
        $this->assertCheckbox($actor->id, $ap->id, false);
    }

    public function testFlagCanBeCheckedAndClearedOnAnExistingAp(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $edit = ['action' => 'edit', 'id' => $ap->id];
        $show = ['action' => 'show', 'id' => $ap->id];
        $post = ['id' => (string) $ap->id] + $this->apFields($area->id, $ap->jmeno);

        $this->assertCheckbox($actor->id, $ap->id, false);
        Assert::notContains('Správce neaktivní', $this->presenters->ui($actor->id, 'Ap', $show)->html);

        $result = $this->presenters->ui($actor->id, 'Ap', $edit, 'POST', $post + ['spravce_neaktivni' => 'on']);
        PermissionScenario::assertRedirect($result, 'ap/show/' . $ap->id);
        $this->assertCheckbox($actor->id, $ap->id, true);
        $detail = $this->presenters->ui($actor->id, 'Ap', $show);
        Assert::same(200, $detail->status);
        Assert::match('~Adresa AP:.*?Testovací 1, Hradec Králové, 50002.*?Správce neaktivní~s', $detail->html);

        $result = $this->presenters->ui($actor->id, 'Ap', $edit, 'POST', $post);
        PermissionScenario::assertRedirect($result, 'ap/show/' . $ap->id);
        $this->assertCheckbox($actor->id, $ap->id, false);
        Assert::notContains('Správce neaktivní', $this->presenters->ui($actor->id, 'Ap', $show)->html);
    }

    public function testDetailShowsTheFlagEvenWithoutAnAddress(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $ap->update(['spravce_neaktivni' => true]);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');

        $detail = $this->presenters->ui($actor->id, 'Ap', ['action' => 'show', 'id' => $ap->id]);
        Assert::same(200, $detail->status);
        Assert::match('~<p>\s*<small class="text-muted">Správce neaktivní</small>\s*</p>~', $detail->html);
    }

    private function assertCheckbox(int $actorId, ?int $apId, bool $checked): void {
        $edit = $this->presenters->ui($actorId, 'Ap', ['action' => 'edit', 'id' => $apId]);
        Assert::same(200, $edit->status);
        $dom = DomQuery::fromHtml($edit->html);
        $labels = $dom->find('label[for="frm-apForm-spravce_neaktivni"]');
        Assert::count(1, $labels);
        Assert::same('Správce neaktivní', trim((string) $labels[0]));
        $inputs = $dom->find('input[type="checkbox"][name="spravce_neaktivni"]');
        Assert::count(1, $inputs);
        Assert::same($checked, isset($inputs[0]['checked']));
    }

    /** @return array<string, string> */
    private function apFields(int $areaId, string $name): array {
        return [
            '_do' => 'apForm-submit', 'id' => '', 'aktivni' => 'on', 'jmeno' => $name,
            'Oblast_id' => (string) $areaId, 'gps' => '50.209722,15.832778',
            'ulice_cp' => 'Testovací 1', 'mesto' => 'Hradec Králové', 'psc' => '50002', 'save' => 'Uložit',
        ];
    }
}

(new ApInactiveManagerPresenterTest())->run();
