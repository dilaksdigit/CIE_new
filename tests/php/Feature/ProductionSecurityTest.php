<?php
// SOURCE: P0 — credential exposure / debug mode in production

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ProductionSecurityTest extends TestCase
{
    /** @test config/app.php forces debug off for production and staging */
    public function test_config_debug_disabled_for_production_env(): void
    {
        $configPath = dirname(__DIR__, 3) . '/config/app.php';
        $contents = file_get_contents($configPath);
        $this->assertStringContainsString("['production', 'staging']", $contents);
        $this->assertStringContainsString('? false', $contents);
        $this->assertStringNotContainsString("env('APP_DEBUG', true)", $contents);
    }

    /** @test exception handler masks errors in production */
    public function test_exception_handler_masks_production_errors(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Exceptions/Handler.php';
        $contents = file_get_contents($file);
        $this->assertStringContainsString("environment(['production', 'staging'])", $contents);
        $this->assertStringNotContainsString('getTraceAsString', $contents);
    }

    /** @test docker-compose defaults do not enable debug */
    public function test_docker_compose_defaults_safe(): void
    {
        $compose = file_get_contents(dirname(__DIR__, 3) . '/docker-compose.yml');
        $this->assertStringContainsString('APP_DEBUG=false', $compose);
        $this->assertStringNotContainsString('APP_DEBUG=true', $compose);
    }

    /** @test pre-deploy script enforces security checks */
    public function test_pre_deploy_check_script_exists(): void
    {
        $sh = dirname(__DIR__, 3) . '/scripts/pre_deploy_check.sh';
        $py = dirname(__DIR__, 3) . '/scripts/scan_tracked_secrets.py';
        $this->assertFileExists($sh);
        $this->assertFileExists($py);
        $contents = file_get_contents($sh);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        $this->assertStringContainsString('scan_tracked_secrets.py', $contents);
    }
}
