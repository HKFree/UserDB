<?php

final class ContractPdf
{
    /** @param array<array-key, mixed> $parameters Values are validated before template substitution. */
    public static function generate(array $parameters, string $template, string $binary, string $cache): string {
        if (is_file($template) === false) {
            throw new RuntimeException('Contract template does not exist.');
        }
        $directory = $cache . '/contract-' . bin2hex(random_bytes(16));
        if (mkdir($directory, 0700, true) === false) {
            throw new RuntimeException('Cannot create the contract working directory.');
        }
        $absoluteDirectory = realpath($directory);
        if ($absoluteDirectory === false) {
            rmdir($directory);
            throw new RuntimeException('Cannot resolve the contract working directory.');
        }
        $directory = $absoluteDirectory;
        try {
            self::run(['unzip', '-q', $template, '-d', $directory . '/document'], $directory);
            $content = self::readFile($directory . '/document/content.xml');
            $replacements = [];
            foreach ($parameters as $key => $value) {
                if (is_scalar($value) === false && $value !== null) {
                    throw new RuntimeException('Contract parameters must be scalar values.');
                }
                $replacements['{' . $key . '}'] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $content = strtr($content, $replacements);
            $content = preg_replace('/\{[a-zA-Z0-9-._]+\}/', '', $content);
            file_put_contents($directory . '/document/content.xml', $content);
            self::run(['zip', '-q', '-0', '-X', '../document.odt', 'mimetype'], $directory . '/document');
            self::run(['zip', '-q', '-r', '../document.odt', '.', '-x', 'mimetype', 'command.log'], $directory . '/document');

            // A separate profile also isolates concurrent conversions in one worktree.
            $profile = 'file://' . implode('/', array_map('rawurlencode', explode('/', $directory . '/profile')));
            self::run([$binary, '-env:UserInstallation=' . $profile, '--headless', '--norestore',
                '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $directory, $directory . '/document.odt'], $directory);
            $file = $directory . '/document.pdf';
            if (is_file($file) === false || str_starts_with($pdf = self::readFile($file), '%PDF-') === false) {
                throw new RuntimeException('LibreOffice did not produce a PDF: ' . trim(self::readFile($directory . '/command.log')));
            }
            return $pdf;
        } finally {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && $file->isLink() === false ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    private static function readFile(string $file): string {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException('Cannot read PDF conversion file: ' . basename($file));
        }
        return $content;
    }

    /** @param non-empty-list<string> $command */
    private static function run(array $command, string $directory): void {
        // @phpstan-ignore argument.type (PHP requires an int redirect target; PHPStan only allows strings in descriptor lists.)
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/command.log', 'a'], 2 => ['redirect', 1]], $pipes, $directory);
        if (is_resource($process) === false) {
            throw new RuntimeException('Cannot start the PDF converter.');
        }
        $deadline = microtime(true) + 60;
        do {
            $status = proc_get_status($process);
            if ($status['running'] === false) {
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                proc_close($process);
                throw new RuntimeException('PDF conversion timed out.');
            }
            usleep(100000);
        } while (true);
        proc_close($process);
        if ($status['exitcode'] !== 0) {
            throw new RuntimeException('PDF command failed: ' . basename($command[0]));
        }
    }
}
