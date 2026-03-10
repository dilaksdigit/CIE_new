<?php
namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\DB;

class ChannelGovernorService
{
    /**
     * Patch 5: Channel Readiness Assessment
     */
    public function assess(Sku $sku): array
    {
        if ($sku->tier === 'kill' || $sku->tier === 'harvest') {
            return $this->getSkipResponse();
        }

        return [
            'google_sge' => $this->calculateReadiness($sku, 'GOOGLE'),
            'amazon' => $this->calculateReadiness($sku, 'AMAZON'),
            'ai_assistants' => $this->calculateReadiness($sku, 'AI_ASST'),
            'own_website' => $this->calculateReadiness($sku, 'WEBSITE')
        ];
    }

    public function recalculateAndPersist(Sku $sku): void
    {
        $fresh = $sku->fresh();
        $results = $this->assess($fresh);

        foreach ($results as $channel => $data) {
            DB::table('channel_readiness')->updateOrInsert(
                ['sku_id' => $fresh->sku_code, 'channel' => $channel],
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
        $score = $sku->readiness_score;
        
        // Amazon penalty if no Best-For/Not-For
        if ($channel === 'AMAZON' && empty($sku->best_for)) {
            $score -= 15;
        }

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

        return [
            'score' => max(0, $score),
            'status' => $status
        ];
    }

    private function getSkipResponse(): array
    {
        $skip = ['score' => 0, 'status' => 'SKIP'];
        return [
            'google_sge' => $skip,
            'amazon' => $skip,
            'ai_assistants' => $skip,
            'own_website' => $skip
        ];
    }
}
