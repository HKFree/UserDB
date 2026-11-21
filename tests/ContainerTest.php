<?php

declare(strict_types=1);

namespace Test;

use App\Model\HkfIdentity;
use Nette\Application\IPresenter;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Loaders\RobotLoader;
use Tester\Assert;
use Tester\TestCase;
use Tracy\Debugger;

require_once __DIR__ . '/bootstrap.php';

final class ContainerTest extends TestCase
{
    public function testContainerAndPresenters(): void {
        $container = TestEnvironment::container();
        Assert::same(realpath(__DIR__ . '/../app'), $container->parameters['appDir']);
        Assert::same(realpath(__DIR__ . '/../www'), $container->parameters['wwwDir']);
        Assert::same(TestEnvironment::TEMP_DIR, $container->parameters['tempDir']);
        Assert::true($container->parameters['debugMode']);
        Assert::false($container->parameters['debug']['fakeUser']);
        // Without the development bootstrap there is no demo person to switch.
        Assert::null($container->getByType(\App\Settings::class)->demoPersonaUrl);
        Assert::same('/userdb', $container->parameters['urlPrefix']);
        Assert::notSame($container, TestEnvironment::container());

        $presenters = $container->findByType(IPresenter::class);
        $classes = [];
        foreach ($presenters as $service) {
            $presenter = $container->getService($service);
            Assert::type(IPresenter::class, $presenter);
            $classes[] = $presenter::class;
        }
        $loader = new RobotLoader();
        $loader->addDirectory(__DIR__ . '/../app/presenters', __DIR__ . '/../app/ApiModule/Presenters');
        $loader->rebuild();
        foreach (array_keys($loader->getIndexedClasses()) as $class) {
            if (str_starts_with($class, 'App\\') && str_ends_with($class, 'Presenter') && !(new \ReflectionClass($class))->isAbstract()) {
                Assert::contains($class, $classes);
            }
        }
        foreach ([
            'mail.mailer' => RecordingMailer::class,
            'smsSender' => RecordingSmsSender::class,
            'mexSmsSender' => RecordingMexSmsSender::class,
            'requestDruzstvoContract' => RecordingContractRequest::class,
            'session.session' => MemorySession::class,
            'security.userStorage' => MemoryUserStorage::class,
            'cache.storage' => MemoryStorage::class,
        ] as $name => $class) {
            Assert::same($class, $container->getService($name)::class);
        }
        Assert::same(
            'mysql:unix_socket=' . getenv('USERDB_MARIADB_SOCKET') . ';dbname=userdb_test',
            $container->getByType(Connection::class)->getDsn(),
        );
        foreach (['igw1IpCheckerUrl', 'igw2IpCheckerUrl', 'sojkaPingerURL', 'outerScannerURL', 'influxUrl', 'maternaMexUrl'] as $name) {
            Assert::same('http://127.0.0.1:0/', $container->parameters[$name]);
        }
        Assert::same('test-only', $container->parameters['env']['USERDB_SALT']);
        Assert::same('test-only', getenv('DIGISIGN_ACCESS_KEY'));
        Assert::same('test-only', getenv('DIGISIGN_SECRET_KEY'));
        Assert::same(TestEnvironment::TEMP_DIR . '/files', getenv('FILE_STORAGE_PATH'));
        Assert::same('http://127.0.0.1:0/', getenv('PDF_GENERATOR_URL'));
        Assert::same(TestEnvironment::TEMP_DIR . '/www', getenv('CONTEXT_DOCUMENT_ROOT'));
        Assert::same('TEST', getenv('AGREEMENT_NAME_PREFIX'));
        Assert::false(getenv('USERDB_LOCAL_PDF'));
    }

    public function testAdministrationPresenterAcceptsMissingMapsKey(): void {
        $container = TestEnvironment::container();
        unset($container->parameters['env']['USERDB_GOOGLE_MAPS_KEY']);

        // Preserve the existing warning; the missing key must not also cause a TypeError.
        Assert::error(function () use ($container): void {
            Assert::type(\App\Presenters\SpravaPresenter::class, $container->getService('SpravaPresenter'));
        }, E_WARNING, 'Undefined array key "USERDB_GOOGLE_MAPS_KEY"');
    }

    public function testContainersDoNotRetainTracyPanels(): void {
        $bar = Debugger::getBar();
        $panels = new \ReflectionProperty($bar, 'panels');
        $before = $panels->getValue($bar);
        for ($i = 0; $i < 3; $i++) {
            $container = TestEnvironment::container();
            $container->getByType(\Nette\Application\Application::class);
            $container->getByType(\Nette\Security\User::class);
            $presenter = $container->getByType(\Nette\Application\IPresenterFactory::class)->createPresenter('Homepage');
            if (!$presenter instanceof \Nette\Application\UI\Presenter) {
                throw new \LogicException('Homepage is not a UI presenter.');
            }
            $container->getByType(\Nette\Application\UI\TemplateFactory::class)->createTemplate($presenter);
        }
        Assert::same($before, $panels->getValue($bar));
    }

    public function testRecordingDoublesAndMemoryStorage(): void {
        $container = TestEnvironment::container();
        $identity = new HkfIdentity(123, ['VV'], 'Test member', 'test-only');
        $storage = $container->getByType(MemoryUserStorage::class);
        $storage->saveAuthentication($identity);
        Assert::same([true, $identity, null], $storage->getState());
        Assert::same([false, null, null], TestEnvironment::container()->getByType(MemoryUserStorage::class)->getState());
        $session = $container->getByType(MemorySession::class);
        $session->getSection('fixture')->set('flash', 'Fixture flash');
        Assert::same('Fixture flash', $session->getSection('fixture')->get('flash'));
        Assert::true($session->isStarted());
        Assert::same(PHP_SESSION_NONE, session_status());
        $session->destroy();
        Assert::false($session->exists());
    }
}

(new ContainerTest())->run();
