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
        $core = $this->calculateCoreScore($sku);           // max 40
        $authority = $this->calculateAuthorityScore($sku); // max 20
        $channel = $this->calculateChannelScore($sku);     // max 25
        $aiVisibilityMax = 15; // §5.3: not in 52 rules; hard-coded
        $aiVisibility = ($sku->ai_citation_rate / 100) * $aiVisibilityMax;

        $total = round($core + $authority + $channel + $aiVisibility);

        $goldThreshold   = (int) BusinessRules::get('readiness.gold_threshold');
        $silverThreshold = (int) BusinessRules::get('readiness.silver_threshold');
        $level = 'Bronze';
        if ($total >= $goldThreshold) $level = 'Gold';
        elseif ($total >= $silverThreshold) $level = 'Silver';

        return [
            'total' => (int)$total,
            'level' => $level,
            'breakdown' => [
                'core' => round($core, 1),
                'authority' => round($authority, 1),
                'channel' => round($channel, 1),
                'ai_visibility' => round($aiVisibility, 1)
            ]
        ];
    }

    private function calculateCoreScore(Sku $sku): float
    {
        $pillarPts = 10; // §5.3: maturity.core_score_pillar_pts not in 52 rules; hard-coded
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
        $points = 0;
        if ($sku->expert_statement) $points += 10;
        if ($sku->certifications_detail) $points += 5;
        if ($sku->wikidata_entities) $points += 5;
        return $points;
    }

    private function calculateChannelScore(Sku $sku): float
    {
        $channelMax = 25; // §5.3: maturity.channel_max_pts not in 52 rules; hard-coded
        return ($sku->readiness_score / 100) * $channelMax;
    }
}
