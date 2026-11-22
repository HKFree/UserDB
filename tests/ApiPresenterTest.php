<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\TextResponse;
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
