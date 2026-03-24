<?php
/**
 * Boot minimal Laravel app for PHPUnit (BusinessRules, Eloquent, app()).
 * SOURCE: backend/php/public/index.php — same path layout.
 */
declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;

define('LARAVEL_START', microtime(true));

// bootstrap lives at tests/php → repository root is two levels up
$repoRoot = dirname(__DIR__, 2);
$backendPhp = $repoRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'php';

require $backendPhp . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!isset($_ENV['APP_KEY']) && !getenv('APP_KEY')) {
    $_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
}

$app = new Illuminate\Foundation\Application($backendPhp);

$app->useAppPath($backendPhp . DIRECTORY_SEPARATOR . 'src');
$app->useConfigPath($repoRoot . DIRECTORY_SEPARATOR . 'config');
$app->useStoragePath($repoRoot . DIRECTORY_SEPARATOR . 'storage');

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

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
