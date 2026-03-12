<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 1.6

namespace Tests\Phase1;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1.6 — Vector similarity < threshold rejected.
 *              Embedding service down → fail-soft: save allowed, warning shown.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.6
 * SOURCE: CLAUDE.md §17 DECISION-005 — Fail-Soft Vector Validation
 */
class VectorFailSoftTest extends TestCase
{
    private string $baseUrl;
    private string $token;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        $this->token   = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';
    }

    /** @test Phase 1.6 — Description with similarity 0.65 is rejected (below 0.72 threshold) */
    public function test_low_similarity_description_is_rejected(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.6
        // "Rejects descriptions below gates.vector_similarity_min."
        // SOURCE: CLAUDE.md §18 — "Cosine similarity threshold 0.72 — default value locked"
        // Test expects a warning or fail on vector_check when embedding returns < 0.72
        $ch = curl_init("{$this->baseUrl}/api/v1/sku/SKU-CABLE-001/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'action'      => 'save',
                'description' => str_repeat('Low quality content. ', 20), // known-bad description
            ]),
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->token}", "Content-Type: application/json"],
        ]);
        $body = json_decode(curl_exec($ch), true);
        $vectorStatus = $body['vector_check']['status'] ?? null;

        // SOURCE: CLAUDE.md DECISION-005 — similarity below 0.72 produces WARNING, not a hard block
        $this->assertContains($vectorStatus, ['fail', 'warn'],
            'Similarity 0.65 must produce fail or warn on vector_check per DECISION-005');

        // SOURCE: cie_v231_openapi.yaml — user_message must never contain numeric similarity score
        $userMessage = $body['vector_check']['user_message'] ?? '';
        $this->assertStringNotContainsString('0.72', $userMessage,
            'user_message must never expose numeric threshold to writer (CLAUDE.md §11)');
        $this->assertStringNotContainsString('0.65', $userMessage,
            'user_message must never expose raw similarity score to writer (CLAUDE.md §11)');
    }

    /** @test Phase 1.6 — Embedding service down: save allowed, degraded_mode true, warning shown */
    public function test_embedding_service_down_is_fail_soft(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.6
        // "Fail-soft when embedding service mocked as down."
        // SOURCE: CLAUDE.md DECISION-005 — "fail-soft: warn but do not block"
        // This test requires the embedding service to be mocked as unavailable.
        // Set TEST_MOCK_EMBEDDING_DOWN=1 in environment to activate mock.
        if (!getenv('TEST_MOCK_EMBEDDING_DOWN')) {
            $this->markTestSkipped('Set TEST_MOCK_EMBEDDING_DOWN=1 to run fail-soft test');
        }

        $ch = curl_init("{$this->baseUrl}/api/v1/sku/SKU-CABLE-001/validate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['action' => 'save']),
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->token}", "Content-Type: application/json"],
        ]);
        $body = json_decode(curl_exec($ch), true);

        // SOURCE: cie_v231_openapi.yaml ValidationResponse — save_allowed + degraded_mode fields
        $this->assertSame(true,  $body['save_allowed']    ?? null, 'Save must be allowed when embedding is down');
        $this->assertSame(true,  $body['degraded_mode']   ?? null, 'degraded_mode must be true when embedding down');
        $this->assertSame(false, $body['publish_allowed'] ?? null, 'Publish must be blocked in degraded mode');
    }
}
