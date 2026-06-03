<?php
// SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3 — channel decisions; DECISION-001 Shopify+GMC only
namespace Tests\Phase1;

use App\Models\Sku;
use App\Services\ChannelGovernorService;
use PHPUnit\Framework\TestCase;

class ChannelGovernorTest extends TestCase
{
    /** @test Kill tier: all channels SKIP, active_channels 0 */
    public function test_kill_tier_all_channels_skip(): void
    {
        $sku = new Sku(['tier' => 'kill', 'readiness_score' => 0]);
        $result = (new ChannelGovernorService())->assess($sku);
        $this->assertSame('SKIP', $result['shopify']['decision']);
        $this->assertSame('SKIP', $result['gmc']['decision']);
        $this->assertSame(0, $result['active_channels']);
    }

    /** @test DECISION-001 — governor surface is shopify + gmc only (no amazon key) */
    public function test_assess_returns_shopify_and_gmc_only(): void
    {
        $sku = new Sku(['tier' => 'kill']);
        $result = (new ChannelGovernorService())->assess($sku);
        $this->assertArrayHasKey('shopify', $result);
        $this->assertArrayHasKey('gmc', $result);
        $this->assertArrayNotHasKey('amazon', $result);
    }

    /** @test Kill SKU not eligible for GMC feed */
    public function test_kill_not_eligible_for_gmc(): void
    {
        $sku = new Sku(['tier' => 'kill', 'readiness_score' => 99]);
        $this->assertFalse(ChannelGovernorService::isEligibleForGMC($sku));
    }

    /** @test Harvest SKU not eligible for GMC per CHAN-02 */
    public function test_harvest_not_eligible_for_gmc(): void
    {
        $sku = new Sku(['tier' => 'harvest', 'readiness_score' => 99]);
        $this->assertFalse(ChannelGovernorService::isEligibleForGMC($sku));
    }
}
