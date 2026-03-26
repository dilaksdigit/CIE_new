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
        $this->baseUrl = env('PYTHON_API_URL', 'http://localhost:5000');
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — Core API Endpoints
     * SOURCE: openapi.yaml POST /api/v1/sku/similarity — returns { status, message }.
     */
    public function validateVector(string $description, string $clusterId, ?string $skuId = null): array
    {
        try {
            $response = $this->client->post('/api/v1/sku/similarity', [
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
     * Queue an AI audit job (legacy per-SKU path; retained for callers not migrated to audit/run).
     */
    public function queueAudit(int $skuId): array
    {
        try {
            $response = $this->client->post('/queue/audit', [
                'json' => ['sku_id' => $skuId]
            ]);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 202) {
                Log::warning("Python audit queue returned {$response->getStatusCode()}");
                return ['queued' => false, 'error' => 'Queue failed'];
            }

            return json_decode($response->getBody()->getContents(), true) ?? [
                'queued' => true,
                'audit_id' => bin2hex(random_bytes(8))
            ];
        } catch (RequestException $e) {
            Log::error("Failed to queue audit: {$e->getMessage()}", ['sku_id' => $skuId]);
            return ['queued' => false, 'error' => 'Service unavailable'];
        }
    }

    /**
     * Queue a brief generation job
     */
    public function queueBriefGeneration(int $skuId, string $title, ?string $category = null): array
    {
        try {
            $response = $this->client->post('/queue/brief-generation', [
                'json' => [
                    'sku_id' => $skuId,
                    'title' => $title,
                    'category' => $category
                ]
            ]);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 202) {
                Log::warning("Python brief queue returned {$response->getStatusCode()}");
                return ['queued' => false, 'error' => 'Queue failed'];
            }

            return json_decode($response->getBody()->getContents(), true) ?? [
                'queued' => true,
                'brief_id' => bin2hex(random_bytes(8))
            ];
        } catch (RequestException $e) {
            Log::error("Failed to queue brief generation: {$e->getMessage()}", ['sku_id' => $skuId]);
            return ['queued' => false, 'error' => 'Service unavailable'];
        }
    }

    /**
     * Get audit results (polling)
     */
    public function getAuditResult(string $auditId): array
    {
        try {
            $response = $this->client->get("/audits/{$auditId}");

            if ($response->getStatusCode() === 404) {
                return ['status' => 'pending'];
            }

            if ($response->getStatusCode() >= 400) {
                return ['status' => 'error'];
            }

            return json_decode($response->getBody()->getContents(), true) ?? ['status' => 'pending'];
        } catch (RequestException $e) {
            Log::error("Failed to fetch audit result: {$e->getMessage()}");
            return ['status' => 'error'];
        }
    }

    /**
     * Health check
     */
    public function health(): bool
    {
        try {
            $response = $this->client->get('/health');
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::warning("Python worker health check failed: {$e->getMessage()}");
            return false;
        }
    }
}
