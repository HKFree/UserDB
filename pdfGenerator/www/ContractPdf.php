<?php

final class ContractPdf
{
    public static function generate(array $parameters, string $template, string $binary, string $cache): string {
        if (!is_file($template)) {
            throw new RuntimeException('Contract template does not exist.');
        }
        $directory = $cache . '/contract-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700, true)) {
            throw new RuntimeException('Cannot create the contract working directory.');
        }
        $directory = realpath($directory);
        try {
            self::run(['unzip', '-q', $template, '-d', $directory . '/document'], $directory);
            $content = file_get_contents($directory . '/document/content.xml');
            $replacements = [];
            foreach ($parameters as $key => $value) {
                if (!is_scalar($value) && $value !== null) {
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
            if (!is_file($file) || !str_starts_with($pdf = file_get_contents($file), '%PDF-')) {
                throw new RuntimeException('LibreOffice did not produce a PDF: ' . trim(file_get_contents($directory . '/command.log')));
            }
            return $pdf;
        } finally {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    private static function run(array $command, string $directory): void {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/command.log', 'a'], 2 => ['redirect', 1]], $pipes, $directory);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the PDF converter.');
        }
        $deadline = microtime(true) + 60;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
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
