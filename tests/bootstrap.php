<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();
ob_start();
date_default_timezone_set('Europe/Prague');

foreach (['TestDoubles', 'TestEnvironment', 'Fixtures', 'PresenterRunner', 'DatabaseTestCase'] as $helper) {
    require_once __DIR__ . '/' . $helper . '.php';
}
require_once __DIR__ . '/PermissionScenario.php';

$storage = Test\TestEnvironment::TEMP_DIR . '/files';
Nette\Utils\FileSystem::createDir($storage);
foreach ([
    'DIGISIGN_ACCESS_KEY' => 'test-only',
    'DIGISIGN_SECRET_KEY' => 'test-only',
    'FILE_STORAGE_PATH' => $storage,
    'PDF_GENERATOR_URL' => 'http://127.0.0.1:0/',
    'CONTEXT_DOCUMENT_ROOT' => Test\TestEnvironment::TEMP_DIR . '/www',
    'AGREEMENT_NAME_PREFIX' => 'TEST',
] as $name => $value) {
    putenv("$name=$value");
    $_ENV[$name] = $value;
}
putenv('USERDB_LOCAL_PDF');
unset($_ENV['USERDB_LOCAL_PDF']);
