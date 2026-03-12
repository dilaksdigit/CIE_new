<?php
namespace App\Services;

// SOURCE: CIE_Master_Developer_Build_Spec.docx §8.1 / §5.3; CLAUDE.md §7
// SOURCE: CIE_Integration_Specification.pdf §1.2, §1.3; CIE_v231_Developer_Build_Pack.pdf (sku_master schema)
// SOURCE: CIE_v231_Developer_Build_Pack.pdf §7.1; CIE_Master_Developer_Build_Spec.docx §5; CIE_Integration_Specification.pdf §1.3
// SOURCE: CIE_Master_Developer_Build_Spec.docx §5; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.2; CIE_Integration_Specification.pdf §1.3
use App\Models\Sku;
use App\Enums\TierType;
use App\Support\BusinessRules;
use Illuminate\Support\Collection;

class TierCalculationService
{
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §8.2 — Tier Assignment from Percentile
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — Business Rules seed keys:
    //         tier.hero_percentile_threshold (0.80),
    //         tier.support_percentile_threshold (0.30),
    //         tier.harvest_percentile_threshold (0.10)
    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §3.2 — Tier bands (top 20% Hero, etc.)
    public function recalculateAllTiers(): array
    {
        // Load all active SKUs with the commercial fields needed
        $allSkus = Sku::whereNotNull('erp_margin_pct')
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

        $heroThreshold    = (float) BusinessRules::get('tier.hero_percentile_threshold');
        $supportThreshold = (float) BusinessRules::get('tier.support_percentile_threshold');
        $harvestThreshold = (float) BusinessRules::get('tier.harvest_percentile_threshold');

        $heroCut    = $sortedScores[(int) floor($count * $heroThreshold)] ?? $sortedScores->last();
        $supportCut = $sortedScores[(int) floor($count * $supportThreshold)] ?? $sortedScores->first();
        $harvestCut = $sortedScores[(int) floor($count * $harvestThreshold)] ?? $sortedScores->first();

        $changes = [];

        foreach ($allSkus as $sku) {
            $oldTier = $sku->tier;
            $score   = $scores[$sku->id];

            if ($this->shouldBeKilled($sku)) {
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

            // Auto-promotion rule: Harvest SKUs with >30% QoQ velocity increase become Support (§5.3: not in 52 rules; hard-coded 0.30)
            if ($oldTier === TierType::HARVEST && $newTier === TierType::HARVEST) {
                $previousVelocity = (int) ($sku->previous_velocity_90d ?? 0);
                $currentVelocity  = (int) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
                if ($previousVelocity > 0) {
                    $growth = ($currentVelocity - $previousVelocity) / $previousVelocity;
                    $autoPromotionThreshold = 0.30;
                    if ($growth > $autoPromotionThreshold) {
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
                    'margin'  => $sku->erp_margin_pct,
                    'volume'  => $sku->erp_velocity_90d ?? $sku->annual_volume,
                    'score'   => $score,
                ];
            }
        }

        return $changes;
    }

    public function calculateCommercialScore(Sku $sku, float $maxVelocity): float
    {
        // SOURCE: CIE Validation Report DB-08 | CLAUDE.md Section 7 — exact formula
        // composite_score = (margin × 0.40) + ((1/cppc)×10×0.25) + (log10(velocity)×25×0.20) + ((1 - return_rate/100)×0.15)
        $marginPct   = (float) ($sku->erp_margin_pct ?? 0);
        $cppc        = (float) ($sku->erp_cppc ?? 0);
        $velocity90d = (float) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
        $returnPct   = (float) ($sku->erp_return_rate_pct ?? 0);

        $safeCppc     = max($cppc, 0.001);
        $safeVelocity = max($velocity90d, 0.001);

        // Tier score: (margin_pct * 0.40) + (cppc_pct * 0.35) + (velocity_pct * 0.25)
        // margin_pct, cppc_pct, velocity_pct are 0–100 raw percentile values from ERP.
        // SOURCE: CIE_v231_Developer_Build_Pack — Tier Calculation
        // Note: margin stored 0–100; term below uses /100 so margin contributes 0–0.4 to composite (compatible with percentile cutoffs).
        // SOURCE: CIE_v231_Developer_Build_Pack.pdf — Tier Scoring Formula. Return-rate term: ((1 - return_rate_pct/100) × 100 × 0.15)
        $score =
            ($marginPct / 100.0) * 0.40
            + ((1.0 / $safeCppc) * 10.0 * 0.25)
            + (log10($safeVelocity) * 25.0 * 0.20)
            + ((1.0 - ($returnPct / 100.0)) * 100.0 * 0.15);

        return round((float) $score, 4);
    }

    // calculateTierForSku is now inlined into recalculateAllTiers with percentile rules
 
    private function shouldBeKilled(Sku $sku): bool
    {
        $profitabilityThreshold = 5.0; // §5.3: tier.profitability_min_margin_pct not in 52 rules; hard-coded

        if ((float) ($sku->erp_margin_pct ?? 0) < $profitabilityThreshold) {
            return true;
        }
        $noSaleDays = 90; // §5.3: tier.kill_no_sale_days not in 52 rules; hard-coded
        $cutoff = new \DateTime('-' . $noSaleDays . ' days');
        if ($sku->last_sale_date && strtotime($sku->last_sale_date) < $cutoff->getTimestamp()) {
            return true;
        }
        $zeroVelThreshold = 0; // §5.3: tier.kill_zero_velocity_threshold not in 52 rules; hard-coded
        if ((int) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0) <= $zeroVelThreshold) {
            return true;
        }
        return false;
    }

    private function updateSkuTier(Sku $sku, TierType $oldTier, TierType $newTier): void
    {
        $velocity = $sku->erp_velocity_90d ?? $sku->annual_volume;
        $rationale = sprintf(
            'Margin: %.1f%%, Velocity_90d: %d units',
            $sku->erp_margin_pct,
            $velocity
        );
        $sku->update(['tier' => $newTier, 'tier_rationale' => $rationale]);

        $channelGovernor = app(ChannelGovernorService::class);
        $channelGovernor->recalculateAndPersist($sku);

        \App\Models\TierHistory::create([
            'sku_id'        => $sku->id,
            'old_tier'      => $oldTier,
            'new_tier'      => $newTier,
            'reason'        => $rationale,
            'margin_percent'=> $sku->erp_margin_pct,
            'annual_volume' => $velocity,
            'changed_by'    => auth()->id(),
        ]);
        \App\Models\AuditLog::create([
            'entity_type' => 'tier',
            'entity_id'   => $sku->id,
            'action'      => 'tier_change',
            'actor_id'    => 'SYSTEM',
            'old_value'   => $oldTier,
            'new_value'   => $newTier,
            'reason'      => $rationale,
            'created_at'  => now(),
        ]);
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
