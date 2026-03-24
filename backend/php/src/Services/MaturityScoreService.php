<?php
namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;

class MaturityScoreService
{
    /**
     * Calculate 4-part Maturity Score for Golden Set validation
     * Pillars: Core (40pts), Authority (20pts), Channel (25pts), AI Visibility (15pts)
     *
     * FAIL-6b — MISSING FROM SEED DATA: The following rule keys must be added to
     * 040_seed_business_rules.sql and §5.3 by the architect before this service can be de-hardcoded:
     *   maturity.ai_visibility_max_pts   | integer | 15  | points | AI Visibility pillar max score
     *   maturity.channel_max_pts         | integer | 25  | points | Channel Readiness pillar max score
     *   maturity.core_score_max_pts      | integer | 40  | points | Core content score pillar max
     * Once seeded, replace: $aiVisMax = (int) BusinessRules::get('maturity.ai_visibility_max_pts');
     * $channelMax = (int) BusinessRules::get('maturity.channel_max_pts');
     * $coreMax = (int) BusinessRules::get('maturity.core_score_max_pts');
     * and substitute in place of 15, 25, and the 10×4 accumulation.
     */
    public function calculate(Sku $sku): array
    {
        return $this->computeMaturity($sku);
    }

    // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §1 fixture 1
    // FIX: TS-15 — Short keys aligned with golden expected_outputs.maturity (level, core_fields, total, …).
    public function compute(Sku $sku): array
    {
        $m = $this->computeMaturity($sku);

        return [
            'level' => $m['maturity_level'],
            'core_fields' => $m['core_fields_score'],
            'authority' => $m['authority_score'],
            'channel_readiness' => $m['channel_readiness_score'],
            'ai_visibility' => $m['ai_visibility_score'],
            'total' => $m['total_maturity'],
        ];
    }

    // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.3 — maturity scoring components and kill exclusion.
    // FIX: TS-16 — Maturity labels use title case (Bronze, Silver, Gold, Excluded) per golden pack convention.
    public function computeMaturity(Sku $sku): array
    {
        $tier = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower((string) ($sku->tier ?? ''));
        if ($tier === 'kill') {
            return [
                'core_fields_score' => null,
                'authority_score' => null,
                'channel_readiness_score' => null,
                'ai_visibility_score' => null,
                'total_maturity' => null,
                'maturity_level' => 'Excluded',
            ];
        }

        $core = (int) round($this->calculateCoreScore($sku));
        $authority = (int) round($this->calculateAuthorityScore($sku));
        $channel = (int) round($this->calculateChannelScore($sku));
        $aiVisibility = (int) round($this->calculateAiVisibilityScore($sku));
        $total = $core + $authority + $channel + $aiVisibility;

        $goldThreshold = (int) BusinessRules::get('readiness.gold_threshold');
        $silverThreshold = (int) BusinessRules::get('readiness.silver_threshold');
        $level = match (true) {
            $total >= $goldThreshold => 'Gold',
            $total >= $silverThreshold => 'Silver',
            default => 'Bronze',
        };

        return [
            'core_fields_score' => $core,
            'authority_score' => $authority,
            'channel_readiness_score' => $channel,
            'ai_visibility_score' => $aiVisibility,
            'total_maturity' => $total,
            'maturity_level' => $level,
        ];
    }

    private function calculateCoreScore(Sku $sku): float
    {
        // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.3 — Core pillar points from business_rules.
        $pillarPts = (int) BusinessRules::get('maturity.core_pillar_points');
        $points = 0;
        if ($sku->primary_cluster_id) $points += $pillarPts;
        if ($sku->primary_intent) $points += $pillarPts;
        $answerBlockMin = (int) BusinessRules::get('gates.answer_block_min_chars');
        if (strlen($sku->ai_answer_block ?? '') >= $answerBlockMin) $points += $pillarPts;
        if ($sku->best_for && $sku->not_for) $points += $pillarPts;
        return $points;
    }

    private function calculateAuthorityScore(Sku $sku): float
    {
        // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.3 — Authority component scoring from business_rules.
        $expertPts = (int) BusinessRules::get('maturity.authority_expert_points');
        $wikidataPts = (int) BusinessRules::get('maturity.authority_wikidata_points');
        $certPts = (int) BusinessRules::get('maturity.authority_cert_points');
        $points = 0;
        if ($sku->expert_statement) $points += $expertPts;
        if ($sku->certifications_detail) $points += $certPts;
        if ($sku->wikidata_entities) $points += $wikidataPts;
        return $points;
    }

    private function calculateChannelScore(Sku $sku): float
    {
        // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.3 — Channel component max points from business_rules.
        $channelMax = (int) BusinessRules::get('maturity.channel_max');
        return ($sku->readiness_score / 100) * $channelMax;
    }

    private function calculateAiVisibilityScore(Sku $sku): float
    {
        // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.3 — AI visibility max points from business_rules.
        $aiVisibilityMax = (int) BusinessRules::get('maturity.ai_visibility_max');
        $citationRate = (float) ($sku->ai_citation_rate ?? $sku->score_citation ?? 0);
        return (max(0, min(100, $citationRate)) / 100.0) * $aiVisibilityMax;
    }
}
