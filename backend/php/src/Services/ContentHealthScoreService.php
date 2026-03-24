<?php

namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;
use App\Utils\JsonLdRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Content Health Score (CHS) per CLAUDE.md §15.
 * Single score 0–100 from exactly 5 weighted components:
 * - Intent Alignment 25% (Gate G2 + G3 validation outcome)
 * - Semantic Coverage 20% (Vector similarity score)
 * - Technical SEO 20% (Meta length, schema presence, alt text, slug)
 * - Competitive Gap 20% (100 - gap_keywords/total_keywords×100); "No Data" if no Semrush data
 * - AI Readiness 15% (AI audit citation scores 0–3 scale → 0–100)
 *
 * When Competitive Gap is "No Data", the other 4 components are scaled to 80% total (CHS max 80).
 */
class ContentHealthScoreService
{
    /** Sentinel for Competitive Gap when SKU has no Semrush data. */
    public const COMPETITIVE_GAP_NO_DATA = 'No Data';

    /**
     * Calculate CHS (0–100) and component breakdown.
     *
     * @return array{chs: float, components: array{intent_alignment: float, semantic_coverage: float, technical_seo: float, competitive_gap: float|string, ai_readiness: float}, competitive_gap_no_data: bool}
     */
    public function calculateCHS(Sku $sku): array
    {
        $sku->loadMissing(['primaryCluster', 'skuIntents.intent']);

        // SOURCE: CLAUDE.md §15; CIE_Master_Developer_Build_Spec.docx §5.3 — CHS weights from business_rules
        // FIX: TS-03 addendum — replace hard-coded CHS weights (040_seed: chs.*_weight keys)
        $wIntent = (float) BusinessRules::get('chs.intent_alignment_weight');
        $wSemantic = (float) BusinessRules::get('chs.semantic_coverage_weight');
        $wTechnical = (float) BusinessRules::get('chs.technical_hygiene_weight');
        $wCompetitive = (float) BusinessRules::get('chs.competitive_gap_weight');
        $wAi = (float) BusinessRules::get('chs.ai_readiness_weight');

        $intent = $this->getIntentAlignmentScore($sku);
        $semantic = $this->getSemanticCoverageScore($sku);
        $technical = $this->getTechnicalSEOScore($sku);
        $competitive = $this->getCompetitiveGapScore($sku);
        $ai = $this->getAIReadinessScore($sku);

        $noData = $competitive === self::COMPETITIVE_GAP_NO_DATA;
        if ($noData) {
            // Scale the four scored components to 0–100 when competitive gap is "No Data" (CLAUDE.md §15).
            $sumFourWeights = $wIntent + $wSemantic + $wTechnical + $wAi;
            $weightedSum = $intent * $wIntent
                + $semantic * $wSemantic
                + $technical * $wTechnical
                + $ai * $wAi;
            $chs = $sumFourWeights > 0 ? ($weightedSum / $sumFourWeights) : 0;
            $competitiveValue = self::COMPETITIVE_GAP_NO_DATA;
        } else {
            $chs = $intent * $wIntent
                + $semantic * $wSemantic
                + $technical * $wTechnical
                + (float) $competitive * $wCompetitive
                + $ai * $wAi;
            $competitiveValue = (float) $competitive;
        }

        $chs = min(100.0, max(0.0, round($chs, 2)));

        return [
            'chs'                      => $chs,
            'components'               => [
                'intent_alignment'     => $intent,
                'semantic_coverage'    => $semantic,
                'technical_seo'        => $technical,
                'competitive_gap'      => $competitiveValue,
                'ai_readiness'         => $ai,
            ],
            'competitive_gap_no_data'  => $noData,
        ];
    }

    /**
     * Intent Alignment 0–100 from Gate G2 + G3 (G2_INTENT, G3_SECONDARY_INTENT) latest outcome.
     */
    private function getIntentAlignmentScore(Sku $sku): float
    {
        $gates = ['G2_INTENT', 'G3_SECONDARY_INTENT'];
        $passed = 0;
        foreach ($gates as $gate) {
            $latest = DB::table('validation_logs')
                ->where('sku_id', $sku->id)
                ->where('gate_type', $gate)
                ->orderByDesc('validated_at')
                ->limit(1)
                ->value('passed');
            if ($latest) {
                $passed++;
            }
        }
        return $passed / count($gates) * 100.0;
    }

    /**
     * Semantic Coverage 0–100 from latest vector similarity score (0–1).
     */
    private function getSemanticCoverageScore(Sku $sku): float
    {
        $similarity = DB::table('validation_logs')
            ->where('sku_id', $sku->id)
            ->where('gate_type', 'G4_VECTOR')
            ->whereNotNull('similarity_score')
            ->orderByDesc('validated_at')
            ->limit(1)
            ->value('similarity_score');
        if ($similarity === null) {
            return 0.0;
        }
        return min(100.0, (float) $similarity * 100.0);
    }

    /**
     * Technical SEO 0–100: meta length, schema presence, slug (sku_code).
     */
    private function getTechnicalSEOScore(Sku $sku): float
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — no silent fallback defaults.
        $maxTitle = (int) BusinessRules::get('gates.meta_title_max_chars');
        $maxDesc = (int) BusinessRules::get('gates.meta_description_max_chars');
        $minDesc = (int) BusinessRules::get('gates.meta_description_min_chars');

        $score = 0.0;
        $parts = 4;
        $perPart = 100.0 / $parts;

        // Meta title
        $titleLen = strlen(trim((string) ($sku->meta_title ?? '')));
        if ($titleLen > 0 && $titleLen <= $maxTitle) {
            $score += $perPart;
        } elseif ($titleLen > 0) {
            $score += $perPart * ($maxTitle / max($titleLen, 1));
        }

        // Meta description
        $descLen = strlen(trim((string) ($sku->meta_description ?? '')));
        if ($descLen >= $minDesc && $descLen <= $maxDesc) {
            $score += $perPart;
        } elseif ($descLen > 0) {
            $score += $perPart * min(1.0, $descLen / $minDesc);
        }

        // Schema presence
        $html = JsonLdRenderer::renderCieJsonld($sku);
        if ($html !== '' && str_contains($html, '"@type":"Product"')) {
            $score += $perPart;
        }

        // Slug / sku_code
        if (!empty(trim((string) ($sku->sku_code ?? '')))) {
            $score += $perPart;
        }

        return min(100.0, $score);
    }

    /**
     * Competitive Gap 0–100 or sentinel "No Data". Formula: 100 - (gap_keywords/total_keywords×100).
     * gap_keywords = keywords where position null or > 10 (not in top 10).
     */
    private function getCompetitiveGapScore(Sku $sku): float|string
    {
        if (!Schema::hasTable('semrush_imports')) {
            return self::COMPETITIVE_GAP_NO_DATA;
        }
        $skuCode = trim((string) ($sku->sku_code ?? ''));
        if ($skuCode === '') {
            return self::COMPETITIVE_GAP_NO_DATA;
        }

        $total = (int) DB::table('semrush_imports')
            ->where('sku_code', $skuCode)
            ->count();
        if ($total === 0) {
            return self::COMPETITIVE_GAP_NO_DATA;
        }

        $gap = (int) DB::table('semrush_imports')
            ->where('sku_code', $skuCode)
            ->where(function ($q) {
                $q->whereNull('position')->orWhere('position', '>', 10);
            })
            ->count();

        $pct = $total > 0 ? ($gap / $total) * 100 : 0;
        return min(100.0, max(0.0, 100.0 - $pct));
    }

    /**
     * AI Readiness 0–100 from AI audit citation scores (0–3 scale). Uses latest run per SKU.
     */
    private function getAIReadinessScore(Sku $sku): float
    {
        if (!Schema::hasTable('ai_audit_results')) {
            return 0.0;
        }
        $avg = DB::table('ai_audit_results')
            ->where('cited_sku_id', $sku->id)
            ->avg('score');
        if ($avg === null) {
            return 0.0;
        }
        // score is 0–3 → 0–100
        return min(100.0, ((float) $avg / 3.0) * 100.0);
    }
}
