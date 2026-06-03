<?php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class PythonWorkerClient
{
    private $client;
    private $baseUrl;

    public function __construct()
    {
        // SOURCE: config/services.php — align with G4_VectorGate / uvicorn default port 8000
        $this->baseUrl = rtrim((string) config('services.python_worker.url', 'http://localhost:8000'), '/');
        
        $validateTimeout = max(
            60,
            (int) config('services.python_worker.validate_timeout_seconds', 120)
        );
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $validateTimeout,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * SOURCE: DECISION-011 — Python FastAPI is the sole gate engine; CMS delegates here.
     * SOURCE: openapi.yaml POST /api/v1/sku/validate — ValidationResponse at JSON root.
     *
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function validateSkuGates(array $payload): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            $svcToken = (string) config('services.python_worker.internal_service_token', '');
            if ($svcToken !== '') {
                $headers['x-service-token'] = $svcToken;
            }

            $response = $this->client->post('/api/v1/sku/validate', [
                'headers' => $headers,
                'json' => $payload,
            ]);

            $httpStatus = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true) ?? [];

            if ($httpStatus === 422) {
                Log::warning('Python validate returned 422', ['body' => $body]);

                return [
                    'http_status' => 422,
                    'body' => [
                        'status' => 'pending',
                        'gates' => [],
                        'vector_check' => [
                            'status' => 'pending',
                            'user_message' => 'Validation request could not be processed. Please try again.',
                        ],
                        'degraded_mode' => true,
                        'save_allowed' => true,
                        'publish_allowed' => false,
                    ],
                ];
            }

            if ($httpStatus >= 500) {
                Log::error("Python validate returned {$httpStatus}", ['body' => $body]);

                return [
                    'http_status' => 500,
                    'body' => [
                        'status' => 'pending',
                        'gates' => [],
                        'vector_check' => [
                            'status' => 'pending',
                            'user_message' => 'Validation service temporarily unavailable. Your changes are saved but publishing is paused.',
                        ],
                        'degraded_mode' => true,
                        'save_allowed' => true,
                        'publish_allowed' => false,
                    ],
                ];
            }

            return ['http_status' => $httpStatus, 'body' => $body];
        } catch (RequestException $e) {
            Log::error("Python validate request failed: {$e->getMessage()}", [
                'sku_id' => $payload['sku_id'] ?? null,
            ]);

            return [
                'http_status' => 503,
                'body' => [
                    'status' => 'pending',
                    'gates' => [],
                    'vector_check' => [
                        'status' => 'pending',
                        'user_message' => 'Validation service temporarily unavailable. Your changes are saved but publishing is paused.',
                    ],
                    'degraded_mode' => true,
                    'save_allowed' => true,
                    'publish_allowed' => false,
                ],
            ];
        }
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — Core API Endpoints
     * SOURCE: openapi.yaml POST /api/v1/sku/similarity — returns { status, message }.
     */
    public function validateVector(string $description, string $clusterId, ?string $skuId = null): array
    {
        try {
            $headers = [];
            $svcToken = (string) config('services.python_worker.internal_service_token', '');
            if ($svcToken !== '') {
                $headers['x-service-token'] = $svcToken;
            }
            $response = $this->client->post('/api/v1/sku/similarity', [
                'headers' => $headers,
                'json' => [
                    'description' => $description,
                    'cluster_id'  => $clusterId,
                ]
            ]);

            if ($response->getStatusCode() >= 500) {
                Log::warning("Python similarity returned {$response->getStatusCode()} (fail-soft → pending)", [
                    'body' => $response->getBody()->getContents()
                ]);
                return ['status' => 'pending', 'message' => null];
            }

            if ($response->getStatusCode() >= 400) {
                Log::warning("Python similarity returned {$response->getStatusCode()}", [
                    'body' => $response->getBody()->getContents()
                ]);
                return ['status' => 'fail', 'message' => 'Validation service error'];
            }

            $body = json_decode($response->getBody()->getContents(), true) ?? [];
            return [
                'status'  => $body['status'] ?? 'fail',
                'message' => $body['message'] ?? null,
            ];
        } catch (RequestException $e) {
            Log::error("Python similarity request failed (fail-soft → pending): {$e->getMessage()}", [
                'cluster_id' => $clusterId,
                'sku_id' => $skuId
            ]);
            return ['status' => 'pending', 'message' => null];
        }
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — POST /api/v1/audit/run (category-wide weekly audit).
     * SOURCE: openapi.yaml AuditRunResponse — async 202; quorum/run_status finalized in ai_audit_runs (weekly_service).
     */
    public function auditRunForCategory(string $runId, string $category): array
    {
        try {
            $response = $this->client->post('/api/v1/audit/run', [
                'json' => [
                    'category' => $category,
                    'run_id' => $runId,
                ],
            ]);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 202) {
                Log::warning('Python audit/run returned '.$response->getStatusCode(), [
                    'body' => $response->getBody()->getContents(),
                ]);

                return ['ok' => false, 'error' => 'Audit dispatch failed'];
            }

            $body = json_decode($response->getBody()->getContents(), true) ?? [];
            $body['ok'] = true;

            return $body;
        } catch (RequestException $e) {
            Log::error('auditRunForCategory request failed: '.$e->getMessage(), [
                'category' => $category,
                'run_id' => $runId,
            ]);

            return ['ok' => false, 'error' => 'Service unavailable'];
        }
    }

    /**
     * Legacy per-SKU entry point — routes to POST /api/v1/audit/run (dead /queue/audit removed).
     * Pass $category (e.g. cables, lamps) or use auditRunForCategory() directly.
     */
    public function queueAudit(int $skuId, ?string $category = null): array
    {
        if ($category === null || trim($category) === '') {
            Log::warning('queueAudit(skuId) without category — use auditRunForCategory(runId, category)', [
                'sku_id' => $skuId,
            ]);
            return ['queued' => false, 'error' => 'Category required; use auditRunForCategory'];
        }

        $runId = bin2hex(random_bytes(16));
        $result = $this->auditRunForCategory($runId, trim($category));
        if (!($result['ok'] ?? false)) {
            return ['queued' => false, 'error' => $result['error'] ?? 'Audit dispatch failed'];
        }

        return [
            'queued' => true,
            'audit_id' => $runId,
            'run_id' => $result['run_id'] ?? $runId,
        ];
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — POST /api/v1/brief/generate
     * Forwards to Python worker (which proxies to Laravel when CIE_CMS_URL is set) or accepts decay payloads directly.
     */
    public function queueBriefGeneration(int $skuId, string $title, ?string $category = null): array
    {
        try {
            $response = $this->client->post('/api/v1/brief/generate', [
                'json' => [
                    'sku_id' => (string) $skuId,
                    'failing_questions' => [],
                ],
            ]);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201 && $response->getStatusCode() !== 202) {
                Log::warning("Python brief/generate returned {$response->getStatusCode()}");
                return ['queued' => false, 'error' => 'Brief generation failed'];
            }

            return json_decode($response->getBody()->getContents(), true) ?? [
                'queued' => true,
                'brief_id' => bin2hex(random_bytes(8)),
            ];
        } catch (RequestException $e) {
            Log::error("Failed to call brief/generate: {$e->getMessage()}", ['sku_id' => $skuId]);
            return ['queued' => false, 'error' => 'Service unavailable'];
        }
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — GET /api/v1/audit/results/{category}
     */
    public function getAuditResult(string $category, ?string $runId = null): array
    {
        try {
            $path = '/api/v1/audit/results/'.rawurlencode($category);
            if ($runId !== null && $runId !== '') {
                $path .= '?run_id='.rawurlencode($runId);
            }
            $response = $this->client->get($path);

            if ($response->getStatusCode() === 404) {
                return ['status' => 'pending'];
            }

            if ($response->getStatusCode() >= 400) {
                return ['status' => 'error'];
            }

            return json_decode($response->getBody()->getContents(), true) ?? ['status' => 'pending'];
        } catch (RequestException $e) {
            Log::error("Failed to fetch audit results: {$e->getMessage()}");
            return ['status' => 'error'];
        }
    }

    /**
     * Health check
     */
    public function health(): bool
    {
        try {
            $response = $this->client->get('/api/');
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::warning("Python worker health check failed: {$e->getMessage()}");
            return false;
        }
    }
}
