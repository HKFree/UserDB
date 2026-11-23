<?php

declare(strict_types=1);

namespace Test;

use App\Components\UserLabelsComponent;
use App\Model\HkfIdentity;
use App\Presenters\SpravaGrafuPresenter;
use App\Presenters\SpravaPresenter;
use App\Presenters\SpravaStitkuPresenter;
use App\Presenters\UzivatelPresenter;
use App\Services\CryptoSluzba;
use Nette\Application\IPresenterFactory;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class ParentConstructorDatabaseTest extends DatabaseTestCase
{
    private ActiveRow $area;
    private ActiveRow $ap;
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $this->area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($this->area->id);
        $this->actor = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->actor->id, 'SO', $this->area->id);
        $this->member = $this->fixtures->member($this->ap->id, [
            'location_status' => 'valid', 'latitude' => 50.21, 'longitude' => 15.83,
            'druzstvo' => 1, 'spolek' => 0, 'smazano' => 0,
        ]);
    }

    /** @return list<array{string}> */
    public function mapCases(): array {
        return [
            ['Sprava'], ['SpravaCc'], ['SpravaGrafu'], ['SpravaMailu'],
            ['SpravaNesparovanych'], ['SpravaOblasti'], ['SpravaPlateb'],
            ['SpravaSifrovani'], ['SpravaSms'], ['SpravaStitku'], ['SpravaUctu'],
        ];
    }

    /** @dataProvider mapCases */
    public function testInheritedMapFiltersMembersByArea(string $name): void {
        $otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->member($otherAp->id, [
            'location_status' => 'valid', 'latitude' => 50.22, 'longitude' => 15.84,
            'druzstvo' => 1, 'spolek' => 0, 'smazano' => 0,
        ]);
        $presenter = $this->presenter($name, SpravaPresenter::class);
        $presenter->renderMapa();

        $data = json_decode($presenter->getTemplate()->data, true, flags: JSON_THROW_ON_ERROR);
        Assert::count(1, $data);
        Assert::same(50.21, $data[0]['lat']);
        Assert::same(15.83, $data[0]['lon']);
        Assert::same(0, $data[0]['ax']);
        Assert::same([[
            'id' => $this->member->id,
            'ni' => $this->member->nick,
            'jm' => $this->member->jmeno,
            'pr' => $this->member->prijmeni,
            'li' => '/userdb/uzivatel/show/' . $this->member->id,
        ]], $data[0]['us']);

        $this->member->update(['location_status' => 'unknown']);
        $presenter->renderMapa();
        Assert::same('[]', $presenter->getTemplate()->data);
    }

    /** @return list<array{string}> */
    public function memberCases(): array {
        return [
            ['Uzivatel'], ['Mailing'], ['UzivatelAccount'], ['UzivatelActions'],
            ['UzivatelMailSms'], ['UzivatelRightsCc'], ['UzivatelTelevizeActions'],
        ];
    }

    /** @dataProvider memberCases */
    public function testInheritedMemberFormAndLabels(string $name): void {
        $tag = $this->fixtures->insert('Stitek', [
            'Oblast_id' => $this->area->id, 'text' => 'Štítek člena',
            'barva_pozadi' => '#123456', 'barva_popredi' => '#ffffff', 'poznamka' => 'Test',
        ]);
        $this->database->table('StitekUzivatele')->insert([
            'Uzivatel_id' => $this->member->id, 'Stitek_id' => $tag->id,
            'kdy_vytvoreno' => '2026-01-02 03:04:05',
        ]);
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.0.2', [
            'heslo' => $this->container->getByType(CryptoSluzba::class)->encrypt('test-password'),
            'heslo_sifrovane' => 1,
        ]);
        $presenter = $this->presenter($name, UzivatelPresenter::class);
        $form = $presenter->getComponent('uzivatelForm');
        Assert::type(Form::class, $form);
        $values = $form->getUntrustedValues('array');
        Assert::same($this->member->jmeno, $values['jmeno']);
        Assert::same($this->member->email, $values['email']);
        Assert::same($this->member->Ap_id, $values['Ap_id']);
        Assert::same('test-password', $values['ip'][$ip->id]['heslo']);

        $labels = $presenter->getComponent('userLabels');
        Assert::type(UserLabelsComponent::class, $labels);
        ob_start();
        try {
            $labels->render();
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $dom = DomQuery::fromHtml($html);
        $badges = $dom->find('.user-labels abbr');
        Assert::count(1, $badges);
        Assert::same('Štítek člena', trim((string) $badges[0]));
        $remove = $dom->find('.badge-remove');
        Assert::count(1, $remove);
        Assert::same((string) $tag->id, (string) $remove[0]['data-stitek-id']);
        Assert::same((string) $this->member->id, (string) $remove[0]['data-user-id']);
    }

    public function testGraphRetainsOwnDependencies(): void {
        $presenter = $this->presenter('SpravaGrafu', SpravaGrafuPresenter::class);
        $presenter->renderUsersgraph();
        Assert::same('[]', $presenter->getTemplate()->actdata);
        foreach ([4, 7] as $type) {
            $this->fixtures->insert('UzivatelskeKonto', [
                'Uzivatel_id' => $this->member->id, 'TypPohybuNaUctu_id' => $type,
                'castka' => -100, 'datum' => '2026-01-15', 'zmenu_provedl' => $this->actor->id,
            ]);
        }
        $presenter->renderUsersgraph();
        Assert::same([
            ['x' => (new \DateTimeImmutable('2026-01-01'))->getTimestamp(), 'y' => 2],
        ], json_decode($presenter->getTemplate()->actdata, true, flags: JSON_THROW_ON_ERROR));
    }

    public function testTagEditorRetainsOwnDependencies(): void {
        $tag = $this->fixtures->insert('Stitek', [
            'Oblast_id' => $this->area->id, 'text' => 'Upravovaný štítek',
            'barva_pozadi' => '#123456', 'barva_popredi' => '#ffffff', 'poznamka' => 'Poznámka',
        ]);
        $presenter = $this->presenter('SpravaStitku', SpravaStitkuPresenter::class);
        $presenter->loadState(['id' => $tag->id]);
        $form = $presenter->getComponent('editStitekForm');
        Assert::type(Form::class, $form);
        Assert::same([
            'text' => 'Upravovaný štítek', 'barva_pozadi' => '#123456',
            'barva_popredi' => '#ffffff', 'poznamka' => 'Poznámka',
        ], $form->getUntrustedValues('array'));
    }

    /**
     * @template T of Presenter
     * @param class-string<T> $type
     * @return T
     */
    private function presenter(string $name, string $type): Presenter {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter($name);
        if (($presenter instanceof $type) === false) {
            throw new \LogicException("Unexpected presenter type for $name.");
        }
        $presenter->setParent(null, $name);
        $presenter->loadState(['id' => $this->member->id]);
        $presenter->getUser()->login(new HkfIdentity($this->actor->id, ['SO', 'SO-' . $this->area->id], $this->actor->nick, 'test-only'));
        return $presenter;
    }
}

(new ParentConstructorDatabaseTest())->run();
