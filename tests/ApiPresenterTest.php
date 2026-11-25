<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class ApiPresenterTest extends DatabaseTestCase
{
    public function testMissingCredentials(): void {
        $result = $this->presenters->api('Api:HealthCheck');
        Assert::same(JsonResponse::class, $result->response::class);
        Assert::same(401, $result->status);
        Assert::same(['result' => 'UNAUTHORIZED: Missing HTTP basic username', 'resultNumeric' => -1], $result->json);
    }

    /** @return list<array{string, string}> */
    public function invalidCredentialCases(): array {
        return [
            ['unknown key', '2099-12-31'],
            ['wrong password', '2099-12-31'],
            ['expired key', '2001-01-01'],
        ];
    }

    /** @dataProvider invalidCredentialCases */
    public function testInvalidCredentials(string $case, string $expires): void {
        $key = $this->fixtures->apiKey(expires: $expires);
        $id = $key->id;
        $password = $key->klic;
        if ($case === 'unknown key') {
            $id = (int) $this->database->table('ApiKlic')->max('id') + 1;
            Assert::null($this->database->table('ApiKlic')->get($id));
        } elseif ($case === 'wrong password') {
            $password .= '-wrong';
        }
        $result = $this->presenters->api('Api:HealthCheck', ['apikey' . $id, $password]);
        Assert::same(JsonResponse::class, $result->response::class);
        Assert::same(401, $result->status);
        Assert::same(['result' => 'UNAUTHORIZED: Invalid credentials', 'resultNumeric' => -1], $result->json);
    }

    public function testUnrestrictedKey(): void {
        $key = $this->fixtures->apiKey();
        $result = $this->presenters->api('Api:HealthCheck', ['apikey' . $key->id, $key->klic]);
        Assert::same(JsonResponse::class, $result->response::class);
        Assert::same(200, $result->status);
        Assert::same('OK', $result->json['result']);
    }

    public function testPresenterRestriction(): void {
        $area = $this->fixtures->area();
        $key = $this->fixtures->apiKey(presenter: 'Api:DeviceDb');
        $credentials = ['apikey' . $key->id, $key->klic];

        $allowed = $this->presenters->api('Api:DeviceDb', $credentials);
        Assert::same(JsonResponse::class, $allowed->response::class);
        Assert::same(200, $allowed->status);
        Assert::same([$area->id => $area->jmeno], $allowed->json);

        $forbidden = $this->presenters->api('Api:Areas', $credentials);
        Assert::same(JsonResponse::class, $forbidden->response::class);
        Assert::same(403, $forbidden->status);
        Assert::same(['result' => 'FORBIDDEN: not allowed to view module Api:Areas', 'resultNumeric' => -1], $forbidden->json);

        $health = $this->presenters->api('Api:HealthCheck', $credentials);
        Assert::same(JsonResponse::class, $health->response::class);
        Assert::same(200, $health->status);
        Assert::same('OK', $health->json['result']);
    }

    public function testAreasReturnsApFlagsAsJsonBooleans(): void {
        $area = $this->fixtures->area();
        $firstAp = $this->fixtures->ap($area->id);
        $firstAp->update(['no_auto_dns' => 1, 'no_auto_upgrade' => 0, 'spravce_neaktivni' => 1]);
        $secondAp = $this->fixtures->ap($area->id);
        $secondAp->update(['no_auto_dns' => 0, 'no_auto_upgrade' => 1, 'spravce_neaktivni' => 0]);
        $key = $this->fixtures->apiKey(presenter: 'Api:Areas');

        $result = $this->presenters->api('Api:Areas', ['apikey' . $key->id, $key->klic]);
        Assert::same(200, $result->status);
        Assert::type(JsonResponse::class, $result->response);

        ob_start();
        try {
            $result->response->send(new Http\Request(new Http\UrlScript('http://localhost/userdb/api/areas')), new Http\Response());
            $areas = json_decode((string) ob_get_contents(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }

        foreach ([$firstAp->id => [true, false, true], $secondAp->id => [false, true, false]] as $apId => $expected) {
            $ap = $areas[$area->id]['aps'][$apId];
            Assert::same($expected, [
                $ap['no_auto_dns'] ?? null,
                $ap['no_auto_upgrade'] ?? null,
                $ap['spravce_neaktivni'] ?? null,
            ]);
        }
    }

    public function testOptionsWithoutCredentials(): void {
        $result = $this->presenters->api('Api:DeviceDb', null, [], 'OPTIONS');
        Assert::same(TextResponse::class, $result->response::class);
        Assert::same(200, $result->status);
        Assert::same('', $result->html);
        Assert::null($result->json);
        if ($result->response instanceof TextResponse) {
            Assert::same('', $result->response->getSource());
        }
    }
}

(new ApiPresenterTest())->run();
