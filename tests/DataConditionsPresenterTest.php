<?php

declare(strict_types=1);

namespace Test;

use App\Model\IPAdresa;
use App\Model\Parameters;
use App\Model\Subnet;
use App\Model\Wewimo;
use App\Services\CryptoSluzba;
use App\Services\GeneratorSmlouvy;
use Nette\Application\Responses\TextResponse;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\Html;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

$_SERVER['HTTP_USER_AGENT'] = 'UserDB tests';

final class DataConditionsPresenterTest extends DatabaseTestCase
{
    public function testDatabaseFlagsKeepNonzeroValuesEnabled(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $ip = $this->fixtures->ipAddress($member->id, '192.0.2.42');
        $subnet = $this->fixtures->subnet($ap->id, '192.0.2.0/24', '192.0.2.1');
        $flags = ['internet' => 'transfer', 'smokeping' => 'eye-open', 'dhcp' => 'open', 'mac_filter' => 'filter', 'wewimo' => 'signal'];
        foreach ([[0, false], [1, true], [2, true], [-1, true]] as [$value, $enabled]) {
            $ip->update(array_fill_keys(array_keys($flags), $value));
            foreach ([null, []] as $subnetMode) {
                $table = Html::el('table');
                $this->container->getByType(IPAdresa::class)->addIPTableRow($ip, false, $table, $subnetMode);
                Assert::contains('highlightable-ip192.0.2.42', (string) $table);
                foreach ($flags as $icon) {
                    Assert::same($enabled, str_contains((string) $table, 'glyphicon glyphicon-' . $icon . '"'));
                }
            }
            $subnet->update(['arp_proxy' => $value]);
            $html = (string) $this->container->getByType(Subnet::class)->getSubnetTable([$subnet]);
            Assert::contains($enabled ? '>zapnuto<' : '>vypnuto<', $html);
        }
    }

    public function testBooleanDatabaseFlagsPreserveEnabledAndDisabledBranches(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id);
        $this->fixtures->role($member->id, 'VV');
        $this->fixtures->role($member->id, 'SO', $area->id);
        $subnet = $this->fixtures->subnet($ap->id, '192.0.2.0/24', '192.0.2.1');
        $key = $this->fixtures->apiKey();
        $salt = $this->container->getByType(Parameters::class)->salt;
        $confirmationKey = base64_encode($member->id . '-' . md5($salt . $member->zalozen));

        // Exercise Nette's optional TINYINT(1) conversion on the same transactional connection.
        $driver = $this->database->getConnection()->getDriver();
        $conversion = new \ReflectionProperty($driver, 'convertBoolean');
        $previous = $conversion->getValue($driver);
        $conversion->setValue($driver, true);
        try {
            foreach ([false, true] as $enabled) {
                $ap->update(['aktivni' => $enabled]);
                $subnet->update(['arp_proxy' => $enabled]);
                $member->update(['systemovy' => $enabled, 'spolek' => !$enabled, 'druzstvo' => $enabled, 'regform_downloaded_password_sent' => 0]);
                $storedSubnet = $this->database->table('Subnet')->get($subnet->id);
                Assert::same($enabled, $storedSubnet->arp_proxy);
                $subnetModel = $this->container->getByType(Subnet::class);
                Assert::contains($enabled ? '>zapnuto<' : '>vypnuto<', (string) $subnetModel->getSubnetTable([$storedSubnet]));
                Assert::same($enabled ? Subnet::ARP_PROXY_GW : '192.0.2.1', $subnetModel->getSubnetOfIP('192.0.2.42')['gateway']);

                Assert::same($enabled, $this->database->table('Ap')->get($ap->id)->aktivni);
                $list = $this->presenters->ui($member->id, 'Ap', ['action' => 'list', 'id' => $area->id]);
                Assert::same(!$enabled, str_contains($list->html, '<s>' . $ap->jmeno . '</s>'));
                Assert::same($enabled, $this->database->table('Uzivatel')->get($member->id)->systemovy);
                $areas = $this->presenters->api('Api:Areas', ['apikey' . $key->id, $key->klic]);
                Assert::same($enabled ? [] : [$member->id], array_keys($areas->json[$area->id]['admins']));

                Assert::same($enabled, $this->database->table('Uzivatel')->get($member->id)->druzstvo);
                Assert::same(!$enabled, $this->database->table('Uzivatel')->get($member->id)->spolek);
                $confirmation = $this->presenters->ui($member->id, 'SelfService', ['action' => 'confirmEmail', 'key' => $confirmationKey]);
                Assert::contains($enabled ? 'Ověření proběhlo úspěšně' : 'Vaše registrace do hkfree.org z.s. byla úspěšně dokončena.', $confirmation->html);
                Assert::same($enabled ? [$member->id] : [], $this->container->getByType(RecordingContractRequest::class)->calls);
                Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
            }
        } finally {
            $conversion->setValue($driver, $previous);
        }
    }

    public function testWewimoHostnameFallbackKeepsZeroTextAndOwnerLinks(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['nick' => 'FixtureNick']);
        $memberIp = $this->fixtures->ipAddress($member->id, '192.0.2.42');
        $apIp = $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.43']);
        $model = $this->container->getByType(Wewimo::class);
        foreach ([[null, '(FixtureNick)'], ['', '(FixtureNick)'], ['0', '(FixtureNick)'], ['00', '00'], ['router', 'router']] as [$hostname, $expected]) {
            $memberIp->update(['hostname' => $hostname]);
            $station = [];
            $model->prepareIpInfoAndLink($memberIp, $station);
            Assert::same($expected, $station['xx-last-ip-host']);
            Assert::same(['presenter' => 'Uzivatel:show', 'id' => $member->id, 'anchor' => '#ip192.0.2.42'], $station['xx-last-ip-link']);
            $apIp->update(['hostname' => $hostname]);
            $station = [];
            $model->prepareIpInfoAndLink($apIp, $station);
            Assert::same($hostname, $station['xx-last-ip-host']);
            Assert::same(['presenter' => 'Ap:show', 'id' => $ap->id, 'anchor' => '#ip192.0.2.43'], $station['xx-last-ip-link']);
        }
    }

    public function testEmptyAndZeroBalancesKeepNumericFallback(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        foreach ([[[], 0, 0], [[0], 0, 0], [[12.5], 12.5, 12.5], [[-7.5], -7.5, 0], [[12.5, -12.5], 0, 12.5]] as [$amounts, $balance, $positive]) {
            $member = $this->fixtures->member($ap->id, ['spolek' => 1, 'druzstvo' => 1]);
            foreach ($amounts as $amount) {
                $this->fixtures->insert('UzivatelskeKonto', [
                    'Uzivatel_id' => $member->id, 'TypPohybuNaUctu_id' => 1,
                    'castka' => $amount, 'spolek' => 1, 'druzstvo' => 1, 'datum' => '2026-01-01',
                ]);
            }
            $result = $this->presenters->ui($actor->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
            if (!$result->response instanceof TextResponse || !($template = $result->response->getSource()) instanceof Template) {
                throw new \LogicException('Expected the member detail template.');
            }
            Assert::same($balance, $template->money_bal_spolek);
            Assert::same($balance, $template->money_bal_druzstvo);
            Assert::same($positive, $template->money_dph);
        }
    }

    public function testDevicePasswordZeroKeepsExistingEmptyPasswordSemantics(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->member($ap->id, ['id' => 1, 'systemovy' => 1]); // API audit uses the system member.
        $member = $this->fixtures->member($ap->id);
        $memberIp = $this->fixtures->ipAddress($member->id, '192.0.2.42');
        $apIp = $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.43']);
        $key = $this->fixtures->apiKey(apId: $ap->id);
        $crypto = $this->container->getByType(CryptoSluzba::class);
        foreach ([$memberIp, $apIp] as $ip) {
            foreach ([[null, ''], ['', ''], ['0', ''], [false, ''], [0, ''], [0.0, ''], [[], ''], ['00', '00'], ['test-only', 'test-only']] as [$input, $expected]) {
                $this->presenters->api('Api:Device', ['apikey' . $key->id, $key->klic], ['action' => 'setCredentials', 'id' => $ip->ip_adresa], 'POST', ['login' => 'fixture', 'heslo' => $input]);
                $stored = $this->database->table('IPAdresa')->get($ip->id);
                Assert::same($expected === '' ? 0 : 1, $stored->heslo_sifrovane);
                Assert::same($expected, $expected === '' ? $stored->heslo : $crypto->decrypt($stored->heslo));
            }
        }
    }

    public function testApiEmptyPresenterRestrictionRemainsUnrestricted(): void {
        foreach ([null, '', '0'] as $restriction) {
            $key = $this->fixtures->apiKey(presenter: $restriction);
            $result = $this->presenters->api('Api:Areas', ['apikey' . $key->id, $key->klic]);
            Assert::same(200, $result->status);
            Assert::same([], $result->json);
        }
    }

    public function testContractBirthDateRemainsOptionalWithoutGeneratingPdf(): void {
        $previous = $GLOBALS['container'] ?? null;
        $GLOBALS['container'] = $this->container;
        try {
            $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
            foreach ([[null, ''], ['1980-02-03', '03.02.1980']] as [$birthDate, $expected]) {
                $member->update(['datum_narozeni' => $birthDate]);
                Assert::same($expected, GeneratorSmlouvy::parametryUcastnickeSmlouvy($member)['datum_narozeni']);
            }
        } finally {
            $GLOBALS['container'] = $previous;
        }
    }
}

(new DataConditionsPresenterTest())->run();
