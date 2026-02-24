<?php
namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;

class MaturityScoreService
{
    /**
     * Calculate 4-part Maturity Score for Golden Set validation
     * Pillars: Core (40pts), Authority (20pts), Channel (25pts), AI Visibility (15pts)
     */
    public function calculate(Sku $sku): array
    {
        $core = $this->calculateCoreScore($sku);           // max 40
        $authority = $this->calculateAuthorityScore($sku); // max 20
        $channel = $this->calculateChannelScore($sku);     // max 25
        $aiVisibility = ($sku->ai_citation_rate / 100) * 15; // max 15

        $total = round($core + $authority + $channel + $aiVisibility);

        $level = 'Bronze';
        if ($total >= 80) $level = 'Gold';
        elseif ($total >= 60) $level = 'Silver';

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
        $points = 0;
        if ($sku->primary_cluster_id) $points += 10;
        if ($sku->primary_intent) $points += 10;
        $answerBlockMin = (int) BusinessRules::get('g4.answer_block_min', 250);
        if (strlen($sku->ai_answer_block ?? '') >= $answerBlockMin) $points += 10;
        if ($sku->best_for && $sku->not_for) $points += 10;
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
        // Placeholder: Average readiness across 4 channels
        // In reality, this would query the ChannelGovernorService
        return ($sku->readiness_score / 100) * 25;
    }
}
