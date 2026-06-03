<?php
// SOURCE: Production Roadmap P6.2 — RBAC matrix vs protected routes
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Live matrix: set APP_URL and role tokens (TEST_TOKEN_*).
 * Static matrix always runs without HTTP.
 */
class RbacEndpointMatrixTest extends TestCase
{
    private string $baseUrl;

    /** @var array<int, array{method:string,path:string,roles:array<string>,deny_roles:array<string>}> */
    private const MATRIX = [
        ['method' => 'GET', 'path' => '/api/admin/users', 'roles' => ['ADMIN'], 'deny_roles' => ['CONTENT_EDITOR', 'CONTENT_LEAD']],
        ['method' => 'GET', 'path' => '/api/v1/admin/bulk-ops/summary', 'roles' => ['ADMIN'], 'deny_roles' => ['CONTENT_EDITOR']],
        ['method' => 'PUT', 'path' => '/api/v1/config', 'roles' => ['ADMIN'], 'deny_roles' => ['CONTENT_EDITOR', 'CONTENT_LEAD']],
        ['method' => 'GET', 'path' => '/api/v1/gsc/status', 'roles' => ['ADMIN'], 'deny_roles' => ['CONTENT_EDITOR']],
        ['method' => 'GET', 'path' => '/api/v1/queue/today', 'roles' => ['CONTENT_EDITOR'], 'deny_roles' => []],
        ['method' => 'POST', 'path' => '/api/v1/audit-results/weekly-scores', 'roles' => ['CONTENT_LEAD', 'SEO_GOVERNOR'], 'deny_roles' => ['CONTENT_EDITOR']],
        ['method' => 'POST', 'path' => '/api/v1/erp/sync', 'roles' => ['ADMIN', 'FINANCE'], 'deny_roles' => ['CONTENT_EDITOR']],
    ];

    protected function setUp(): void
    {
        $this->baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
    }

    public function test_rbac_matrix_definition_is_non_empty(): void
    {
        $this->assertNotEmpty(self::MATRIX);
    }

    /**
     * @dataProvider liveRbacCases
     */
    public function test_live_rbac_expectations(string $role, string $method, string $path, bool $shouldAllow): void
    {
        if ($this->baseUrl === '') {
            $this->markTestSkipped('Set APP_URL for live RBAC matrix');
        }
        $token = getenv('TEST_TOKEN_' . $role) ?: '';
        if ($token === '') {
            $this->markTestSkipped("Set TEST_TOKEN_{$role}");
        }
        $code = $this->request($method, $path, $token);
        if ($shouldAllow) {
            $this->assertNotSame(403, $code, "{$role} should access {$method} {$path}");
        } else {
            $this->assertSame(403, $code, "{$role} must be denied {$method} {$path}");
        }
    }

    public static function liveRbacCases(): array
    {
        $cases = [];
        foreach (self::MATRIX as $row) {
            foreach ($row['roles'] as $role) {
                $cases[] = [$role, $row['method'], $row['path'], true];
            }
            foreach ($row['deny_roles'] as $role) {
                $cases[] = [$role, $row['method'], $row['path'], false];
            }
        }
        return $cases;
    }

    public function test_public_register_disabled(): void
    {
        if ($this->baseUrl === '') {
            $this->markTestSkipped('Set APP_URL');
        }
        $code = $this->request('POST', '/api/v1/auth/register', '', [
            'name' => 'Test User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $this->assertSame(403, $code);
    }

    private function request(string $method, string $path, string $token, ?array $body = null): int
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Accept: application/json'];
        if ($token !== '') {
            $headers[] = "Authorization: Bearer {$token}";
        }
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
}
