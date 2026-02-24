<?php

namespace App\Services;

use App\Models\Sku;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\Gates\G4_AnswerBlockGate;
use App\Validators\Gates\G2_ImagesGate;
use App\Utils\JsonLdRenderer;

/**
 * CIE v2.3.1 + v2.3.2 Patch 3 — Readiness score (0-100) per channel per SKU.
 * CIS point values from BusinessRules (readiness.*). For Harvest: only applicable gates (max 45), then normalise to 0-100.
 */
class ReadinessScoreService
{
    /** Default point values when BusinessRules not available. */
    private const DEFAULT_COMPONENTS = [
        'valid_cluster_id'           => 10,
        'has_primary_intent'        => 10,
        'has_secondary_intents'      => 10,
        'answer_block_passes_g4'     => 15,
        'best_for_not_for_populated' => 10,
        'expert_authority_present'   => 10,
        'json_ld_renders_valid'     => 10,
        'images_meet_channel'       => 10,
        'pricing_present'           => 10,
        'category_specific_complete' => 5,
    ];

    /** Harvest tier: only these components apply (max 45). */
    private const HARVEST_APPLICABLE = [
        'valid_cluster_id',
        'has_primary_intent',
        'has_secondary_intents',
        'pricing_present',
        'category_specific_complete',
    ];

    private const READINESS_KEYS = [
        'valid_cluster_id' => 'readiness.valid_cluster_id',
        'has_primary_intent' => 'readiness.has_primary_intent',
        'has_secondary_intents' => 'readiness.has_secondary_intents',
        'answer_block_passes_g4' => 'readiness.answer_block_passes_g4',
        'best_for_not_for_populated' => 'readiness.best_for_not_for_populated',
        'expert_authority_present' => 'readiness.expert_authority_present',
        'json_ld_renders_valid' => 'readiness.json_ld_renders_valid',
        'images_meet_channel' => 'readiness.images_meet_channel',
        'pricing_present' => 'readiness.pricing_present',
        'category_specific_complete' => 'readiness.category_specific_complete',
    ];

    /** Channels for dashboard (same base score; channel-specific thresholds applied by frontend). */
    private const CHANNELS = ['own_website', 'google_sge', 'amazon', 'ai_assistants'];

    private function getComponents(): array
    {
        $out = [];
        foreach (self::DEFAULT_COMPONENTS as $key => $default) {
            $ruleKey = self::READINESS_KEYS[$key] ?? 'readiness.' . $key;
            try {
                $out[$key] = (int) BusinessRules::get($ruleKey, $default);
            } catch (\Throwable $e) {
                $out[$key] = $default;
            }
        }
        return $out;
    }

    public function __construct(
        private G4_AnswerBlockGate $g4Gate,
        private G2_ImagesGate $g2Gate
    ) {
    }

    /**
     * Compute readiness for one SKU: components earned, overall 0-100, and per-channel scores.
     *
     * @return array{sku_id: string, tier: string, overall: int, max_possible: int, components: array<string, array{points_earned: int, points_max: int, applies: bool}>, channels: list<array{channel: string, score: int}>}
     */
    public function computeReadiness(Sku $sku): array
    {
        $sku->loadMissing(['primaryCluster', 'skuIntents.intent']);
        $tier = $this->normalizeTier($sku->tier);
        $isHarvest = $tier === 'harvest';

        $components = $this->evaluateComponents($sku, $isHarvest);

        $maxPossible = $isHarvest ? 45 : 100;
        $earned = 0;
        foreach ($components as $key => $data) {
            if ($data['applies']) {
                $earned += $data['points_earned'];
            }
        }

        // §11.3: Harvest = raw score (0-45), no normalization. Hero/Support = 0-100 scale
        if ($isHarvest) {
            $overall = min(45, max(0, $earned));
        } else {
            $overall = $maxPossible > 0
                ? (int) round(($earned / $maxPossible) * 100)
                : 0;
            $overall = min(100, max(0, $overall));
        }

        $channels = [];
        foreach (self::CHANNELS as $channel) {
            $modifier = 0;
            try {
                $modifier = (int) BusinessRules::get('readiness.channel_' . $channel . '_modifier', 0);
            } catch (\Throwable $e) {
                // keep 0
            }
            $channelScore = min(100, max(0, $overall + $modifier));
            $channels[] = ['channel' => $channel, 'score' => $channelScore];
        }

        // v2.3.2 Patch 3: Decomposed sub-scores for dashboard breakdown (content / schema / commercial)
        $subScores = $this->computeSubScores($components, $isHarvest);

        return [
            'sku_id'         => (string) $sku->id,
            'tier'           => $tier,
            'overall'        => $overall,
            'max_possible'   => $maxPossible,
            'content_score'  => $subScores['content_score'],
            'schema_score'   => $subScores['schema_score'],
            'commercial_score' => $subScores['commercial_score'],
            'components'     => $components,
            'channels'       => $channels,
        ];
    }

    /**
     * v2.3.2 Patch 3: Break readiness into content_score, schema_score, commercial_score (0-100 each).
     */
    private function computeSubScores(array $components, bool $isHarvest): array
    {
        $contentKeys = [
            'valid_cluster_id', 'has_primary_intent', 'has_secondary_intents',
            'answer_block_passes_g4', 'best_for_not_for_populated', 'expert_authority_present',
            'category_specific_complete',
        ];
        $schemaKeys = ['json_ld_renders_valid'];
        $commercialKeys = ['pricing_present', 'images_meet_channel'];

        $score = fn(array $keys) => array_reduce($keys, function ($sum, $k) use ($components) {
            $d = $components[$k] ?? null;
            return $sum + ($d && ($d['applies'] ?? false) ? ($d['points_earned'] ?? 0) : 0);
        }, 0);
        $max = fn(array $keys) => array_reduce($keys, function ($sum, $k) use ($components) {
            $d = $components[$k] ?? null;
            return $sum + ($d && ($d['applies'] ?? false) ? ($d['points_max'] ?? 0) : 0);
        }, 0);

        $contentEarned = $score($contentKeys);
        $contentMax = $max($contentKeys);
        $schemaEarned = $score($schemaKeys);
        $schemaMax = $max($schemaKeys);
        $commercialEarned = $score($commercialKeys);
        $commercialMax = $max($commercialKeys);

        $norm = static function ($earned, $max) {
            if ($max <= 0) return 0;
            return min(100, (int) round(($earned / $max) * 100));
        };

        return [
            'content_score'    => $norm($contentEarned, $contentMax),
            'schema_score'     => $norm($schemaEarned, $schemaMax),
            'commercial_score' => $norm($commercialEarned, $commercialMax),
        ];
    }

    /**
     * Evaluate each component: points earned, max, and whether it applies for this tier.
     */
    private function evaluateComponents(Sku $sku, bool $isHarvest): array
    {
        $out = [];
        $components = $this->getComponents();

        foreach ($components as $key => $maxPoints) {
            $applies = !$isHarvest || in_array($key, self::HARVEST_APPLICABLE, true);
            $earned = $applies ? $this->scoreComponent($sku, $key, $maxPoints) : 0;
            $out[$key] = [
                'points_earned' => $earned,
                'points_max'    => $applies ? $maxPoints : 0,
                'applies'       => $applies,
            ];
        }

        return $out;
    }

    /**
     * Score a single component. Uses graduated scoring where applicable (e.g. best_for count, secondary intents).
     * Spec: Hero min 2 best_for, min 1 not_for for full points.
     */
    private function scoreComponent(Sku $sku, string $key, int $maxPoints): int
    {
        $tier = $this->normalizeTier($sku->tier);

        switch ($key) {
            case 'valid_cluster_id':
                return (!empty($sku->primary_cluster_id) && $sku->primaryCluster) ? $maxPoints : 0;
            case 'has_primary_intent':
                $primary = $sku->skuIntents->where('is_primary', true)->first();
                return $primary !== null ? $maxPoints : 0;
            case 'has_secondary_intents':
                $secondary = $sku->skuIntents->where('is_primary', false);
                $n = $secondary->count();
                if ($n === 0) return 0;
                if ($n === 1) return (int) round($maxPoints * 0.5);
                return $maxPoints;
            case 'answer_block_passes_g4':
                $result = $this->g4Gate->validate($sku);
                return $result->passed ? $maxPoints : 0;
            case 'best_for_not_for_populated':
                return $this->scoreBestForNotFor($sku, $maxPoints, $tier);
            case 'expert_authority_present':
                $auth = trim((string) ($sku->expert_authority_name ?? $sku->expert_authority ?? ''));
                return $auth !== '' ? $maxPoints : 0;
            case 'json_ld_renders_valid':
                $html = JsonLdRenderer::renderCieJsonld($sku);
                return ($html !== '' && str_contains($html, '"@type":"Product"')) ? $maxPoints : 0;
            case 'images_meet_channel':
                try {
                    $result = $this->g2Gate->validate($sku);
                    return $result->passed ? $maxPoints : 0;
                } catch (\Throwable) {
                    return 0;
                }
            case 'pricing_present':
                $price = $sku->current_price ?? $sku->price ?? null;
                return ($price !== null && (float) $price > 0) ? $maxPoints : 0;
            case 'category_specific_complete':
                $cluster = $sku->primaryCluster;
                $passed = $cluster && (
                    (!empty($cluster->category)) ||
                    (!empty($cluster->name))
                );
                return $passed ? $maxPoints : 0;
            default:
                return 0;
        }
    }

    /**
     * Graduated best_for/not_for: Hero requires min 2 best_for, min 1 not_for (spec).
     * Other tiers: both non-empty = full; one empty = half; both empty = 0.
     * Handles best_for/not_for stored as JSON array (e.g. ["Item one","Item two"]) or comma-separated string.
     */
    private function scoreBestForNotFor(Sku $sku, int $maxPoints, string $tier): int
    {
        $bestForCount = self::countListAttribute($sku->best_for);
        $notForCount = self::countListAttribute($sku->not_for);

        if ($tier === 'hero') {
            if ($bestForCount >= 2 && $notForCount >= 1) return $maxPoints;
            if ($bestForCount >= 1 && $notForCount >= 1) return (int) round($maxPoints * 0.5);
            return 0;
        }

        if ($bestForCount >= 1 && $notForCount >= 1) return $maxPoints;
        if ($bestForCount >= 1 || $notForCount >= 1) return (int) round($maxPoints * 0.5);
        return 0;
    }

    /** Count items in best_for/not_for whether stored as JSON array or comma/whitespace-separated string. */
    private static function countListAttribute($value): int
    {
        if (is_array($value)) {
            return count(array_filter($value));
        }
        $raw = trim((string) ($value ?? ''));
        if ($raw !== '' && (str_starts_with($raw, '[') || str_starts_with($raw, '{'))) {
            $decoded = json_decode($value ?? '[]', true);
            if (is_array($decoded)) {
                return count(array_filter($decoded));
            }
        }
        return count(array_filter(preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY)));
    }

    private function normalizeTier($tier): string
    {
        if ($tier instanceof TierType) {
            return strtolower($tier->value);
        }
        return strtolower(trim((string) $tier));
    }
}
