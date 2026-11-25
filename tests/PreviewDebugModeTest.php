<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

/**
 * The preview runs without the debug bar of Tracy: on a slow connection it made every page much larger.
 * The local web keeps it. Tracy is set up once per process, so each mode is read from its own PHP.
 */
$modeOf = static function (array $environment): string {
    $directory = __DIR__ . '/output/debug-mode-' . bin2hex(random_bytes(8));
    FileSystem::createDir($directory);
    FileSystem::write($directory . '/encryption.key', \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString(), 0600);
    try {
        $code = 'require ' . var_export(__DIR__ . '/../scripts/userdb_dev_bootstrap.php', true) . ';'
            . ' echo Tracy\Debugger::$productionMode ? "production" : "debug";';
        $inherited = getenv();
        unset($inherited['USERDB_DEBUG_MODE']);
        $null = ['file', '/dev/null', 'r'];
        $process = proc_open([PHP_BINARY, '-r', $code], [$null, ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['FLOX_ENV_CACHE' => $directory] + $environment + $inherited);
        Assert::true(is_resource($process));
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        Assert::same(0, proc_close($process), $errors);
        return $output;
    } finally {
        FileSystem::delete($directory);
    }
};

Assert::same('debug', $modeOf([]));
Assert::same('production', $modeOf(['USERDB_DEBUG_MODE' => '0']));

// scripts/userdb_preview.sh is what starts the preview.
Assert::contains("\nexport USERDB_DEBUG_MODE=0\n", FileSystem::read(__DIR__ . '/../scripts/userdb_preview.sh'));
