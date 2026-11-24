<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class LogTablePresenterTest extends DatabaseTestCase
{
    public function testContractHistoryGroupsInsertAndUpdateAndHidesInternalFields(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($member->id, 'VV');
        foreach ([
            ['Smlouva[123].typ', null, 'ucastnicka', 'I', '2020-01-01 00:00:00'],
            ['Smlouva[123].kdy_vygenerovano', null, '2020-01-01 00:00:00', 'I', '2020-01-01 00:00:00'],
            ['Smlouva[123].poznamka', 'Původní', '<b>Nová</b>', 'U', '2020-01-02 00:00:00'],
            ['Smlouva[123].kdy_ukonceno', null, '2020-01-02 00:00:00', 'U', '2020-01-02 00:00:00'],
            ['sMlOuVa[124].kdy_podepsano', null, '2020-01-03 00:00:00', 'U', '2020-01-03 00:00:00'],
            ['sMlOuVa[124].podepsany_dokument_nazev', null, 'smlouva.pdf', 'U', '2020-01-03 00:00:00'],
            ['Smlouva[125].kdy_odmitnuto', null, '2020-01-03 00:00:00', 'U', '2020-01-03 00:00:00'],
            ['Smlouva[123].Uzivatel_id', null, '123456789', 'I', '2020-01-04 00:00:00'],
            ['Smlouva[123].podepsany_dokument_path', '/old/server/path', '/new/server/path', 'U', '2020-01-04 00:00:00'],
            ['Smlouva[123].podepsany_dokument_content_type', null, 'application/pdf', 'U', '2020-01-04 00:00:00'],
        ] as [$column, $old, $new, $action, $date]) {
            $this->fixtures->insert('Log', [
                'Uzivatel_id' => $member->id, 'tabulka' => 'Uzivatel', 'tabulka_id' => $member->id,
                'sloupec' => $column, 'puvodni_hodnota' => $old, 'nova_hodnota' => $new,
                'datum' => $date, 'ip_adresa' => '127.0.0.1', 'akce' => $action,
            ]);
        }
        $result = $this->presenters->ui($member->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        Assert::same(200, $result->status);
        Assert::same([
            ['Kdy', 'Kdo', 'Co', 'Z', 'Na'],
            ['2020-01-03 00:00:00', $member->nick, 'Smlouva č. 125', 'Změna odmítnuto z ---- na 2020-01-03 00:00:00, '],
            ['2020-01-03 00:00:00', $member->nick, 'Smlouva č. 124', 'Změna podepsaný dokument z ---- na smlouva.pdf, podepsáno z ---- na 2020-01-03 00:00:00, '],
            ['2020-01-02 00:00:00', $member->nick, 'Smlouva č. 123', 'Změna poznámka z Původní na <b>Nová</b>, ukončeno z ---- na 2020-01-02 00:00:00, '],
            ['2020-01-01 00:00:00', $member->nick, 'Smlouva č. 123', 'Založení smlouvy s parametry typ = ucastnicka, vygenerováno = 2020-01-01 00:00:00, '],
        ], $this->logRows($result->html));
        Assert::notContains('<b>Nová</b>', $result->html);
    }

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

    public function testMissingCurrentIpAddressUsesHistory(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($member->id, 'VV');
        $ip = $this->fixtures->insert('IPAdresa', ['ip_adresa' => null]);
        $this->fixtures->insert('Log', [
            'Uzivatel_id' => $member->id, 'tabulka' => 'Uzivatel', 'tabulka_id' => $member->id,
            'sloupec' => 'IPAdresa[' . $ip->id . '].ip_adresa',
            'puvodni_hodnota' => '192.0.2.42', 'nova_hodnota' => null,
            'datum' => '2020-01-01 00:00:00', 'ip_adresa' => '127.0.0.1', 'akce' => 'U',
        ]);

        $result = $this->presenters->ui($member->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        Assert::same([
            ['Kdy', 'Kdo', 'Co', 'Z', 'Na'],
            ['2020-01-01 00:00:00', $member->nick, 'IP 192.0.2.42', 'Změna ip_adresa z 192.0.2.42 na , '],
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
        Assert::true(preg_match('~<table class="table table-striped logstable">.*?</table>~s', $html, $matches) === 1);
        $rows = [];
        foreach (DomQuery::fromHtml($matches[0])->find('tr') as $row) {
            $rows[] = array_map(static fn (DomQuery $cell): string => dom_import_simplexml($cell)->textContent, $row->find('th, td'));
        }
        return $rows;
    }
}

(new LogTablePresenterTest())->run();
