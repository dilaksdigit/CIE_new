<?php

namespace App\Services;

use App\Models\Sku;
use App\Enums\TierType;

/**
 * CIE v2.3.2 — Readiness score (0-100) per channel per SKU.
 * SOURCE: CLAUDE.md §15 — scoring model is Content Health Score (CHS).
 * This service delegates to ContentHealthScoreService and maps CHS to the existing
 * readiness API shape (overall, channels, components) for backward compatibility.
 */
class ReadinessScoreService
{
    /** Channels for dashboard (v2.3.2: shopify + gmc only per CLAUDE.md Section 4 — DECISION-001). */
    private const CHANNELS = ['shopify', 'gmc'];

    public function __construct(
        private ContentHealthScoreService $contentHealthScoreService
    ) {
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
        $chsResult = $this->contentHealthScoreService->calculateCHS($sku);
        $chs = (int) round($chsResult['chs']);
        $chs = min(100, max(0, $chs));

        $channels = [];
        foreach (self::CHANNELS as $channel) {
            $channels[] = ['channel' => $channel, 'score' => $chs];
        }

        // Map CHS components to legacy component shape for compatibility; sub-scores derived from CHS components
        $comp = $chsResult['components'];
        $contentScore = (int) round(($comp['intent_alignment'] + $comp['semantic_coverage']) / 2);
        $schemaScore = (int) round($comp['technical_seo']);
        $competitiveNum = is_numeric($comp['competitive_gap']) ? (float) $comp['competitive_gap'] : 0.0;
        $commercialScore = (int) round(($competitiveNum + $comp['ai_readiness']) / 2);

        return [
            'sku_id'           => (string) $sku->id,
            'tier'             => $tier,
            'overall'          => $chs,
            'max_possible'     => 100,
            'content_score'    => $contentScore,
            'schema_score'     => $schemaScore,
            'commercial_score' => $commercialScore,
            'components'       => $this->chsToLegacyComponents($comp),
            'channels'         => $channels,
            'chs_components'   => $comp,
            'competitive_gap_no_data' => $chsResult['competitive_gap_no_data'],
        ];
    }

    /**
     * Map CHS component breakdown to legacy components array shape (for API/dashboard compatibility).
     */
    private function chsToLegacyComponents(array $comp): array
    {
        return [
            'intent_alignment'     => ['points_earned' => (int) round($comp['intent_alignment']), 'points_max' => 100, 'applies' => true],
            'semantic_coverage'    => ['points_earned' => (int) round($comp['semantic_coverage']), 'points_max' => 100, 'applies' => true],
            'technical_seo'        => ['points_earned' => (int) round($comp['technical_seo']), 'points_max' => 100, 'applies' => true],
            'competitive_gap'      => ['points_earned' => is_numeric($comp['competitive_gap']) ? (int) round($comp['competitive_gap']) : 0, 'points_max' => 100, 'applies' => true, 'no_data' => $comp['competitive_gap'] === ContentHealthScoreService::COMPETITIVE_GAP_NO_DATA],
            'ai_readiness'         => ['points_earned' => (int) round($comp['ai_readiness']), 'points_max' => 100, 'applies' => true],
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
