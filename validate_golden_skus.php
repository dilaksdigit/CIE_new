<?php
require_once 'backend/php/vendor/autoload.php';

use App\Models\Sku;
use App\Services\MaturityScoreService;
use App\Services\ChannelGovernorService;
use App\Services\TitleEngineService;
use App\Validators\GateValidator;
use Illuminate\Database\Capsule\Manager as Capsule;

// Setup Eloquent
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'cie_v232',
    'username' => 'root',
    'password' => 'root1234',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$skus = json_decode(file_get_contents('database/seeds/golden_test_data.json'));
$validator = new GateValidator();
$maturityService = new MaturityScoreService();
$governorService = new ChannelGovernorService();
$titleService = new TitleEngineService();

foreach ($skus as $data) {
    echo "Validating SKU: {$data->sku_code}...\n";
    $sku = Sku::where('sku_code', $data->sku_code)->first();
    
    if (!$sku) {
        echo "FAIL: SKU {$data->sku_code} not found.\n";
        continue;
    }

    $results = $validator->validateAll($sku);
    echo "  L3 Gates: " . ($results['overall_status'] === 'VALID' ? "PASS" : "FAIL ({$results['overall_status']})") . "\n";
    if ($results['overall_status'] !== 'VALID') {
        foreach ($results['gates'] as $gate) {
            if (!$gate['passed']) {
                echo "    - {$gate['gate']}: {$gate['reason']}\n";
            }
        }
    }

    $titles = $titleService->generate($sku);
    echo "  L4 Title: {$titles['shopify_title']}\n";

    $channels = $governorService->assess($sku);
    echo "  L5 Channels: Google={$channels['google_sge']['status']}, Amazon={$channels['amazon']['status']}\n";

    $maturity = $maturityService->calculate($sku);
    echo "  Maturity: {$maturity['total']} ({$maturity['level']})\n";
    
    echo "-----------------------------------\n";
}
echo "Verification complete.\n";
