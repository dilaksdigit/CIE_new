<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 0.4

namespace Tests\Phase0;

use PHPUnit\Framework\TestCase;

/**
 * Phase 0.4 — RBAC: content_writer cannot access admin endpoints.
 *              kpi_conductor cannot edit content. Must return 403.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.4
 */
class RBACTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    }

    /** @test Phase 0.4 — content_writer attempting admin endpoint returns 403 */
    public function test_writer_cannot_access_admin_endpoint(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.4
        // "content_writer cannot access admin endpoints — must return 403"
        $token = $this->loginAs('content_writer');
        $response = $this->httpGet('/api/admin/users', $token);
        $this->assertSame(403, $response['code'], 'Writer must receive 403 on admin endpoint');
    }

    /** @test Phase 0.4 — kpi_conductor attempting content edit returns 403 */
    public function test_reviewer_cannot_edit_content(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.4
        // "kpi_conductor cannot edit content — must return 403"
        $token = $this->loginAs('kpi_conductor');
        $response = $this->httpPost('/api/v1/sku/SKU-CABLE-001/content', $token, ['title' => 'test']);
        $this->assertSame(403, $response['code'], 'KPI conductor must receive 403 on content edit');
    }

    private function loginAs(string $role): string
    {
        // Resolves test credentials from environment — credentials not hard-coded per CLAUDE.md §19
        return getenv('TEST_TOKEN_' . strtoupper($role)) ?: 'test-token-placeholder';
    }

    private function httpGet(string $path, string $token): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]);
        curl_exec($ch);
        return ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
    }

    private function httpPost(string $path, string $token, array $body): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"]]);
        curl_exec($ch);
        return ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
    }
}
