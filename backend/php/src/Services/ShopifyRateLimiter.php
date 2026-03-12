<?php
// SOURCE: Task E2 Validation Report — Items 1, 2, 3, 6, 7, 8
// SOURCE: CLAUDE.md Section 10 (Shopify PRIMARY, 2 calls/sec)

namespace App\Services;

use App\Exceptions\ShopifyRateLimitException;

class ShopifyRateLimiter
{
    /**
     * Max 2 Shopify API calls per second — enforced by 0.5s spacing.
     * SOURCE: CLAUDE.md Section 10 / Task E2 Item 2
     */
    private static float $lastCallTime = 0.0;
    private const MIN_CALL_SPACING_US = 500000; // 0.5s in microseconds

    /**
     * Retry backoff in seconds: 30s, 2m, 10m.
     * SOURCE: Task E2 Item 4 — hard-coded defaults (no BusinessRules keys defined for these).
     */
    private const BACKOFF_SECONDS = [30, 120, 600];

    /**
     * Execute a Shopify API callable with rate limiting and retry logic.
     *
     * Behaviour by HTTP status (SOURCE: Task E2 Items 6, 7, 8):
     *  - 429 : retry up to 3 times using BACKOFF_SECONDS; throw ShopifyRateLimitException on exhaustion.
     *  - 500/503 : queue for next cron window, log to audit_log, return failed result — do NOT throw.
     *  - 401/403 : halt immediately, log to audit_log with severity "critical", do NOT retry — return failed result.
     *  - Other non-success : return failed result — do NOT throw.
     *
     * @param  callable $apiCall  Must return array with at least ['status_code' => int, ...].
     * @param  string   $skuId    Used for audit log entity_id.
     * @param  callable $auditLogger  fn(string $event, array $payload): void
     * @param  callable $cronQueuer   fn(string $skuId): void  — called on 500/503 to queue for next cron.
     * @return array    Result array from $apiCall, or ['status' => 'failed', 'reason' => string].
     *
     * @throws ShopifyRateLimitException  Only on 429 exhaustion after 3 retries.
     */
    public function callWithRetry(
        callable $apiCall,
        string $skuId,
        callable $auditLogger,
        callable $cronQueuer
    ): array {
        $attempts = 0;
        $maxRetries = count(self::BACKOFF_SECONDS);

        while (true) {
            $this->enforceRateLimit();

            $result = $apiCall();
            $statusCode = $result['status_code'] ?? 0;

            // ── SUCCESS ──────────────────────────────────────────────
            if ($statusCode >= 200 && $statusCode < 300) {
                return $result;
            }

            // ── 401 / 403: halt, log critical, do NOT retry ──────────
            // SOURCE: Task E2 Item 8
            if ($statusCode === 401 || $statusCode === 403) {
                $auditLogger('shopify_auth_failure', [
                    'sku_id'      => $skuId,
                    'status_code' => $statusCode,
                    'severity'    => 'critical',
                ]);
                return ['status' => 'failed', 'reason' => "Shopify auth error: {$statusCode}"];
            }

            // ── 500 / 503: queue for next cron, log, do NOT throw ────
            // SOURCE: Task E2 Item 7
            if ($statusCode === 500 || $statusCode === 503) {
                $cronQueuer($skuId);
                $auditLogger('shopify_server_error', [
                    'sku_id'      => $skuId,
                    'status_code' => $statusCode,
                ]);
                return ['status' => 'failed', 'reason' => "Shopify server error: {$statusCode} — queued for next cron"];
            }

            // ── 429: retry with backoff ───────────────────────────────
            // SOURCE: Task E2 Items 3, 6
            if ($statusCode === 429) {
                if ($attempts >= $maxRetries) {
                    throw new ShopifyRateLimitException(
                        "Shopify rate limit exceeded after {$maxRetries} retries for SKU {$skuId}"
                    );
                }

                $waitSeconds = self::BACKOFF_SECONDS[$attempts];

                $auditLogger('shopify_retry', [
                    'attempt'      => $attempts + 1,
                    'wait_seconds' => $waitSeconds,
                    'sku_id'       => $skuId,
                    'status_code'  => $statusCode,
                ]);

                sleep($waitSeconds);
                $attempts++;
                continue;
            }

            // ── Other non-success ─────────────────────────────────────
            return ['status' => 'failed', 'reason' => "Shopify unexpected status: {$statusCode}"];
        }
    }

    /**
     * Enforce max 2 calls/second by spacing calls 0.5s apart.
     * SOURCE: CLAUDE.md Section 10
     */
    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $elapsed = (int)(($now - self::$lastCallTime) * 1_000_000); // microseconds

        if ($elapsed < self::MIN_CALL_SPACING_US) {
            usleep(self::MIN_CALL_SPACING_US - $elapsed);
        }

        self::$lastCallTime = microtime(true);
    }
}
