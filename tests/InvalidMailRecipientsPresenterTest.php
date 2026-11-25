<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class InvalidMailRecipientsPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;
    private ActiveRow $area;

    protected function setUp(): void {
        parent::setUp();
        $this->area = $this->fixtures->area();
        $ap = $this->fixtures->ap($this->area->id);
        $this->actor = $this->fixtures->member($ap->id, ['email' => 'sender@example.invalid']);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id, ['email' => 'member@example.invalid', 'spolek' => 1]);
    }

    /** @return list<array{int, bool}> */
    public function registrationCases(): array {
        return [[0, true], [1, true], [0, false], [1, false]];
    }

    /** @dataProvider registrationCases */
    public function testRegistrationWithoutMemberAddress(int $cooperative, bool $editLink): void {
        $this->member->update(['druzstvo' => $cooperative, 'email' => 'member@@example.invalid']);
        $actor = $this->actor;
        if ($editLink === false) {
            $actor = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
            $this->fixtures->role($actor->id, 'DRUŽSTEVNÍK');
        }
        $result = $this->presenters->ui($actor->id, 'UzivatelActions', ['action' => 'sendRegActivation', 'id' => $this->member->id]);
        $this->assertRejected($result, $editLink);
    }

    /** @return list<array{int, string}> */
    public function skippedRegistrationCases(): array {
        return [[0, 'area'], [1, 'area'], [0, 'sender'], [1, 'sender'], [0, 'all'], [1, 'all']];
    }

    /** @dataProvider skippedRegistrationCases */
    public function testRegistrationReportsSkippedCopies(int $cooperative, string $invalid): void {
        $this->member->update(['druzstvo' => $cooperative]);
        $bad = $invalid === 'area' ? $this->fixtures->member($this->member->Ap_id, ['email' => 'manager@@example.invalid', 'jmeno' => 'Žofie', 'prijmeni' => 'Správcová']) : $this->actor;
        if ($invalid !== 'area') {
            $bad->update(['email' => 'sender@@example.invalid', 'email2' => 'second@@example.invalid']);
        }
        $this->fixtures->role($bad->id, 'SO', $this->area->id);
        $this->fixtures->role($bad->id, 'ZSO', $this->area->id);
        if ($invalid === 'sender') {
            $good = $this->fixtures->member($this->member->Ap_id, ['email' => 'manager@example.invalid']);
            $this->fixtures->role($good->id, 'ZSO', $this->area->id);
        }
        $result = $this->send('UzivatelActions', 'sendRegActivation');
        $this->assertRedirect($result);
        Assert::count($invalid === 'all' ? 1 : 2, $this->messages());
        Assert::same(['member@example.invalid' => null], $this->messages()[0]->getHeader('To'));
        Assert::contains('byl odeslán.', $result->flashes[0]);
        Assert::same(['info', 'warning'], $result->flashTypes);
        $warning = $result->flashes[1];
        $recipient = $invalid === 'area' ? 'Žofie Správcová (UID ' . $bad->id . ')' : 'vaše vlastní, oba e-maily';
        Assert::same(
            ($invalid === 'all' ? 'Kopie e-mailu nebyla odeslána, žádný ze správců nemá platnou e-mailovou adresu. ' : '')
                . 'Na neplatné e-mailové adresy zpráva neodešla: ' . $recipient
                . '. Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            $warning,
        );
        Assert::notContains('@@', $warning);
        if ($invalid === 'all') {
            Assert::contains('Kopie e-mailu nebyla odeslána, žádný ze správců nemá platnou e-mailovou adresu.', $warning);
        } else {
            Assert::same([$invalid === 'area' ? 'sender@example.invalid' : 'manager@example.invalid' => null], $this->messages()[1]->getHeader('To'));
        }
    }

    /** @return list<array{string, string, ?string}> */
    public function sendingActions(): array {
        return [
            ['UzivatelActions', 'exportAndSendRegForm', null],
            ['Mailing', 'sendUserEmail', 'ZpoplatneniTelevize2026'],
            ['PaymentSchedule', 'sendPaymentScheduleEmail', null],
        ];
    }

    /** @dataProvider sendingActions */
    public function testActionsWithoutMemberAddress(string $presenter, string $action, ?string $variant): void {
        $this->member->update(['druzstvo' => 1, 'email' => 'member@@example.invalid']);
        $result = $this->send($presenter, $action, $variant);
        $this->assertRejected($result, true);
        Assert::same(0, $this->database->table('StitekUzivatele')->where('Uzivatel_id', $this->member->id)->count('*'));
    }

    /** @dataProvider sendingActions */
    public function testActionsReportActualRecipients(string $presenter, string $action, ?string $variant): void {
        $pdf = $action === 'exportAndSendRegForm';
        $this->member->update(['druzstvo' => 1, 'email' => $pdf ? 'member@example.invalid' : 'member@@example.invalid', 'email2' => 'second@example.invalid']);
        if ($pdf) {
            $this->actor->update(['email' => 'sender@@example.invalid']);
        }
        $result = $this->send($presenter, $action, $variant);
        $this->assertRedirect($result);
        Assert::count(1, $this->messages());
        Assert::same([$pdf ? 'member@example.invalid' : 'second@example.invalid' => null], $this->messages()[0]->getHeader('To'));
        Assert::same(['info', 'warning'], $result->flashTypes);
        Assert::notContains('@@', implode(' ', $result->flashes));
        if ($pdf === false) {
            Assert::contains('odeslán na second@example.invalid.', $result->flashes[0]);
        }
        if ($presenter === 'Mailing') {
            $labels = $this->database->table('StitekUzivatele')->where('Uzivatel_id', $this->member->id)->fetchAll();
            Assert::count(1, $labels);
            Assert::same('Odesláno:' . $variant, array_values($labels)[0]->Stitek->text);
        }
    }

    /** @return list<array{int, int, bool}> */
    public function newMemberCases(): array {
        return [[0, 3, false], [1, -1, false], [0, 0, false], [0, 0, true]];
    }

    /** @dataProvider newMemberCases */
    public function testSavingMemberWithInvalidManager(int $cooperative, int $membership, bool $allInvalid): void {
        $bad = $this->fixtures->member($this->member->Ap_id, ['jmeno' => 'Žofie', 'prijmeni' => 'Správcová', 'email' => 'manager@@example.invalid']);
        $this->fixtures->role($bad->id, 'SO', $this->area->id);
        if ($allInvalid) {
            $this->actor->update(['email' => 'sender@@example.invalid']);
        }
        $m = $this->member;
        $nick = $this->fixtures->name('New');
        $result = $this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'edit'], 'POST', [
            '_do' => 'uzivatelForm-submit', 'save' => 'Uložit', 'id' => '', 'Ap_id' => (string) $m->Ap_id,
            'TypPravniFormyUzivatele_id' => (string) $m->TypPravniFormyUzivatele_id,
            'firma_nazev' => '', 'firma_ico' => '', 'jmeno' => 'Nový', 'prijmeni' => 'Člen',
            'datum_narozeni' => '1985-06-15', 'nick' => $nick, 'email' => 'new@example.invalid', 'email2' => '', 'telefon' => $m->telefon,
            'cislo_clenske_karty' => '', 'kauce_mobil' => '0', 'ulice_cp' => $m->ulice_cp, 'mesto' => $m->mesto,
            'psc' => (string) $m->psc, 'druzstvo' => (string) $cooperative, 'smazano' => '0',
            'spolek' => (string) (1 - $cooperative), 'TypClenstvi_id' => (string) $membership,
            'poznamka' => '', 'gpg' => '', 'TechnologiePripojeni_id' => (string) $m->TechnologiePripojeni_id,
            'ZpusobPripojeni_id' => (string) $m->ZpusobPripojeni_id, 'ipsubnet' => '', 'iprange' => '', 'ip' => [],
        ]);
        $created = $this->requireRow($this->database->table('Uzivatel')->where('nick', $nick)->fetch());
        Assert::same(303, $result->status);
        Assert::contains('/uzivatel/show/' . $created->id, (string) $result->redirectUrl);
        Assert::same('new@example.invalid', $created->email);
        Assert::count($allInvalid ? 0 : ($membership === 0 ? 1 : 2), $this->messages());
        $warnings = array_values(array_filter($result->flashes, fn (string $message, int $key) => $result->flashTypes[$key] === 'warning', ARRAY_FILTER_USE_BOTH));
        Assert::count(1, $warnings);
        Assert::same(
            ($allInvalid ? 'Notifikace správcům nebyla odeslána, žádný ze správců nemá platnou e-mailovou adresu. ' : '')
                . 'Na neplatné e-mailové adresy zpráva neodešla: ' . ($allInvalid ? 'vaše vlastní; ' : '') . 'Žofie Správcová (UID ' . $bad->id
                . '). Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            $warnings[0],
        );
        Assert::notContains('@@', $warnings[0]);
        if ($allInvalid) {
            Assert::contains('Notifikace správcům nebyla odeslána, žádný ze správců nemá platnou e-mailovou adresu.', $warnings[0]);
            Assert::notContains('E-mail s notifikací správcům byl odeslán.', $result->flashes);
        } elseif ($membership === 0) {
            Assert::contains('E-mail s notifikací správcům byl odeslán.', $result->flashes);
        }
    }

    /** @return list<array{string}> */
    public function registrationMethods(): array {
        return [['sendSpolekRegistrationEmail'], ['sendDruzstvoRegistrationEmail']];
    }

    /** @dataProvider registrationMethods */
    public function testRegistrationMethodsRenderDangerLink(string $method): void {
        $this->member->update(['email' => 'member@@example.invalid']);
        $runner = new PresenterRunner($this->database, $this->container, preparePresenter: function (\Nette\Application\UI\Presenter $presenter) use ($method): void {
            $presenter->onRender[] = function (\Nette\Application\UI\Presenter $presenter) use ($method): void {
                Assert::true($presenter instanceof \App\Presenters\UzivatelPresenter);
                if ($method === 'sendSpolekRegistrationEmail') {
                    $presenter->sendSpolekRegistrationEmail($this->member->id);
                } else {
                    $presenter->sendDruzstvoRegistrationEmail($this->member->id);
                }
            };
        });
        $result = $runner->ui($this->actor->id, 'Uzivatel', ['action' => 'show', 'id' => $this->member->id]);
        Assert::same([], $this->messages());
        Assert::same(['danger'], $result->flashTypes);
        Assert::true(preg_match('~<div class="h4 alert alert-danger">(.*?)</div>~s', $result->html, $flash) === 1);
        $dom = DomQuery::fromHtml($flash[0]);
        $links = $dom->find('.alert-danger a');
        Assert::count(1, $links);
        Assert::same('editaci účastníka', (string) $links[0]);
        Assert::contains('/uzivatel/edit/' . $this->member->id, (string) $links[0]['href']);
    }

    /** @return list<array{int, bool}> */
    public function secondaryRegistrationCases(): array {
        return [[0, false], [1, false], [0, true], [1, true]];
    }

    /** @dataProvider secondaryRegistrationCases */
    public function testRegistrationReportsWhichAddressWasSkipped(int $cooperative, bool $invalidSecond): void {
        $this->member->update([
            'druzstvo' => $cooperative,
            'email' => $invalidSecond ? 'member@example.invalid' : 'member@@example.invalid',
            'email2' => $invalidSecond ? 'second@@example.invalid' : 'second@example.invalid',
        ]);
        $result = $this->send('UzivatelActions', 'sendRegActivation');
        $this->assertRedirect($result);
        Assert::count(2, $this->messages());
        Assert::same([$invalidSecond ? 'member@example.invalid' : 'second@example.invalid' => null], $this->messages()[0]->getHeader('To'));
        Assert::same(['info', 'warning'], $result->flashTypes);
        Assert::same(
            'Na neplatné e-mailové adresy zpráva neodešla: ' . $this->member->jmeno . ' ' . $this->member->prijmeni . ' (UID ' . $this->member->id . ')'
                . ($invalidSecond ? ', sekundární e-mail' : '')
                . '. Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            $result->flashes[1],
        );
        Assert::notContains('@@', $result->flashes[1]);
    }

    public function testWarningsKeepFirstRecipientOrderAcrossRegistrationAndCopy(): void {
        $this->member = $this->actor;
        $this->member->update(['spolek' => 1, 'email2' => 'second@@example.invalid']);
        $bad = $this->fixtures->member($this->member->Ap_id, ['jmeno' => 'Žofie', 'prijmeni' => 'Správcová', 'email' => 'manager@@example.invalid']);
        $this->fixtures->role($bad->id, 'SO', $this->area->id);
        $result = $this->send('UzivatelActions', 'sendRegActivation');
        Assert::count(2, $this->messages());
        Assert::same(['info', 'warning'], $result->flashTypes);
        Assert::same(
            'Na neplatné e-mailové adresy zpráva neodešla: vaše vlastní, sekundární e-mail; Žofie Správcová (UID ' . $bad->id
                . '). Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            $result->flashes[1],
        );
    }

    public function testWarningsShowOnlyUidsWhenMemberNamesAreHidden(): void {
        $this->member->update(['email2' => 'second@@example.invalid']);
        $bad = $this->fixtures->member($this->member->Ap_id, ['jmeno' => 'Žofie', 'prijmeni' => 'Správcová', 'email' => 'manager@@example.invalid']);
        $this->fixtures->role($bad->id, 'SO', $this->area->id);
        $this->actor = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($this->actor->id, 'DRUŽSTEVNÍK');
        $result = $this->send('UzivatelActions', 'sendRegActivation');
        Assert::count(2, $this->messages());
        Assert::same(['info', 'warning'], $result->flashTypes);
        Assert::same(
            'Na neplatné e-mailové adresy zpráva neodešla: UID ' . $this->member->id . ', sekundární e-mail; UID ' . $bad->id
                . '. Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            $result->flashes[1],
        );
    }

    /** @return list<array{string, string, string}> */
    public function incompleteNames(): array {
        return [['Jan', '', 'Jan '], ['', 'Novák', 'Novák '], ['', '', ''], ['Jan', "Novák \t", 'Jan Novák ']];
    }

    /** @dataProvider incompleteNames */
    public function testWarningHasNoStrayWhitespaceAroundIncompleteName(string $firstName, string $surname, string $expected): void {
        $bad = $this->fixtures->member($this->member->Ap_id, ['jmeno' => $firstName, 'prijmeni' => $surname, 'email' => 'manager@@example.invalid']);
        $this->fixtures->role($bad->id, 'SO', $this->area->id);
        $result = $this->send('UzivatelActions', 'sendRegActivation');
        Assert::same(['info', 'warning'], $result->flashTypes);
        Assert::same(
            'Na neplatné e-mailové adresy zpráva neodešla: ' . $expected . '(UID ' . $bad->id
                . '). Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            $result->flashes[1],
        );
    }

    public function testSkippedManagerNameIsEscapedInRenderedWarning(): void {
        $bad = $this->fixtures->member($this->member->Ap_id, ['jmeno' => '<b>Žofie</b>', 'prijmeni' => 'Správcová', 'email' => 'manager@@example.invalid']);
        $this->fixtures->role($bad->id, 'SO', $this->area->id);
        $runner = new PresenterRunner($this->database, $this->container, preparePresenter: function (\Nette\Application\UI\Presenter $presenter): void {
            $presenter->onRender[] = function (\Nette\Application\UI\Presenter $presenter): void {
                Assert::true($presenter instanceof \App\Presenters\UzivatelPresenter);
                $presenter->sendSpolekRegistrationEmail($this->member->id);
            };
        });
        $result = $runner->ui($this->actor->id, 'Uzivatel', ['action' => 'show', 'id' => $this->member->id]);
        Assert::same(['info', 'warning'], $result->flashTypes);
        Assert::true(preg_match('~<div class="h4 alert alert-warning">(.*?)</div>~s', $result->html, $flash) === 1);
        Assert::same(
            'Na neplatné e-mailové adresy zpráva neodešla: &lt;b&gt;Žofie&lt;/b&gt; Správcová (UID ' . $bad->id
                . '). Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.',
            trim($flash[1]),
        );
    }

    private function send(string $presenter, string $action, ?string $variant = null): PresenterResult {
        return $this->presenters->ui($this->actor->id, $presenter, ['action' => $action, 'id' => $this->member->id, 'variant' => $variant]);
    }

    private function assertRejected(PresenterResult $result, bool $editLink): void {
        $this->assertRedirect($result);
        Assert::same([], $this->messages());
        Assert::same(['danger'], $result->flashTypes);
        Assert::count(1, $result->flashes);
        Assert::same('E-mail nebyl odeslán, účastník nemá platnou e-mailovou adresu. Zadejte ji v editaci účastníka.', strip_tags($result->flashes[0]));
        $links = DomQuery::fromHtml('<div>' . $result->flashes[0] . '</div>')->find('a');
        Assert::count($editLink ? 1 : 0, $links);
        if ($editLink) {
            Assert::same('editaci účastníka', (string) $links[0]);
            Assert::contains('/uzivatel/edit/' . $this->member->id, (string) $links[0]['href']);
        }
    }

    private function assertRedirect(PresenterResult $result): void {
        Assert::same(302, $result->status);
        Assert::contains('/uzivatel/show/' . $this->member->id, (string) $result->redirectUrl);
    }

    /** @return list<\Nette\Mail\Message> */
    private function messages(): array {
        return $this->container->getByType(RecordingMailer::class)->messages;
    }
}

(new InvalidMailRecipientsPresenterTest())->run();
