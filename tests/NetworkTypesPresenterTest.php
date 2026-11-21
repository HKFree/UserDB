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
        if (!$result->response instanceof TextResponse || !($template = $result->response->getSource()) instanceof Template) {
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
        if (!$presenter instanceof WewimoPresenter) {
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

    public function testScanDetailsUseLocalRowsAndPreserveMissingScan(): void {
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
        $missing = null;
        Assert::error(function () use ($method, $presenter, &$missing): void {
            $missing = $method->invoke($presenter, null);
        }, E_WARNING, 'foreach() argument must be of type array|object, null given');
        Assert::same([], $missing);
    }

    public function testStatusIntervalBoundaries(): void {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('Status');
        if (!$presenter instanceof StatusPresenter) {
            throw new \LogicException('Expected the status presenter.');
        }
        foreach ([0 => '0 sekund', 59 => '59 sekund', 60 => '1 minut', 3599 => '60 minut', 3600 => '1 hodin', 86400 => '1 dny'] as $seconds => $text) {
            Assert::same($text, $presenter->formatujInterval($seconds));
        }
        Assert::same('1.5 sekund', $presenter->formatujInterval(1.5));
    }
}

(new NetworkTypesPresenterTest())->run();
