<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class AdminerAccessTest extends TestCase
{
    public function addressCases(): array {
        return [
            [['REMOTE_ADDR' => '127.0.0.1'], true],
            [['REMOTE_ADDR' => '::1'], true],
            [['REMOTE_ADDR' => '192.0.2.1'], false],
            [['REMOTE_ADDR' => '127.0.0.2'], false],
            [['REMOTE_ADDR' => ''], false],
            [[], false],
            [['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.1'], false],
        ];
    }

    /** @dataProvider addressCases */
    public function testAccess(array $server, bool $allowed): void {
        // Isolate exit() and headers; a static asset reaches Adminer without login or database access.
        $code = '$_SERVER = ' . var_export($server + ['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'localhost'], true) . ';'
            . '$_GET = ["file" => "adminer.css"]; putenv("ADMINER_HTTPS_REDIRECT=false");'
            . 'http_response_code(200); ob_start(); register_shutdown_function(static function (): void {'
            . '$body = ob_get_clean(); echo json_encode([http_response_code(), $body], JSON_THROW_ON_ERROR); });'
            . 'require ' . var_export(__DIR__ . '/../www/adminer/index.php', true) . ';';
        $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        Assert::type('resource', $process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        Assert::same(0, proc_close($process), $errors . $output);
        Assert::same('', $errors);
        [$status, $body] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        Assert::same($allowed ? 200 : 403, $status);
        if ($allowed) {
            Assert::same(file_get_contents(__DIR__ . '/../vendor/dg/adminer-custom/adminer.css'), $body);
        } else {
            Assert::same('Adminer is available only from localhost', trim($body));
        }
    }
}

(new AdminerAccessTest())->run();
