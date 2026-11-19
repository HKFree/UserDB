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
} finally {
    chdir($originalDirectory);
}
