<?php
namespace App\Services;

// SOURCE: CIE_Master_Developer_Build_Spec.docx §8.1 / §5.3; CLAUDE.md §7
// SOURCE: CIE_Integration_Specification.pdf §1.2, §1.3; CIE_v231_Developer_Build_Pack.pdf (sku_master schema)
// SOURCE: CIE_v231_Developer_Build_Pack.pdf §7.1; CIE_Master_Developer_Build_Spec.docx §5; CIE_Integration_Specification.pdf §1.3
// SOURCE: CIE_Master_Developer_Build_Spec.docx §5; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.2; CIE_Integration_Specification.pdf §1.3
use App\Models\Sku;
use App\Enums\TierType;
use App\Support\BusinessRules;

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

        // Cohort max velocity_90d used for normalisation (legacy param to calculateCommercialScore).
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — tier.velocity_normalisation_min from BusinessRules
        // FIX: TS-03 — avoid literal 1.0 in tier engine
        $maxVelocity = (float) $allSkus->max(function (Sku $sku) {
            return (float) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
        });
        $normMin = (int) BusinessRules::get('tier.velocity_normalisation_min', 1);
        if ($maxVelocity <= 0) {
            $maxVelocity = (float) $normMin;
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

            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 9.2 — auto-promotion velocity threshold
            // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5 — zero hard-coded values
            // Auto-promotion rule: Harvest SKUs with > configured QoQ velocity increase become Support.
            if ($oldTier === TierType::HARVEST && $newTier === TierType::HARVEST) {
                $previousVelocity = (int) ($sku->previous_velocity_90d ?? 0);
                $currentVelocity  = (int) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
                if ($previousVelocity > 0) {
                    $growth = ($currentVelocity - $previousVelocity) / $previousVelocity;
                    $autoPromotionThreshold = (float) BusinessRules::get('tier.auto_promotion_velocity_threshold');
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

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §3.2; CIE_Master_Developer_Build_Spec.docx §8.1
     * FIX: TS-03 — Authoritative commercial priority formula (shared with TierController::erpSync).
     */
    public static function commercialPriorityScore(
        float $marginPct,
        float $cppc,
        float $velocity,
        float $returnPct
    ): float {
        $wMargin = (float) BusinessRules::get('tier.margin_weight');
        $wCppc = (float) BusinessRules::get('tier.cppc_weight');
        $wVelocity = (float) BusinessRules::get('tier.velocity_weight');
        $wReturns = (float) BusinessRules::get('tier.returns_weight');
        $cppcScale = (float) BusinessRules::get('tier.cppc_inverse_scale');
        $velLogScale = (float) BusinessRules::get('tier.velocity_log_scale');
        $cppcFloor = (float) BusinessRules::get('tier.cppc_floor');
        $velocityFloor = (float) BusinessRules::get('tier.velocity_floor');

        $safeCppc = max($cppc, $cppcFloor);
        $safeVelocity = max($velocity, $velocityFloor);

        $score =
            ($marginPct / 100) * $wMargin
            + ((1 / $safeCppc) * $cppcScale * $wCppc)
            + (log10($safeVelocity) * $velLogScale * $wVelocity)
            + ((1 - ($returnPct / 100)) * $wReturns);

        return (float) $score;
    }

    public function calculateCommercialScore(Sku $sku, float $maxVelocity): float
    {
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — same inputs as ERP sync (margin_percent + erp_* aliases)
        // FIX: TS-03 — delegate to commercialPriorityScore; return term aligned with TierController (no ×100)
        $marginPct = (float) ($sku->erp_margin_pct ?? $sku->margin_percent ?? 0);
        $cppc = (float) ($sku->erp_cppc ?? 0);
        $velocity90d = (float) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
        $returnPct = (float) ($sku->erp_return_rate_pct ?? 0);

        $score = self::commercialPriorityScore($marginPct, $cppc, $velocity90d, $returnPct);

        return round((float) $score, 4);
    }

    // calculateTierForSku is now inlined into recalculateAllTiers with percentile rules
 
    private function shouldBeKilled(Sku $sku): bool
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5 — zero hard-coded values
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 3.1 — Kill = Negative margin, NNV
        $profitabilityThreshold = (float) BusinessRules::get('tier.kill_margin_floor');

        if ((float) ($sku->erp_margin_pct ?? 0) < $profitabilityThreshold) {
            return true;
        }
        $noSaleDays = (int) BusinessRules::get('tier.kill_no_sale_days');
        $cutoff = new \DateTime('-' . $noSaleDays . ' days');
        if ($sku->last_sale_date && strtotime($sku->last_sale_date) < $cutoff->getTimestamp()) {
            return true;
        }
        $zeroVelThreshold = (int) BusinessRules::get('tier.kill_zero_velocity_threshold');
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
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — audit_log on tier change
        // FIX: TS-04 — entity_type sku (aligned with TierController::erpSync)
        \App\Models\AuditLog::create([
            'entity_type' => 'sku',
            'entity_id'   => $sku->id,
            'action'      => 'tier_change',
            'field_name'  => 'tier',
            'old_value'   => $oldTier->value ?? (string) $oldTier,
            'new_value'   => $newTier->value ?? (string) $newTier,
            'actor_id'    => 'SYSTEM',
            'actor_role'  => 'system',
            'timestamp'   => now(),
            'created_at'  => now(),
        ]);
    }

}
