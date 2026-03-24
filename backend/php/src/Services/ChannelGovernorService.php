<?php
namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\DB;

class ChannelGovernorService
{
    // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §7.1 — four-channel readiness.
    private const CHANNELS = ['google_sge', 'amazon', 'ai_assistants', 'own_website'];

    /**
     * GMC feed inclusion (CHAN-02): Kill/Harvest excluded; Hero ≥85, Support ≥70.
     */
    public static function isEligibleForGMC(Sku $sku): bool
    {
        $tier = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower((string) ($sku->tier ?? ''));
        if (in_array($tier, ['kill', 'harvest'])) {
            return false;
        }
        $score = (int) ($sku->readiness_score ?? 0);
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — readiness thresholds via BusinessRules.
        $heroMin = (int) BusinessRules::get('readiness.hero_primary_channel_min');
        $supportMin = (int) BusinessRules::get('readiness.hero_all_channels_min');
        if ($tier === 'hero') {
            return $score >= $heroMin;
        }
        if ($tier === 'support') {
            return $score >= $supportMin;
        }
        return false;
    }

    /**
     * Patch 5: Channel Readiness Assessment. Channels: shopify, gmc only (Amazon deferred).
     */
    public function assess(Sku $sku): array
    {
        $tier = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower((string) ($sku->tier ?? ''));
        if ($tier === 'kill') {
            return $this->getKillSkipResponse();
        }

        $baseScore = max(0, min(100, (int) ($sku->readiness_score ?? 0)));
        $componentScores = $this->computeAiReadinessComponents($sku);

        $results = [];
        foreach (self::CHANNELS as $channel) {
            $score = $this->computeChannelScore($baseScore, $channel);
            $decision = $this->computeDecision($tier, $score, $channel);
            $results[$channel] = [
                'score' => $score,
                'decision' => $decision,
                'status' => $decision,
                'component_scores' => $componentScores,
            ];
        }
        $results['active_channels'] = count(array_filter($results, fn ($r, $k) => $k !== 'active_channels' && ($r['decision'] ?? 'SKIP') !== 'SKIP', ARRAY_FILTER_USE_BOTH));
        $results['deadline'] = $this->deadlineStatus($sku, $tier, $results);

        return $results;
    }

    public function recalculateAndPersist(Sku $sku): void
    {
        $fresh = $sku->fresh();
        $results = $this->assess($fresh);

        foreach ($results as $channel => $data) {
            if (!in_array($channel, self::CHANNELS, true)) {
                continue;
            }
            DB::table('channel_readiness')->updateOrInsert(
                ['sku_id' => $fresh->sku_code ?? $fresh->id, 'channel' => $channel],
                [
                    'score'            => $data['score'],
                    'component_scores' => json_encode($data['component_scores'] ?? [], JSON_UNESCAPED_SLASHES),
                    'computed_at'      => now(),
                    'updated_at'       => now(),
                ]
            );
        }
    }

    private function computeChannelScore(int $baseScore, string $channel): int
    {
        // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §7.1 — channel-specific scoring surface.
        $delta = match ($channel) {
            'own_website' => 10,
            'google_sge' => 7,
            'ai_assistants' => 0,
            'amazon' => -7,
            default => 0,
        };
        return max(0, min(100, $baseScore + $delta));
    }

    private function computeDecision(string $tier, int $score, string $channel): string
    {
        if ($tier === 'kill') {
            return 'SKIP';
        }

        $primaryChannel = 'own_website';
        $heroPrimaryMin = (int) BusinessRules::get('readiness.hero_primary_channel_min');
        $heroAllMin = (int) BusinessRules::get('readiness.hero_all_channels_min');
        $supportPrimaryMin = (int) BusinessRules::get('readiness.support_primary_channel_min');
        $supportAllMin = (int) BusinessRules::get('readiness.support_all_channels_min', BusinessRules::get('readiness.support_primary_channel_min'));

        if ($tier === 'hero') {
            if ($channel === $primaryChannel) {
                return $score >= $heroPrimaryMin ? 'COMPETE' : 'SKIP';
            }
            return $score >= $heroAllMin ? 'COMPETE' : 'SKIP';
        }
        if ($tier === 'support') {
            if ($channel === $primaryChannel) {
                return $score >= $supportPrimaryMin ? 'COMPETE' : 'SKIP';
            }
            return $score >= $supportAllMin ? 'COMPETE' : 'SKIP';
        }

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5
        $harvestThreshold = (int) BusinessRules::get('channel.harvest_threshold');
        return $score >= $harvestThreshold ? 'COMPETE' : 'SKIP';
    }

    private function getKillSkipResponse(): array
    {
        $skip = [
            'score' => 0,
            'decision' => 'SKIP',
            'status' => 'SKIP',
            'component_scores' => $this->emptyAiReadinessComponents(),
        ];
        return [
            'google_sge' => $skip,
            'amazon' => $skip,
            'ai_assistants' => $skip,
            'own_website' => $skip,
            'active_channels' => 0,
            'deadline' => ['breached' => false, 'days_since_publish' => null],
        ];
    }

    // SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 3 §3.2 — six-component AI readiness.
    private function computeAiReadinessComponents(Sku $sku): array
    {
        return [
            'answer_block' => $this->scoreAnswerBlock($sku),
            'faq_coverage' => $this->scoreFaqCoverage($sku),
            'safety_depth' => $this->scoreSafetyDepth($sku),
            'comparison_data' => $this->scoreComparisonData($sku),
            'structured_data' => $this->scoreStructuredData($sku),
            'citation_score' => $this->scoreCitationAudit($sku),
        ];
    }

    private function emptyAiReadinessComponents(): array
    {
        return [
            'answer_block' => 0,
            'faq_coverage' => 0,
            'safety_depth' => 0,
            'comparison_data' => 0,
            'structured_data' => 0,
            'citation_score' => 0,
        ];
    }

    private function scoreAnswerBlock(Sku $sku): int
    {
        $answer = trim((string) ($sku->ai_answer_block ?? ''));
        if ($answer === '') {
            return 0;
        }
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5
        $answerBlockMin = (int) BusinessRules::get('gates.answer_block_min_chars');
        $len = strlen($answer);
        return $len >= $answerBlockMin ? 25 : 15;
    }

    private function scoreFaqCoverage(Sku $sku): int
    {
        $faqRaw = $sku->faq_data ?? null;
        $faqArr = is_string($faqRaw) ? json_decode($faqRaw, true) : (is_array($faqRaw) ? $faqRaw : []);
        $count = is_array($faqArr) ? count($faqArr) : 0;
        if ($count >= 3) {
            return 20;
        }
        if ($count >= 1) {
            return 10;
        }
        return 0;
    }

    private function scoreSafetyDepth(Sku $sku): int
    {
        $text = strtolower((string) ($sku->expert_authority ?? ''));
        $signals = ['bs ', 'en ', 'iso', 'iec', 'ce', 'ukca', 'rohs'];
        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return 15;
            }
        }
        return $text !== '' ? 8 : 0;
    }

    private function scoreComparisonData(Sku $sku): int
    {
        $bestFor = trim((string) ($sku->best_for ?? ''));
        $notFor = trim((string) ($sku->not_for ?? ''));
        if ($bestFor !== '' && $notFor !== '') {
            return 15;
        }
        return ($bestFor !== '' || $notFor !== '') ? 8 : 0;
    }

    private function scoreStructuredData(Sku $sku): int
    {
        $hasWikidata = !empty($sku->wikidata_uri) || !empty($sku->wikidata_entities);
        return $hasWikidata ? 15 : 8;
    }

    private function scoreCitationAudit(Sku $sku): int
    {
        $citationRate = (float) ($sku->score_citation ?? $sku->ai_citation_rate ?? 0);
        $score = (int) round(max(0, min(100, $citationRate)) * 0.10);
        return max(0, min(10, $score));
    }

    // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §7.1 — 30-day readiness deadline.
    private function deadlineStatus(Sku $sku, string $tier, array $results): array
    {
        if ($tier !== 'hero' || empty($sku->last_published_at)) {
            return ['breached' => false, 'days_since_publish' => null];
        }

        $deadlineDays = (int) BusinessRules::get('readiness.deadline_days_after_completion');
        $daysSincePublish = now()->diffInDays($sku->last_published_at);
        $primaryChannel = 'own_website';
        $heroPrimaryMin = (int) BusinessRules::get('readiness.hero_primary_channel_min');
        $heroAllMin = (int) BusinessRules::get('readiness.hero_all_channels_min');

        $primaryScore = (int) ($results[$primaryChannel]['score'] ?? 0);
        $allChannelsMet = true;
        foreach (self::CHANNELS as $channel) {
            if ((int) ($results[$channel]['score'] ?? 0) < $heroAllMin) {
                $allChannelsMet = false;
                break;
            }
        }
        $breached = $daysSincePublish >= $deadlineDays && ($primaryScore < $heroPrimaryMin || !$allChannelsMet);

        return ['breached' => $breached, 'days_since_publish' => $daysSincePublish];
    }
}
