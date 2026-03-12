<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 1.3 / 1.4 / 1.5

namespace Tests\Phase1;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1.3 — Kill-tier: all fields blocked. Harvest-tier: G1/G2/G6 only.
 * Phase 1.5 — LLM scan: zero hard-coded weight values in tier engine code.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.3 + 1.5
 */
class TierEngineTest extends TestCase
{
    /** @test Phase 1.3 — Kill-tier SKU (SKU-CABLE-002): all content fields blocked */
    public function test_kill_tier_all_fields_blocked(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.3
        // "Kill-tier SKU: all content fields blocked."
        // SOURCE: CLAUDE.md §7 — "Kill: ALL FIELDS BLOCKED. Read-only."
        $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        $token   = getenv('TEST_TOKEN_CONTENT_WRITER') ?: 'test-token-placeholder';

        $ch = curl_init("{$baseUrl}/api/v1/sku/SKU-CABLE-002");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]]);
        $body = json_decode(curl_exec($ch), true);

        $this->assertSame('kill', $body['tier'] ?? null, 'SKU-CABLE-002 must be kill tier');
        $this->assertSame(true, $body['all_fields_blocked'] ?? null,
            'Kill-tier SKU must report all_fields_blocked = true');
    }

    /** @test Phase 1.5 — Tier engine files must not contain hard-coded weight values */
    public function test_tier_engine_has_no_hard_coded_weights(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.5
        // "LLM adversarial scan: find hard-coded numbers in tier engine. Zero findings."
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §8.1 — "All weights read from business_rules table"
        $tierEngineDir = __DIR__ . '/../../../backend/php/src/Services';
        $tierEngineFiles = glob($tierEngineDir . '/Tier*.php') ?: [];
        $hardCodedWeightPattern = '/(?<![A-Za-z_>])(0\.\d+|[1-9]\d*\.\d+)(?!\s*\)?\s*;?\s*\/\/.*BusinessRules)/';

        foreach ($tierEngineFiles as $file) {
            $content = file_get_contents($file);
            // Strip comments to avoid false positives
            $code = preg_replace('/\/\/[^\n]*/', '', $content);
            $code = preg_replace('/\/\*.*?\*\//s', '', $code);
            preg_match_all($hardCodedWeightPattern, $code, $matches);
            $this->assertEmpty($matches[0],
                "Hard-coded weight value found in {$file}. Must use BusinessRules::get() per §8.1.");
        }
    }
}
