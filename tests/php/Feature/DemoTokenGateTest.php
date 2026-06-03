<?php
// SOURCE: P0 security — demo-token must not work in production

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class DemoTokenGateTest extends TestCase
{
    /** @test AuthMiddleware gates demo-token behind env check */
    public function test_auth_middleware_contains_demo_token_env_gate(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Middleware/AuthMiddleware.php';
        $contents = file_get_contents($file);
        $this->assertStringContainsString('ALLOW_DEMO_TOKEN', $contents);
        $this->assertStringContainsString("'local', 'development', 'testing'", $contents);
        $this->assertStringContainsString("'error' => 'Unauthenticated'", $contents);
    }

    /** @test .env.example documents ALLOW_DEMO_TOKEN default false */
    public function test_env_example_documents_allow_demo_token(): void
    {
        $envExample = dirname(__DIR__, 3) . '/.env.example';
        $contents = file_get_contents($envExample);
        $this->assertStringContainsString('ALLOW_DEMO_TOKEN=false', $contents);
    }
}
