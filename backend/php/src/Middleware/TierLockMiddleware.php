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

        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 Gate G6.1
        // Harvest-tier: only specification + 1 optional secondary intent permitted. Override: NONE.
        if ($sku->tier === TierType::HARVEST) {
            $harvestPermitted = ['specification', 'cluster_id', 'tier', 'lock_version', 'validation_status', 'secondary_intents'];
            $harvestBlocked = [
                'answer_block', 'best_for', 'not_for',
                'expert_authority', 'title', 'long_description', 'wikidata_uri',
            ];

            foreach ($harvestBlocked as $field) {
                if ($request->has($field)) {
                    return response()->json([
                        'status'       => 'fail',
                        'error_code'   => 'HARVEST_TIER_FIELD_BLOCKED',
                        'detail'       => "Harvest-tier SKU. Field '{$field}' is not permitted. Harvest allows: specification + 1 optional intent only.",
                        'user_message' => 'This SKU is Harvest tier. Only Specification and 1 optional intent are available. Answer Block, Best-For/Not-For, and Expert Authority are suspended.',
                    ], 422);
                }
            }

            if ($request->has('secondary_intents')) {
                $allowedIntents = ['problem_solving', 'compatibility'];
                $provided = (array) $request->input('secondary_intents');

                if (count($provided) > 1) {
                    return response()->json([
                        'status'     => 'fail',
                        'error_code' => 'HARVEST_SECONDARY_INTENT_LIMIT',
                        'detail'     => 'Harvest-tier SKU allows max 1 secondary intent.',
                    ], 422);
                }

                foreach ($provided as $intent) {
                    if (!in_array($intent, $allowedIntents, true)) {
                        return response()->json([
                            'status'     => 'fail',
                            'error_code' => 'HARVEST_SECONDARY_INTENT_INVALID',
                            'detail'     => "Harvest-tier SKU secondary intent must be 'problem_solving' or 'compatibility'. Got: '{$intent}'.",
                        ], 422);
                    }
                }
            }
        }

        return $next($request);
    }
}
