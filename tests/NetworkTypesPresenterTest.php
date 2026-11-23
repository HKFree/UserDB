<?php

declare(strict_types=1);

namespace Test;

use App\Presenters\StatusPresenter;
use App\Presenters\VnejsiScannerPresenter;
use App\Presenters\WewimoPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Application\Responses\TextResponse;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class NetworkTypesPresenterTest extends DatabaseTestCase
{
    private ActiveRow $area;
    private ActiveRow $ap;
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($this->area->id);
        $this->actor = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    public function testSubnetOverviewKeepsBlockSizes(): void {
        foreach (['192.0.2.0/25', '192.0.2.128/26', '192.0.2.200/32'] as $subnet) {
            $this->fixtures->subnet($this->ap->id, $subnet, '192.0.2.1');
        }
        $result = $this->presenters->ui($this->actor->id, 'Subnet', ['action' => 'overview', 'id' => '192.0.2']);
        Assert::same(200, $result->status);
        if (($result->response instanceof TextResponse) === false || (($template = $result->response->getSource()) instanceof Template) === false) {
            throw new \LogicException('Expected the subnet overview template.');
        }
        Assert::equal([0 => 128, 128 => 64, 200 => 1], $template->networks);
        Assert::same('192.0.2.', $template->prefix);
    }

    public function testSubnetDetailKeepsPrefixesAndOccupiedAndFreeRows(): void {
        $missing = $this->presenters->ui($this->actor->id, 'Subnet', ['action' => 'overview']);
        PermissionScenario::assertRedirect($missing, '', 302);
        Assert::same(['Nebyl vybrán subnet.'], $missing->flashes);
        $this->fixtures->subnet($this->ap->id, '192.0.2.0/29', '192.0.2.1');
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $this->ap->id, 'ip_adresa' => '192.0.2.1']);
        $this->fixtures->ipAddress($this->actor->id, '192.0.2.2');
        foreach (['192.0.2', '192.0.2.'] as $prefix) {
            $result = $this->presenters->ui($this->actor->id, 'Subnet', ['action' => 'detail', 'id' => $prefix]);
            Assert::same(200, $result->status);
            $document = DomQuery::fromHtml($result->html);
            Assert::count(256, $document->find('tr[id^="highlightable-ip192.0.2."]'));
            Assert::count(1, $document->find('tr.subnet-ip-network-row'));
            Assert::count(1, $document->find('tr.subnet-ip-broadcast-row'));
            Assert::contains('192.0.2.1 (GW)', $result->html);
            Assert::contains('/ap/show/' . $this->ap->id . '#ip192.0.2.1', $result->html);
            Assert::contains('/uzivatel/show/' . $this->actor->id . '#ip192.0.2.2', $result->html);
            Assert::contains('<td>192.0.2.3</td>', $result->html);
        }
    }

    public function testWewimoLinksAndAnonymizationMutateNestedData(): void {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('Wewimo');
        if (($presenter instanceof WewimoPresenter) === false) {
            throw new \LogicException('Expected the Wewimo presenter.');
        }
        // Presenter::run normally sets the name before any links are generated.
        $presenter->setParent(null, 'Wewimo');
        $memberLink = ['presenter' => 'Uzivatel:show', 'id' => $this->actor->id, 'anchor' => '#ip192.0.2.2'];
        $apLink = ['presenter' => 'Ap:show', 'id' => $this->ap->id, 'anchor' => '#ip192.0.2.1'];
        $station = [
            'xx-mac-host' => 'private-host', 'xx-last-ip-host' => 'private-last-host',
            'x-identity' => 'private-identity', 'radio-name' => 'private-radio',
            'mac-address' => '00:00:00:00:00:42', 'x-anonymous-mac-address' => 'xx:xx:xx:00:00:42',
            'xx-mac-link' => $memberLink, 'xx-last-ip-link' => null, 'xx-last-ips' => [2 => ['xx-last-ip-link' => $apLink]],
        ];
        $data = [['interfaces' => ['wlan1' => ['stations' => [
            $station,
            ['xx-user-nick' => 'MemberNick', 'xx-last-ip-user-nick' => 'OtherNick'] + $station,
            ['xx-mac-host' => '', 'xx-last-ip-host' => '', 'x-identity' => '', 'radio-name' => ''] + $station,
        ]]]]];
        $presenter->addWewimoLinks($data);
        $presenter->anonymizeWewimoData($data);
        $stations = $data[0]['interfaces']['wlan1']['stations'];
        Assert::same('***', $stations[0]['xx-mac-host']);
        Assert::same('***', $stations[0]['xx-last-ip-host']);
        Assert::same('***', $stations[0]['x-identity']);
        Assert::same('***', $stations[0]['radio-name']);
        Assert::same('(MemberNick)', $stations[1]['xx-mac-host']);
        Assert::same('(OtherNick)', $stations[1]['xx-last-ip-host']);
        foreach (['xx-mac-host', 'xx-last-ip-host', 'x-identity', 'radio-name'] as $key) {
            Assert::same('', $stations[2][$key]);
        }
        foreach ($stations as $result) {
            Assert::same('xx:xx:xx:00:00:42', $result['mac-address']);
            Assert::contains('/uzivatel/show/' . $this->actor->id . '#ip192.0.2.2', $result['xx-mac-link']);
            Assert::same('', $result['xx-last-ip-link']);
            Assert::contains('/ap/show/' . $this->ap->id . '#ip192.0.2.1', $result['xx-last-ips'][2]['xx-last-ip-link']);
        }
        $empty = [];
        $presenter->addWewimoLinks($empty);
        $presenter->anonymizeWewimoData($empty);
        Assert::same([], $empty);
    }

    public function testWewimoOrphanAddressesPassThroughLinksAndAnonymization(): void {
        $this->fixtures->insert('IPAdresa', [
            'Ap_id' => $this->ap->id, 'ip_adresa' => '192.0.2.1', 'wewimo' => 1,
            'login' => 'fixture', 'heslo' => 'fixture-password', 'heslo_sifrovane' => 0,
        ]);
        foreach (['192.0.2.42', '192.0.2.43'] as $ip) {
            $this->fixtures->insert('IPAdresa', [
                'ip_adresa' => $ip, 'hostname' => 'orphan-host', 'w_client_mac' => '00:00:00:00:00:42',
                'mac_adresa' => $ip === '192.0.2.42' ? '00:00:00:00:00:42' : null,
            ]);
        }
        $model = new class ($this->container->getByType(\App\Services\CryptoSluzba::class), $this->container->getByType(\App\Model\IPAdresa::class), $this->container->getByType(\App\Model\AP::class)) extends \App\Model\Wewimo {
            /** @return array{interfaces: array<string, array<string, mixed>>, resources: array<string, mixed>, neighborsByIp: array<string, array<string, mixed>>} */
            public function getWewimoData(mixed $ip, mixed $username, mixed $password, mixed $invoker): array {
                return [
                    'interfaces' => ['wlan1' => ['ssid' => 'fixture', 'stations' => [[
                        'mac-address' => '00:00:00:00:00:42', 'last-ip' => '192.0.2.42', 'x-in-neighbors' => 1,
                        'x-identity' => 'private-identity', 'radio-name' => 'private-radio',
                        'x-anonymous-mac-address' => 'xx:xx:xx:00:00:42',
                    ]]]],
                    'resources' => [], 'neighborsByIp' => [],
                ];
            }
        };
        $devices = $model->getWewimoFullData($this->ap->id, 'Test')['devices'];
        Assert::same('', $devices[0]['error']);
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('Wewimo');
        if (($presenter instanceof WewimoPresenter) === false) {
            throw new \LogicException('Expected the Wewimo presenter.');
        }
        $presenter->setParent(null, 'Wewimo');
        $presenter->addWewimoLinks($devices);
        $presenter->anonymizeWewimoData($devices);
        $station = $devices[0]['interfaces']['wlan1']['stations'][0];
        Assert::same('', $station['xx-mac-link']);
        Assert::same('', $station['xx-last-ip-link']);
        Assert::count(1, $station['xx-last-ips']);
        foreach ($station['xx-last-ips'] as $lastIp) {
            Assert::same('', $lastIp['xx-last-ip-link']);
        }
        Assert::same('***', $station['xx-mac-host']);
        Assert::same('***', $station['xx-last-ip-host']);
        Assert::same('xx:xx:xx:00:00:42', $station['mac-address']);
    }

    public function testScanDetailsUseLocalRowsAndPreserveEmptyScan(): void {
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $this->ap->id, 'ip_adresa' => '192.0.2.1']);
        $this->fixtures->ipAddress($this->actor->id, '192.0.2.2');
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('VnejsiScanner');
        $method = new \ReflectionMethod(VnejsiScannerPresenter::class, 'getDetaily');
        $details = $method->invoke($presenter, ['192.0.2.1' => ['22'], '192.0.2.2' => ['80', '443'], '192.0.2.3' => []]);
        Assert::same(['22'], $details['192.0.2.1']['porty']);
        Assert::same($this->area->jmeno, $details['192.0.2.1']['oblast']);
        Assert::false($details['192.0.2.1']['uzivatel']);
        Assert::same(['80', '443'], $details['192.0.2.2']['porty']);
        Assert::same($this->actor->id, $details['192.0.2.2']['uzivatel']->id);
        Assert::same(['porty' => [], 'oblast' => 'Není v UserDB', 'uzivatel' => false], $details['192.0.2.3']);
        Assert::same([], $method->invoke($presenter, []));
    }

    public function testScannerRendersUnavailableEmptyAndPopulatedStates(): void {
        $scanner = new class ('http://127.0.0.1:0/') extends \App\Model\VnejsiScanner {
            /** @var array<array-key, list<string>>|null */
            public ?array $scan = null;

            public function getScan(): ?array {
                return $this->scan;
            }

            public function getScanDate(): \DateTime {
                return new \DateTime('2026-01-01 12:00:00');
            }
        };
        foreach ($this->container->findByType(\App\Model\VnejsiScanner::class) as $name) {
            $this->container->removeService($name);
            $this->container->addService($name, $scanner);
        }
        foreach ([null, [], ['192.0.2.99' => ['443']]] as $scan) {
            $scanner->scan = $scan;
            $httpResponse = $this->container->getByType(\Nette\Http\Response::class);
            $httpResponse->setCode(200);
            $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('VnejsiScanner');
            if (($presenter instanceof VnejsiScannerPresenter) === false) {
                throw new \LogicException('Expected the scanner presenter.');
            }
            $presenter->setParent(null, 'VnejsiScanner');
            $presenter->setLayout(false);
            $presenter->renderDefault();
            $template = $presenter->getTemplate();
            if (($template instanceof Template) === false) {
                throw new \LogicException('Expected the scanner template.');
            }
            $html = $template->getLatte()->renderToString(
                __DIR__ . '/../app/templates/VnejsiScanner/default.latte',
                $template->getParameters(),
                'content',
            );
            Assert::same($scan === null ? 503 : 200, $httpResponse->getCode());
            if ($scan === null) {
                Assert::contains('Data vnějšího scanneru nejsou dostupná.', $html);
                Assert::notContains('Aktuální data jsou z', $html);
                Assert::notContains('<table', $html);
            } else {
                Assert::contains('Aktuální data jsou z 12:00 1. 1. 2026', $html);
                Assert::contains('<table', $html);
                Assert::notContains('Data vnějšího scanneru nejsou dostupná.', $html);
                if ($scan !== []) {
                    Assert::contains('192.0.2.99', $html);
                    Assert::contains('443', $html);
                }
            }
        }
    }

    public function testStatusIntervalBoundaries(): void {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('Status');
        if (($presenter instanceof StatusPresenter) === false) {
            throw new \LogicException('Expected the status presenter.');
        }
        foreach ([0 => '0 sekund', 59 => '59 sekund', 60 => '1 minut', 3599 => '60 minut', 3600 => '1 hodin', 86400 => '1 dny'] as $seconds => $text) {
            Assert::same($text, $presenter->formatujInterval($seconds));
        }
        Assert::same('1.5 sekund', $presenter->formatujInterval(1.5));
    }
}

(new NetworkTypesPresenterTest())->run();
