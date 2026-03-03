<?php
namespace App\Services;

use App\Models\Sku;
use App\Enums\TierType;
use App\Support\BusinessRules;
use Illuminate\Support\Collection;

class TierCalculationService
{
 private const PROFITABILITY_THRESHOLD = 5.0; // 5% margin
 private const PERCENTILE_TOP = 20; // Top 20%
 
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §8.2 — Tier Assignment from Percentile
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — Business Rules seed keys:
    //         tier.hero_percentile_threshold (0.80),
    //         tier.support_percentile_threshold (0.30),
    //         tier.harvest_percentile_threshold (0.10)
    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §3.2 — Tier bands (top 20% Hero, etc.)
    public function recalculateAllTiers(): array
    {
        // Load all active SKUs with the commercial fields needed
        $allSkus = Sku::whereNotNull('margin_percent')
            ->whereNotNull('erp_cppc')
            ->whereNotNull('erp_return_rate_pct')
            ->get();

        if ($allSkus->isEmpty()) {
            return [];
        }

        // Cohort max velocity_90d used for normalisation.
        $maxVelocity = (float) $allSkus->max(function (Sku $sku) {
            return (float) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
        });
        if ($maxVelocity <= 0) {
            $maxVelocity = 1.0;
        }

        // Compute composite scores first
        $scores = [];
        foreach ($allSkus as $sku) {
            $scores[$sku->id] = $this->calculateCommercialScore($sku, $maxVelocity);
            $sku->update(['commercial_score' => $scores[$sku->id]]);
        }

        if (empty($scores)) {
            return [];
        }

        // Derive percentile thresholds from composite scores
        $sortedScores = collect($scores)->values()->sort()->values();
        $count        = $sortedScores->count();

        $heroThreshold    = $this->rule('tier.hero_percentile_threshold', 0.80);
        $supportThreshold = $this->rule('tier.support_percentile_threshold', 0.30);
        $harvestThreshold = $this->rule('tier.harvest_percentile_threshold', 0.10);

        $heroCut    = $sortedScores[(int) floor($count * $heroThreshold)] ?? $sortedScores->last();
        $supportCut = $sortedScores[(int) floor($count * $supportThreshold)] ?? $sortedScores->first();
        $harvestCut = $sortedScores[(int) floor($count * $harvestThreshold)] ?? $sortedScores->first();

        $changes = [];

        foreach ($allSkus as $sku) {
            $oldTier = $sku->tier;
            $score   = $scores[$sku->id];

            // Strategic hero override and kill conditions still apply
            if ($sku->strategic_hero) {
                $newTier = TierType::HERO;
            } elseif ($this->shouldBeKilled($sku)) {
                $newTier = TierType::KILL;
            } else {
                if ($score >= $heroCut) {
                    $newTier = TierType::HERO;
                } elseif ($score >= $supportCut) {
                    $newTier = TierType::SUPPORT;
                } elseif ($score >= $harvestCut) {
                    $newTier = TierType::HARVEST;
                } else {
                    $newTier = TierType::KILL;
                }
            }

            // Auto-promotion rule: Harvest SKUs with >30% QoQ velocity increase become Support
            if ($oldTier === TierType::HARVEST && $newTier === TierType::HARVEST) {
                $previousVelocity = (int) ($sku->previous_velocity_90d ?? 0);
                $currentVelocity  = (int) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
                if ($previousVelocity > 0) {
                    $growth = ($currentVelocity - $previousVelocity) / $previousVelocity;
                    if ($growth > 0.3) {
                        $newTier = TierType::SUPPORT;
                    }
                }
            }

            if ($oldTier !== $newTier) {
                $this->updateSkuTier($sku, $oldTier, $newTier);
                $changes[] = [
                    'sku_id'  => $sku->id,
                    'sku_code'=> $sku->sku_code,
                    'old_tier'=> $oldTier->value,
                    'new_tier'=> $newTier->value,
                    'margin'  => $sku->margin_percent,
                    'volume'  => $sku->erp_velocity_90d ?? $sku->annual_volume,
                    'score'   => $score,
                ];
            }
        }

        return $changes;
    }

    public function calculateCommercialScore(Sku $sku, float $maxVelocity): float
    {
        // Spec formula: weights from BusinessRules (margin_weight, cppc_weight, velocity_weight, returns_weight).
        $marginPct   = (float) ($sku->margin_percent ?? 0);
        $cppc        = (float) ($sku->erp_cppc ?? 0);
        $velocity90d = (float) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
        $returnPct   = (float) ($sku->erp_return_rate_pct ?? 0);

        $wMargin   = $this->weight('tier.margin_weight', 0.40);
        $wCppc     = $this->weight('tier.cppc_weight', 0.25);
        $wVelocity = $this->weight('tier.velocity_weight', 0.20);
        $wReturns  = $this->weight('tier.returns_weight', 0.15);

        $marginNorm   = $marginPct / 100.0;
        $cppcNorm     = $cppc > 0 ? (1.0 / $cppc) : 0.0;
        $velocityNorm = $this->normalise($velocity90d, 0.0, $maxVelocity);
        $returnsNorm  = 1.0 - ($returnPct / 100.0);

        $score =
            ($marginNorm * $wMargin) +
            ($cppcNorm * $wCppc) +
            ($velocityNorm * $wVelocity) +
            ($returnsNorm * $wReturns);

        return round((float) $score, 4);
    }

    // calculateTierForSku is now inlined into recalculateAllTiers with percentile rules
 
 private function shouldBeKilled(Sku $sku): bool
 {
 if ($sku->margin_percent <= 0) { return true; }
 if ($sku->last_sale_date && strtotime($sku->last_sale_date) < strtotime('-90 days')) { return true; }
 if (($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0) === 0) { return true; }
 return false;
 }
 
 private function calculatePercentile(Collection $skus, string $field, int $percentile): float|int
 {
 $values = $skus->pluck($field)->filter()->sort()->values();
 if ($values->isEmpty()) { return 0; }
 $index = (int) ceil($values->count() * ((100 - $percentile) / 100));
 return $values[$index] ?? $values->last();
 }
 
 private function updateSkuTier(Sku $sku, TierType $oldTier, TierType $newTier): void
 {
 $velocity = $sku->erp_velocity_90d ?? $sku->annual_volume;
 $rationale = sprintf(
 'Margin: %.1f%%, Velocity_90d: %d units',
 $sku->margin_percent,
 $velocity
 );
 $sku->update(['tier' => $newTier, 'tier_rationale' => $rationale]);
 \App\Models\TierHistory::create([
 'sku_id' => $sku->id,
 'old_tier' => $oldTier,
 'new_tier' => $newTier,
 'reason' => $rationale,
 'margin_percent' => $sku->margin_percent,
 'annual_volume' => $velocity,
 'changed_by' => auth()->id()
 ]);
 }

 private function weight(string $key, float $default): float
 {
 try {
 return (float) BusinessRules::get($key, $default);
 } catch (\Throwable $e) {
 return $default;
 }
 }

    private function rule(string $key, float $default): float
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — BusinessRules helper
        try {
            return (float) BusinessRules::get($key);
        } catch (\Throwable $e) {
            return $default;
        }
    }

 private function normalise(float $value, float $min, float $max): float
 {
 if ($max <= $min) {
 return 0.0;
 }

 $normalised = ($value - $min) / ($max - $min);

 if ($normalised < 0.0) {
 return 0.0;
 }

 if ($normalised > 1.0) {
 return 1.0;
 }

 return $normalised;
 }
}
