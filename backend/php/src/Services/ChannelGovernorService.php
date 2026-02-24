<?php
namespace App\Services;

use App\Models\Sku;

class ChannelGovernorService
{
    /**
     * Patch 5: Channel Readiness Assessment
     */
    public function assess(Sku $sku): array
    {
        if ($sku->tier === 'KILL') {
            return $this->getSkipResponse();
        }

        return [
            'google_sge' => $this->calculateReadiness($sku, 'GOOGLE'),
            'amazon' => $this->calculateReadiness($sku, 'AMAZON'),
            'ai_assistants' => $this->calculateReadiness($sku, 'AI_ASST'),
            'own_website' => $this->calculateReadiness($sku, 'WEBSITE')
        ];
    }

    private function calculateReadiness(Sku $sku, string $channel): array
    {
        $score = $sku->readiness_score;
        
        // Amazon penalty if no Best-For/Not-For
        if ($channel === 'AMAZON' && empty($sku->best_for)) {
            $score -= 15;
        }

        $status = $score >= 80 ? 'COMPETE' : ($score >= 60 ? 'MONITOR' : 'SKIP');

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
