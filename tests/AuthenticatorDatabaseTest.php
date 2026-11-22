<?php

declare(strict_types=1);

namespace Test;

use App\Model\Authenticator;
use App\Model\HkfIdentity;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator as SecurityAuthenticator;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class AuthenticatorDatabaseTest extends DatabaseTestCase
{
    /** @var array<string, mixed> */
    private array $savedServer;
    private ActiveRow $area;
    private ActiveRow $member;
    private Authenticator $authenticator;

    protected function setUp(): void {
        parent::setUp();
        $this->savedServer = $_SERVER;
        $this->area = $this->fixtures->area();
        $ap = $this->fixtures->ap($this->area->id);
        $this->member = $this->fixtures->member($ap->id);
        $_SERVER['HTTP_GIVENNAME'] = 'Header-' . $this->member->nick;
        $_SERVER['HTTP_INITIALS'] = 'test-password';
        $this->authenticator = $this->container->getByType(Authenticator::class);
    }

    protected function tearDown(): void {
        $_SERVER = $this->savedServer;
        parent::tearDown();
    }

    public function testActiveAreaAndGlobalRoles(): void {
        $this->fixtures->role($this->member->id, 'SO', $this->area->id, until: null);
        $this->fixtures->role($this->member->id, 'VV');

        $identity = $this->authenticator->authenticate([$this->member->id, null]);
        Assert::type(HkfIdentity::class, $identity);
        Assert::same($this->member->id, $identity->getId());
        Assert::same('Header-' . $this->member->nick, $identity->getNick());
        Assert::same('test-password', $identity->getPasswordHash());
        $roles = $identity->getRoles();
        sort($roles);
        Assert::same(['SO-' . $this->area->id, 'VV'], $roles);
    }

    public function testConfiguredIdentityKeepsItsIdAndOptionalPassword(): void {
        $this->fixtures->role($this->member->id, 'VV');
        unset($_SERVER['HTTP_GIVENNAME'], $_SERVER['HTTP_INITIALS']);
        foreach ([[], ['passwordHash' => 'configured-hash']] as $optional) {
            $authenticator = new Authenticator([
                'userID' => (string) $this->member->id,
                'userName' => 'Configured identity',
            ] + $optional, $this->database);
            $identity = $authenticator->authenticate(['DBG', null]);
            Assert::type(HkfIdentity::class, $identity);
            Assert::same($this->member->id, $identity->getId());
            Assert::same(['VV'], $identity->getRoles());
            Assert::same('Configured identity', $identity->getNick());
            Assert::same($optional['passwordHash'] ?? 'dummy', $identity->getPasswordHash());
        }
    }

    public function testInactiveRolesAreIgnored(): void {
        $this->fixtures->role($this->member->id, 'SO', $this->area->id);
        $this->fixtures->role($this->member->id, 'VV', until: '2001-01-01');
        $this->fixtures->role($this->member->id, 'TECH', from: '2090-01-01');

        $identity = $this->authenticator->authenticate([$this->member->id, null]);
        Assert::same(['SO-' . $this->area->id], $identity->getRoles());
    }

    /** @return list<array{bool, bool}> */
    public function inactiveRoleCases(): array {
        return [
            [true, false],
            [false, true],
            [true, true],
        ];
    }

    /** @dataProvider inactiveRoleCases */
    public function testOnlyInactiveRolesAreRejected(bool $expired, bool $future): void {
        if ($expired) {
            $this->fixtures->role($this->member->id, 'SO', $this->area->id, until: '2001-01-01');
        }
        if ($future) {
            $this->fixtures->role($this->member->id, 'VV', from: '2090-01-01');
        }
        Assert::exception(
            fn () => $this->authenticator->authenticate([$this->member->id, null]),
            AuthenticationException::class,
            'User not allowed.',
            SecurityAuthenticator::NotApproved,
        );
    }

    /** @return array<string, array{list<string>}> */
    public function missingHeaderCases(): array {
        return [
            'name' => [['HTTP_GIVENNAME']],
            'password' => [['HTTP_INITIALS']],
            'both' => [['HTTP_GIVENNAME', 'HTTP_INITIALS']],
        ];
    }

    /**
     * @dataProvider missingHeaderCases
     * @param list<string> $missing
     */
    public function testMissingIdentityHeaderIsRejected(array $missing): void {
        $this->fixtures->role($this->member->id, 'VV');
        foreach ($missing as $header) {
            unset($_SERVER[$header]);
        }

        Assert::exception(
            fn () => $this->authenticator->authenticate([$this->member->id, null]),
            AuthenticationException::class,
            'User attributes not provided.',
            SecurityAuthenticator::Failure,
        );
    }

    public function testEmptyUserIdIsRejected(): void {
        foreach ([null, '', 0, '0'] as $id) {
            Assert::exception(
                fn () => $this->authenticator->authenticate([$id, null]),
                AuthenticationException::class,
                'User not found.',
                SecurityAuthenticator::IdentityNotFound,
            );
        }
        Assert::exception(fn () => $this->authenticator->authenticate(['00', null]), AuthenticationException::class, 'User not allowed.', SecurityAuthenticator::NotApproved);
    }
}

(new AuthenticatorDatabaseTest())->run();
