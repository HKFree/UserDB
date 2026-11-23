<?php

declare(strict_types=1);

namespace Test;

use App\Model\Sojka;
use App\Presenters\AjaxApiPresenter;
use Nette\Application\AbortException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class EmptyValuesPresenterTest extends DatabaseTestCase
{
    public function testEmptyPingNeverCallsTheConnector(): void {
        $sojka = new class ('http://127.0.0.1:0/') extends Sojka {
            /** @var list<mixed> */
            public array $calls = [];

            /**
             * @param mixed $ips
             * @return array<string, bool>
             */
            public function pingIPS($ips): array {
                $this->calls[] = $ips;
                return ['local-result' => true];
            }
        };
        $responseProperty = new \ReflectionProperty(\Nette\Application\UI\Presenter::class, 'response');
        $presenter = new AjaxApiPresenter($this->container->getByType(\App\Model\Subnet::class), $sojka);
        foreach ([null, '', '0', 0, 0.0, false, []] as $input) {
            $presenter->loadState(['ips' => $input]);
            Assert::exception(fn () => $presenter->renderGetIpsPing(), AbortException::class);
            $response = $responseProperty->getValue($presenter);
            Assert::type(JsonResponse::class, $response);
            Assert::same([], $response->getPayload());
            Assert::same([], $sojka->calls);
        }
        $presenter->loadState(['ips' => ['192.0.2.1']]);
        Assert::exception(fn () => $presenter->renderGetIpsPing(), AbortException::class);
        Assert::same([['192.0.2.1']], $sojka->calls);
        $response = $responseProperty->getValue($presenter);
        Assert::type(JsonResponse::class, $response);
        Assert::same(['local-result' => true], $response->getPayload());
    }

    public function testTagFormKeepsCreateAndEditButtons(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        foreach ([null, '', '0', 0] as $id) {
            // An empty path segment cannot be routed, so exercise form creation directly too.
            $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('SpravaStitku');
            if (($presenter instanceof \App\Presenters\SpravaStitkuPresenter) === false) {
                throw new \LogicException('Expected the tag administration presenter.');
            }
            $presenter->loadState(['id' => $id]);
            $form = $presenter->getComponent('editStitekForm');
            if ((($button = $form['send']) instanceof \Nette\Forms\Controls\SubmitButton) === false) {
                throw new \LogicException('Expected the tag form submit button.');
            }
            Assert::same('Vytvořit', $button->getCaption());
        }
        $tag = $this->fixtures->insert('Stitek', ['text' => 'Testovací', 'barva_pozadi' => '#ffffff', 'barva_popredi' => '#000000']);
        $result = $this->presenters->ui($actor->id, 'SpravaStitku', ['action' => 'edit', 'id' => (string) $tag->id]);
        $buttons = DomQuery::fromHtml($result->html)->find('input[name="send"]');
        Assert::count(1, $buttons);
        Assert::same('Upravit', (string) $buttons[0]['value']);
    }

    public function testAreaAdminCannotCreateApThroughMissingOrZeroId(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $other = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $user = $this->container->getByType(\Nette\Security\User::class);
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('Ap');
        $check = new \ReflectionMethod($presenter, 'smiUpravitAp');
        $user->login(new \Nette\Security\SimpleIdentity($actor->id, ['SO-' . $ap->Oblast_id]));
        foreach ([null, '', '0', 0, $other->id] as $id) {
            Assert::false($check->invoke($presenter, $id));
        }
        Assert::true($check->invoke($presenter, $ap->id));
        Assert::true($check->invoke($presenter, (string) $ap->id));
        $user->login(new \Nette\Security\SimpleIdentity($actor->id, ['VV']));
        Assert::true($check->invoke($presenter, null));
    }
}

(new EmptyValuesPresenterTest())->run();
