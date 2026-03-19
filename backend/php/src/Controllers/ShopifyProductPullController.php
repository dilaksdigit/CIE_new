<?php
namespace App\Controllers;

use App\Services\ShopifyProductPullService;
use Illuminate\Http\Request;

/**
 * Shopify product pull — read-only list and sync to CIE SKUs.
 * Admin-only. Does not affect deploy or publish flow.
 */
class ShopifyProductPullController
{
    /**
     * GET /api/v1/shopify/products — list products from Shopify (optional limit, optional sync to SKUs).
     * Query: limit (int, optional), sync (bool, default false = list only).
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $service = new ShopifyProductPullService();
        if (!$service->isConfigured()) {
            return response()->json([
                'configured' => false,
                'message' => 'SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_ACCESS_TOKEN must be set. Set SHOPIFY_PULL_ENABLED=false to disable.',
            ], 503);
        }

        $limit = $request->query('limit');
        $maxProducts = $limit !== null && $limit !== '' ? (int) $limit : null;
        if ($maxProducts !== null && $maxProducts < 1) {
            $maxProducts = 250;
        }

        $result = $service->pullAllProducts($maxProducts);

        $sync = filter_var($request->query('sync'), FILTER_VALIDATE_BOOLEAN);
        $syncResult = null;
        if ($sync && !empty($result['products'])) {
            $syncResult = $service->syncProductsToSkus($result['products']);
        }

        $statusBreakdown = ['active' => 0, 'draft' => 0, 'archived' => 0];
        foreach ($result['products'] as $p) {
            $s = strtolower($p['status'] ?? '');
            if (isset($statusBreakdown[$s])) {
                $statusBreakdown[$s]++;
            }
        }

        return response()->json([
            'configured' => true,
            'total_fetched' => $result['total_fetched'],
            'status_breakdown' => $statusBreakdown,
            'products' => $result['products'],
            'error' => $result['error'],
            'sync' => $syncResult,
        ], 200);
    }

    /**
     * POST /api/v1/shopify/sync — pull all products from Shopify and sync to CIE SKUs (match by variant SKU).
     */
    public function sync(Request $request)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $service = new ShopifyProductPullService();
        if (!$service->isConfigured()) {
            return response()->json([
                'configured' => false,
                'message' => 'SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_ACCESS_TOKEN must be set.',
            ], 503);
        }

        $result = $service->pullAllProducts(null);
        if ($result['error'] !== null) {
            return response()->json([
                'configured' => true,
                'total_fetched' => $result['total_fetched'],
                'error' => $result['error'],
                'sync' => null,
            ], 200);
        }

        $syncResult = $service->syncProductsToSkus($result['products']);

        $statusBreakdown = ['active' => 0, 'draft' => 0, 'archived' => 0];
        foreach ($result['products'] as $p) {
            $s = strtolower($p['status'] ?? '');
            if (isset($statusBreakdown[$s])) {
                $statusBreakdown[$s]++;
            }
        }

        return response()->json([
            'configured' => true,
            'total_fetched' => $result['total_fetched'],
            'status_breakdown' => $statusBreakdown,
            'sync' => $syncResult,
        ], 200);
    }

    /**
     * GET /api/v1/shopify/status — whether pull is configured (no auth required for health check; optional: require admin).
     */
    public function status()
    {
        $service = new ShopifyProductPullService();
        return response()->json([
            'configured' => $service->isConfigured(),
        ], 200);
    }
}
