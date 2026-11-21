<?php

declare(strict_types=1);

namespace Test;

use App\Model\Uzivatel;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/** @testCase */
final class NullableNetworkAccessPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($this->ap->id, ['id' => 1]);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    public function testScopedKeyCannotReadDeviceWithoutAp(): void {
        $this->assertUnknownDeviceApDenied('getCredentials', 'GET');
    }

    public function testScopedKeyCannotChangeDeviceWithoutAp(): void {
        $this->assertUnknownDeviceApDenied('setCredentials', 'POST');
    }

    private function assertUnknownDeviceApDenied(string $action, string $method): void {
        $ip = $this->fixtures->insert('IPAdresa', ['ip_adresa' => '192.0.2.180', 'login' => 'original', 'heslo' => 'test-only']);
        $before = serialize($ip->toArray());
        $key = $this->fixtures->apiKey(apId: $this->ap->id);
        $result = $this->presenters->api('Api:Device', ['apikey' . $key->id, $key->klic], [
            'action' => $action, 'id' => $ip->ip_adresa,
        ], $method, ['login' => 'changed', 'heslo' => 'changed-test']);
        Assert::same(403, $result->status);
        Assert::same(['result' => 'FORBIDDEN: device AP is unknown', 'resultNumeric' => -1], $result->json);
        Assert::same($before, serialize($this->requireRow($this->database->table('IPAdresa')->get($ip->id))->toArray()));
        Assert::same(0, $this->database->table('Log')->count('*'));
    }

    public function testUnrestrictedKeyCanReadDeviceWithoutAp(): void {
        $ip = $this->fixtures->insert('IPAdresa', ['ip_adresa' => '192.0.2.181', 'login' => 'test-login', 'heslo' => 'test-only']);
        $key = $this->fixtures->apiKey();
        $result = $this->presenters->api('Api:Device', ['apikey' . $key->id, $key->klic], ['action' => 'getCredentials', 'id' => $ip->ip_adresa]);
        Assert::same(200, $result->status);
        Assert::same(['ip' => $ip->ip_adresa, 'login' => 'test-login', 'heslo' => 'test-only'], $result->json);
    }

    public function testDeviceWritesPreserveOwnApAndUnrestrictedAccess(): void {
        $otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        foreach ([null, $this->ap->id, $otherAp->id] as $keyAp) {
            foreach (['Ap_id' => $this->ap->id, 'Uzivatel_id' => $this->actor->id] as $ownerColumn => $ownerId) {
                $ip = $this->fixtures->insert('IPAdresa', [$ownerColumn => $ownerId, 'ip_adresa' => '192.0.2.182', 'login' => 'old']);
                $key = $this->fixtures->apiKey(apId: $keyAp);
                $credentials = ['apikey' . $key->id, $key->klic];
                $before = serialize($ip->toArray());
                $logs = $this->database->table('Log')->count('*');
                $result = $this->presenters->api('Api:Device', $credentials, ['action' => 'setCredentials', 'id' => $ip->ip_adresa], 'POST', ['login' => 'new', 'heslo' => 'test-new']);
                $stored = $this->requireRow($this->database->table('IPAdresa')->get($ip->id));
                if ($keyAp === $otherAp->id) {
                    Assert::same(403, $result->status);
                    Assert::same($before, serialize($stored->toArray()));
                    Assert::same($logs, $this->database->table('Log')->count('*'));
                } else {
                    Assert::same('OK', $result->json['result']);
                    Assert::same(1, $stored->heslo_sifrovane);
                    $read = $this->presenters->api('Api:Device', $credentials, ['action' => 'getCredentials', 'id' => $ip->ip_adresa]);
                    Assert::same(['ip' => $ip->ip_adresa, 'login' => 'new', 'heslo' => 'test-new'], $read->json);
                    Assert::true($this->database->table('Log')->count('*') > $logs);
                }
                $ip->delete();
            }
        }
    }

    public function testSubnetShowsOccupiedAddressWithoutOwner(): void {
        $this->fixtures->insert('IPAdresa', ['ip_adresa' => '192.0.2.183', 'hostname' => 'private-orphan-host', 'heslo' => 'private-orphan-password']);
        $this->fixtures->ipAddress($this->actor->id, '192.0.2.184', ['hostname' => 'owned-device']);
        $result = $this->presenters->ui($this->actor->id, 'Subnet', ['action' => 'detail', 'id' => '192.0.2']);
        Assert::same(200, $result->status);
        Assert::true(preg_match('~<tr id="highlightable-ip192\.0\.2\.183"[^>]*>.*?</tr>~s', $result->html, $matches) === 1);
        Assert::contains('Obsazeno, vlastník neznámý', $matches[0]);
        Assert::notContains('<a ', $matches[0]);
        Assert::notContains('private-orphan', $matches[0]);
        Assert::contains('owned-device', $result->html);
        Assert::contains('highlightable-ip192.0.2.185', $result->html);
    }

    public function testContactSearchWithoutIdentityReturnsNoMembers(): void {
        $user = $this->container->getByType(User::class);
        Assert::null($user->getIdentity());
        Assert::same([], $this->container->getByType(Uzivatel::class)->findUserByFulltext($this->actor->email, $user));
    }
}

(new NullableNetworkAccessPresenterTest())->run();
