<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

$originalCache = getenv('FLOX_ENV_CACHE');
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
    Assert::count(1, $files);
    Assert::contains('Local development mail', file_get_contents($files[0]));

    $crypto = $container->getByType(\App\Services\CryptoSluzba::class);
    Assert::same('local test', $crypto->decrypt($crypto->encrypt('local test')));
} finally {
    putenv($originalCache === false ? 'FLOX_ENV_CACHE' : 'FLOX_ENV_CACHE=' . $originalCache);
    FileSystem::delete($directory);
}
