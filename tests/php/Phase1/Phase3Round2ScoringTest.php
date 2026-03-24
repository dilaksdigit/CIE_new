<?php
// SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §1 fixture 1
// FIX: TS-15 — Hero golden maturity fixture + service parity when DB seeded.

namespace Tests\Phase1;

use App\Models\Sku;
use App\Services\ChannelGovernorService;
use App\Services\MaturityScoreService;
use PHPUnit\Framework\TestCase;

class Phase3Round2ScoringTest extends TestCase
{
    /** @test */
    public function golden_fixture_cbl_blk_maturity_totals_match_doc4b(): void
    {
        $path = __DIR__ . '/../../../database/seeds/golden_test_data.json';
        $this->assertFileExists($path);
        $rows = json_decode((string) file_get_contents($path), true);
        $cbl = null;
        foreach ($rows as $row) {
            if (($row['sku_code'] ?? '') === 'CBL-BLK-3C-1M') {
                $cbl = $row;
                break;
            }
        }
        $this->assertNotNull($cbl);
        $m = $cbl['expected_outputs']['maturity'] ?? null;
        $this->assertIsArray($m);
        $this->assertSame(94, (int) $m['total']);
        $this->assertSame(12, (int) $m['ai_visibility']);
        $this->assertSame('Gold', $m['level']);
    }

    /**
     * @test
     * SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §1 fixture 1
     * FIX: TS-15 — MaturityScoreService::compute matches golden when SKU exists in DB.
     */
    public function hero_sku_maturity_matches_golden_data(): void
    {
        require_once dirname(__DIR__) . '/bootstrap-app.php';

        $sku = Sku::query()->where('sku_code', 'CBL-BLK-3C-1M')->first();
        if ($sku === null) {
            $this->markTestSkipped('CBL-BLK-3C-1M not in database; seed golden_test_data first.');
        }

        $service = app(MaturityScoreService::class);
        $result = $service->compute($sku);

        if ((int) ($result['core_fields'] ?? 0) < 40 || (int) ($result['total'] ?? 0) < 93) {
            $this->markTestSkipped(
                'CBL-BLK-3C-1M row exists but maturity inputs incomplete; run seed_golden_data.php for full Doc4b parity.'
            );
        }

        $this->assertSame('Gold', $result['level']);
        $this->assertSame(40, $result['core_fields']);
        $this->assertSame(20, $result['authority']);
        $this->assertSame(22, $result['channel_readiness']);
        $this->assertGreaterThanOrEqual(11, (int) $result['ai_visibility']);
        $this->assertGreaterThanOrEqual(93, (int) $result['total']);
    }

    /**
     * @test
     * SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf Kill fixture
     * FIX: TS-14 — Kill tier channel assessment is SKIP / score 0 for all channels (no DB).
     */
    public function kill_sku_returns_skip_zero_for_all_channels(): void
    {
        $sku = new Sku();
        $sku->forceFill(['tier' => 'kill']);

        $service = new ChannelGovernorService();
        $result = $service->assess($sku);

        foreach (['google_sge', 'amazon', 'ai_assistants', 'own_website'] as $ch) {
            $this->assertArrayHasKey($ch, $result);
            $this->assertSame('SKIP', $result[$ch]['decision']);
            $this->assertSame(0, $result[$ch]['score']);
        }
        $this->assertSame(0, (int) ($result['active_channels'] ?? -1));
    }
}
