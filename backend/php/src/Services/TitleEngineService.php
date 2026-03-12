<?php
namespace App\Services;

use App\Models\Sku;
use App\Support\BusinessRules;

class TitleEngineService
{
    /**
     * L4: Intent-First Title Generation
     */
    public function generate(Sku $sku): array
    {
        $primaryIntent = $sku->primary_intent ?? 'General';
        $intentPrefix = $this->getIntentPrefix($primaryIntent);
        
        $baseTitle = $sku->title ?? $sku->sku_code;
        $fitting = $sku->fitting_type ?? 'Standard';
        
        $shopifyTitle = "{$baseTitle} - {$intentPrefix} | {$fitting}";
        
        $maxLen = 70; // §5.3: content.shopify_title_max_len not in 52 rules; hard-coded
        if (strlen($shopifyTitle) > $maxLen) {
            $shopifyTitle = substr($shopifyTitle, 0, $maxLen - 3) . '...';
        }

        return [
            'shopify_title' => $shopifyTitle,
            'feed_title' => $baseTitle . " with " . $fitting . " fitting",
            'is_valid' => !empty($sku->primary_intent)
        ];
    }

    private function getIntentPrefix(?string $intent): string
    {
        if (!$intent) return "Premium Lighting Component";
        
        switch ($intent) {
            case 'Compatibility': return "Safe Wiring Made Simple";
            case 'Problem-Solving': return "Solution for Period Homes";
            case 'Inspiration': return "Design-Led Lighting Upgrade";
            default: return "Premium Lighting Component";
        }
    }
}
