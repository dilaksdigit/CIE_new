<?php
require_once 'backend/php/vendor/autoload.php';

use App\Models\Sku;
use App\Models\Intent;
use App\Models\SkuIntent;
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

$data = json_decode(file_get_contents('database/seeds/golden_test_data.json'), true);

foreach ($data as $skuData) {
    echo "Importing {$skuData['sku_code']}...\n";
    
    // 1. Handle Secondary Intents (remove for Sku table update)
    $secondary = $skuData['secondary_intents'] ?? [];
    $primary = $skuData['primary_intent'] ?? null;
    unset($skuData['secondary_intents']);
    unset($skuData['primary_intent']);

    // 2. Update/Create SKU
    $sku = Sku::updateOrCreate(
        ['sku_code' => $skuData['sku_code']],
        $skuData
    );

    // 3. Clear existing intents
    SkuIntent::where('sku_id', $sku->id)->delete();

    // 4. Add Primary
    if ($primary) {
        $intent = Intent::firstOrCreate(['name' => $primary]);
        SkuIntent::create([
            'sku_id' => $sku->id,
            'intent_id' => $intent->id,
            'cluster_id' => $sku->primary_cluster_id,
            'is_primary' => true
        ]);
    }

    // 5. Add Secondaries
    foreach ($secondary as $sName) {
        $intent = Intent::where('name', $sName)->first();
        if (!$intent) {
            $intent = new Intent();
            $intent->id = \Illuminate\Support\Str::uuid()->toString();
            $intent->name = $sName;
            $intent->save();
        }
        
        SkuIntent::create([
            'sku_id' => $sku->id,
            'intent_id' => $intent->id,
            'cluster_id' => $sku->primary_cluster_id,
            'is_primary' => false
        ]);
    }
}
echo "Seeding complete.\n";
