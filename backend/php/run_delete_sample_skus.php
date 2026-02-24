<?php
/**
 * One-off: delete all sample SKUs. Run from repo root:
 *   php backend/php/run_delete_sample_skus.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = new \Illuminate\Foundation\Application(realpath(__DIR__));
$app->useConfigPath(realpath(__DIR__ . '/../../config'));
$app->useStoragePath(realpath(__DIR__ . '/../../storage'));
$app->singleton(\Illuminate\Contracts\Console\Kernel::class, \App\Console\Kernel::class);
$app->singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, \App\Exceptions\Handler::class);
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sku;

$codes = [
    'CBL-BLK-3C-1M', 'CBL-GLD-3C-1M', 'CBL-WHT-2C-3M', 'CBL-RED-3C-2M',
    'SHD-TPE-DRM-35', 'SHD-GLS-CNE-20', 'BLB-LED-E27-4W', 'BLB-LED-B22-8W',
    'PND-SET-BRS-3L', 'FLR-ARC-BLK-175',
];
$exact = Sku::whereIn('sku_code', $codes)->get();
$pattern = Sku::where('sku_code', 'like', 'CBL-BLK-3C-1M-%')->get();
$all = $exact->merge($pattern)->unique('id');

if ($all->isEmpty()) {
    echo "No sample SKUs found.\n";
    exit(0);
}
foreach ($all as $sku) {
    $sku->delete();
    echo "Deleted: {$sku->sku_code}\n";
}
echo count($all) . " sample SKU(s) deleted.\n";
