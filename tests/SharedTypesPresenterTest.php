<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class SharedTypesPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($area->id);
        $this->actor = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->actor->id, 'SO', $area->id);
    }

    public function testAjaxLookupAndEmptyPing(): void {
        foreach ([[], ['ip' => 'invalid']] as $parameters) {
            $result = $this->presenters->ui($this->actor->id, 'AjaxApi', ['action' => 'getIpDetails', 'reqid' => 'lookup'] + $parameters);
            Assert::same(['error' => 'Neplatná IP adresa!', 'reqid' => 'lookup'], $result->json);
        }
        $this->fixtures->subnet($this->ap->id, '192.0.2.0/24', '192.0.2.1');
        $result = $this->presenters->ui($this->actor->id, 'AjaxApi', ['action' => 'getIpDetails', 'ip' => '192.0.2.42']);
        Assert::same([
            'subnet' => '192.0.2.0/24', 'network' => '192.0.2.0', 'cidr' => '24',
            'mask' => '255.255.255.0', 'gateway' => '192.0.2.1',
            'subnetLink' => '/userdb/subnet/detail/192.0.2.#ip192.0.2.42',
        ], $result->json);
        // Empty input returns before the external ping service is called.
        Assert::same([], $this->presenters->ui($this->actor->id, 'AjaxApi', ['action' => 'getIpsPing'])->json);
    }

    public function testSearchRedirectsToApOrMemberList(): void {
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $this->ap->id, 'ip_adresa' => '192.0.2.10']);
        foreach (['192.0.2.10', $this->actor->nick] as $search) {
            $result = $this->presenters->ui($this->actor->id, 'Homepage', method: 'POST', post: [
                '_do' => 'searchForm-submit', 'search' => $search, 'send' => 'Vyhledat',
            ]);
            $expected = $search === '192.0.2.10' ? 'ap/show/' . $this->ap->id : 'uzivatel-list/listall?search=' . rawurlencode($search);
            Assert::same(303, $result->status);
            Assert::same('http://localhost/userdb/' . $expected, $result->redirectUrl);
        }
    }

    public function testSearchWithEmojiEndsInEmptyMemberList(): void {
        // #306: neither the AP lookup of the search form nor the member search may end in a database error.
        $result = $this->presenters->ui($this->actor->id, 'Homepage', method: 'POST', post: [
            '_do' => 'searchForm-submit', 'search' => '😀', 'send' => 'Vyhledat',
        ]);
        Assert::same('http://localhost/userdb/uzivatel-list/listall?search=' . rawurlencode('😀'), $result->redirectUrl);

        $page = $this->presenters->ui($this->actor->id, 'UzivatelList', ['action' => 'listall', 'search' => '😀']);
        Assert::same(200, $page->status);
        Assert::contains('Fulltextové vyhledávání: <code>😀</code>', $page->html);
        Assert::contains('class="no-results">Žádné výsledky.</td>', $page->html);
    }

    public function testDownloadKeepsMissingIdsAndAreaPermission(): void {
        foreach ([[], ['id' => '0']] as $parameters) {
            Assert::exception(
                fn () => $this->presenters->ui($this->actor->id, 'File', ['action' => 'downloadWinboxCmd'] + $parameters),
                BadRequestException::class,
                'ID not found',
                404,
            );
        }
        Assert::exception(
            fn () => $this->presenters->ui($this->actor->id, 'File', ['action' => 'downloadWinboxCmd', 'id' => '-1']),
            BadRequestException::class,
            'IP not found',
            404,
        );
        $ip = $this->fixtures->ipAddress($this->actor->id, '192.0.2.42', ['login' => 'fixture-login']);
        $result = $this->presenters->ui($this->actor->id, 'File', ['action' => 'downloadWinboxCmd', 'id' => (string) $ip->id]);
        Assert::type(FileResponse::class, $result->response);
        if (!$result->response instanceof FileResponse) {
            throw new \LogicException('Expected a file response.');
        }
        try {
            Assert::same('winbox.bat', $result->response->getName());
            Assert::same("winbox.exe 192.0.2.42 fixture-login\n", file_get_contents($result->response->getFile()));
        } finally {
            unlink($result->response->getFile());
        }
        $otherArea = $this->fixtures->area();
        $other = $this->fixtures->member($this->fixtures->ap($otherArea->id)->id);
        $this->fixtures->role($other->id, 'SO', $otherArea->id);
        Assert::exception(
            fn () => $this->presenters->ui($other->id, 'File', ['action' => 'downloadWinboxCmd', 'id' => (string) $ip->id]),
            BadRequestException::class,
            'IP not found',
            404,
        );
    }

    public function testMemberRedirectAcceptsMissingId(): void {
        foreach ([[], ['id' => (string) $this->actor->id]] as $parameters) {
            $result = $this->presenters->ui($this->actor->id, 'Sprava', ['action' => 'show'] + $parameters);
            Assert::same('http://localhost/userdb/uzivatel/show' . (isset($parameters['id']) ? '/' . $parameters['id'] : ''), $result->redirectUrl);
        }
    }

    public function testMapGroupsVisibleMembersAndHidesOtherAreas(): void {
        $first = $this->fixtures->member($this->ap->id, ['druzstvo' => 1, 'spolek' => 0, 'location_status' => 'valid', 'latitude' => 50.2, 'longitude' => 15.8]);
        $second = $this->fixtures->member($this->ap->id, ['druzstvo' => 1, 'spolek' => 0, 'location_status' => 'approx', 'latitude' => 50.2, 'longitude' => 15.8]);
        $otherArea = $this->fixtures->area();
        $hidden = $this->fixtures->member($this->fixtures->ap($otherArea->id)->id, ['druzstvo' => 1, 'spolek' => 0, 'location_status' => 'valid', 'latitude' => 50.3, 'longitude' => 15.9]);
        $result = $this->presenters->ui($this->actor->id, 'Sprava', ['action' => 'mapa']);
        Assert::true(preg_match('~var pointsData = (.*);~', $result->html, $matches) === 1);
        $points = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        Assert::count(1, $points);
        Assert::same(1, $points[0]['ax']);
        $ids = array_column($points[0]['us'], 'id');
        sort($ids);
        $expected = [$first->id, $second->id];
        sort($expected);
        Assert::same($expected, $ids);
        Assert::notContains($hidden->nick, $result->html);
    }

    public function testAdministrativeApFormEditsExistingRow(): void {
        $this->fixtures->role($this->actor->id, 'VV');
        $name = $this->fixtures->name('EditedAP');
        $result = $this->presenters->ui($this->actor->id, 'SpravaOblasti', ['action' => 'noveap', 'id' => (string) $this->ap->id], 'POST', [
            '_do' => 'noveapForm-submit', 'id' => (string) $this->ap->id, 'Oblast_id' => (string) $this->ap->Oblast_id,
            'jmeno' => $name, 'poznamka' => 'Nová poznámka', 'send' => 'Vytvořit',
        ]);
        PermissionScenario::assertRedirect($result, 'sprava/nastroje/' . $this->ap->id);
        $stored = $this->database->table('Ap')->get($this->ap->id);
        Assert::true($stored !== null);
        Assert::same($name, $stored->jmeno);
        Assert::same('Nová poznámka', $stored->poznamka);
        Assert::same(1, $this->database->table('Ap')->count('*'));
    }

    public function testLabelFormCreatesAndEditsAndMissingActionIdStaysJson(): void {
        $this->fixtures->role($this->actor->id, 'VV');
        $name = $this->fixtures->name('Label');
        $post = ['_do' => 'editStitekForm-submit', 'text' => $name, 'barva_pozadi' => '#123456', 'barva_popredi' => '#ffffff', 'poznamka' => 'Testovací poznámka', 'send' => 'Vytvořit'];
        $created = $this->presenters->ui($this->actor->id, 'SpravaStitku', ['action' => 'edit'], 'POST', $post);
        PermissionScenario::assertRedirect($created, 'sprava-stitku/');
        $label = $this->database->table('Stitek')->where('text', $name)->fetch();
        Assert::true($label !== null);
        Assert::same('Testovací poznámka', $label->poznamka);
        $post['text'] = $name . '-edit';
        $edited = $this->presenters->ui($this->actor->id, 'SpravaStitku', ['action' => 'edit', 'id' => (string) $label->id], 'POST', $post);
        Assert::same(303, $edited->status);
        Assert::same($post['text'], $this->requireRow($this->database->table('Stitek')->get($label->id))->text);
        $missing = $this->presenters->ui($this->actor->id, 'SpravaStitku', ['action' => 'saveLabel'], 'POST');
        Assert::same(['success' => false, 'message' => 'Štítek nebyl nalezen.'], json_decode($missing->html, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testMissingContractRedirectsBeforeItsActions(): void {
        $this->fixtures->role($this->actor->id, 'VV');
        foreach (['show', 'handleDownload', 'cancelContract', 'updateNote'] as $action) {
            foreach ([[], ['id' => '-1']] as $parameters) {
                $result = $this->presenters->ui($this->actor->id, 'SpravaSmluv', ['action' => $action] + $parameters, 'POST');
                $expected = isset($parameters['id']) ? 'http://localhost/userdb/homepage/default/-1' : 'http://localhost/userdb/';
                Assert::same($expected, $result->redirectUrl === null ? null : explode('?', $result->redirectUrl)[0]);
                Assert::same(['Nezname ID smlouvy.'], $result->flashes);
            }
        }
    }
}

(new SharedTypesPresenterTest())->run();
