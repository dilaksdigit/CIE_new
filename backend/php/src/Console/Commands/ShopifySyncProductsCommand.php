<?php

namespace App\Console\Commands;

use App\Services\ShopifyProductPullService;
use Illuminate\Console\Command;

/**
 * Pull products from Shopify and optionally sync to CIE SKUs (match by variant SKU).
 * Rate-limited (2 req/s). Run via cron or manually.
 * SOURCE: Shopify product pull — does not affect deploy/publish.
 */
class ShopifySyncProductsCommand extends Command
{
    protected $signature = 'shopify:sync-products
                            {--limit= : Max products to fetch (default: all)}
                            {--no-sync : Only fetch and output count; do not update SKUs}';
    protected $description = 'Pull products from Shopify API and sync full catalogue data (pricing, status, content, images) to CIE SKUs';

    public function handle(): int
    {
        $service = new ShopifyProductPullService();
        if (!$service->isConfigured()) {
            $this->warn('Shopify pull not configured. Set SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_ACCESS_TOKEN. Set SHOPIFY_PULL_ENABLED=false to disable.');
            return 1;
        }

        $limit = $this->option('limit');
        $maxProducts = $limit !== null && $limit !== '' ? (int) $limit : null;
        $doSync = !$this->option('no-sync');

        $this->info('Fetching products from Shopify (2 req/s)...');
        $result = $service->pullAllProducts($maxProducts);

        if ($result['error'] !== null) {
            $this->error('Pull error: ' . $result['error']);
            return 1;
        }

        $this->info('Fetched ' . $result['total_fetched'] . ' products.');

        if (!$doSync || empty($result['products'])) {
            return 0;
        }

        $this->info('Syncing to CIE SKUs (match by variant SKU)...');
        $syncResult = $service->syncProductsToSkus($result['products']);
        $this->info('Updated: ' . $syncResult['updated'] . ', Skipped (no matching SKU): ' . $syncResult['skipped']);
        if (!empty($syncResult['errors'])) {
            foreach (array_slice($syncResult['errors'], 0, 10) as $err) {
                $this->warn('  ' . $err);
            }
            if (count($syncResult['errors']) > 10) {
                $this->warn('  ... and ' . (count($syncResult['errors']) - 10) . ' more.');
            }
        }

        return 0;
    }
}
