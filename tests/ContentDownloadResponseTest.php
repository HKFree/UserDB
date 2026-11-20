<?php

declare(strict_types=1);

namespace Test;

use App\Model\ContentDownloadResponse;
use Nette\Http;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

foreach ([null, 'text/plain'] as $contentType) {
    $download = new ContentDownloadResponse("download\0content", 'example.txt', $contentType);
    Assert::same('example.txt', $download->getName());
    Assert::same($contentType ?? 'application/octet-stream', $download->getContentType());

    ob_start();
    try {
        $download->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
        $body = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    Assert::same("download\0content", $body);
}
