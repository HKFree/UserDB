<?php

declare(strict_types=1);

namespace Test;

use App\Model\HkfIdentity;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Database\Connection;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class SmokePresenterTest extends DatabaseTestCase
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
        $this->fixtures->role($this->actor->id, 'SO', $this->area->id);
    }

    public function testHomepageAndMemberDetail(): void {
        Assert::same($this->database, $this->container->getByType(Explorer::class));
        Assert::same($this->database->getConnection(), $this->container->getByType(Connection::class));
        $home = $this->presenters->ui($this->actor->id, 'Homepage');
        Assert::same(TextResponse::class, $home->response::class);
        Assert::same(200, $home->status);
        Assert::contains($this->ap->jmeno, $home->html);
        Assert::contains('Odhlášení', $home->html);
        Assert::notContains('demo-osoba', $home->html);

        $member = $this->fixtures->member($this->ap->id);
        $detail = $this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
        Assert::same(TextResponse::class, $detail->response::class);
        Assert::same(200, $detail->status);
        Assert::contains('UID ' . $member->id, $detail->html);
        Assert::contains($member->jmeno . ' ' . $member->prijmeni, $detail->html);
        Assert::contains($member->email, $detail->html);
    }

    /** @return list<array{string, string}> */
    public function pageCases(): array {
        return [
            ['Ap', 'show'],
            ['UzivatelList', 'list'],
            ['Sprava', 'nastroje'],
            ['Uzivatel', 'edit'],
        ];
    }

    /** @dataProvider pageCases */
    public function testCorePage(string $presenter, string $action): void {
        $member = $this->fixtures->member($this->ap->id);
        $id = match ($presenter) {
            'Ap', 'UzivatelList' => $this->ap->id,
            'Uzivatel' => $member->id,
            default => null,
        };
        $expected = match ($presenter) {
            'Ap' => [$this->ap->jmeno, $this->area->jmeno, 'Zobrazit uživatele'],
            'UzivatelList' => [$this->ap->jmeno, $member->nick, $member->email],
            'Sprava' => [$this->actor->nick, 'Nástroje pro správu', 'Schvalování čestného členství'],
            // Nette writes @ in attribute values as a character reference.
            'Uzivatel' => ['id="frm-uzivatelForm"', 'value="' . $member->jmeno . '"', 'value="' . str_replace('@', '&#64;', $member->email) . '"'],
            default => throw new \InvalidArgumentException("Unknown page: $presenter"),
        };
        $result = $this->presenters->ui($this->actor->id, $presenter, ['action' => $action, 'id' => $id]);
        Assert::same(TextResponse::class, $result->response::class);
        Assert::same(200, $result->status);
        foreach ($expected as $text) {
            Assert::contains($text, $result->html);
        }
    }

    public function testVvOnlyTool(): void {
        $administrator = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($administrator->id, 'SO', $this->area->id);
        $parameters = ['action' => 'slouceni'];
        Assert::exception(
            fn () => $this->presenters->ui($administrator->id, 'SpravaSlucovani', $parameters),
            BadRequestException::class,
            'Nemáte oprávnění.',
            403,
        );

        $allowed = $this->presenters->ui($this->actor->id, 'SpravaSlucovani', $parameters);
        Assert::same(TextResponse::class, $allowed->response::class);
        Assert::same(200, $allowed->status);
        Assert::contains('Přehled sloučených uživatelů', $allowed->html);
        Assert::contains($this->actor->nick, $allowed->html);
    }

    public function testMemberWithoutRoleIsRejected(): void {
        $member = $this->fixtures->member($this->ap->id);
        $server = $_SERVER;
        Assert::exception(
            fn () => $this->presenters->ui($member->id, 'Homepage'),
            AuthenticationException::class,
            'User not allowed.',
        );
        Assert::same($server, $_SERVER);
    }

    public function testSearchFormRequiresSameSiteCookie(): void {
        $term = $this->actor->nick;
        $post = ['_do' => 'searchForm-submit', 'search' => $term, 'send' => 'Vyhledat'];
        $result = $this->presenters->ui($this->actor->id, 'Homepage', method: 'POST', post: $post);
        Assert::same(RedirectResponse::class, $result->response::class);
        Assert::same(303, $result->status);
        Assert::same('http://localhost/userdb/uzivatel-list/listall?search=' . rawurlencode($term), $result->redirectUrl);

        $blocked = $this->presenters->ui($this->actor->id, 'Homepage', method: 'POST', post: $post, sameSite: false);
        Assert::same(RedirectResponse::class, $blocked->response::class);
        Assert::same(303, $blocked->status);
        Assert::same('http://localhost/userdb/', $blocked->redirectUrl);
        Assert::notSame($result->redirectUrl, $blocked->redirectUrl);
    }

    public function testMemberEmailFormRecordsMessageAndChecksAreaPermission(): void {
        $member = $this->fixtures->member($this->ap->id);
        $parameters = ['action' => 'email', 'id' => $member->id];
        $post = [
            '_do' => 'emailForm-submit',
            'id' => (string) $member->id,
            'from' => '0',
            'email' => $member->email,
            'subject' => 'Fixture subject',
            'message' => 'Fixture message body',
            'send' => 'Odeslat',
        ];
        $mailer = $this->container->getByType(RecordingMailer::class);
        $result = $this->presenters->ui($this->actor->id, 'UzivatelMailSms', $parameters, 'POST', $post);
        Assert::same(RedirectResponse::class, $result->response::class);
        Assert::same(303, $result->status);
        Assert::true($result->redirectUrl !== null);
        Assert::match('http://localhost/userdb/uzivatel/show/' . $member->id . '?_fid=%a%', $result->redirectUrl);
        Assert::count(1, $mailer->messages);
        Assert::same([$post['email'] => null], $mailer->messages[0]->getHeader('To'));
        Assert::same($post['subject'], $mailer->messages[0]->getSubject());
        Assert::same($post['message'], $mailer->messages[0]->getBody());
        Assert::same(['E-mail byl odeslán.'], $result->flashes);

        $otherArea = $this->fixtures->area();
        $otherAp = $this->fixtures->ap($otherArea->id);
        $administrator = $this->fixtures->member($otherAp->id);
        $this->fixtures->role($administrator->id, 'SO', $otherArea->id);
        Assert::exception(
            fn () => $this->presenters->ui($administrator->id, 'UzivatelMailSms', $parameters, 'POST', $post),
            BadRequestException::class,
            'Nemáte oprávnění.',
            403,
        );
        Assert::count(1, $mailer->messages);
    }

    public function testRequestIdentityAndStatusAreIsolated(): void {
        $second = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($second->id, 'VV');
        $server = $_SERVER;
        foreach ([$this->actor, $second] as $member) {
            $result = $this->presenters->ui($member->id, 'Homepage');
            Assert::same(TextResponse::class, $result->response::class);
            Assert::same(200, $result->status);
            Assert::contains($this->ap->jmeno, $result->html);
            Assert::true($result->identity instanceof HkfIdentity);
            Assert::same($member->id, $result->identity->getId());
            Assert::same($member->nick, $result->identity->getNick());
            Assert::same($server, $_SERVER);
        }

        $unauthorized = $this->presenters->api('Api:HealthCheck');
        Assert::same(JsonResponse::class, $unauthorized->response::class);
        Assert::same(401, $unauthorized->status);
        Assert::same(['result' => 'UNAUTHORIZED: Missing HTTP basic username', 'resultNumeric' => -1], $unauthorized->json);
        Assert::null($unauthorized->identity);

        $key = $this->fixtures->apiKey($this->ap->id);
        $authorized = $this->presenters->api('Api:HealthCheck', ['apikey' . $key->id, $key->klic]);
        Assert::same(JsonResponse::class, $authorized->response::class);
        Assert::same(200, $authorized->status);
        Assert::same('OK', $authorized->json['result']);
        Assert::match('%d%-%d%-%d%T%d%:%d%:%d%%a%', $authorized->json['serverTime']);
        Assert::null($authorized->identity);
        Assert::same($server, $_SERVER);
    }
}

(new SmokePresenterTest())->run();
