<?php

declare(strict_types=1);

namespace Test;

use App\Model\VnejsiScanner;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class VnejsiScannerTest extends TestCase
{
    public function testNumericFiltersMatchTextPortsWithoutChangingOutput(): void {
        $scan = [
            '192.0.2.1' => ['22', '80', '443'],
            '192.0.2.2' => ['0080', ' 443 ', '8.0e1'],
            '192.0.2.3' => ['8080', '80/tcp', ''],
            '192.0.2.4' => ['0', '65535'],
            '192.0.2.5' => [],
        ];
        $scanner = new VnejsiScanner('http://127.0.0.1:0/');
        (new \ReflectionProperty(VnejsiScanner::class, 'scanData'))->setValue($scanner, $scan);

        foreach ([[80], ['80'], ['0080'], [443], ['443'], [' 80 '], ['8e1']] as $filter) {
            Assert::same(array_slice($scan, 0, 2, true), $scanner->getScanNaPortech($filter));
        }
        Assert::same(['192.0.2.3' => $scan['192.0.2.3']], $scanner->getScanNaPortech([8080]));
        Assert::same(['192.0.2.4' => $scan['192.0.2.4']], $scanner->getScanNaPortech([0, 65535]));
        Assert::same([], $scanner->getScanNaPortech([21]));
        Assert::same([], $scanner->getScanNaPortech([]));
    }
}

(new VnejsiScannerTest())->run();
