<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/UserdbLocalInstance.php';
require_once __DIR__ . '/UserdbLocalMailer.php';
require_once __DIR__ . '/UserdbDemoPersonas.php';
require_once __DIR__ . '/UserdbDemoPersonaPanel.php';

return (static function (): Nette\DI\Container {
    $root = dirname(__DIR__);
    $persona = new UserdbDemoPersonas($_COOKIE[UserdbDemoPersonas::cookieName($root)] ?? null);
    $cache = getenv('FLOX_ENV_CACHE');
    if (in_array($cache, [false, '', '0'], true) || in_array(getenv('USERDB_MARIADB_SOCKET'), [false, '', '0'], true)) {
        throw new RuntimeException('Activate the project with flox activate -s first.');
    }
    $keyFile = $cache . '/encryption.key';
    if (!is_file($keyFile)) {
        throw new RuntimeException('Initialize local development first: php scripts/userdb_dev_setup.php');
    }
    $instance = UserdbLocalInstance::fromEnvironment();
    $runtime = $instance->runtimeDirectory($cache);
    foreach (['sessions', 'storage', 'mail'] as $directory) {
        Nette\Utils\FileSystem::createDir($runtime . '/' . $directory);
    }
    putenv('FILE_STORAGE_PATH=' . $runtime . '/storage');
    putenv('AGREEMENT_NAME_PREFIX=LOCAL TEST');
    putenv('USERDB_LOCAL_PDF=1');
    putenv('CONTEXT_DOCUMENT_ROOT=' . $root . '/www');

    // A key from the environment wins, the rest is always local.
    $env = array_replace(['USERDB_GOOGLE_MAPS_KEY' => 'local-only'], getenv(), [
        'USERDB_ENCRYPTION_PASSPHRASE' => trim(Nette\Utils\FileSystem::read($keyFile)),
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
    $configurator->addDynamicParameters([
        'env' => $env,
        'demoFakeUser' => $persona->fakeUser(),
        'demoPersonaUrl' => UserdbDemoPersonas::PATH,
        'demoPersonaName' => $persona->name(),
        'localInstance' => [
            'dsn' => $instance->dsn((string) getenv('USERDB_MARIADB_SOCKET')),
            'user' => $instance->user(),
            'password' => $instance->password(),
            'runtimeDir' => $runtime,
        ],
    ]);
    $configurator->addConfig($root . '/app/config/config.neon');
    $configurator->addConfig($root . '/app/config/config.flox.neon');
    return $configurator->createContainer();
})();
