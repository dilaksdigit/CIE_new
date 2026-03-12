<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3

namespace App\Services;

/**
 * Tier → Channel eligibility per Section 8.3.
 * hero/support → COMPETE for shopify and gmc.
 * harvest/kill → SKIP for shopify and gmc.
 * Amazon is DEFERRED — not supported.
 */
class ChannelTierRulesService
{
    private const VALID_TIERS = ['hero', 'support', 'harvest', 'kill'];
    private const VALID_CHANNELS = ['shopify', 'gmc'];

    /** Tier → channel → decision. Section 8.3 table. */
    private const RULES = [
        'hero'    => ['shopify' => 'COMPETE', 'gmc' => 'COMPETE'],
        'support' => ['shopify' => 'COMPETE', 'gmc' => 'COMPETE'],
        'harvest' => ['shopify' => 'SKIP',    'gmc' => 'SKIP'],
        'kill'    => ['shopify' => 'SKIP',    'gmc' => 'SKIP'],
    ];

    /**
     * Returns "COMPETE" or "SKIP" for the given tier and channel.
     *
     * @throws \InvalidArgumentException for invalid tier or channel.
     */
    public function getChannelDecision(string $tier, string $channel): string
    {
        $tier = strtolower(trim($tier));
        $channel = strtolower(trim($channel));

        if (!in_array($tier, self::VALID_TIERS, true)) {
            throw new \InvalidArgumentException('Invalid tier: ' . $tier . '. Must be one of: hero, support, harvest, kill.');
        }
        if (!in_array($channel, self::VALID_CHANNELS, true)) {
            throw new \InvalidArgumentException('Invalid channel: ' . $channel . '. Must be one of: shopify, gmc.');
        }

        return self::RULES[$tier][$channel];
    }

    /**
     * Returns decisions for both channels: [ 'shopify' => 'COMPETE'|'SKIP', 'gmc' => 'COMPETE'|'SKIP' ].
     *
     * @throws \InvalidArgumentException for invalid tier.
     */
    public function getAllChannelDecisions(string $tier): array
    {
        return [
            'shopify' => $this->getChannelDecision($tier, 'shopify'),
            'gmc'     => $this->getChannelDecision($tier, 'gmc'),
        ];
    }
}
