<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class ParameterConditionsPresenterTest extends DatabaseTestCase
{
    public function testAjaxRequestIdKeepsZeroAndEmptyValueSemantics(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($member->id, 'VV');
        foreach ([null, false, 0, 0.0, '', '0', []] as $requestId) {
            $result = $this->presenters->ui($member->id, 'AjaxApi', ['action' => 'getIpDetails', 'ip' => 'invalid', 'reqid' => $requestId]);
            Assert::same(['error' => 'Neplatná IP adresa!'], $result->json);
        }
        foreach (['00', '0.0', 'false', ' ', 1, -1, true, ['0']] as $requestId) {
            $result = $this->presenters->ui($member->id, 'AjaxApi', ['action' => 'getIpDetails', 'ip' => 'invalid', 'reqid' => $requestId]);
            Assert::same(['error' => 'Neplatná IP adresa!', 'reqid' => $requestId], $result->json);
        }
    }

    public function testMoneySwitchCannotBypassPermissions(): void {
        $s = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $s->member->id, 'TypPohybuNaUctu_id' => $this->fixtures->lookup('TypPohybuNaUctu', 'Prichozi platba'),
            'castka' => 100000, 'druzstvo' => 1, 'datum' => date('Y-m-d'), 'poznamka' => 'Test přepínače',
        ]);
        $before = $s->snapshot();
        foreach (['list', 'listall'] as $action) {
            foreach (['otherSo' => false, 'ownSo' => $action === 'list', 'vv' => true] as $actor => $allowed) {
                // Nette converts the persistent integer money parameter before rendering.
                foreach ([[null, false], [0, false], ['0', false], [1, true], [-1, true]] as [$money, $enabled]) {
                    $parameters = ['action' => $action, 'money' => $money] + ($action === 'list' ? ['id' => $s->ap->id] : []);
                    $result = $this->presenters->ui($s->actors[$actor]->id, 'UzivatelList', $parameters);
                    Assert::same(200, $result->status);
                    Assert::same($enabled && $allowed, str_contains($result->html, 'preplatek'), $action . '/' . $actor . '/' . var_export($money, true));
                }
                Assert::exception(
                    fn () => $this->presenters->ui($s->actors[$actor]->id, 'UzivatelList', ['action' => $action, 'money' => '00', 'id' => $s->ap->id]),
                    BadRequestException::class,
                    "Value passed to persistent parameter 'money' in presenter UzivatelList must be int, string given.",
                    404,
                );
            }
        }
        Assert::same($before, $s->snapshot());
    }

    public function testMissingIdsKeepTheirErrorsAndDoNotChangeData(): void {
        $s = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $before = $s->snapshot();
        foreach ([null, 0, '0'] as $id) {
            $result = $this->presenters->ui($s->actors['vv']->id, 'UzivatelList', ['action' => 'list', 'id' => $id]);
            Assert::contains('Chyba, AP s tímto ID neexistuje.', $result->flashes);
            Assert::type(\Nette\Application\Responses\RedirectResponse::class, $result->response);
        }
        Assert::same($before, $s->snapshot());
    }

    public function testMoneyActionsWithEmptyIdsDoNotChangeData(): void {
        $s = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $before = $s->snapshot();
        foreach (['moneyActivate', 'moneyReactivate', 'moneyDeactivate'] as $action) {
            foreach ([null, 0, '0'] as $id) {
                Assert::exception(
                    fn () => $this->presenters->ui($s->actors['vv']->id, 'UzivatelActions', ['action' => $action, 'id' => $id]),
                    BadRequestException::class,
                    "Page not found. Missing template '%a%/app/templates/UzivatelActions/" . $action . ".latte'.",
                    404,
                );
                Assert::same($before, $s->snapshot());
            }
            $result = $this->presenters->ui($s->actors['vv']->id, 'UzivatelActions', ['action' => $action, 'id' => '00']);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/00', 302);
            Assert::same([], $result->flashes);
            Assert::same($before, $s->snapshot());
        }
    }

    public function testMonitoringFiltersKeepZeroSemanticsAndKeyRestriction(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $this->fixtures->ipAddress($member->id, '192.0.2.40');
        $this->fixtures->ipAddress($member->id, '192.0.2.41', ['Uzivatel_id' => null, 'Ap_id' => $ap->id]);
        $key = $this->fixtures->apiKey();
        $credentials = ['apikey' . $key->id, $key->klic];
        foreach ([null, 0, '0', ''] as $filter) {
            foreach ([[0, ['192.0.2.41']], ['0', ['192.0.2.41']], [1, ['192.0.2.40', '192.0.2.41']]] as [$users, $expected]) {
                $result = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'typ' => $filter, 'ap' => $filter, 'uzivatele' => $users]);
                Assert::same(200, $result->status);
                $addresses = array_keys($result->json['zarizeni']);
                sort($addresses);
                Assert::same($expected, $addresses);
            }
        }
        Assert::exception(
            fn () => $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'uzivatele' => '00']),
            BadRequestException::class,
            'Argument $uzivatele passed to App\\ApiModule\\Presenters\\MonitoringPresenter::actionGetZarizeni() must be int, string given.',
            404,
        );
        foreach (['typ' => 'ERROR, typZarizeni 00 not valid', 'ap' => 'ERROR, AP ID 00 does not exist'] as $parameter => $message) {
            $result = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', $parameter => '00']);
            Assert::same(['result' => $message], $result->json);
        }
        $restricted = $this->fixtures->apiKey(apId: $ap->id);
        foreach ([0, '0', '00'] as $requestedAp) {
            $result = $this->presenters->api('Api:Monitoring', ['apikey' . $restricted->id, $restricted->klic], ['action' => 'getZarizeni', 'ap' => $requestedAp]);
            Assert::same(403, $result->status);
            Assert::false(array_key_exists('zarizeni', $result->json));
        }
    }
}

(new ParameterConditionsPresenterTest())->run();
