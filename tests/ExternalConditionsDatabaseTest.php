<?php

declare(strict_types=1);

namespace Test;

use App\ApiModule\Presenters\StatusPresenter;
use App\Model\IPAdresa;
use App\Model\Oblast;
use App\Model\Sojka;
use App\Model\Status;
use App\Presenters\SpravaStitkuPresenter;
use Nette\Application\AbortException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Responses\JsonResponse;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class ExternalConditionsDatabaseTest extends DatabaseTestCase
{
    public function testPingFlagsKeepTheirMeaningInModelAndApi(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.42']);
        $sojka = new class ('http://127.0.0.1:0/') extends Sojka {
            public mixed $alive = null;

            /**
             * @param string[] $ips
             * @return array<string, array{alive: mixed, time_lastpong: int, loss: int, rtt: int}>
             */
            public function pingIPS($ips): array {
                $result = [];
                foreach ($ips as $ip) {
                    $result[$ip] = ['alive' => $this->alive, 'time_lastpong' => time(), 'loss' => 0, 'rtt' => 0];
                }
                return $result;
            }
        };
        $areas = $this->container->getByType(Oblast::class);
        $status = new Status($sojka, $areas);
        $api = new StatusPresenter($areas, $sojka, $this->container->getByType(IPAdresa::class), $status);
        $api->injectHttpRequest(new Http\Request(new Http\UrlScript('http://localhost/')));
        $api->injectHttpResponse(new Http\Response());
        foreach ([[null, true], [false, true], [0, true], [0.0, true], ['', true], ['0', true], [[], true], [true, false], [1, false], ['00', false], ['false', false], [['0'], false]] as [$value, $dead]) {
            $sojka->alive = $value;
            $problems = $status->getProblemoveAP()[0][1][0][1];
            Assert::same($dead ? [Status::PROBLEM_MRTVA] : [], array_column($problems, 1));
            Assert::exception(fn () => $api->actionGetOblasti(), AbortException::class);
            $response = (new \ReflectionProperty(\Nette\Application\UI\Presenter::class, 'response'))->getValue($api);
            if (!$response instanceof JsonResponse) {
                throw new \LogicException('Expected the status JSON response.');
            }
            $result = $response->getPayload()[$area->id]['ap'][$ap->id];
            Assert::same($dead ? 1 : 0, $result['mrtvych']);
            Assert::same($dead ? 3 : 0, $result['status']);
        }
    }

    public function testLabelDeletionRejectsEmptyJsonIdsWithoutChangingLabels(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        foreach ([20261, 20262] as $labelId) {
            $this->database->table('StitekUzivatele')->insert(['Uzivatel_id' => $member->id, 'Stitek_id' => $labelId]);
        }
        $before = $this->database->table('StitekUzivatele')->count('*');
        foreach (['stitek_id', 'user_id'] as $field) {
            foreach ([null, false, 0, 0.0, '', '0', []] as $value) {
                $data = [$field => $value] + ['stitek_id' => 20261, 'user_id' => $member->id];
                Assert::same(['success' => false, 'message' => 'Neplatné parametry.'], $this->deleteLabel($data));
                Assert::same($before, $this->database->table('StitekUzivatele')->count('*'));
            }
        }
        Assert::same(['success' => true, 'stitekId' => 20261, 'userId' => $member->id], $this->deleteLabel(['stitek_id' => 20261, 'user_id' => $member->id]));
        Assert::same([20262], $this->database->table('StitekUzivatele')->fetchPairs(null, 'Stitek_id'));
    }

    /**
     * @param array{stitek_id: mixed, user_id: mixed} $data
     * @return array<string, mixed>
     */
    private function deleteLabel(array $data): array {
        $request = new Http\Request(new Http\UrlScript('http://localhost/'), method: 'DELETE', remoteAddress: '192.0.2.1', rawBodyCallback: static fn (): string => json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $container = TestEnvironment::container($this->database, $request, $this->container);
        $presenter = $container->getByType(IPresenterFactory::class)->createPresenter('SpravaStitku');
        if (!$presenter instanceof SpravaStitkuPresenter) {
            throw new \LogicException('Expected the label presenter.');
        }
        ob_start();
        try {
            Assert::exception(fn () => $presenter->actionDeleteLabel(), AbortException::class);
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        Assert::true(is_string($body));
        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }
}

(new ExternalConditionsDatabaseTest())->run();
