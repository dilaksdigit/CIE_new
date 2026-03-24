<?php
// SOURCE: CIE_Doc4b_Golden_Test_Data_Pack.pdf §3.2
// FIX: TS-13 — Automated contract test: golden seed JSON channel readiness matches Doc4b (no HTTP / DB required)

namespace Tests\Phase1;

use PHPUnit\Framework\TestCase;

class ReadinessGoldenDoc4bTest extends TestCase
{
    private const EXPECTED_CBL_BLK = [
        'google_sge' => ['decision' => 'COMPETE', 'readiness' => 92],
        'amazon' => ['decision' => 'COMPETE', 'readiness' => 78],
        'ai_assistants' => ['decision' => 'COMPETE', 'readiness' => 85],
        'own_website' => ['decision' => 'COMPETE', 'readiness' => 95],
        'active_channels' => 4,
    ];

    /** @test */
    public function golden_test_data_json_cbl_blk_channel_readiness_matches_doc4b(): void
    {
        $path = __DIR__ . '/../../../database/seeds/golden_test_data.json';
        $this->assertFileExists($path, 'golden_test_data.json must exist for Doc4b alignment');
        $rows = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($rows);
        $cbl = null;
        foreach ($rows as $row) {
            if (($row['sku_code'] ?? '') === 'CBL-BLK-3C-1M') {
                $cbl = $row;
                break;
            }
        }
        $this->assertNotNull($cbl, 'CBL-BLK-3C-1M row missing from golden_test_data.json');
        $ch = $cbl['expected_outputs']['channel_decisions'] ?? null;
        $this->assertIsArray($ch);
        $this->assertSame(self::EXPECTED_CBL_BLK, $ch, 'Doc4b §3.2 channel readiness must match golden_test_data.json');
    }
}
