<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/UserdbLocalMailer.php';
require_once __DIR__ . '/UserdbDemoPersonas.php';
require_once __DIR__ . '/UserdbDemoPersonaPanel.php';

return (static function (): Nette\DI\Container {
    $root = dirname(__DIR__);
    $persona = new UserdbDemoPersonas($_COOKIE[UserdbDemoPersonas::cookieName($root)] ?? null);
    $cache = getenv('FLOX_ENV_CACHE');
    if (!$cache || !getenv('USERDB_MARIADB_SOCKET')) {
        throw new RuntimeException('Activate the project with flox activate -s first.');
    }
    $keyFile = $cache . '/encryption.key';
    if (!is_file($keyFile)) {
        throw new RuntimeException('Initialize local development first: php scripts/userdb_dev_setup.php');
    }
    foreach (['sessions', 'storage', 'mail'] as $directory) {
        Nette\Utils\FileSystem::createDir($cache . '/' . $directory);
    }
    putenv('FILE_STORAGE_PATH=' . $cache . '/storage');
    putenv('AGREEMENT_NAME_PREFIX=LOCAL TEST');
    putenv('USERDB_LOCAL_PDF=1');
    putenv('CONTEXT_DOCUMENT_ROOT=' . $root . '/www');

    $env = array_replace(getenv(), [
        'USERDB_ENCRYPTION_PASSPHRASE' => trim(file_get_contents($keyFile)),
        'USERDB_IDS_USERNAME' => 'local-only',
        'USERDB_IDS_PASSWORD' => 'local-only',
        'USERDB_IDS_IPS_WHITELIST' => '',
        'USERDB_SESSION_NAME' => 'userdb_' . substr(hash('sha256', $root), 0, 20),
    ]);
    $configurator = new Nette\Bootstrap\Configurator();
    $configurator->setDebugMode(true);
    $configurator->setTempDirectory($root . '/temp/flox');
    $configurator->enableTracy($root . '/log');
    // Registered before the container is built, so the panel is there even when its compilation fails.
    Tracy\Debugger::getBar()->addPanel(new UserdbDemoPersonaPanel($persona), 'userdb-demo-persona');
    $configurator->addStaticParameters(['appDir' => $root . '/app', 'wwwDir' => $root . '/www']);
    $configurator->addDynamicParameters(['env' => $env, 'demoFakeUser' => $persona->fakeUser()]);
    $configurator->addConfig($root . '/app/config/config.neon');
    $configurator->addConfig($root . '/app/config/config.flox.neon');
    return $configurator->createContainer();
})();
