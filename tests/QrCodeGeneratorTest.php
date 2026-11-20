<?php

namespace Test;

use App\Services\QrCodeGenerator;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

$originalDirectory = getcwd();
try {
    chdir(sys_get_temp_dir());
    Assert::true(class_exists(QrCodeGenerator::class));
    Assert::true(is_callable(['QRcode', 'png']));
    $png = QrCodeGenerator::renderPngBase64('Local contract');
    Assert::true(str_starts_with($png, 'data:image/png;base64,'));
    Assert::same("\x89PNG\r\n\x1a\n", substr(base64_decode(substr($png, 22)), 0, 8));
} finally {
    chdir($originalDirectory);
}
