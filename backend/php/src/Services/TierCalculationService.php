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
 
    public function recalculateAllTiers(): array
    {
        // Load all active SKUs with the commercial fields needed
        $allSkus = Sku::whereNotNull('margin_percent')
            ->whereNotNull('erp_cppc')
            ->whereNotNull('annual_volume')
            ->whereNotNull('erp_return_rate_pct')
            ->get();

        // Compute composite scores first
        $scores = [];
        foreach ($allSkus as $sku) {
            $scores[$sku->id] = $this->calculateCommercialScore($sku);
            $sku->update(['commercial_score' => $scores[$sku->id]]);
        }

        if (empty($scores)) {
            return [];
        }

        // Derive percentile thresholds from composite scores
        $sortedScores = collect($scores)->values()->sort()->values();
        $count        = $sortedScores->count();

        $heroCut    = $sortedScores[(int) floor($count * 0.8)] ?? $sortedScores->last();   // top 20%
        $supportCut = $sortedScores[(int) floor($count * 0.3)] ?? $sortedScores->first();  // next 50%
        $harvestCut = $sortedScores[(int) floor($count * 0.1)] ?? $sortedScores->first();  // next 20%

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
                if ($previousVelocity > 0) {
                    $growth = ($sku->annual_volume - $previousVelocity) / $previousVelocity;
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
                    'volume'  => $sku->annual_volume,
                    'score'   => $score,
                ];
            }
        }

        return $changes;
    }
 
    public function calculateCommercialScore(Sku $sku): float
    {
        // Spec formula: weights from BusinessRules (margin_weight, cppc_weight, velocity_weight, returns_weight).
        $marginPct   = (float) ($sku->margin_percent ?? 0);
        $cppc        = (float) ($sku->erp_cppc ?? 0);
        $velocity    = (float) ($sku->annual_volume ?? 0);
        $returnPct   = (float) ($sku->erp_return_rate_pct ?? 0);

        $wMargin  = $this->weight('tier.margin_weight', 0.40);
        $wCppc    = $this->weight('tier.cppc_weight', 0.25);
        $wVelocity= $this->weight('tier.velocity_weight', 0.20);
        $wReturns = $this->weight('tier.returns_weight', 0.15);

        $marginTerm  = ($marginPct / 100.0) * $wMargin;
        $cppcTerm    = ($cppc > 0 ? (1 / $cppc) : 0) * $wCppc;
        $velocityTerm= $velocity * $wVelocity;
        $returnTerm  = (1 - ($returnPct / 100.0)) * $wReturns;

        return round($marginTerm + $cppcTerm + $velocityTerm + $returnTerm, 4);
    }

    // calculateTierForSku is now inlined into recalculateAllTiers with percentile rules
 
 private function shouldBeKilled(Sku $sku): bool
 {
 if ($sku->margin_percent <= 0) { return true; }
 if ($sku->last_sale_date && strtotime($sku->last_sale_date) < strtotime('-90 days')) { return true; }
 if ($sku->annual_volume === 0) { return true; }
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
 $rationale = sprintf(
 'Margin: %.1f%%, Volume: %d units',
 $sku->margin_percent,
 $sku->annual_volume
 );
 $sku->update(['tier' => $newTier, 'tier_rationale' => $rationale]);
 \App\Models\TierHistory::create([
 'sku_id' => $sku->id,
 'old_tier' => $oldTier,
 'new_tier' => $newTier,
 'reason' => $rationale,
 'margin_percent' => $sku->margin_percent,
 'annual_volume' => $sku->annual_volume,
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
}
