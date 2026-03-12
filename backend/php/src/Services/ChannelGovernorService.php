<?php
namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\DB;

class ChannelGovernorService
{
    /**
     * GMC feed inclusion (CHAN-02): Kill/Harvest excluded; Hero ≥85, Support ≥70.
     */
    public static function isEligibleForGMC(Sku $sku): bool
    {
        $tier = strtolower((string) ($sku->tier ?? ''));
        if (in_array($tier, ['kill', 'harvest'])) {
            return false;
        }
        $score = (int) ($sku->readiness_score ?? 0);
        if ($tier === 'hero') {
            return $score >= 85;
        }
        if ($tier === 'support') {
            return $score >= 70;
        }
        return false;
    }

    /**
     * Patch 5: Channel Readiness Assessment. Channels: shopify, gmc only (Amazon deferred).
     */
    public function assess(Sku $sku): array
    {
        if ($sku->tier === 'kill' || $sku->tier === 'harvest') {
            return $this->getSkipResponse();
        }

        return [
            'shopify' => $this->calculateReadiness($sku, 'shopify'),
            'gmc'     => $this->calculateReadinessGmc($sku),
        ];
    }

    public function recalculateAndPersist(Sku $sku): void
    {
        $fresh = $sku->fresh();
        $results = $this->assess($fresh);

        foreach ($results as $channel => $data) {
            DB::table('channel_readiness')->updateOrInsert(
                ['sku_id' => $fresh->sku_code ?? $fresh->id, 'channel' => $channel],
                [
                    'score'            => $data['score'],
                    'component_scores' => json_encode($data),
                    'computed_at'      => now(),
                    'updated_at'       => now(),
                ]
            );
        }
    }

    private function calculateReadiness(Sku $sku, string $channel): array
    {
        $score = (int) ($sku->readiness_score ?? 0);
        if ($sku->tier === 'hero') {
            $primaryThreshold = (int) BusinessRules::get('readiness.hero_primary_channel_min');
            $allChannelsThreshold = (int) BusinessRules::get('readiness.hero_all_channels_min');
        } elseif ($sku->tier === 'support') {
            $primaryThreshold = (int) BusinessRules::get('readiness.support_primary_channel_min');
            $allChannelsThreshold = null;
        } else {
            return ['score' => max(0, $score), 'status' => 'SKIP'];
        }
        $status = $score >= $primaryThreshold
            ? 'COMPETE'
            : ($allChannelsThreshold !== null && $score >= $allChannelsThreshold ? 'MONITOR' : 'SKIP');
        return ['score' => max(0, $score), 'status' => $status];
    }

    private function calculateReadinessGmc(Sku $sku): array
    {
        $score = (int) ($sku->readiness_score ?? 0);
        $eligible = self::isEligibleForGMC($sku);
        return [
            'score'  => max(0, $score),
            'status' => $eligible ? 'COMPETE' : 'SKIP',
        ];
    }

    private function getSkipResponse(): array
    {
        $skip = ['score' => 0, 'status' => 'SKIP'];
        return ['shopify' => $skip, 'gmc' => $skip];
    }
}
