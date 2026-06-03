<?php
// SOURCE: Production Roadmap P2.2 — admin user management routes
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Contract test: /api/admin/users requires ADMIN (RBAC Phase 0.4).
 * Run with live APP_URL + tokens when stack is up.
 */
class AdminUsersEndpointTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
    }

    public function test_admin_users_requires_auth(): void
    {
        $code = $this->httpGet('/api/admin/users', '');
        $this->assertContains($code, [401, 403], 'Unauthenticated request must not succeed');
    }

    public function test_writer_token_denied_on_admin_users(): void
    {
        $token = getenv('TEST_TOKEN_CONTENT_WRITER') ?: getenv('TEST_TOKEN_CONTENT_EDITOR') ?: '';
        if ($token === '') {
            $this->markTestSkipped('Set TEST_TOKEN_CONTENT_WRITER for live RBAC check');
        }
        $code = $this->httpGet('/api/admin/users', $token);
        $this->assertSame(403, $code);
    }

    private function httpGet(string $path, string $token): int
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = "Authorization: Bearer $token";
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
}
