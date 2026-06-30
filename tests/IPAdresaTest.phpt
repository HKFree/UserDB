<?php

namespace Test;

use Nette;
use Tester;
use Tester\Assert;
use App\Model\IPAdresa;

$container = require __DIR__ . '/bootstrap.php';


/**
 * Unit testy čistých (bez DB) pomocných metod modelu IPAdresa.
 * Service se bere z DI kontejneru; k databázi se Nette připojuje líně,
 * takže žádné z těchto volání DB nepotřebuje.
 */
class IPAdresaTest extends Tester\TestCase
{
    private IPAdresa $ipAdresa;

    public function __construct(Nette\DI\Container $container)
    {
        $this->ipAdresa = $container->getByType(IPAdresa::class);
    }

    public function testValidateIpAcceptsIpv4()
    {
        Assert::truthy($this->ipAdresa->validateIP('192.168.1.1'));
        Assert::truthy($this->ipAdresa->validateIP('10.0.0.254'));
    }

    public function testValidateIpRejectsInvalid()
    {
        Assert::false((bool) $this->ipAdresa->validateIP('999.1.1.1'));
        Assert::false((bool) $this->ipAdresa->validateIP('not-an-ip'));
        Assert::false((bool) $this->ipAdresa->validateIP('192.168.1.1/24'));
    }

    public function testSubnetExpansion()
    {
        // /30 => síťová + 2 použitelné (smyčka končí před broadcastem)
        Assert::same(
            ['192.168.1.0', '192.168.1.1', '192.168.1.2'],
            $this->ipAdresa->getListOfIPFromSubnet('192.168.1.0/30')
        );
    }

    public function testSubnetInvalidReturnsEmpty()
    {
        Assert::same([], $this->ipAdresa->getListOfIPFromSubnet('garbage'));
        Assert::same([], $this->ipAdresa->getListOfIPFromSubnet(''));
    }

    public function testRangeExpansionIsInclusive()
    {
        Assert::same(
            ['192.168.1.1', '192.168.1.2', '192.168.1.3'],
            $this->ipAdresa->getListOfIPFromRange('192.168.1.1-192.168.1.3')
        );
    }

    public function testRangeInvalidReturnsEmpty()
    {
        Assert::same([], $this->ipAdresa->getListOfIPFromRange('garbage'));
        Assert::same([], $this->ipAdresa->getListOfIPFromRange(''));
    }
}


(new IPAdresaTest($container))->run();
