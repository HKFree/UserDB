<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\Application;
use Nette\Application\BadRequestException;
use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Database\Connection;
use Nette\Database\ResultSet;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class RemovedMemberMergePresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    public function testMigrationsDoNotCreateMergeTable(): void {
        Assert::same(0, $this->database->query(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            'SloucenyUzivatel',
        )->fetchField());
    }

    public function testMergePresenterCannotBeCreated(): void {
        Assert::exception(
            fn (): IPresenter => $this->container->getByType(IPresenterFactory::class)->createPresenter('SpravaSlucovani'),
            InvalidPresenterException::class,
        );
    }

    public function testMergeFormUrlReturnsNotFound(): void {
        // The former form defaulted both members to UID 1; a missing member must not explain the 404.
        $this->fixtures->member($this->scenario->ap->id, ['id' => 1]);
        $this->assertMissingPage('/sprava-slucovani/slucovani');
    }

    public function testMergeListUrlReturnsNotFound(): void {
        $this->assertMissingPage('/sprava-slucovani/slouceni');
    }

    public function testAdminToolsDoNotOfferMerging(): void {
        $result = $this->presenters->ui($this->scenario->actors['vv']->id, 'Sprava', ['action' => 'nastroje']);
        Assert::same(200, $result->status);
        Assert::contains('Nástroje pro správu', $result->html);
        Assert::notContains('/sprava-slucovani/', $result->html);
        Assert::notContains('Sloučit uživatele', $result->html);
        Assert::notContains('Sloučení uživatelé', $result->html);
        Assert::contains('Schvalování čestného členství', $result->html);
    }

    public function testMemberDetailDoesNotReadOrDisplayMerges(): void {
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $mergeQueries = [];
        $connection->onQuery[] = static function (Connection $connection, ResultSet|\PDOException $result) use (&$mergeQueries): void {
            if ($result instanceof ResultSet && stripos($result->getQueryString(), 'SloucenyUzivatel') !== false) {
                $mergeQueries[] = $result->getQueryString();
            }
        };
        try {
            $result = $this->presenters->ui($this->scenario->actors['vv']->id, 'Uzivatel', [
                'action' => 'show', 'id' => (string) $this->scenario->member->id,
            ]);
        } finally {
            $connection->onQuery = $listeners;
        }
        Assert::same(200, $result->status);
        Assert::contains($this->scenario->member->nick, $result->html);
        Assert::notContains('Sloučen pod uživatele', $result->html);
        Assert::notContains('Sloučený uživatel', $result->html);
        Assert::same([], $mergeQueries);
    }

    private function assertMissingPage(string $path): void {
        $savedServer = $_SERVER;
        $savedSession = $_SESSION ?? null;
        $savedStatus = http_response_code();
        $bufferLevel = ob_get_level();
        try {
            $_SERVER['HTTP_UID'] = (string) $this->scenario->actors['vv']->id;
            $_SERVER['HTTP_GIVENNAME'] = $this->scenario->actors['vv']->nick;
            $_SERVER['HTTP_INITIALS'] = 'test-only';
            $_SESSION = [];
            http_response_code(200);
            $request = new Http\Request(new Http\UrlScript('http://localhost/userdb' . $path));
            $container = TestEnvironment::container($this->database, $request, $this->container);
            $application = $container->getByType(Application::class);
            $application->catchExceptions = false;
            ob_start();
            $exception = Assert::exception(static function () use ($application): void {
                $application->run();
            }, BadRequestException::class, null, 404);
            Assert::type(InvalidPresenterException::class, $exception?->getPrevious());
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $_SERVER = $savedServer;
            if ($savedSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $savedSession;
            }
            http_response_code(is_int($savedStatus) ? $savedStatus : 200);
        }
    }
}

(new RemovedMemberMergePresenterTest())->run();
