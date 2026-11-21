<?php

declare(strict_types=1);

// Sign-in service of the shared testing server, see "Testing server" in readme.md. Runs under PHP's own server
// with the options of userdb_testing_login.service:
//   php -d display_errors=0 -d display_startup_errors=0 -S 127.0.0.1:981 userdb_testing_login.php
// The reverse proxy lets a request in on 2xx and hands any other answer over to the visitor. With errors
// displayed, a warning or a broken setup would be answered with 200 and the error; without them PHP answers
// a broken setup with 500 and logs the reason. The two options have to be set before PHP reads the request.
if (ini_get('display_errors') !== '0' || ini_get('display_startup_errors') !== '0') {
    http_response_code(500);
    error_log('Start the sign-in service with -d display_errors=0 -d display_startup_errors=0.');
    return;
}
ini_set('log_errors', '1');
header_remove('X-Powered-By');
require_once __DIR__ . '/UserdbTestingLogin.php';

$login = new UserdbTestingLogin(
    (string) getenv('USERDB_TESTING_LOGIN_PASSWORD_HASH'),
    (string) getenv('USERDB_TESTING_LOGIN_COOKIE_DOMAIN'),
    (string) getenv('STATE_DIRECTORY'),
);
$login->handle($_SERVER, getallheaders(), $_GET, $_POST, $_COOKIE, time())->send();
