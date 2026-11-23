<?php

declare(strict_types=1);

namespace Test;

use App\ApiModule\Presenters\AppPresenter;
use App\ApiModule\Presenters\WewimoPresenter;
use App\Model\Uzivatel;
use App\Services\CryptoSluzba;
use Nette\Application;
use Nette\Application\Responses\JsonResponse;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class ApiTypesPresenterTest extends DatabaseTestCase
{
    public function testMonitoringDefaultsToKeyApAndKeepsUrlValues(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $otherAp = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id);
        $otherMember = $this->fixtures->member($otherAp->id);
        $this->fixtures->ipAddress($member->id, '192.0.2.10', ['hostname' => 'member']);
        $this->fixtures->ipAddress($member->id, '192.0.2.11', ['Uzivatel_id' => null, 'Ap_id' => $ap->id, 'hostname' => 'ap']);
        $this->fixtures->ipAddress($otherMember->id, '192.0.2.12', ['Uzivatel_id' => null, 'Ap_id' => $otherAp->id]);
        $key = $this->fixtures->apiKey(apId: $ap->id);
        $credentials = ['apikey' . $key->id, $key->klic];

        $apOnly = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni']);
        Assert::same(200, $apOnly->status);
        Assert::same(['192.0.2.11' => ['hostname' => 'ap', 'popis' => null, 'Ap_id' => $ap->id]], $apOnly->json['zarizeni']);
        Assert::null($apOnly->json['typZarizeni']);
        Assert::same(0, $apOnly->json['uzivatele']);

        $withMembers = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'ap' => (string) $ap->id, 'uzivatele' => '1']);
        Assert::same(1, $withMembers->json['uzivatele']);
        Assert::equal([
            '192.0.2.10' => ['hostname' => 'member', 'popis' => null, 'Uzivatel_id' => $member->id],
            '192.0.2.11' => ['hostname' => 'ap', 'popis' => null, 'Ap_id' => $ap->id],
        ], $withMembers->json['zarizeni']);

        $forbidden = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'ap' => (string) $otherAp->id]);
        Assert::same(403, $forbidden->status);
        Assert::same(['result' => 'FORBIDDEN: not allowed to view AP ' . $otherAp->id, 'resultNumeric' => -1], $forbidden->json);
        $invalidType = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'typ' => '-1']);
        Assert::same(['result' => 'ERROR, typZarizeni -1 not valid'], $invalidType->json);
    }

    public function testRestrictedApAcceptsDecimalIdsAndRejectsNumericAlternatives(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $key = $this->fixtures->apiKey(apId: $ap->id);
        $credentials = ['apikey' . $key->id, $key->klic];
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.25', 'hostname' => 'restricted']);

        foreach ([$ap->id, (string) $ap->id, '00' . $ap->id] as $requestedId) {
            $result = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'ap' => $requestedId]);
            Assert::same(200, $result->status);
            Assert::same(['192.0.2.25' => ['hostname' => 'restricted', 'popis' => null, 'Ap_id' => $ap->id]], $result->json['zarizeni']);
        }

        foreach ([$ap->id . '.0', $ap->id . 'e0', '+' . $ap->id, ' ' . $ap->id, $ap->id . 'suffix', '0', ''] as $requestedId) {
            $result = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'ap' => $requestedId]);
            Assert::same(403, $result->status);
            Assert::same(['result' => 'FORBIDDEN: not allowed to view AP ' . $requestedId, 'resultNumeric' => -1], $result->json);
        }
    }

    public function testDeviceCredentialsKeepNullPlaintextAndEncryptedPasswords(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id);
        $ip = $this->fixtures->ipAddress($member->id, '192.0.2.20', ['login' => 'test-login']);
        $key = $this->fixtures->apiKey(apId: $ap->id);
        $credentials = ['apikey' . $key->id, $key->klic];
        $crypto = $this->container->getByType(CryptoSluzba::class);
        foreach ([[null, 0, null], ['test-only', 0, 'test-only'], [$crypto->encrypt('encrypted-test'), 1, 'encrypted-test']] as [$stored, $encrypted, $expected]) {
            $ip->update(['heslo' => $stored, 'heslo_sifrovane' => $encrypted]);
            $result = $this->presenters->api('Api:Device', $credentials, ['action' => 'getCredentials', 'id' => $ip->ip_adresa]);
            Assert::same(200, $result->status);
            Assert::same(['ip' => $ip->ip_adresa, 'login' => 'test-login', 'heslo' => $expected], $result->json);
        }

        $missing = $this->presenters->api('Api:Device', $credentials, ['action' => 'getCredentials']);
        Assert::same('IP address not found', $missing->json['result']);
        $otherKey = $this->fixtures->apiKey(apId: $this->fixtures->ap($area->id)->id);
        $forbidden = $this->presenters->api('Api:Device', ['apikey' . $otherKey->id, $otherKey->klic], ['action' => 'getCredentials', 'id' => $ip->ip_adresa]);
        Assert::same(403, $forbidden->status);
        Assert::false(array_key_exists('heslo', $forbidden->json));
        $wrongMethod = $this->presenters->api('Api:Device', $credentials, ['action' => 'setCredentials', 'id' => $ip->ip_adresa]);
        Assert::same(405, $wrongMethod->status);
        Assert::same('METHOD_NOT_ALLOWED: Use http method POST', $wrongMethod->json['result']);
    }

    public function testMemberApiRestrictsApAndOmitsCredentials(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['heslo' => 'test-only', 'heslo_strong_hash' => 'test-only']);
        $ip = $this->fixtures->ipAddress($member->id, '192.0.2.30');
        $key = $this->fixtures->apiKey(apId: $ap->id);
        $credentials = ['apikey' . $key->id, $key->klic];
        $detail = $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatel', 'id' => (string) $member->id]);
        Assert::same('OK', $detail->json['result']);
        Assert::same($member->email, $detail->json['uzivatel']['email']);
        Assert::false(array_key_exists('heslo', $detail->json['uzivatel']));
        Assert::false(array_key_exists('heslo_strong_hash', $detail->json['uzivatel']));
        $byIp = $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatelbyIP', 'id' => $ip->ip_adresa]);
        Assert::same(['result' => 'OK', 'Uzivatel_id' => $member->id], $byIp->json);
        $missing = $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatel', 'id' => '-1']);
        Assert::same(404, $missing->status);
        Assert::same(['result' => 'User ID not found'], $missing->json);
        $otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $otherMember = $this->fixtures->member($otherAp->id);
        $forbidden = $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatel', 'id' => (string) $otherMember->id]);
        Assert::same(403, $forbidden->status);
        Assert::false(array_key_exists('uzivatel', $forbidden->json));
    }

    public function testAppMissingAndWrongTokensDoNotAuthenticateOrUpdateUsage(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $token = $this->fixtures->insert('AplikaceToken', [
            'Uzivatel_id' => $member->id, 'token' => str_repeat('x', 64),
            'pouzit_poprve' => '2001-01-01 00:00:00', 'pouzit_naposledy' => '2001-01-01 00:00:00',
        ]);
        foreach (['getMembership', 'getMap'] as $action) {
            foreach ([[], ['uid' => (string) $member->id], ['uid' => (string) $member->id, 'token' => 'wrong-token']] as $parameters) {
                Assert::same(['result' => 'Token invalid'], $this->appRequest(['action' => $action] + $parameters));
            }
        }
        Assert::same('2001-01-01', $this->requireRow($this->database->table('AplikaceToken')->get($token->id))->pouzit_naposledy->format('Y-m-d'));
        $logs = $this->database->table('AplikaceLog')->where('action', 'app.verifyToken.failed');
        Assert::same(6, $logs->count('*'));
        Assert::same(['192.0.2.100'], array_values(array_unique($logs->fetchPairs('id', 'ip'))));
    }

    public function testAppLoginAndMembershipKeepResponseAndAudit(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, [
            'heslo_strong_hash' => $this->container->getByType(Uzivatel::class)->generateStrongHash('test-only'),
        ]);
        Assert::same(['result' => 'Login failed'], $this->appRequest(['action' => 'getToken']));
        Assert::same(['result' => 'Login failed'], $this->appRequest(['action' => 'getToken'], 'POST', ['uid' => (string) $member->id, 'heslo' => 'wrong']));
        $login = $this->appRequest(['action' => 'getToken'], 'POST', ['uid' => (string) $member->id, 'heslo' => 'test-only']);
        Assert::same('OK', $login['result']);
        Assert::same(64, strlen($login['token']));
        $membership = $this->appRequest(['action' => 'getMembership', 'uid' => (string) $member->id, 'token' => $login['token']]);
        Assert::same(['result' => 'OK', 'clenstvi' => $member->TypClenstvi->text, 'jmeno' => $member->jmeno], $membership);
        Assert::same(1, $this->database->table('AplikaceLog')->where('action', 'app.getMembership.successful')->count('*'));
    }

    public function testWewimoSanitizerKeepsMixedValuesAndNestedKeys(): void {
        $presenter = $this->container->getByType(Application\IPresenterFactory::class)->createPresenter('Api:Wewimo');
        if (($presenter instanceof WewimoPresenter) === false) {
            throw new \LogicException('Expected API Wewimo presenter.');
        }
        foreach ([null, false, true, 12, 1.5, []] as $value) {
            Assert::same($value, $presenter->sanitize($value));
        }
        Assert::error(function () use ($presenter): void {
            Assert::same([3 => ['name' => 'café', 'empty' => null, 'active' => false]], $presenter->sanitize([3 => ['name' => "caf\xe9", 'empty' => null, 'active' => false]]));
        }, E_DEPRECATED, 'Function utf8_encode() is deprecated');
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function appRequest(array $parameters, string $method = 'GET', array $post = []): array {
        $key = $this->fixtures->apiKey(presenter: 'Api:App');
        $savedServer = $_SERVER;
        $savedStatus = http_response_code();
        try {
            $_SERVER['PHP_AUTH_USER'] = 'apikey' . $key->id;
            $_SERVER['PHP_AUTH_PW'] = $key->klic;
            http_response_code(200);
            // Application audit requires an IP; the shared presenter runner has no remote address.
            $request = new Http\Request(new Http\UrlScript('http://localhost/userdb/api/app'), post: $post, method: $method, remoteAddress: '192.0.2.100');
            $container = TestEnvironment::container($this->database, $request, $this->container);
            $presenter = $container->getByType(Application\IPresenterFactory::class)->createPresenter('Api:App');
            if (($presenter instanceof AppPresenter) === false) {
                throw new \LogicException('Expected API App presenter.');
            }
            $presenter->autoCanonicalize = false;
            $response = $presenter->run(new Application\Request('Api:App', $method, $parameters, $post));
            Assert::same(200, $container->getByType(Http\Response::class)->getCode());
            if (($response instanceof JsonResponse) === false) {
                throw new \LogicException('Expected a JSON response.');
            }
            return $response->getPayload();
        } finally {
            $_SERVER = $savedServer;
            http_response_code(is_int($savedStatus) ? $savedStatus : 200);
        }
    }
}

(new ApiTypesPresenterTest())->run();
