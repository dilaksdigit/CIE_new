<?php
namespace App\Services;

// SOURCE: CIE_Integration_Specification.pdf Section 1.2-1.4; CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 9.2
use App\Models\Sku;
use App\Models\TierHistory;
use App\Models\AuditLog;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Exception;
use Carbon\Carbon;

class ERPSyncService {
    /**
     * Sync ERP payload and recompute tiers.
     * @param array $payload ERP sync payload (see openapi.yaml)
     * @return array Response object per /erp/sync 200 schema
     */
    public function sync(array $payload): array
    {
        $now = Carbon::now();
        $errors = [];
        $skus_processed = 0;
        $tier_changes = 0;
        $auto_promotions = 0;
        $tier_band_percentiles = [
            'hero' => (float) BusinessRules::get('tier.hero_percentile', 80),
            'support' => (float) BusinessRules::get('tier.support_percentile', 30),
            'harvest' => (float) BusinessRules::get('tier.harvest_percentile', 10),
        ];
        $weights = [
            'margin' => (float) BusinessRules::get('tier.margin_weight', 0.4),
            'cppc' => (float) BusinessRules::get('tier.cppc_weight', 0.25),
            'velocity' => (float) BusinessRules::get('tier.velocity_weight', 0.2),
            'returns' => (float) BusinessRules::get('tier.returns_weight', 0.15),
        ];
        $skus = $payload['skus'] ?? [];
        $erp_sku_ids = array_column($skus, 'sku_id');
        $cie_skus = Sku::all()->keyBy('sku_id');
        $previous_tiers = $cie_skus->mapWithKeys(fn($sku) => [$sku->sku_id => $sku->tier]);
        $previous_velocity = $cie_skus->mapWithKeys(fn($sku) => [$sku->sku_id => $sku->velocity_90d]);
        $score_map = [];
        // 1. Validate and process each ERP row
        foreach ($skus as $row) {
            $sku_id = $row['sku_id'] ?? null;
            $margin = $row['contribution_margin_pct'] ?? null;
            $cppc = $row['cppc'] ?? null;
            $velocity = $row['velocity_90d'] ?? null;
            $returns = $row['return_rate_pct'] ?? null;
            // Validate row
            if (!$sku_id || !is_numeric($margin) || !is_numeric($cppc) || !is_numeric($velocity) || !is_numeric($returns) || $margin > 100 || $margin < -100 || $cppc < 0 || $velocity < 0 || $returns < 0) {
                $errors[] = "Invalid data for SKU $sku_id";
                Log::error("ERP sync: Invalid data for SKU $sku_id", $row);
                $this->alertAdmin("ERP sync: Invalid data for SKU $sku_id", $row);
                continue;
            }
            // Orphan ERP SKU (not in CIE)
            if (!isset($cie_skus[$sku_id])) {
                $errors[] = "Orphan ERP SKU: $sku_id";
                Log::warning("ERP sync: Orphan ERP SKU $sku_id", $row);
                continue;
            }
            // Compute normalized scores (0-1 scale)
            $marginScore = $this->normalize($margin, 0, 100);
            $cppcScore = $this->normalize($cppc, 0, 1); // Assume max CPPC = 1 for normalization
            $velocityScore = $this->normalize($velocity, 0, 1000); // Assume max velocity = 1000
            $returnScore = 1 - $this->normalize($returns, 0, 100); // Lower returns = better
            $score = (
                $marginScore * $weights['margin'] +
                $cppcScore * $weights['cppc'] +
                $velocityScore * $weights['velocity'] +
                $returnScore * $weights['returns']
            );
            $score_map[$sku_id] = $score;
            // Update SKU commercial data
            $sku = $cie_skus[$sku_id];
            $sku->contribution_margin_pct = $margin;
            $sku->cppc = $cppc;
            $sku->velocity_90d = $velocity;
            $sku->return_rate_pct = $returns;
            $sku->save();
            $skus_processed++;
        }
        // 2. Assign percentiles and tiers
        $sorted = collect($score_map)->sortDesc();
        $total = $sorted->count();
        $band_limits = [
            'hero' => (int) ceil($total * (1 - $tier_band_percentiles['hero'] / 100)),
            'support' => (int) ceil($total * (1 - $tier_band_percentiles['support'] / 100)),
            'harvest' => (int) ceil($total * (1 - $tier_band_percentiles['harvest'] / 100)),
        ];
        $i = 0;
        $tier_assignments = [];
        foreach ($sorted as $sku_id => $score) {
            $i++;
            if ($i <= $band_limits['hero']) {
                $tier = 'HERO';
            } elseif ($i <= $band_limits['support']) {
                $tier = 'SUPPORT';
            } elseif ($i <= $band_limits['harvest']) {
                $tier = 'HARVEST';
            } else {
                $tier = 'KILL';
            }
            $tier_assignments[$sku_id] = $tier;
        }
        // 3. Apply auto-promotion rule and write audit/tier_history
        foreach ($tier_assignments as $sku_id => $new_tier) {
            $sku = $cie_skus[$sku_id];
            $old_tier = $previous_tiers[$sku_id] ?? null;
            $was_harvest = $old_tier === 'HARVEST';
            $now_support = $new_tier === 'SUPPORT';
            $auto_promoted = false;
            // Auto-promotion: harvest→support if velocity up 30%
            if ($was_harvest && $now_support && $sku->velocity_90d > ($previous_velocity[$sku_id] ?? 0) * 1.3) {
                $auto_promotions++;
                $auto_promoted = true;
            }
            if ($old_tier !== $new_tier || $auto_promoted) {
                $tier_changes++;
                TierHistory::create([
                    'sku_id' => $sku_id,
                    'old_tier' => $old_tier,
                    'new_tier' => $new_tier,
                    'changed_at' => $now,
                ]);
                AuditLog::create([
                    'sku_id' => $sku_id,
                    'event' => 'tier_change',
                    'details' => json_encode([
                        'from' => $old_tier,
                        'to' => $new_tier,
                        'auto_promotion' => $auto_promoted,
                    ]),
                    'created_at' => $now,
                ]);
            }
            $sku->tier = $new_tier;
            $sku->save();
        }
        // 4. Mark CIE SKUs not in ERP as stale
        foreach ($cie_skus as $sku_id => $sku) {
            if (!in_array($sku_id, $erp_sku_ids)) {
                $sku->commercial_data_stale = true;
                $sku->save();
            }
        }
        // 5. Handle ERP errors: retry on timeout (simulate, as this is not a real HTTP call)
        // (In real implementation, wrap ERP call in try/catch and retry with sleep/backoff)
        // 6. Return response
        return [
            'sync_date' => $now->toIso8601String(),
            'skus_processed' => $skus_processed,
            'tier_changes' => $tier_changes,
            'auto_promotions' => $auto_promotions,
            'errors' => $errors,
        ];
    }

    private function normalize($value, $min, $max)
    {
        if ($max == $min) return 0;
        return max(0, min(1, ($value - $min) / ($max - $min)));
    }

    private function alertAdmin($message, $context = [])
    {
        // Placeholder: send notification to admin (implement as needed)
        Log::alert($message, $context);
    }
}
