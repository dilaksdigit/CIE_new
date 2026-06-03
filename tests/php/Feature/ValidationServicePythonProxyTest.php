<?php
// SOURCE: DECISION-011 — CMS validate must delegate to Python, not PHP GateValidator.

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ValidationServicePythonProxyTest extends TestCase
{
    public function test_validation_service_delegates_to_python_worker(): void
    {
        $service = file_get_contents(dirname(__DIR__, 3) . '/backend/php/src/Services/ValidationService.php');
        $this->assertStringContainsString('validateSkuGates', $service);
        $this->assertStringNotContainsString('validateAll($sku', $service);
        $this->assertStringContainsString('buildSkuValidatePayload', $service);
    }

    public function test_python_worker_client_exposes_validate_sku_gates(): void
    {
        $client = file_get_contents(dirname(__DIR__, 3) . '/backend/php/src/Services/PythonWorkerClient.php');
        $this->assertStringContainsString("'/api/v1/sku/validate'", $client);
        $this->assertStringContainsString('function validateSkuGates', $client);
    }
}
