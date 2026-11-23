<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\Responses\TextResponse;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class SmokepingPresenterTest extends DatabaseTestCase
{
    public function testConfigurationListsWatchedAddressesWithAsciiTitles(): void {
        $area = $this->fixtures->insert('Oblast', ['jmeno' => 'Černá Hůrka', 'datum_zalozeni' => '2000-01-01 00:00:00']);
        // An AP named like its area gets a single title, any other AP the area and its own name.
        $mainAp = $this->fixtures->insert('Ap', ['Oblast_id' => $area->id, 'jmeno' => 'Černá Hůrka', 'aktivni' => 1]);
        $otherAp = $this->fixtures->insert('Ap', ['Oblast_id' => $area->id, 'jmeno' => 'Vysílač Úpice', 'aktivni' => 1]);
        $this->fixtures->ap($area->id);
        $described = $this->apAddress($mainAp, '192.0.2.10', ['popis' => 'Hlavní spoj Řečkova', 'hostname' => 'spoj']);
        $unnamed = $this->apAddress($mainAp, '192.0.2.11');
        $this->apAddress($mainAp, '192.0.2.12', ['popis' => 'Nesledovaná', 'smokeping' => 0]);
        $member = $this->fixtures->member($otherAp->id, ['nick' => 'Žížala']);
        $memberAddress = $this->fixtures->ipAddress($member->id, '192.0.2.20', ['hostname' => 'domaci-router', 'smokeping' => 1]);
        $unnamedMemberAddress = $this->fixtures->ipAddress($member->id, '192.0.2.21', ['smokeping' => 1]);
        $this->fixtures->ipAddress($member->id, '192.0.2.23', ['popis' => 'Nesledovaná', 'smokeping' => 0]);
        $unwatched = $this->fixtures->member($otherAp->id, ['nick' => 'Nesledovaný']);
        $this->fixtures->ipAddress($unwatched->id, '192.0.2.22');
        $key = $this->fixtures->apiKey(presenter: 'Api:Smokeping');

        $result = $this->presenters->api('Api:Smokeping', ['apikey' . $key->id, $key->klic]);

        Assert::same(TextResponse::class, $result->response::class);
        Assert::same(200, $result->status);
        Assert::same([
            '+ userdb',
            'menu = userdb',
            'title = generovano z userdb',
            "++ userdb-cerna-hurka-$mainAp->id",
            'menu = Cerna Hurka',
            'title = Cerna Hurka',
            "+++ userdb-cerna-hurka-$mainAp->id",
            'menu = ---AP---',
            'title = ---AP---',
            "++++ userdb-cerna-hurka-AP-IP-$described->id",
            'menu = Hlavni spoj Reckova',
            'title = Hlavni spoj Reckova',
            'host = 192.0.2.10',
            "++++ userdb-cerna-hurka-AP-IP-$unnamed->id",
            "menu = AP-IP-$unnamed->id",
            "title = AP-IP-$unnamed->id",
            'host = 192.0.2.11',
            "++ userdb-cerna-hurka-$otherAp->id",
            'menu = Cerna Hurka - Vysilac Upice',
            'title = Cerna Hurka - Vysilac Upice',
            "+++ userdb-vysilac-upice-$member->id",
            "menu = $member->id-Zizala",
            "title = $member->id-Zizala",
            "++++ userdb-vysilac-upice-USER-IP-$memberAddress->id",
            'menu = domaci-router',
            'title = domaci-router',
            'host = 192.0.2.20',
            "++++ userdb-vysilac-upice-USER-IP-$unnamedMemberAddress->id",
            "menu = USER-IP-$unnamedMemberAddress->id",
            "title = USER-IP-$unnamedMemberAddress->id",
            'host = 192.0.2.21',
        ], $this->lines($result->html));
    }

    /** @param array<string, mixed> $values */
    private function apAddress(ActiveRow $ap, string $address, array $values = []): ActiveRow {
        return $this->fixtures->insert('IPAdresa', $values + [
            'Ap_id' => $ap->id, 'ip_adresa' => $address,
            'dhcp' => 0, 'mac_filter' => 0, 'internet' => 1, 'smokeping' => 1,
        ]);
    }

    /** @return list<string> */
    private function lines(string $configuration): array {
        $lines = array_map('trim', explode("\n", $configuration));
        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }
}

(new SmokepingPresenterTest())->run();
