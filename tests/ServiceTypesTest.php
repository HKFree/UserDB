<?php

declare(strict_types=1);

namespace Test;

use App\Services\CryptoSluzba;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class ServiceTypesTest extends TestCase
{
    public function testEncryptedEmptyAndUnicodeValues(): void {
        $crypto = new CryptoSluzba(Key::createNewRandomKey()->saveToAsciiSafeString());
        foreach (['', 'Žluťoučké heslo', "binary\0value"] as $value) {
            Assert::same($value, $crypto->decrypt($crypto->encrypt($value)));
        }
    }

    public function testCiphertextNeedsItsOriginalKey(): void {
        $first = new CryptoSluzba(Key::createNewRandomKey()->saveToAsciiSafeString());
        $second = new CryptoSluzba(Key::createNewRandomKey()->saveToAsciiSafeString());
        $encrypted = $first->encrypt('Secret fixture');
        Assert::exception(fn () => $second->decrypt($encrypted), WrongKeyOrModifiedCiphertextException::class, 'Integrity check failed.');
    }

}

(new ServiceTypesTest())->run();
