<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class LogTablePresenterTest extends DatabaseTestCase
{
    public function testMemberLogKeepsNullsAndHidesPasswords(): void {
        $area = $this->fixtures->area();
        $member = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        $this->fixtures->role($member->id, 'VV');
        foreach ([
            ['Uzivatel.nick', null, 'Nová přezdívka'],
            ['Uzivatel.heslo', 'old-secret', 'new-secret'],
            ['unknown', 'old', 'new'],
        ] as [$column, $old, $new]) {
            $this->fixtures->insert('Log', [
                'Uzivatel_id' => $member->id, 'tabulka' => 'Uzivatel', 'tabulka_id' => $member->id,
                'sloupec' => $column, 'puvodni_hodnota' => $old, 'nova_hodnota' => $new,
                'datum' => '2020-01-01 00:00:00', 'ip_adresa' => '127.0.0.1', 'akce' => 'U',
            ]);
        }
        $result = $this->presenters->ui($member->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        Assert::same(200, $result->status);
        Assert::same([
            ['Kdy', 'Kdo', 'Co', 'Z', 'Na'],
            ['2020-01-01 00:00:00', $member->nick, 'přezdívka', '----', 'Nová přezdívka'],
        ], $this->logRows($result->html));
    }

    public function testLogKeepsEmptyPlaceholderAndDisplaysTextZero(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($member->id, 'VV');
        $this->fixtures->insert('Log', [
            'Uzivatel_id' => $member->id, 'tabulka' => 'Uzivatel', 'tabulka_id' => $member->id,
            'sloupec' => 'Uzivatel.nick', 'puvodni_hodnota' => '', 'nova_hodnota' => '0',
            'datum' => '2020-01-01 00:00:00', 'ip_adresa' => '127.0.0.1', 'akce' => 'U',
        ]);
        $result = $this->presenters->ui($member->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        Assert::same([
            ['Kdy', 'Kdo', 'Co', 'Z', 'Na'],
            ['2020-01-01 00:00:00', $member->nick, 'přezdívka', '----', '0'],
        ], $this->logRows($result->html));
    }

    /** @return list<array{string, string, string, string, string}> */
    public function groupedLogCases(): array {
        return [
            ['IPAdresa', 'ip_adresa', '192.0.2.42', 'IP', 'IP Adresa'],
            ['Subnet', 'subnet', '192.0.2.0/24', 'Subnet', 'Subnet'],
        ];
    }

    /** @dataProvider groupedLogCases */
    public function testLogGroupsTheSameTimestampAndKeepsZeroParameter(string $table, string $column, string $address, string $title, string $deleted): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($member->id, 'VV');
        foreach ([[$column, $address], ['hostname', '0'], ['dhcp', '']] as [$field, $old]) {
            $this->fixtures->insert('Log', [
                'Uzivatel_id' => $member->id, 'tabulka' => 'Uzivatel', 'tabulka_id' => $member->id,
                'sloupec' => $table . '[123456789].' . $field, 'puvodni_hodnota' => $old, 'nova_hodnota' => null,
                'datum' => '2020-01-01 00:00:00', 'ip_adresa' => '127.0.0.1', 'akce' => 'D',
            ]);
        }
        $result = $this->presenters->ui($member->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        $verb = $table === 'IPAdresa' ? 'byla smazána' : 'byl smazán';
        Assert::same([
            ['Kdy', 'Kdo', 'Co', 'Z', 'Na'],
            ['2020-01-01 00:00:00', $member->nick, $title . ' ' . $address,
                $deleted . ' ' . $verb . '. Parametry byly ' . $column . ' = ' . $address . ', hostname = 0, '],
        ], $this->logRows($result->html));
    }

    public function testEmptyMemberLog(): void {
        $area = $this->fixtures->area();
        $member = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        $this->fixtures->role($member->id, 'VV');
        $result = $this->presenters->ui($member->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        Assert::same([['Kdy', 'Kdo', 'Co', 'Z', 'Na']], $this->logRows($result->html));
    }

    /** @return list<list<string>> */
    private function logRows(string $html): array {
        Assert::same(1, preg_match('~<table class="table table-striped logstable">.*?</table>~s', $html, $matches));
        $rows = [];
        foreach (DomQuery::fromHtml($matches[0])->find('tr') as $row) {
            $rows[] = array_map(static fn (DomQuery $cell): string => dom_import_simplexml($cell)->textContent, $row->find('th, td'));
        }
        return $rows;
    }
}

(new LogTablePresenterTest())->run();
