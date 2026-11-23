<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

$originalCache = getenv('FLOX_ENV_CACHE');
$originalInstance = getenv('USERDB_LOCAL_INSTANCE');
$originalCookies = $_COOKIE;
$directory = __DIR__ . '/output/local-' . bin2hex(random_bytes(8));
FileSystem::createDir($directory);
FileSystem::write($directory . '/encryption.key', \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString(), 0600);
putenv('FLOX_ENV_CACHE=' . $directory);
putenv('USERDB_LOCAL_INSTANCE');
try {
    $container = require __DIR__ . '/../scripts/userdb_dev_bootstrap.php';
    $mailer = $container->getByType(\Nette\Mail\Mailer::class);
    Assert::type(\UserdbLocalMailer::class, $mailer);
    $message = (new \Nette\Mail\Message())
        ->setFrom('sender@example.invalid')
        ->addTo('recipient@example.invalid')
        ->setSubject('Local test')
        ->setBody('Local development mail');
    $mailer->send($message);
    $files = glob($directory . '/mail/*.eml');
    Assert::true(is_array($files));
    Assert::count(1, $files);
    $mail = file_get_contents($files[0]);
    Assert::true(is_string($mail));
    Assert::contains('Local development mail', $mail);

    Assert::match('mysql:unix_socket=%a%;dbname=userdb_dev', $container->getByType(\Nette\Database\Connection::class)->getDsn());
    // The setup may only ever complete the developer's database, never drop it.
    Assert::false(\UserdbLocalInstance::fromEnvironment()->isRebuiltOnSetup());
    Assert::same($directory, \UserdbLocalInstance::fromEnvironment()->runtimeDirectory($directory));

    $crypto = $container->getByType(\App\Services\CryptoSluzba::class);
    Assert::same('local test', $crypto->decrypt($crypto->encrypt('local test')));

    // A presenter reads its settings from the environment when it is created, so a variable the local web
    // does not provide fails only on the pages of that presenter.
    foreach ($container->findByType(\Nette\Application\IPresenter::class) as $service) {
        Assert::type(\Nette\Application\IPresenter::class, $container->getService($service));
    }

    // The production image raises the limit for forms with many IP addresses. With a lower limit here PHP
    // would drop the rest of such a form without an error.
    Assert::true(preg_match('~max_input_vars = (\d+)~', FileSystem::read(__DIR__ . '/../Dockerfile'), $productionLimit) === 1);
    Assert::same($productionLimit[1], ini_get('max_input_vars'));

    $cookieName = 'userdb_demo_persona_' . substr(hash('sha256', dirname(__DIR__)), 0, 20);
    $containerClass = $container::class;
    $fakeUser = new \ReflectionProperty(\App\Model\Authenticator::class, 'fakeUser');
    foreach ([null, '20002', '20005', '20006', '999999', '20002.0', '2e4', '+20002', "20002\n", ['20002']] as $value) {
        $_COOKIE = $value === null ? [] : [$cookieName => $value];
        $expectedId = in_array($value, ['20002', '20005', '20006'], true) ? (int) $value : 1;
        $expected = ['userID' => $expectedId, 'userName' => $expectedId === 1 ? 'tester' : 'demo' . $expectedId, 'passwordHash' => 'xxx'];
        $selected = require __DIR__ . '/../scripts/userdb_dev_bootstrap.php';
        Assert::same($containerClass, $selected::class, 'Personas must share the compiled DI container.');
        Assert::same($expected, $selected->getByType(\App\Settings::class)->fakeUser);
        Assert::same('/demo-osoba', $selected->getByType(\App\Settings::class)->demoPersonaUrl);
        Assert::same(\UserdbDemoPersonas::all()[$expectedId]['name'], $selected->getByType(\App\Settings::class)->demoPersonaName);
        Assert::same($expected, $fakeUser->getValue($selected->getByType(\App\Model\Authenticator::class)));
        $panel = \Tracy\Debugger::getBar()->getPanel('userdb-demo-persona');
        Assert::true($panel instanceof \UserdbDemoPersonaPanel);
        Assert::contains('<span class="tracy-label">' . $expectedId . ': ', $panel->getTab());
        Assert::contains('href="/demo-osoba"', $panel->getPanel());
    }

    // The browser tests run the same web over their own database, sessions, files and e-mails,
    // so they never touch what the developer has in the worktree.
    $_COOKIE = [];
    putenv('USERDB_LOCAL_INSTANCE=e2e');
    $e2e = require __DIR__ . '/../scripts/userdb_dev_bootstrap.php';
    Assert::same($containerClass, $e2e::class, 'Both instances must share the compiled DI container.');
    $e2eConnection = $e2e->getByType(\Nette\Database\Connection::class);
    Assert::match('mysql:unix_socket=%a%;dbname=userdb_e2e', $e2eConnection->getDsn());
    $e2e->getByType(\Nette\Mail\Mailer::class)->send($message);
    Assert::count(1, (array) glob($directory . '/e2e/mail/*.eml'));
    Assert::count(1, (array) glob($directory . '/mail/*.eml'));
    Assert::same($directory . '/e2e/sessions', $e2e->getByType(\Nette\Http\Session::class)->getOptions()['save_path']);
    Assert::same($directory . '/e2e/storage', getenv('FILE_STORAGE_PATH'));
    Assert::true(\UserdbLocalInstance::fromEnvironment()->isRebuiltOnSetup());
    Assert::same('userdb_e2e', \UserdbLocalInstance::fromEnvironment()->database());
    Assert::same($directory . '/e2e', \UserdbLocalInstance::fromEnvironment()->runtimeDirectory($directory));

    // A mistyped name must not fall back to the developer's database.
    putenv('USERDB_LOCAL_INSTANCE=e2e-typo');
    Assert::exception(
        static fn () => require __DIR__ . '/../scripts/userdb_dev_bootstrap.php',
        \RuntimeException::class,
        'USERDB_LOCAL_INSTANCE must be empty or "e2e".',
    );
} finally {
    putenv($originalInstance === false ? 'USERDB_LOCAL_INSTANCE' : 'USERDB_LOCAL_INSTANCE=' . $originalInstance);
    $_COOKIE = $originalCookies;
    putenv($originalCache === false ? 'FLOX_ENV_CACHE' : 'FLOX_ENV_CACHE=' . $originalCache);
    FileSystem::delete($directory);
}
