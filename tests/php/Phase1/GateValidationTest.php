<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 1.1 / 1.2
// SOURCE: openapi.yaml ValidationResponse, GOLDEN§3.1 — JSON root is ValidationResponse (no success/data envelope)

namespace Tests\Phase1;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1.1 — POST /api/v1/sku/{sku_id}/validate returns 200 all-pass for golden Hero SKU.
 * Phase 1.2 — Gate failures appear under top-level `gates` (OpenAPI), not under `data`.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.1–1.2
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 Gate Response Format
 * SOURCE: openapi.yaml /sku/{sku_id}/validate → ValidationResponse at root
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
        $this->assertArrayNotHasKey('data', $body, 'Validate must return OpenAPI body at root, not ResponseFormatter envelope');
        $this->assertArrayHasKey('gates', $body);
        $this->assertSame('pass', $body['status'] ?? null, 'Golden Hero SKU status must be pass');
    }

    /**
     * @test Phase 1.2 — failing gates appear under top-level `gates` with OpenAPI gate object shape
     * SOURCE: openapi.yaml ValidationResponse — failures are expressed per gate key, not only in a separate list
     */
    public function test_gate_failures_use_openapi_shape_not_envelope(): void
    {
        $token = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';

        // Intentionally invalid draft on golden Hero SKU to surface multiple blocking gates (G1, G2, G4, G5 depend on DB/tier)
        $ch = curl_init("{$this->baseUrl}/api/v1/sku/SKU-CABLE-001/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'action'         => 'save',
                'cluster_id'     => '__CIE_INVALID_CLUSTER_NOT_IN_MASTER__',
                'primary_intent' => '__invalid_intent_not_in_taxonomy__',
                'answer_block'   => 'x',
                'best_for'       => [],
                'not_for'        => [],
            ]),
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token", "Content-Type: application/json"],
        ]);
        $body = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertArrayNotHasKey('data', $body, 'Validate must not wrap payload in data envelope');
        $this->assertArrayHasKey('gates', $body, 'OpenAPI ValidationResponse requires top-level gates');
        $this->assertContains($code, [200, 400], 'Validate returns 200 (pending) or 400 (fail)');

        if (($body['status'] ?? '') === 'fail') {
            $failGates = array_filter(
                $body['gates'] ?? [],
                static fn ($g) => is_array($g) && ($g['status'] ?? '') === 'fail'
            );
            $this->assertNotEmpty($failGates, 'At least one gate must be fail when status is fail');
            foreach ($failGates as $gateKey => $g) {
                $this->assertIsString($gateKey);
                $this->assertArrayHasKey('error_code', $g);
                $this->assertArrayHasKey('user_message', $g);
            }
        }
    }

    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — all failures returned simultaneously.
     * SOURCE: CIE_v232_FINAL_Developer_Instruction.docx Audit API-01 — ≥3 intentional gate failures.
     * FIX: MF-02
     */
    public function test_returns_at_least_three_simultaneous_gate_failures(): void
    {
        $token = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';

        $ch = curl_init("{$this->baseUrl}/api/v1/sku/SKU-CABLE-001/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'action' => 'save',
                'tier' => 'hero',
                'cluster_id' => '__CIE_INVALID_CLUSTER_NOT_IN_MASTER__',
                'primary_intent' => 'compatibility',
                'secondary_intents' => ['installation'],
                'answer_block' => str_repeat('x', 100),
                'best_for' => [],
                'not_for' => ['outdoor use'],
                'expert_authority' => 'BS 7671 compliant',
            ]),
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        ]);
        $body = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertSame(400, $code, 'Invalid hero draft must return HTTP 400');
        $this->assertSame('fail', $body['status'] ?? null);

        $failGates = array_filter(
            $body['gates'] ?? [],
            static fn ($g) => is_array($g) && ($g['status'] ?? '') === 'fail'
        );
        $this->assertGreaterThanOrEqual(
            3,
            count($failGates),
            'Expected at least 3 simultaneous gate failures'
        );

        $codes = array_values(array_filter(array_map(
            static fn ($g) => is_array($g) ? ($g['error_code'] ?? null) : null,
            $failGates
        )));
        $this->assertContains('CIE_G1_INVALID_CLUSTER', $codes);
        $this->assertContains('CIE_G4_CHAR_LIMIT', $codes);
        $this->assertContains('CIE_G5_BESTFOR_COUNT', $codes);
    }

    /**
     * Kill tier validate — ENF§2.1 G6.1: any request is blocked; suspended gates N/A; OpenAPI shape at root.
     * SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §2.1 G6.1, openapi.yaml ValidationResponse
     */
    public function test_kill_tier_validate_returns_unwrapped_openapi_shape(): void
    {
        $token = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';
        $ch = curl_init("{$this->baseUrl}/api/v1/sku/SKU-CABLE-002/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['action' => 'save']),
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token", "Content-Type: application/json"],
        ]);
        $body = json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertArrayNotHasKey('data', $body);
        $this->assertArrayHasKey('gates', $body);
        $this->assertArrayHasKey('vector_check', $body);
        $this->assertContains($code, [200, 400], 'Kill validate: 400 when G6.1 blocks; 200 only if fixture SKU is not Kill tier');
        if (($body['status'] ?? '') === 'fail' && isset($body['gates']['G6_1_tier_lock'])) {
            $this->assertSame('fail', $body['gates']['G6_1_tier_lock']['status'] ?? null);
            $this->assertSame('CIE_G6_1_KILL_EDIT_BLOCKED', $body['gates']['G6_1_tier_lock']['error_code'] ?? null);
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — Kill: G6 required and must record PASS before G6.1 block. FIX: G6-03
            if (isset($body['gates']['G6_tier_tag'])) {
                $this->assertSame('pass', $body['gates']['G6_tier_tag']['status'] ?? null);
            }
        }
        foreach (['G3_secondary_intents', 'G4_answer_block', 'G5_best_not_for', 'G7_expert_authority'] as $gk) {
            if (isset($body['gates'][$gk])) {
                $this->assertSame(
                    'not_applicable',
                    $body['gates'][$gk]['status'] ?? null,
                    "Kill tier: {$gk} should be not_applicable when present"
                );
            }
        }
    }
}
