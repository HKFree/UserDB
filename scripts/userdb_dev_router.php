<?php

// Router for PHP's development server. The application's production entry stays unchanged.
$www = realpath(__DIR__ . '/../www');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~(?:^|/)\.~', $path)) {
    http_response_code(403);
    return;
}
$file = realpath($www . $path);
if ($file && str_starts_with($file, $www . '/') && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $www . '/index.php';
$container = require __DIR__ . '/userdb_dev_bootstrap.php';
$container->getByType(Nette\Application\Application::class)->run();
