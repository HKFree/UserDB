<?php

declare(strict_types=1);

/** @testCase */

namespace Test;

use App\Model\AP;
use App\Model\AccountActivation;
use App\Model\HkfIdentity;
use App\Model\IPAdresa;
use App\Model\Komunikace;
use App\Model\Oblast;
use App\Model\PrichoziPlatba;
use App\Model\StitekUzivatele;
use App\Model\Subnet;
use App\Model\Uzivatel;
use App\Services\SmsSenderException;
use Nette\Security\User;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class NullableModelsDatabaseTest extends DatabaseTestCase
{
    public function testOptionalIdsAndUnknownIdsReturnNull(): void {
        foreach ([
            $this->container->getByType(AP::class)->getAP(...),
            $this->container->getByType(Oblast::class)->getOblast(...),
            $this->container->getByType(IPAdresa::class)->getIPAdresa(...),
            $this->container->getByType(Subnet::class)->getSubnet(...),
            $this->container->getByType(PrichoziPlatba::class)->getPrichoziPlatba(...),
        ] as $find) {
            foreach ([null, 0, '0'] as $id) {
                Assert::null($find($id));
            }
        }
    }

    public function testUpdateMissingRowDoesNotChangeAnotherRow(): void {
        $area = $this->fixtures->area();
        $before = serialize($area->toArray());
        $areas = $this->container->getByType(Oblast::class);
        Assert::exception(fn () => $areas->update(0, ['jmeno' => 'Changed']), \InvalidArgumentException::class, 'Záznam pro úpravu nebyl nalezen.');
        Assert::same($before, serialize($this->requireRow($areas->find($area->id))->toArray()));
        Assert::null($areas->find(0));
    }

    public function testMissingAreaHasNoAdministrators(): void {
        $areas = $this->container->getByType(Oblast::class);
        $area = $this->fixtures->area();
        $member = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        Assert::same([], $areas->getSeznamSpravcu($area->id));
        $this->fixtures->role($member->id, 'SO', $area->id);
        $administrators = $areas->getSeznamSpravcu($area->id);
        Assert::same([$member->id], array_keys($administrators));
        Assert::same($member->nick, $administrators[$member->id]->nick);
        Assert::same([], $areas->getSeznamSpravcu(0));
    }

    public function testNameForMissingMemberIsNull(): void {
        $members = $this->container->getByType(Uzivatel::class);
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        Assert::same($member->jmeno . ' ' . $member->prijmeni, $members->nazevUzivatele($member->id));
        Assert::null($members->nazevUzivatele(0));
    }

    public function testMissingTagCannotCreateAnAssignmentOrAudit(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $assignments = $this->container->getByType(StitekUzivatele::class);
        $before = $this->database->table('Log')->count('*');
        Assert::exception(fn () => $assignments->createStitekUzivatele(['Uzivatel_id' => $member->id, 'Stitek_id' => 0]), \InvalidArgumentException::class, 'Štítek nebyl nalezen.');
        Assert::same([], $assignments->getStitekByUserId($member->id));
        Assert::same($before, $this->database->table('Log')->count('*'));
    }

    public function testRemovingMissingTagDoesNotWriteAudit(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $assignments = $this->container->getByType(StitekUzivatele::class);
        $before = $this->database->table('Log')->count('*');
        Assert::same(0, $assignments->odstranStitek(0, $member->id));
        Assert::same($before, $this->database->table('Log')->count('*'));
    }

    public function testMissingApCannotGrantAreaRole(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id);
        $user = $this->container->getByType(User::class);
        $aps = $this->container->getByType(AP::class);
        foreach (['SO', 'ZSO'] as $role) {
            $user->login(new HkfIdentity($member->id, [$role . '-' . $area->id], $member->nick, 'test-only'));
            Assert::true($aps->canViewOrEditAP($ap->id, $user));
            Assert::false($aps->canViewOrEditAP(null, $user));
            Assert::false($aps->canViewOrEditAP(0, $user));
            Assert::false($aps->canViewOrEditAP($this->fixtures->ap($this->fixtures->area()->id)->id, $user));
        }
        foreach (['TECH', 'VV', 'KONTROLA'] as $role) {
            $user->login(new HkfIdentity($member->id, [$role], $member->nick, 'test-only'));
            Assert::true($aps->canViewOrEditAP(null, $user));
            Assert::true($aps->canViewOrEditAP($ap->id, $user));
        }
    }

    public function testActivationWithoutIdentityCannotChangeAccount(): void {
        $this->assertMissingIdentity('activateAccount', 0, 0);
    }

    public function testReactivationWithoutIdentityCannotChangeAccount(): void {
        $this->assertMissingIdentity('reactivateAccount', 0, 1);
    }

    public function testCancelDeactivationWithoutIdentityCannotChangeAccount(): void {
        $this->assertMissingIdentity('reactivateAccount', 1, 1);
    }

    public function testDeactivationWithoutIdentityCannotChangeAccount(): void {
        $this->assertMissingIdentity('deactivateAccount', 1, 0);
    }

    private function assertMissingIdentity(string $method, int $active, int $deactivation): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id, ['money_aktivni' => $active, 'money_deaktivace' => $deactivation]);
        $user = $this->container->getByType(User::class);
        $user->logout(true);
        $activation = $this->container->getByType(AccountActivation::class);
        $before = serialize($member->toArray());
        $movements = $this->database->table('UzivatelskeKonto')->count('*');
        $change = match ($method) {
            'activateAccount' => $activation->activateAccount(...),
            'reactivateAccount' => $activation->reactivateAccount(...),
            'deactivateAccount' => $activation->deactivateAccount(...),
            default => throw new \InvalidArgumentException('Unknown account change.'),
        };
        Assert::exception(fn () => $change($user, $member->id), \LogicException::class, 'Pro změnu účtu je nutná identita uživatele.');
        Assert::same($before, serialize($this->requireRow($this->database->table('Uzivatel')->get($member->id))->toArray()));
        Assert::same($movements, $this->database->table('UzivatelskeKonto')->count('*'));
    }

    public function testMissingSmsRecipientDoesNotSendToOtherRecipients(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $communication = $this->container->getByType(Komunikace::class);
        Assert::exception(fn () => $communication->posliSMS([$member->id, 0], 'Test'), SmsSenderException::class, 'Uživatel pro odeslání SMS nebyl nalezen.');
        Assert::same([], $this->container->getByType(RecordingMexSmsSender::class)->calls);
    }

    public function testMissingSmsRecipientHasNoValidNumber(): void {
        Assert::false($this->container->getByType(Komunikace::class)->maValidniCislo(0));
    }

    public function testMissingOrUnassignedIpHasNoAp(): void {
        $ips = $this->container->getByType(IPAdresa::class);
        Assert::null($ips->getAPOfIP(0));
        $orphan = $this->fixtures->insert('IPAdresa', ['ip_adresa' => '192.0.2.1', 'Ap_id' => null, 'Uzivatel_id' => null]);
        Assert::null($ips->getAPOfIP($orphan->id));
    }
}

(new NullableModelsDatabaseTest())->run();
