<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Sku;
use App\Services\ValidationService;
use App\Validators\GateValidator;
use App\Services\PythonWorkerClient; // Mock this if needed

require __DIR__ . '/backend/php/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/backend/php');
$dotenv->load();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'database'  => $_ENV['DB_DATABASE'] ?? 'cie',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Mock Log Facade
class MockLog {
    public static function __callStatic($method, $args) {}
}
class_alias('MockLog', 'Illuminate\Support\Facades\Log');

// Mock dependencies
$pythonClient = new class extends PythonWorkerClient { 
    public function __construct() {} // Override to avoid Guzzle init
    public function validateVector($d, $c, $s = null): array { return ['similarity' => 0.9]; } 
};
$validator = new GateValidator();
$service = new ValidationService($validator, $pythonClient);

try {
    // Find a SKU
    $sku = Sku::first();
    if (!$sku) die("No SKU found");

    echo "Initial Status: {$sku->validation_status->value}\n";

    // Simulate Controller Update
    $updateData = ['validation_status' => 'VALID'];
    $sku->update($updateData);
    
    // Simulate Service Call with Preservation Flag
    $manualStatusUpdate = true;
    $service->validate($sku->fresh(), $manualStatusUpdate);

    // Check Result
    $finalSku = $sku->fresh();
    echo "Final Status: {$finalSku->validation_status->value}\n";

    if ($finalSku->validation_status->value === 'VALID') {
        echo "SUCCESS: Status preserved.\n";
    } else {
        echo "FAILURE: Status reverted.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
