<?php

declare(strict_types=1);

if (file_exists('maintenance.php')) {
    require 'maintenance.php';
}

// Load the Composer autoloader
if (@!include __DIR__ . '/../vendor/autoload.php') {
    die('Install Nette using `composer install`');
}

// Initialize the application environment
$bootstrap = new App\Bootstrap();

// Create the Dependency Injection container
$container = $bootstrap->boot();

// Start the application and handle the incoming request
$application = $container->getByType(Nette\Application\Application::class);
$application->run();
