<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Runs PHPStan with the project configuration over a fixture and checks that the custom rule
 * UserDb\PHPStan\Rules\NoBooleanNotRule reports every "!" and nothing else.
 */
final class NoBooleanNotRuleTest extends TestCase
{
    private const ROOT_DIR = __DIR__ . '/..';

    public function testReportsEveryBooleanNot(): void {
        Assert::same([8, 11, 14, 17, 20], $this->reportedLines(__DIR__ . '/phpstan/BooleanNot.php.fixture'));
    }

    /**
     * @return list<int>
     */
    private function reportedLines(string $file): array {
        $command = [
            PHP_BINARY,
            self::ROOT_DIR . '/vendor/bin/phpstan',
            'analyse',
            '--configuration=' . __DIR__ . '/phpstan/no-boolean-not.neon',
            '--error-format=json',
            '--no-progress',
            '--memory-limit=512M',
            $file,
        ];
        $output = $this->runCommand($command);
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        Assert::true(is_array($result));

        $lines = [];
        foreach ($result['files'] ?? [] as $fileResult) {
            foreach ($fileResult['messages'] as $message) {
                if (($message['identifier'] ?? null) === 'userdb.booleanNot') {
                    $lines[] = $message['line'];
                }
            }
        }
        sort($lines);
        return $lines;
    }

    /**
     * @param list<string> $command
     */
    private function runCommand(array $command): string {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT_DIR);
        Assert::true(is_resource($process));
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        Assert::true(is_string($output) && $output !== '', 'PHPStan returned no output: ' . $errors);
        return $output;
    }
}

(new NoBooleanNotRuleTest())->run();
