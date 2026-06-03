<?php
// SOURCE: Python worker URL alignment — port 8000 / config/services.php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PythonWorkerConfigTest extends TestCase
{
    /** @test services.php defaults python worker to port 8000 */
    public function test_services_config_defaults_to_port_8000(): void
    {
        $configFile = dirname(__DIR__, 3) . '/config/services.php';
        $contents = file_get_contents($configFile);
        $this->assertStringContainsString("'http://python-worker:8000'", $contents);
    }

    /** @test services.php exposes api_v1_base for GSC/GA4/baseline proxies */
    public function test_services_config_exposes_api_v1_base(): void
    {
        $configFile = dirname(__DIR__, 3) . '/config/services.php';
        $contents = file_get_contents($configFile);
        $this->assertStringContainsString("'api_v1_base'", $contents);
        $this->assertStringContainsString('/api/v1', $contents);
    }

    /** @test baseline and GSC controllers use config not raw CIE_ENGINE_BASE_URL env */
    public function test_baseline_and_gsc_use_config_api_v1_base(): void
    {
        $baseline = file_get_contents(dirname(__DIR__, 3) . '/backend/php/src/Services/BaselineService.php');
        $gsc = file_get_contents(dirname(__DIR__, 3) . '/backend/php/src/Controllers/GscController.php');
        $ga4 = file_get_contents(dirname(__DIR__, 3) . '/backend/php/src/Controllers/Ga4Controller.php');
        foreach ([$baseline, $gsc, $ga4] as $contents) {
            $this->assertStringContainsString("config('services.python_worker.api_v1_base')", $contents);
        }
    }

    /** @test PythonWorkerClient reads config not stale port 5000 env default */
    public function test_python_worker_client_uses_config_services(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Services/PythonWorkerClient.php';
        $contents = file_get_contents($file);
        $this->assertStringContainsString("config('services.python_worker.url'", $contents);
        $this->assertStringNotContainsString("env('PYTHON_API_URL', 'http://localhost:5000')", $contents);
    }

    /** @test health check hits live Python root endpoint not dead /health path */
    public function test_python_worker_health_uses_api_root(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Services/PythonWorkerClient.php';
        $contents = file_get_contents($file);
        $this->assertStringContainsString("->get('/api/')", $contents);
        $this->assertStringNotContainsString("->get('/health')", $contents);
    }

    /** @test ValidationService proxies gate checks to POST /api/v1/sku/validate */
    public function test_python_worker_client_exposes_sku_validate(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Services/PythonWorkerClient.php';
        $contents = file_get_contents($file);
        $this->assertStringContainsString("'/api/v1/sku/validate'", $contents);
        $this->assertStringContainsString('validateSkuGates', $contents);
    }

    /** @test PythonWorkerClient no longer posts to dead /queue/audit path */
    public function test_queue_audit_does_not_use_dead_queue_path(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Services/PythonWorkerClient.php';
        $contents = file_get_contents($file);
        $this->assertStringNotContainsString("'/queue/audit'", $contents);
        $this->assertStringContainsString('auditRunForCategory', $contents);
    }

    /** @test decay_cron default worker URL uses port 8000 */
    public function test_decay_cron_default_worker_url_uses_port_8000(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/python/src/ai_audit/decay_cron.py';
        $contents = file_get_contents($file);
        $this->assertStringContainsString('http://localhost:8000', $contents);
        $this->assertStringNotContainsString('http://localhost:5000', $contents);
        $this->assertStringContainsString('/api/v1/brief/generate', $contents);
    }
}
