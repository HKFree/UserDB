<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

$originalCache = getenv('FLOX_ENV_CACHE');
$originalCookies = $_COOKIE;
$directory = __DIR__ . '/output/local-' . bin2hex(random_bytes(8));
FileSystem::createDir($directory);
FileSystem::write($directory . '/encryption.key', \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString(), 0600);
putenv('FLOX_ENV_CACHE=' . $directory);
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
        Assert::type(\UserdbDemoPersonaPanel::class, $panel);
        Assert::contains('<span class="tracy-label">' . $expectedId . ': ', $panel->getTab());
        Assert::contains('href="/demo-osoba"', $panel->getPanel());
    }
} finally {
    $_COOKIE = $originalCookies;
    putenv($originalCache === false ? 'FLOX_ENV_CACHE' : 'FLOX_ENV_CACHE=' . $originalCache);
    FileSystem::delete($directory);
}
