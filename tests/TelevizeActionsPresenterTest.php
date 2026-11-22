<?php

declare(strict_types=1);

namespace Test;

use App\Model\Parameters;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Activating the TV service charges the member's account and records who did it.
 */
final class TelevizeActionsPresenterTest extends DatabaseTestCase
{
    public function testActivationChargesAccountAndNamesTheActor(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $member = $this->fixtures->member($ap->id);
        $price = $this->container->getByType(Parameters::class)->getCenaSledovaniTV();
        Assert::true($price > 0);

        $result = $this->presenters->ui($actor->id, 'UzivatelTelevizeActions', ['action' => 'activate', 'id' => $member->id]);

        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $member->id, 302);
        $entries = $this->database->table('UzivatelskeKonto')->where('Uzivatel_id', $member->id)->fetchAll();
        Assert::count(1, $entries);
        $entry = reset($entries);
        Assert::true($entry instanceof \Nette\Database\Table\ActiveRow);
        Assert::same('[UID' . $actor->id . ' ' . $actor->nick . '] Aktivace služby Televize na 1 kalendářní měsíc', $entry->poznamka);
        Assert::equal(-(float) $price, (float) $entry->castka);
        Assert::same($actor->id, $entry->zmenu_provedl);
        $active = $this->database->table('UzivatelTelevizeAktivni')->where('Uzivatel_id', $member->id)->fetchAll();
        Assert::count(1, $active);
        $activation = reset($active);
        Assert::true($activation instanceof \Nette\Database\Table\ActiveRow);
        Assert::same('Placená aktivace na 1 kalendářní měsíc', $activation->poznamka);
    }
}

(new TelevizeActionsPresenterTest())->run();
