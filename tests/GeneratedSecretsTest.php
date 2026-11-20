<?php

declare(strict_types=1);

namespace Test;

use App\Model\ApiKlic;
use App\Model\Uzivatel;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Shape of generated passwords and API keys. The generators use no database or services.
 */
final class GeneratedSecretsTest extends TestCase
{
    private const PASSWORD_CHAR = '[a-hjkmnp-zA-HJKMNP-Z2-9]';
    private const KEY_CHAR = '[2-9a-km-np-zA-NP-Z]';

    public function passwordCases(): array {
        return [
            'default' => [9, false, '{9}'],
            'dashes, 9' => [9, true, '{3}(-' . self::PASSWORD_CHAR . '{3}){2}'],
            'dashes, 10' => [10, true, '{3}(-' . self::PASSWORD_CHAR . '{3}){2}-' . self::PASSWORD_CHAR],
            'dashes, 16' => [16, true, '{4}(-' . self::PASSWORD_CHAR . '{4}){3}'],
        ];
    }

    /** @dataProvider passwordCases */
    public function testStrongPassword(int $length, bool $dashes, string $shape): void {
        $model = (new \ReflectionClass(Uzivatel::class))->newInstanceWithoutConstructor();
        for ($i = 0; $i < 20; $i++) {
            $password = $model->generateStrongPassword($length, $dashes);
            Assert::match('~^' . self::PASSWORD_CHAR . $shape . '$~', $password);
            // One character of each set: lowercase, uppercase, digit.
            Assert::match('~[a-z]~', $password);
            Assert::match('~[A-Z]~', $password);
            Assert::match('~[2-9]~', $password);
        }
    }

    public function keyCases(): array {
        return [[30], [5], [100]];
    }

    /** @dataProvider keyCases */
    public function testApiKey(int $length): void {
        $model = (new \ReflectionClass(ApiKlic::class))->newInstanceWithoutConstructor();
        for ($i = 0; $i < 20; $i++) {
            Assert::match('~^' . self::KEY_CHAR . '{' . $length . '}$~', $model->generateKey($length));
        }
        Assert::same(30, strlen($model->generateKey()));
    }
}

(new GeneratedSecretsTest())->run();
