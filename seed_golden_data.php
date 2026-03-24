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

// Map display intent names to canonical intent keys (intents table uses name = key)
$intentNameMap = [
    'Compatibility' => 'compatibility',
    'Installation/How-To' => 'installation',
    'Specification' => 'specification',
    'Inspiration/Style' => 'inspiration',
    'Comparison' => 'comparison',
    'Replacement/Refill' => 'replacement',
    'Problem-Solving' => 'problem_solving',
    'Regulatory/Safety' => 'regulatory',
    'Troubleshooting' => 'troubleshooting',
];

foreach ($data as $skuData) {
    echo "Importing {$skuData['sku_code']}...\n";

    // Support new structure (use_case, content, commercial) or legacy flat structure
    $useCase = $skuData['use_case'] ?? null;
    $content = $skuData['content'] ?? [];
    $commercial = $skuData['commercial'] ?? [];

    if ($useCase !== null) {
        $primary = isset($useCase['primary_intent']) ? ($intentNameMap[$useCase['primary_intent']] ?? $useCase['primary_intent']) : null;
        $secondary = $useCase['secondary_intents'] ?? [];
        $secondary = array_map(function ($s) use ($intentNameMap) {
            return $intentNameMap[$s] ?? $s;
        }, $secondary);
        $payload = [
            'sku_code' => $skuData['sku_code'],
            'title' => $skuData['product_name'] ?? $skuData['sku_code'],
            'tier' => isset($skuData['tier']) ? strtolower($skuData['tier']) : null,
            'meta_description' => $content['meta_description'] ?? null,
            'ai_answer_block' => $content['ai_answer_block'] ?? null,
            'current_price' => $commercial['price_gbp'] ?? null,
            'cost' => $commercial['cost_gbp'] ?? null,
            'margin_percent' => $commercial['contribution_margin_pct'] ?? null,
            'annual_volume' => $commercial['velocity_90d'] ?? null,
            'erp_cppc' => $commercial['cppc'] ?? null,
            'erp_return_rate_pct' => $commercial['return_rate_pct'] ?? null,
        ];
        if (!empty($content['best_for'])) {
            $payload['best_for'] = is_array($content['best_for']) ? json_encode($content['best_for']) : $content['best_for'];
        }
        if (!empty($content['not_for'])) {
            $payload['not_for'] = is_array($content['not_for']) ? json_encode($content['not_for']) : $content['not_for'];
        }
    } else {
        $primary = $skuData['primary_intent'] ?? null;
        $secondary = $skuData['secondary_intents'] ?? [];
        $payload = $skuData;
        unset($payload['secondary_intents'], $payload['primary_intent']);
    }

    $sku = Sku::updateOrCreate(
        ['sku_code' => $payload['sku_code']],
        $payload
    );

    SkuIntent::where('sku_id', $sku->id)->delete();

    if ($primary) {
        $intent = Intent::firstOrCreate(['name' => $primary]);
        SkuIntent::create([
            'sku_id' => $sku->id,
            'intent_id' => $intent->id,
            'cluster_id' => $sku->primary_cluster_id,
            'is_primary' => true
        ]);
    }

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
