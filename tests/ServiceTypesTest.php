<?php

declare(strict_types=1);

namespace Test;

use App\Model\Subnet;
use App\Services\CryptoSluzba;
use App\Services\PdfGenerator;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Nette\Utils\DateTime;
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

    public function testPdfMonthsAcceptDatabaseDatesAndText(): void {
        $subnet = (new \ReflectionClass(Subnet::class))->newInstanceWithoutConstructor();
        $pdf = new PdfGenerator($subnet);
        $date = new DateTime('2026-12-15 12:30:00');
        foreach (['2026-12-15 12:30:00', $date] as $input) {
            Assert::same('leden', $pdf->mesicName($input, 1));
            Assert::same('17.12.2026', $pdf->mesicDate($input, 0));
        }
        Assert::same('2026-12-15 12:30:00', $date->format('Y-m-d H:i:s'));
        Assert::same('17.03.2026', $pdf->mesicDate('2026-01-31', 1));
    }
}

(new ServiceTypesTest())->run();
