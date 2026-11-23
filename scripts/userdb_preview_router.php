<?php

declare(strict_types=1);

// Router of a pull request preview, see "Pull request previews" in readme.md. The sign-in form decides about
// every request first, also about the files of www/; what it lets in is answered by the router of the local web.
header_remove('X-Powered-By');
require_once __DIR__ . '/UserdbTestingLogin.php';

try {
    $refusal = UserdbTestingLogin::fromEnvironment()->guard($_SERVER, getallheaders(), $_GET, $_POST, $_COOKIE, time());
} catch (Throwable $e) {
    // A preview with a broken setup lets nobody in and shows nothing of the setup to the visitor.
    error_log('The sign-in form of the preview failed: ' . $e->getMessage());
    http_response_code(500);
    return;
}
if ($refusal !== null) {
    $refusal->send();
    return;
}

return require __DIR__ . '/userdb_dev_router.php';
