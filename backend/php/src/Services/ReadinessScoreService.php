<?php

namespace App\Services;

use App\Models\Sku;
use App\Enums\TierType;
use App\Support\BusinessRules;

/**
 * CIE v2.3.2 — Readiness score (0-100) per channel per SKU.
 * SOURCE: CLAUDE.md §15 — scoring model is Content Health Score (CHS).
 * This service delegates to ContentHealthScoreService and maps CHS to the existing
 * readiness API shape (overall, channels, components) for backward compatibility.
 */
class ReadinessScoreService
{
    /** SOURCE: CLAUDE.md §4 DECISION-001; openapi.yaml ReadinessResponse */
    private const CHANNELS = ['shopify', 'gmc'];

    public function __construct(private ChannelGovernorService $channelGovernorService)
    {
    }

    /**
     * Compute readiness for one SKU using Content Health Score (CHS) per CLAUDE.md §15.
     * Returns existing shape: overall 0-100 (CHS), per-channel scores (CHS), and CHS component breakdown.
     *
     * @return array{sku_id: string, tier: string, overall: int, max_possible: int, content_score: int, schema_score: int, commercial_score: int, components: array, channels: list<array{channel: string, score: int}>, chs_components?: array}
     */
    public function computeReadiness(Sku $sku): array
    {
        $tier = $this->normalizeTier($sku->tier);
        $assessment = $this->channelGovernorService->assess($sku);
        $channels = [];
        $scores = [];
        foreach (self::CHANNELS as $channel) {
            $row = $assessment[$channel] ?? ['score' => 0, 'decision' => 'SKIP', 'component_scores' => []];
            $score = (int) ($row['score'] ?? 0);
            $scores[] = $score;
            $channels[] = [
                'channel' => $channel,
                'score' => $score,
                'decision' => $row['decision'] ?? 'SKIP',
                'components' => $row['component_scores'] ?? [],
            ];
        }
        $overall = count($scores) > 0 ? (int) round(array_sum($scores) / count($scores)) : 0;
        // SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 3 — openapi.yaml component_scores naming (weights configured via BusinessRules in governor)
        $componentScores = $assessment['shopify']['component_scores'] ?? ($assessment['gmc']['component_scores'] ?? []);

        return [
            'sku_id'           => (string) $sku->id,
            'tier'             => $tier,
            'overall'          => $overall,
            'max_possible'     => 100,
            'content_score'    => $overall,
            'schema_score'     => $overall,
            'commercial_score' => $overall,
            'components'       => [],
            'channels'         => $channels,
            'component_scores' => [
                // SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 3; openapi.yaml ReadinessResponse component names
                'answer_block_score' => (int) ($componentScores['answer_block'] ?? 0),
                'faq_coverage_score' => (int) ($componentScores['faq_coverage'] ?? 0),
                'safety_depth_score' => (int) ($componentScores['safety_depth'] ?? 0),
                'cross_sku_comparison_score' => (int) ($componentScores['comparison_data'] ?? 0),
                'structured_data_score' => (int) ($componentScores['structured_data'] ?? 0),
                // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — display scale from BusinessRules
                'citation_score' => (int) (($componentScores['citation_score'] ?? 0) * (int) BusinessRules::get('readiness.citation_component_scale_multiplier', 10)),
            ],
            'active_channels' => (int) ($assessment['active_channels'] ?? 0),
            'deadline' => $assessment['deadline'] ?? ['breached' => false, 'days_since_publish' => null],
        ];
    }

    private function normalizeTier($tier): string
    {
        if ($tier instanceof TierType) {
            return strtolower($tier->value);
        }
        return strtolower(trim((string) $tier));
    }
}
