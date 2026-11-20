<?php

declare(strict_types=1);

namespace Test;

use App\Model\Subnet;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class SubnetTest extends TestCase
{
    private Subnet $model;

    protected function setUp(): void {
        $this->model = (new \ReflectionClass(Subnet::class))->newInstanceWithoutConstructor();
    }

    public function validationCases(): array {
        return [
            ['10.107.0.0/16', true],
            ['10.107.0.64/16', false],
            ['10.107.0.64/26', true],
            ['10.107.0.0', false],
            ['10.107.0.0//16', false],
            ['not-an-ip/24', false],
            ['256.0.0.0/24', false],
            ['10.107.0.0/-1', false],
            ['10.107.0.0/33', false],
            ['10.107.0.0/mask', false],
            ['0.0.0.0/0', true],
            ['10.107.0.0/0', false],
            ['0.0.0.1/0', false],
            ['255.255.255.255/0', false],
        ];
    }

    /** @dataProvider validationCases */
    public function testValidation(string $subnet, bool $expected): void {
        Assert::same($expected, $this->model->validateSubnet($subnet));
    }

    public function maskCases(): array {
        return [
            [0, '0.0.0.0'],
            [8, '255.0.0.0'],
            [24, '255.255.255.0'],
            [30, '255.255.255.252'],
            [32, '255.255.255.255'],
        ];
    }

    /** @dataProvider maskCases */
    public function testMask(int $cidr, string $expected): void {
        Assert::same($expected, $this->model->CIDRToMask($cidr));
    }

    public function parsingCases(): array {
        return [
            ['10.107.0.64/26', '10.107.0.64', '26', '255.255.255.192'],
            ['0.0.0.0/0', '0.0.0.0', '0', '0.0.0.0'],
            ['192.0.2.1/32', '192.0.2.1', '32', '255.255.255.255'],
        ];
    }

    /** @dataProvider parsingCases */
    public function testParsing(string $subnet, string $network, string $cidr, string $mask): void {
        Assert::same([
            'subnet' => $subnet,
            'network' => $network,
            'cidr' => $cidr,
            'mask' => $mask,
        ], $this->model->parseSubnet($subnet));
    }

    public function collisionCases(): array {
        return [
            ['192.0.2.0/24', '192.0.2.0/24', true],
            ['192.0.2.0/24', '192.0.2.64/26', true],
            ['192.0.2.64/26', '192.0.2.0/24', true],
            ['192.0.2.0/25', '192.0.2.128/25', false],
            ['192.0.2.128/25', '192.0.2.0/25', false],
            ['192.0.2.0/24', '198.51.100.0/24', false],
        ];
    }

    /** @dataProvider collisionCases */
    public function testCollision(string $first, string $second, bool $expected): void {
        Assert::same($expected, $this->model->checkColision($first, $second));
    }

    public function membershipCases(): array {
        return [
            ['192.0.2.65', '192.0.2.64/26', true],
            ['192.0.2.64', '192.0.2.64/26', true],
            ['192.0.2.127', '192.0.2.64/26', true],
            ['192.0.2.63', '192.0.2.64/26', false],
            ['192.0.2.128', '192.0.2.64/26', false],
            ['203.0.113.42', '0.0.0.0/0', true],
            ['0.0.0.0', '0.0.0.0/0', true],
            ['255.255.255.255', '0.0.0.0/0', true],
        ];
    }

    /** @dataProvider membershipCases */
    public function testMembership(string $ip, string $subnet, bool $expected): void {
        Assert::same($expected, $this->model->inSubnet($ip, $subnet));
    }

    public function collisionsCases(): array {
        return [
            [[], false],
            [['192.0.2.0/24'], false],
            [['192.0.2.0/25', '192.0.2.128/25', '198.51.100.0/24'], false],
            [
                ['192.0.2.0/24', '192.0.2.64/26', '192.0.2.128/25', '198.51.100.0/24'],
                ['192.0.2.64/26 - 192.0.2.0/24', '192.0.2.128/25 - 192.0.2.0/24'],
            ],
        ];
    }

    /** @dataProvider collisionsCases */
    public function testCollisions(array $subnets, array|bool $expected): void {
        Assert::same($expected, $this->model->checkColisions($subnets));
    }
}

(new SubnetTest())->run();
