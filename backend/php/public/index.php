<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Autoload
require __DIR__ . '/../vendor/autoload.php';

// Create the Application
$app = new \Illuminate\Foundation\Application(
    realpath(__DIR__ . '/../')
);

// Set custom paths
$app->useConfigPath(realpath(__DIR__ . '/../../../config'));
$app->useStoragePath(realpath(__DIR__ . '/../../../storage'));

// Register base bindings/components if needed
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

// Handle the Request
$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
