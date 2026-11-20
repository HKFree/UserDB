<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../pdfGenerator/www/ContractPdf.php';
\Tester\Environment::setup();

$directory = __DIR__ . "/output/pdf space's-" . bin2hex(random_bytes(8));
FileSystem::createDir($directory);
$template = __DIR__ . '/../pdfGenerator/templates/SmlouvaUcastnicka_v7_template.odt';
$binary = getenv('USERDB_LIBREOFFICE') ?: 'libreoffice';
try {
    foreach (['Žluťoučký & syn', 'Prázdné volitelné údaje'] as $name) {
        $pdf = \ContractPdf::generate(['jmeno_prijmeni' => $name, 'cislo' => 'LOCAL-42'], $template, $binary, $directory);
        Assert::true(str_starts_with($pdf, '%PDF-'));
        Assert::same([], glob($directory . '/contract-*'));
        file_put_contents($directory . '/result.pdf', $pdf);
        $process = proc_open(['pdftotext', $directory . '/result.pdf', $directory . '/result.txt'], [], $pipes);
        Assert::same(0, proc_close($process));
        $text = file_get_contents($directory . '/result.txt');
        Assert::contains($name, $text);
        Assert::contains('LOCAL-42', $text);
        Assert::notContains('{telefon}', $text);
    }
    Assert::exception(fn () => \ContractPdf::generate([], $directory . '/missing.odt', $binary, $directory), \RuntimeException::class);
    Assert::exception(fn () => \ContractPdf::generate([], $template, '/usr/bin/false', $directory), \RuntimeException::class);
    Assert::exception(fn () => \ContractPdf::generate(['telefon' => []], $template, $binary, $directory), \RuntimeException::class);
    Assert::same([], glob($directory . '/contract-*'));
} finally {
    FileSystem::delete($directory);
}
