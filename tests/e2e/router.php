<?php

// Lokální běh E2E bez Dockeru (`php -S`); v CI běží skutečný Apache z Docker obrazu.
// Nette pod `php -S` vždy počítá basePath = '/', proto se statické soubory servírují i bez /userdb.
$root = realpath(__DIR__ . '/../../www');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . (str_starts_with($path, '/userdb/') ? substr($path, 7) : $path);
if (is_file($file) && $file !== $root . '/index.php') {
    $types = ['js' => 'application/javascript', 'css' => 'text/css', 'ico' => 'image/x-icon', 'png' => 'image/png'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}
chdir($root);
require $root . '/index.php';
