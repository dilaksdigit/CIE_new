<?php
// SOURCE: GAP-ROUTES-01 — PHP routes must be documented in cie_v231_openapi.yaml

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class OpenApiRouteParityTest extends TestCase
{
    /** @test canonical OpenAPI documents every route in api.php */
    public function test_php_routes_documented_in_canonical_openapi(): void
    {
        $root = dirname(__DIR__, 3);
        $script = $root . '/scripts/verify_openapi_route_parity.py';
        $this->assertFileExists($script);

        $cmd = escapeshellarg(PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3')
            . ' ' . escapeshellarg($script);
        exec($cmd . ' 2>&1', $output, $code);
        $this->assertSame(
            0,
            $code,
            "OpenAPI route parity failed:\n" . implode("\n", $output)
        );
    }

    /** @test locked contract file exists and lists integration callbacks */
    public function test_canonical_openapi_includes_channel_callbacks(): void
    {
        $yaml = file_get_contents(dirname(__DIR__, 3) . '/cie_v231_openapi.yaml');
        $this->assertStringContainsString('/skus/{sku_code}/channel-deployed:', $yaml);
        $this->assertStringContainsString('/skus/{sku_code}/channel-failed:', $yaml);
        $this->assertStringContainsString('/sku/{sku_id}/suggest:', $yaml);
        $this->assertStringContainsString('/admin/sync-failed:', $yaml);
    }
}
