<?php

declare(strict_types=1);

namespace Test;

use App\Services\CryptoSluzba;
use Grido\Components\Export;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionAdminToolsPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    /** @return array<string, array{string, string, bool}> */
    public function toolCases(): array {
        $cases = [];
        foreach (['sms', 'encryption', 'areaCreate', 'areaEdit', 'apCreate', 'payment', 'merge', 'honorary'] as $tool) {
            foreach (['cooperative', 'ownSo', 'support', 'tech', 'vv'] as $actor) {
                $cases["$tool/$actor"] = [$tool, $actor, $actor === 'vv' || ($actor === 'tech' && !in_array($tool, ['merge', 'honorary'], true))];
            }
        }
        return $cases;
    }

    /** @dataProvider toolCases */
    public function testToolSubmission(string $tool, string $actor, bool $allowed): void {
        $s = $this->scenario;
        $name = $this->fixtures->name('Changed');
        $ip = $this->fixtures->ipAddress($s->member->id, '10.107.252.250', ['heslo' => 'plain-password', 'heslo_sifrovane' => 0]);
        $payment = $this->fixtures->insert('PrichoziPlatba', [
            'datum' => date('Y-m-d'), 'castka' => 1234, 'kod_cilove_banky' => 2010, 'druzstvo' => 1, 'spolek' => 0,
        ]);
        $cc = $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $s->member->id, 'plati_od' => date('Y-m-d'), 'schvaleno' => 0,
            'TypCestnehoClenstvi_id' => $this->fixtures->lookup('TypCestnehoClenstvi', 'HKFree do škol'),
            'zadost_podal' => $s->actors['ownSo']->id, 'zadost_podana' => date('Y-m-d'),
        ]);
        [$presenter, $action, $signal, $post] = match ($tool) {
            'sms' => ['SpravaSms', 'sms', 'smsForm-submit', ['komu' => '0', 'message' => $name, 'send' => 'Odeslat']],
            'encryption' => ['SpravaSifrovani', 'presifrovani', 'presifrovaniForm-submit', ['send' => 'Přešifrovat']],
            'areaCreate', 'areaEdit' => ['SpravaOblasti', 'novaoblast', 'novaoblastForm-submit', ['id' => $tool === 'areaEdit' ? (string) $s->area->id : '', 'jmeno' => $name, 'send' => 'Vytvořit']],
            'apCreate' => ['SpravaOblasti', 'noveap', 'noveapForm-submit', ['Oblast_id' => (string) $s->area->id, 'jmeno' => $name, 'send' => 'Vytvořit']],
            'payment' => ['SpravaNesparovanych', 'prevod', 'prevodForm-submit', ['Uzivatel_id' => (string) $s->member->id, 'poznamka' => $name, 'send' => 'Převést']],
            'merge' => ['SpravaSlucovani', 'slucovani', 'slucovaniForm-submit', ['Uzivatel_id' => (string) $s->member->id, 'slouceny_uzivatel' => (string) $s->otherMember->id, 'slouceni' => 'Sloučit']],
            'honorary' => ['SpravaCc', 'schvalovanicc', 'spravaCCForm-submit', ['rights' => [$cc->id => [
                'id' => (string) $cc->id, 'Uzivatel_id' => (string) $s->member->id, 'plati_od' => date('Y-m-d'),
                'schvaleno' => $allowed ? '0' : '1', 'poznamka' => $name, 'zadost_podal' => (string) $s->actors['ownSo']->id,
            ]], 'save' => 'Uložit']],
            default => throw new \LogicException($tool),
        };
        $parameters = ['action' => $action];
        if ($tool === 'payment') {
            $parameters['id'] = $payment->id;
        }
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, $presenter, $parameters, 'POST', ['_do' => $signal] + $post);
        if (!$allowed) {
            $s->assertDenied($request);
            return;
        }
        $result = $request();
        if ($tool === 'payment') {
            // This redirect also carries the persistent payment id; only the target page is asserted.
            Assert::same(303, $result->status);
            Assert::match('http://localhost/userdb/sprava/nastroje%A?%', $result->redirectUrl);
        } else {
            PermissionScenario::assertRedirect($result, match ($tool) {
                'merge' => 'uzivatel/edit/' . $s->member->id,
                'honorary' => 'sprava-cc/schvalovanicc',
                default => 'sprava/nastroje',
            });
        }
        switch ($tool) {
            case 'sms':
                Assert::same([[['777000000', '777000000'], $name]], $this->container->getByType(RecordingMexSmsSender::class)->calls);
                break;
            case 'encryption':
                $stored = $this->database->table('IPAdresa')->get($ip->id);
                Assert::same(1, $stored->heslo_sifrovane);
                Assert::notSame('plain-password', $stored->heslo);
                Assert::same('plain-password', $this->container->getByType(CryptoSluzba::class)->decrypt($stored->heslo));
                break;
            case 'areaCreate':
            case 'areaEdit':
                $area = $this->database->table('Oblast')->where('jmeno', $name)->fetch();
                Assert::notNull($area);
                if ($tool === 'areaEdit') {
                    Assert::same($s->area->id, $area->id);
                }
                Assert::same($tool === 'areaEdit' ? 2 : 3, $this->database->table('Oblast')->count('*'));
                break;
            case 'apCreate':
                Assert::same(1, $this->database->table('Ap')->where(['jmeno' => $name, 'Oblast_id' => $s->area->id])->count('*'));
                break;
            case 'payment':
                $entries = $this->database->table('UzivatelskeKonto')->where('PrichoziPlatba_id', $payment->id)->order('id')->fetchAll();
                Assert::count(2, $entries);
                $entries = array_values($entries);
                Assert::same(-1234.0, $entries[0]->castka);
                Assert::null($entries[0]->Uzivatel_id);
                Assert::same(1234.0, $entries[1]->castka);
                Assert::same($s->member->id, $entries[1]->Uzivatel_id);
                Assert::same($s->actors[$actor]->id, $entries[1]->zmenu_provedl);
                break;
            case 'merge':
                $merged = $this->database->table('SloucenyUzivatel')->where('slouceny_uzivatel', $s->otherMember->id)->fetch();
                Assert::notNull($merged);
                Assert::same($s->member->id, $merged->Uzivatel_id);
                Assert::same($s->actors[$actor]->id, $merged->sloucil);
                break;
            case 'honorary':
                $stored = $this->database->table('CestneClenstviUzivatele')->get($cc->id);
                Assert::same($name, $stored->poznamka);
                Assert::same(0, $stored->schvaleno);
                break;
        }
    }

    /** @return list<array{string, bool}> */
    public function mergedListCases(): array {
        $cases = [];
        foreach (['cooperative', 'ownSo', 'support', 'tech', 'vv'] as $actor) {
            foreach ([false, true] as $export) {
                $cases[] = [$actor, $export];
            }
        }
        return $cases;
    }

    /** @dataProvider mergedListCases */
    public function testMergedList(string $actor, bool $export): void {
        $s = $this->scenario;
        $this->fixtures->insert('SloucenyUzivatel', [
            'Uzivatel_id' => $s->member->id, 'slouceny_uzivatel' => $s->otherMember->id,
            'datum_slouceni' => '2020-01-01', 'sloucil' => $s->actors['vv']->id,
        ]);
        $parameters = ['action' => 'slouceni'] + ($export ? ['do' => 'slouceniGrid-export-export'] : []);
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, 'SpravaSlucovani', $parameters);
        if ($actor !== 'vv') {
            $s->assertDenied($request);
            return;
        }
        $result = $request();
        if ($export) {
            Assert::type(Export::class, $result->response);
            ob_start();
            try {
                $result->response->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
                $csv = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            Assert::contains($s->member->id . ',' . $s->otherMember->id . ',', $csv);
        } else {
            Assert::same(200, $result->status);
            Assert::contains('Přehled sloučených uživatelů', $result->html);
            Assert::contains((string) $s->otherMember->id, $result->html);
        }
    }
}

(new PermissionAdminToolsPresenterTest())->run();
