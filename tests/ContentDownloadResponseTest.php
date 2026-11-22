<?php

declare(strict_types=1);

namespace Test;

use App\Model\ContentDownloadResponse;
use Nette\Http;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

foreach ([[null, 'application/octet-stream'], ['', 'application/octet-stream'], ['0', 'application/octet-stream'], ['text/plain', 'text/plain']] as [$contentType, $expectedType]) {
    $download = new ContentDownloadResponse("download\0content", 'example.txt', $contentType);
    Assert::same('example.txt', $download->getName());
    Assert::same($expectedType, $download->getContentType());

    ob_start();
    try {
        $download->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
        $body = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    Assert::same("download\0content", $body);
}
