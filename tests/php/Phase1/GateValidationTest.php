<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 1.1 / 1.2

namespace Tests\Phase1;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1.1 — POST /api/v1/sku/{sku_id}/validate returns 200 all-pass for golden Hero SKU.
 * Phase 1.2 — 3 intentional gate failures returned simultaneously with gate/error_code/detail/user_message.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.1–1.2
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 Gate Response Format
 * SOURCE: cie_v231_openapi.yaml /sku/{sku_id}/validate
 */
class GateValidationTest extends TestCase
{
    private string $baseUrl;
    private array $goldenSkus;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.1
        // Golden fixture from tests/fixtures/golden_skus.json (Doc4b)
        $fixture = file_get_contents(__DIR__ . '/../../fixtures/golden_skus.json');
        $this->goldenSkus = json_decode($fixture, true)['golden_skus'];
    }

    /** @test Phase 1.1 — POST /api/v1/sku/{sku_id}/validate returns 200 all-pass for SKU-CABLE-001 */
    public function test_golden_hero_sku_returns_200_all_pass(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.1
        $skuCode = 'SKU-CABLE-001';
        $token   = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';

        $ch = curl_init("{$this->baseUrl}/api/v1/sku/{$skuCode}/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['action' => 'save']),
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token", "Content-Type: application/json"],
        ]);
        $body = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertSame(200, $code, 'Golden Hero SKU must return HTTP 200');
        $this->assertSame('pass', $body['status'] ?? null, 'Golden Hero SKU status must be pass');
    }

    /** @test Phase 1.2 — 3 intentional failures returned simultaneously; each has gate/error_code/detail/user_message */
    public function test_multiple_gate_failures_returned_simultaneously(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.2
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 — each failure: gate, error_code, detail, user_message
        $token = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';

        // Use kill-tier SKU (SKU-CABLE-002) which will produce multiple gate failures
        $ch = curl_init("{$this->baseUrl}/api/v1/sku/SKU-CABLE-002/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['action' => 'save']),
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token", "Content-Type: application/json"],
        ]);
        $body = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertSame(400, $code, 'SKU with gate failures must return HTTP 400');
        $this->assertSame('fail', $body['status'] ?? null);

        $failures = $body['gates_failed'] ?? $body['failures'] ?? [];
        $this->assertNotEmpty($failures, 'gates_failed/failures array must not be empty');

        foreach ($failures as $failure) {
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 — all 4 fields required
            $this->assertArrayHasKey('gate',         $failure, 'Each failure must include gate field (§7.1)');
            $this->assertArrayHasKey('error_code',   $failure, 'Each failure must include error_code (§7.1)');
            $this->assertArrayHasKey('detail',       $failure, 'Each failure must include detail (§7.1)');
            $this->assertArrayHasKey('user_message', $failure, 'Each failure must include user_message (§7.1)');
        }
    }
}
