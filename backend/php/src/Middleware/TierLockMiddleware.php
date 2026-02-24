<?php
namespace App\Middleware;

use Closure;
use App\Models\Sku;
use App\Enums\TierType;

class TierLockMiddleware
{
    public function handle($request, Closure $next)
    {
        $skuId = $request->route('id');
        if (!$skuId) return $next($request);

        $sku = Sku::find($skuId);
        if (!$sku) return $next($request);

        // Patch 6: Kill-tier SKUs - absolute lock on any edit
        if ($sku->tier === TierType::KILL) {
            return response()->json([
                'error' => "KILL TIER: Policy violation. Any edit to a decommissioned SKU is prohibited."
            ], 403);
        }

        // Hero and Support tiers have certain fields locked if they are already validated (G6.1)
        if (($sku->tier === TierType::HERO || $sku->tier === TierType::SUPPORT) && $sku->validation_status === 'VALID') {
            $lockedFields = ['title', 'primary_cluster_id', 'long_description', 'sku_intents', 'best_for', 'not_for'];
            foreach ($lockedFields as $field) {
                if ($request->has($field) && $request->input($field) !== $sku->$field) {
                    return response()->json([
                        'error' => "Field '$field' is locked for validated {$sku->tier->value} products."
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
