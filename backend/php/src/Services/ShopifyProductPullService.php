<?php
// SOURCE: Shopify product pull — read-only; uses existing ShopifyRateLimiter (2 req/s).
// Does not modify ChannelDeployService or publish flow.

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyProductPullService
{
    private const ENTITY_ID_PULL = 'shopify_pull';
    private const API_VERSION = '2024-01';
    private const MAX_PAGES = 200; // safety cap

    /**
     * Whether pull is configured (store domain + token set).
     */
    public function isConfigured(): bool
    {
        if (env('SHOPIFY_PULL_ENABLED', 'true') === 'false') {
            return false;
        }
        $domain = $this->getStoreDomain();
        $token = $this->getAccessToken();
        return $domain !== '' && $token !== '';
    }

    /**
     * Fetch a single page of products from Shopify REST Admin API.
     * Uses ShopifyRateLimiter. Returns normalized list and next cursor if any.
     *
     * @return array{products: array<int, array>, next_page_info: string|null, status_code: int, body: array}
     */
    public function fetchProductsPage(?string $pageInfo = null, int $limit = 250): array
    {
        $domain = $this->getStoreDomain();
        $token = $this->getAccessToken();
        if ($domain === '' || $token === '') {
            return [
                'products' => [],
                'next_page_info' => null,
                'status_code' => 0,
                'body' => [],
            ];
        }

        $url = 'https://' . $domain . '/admin/api/' . self::API_VERSION . '/products.json';
        $query = ['limit' => min(250, max(1, $limit))];
        if ($pageInfo !== null && $pageInfo !== '') {
            $query['page_info'] = $pageInfo;
        }
        $url .= '?' . http_build_query($query);

        $limiter = new ShopifyRateLimiter();
        $auditLogger = function (string $event, array $payload) {
            $this->auditLog($event, $payload);
        };
        $cronQueuer = function (string $skuId) {
            // No-op for pull; we don't queue per-SKU cron for list.
        };

        $result = $limiter->callWithRetry(
            fn () => $this->getRequest($url, $token),
            self::ENTITY_ID_PULL,
            $auditLogger,
            $cronQueuer
        );

        if (isset($result['status']) && $result['status'] === 'failed') {
            return [
                'products' => [],
                'next_page_info' => null,
                'status_code' => $result['status_code'] ?? 0,
                'body' => [],
            ];
        }

        $body = $result['body'] ?? [];
        $products = $this->normalizeProducts($body['products'] ?? []);
        $nextPageInfo = $this->parseNextPageInfo($result['headers'] ?? []);

        return [
            'products' => $products,
            'next_page_info' => $nextPageInfo,
            'status_code' => $result['status_code'] ?? 200,
            'body' => $body,
        ];
    }

    /**
     * Pull all products (paginated) up to optional max.
     *
     * @param int|null $maxProducts Max products to return (null = all, subject to MAX_PAGES)
     * @return array{products: array<int, array>, total_fetched: int, next_page_info: string|null, error: string|null}
     */
    public function pullAllProducts(?int $maxProducts = null): array
    {
        $all = [];
        $pageInfo = null;
        $pages = 0;

        while ($pages < self::MAX_PAGES) {
            $page = $this->fetchProductsPage($pageInfo, 250);
            $products = $page['products'];
            foreach ($products as $p) {
                $all[] = $p;
                if ($maxProducts !== null && count($all) >= $maxProducts) {
                    return [
                        'products' => array_slice($all, 0, $maxProducts),
                        'total_fetched' => count($all),
                        'next_page_info' => $page['next_page_info'],
                        'error' => null,
                    ];
                }
            }
            $pageInfo = $page['next_page_info'];
            $pages++;
            if ($pageInfo === null || count($products) === 0) {
                break;
            }
        }

        return [
            'products' => $all,
            'total_fetched' => count($all),
            'next_page_info' => $pageInfo ?? null,
            'error' => null,
        ];
    }

    /**
     * Sync pulled products into CIE: match by variant SKU to sku_code,
     * persist full Shopify catalogue data (pricing, status, content, image).
     * Only updates existing SKUs; does not create SKUs.
     */
    public function syncProductsToSkus(array $products): array
    {
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $columnCache = [];
        $optionalColumns = [
            'shopify_variant_id', 'shopify_synced_at', 'shopify_status',
            'shopify_title', 'shopify_handle', 'shopify_body_html',
            'shopify_price', 'shopify_compare_at_price', 'shopify_image_url',
            'shopify_product_type', 'shopify_vendor', 'shopify_tags',
        ];
        foreach ($optionalColumns as $col) {
            $columnCache[$col] = \Illuminate\Support\Facades\Schema::hasColumn('skus', $col);
        }

        foreach ($products as $p) {
            $shopifyId = $p['id'] ?? null;
            $variants = $p['variants'] ?? [];
            foreach ($variants as $v) {
                $skuCode = isset($v['sku']) ? trim((string) $v['sku']) : null;
                if ($skuCode === null || $skuCode === '') {
                    continue;
                }
                $sku = \App\Models\Sku::where('sku_code', $skuCode)->first();
                if (!$sku) {
                    $skipped++;
                    continue;
                }
                try {
                    $data = ['shopify_product_id' => (string) $shopifyId];

                    $fieldMap = [
                        'shopify_variant_id'       => isset($v['id']) ? (string) $v['id'] : null,
                        'shopify_synced_at'        => now(),
                        'shopify_status'           => $p['status'] ?? null,
                        'shopify_title'            => $p['title'] ?? null,
                        'shopify_handle'           => $p['handle'] ?? null,
                        'shopify_body_html'        => $p['body_html'] ?? null,
                        'shopify_price'            => $this->parseDecimal($v['price'] ?? null),
                        'shopify_compare_at_price' => $this->parseDecimal($v['compare_at_price'] ?? null),
                        'shopify_image_url'        => $p['image_url'] ?? null,
                        'shopify_product_type'     => $p['product_type'] ?? null,
                        'shopify_vendor'           => $p['vendor'] ?? null,
                        'shopify_tags'             => $p['tags'] ?? null,
                    ];

                    foreach ($fieldMap as $col => $value) {
                        if (!empty($columnCache[$col])) {
                            $data[$col] = $value;
                        }
                    }

                    $sku->update($data);
                    $updated++;
                } catch (\Throwable $e) {
                    $errors[] = "SKU {$skuCode}: " . $e->getMessage();
                    Log::warning('ShopifyProductPull: sync failed for ' . $skuCode, ['exception' => $e->getMessage()]);
                }
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $parsed !== false ? $parsed : null;
    }

    private function getStoreDomain(): string
    {
        $v = env('SHOPIFY_STORE_DOMAIN', '');
        $v = is_string($v) ? trim($v) : '';
        return preg_replace('#^https?://#', '', $v);
    }

    private function getAccessToken(): string
    {
        $v = env('SHOPIFY_ADMIN_ACCESS_TOKEN', '');
        return is_string($v) ? trim($v) : '';
    }

    /**
     * GET request to Shopify; returns array with status_code, body, headers for rate limiter.
     */
    private function getRequest(string $url, string $token): array
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($url);

            return [
                'status_code' => $response->status(),
                'body' => $response->json() ?? [],
                'headers' => ['Link' => $response->header('Link')],
            ];
        } catch (\Throwable $e) {
            Log::warning('ShopifyProductPull: request failed: ' . $e->getMessage(), ['url' => $url]);
            return ['status_code' => 0, 'body' => [], 'headers' => []];
        }
    }

    /**
     * Parse Link header for cursor (page_info) — Shopify uses rel="next" with page_info=...
     */
    private function parseNextPageInfo(array $headers): ?string
    {
        $link = $headers['Link'] ?? $headers['link'] ?? null;
        if (is_array($link)) {
            $link = $link[0] ?? null;
        }
        if (!is_string($link)) {
            return null;
        }
        if (preg_match('/<[^>]+[?&]page_info=([^&>"\']+)[^>]*>;\s*rel="next"/', $link, $m)) {
            return $m[1];
        }
        if (preg_match('/page_info=([^&\s"\']+)/', $link, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Normalize Shopify product array to a stable shape for CIE.
     */
    private function normalizeProducts(array $products): array
    {
        $out = [];
        foreach ($products as $p) {
            $images = $p['images'] ?? [];
            $primaryImage = !empty($images) ? ($images[0]['src'] ?? null) : null;
            $image = $p['image'] ?? null;
            if ($primaryImage === null && is_array($image)) {
                $primaryImage = $image['src'] ?? null;
            }

            $out[] = [
                'id'           => $p['id'] ?? null,
                'title'        => $p['title'] ?? null,
                'handle'       => $p['handle'] ?? null,
                'status'       => $p['status'] ?? null,
                'body_html'    => $p['body_html'] ?? null,
                'vendor'       => $p['vendor'] ?? null,
                'product_type' => $p['product_type'] ?? null,
                'tags'         => $p['tags'] ?? null,
                'image_url'    => $primaryImage,
                'variants'     => $this->normalizeVariants($p['variants'] ?? []),
                'updated_at'   => $p['updated_at'] ?? null,
            ];
        }
        return $out;
    }

    private function normalizeVariants(array $variants): array
    {
        $out = [];
        foreach ($variants as $v) {
            $out[] = [
                'id'               => $v['id'] ?? null,
                'sku'              => $v['sku'] ?? null,
                'title'            => $v['title'] ?? null,
                'price'            => $v['price'] ?? null,
                'compare_at_price' => $v['compare_at_price'] ?? null,
            ];
        }
        return $out;
    }

    private function auditLog(string $event, array $payload): void
    {
        try {
            AuditLog::create([
                'entity_type' => 'shopify_pull',
                'entity_id'   => $payload['sku_id'] ?? self::ENTITY_ID_PULL,
                'action'      => $event,
                'field_name'  => null,
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
            Log::warning('ShopifyProductPull: audit_log failed: ' . $e->getMessage());
        }
    }
}
