<?php

declare(strict_types=1);

namespace Test;

use App\MapiModule\Data\Cteni;
use App\MapiModule\Data\Ja;
use App\MapiModule\Data\Prihlaseny;
use App\MapiModule\Data\PristupOdepren;
use App\MapiModule\Data\RolePrihlaseneho;
use App\Model\Authenticator;
use App\Model\Oblast;
use App\Model\SpravceOblasti;
use Nette\Database\Connection;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Read layer of the GraphQL API (issue #264, H3): what it returns for "ja", that it agrees with the web's side
 * panel (decision 13) and how many queries it needs.
 */
final class GraphqlReadLayerDatabaseTest extends DatabaseTestCase
{
    /** @var array<string, mixed> */
    private array $savedServer;
    private Cteni $cteni;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $this->savedServer = $_SERVER;
        $this->cteni = $this->container->getByType(Cteni::class);
        $this->member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $_SERVER['HTTP_GIVENNAME'] = $this->member->nick;
        $_SERVER['HTTP_INITIALS'] = 'test-only';
    }

    protected function tearDown(): void {
        $_SERVER = $this->savedServer;
        parent::tearDown();
    }

    public function testJaAgreesWithTheSidePanelOfTheWeb(): void {
        $busy = $this->fixtures->area();
        $busyAps = [$this->fixtures->ap($busy->id), $this->fixtures->ap($busy->id)];
        $inactive = $this->fixtures->ap($busy->id);
        $inactive->update(['aktivni' => 0]);
        $single = $this->fixtures->area();
        $singleAp = $this->fixtures->ap($single->id);
        $outsideList = $this->fixtures->area();
        $outsideListAp = $this->fixtures->ap($outsideList->id);
        $empty = $this->fixtures->area();
        $expired = $this->fixtures->area();
        $this->fixtures->ap($expired->id);
        $this->fixtures->role($this->member->id, 'SO', $busy->id);
        $this->fixtures->role($this->member->id, 'ZSO', $busy->id);
        $this->fixtures->role($this->member->id, 'ZSO', $single->id);
        $this->fixtures->role($this->member->id, 'PMVTEAM', $outsideList->id);
        $this->fixtures->role($this->member->id, 'SO', $empty->id);
        $this->fixtures->role($this->member->id, 'SO', $expired->id, until: '2001-01-01');
        $this->fixtures->role($this->member->id, 'VV');

        $ja = $this->cteni->ja($this->signedIn());

        Assert::same($this->member->id, $ja->uid);
        Assert::same($this->member->nick, $ja->nick);
        $areaIds = array_map(static fn (\App\MapiModule\Data\Oblast $oblast): int => $oblast->id, $ja->oblasti);
        Assert::same(count($areaIds), count(array_unique($areaIds)), 'An area is listed once even with two roles in it.');
        $expectedAreas = [$busy->id, $single->id, $outsideList->id, $empty->id];
        sort($expectedAreas);
        $sortedAreas = $areaIds;
        sort($sortedAreas);
        Assert::same($expectedAreas, $sortedAreas);

        $web = $this->container->getByType(Oblast::class)->formatujOblastiSAP(
            $this->container->getByType(SpravceOblasti::class)->getOblastiSpravce($this->member->id),
        );
        Assert::same($web, $this->sidePanel($ja));
        $expectedAps = [$busyAps[0]->id, $busyAps[1]->id, $singleAp->id, $outsideListAp->id];
        sort($expectedAps);
        $listedAps = array_keys($web);
        sort($listedAps);
        Assert::same($expectedAps, $listedAps, 'The web lists the active APs of every area-bound role.');

        $roles = array_map(static fn (\App\MapiModule\Data\Role $role): string => $role->typ . ($role->oblast === null ? '' : '-' . $role->oblast->id), $ja->role);
        sort($roles);
        $expectedRoles = ['SO-' . $busy->id, 'ZSO-' . $busy->id, 'ZSO-' . $single->id, 'PMVTEAM-' . $outsideList->id, 'SO-' . $empty->id, 'VV'];
        sort($expectedRoles);
        Assert::same($expectedRoles, $roles);
    }

    public function testJaIsRefusedWithoutAnAllowedRole(): void {
        $this->fixtures->role($this->member->id, 'DRUŽSTEVNÍK', $this->fixtures->area()->id);
        $signedIn = $this->signedIn();
        $queries = $this->countQueries(function () use ($signedIn): void {
            Assert::exception(fn () => $this->cteni->ja($signedIn), PristupOdepren::class);
        });
        Assert::same(0, $queries, 'Permission is decided before anything is read.');
    }

    public function testQueriesDoNotGrowWithAreasAndAps(): void {
        $this->fixtures->role($this->member->id, 'VV');
        $global = $this->signedIn();
        Assert::same(0, $this->countQueries(fn () => $this->cteni->ja($global)));

        $counts = [];
        foreach ([1, 4] as $areas) {
            for ($i = 0; $i < $areas; $i++) {
                $area = $this->fixtures->area();
                $this->fixtures->ap($area->id);
                $this->fixtures->ap($area->id);
                $this->fixtures->role($this->member->id, 'SO', $area->id);
            }
            $signedIn = $this->signedIn();
            $counts[] = $this->countQueries(function () use ($signedIn, $areas): void {
                Assert::true(count($this->cteni->ja($signedIn)->oblasti) >= $areas);
            });
        }
        Assert::same([2, 2], $counts);
    }

    /** Signs in the way the web does, so the roles come from the same Authenticator. */
    private function signedIn(): Prihlaseny {
        $identity = $this->container->getByType(Authenticator::class)->authenticate([$this->member->id, null]);
        return new Prihlaseny($identity->getUid(), $identity->getNick(), array_map(RolePrihlaseneho::zTextu(...), array_values($identity->getRoles())));
    }

    /**
     * The side panel as Oblast::formatujOblastiSAP() builds it, from the data of the read layer.
     * @return array<int, string>
     */
    private function sidePanel(Ja $ja): array {
        $panel = [];
        foreach ($ja->oblasti as $oblast) {
            foreach ($oblast->ap as $ap) {
                $panel[$ap->id] = (count($oblast->ap) === 1 ? '' : $oblast->jmeno . ' - ') . $ap->jmeno . ' (' . $ap->id . ')';
            }
        }
        return $panel;
    }

    private function countQueries(callable $read): int {
        $connection = $this->container->getByType(Connection::class);
        $count = 0;
        $listeners = $connection->onQuery;
        $connection->onQuery[] = static function () use (&$count): void {
            $count++;
        };
        try {
            $read();
        } finally {
            $connection->onQuery = $listeners;
        }
        return $count;
    }
}

(new GraphqlReadLayerDatabaseTest())->run();
