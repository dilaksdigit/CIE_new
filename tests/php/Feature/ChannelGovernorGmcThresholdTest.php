<?php
// SOURCE: readiness.support_primary_channel_min (60) for Support GMC eligibility — not hero_all_channels_min (70)

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ChannelGovernorGmcThresholdTest extends TestCase
{
    /** @test Support GMC eligibility uses support_primary_channel_min */
    public function test_is_eligible_for_gmc_uses_support_primary_threshold_for_support_tier(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Services/ChannelGovernorService.php';
        $contents = file_get_contents($file);
        if (!preg_match('/function isEligibleForGMC.*?^\s{4}\}/ms', $contents, $m)) {
            $this->fail('isEligibleForGMC method not found');
        }
        $block = $m[0];
        $this->assertStringContainsString('support_primary_channel_min', $block);
        $this->assertStringNotContainsString('hero_all_channels_min', $block);
    }
}
