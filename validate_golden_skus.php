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

$heroPass = false;
$killPass = false;
$harvestPass = false;

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

    // ---- Golden assertions per spec ----
    $code = $data->sku_code;
    if ($code === 'CBL-BLK-3C-1M') {
        $allGatesPass = true;
        foreach ($results['gates'] as $gate) {
            if (!$gate['passed']) {
                $allGatesPass = false;
                break;
            }
        }
        if ($results['overall_status'] === 'VALID' && $allGatesPass && $results['can_publish'] === true) {
            echo "GOLDEN TEST HERO (CBL-BLK-3C-1M): PASS — all gates G1–G7 + VEC passed.\n";
            $heroPass = true;
        } else {
            echo "GOLDEN TEST HERO (CBL-BLK-3C-1M): FAIL — expected all gates to pass with VALID overall status.\n";
        }
    }

    if ($code === 'SKU-CABLE-002') {
        if ($sku->tier === 'KILL') {
            echo "GOLDEN TEST KILL (SKU-CABLE-002): PASS — tier=KILL (UI must fully lock fields).\n";
            $killPass = true;
        } else {
            echo "GOLDEN TEST KILL (SKU-CABLE-002): FAIL — expected tier=KILL, got {$sku->tier}.\n";
        }
    }

    if ($code === 'SKU-PEND-001') {
        $gateMap = [];
        foreach ($results['gates'] as $gate) {
            $gateMap[$gate['gate']] = $gate;
        }
        $g1Ok = isset($gateMap['G1_BASIC_INFO']) && $gateMap['G1_BASIC_INFO']['passed'];
        $g2Ok = (isset($gateMap['G2_INTENT']) && $gateMap['G2_INTENT']['passed'])
            || (isset($gateMap['G2_IMAGES']) && $gateMap['G2_IMAGES']['passed']);
        $g6Ok = (isset($gateMap['G6_COMMERCIAL']) && $gateMap['G6_COMMERCIAL']['passed'])
            || (isset($gateMap['G6_COMMERCIAL_POLICY']) && $gateMap['G6_COMMERCIAL_POLICY']['passed']);
        $g4Suspended = isset($gateMap['G4_ANSWER_BLOCK'])
            && $gateMap['G4_ANSWER_BLOCK']['passed']
            && str_contains($gateMap['G4_ANSWER_BLOCK']['reason'], 'Suspended');

        if ($g1Ok && $g2Ok && $g6Ok && $g4Suspended) {
            echo "GOLDEN TEST HARVEST (SKU-PEND-001): PASS — G1/G2/G6 active, G4 suspended for Harvest tier.\n";
            $harvestPass = true;
        } else {
            echo "GOLDEN TEST HARVEST (SKU-PEND-001): FAIL — expected G1/G2/G6 active and G4 suspended.\n";
        }
    }
    
    echo "-----------------------------------\n";
}

echo "Golden SKU verification complete.\n";

if (!($heroPass && $killPass && $harvestPass)) {
    echo "GOLDEN TEST SUMMARY: FAIL\n";
    exit(1);
}

echo "GOLDEN TEST SUMMARY: PASS\n";
