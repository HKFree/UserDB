<?php

if (file_exists('maintenance.php')) {
    require 'maintenance.php';
}

$container =  require __DIR__ . '/../app/bootstrap.php';

$container->getService('application')->run();
