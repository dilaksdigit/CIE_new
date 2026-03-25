<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 11
// SOURCE: CLAUDE.md Section 4 (Shopify PRIMARY, GMC SECONDARY, Amazon DEFERRED), Section 10 (rate limits), Section 19 (env only).
// SOURCE: Task E2 | CLAUDE.md Section 10 | CIE_Master_Developer_Build_Spec.docx Section 9.5

namespace App\Services;

use App\Models\Sku;
use App\Models\AuditLog;
use App\Services\ShopifyRateLimiter;
use App\Exceptions\ShopifyRateLimitException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Deploys SKU content to channels via N8N webhooks.
 * Shopify: 2 req/s (enforced in N8N workflow). GMC: 50 req/min.
 * Amazon: NOT implemented (DEFERRED per CLAUDE.md Section 4).
 */
class ChannelDeployService
{
    /** Exponential backoff delays (seconds) on N8N 429. SOURCE: Task A2 spec. */
    private const BACKOFF_SECONDS = [30, 120, 600]; // 30s → 2m → 10m
    // SOURCE: CLAUDE.md §10 — GMC Content API 50 calls/min.
    private static int $gmcWindowStartedAt = 0;
    private static int $gmcCallsInWindow = 0;

    /**
     * Deploy to Shopify via N8N webhook. HMAC-signed payload. On 429: retry with backoff, log each retry to audit_log.
     * Returns: [ channel => 'shopify', status => 'deployed'|'failed', shopify_product_id => ?, deployed_at => ? ]
     */
    public function deployToShopify(int|string $skuId): array
    {
        $sku = Sku::find((string) $skuId);
        if (!$sku) {
            return ['channel' => 'shopify', 'status' => 'failed', 'shopify_product_id' => null, 'deployed_at' => null];
        }

        $baseUrl = rtrim(env('N8N_BASE_URL', ''), '/');
        $secret = env('N8N_WEBHOOK_SECRET', '');
        if ($baseUrl === '' || $secret === '') {
            Log::warning('ChannelDeploy: N8N_BASE_URL or N8N_WEBHOOK_SECRET not set');
            return ['channel' => 'shopify', 'status' => 'failed', 'shopify_product_id' => null, 'deployed_at' => null];
        }

        $payload = $this->buildDeployPayload($sku);
        $url = $baseUrl . '/webhook/shopify-deploy';
        $skuIdStr = (string) $skuId;

        $auditLogger = function (string $event, array $payload) {
            if ($event === 'shopify_retry') {
                $this->logRetryToAudit(
                    $payload['sku_id'],
                    'shopify',
                    $payload['status_code'] ?? 429,
                    $payload['attempt'],
                    $payload['wait_seconds']
                );
            } else {
                try {
                    AuditLog::create([
                        'entity_type' => 'sku_publish',
                        'entity_id'   => (string) ($payload['sku_id'] ?? ''),
                        'action'      => $event,
                        'field_name'  => 'shopify',
                        'old_value'   => null,
                        'new_value'   => json_encode($payload),
                        'actor_id'    => 'SYSTEM',
                        'actor_role'  => 'system',
                        'timestamp'   => now(),
                        'user_id'     => 'SYSTEM',
                        'ip_address'  => null,
                        'user_agent'  => null,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('ChannelDeploy: audit_log ' . $event . ' failed: ' . $e->getMessage());
                }
            }
        };

        $cronQueuer = function (string $queuedSkuId) {
            try {
                AuditLog::create([
                    'entity_type' => 'sku_publish',
                    'entity_id'   => $queuedSkuId,
                    'action'      => 'shopify_deploy_queued_cron',
                    'field_name'  => 'shopify',
                    'old_value'   => null,
                    'new_value'   => json_encode(['reason' => '500/503 — queued for next cron window']),
                    'actor_id'    => 'SYSTEM',
                    'actor_role'  => 'system',
                    'timestamp'   => now(),
                    'user_id'     => 'SYSTEM',
                    'ip_address'  => null,
                    'user_agent'  => null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ChannelDeploy: audit_log shopify_deploy_queued_cron failed: ' . $e->getMessage());
            }
        };

        try {
            $limiter = new ShopifyRateLimiter();
            $result = $limiter->callWithRetry(
                fn () => $this->postWithHmac($url, $payload, $secret),
                $skuIdStr,
                $auditLogger,
                $cronQueuer
            );

            $statusCode = (int) ($result['status_code'] ?? 0);
            // SOURCE: CIE_Integration_Specification.pdf §2.5 — Auth failure: no retry, immediate alert
            if (in_array($statusCode, [401, 403], true)) {
                $this->alertAdmin("Channel deploy auth failure (shopify): HTTP {$statusCode}. Credential rotation required.");
                $this->logDeployFailure($skuIdStr, 'shopify', "auth_failure_{$statusCode}", false);
                return [
                    'channel'            => 'shopify',
                    'status'             => 'failed',
                    'shopify_product_id' => null,
                    'deployed_at'        => null,
                ];
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                $body = $result['body'] ?? [];
                return [
                    'channel'            => 'shopify',
                    'status'             => $body['status'] ?? 'deployed',
                    'shopify_product_id' => $body['shopify_product_id'] ?? null,
                    'deployed_at'        => $body['deployed_at'] ?? now()->toIso8601String(),
                ];
            }

            if (isset($result['status']) && $result['status'] === 'failed') {
                if (str_contains((string) ($result['reason'] ?? ''), 'auth failure')) {
                    $this->alertAdmin('Channel deploy auth failure (shopify): credential rotation required.');
                    $this->logDeployFailure($skuIdStr, 'shopify', (string) ($result['reason'] ?? 'auth_failure'), false);
                }
                Log::error('ChannelDeploy: Shopify webhook failed', [
                    'sku_id' => $skuIdStr,
                    'reason' => $result['reason'] ?? 'unknown',
                ]);
                return [
                    'channel'            => 'shopify',
                    'status'             => 'failed',
                    'shopify_product_id' => null,
                    'deployed_at'        => null,
                ];
            }
        } catch (ShopifyRateLimitException $e) {
            Log::error('ChannelDeploy: Shopify rate limit exceeded after retries', [
                'sku_id' => $skuIdStr,
                'message' => $e->getMessage(),
            ]);
            return [
                'channel'            => 'shopify',
                'status'             => 'failed',
                'shopify_product_id' => null,
                'deployed_at'        => null,
            ];
        }

        return [
            'channel'            => 'shopify',
            'status'             => 'failed',
            'shopify_product_id' => null,
            'deployed_at'        => null,
        ];
    }

    /**
     * Deploy to GMC via N8N webhook. Same HMAC pattern. GMC 50 req/min enforced in N8N.
     * Returns: [ channel => 'gmc', status => 'deployed'|'failed', deployed_at => ? ]
     */
    public function deployToGMC(int|string $skuId): array
    {
        $sku = Sku::find((string) $skuId);
        if (!$sku) {
            return ['channel' => 'gmc', 'status' => 'failed', 'deployed_at' => null];
        }

        $baseUrl = rtrim(env('N8N_BASE_URL', ''), '/');
        $secret = env('N8N_WEBHOOK_SECRET', '');
        if ($baseUrl === '' || $secret === '') {
            Log::warning('ChannelDeploy: N8N_BASE_URL or N8N_WEBHOOK_SECRET not set');
            return ['channel' => 'gmc', 'status' => 'failed', 'deployed_at' => null];
        }

        $payload = $this->buildDeployPayload($sku);
        $url = $baseUrl . '/webhook/gmc-deploy';
        $attempt = 0;

        while (true) {
            $this->enforceGmcRateLimit();
            $response = $this->postWithHmac($url, $payload, $secret);
            $statusCode = $response['status_code'] ?? 0;

            // SOURCE: CIE_Integration_Specification.pdf §2.5 — Auth failure: no retry, immediate alert
            if (in_array((int) $statusCode, [401, 403], true)) {
                $this->alertAdmin("Channel deploy auth failure (gmc): HTTP {$statusCode}. Credential rotation required.");
                $this->logDeployFailure((string) $skuId, 'gmc', "auth_failure_{$statusCode}", false);
                return ['channel' => 'gmc', 'status' => 'failed', 'deployed_at' => null];
            }

            if ($statusCode === 200 || $statusCode === 201) {
                $body = $response['body'] ?? [];
                return [
                    'channel'     => 'gmc',
                    'status'      => $body['status'] ?? 'deployed',
                    'deployed_at' => $body['deployed_at'] ?? now()->toIso8601String(),
                ];
            }

            if ($statusCode === 429 && $attempt < count(self::BACKOFF_SECONDS)) {
                // SOURCE: CIE_Integration_Specification.pdf §2.5
                // FIX: N8N-03 — respect Retry-After header, queue/retry after delay.
                $headers = $response['headers'] ?? [];
                $retryAfterRaw = $headers['Retry-After'] ?? $headers['retry-after'] ?? null;
                if (is_array($retryAfterRaw)) {
                    $retryAfterRaw = $retryAfterRaw[0] ?? null;
                }
                $delay = (is_string($retryAfterRaw) && is_numeric(trim($retryAfterRaw)))
                    ? max(1, (int) trim($retryAfterRaw))
                    : self::BACKOFF_SECONDS[$attempt];
                $this->logRetryToAudit($skuId, 'gmc', 429, $attempt + 1, $delay);
                sleep($delay);
                $attempt++;
                continue;
            }

            if ($statusCode === 429) {
                $headers = $response['headers'] ?? [];
                $retryAfterRaw = $headers['Retry-After'] ?? $headers['retry-after'] ?? null;
                if (is_array($retryAfterRaw)) {
                    $retryAfterRaw = $retryAfterRaw[0] ?? null;
                }
                $retryAfter = (is_string($retryAfterRaw) && is_numeric(trim($retryAfterRaw)))
                    ? max(1, (int) trim($retryAfterRaw))
                    : 60;
                Log::info("GMC 429: queuing for retry after {$retryAfter}s", ['sku_id' => $skuId]);
                return ['channel' => 'gmc', 'status' => 'queued_for_retry', 'retry_after' => $retryAfter, 'deployed_at' => null];
            }

            Log::error('ChannelDeploy: GMC webhook failed', [
                'sku_id' => $skuId,
                'status_code' => $statusCode,
                'attempt' => $attempt + 1,
            ]);
            return ['channel' => 'gmc', 'status' => 'failed', 'deployed_at' => null];
        }
    }

    /**
     * Run channel deploys after publish; skips channels for which tier is not eligible (Section 8.3).
     * Returns array of channel results in order: [ shopifyResult, gmcResult ].
     */
    public function deployAfterPublish(Sku $sku): array
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3
        $tier = $sku->tier instanceof \App\Enums\TierType
            ? $sku->tier->value
            : strtolower(trim((string) $sku->tier));
        $channelTierRulesService = new ChannelTierRulesService();
        $decisions = $channelTierRulesService->getAllChannelDecisions($tier);
        $skuId = (string) $sku->id;
        $entityId = (string) ($sku->sku_id ?? $sku->id);
        $results = [];

        if ($decisions['shopify'] === 'SKIP') {
            $this->logChannelDeploySkipped($entityId, 'shopify', $tier);
            $results[] = ['channel' => 'shopify', 'status' => 'skipped', 'shopify_product_id' => null, 'deployed_at' => null];
        } else {
            $results[] = $this->deployToShopify($skuId);
        }

        if ($decisions['gmc'] === 'SKIP') {
            $this->logChannelDeploySkipped($entityId, 'gmc', $tier);
            $results[] = ['channel' => 'gmc', 'status' => 'skipped', 'deployed_at' => null];
        } else {
            $results[] = $this->deployToGMC($skuId);
        }

        return $results;
    }

    /** Log channel_deploy_skipped to audit_log (Section 8.3). */
    private function logChannelDeploySkipped(string $entityId, string $channel, string $tier): void
    {
        try {
            AuditLog::create([
                'entity_type' => 'sku',
                'entity_id'   => $entityId,
                'action'      => 'channel_deploy_skipped',
                'actor_id'    => null,
                'actor_role'  => 'SYSTEM',
                'field_name'  => $channel,
                'old_value'   => null,
                'new_value'   => json_encode([
                    'tier'     => $tier,
                    'decision' => 'SKIP',
                    'reason'   => "Tier {$tier} is not eligible for {$channel} per Section 8.3",
                ]),
                'timestamp'   => now(),
                'user_id'     => 'SYSTEM',
                'ip_address'  => null,
                'user_agent'  => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChannelDeploy: audit_log channel_deploy_skipped failed: ' . $e->getMessage());
        }
    }

    /**
     * Build payload for N8N: sku_id, shopify_product_id, title, meta_title, meta_description, answer_block, best_for, not_for, faq, json_ld, alt_text.
     * All from Sku; secrets from env only (CLAUDE.md Section 19).
     */
    private function buildDeployPayload(Sku $sku): array
    {
        $faq = $sku->faq_data;
        if (is_string($faq)) {
            $faq = json_decode($faq, true);
        }
        $faq = is_array($faq) ? $faq : [];

        $jsonLd = '';
        if (class_exists(\App\Utils\JsonLdRenderer::class)) {
            $jsonLd = \App\Utils\JsonLdRenderer::renderCieJsonld($sku);
        }

        return [
            'sku_id'               => (string) $sku->id,
            'shopify_product_id'   => (string) ($sku->shopify_product_id ?? $sku->sku_code ?? $sku->id),
            'title'                => (string) ($sku->title ?? ''),
            'meta_title'           => (string) ($sku->meta_title ?? $sku->title ?? ''),
            'meta_description'     => (string) ($sku->short_description ?? $sku->long_description ?? ''),
            'answer_block'         => (string) ($sku->ai_answer_block ?? $sku->short_description ?? ''),
            'best_for'             => $sku->best_for,
            'not_for'              => $sku->not_for,
            'faq'                  => $faq,
            'json_ld'              => $jsonLd,
            'alt_text'             => (string) ($sku->alt_text ?? ''),
        ];
    }

    private function postWithHmac(string $url, array $payload, string $secret): array
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'X-N8N-Signature'   => $signature,
            ])->timeout(60)->post($url, $payload);

            return [
                'status_code' => $response->status(),
                'body'        => $response->json() ?? [],
                'headers'     => $response->headers(),
            ];
        } catch (\Throwable $e) {
            Log::warning('ChannelDeploy: N8N request failed: ' . $e->getMessage());
            return ['status_code' => 0, 'body' => []];
        }
    }

    /**
     * SOURCE: CLAUDE.md §10 — GMC max 50 calls per rolling minute.
     */
    private function enforceGmcRateLimit(): void
    {
        $now = time();
        if (self::$gmcWindowStartedAt === 0 || ($now - self::$gmcWindowStartedAt) >= 60) {
            self::$gmcWindowStartedAt = $now;
            self::$gmcCallsInWindow = 0;
        }
        if (self::$gmcCallsInWindow >= 50) {
            $sleepSeconds = 60 - ($now - self::$gmcWindowStartedAt);
            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
            self::$gmcWindowStartedAt = time();
            self::$gmcCallsInWindow = 0;
        }
        self::$gmcCallsInWindow++;
    }

    /** Log each 429 retry to audit_log (INSERT only). */
    private function logRetryToAudit(int|string $skuId, string $channel, int $httpCode, int $attempt, int $delaySeconds): void
    {
        try {
            AuditLog::create([
                'entity_type' => 'sku_publish',
                'entity_id'   => (string) $skuId,
                'action'      => $channel === 'shopify' ? 'shopify_retry' : 'n8n_retry',
                'field_name'  => $channel,
                'old_value'   => null,
                'new_value'   => json_encode([
                    'http_code' => $httpCode,
                    'attempt'   => $attempt,
                    'delay_seconds' => $delaySeconds,
                ]),
                'actor_id'    => 'SYSTEM',
                'actor_role'  => 'system',
                'timestamp'   => now(),
                'user_id'     => 'SYSTEM',
                'ip_address'  => null,
                'user_agent'  => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChannelDeploy: audit_log retry entry failed: ' . $e->getMessage());
        }
    }

    // SOURCE: CIE_Integration_Specification.pdf §2.5 — immediate admin alert on 401/403 auth failures
    private function alertAdmin(string $message): void
    {
        Log::alert($message);
    }

    // SOURCE: CIE_Integration_Specification.pdf §2.5 — log deploy failure and retry scheduling decision
    private function logDeployFailure(string $skuCode, string $channel, string $reason, bool $retryScheduled): void
    {
        try {
            AuditLog::create([
                'entity_type' => 'channel_deploy',
                'entity_id' => $skuCode,
                'action' => 'deploy_failure',
                'field_name' => $channel,
                'old_value' => null,
                'new_value' => json_encode([
                    'reason' => $reason,
                    'retry_scheduled' => $retryScheduled,
                ]),
                'actor_id' => 'SYSTEM',
                'actor_role' => 'system',
                'timestamp' => now(),
                'user_id' => 'SYSTEM',
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChannelDeploy: deploy failure audit insert failed: ' . $e->getMessage());
        }
    }
}
