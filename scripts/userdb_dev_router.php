<?php

// Router for PHP's development server. The application's production entry stays unchanged.
$www = realpath(__DIR__ . '/../www');
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($requestPath) === false) {
    http_response_code(400);
    return;
}
$path = rawurldecode($requestPath);
if ($path === '/demo-osoba') {
    require_once __DIR__ . '/UserdbDemoPersonas.php';
    require_once __DIR__ . '/UserdbDemoPersonaPage.php';
    $cookieName = UserdbDemoPersonas::cookieName(dirname(__DIR__));
    $page = new UserdbDemoPersonaPage(new UserdbDemoPersonas($_COOKIE[$cookieName] ?? null));
    $page->serve($cookieName, $_GET, $_SERVER, getallheaders());
    return;
}
if (preg_match('~(?:^|/)\.~', $path) === 1) {
    http_response_code(403);
    return;
}
$file = realpath($www . $path);
if ($file !== false && str_starts_with($file, $www . '/') && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $www . '/index.php';
$container = require __DIR__ . '/userdb_dev_bootstrap.php';
$container->getByType(Nette\Application\Application::class)->run();
