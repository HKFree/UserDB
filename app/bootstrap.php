<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

if ($_ENV['TRACY_ENABLE']) {
    $configurator->setDebugMode(true); // enable for all IP
}
//$configurator->setDebugMode(false); // disable for all IP (incl. localhost)
//$configurator->setDebugMode('8.8.8.8'); // enable for IP 8.8.8.8

if (php_sapi_name() !== 'cli') {
    // enable Tracy only in web env. Dump errors to console in CLI mode (eg. during git-based deployment).
    $configurator->enableDebugger(__DIR__ . '/../log', 'is@hkfree.org');
}

$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

$configurator->addParameters(['env' => $_ENV]);

$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon');

$container = $configurator->createContainer();

Kdyby\Replicator\Container::register();

return $container;
