<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Services\ValidationService;
use App\Support\BusinessRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-runs validation for all golden/dummy SKUs so sku_gate_status is populated
 * with every gate (G1–G7, VEC). Uses the app DB connection so portfolio (GET /sku) shows correct data.
 * Applies golden titles (G2 stems) and content (G4/G5) before validating so product names and gate chips match expected.
 *
 * Prerequisites for golden gate alignment:
 * - 007_seed_golden_sku_intents.sql — primary + secondary intents (G2, G3)
 * - 008_seed_golden_sku_content.sql or this command's seed — ai_answer_block, best_for, not_for, long_description (G4, G5, VEC 50 words)
 * - 009_seed_golden_sku_readiness.sql — channel_readiness scores ≥ threshold (G7)
 * - 080_ensure_skus_ai_answer_block_varchar_300.sql — so answer blocks are not truncated to 235 chars
 * Kill SKU is skipped (no validation update). Harvest CBL-RED-3C-2M has blocked fields cleared so G6 passes.
 */
class RefreshGoldenGateStatusCommand extends Command
{
    protected $signature = 'cie:refresh-gate-status
                            {--codes= : Comma-separated sku_codes; default: all golden SKUs}
                            {--no-seed : Skip applying golden seed data; only re-run validation}';
    protected $description = 'Apply golden seed data (same DB as app) and re-run validation to fix portfolio gate chips and product names';

    private const GOLDEN_CODES = [
        'CBL-BLK-3C-1M',
        'CBL-GLD-3C-1M',
        'CBL-WHT-2C-3M',
        'CBL-RED-3C-2M',
        'SHD-TPE-DRM-35',
        'SHD-GLS-CNE-20',
        'BLB-LED-E27-4W',
        'BLB-LED-B22-8W',
        'PND-SET-BRS-3L',
        'FLR-ARC-BLK-175',
    ];

    public function handle(ValidationService $validationService): int
    {
        $codes = $this->option('codes')
            ? array_map('trim', explode(',', $this->option('codes')))
            : self::GOLDEN_CODES;

        if (!$this->option('no-seed')) {
            $this->info('Applying golden seed data (titles + content) on DB: ' . config('database.connections.mysql.database'));
            $this->applyGoldenSeedData($codes);
            BusinessRules::invalidateCache();
        }

        // Re-load SKUs after seed so validation sees updated ai_answer_block etc.
        $skus = Sku::with(['primaryCluster', 'skuIntents.intent'])
            ->whereIn('sku_code', $codes)
            ->get();

        if ($skus->isEmpty()) {
            $this->warn('No SKUs found for codes: ' . implode(', ', $codes));
            return 1;
        }

        $this->info('Refreshing gate status for ' . $skus->count() . ' SKU(s)...');

        $ok = 0;
        $err = 0;
        foreach ($skus as $sku) {
            $tierLabel = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : (string) ($sku->tier ?? '');
            if (strtolower($tierLabel) === 'kill') {
                $this->line('  <info>SKIP</info> ' . $sku->sku_code . ' (kill — no validation update)');
                $ok++;
                continue;
            }
            try {
                $fresh = $sku->fresh(['primaryCluster', 'skuIntents.intent']);
                $result = $validationService->validate($fresh, false);
                if (!empty($result['valid'])) {
                    $this->line('  <info>OK</info> ' . $sku->sku_code . ' (' . $tierLabel . ')');
                    $ok++;
                } else {
                    $failures = $result['failures'] ?? [];
                    $msgs = array_map(fn ($f) => $f['gate'] . ': ' . ($f['detail'] ?? $f['user_message'] ?? 'fail'), $failures);
                    $this->line('  <comment>GATE_FAIL</comment> ' . $sku->sku_code . ' (' . $tierLabel . ') — ' . implode('; ', array_slice($msgs, 0, 3)));
                    $err++;
                }
            } catch (\Throwable $e) {
                $this->error('  FAIL ' . $sku->sku_code . ': ' . $e->getMessage());
                $err++;
            }
        }

        $this->newLine();
        $this->info("Done. Passed: {$ok}, Errors: {$err}. Reload the portfolio overview to see updated gate chips and product names.");
        return $err > 0 ? 1 : 0;
    }

    /**
     * Apply golden titles (007 – G2 stems) and content (008 – G4/G5) using app DB connection.
     */
    private function applyGoldenSeedData(array $codes): void
    {
        $titleUpdates = [
            'CBL-BLK-3C-1M' => 'Pendant Cable Set for Ceiling Lights - Compatibility & Safe Wiring | 3-Core Braided 1m E27',
            'CBL-GLD-3C-1M' => 'Statement Gold Pendant Cable for Period Properties | Inspiration & Braided 3-Core 1m E27',
            'CBL-WHT-2C-3M' => 'Replacement Flex Cable for Table and Floor Lamps | 2-Core White PVC 3m Specification',
            'SHD-TPE-DRM-35' => 'Warm Glare-Free Lighting for Living Rooms | Fabric Drum Shade Taupe 35cm Solution',
            'SHD-GLS-CNE-20' => 'Bright Focused Kitchen Pendant Lighting | Opal Glass Cone Shade 20cm E27 Comparison',
            'BLB-LED-E27-4W' => 'LED Bulb for E27 Pendant and Table Lamps - Compatibility & Warm Filament Glow | 4W 2700K 470lm',
            'BLB-LED-B22-8W' => 'Bright LED Bulb for B22 Kitchen and Ceiling Lights | 8W 4000K Specification 806lm',
            'PND-SET-BRS-3L' => 'Statement Kitchen Island Lighting Solution | Brass 3-Light Pendant Cluster E27',
            'CBL-RED-3C-2M' => 'Red Twisted 3-Core Pendant Cable 2m E27 – Product Specification',
        ];

        foreach ($titleUpdates as $skuCode => $title) {
            if (in_array($skuCode, $codes, true)) {
                DB::table('skus')->where('sku_code', $skuCode)->update(['title' => $title]);
            }
        }

        $contentBySku = $this->goldenContentUpdates();
        $allowedKeys = ['meta_title', 'short_description', 'ai_answer_block', 'best_for', 'not_for', 'long_description', 'expert_authority'];
        foreach ($contentBySku as $skuCode => $row) {
            if (!in_array($skuCode, $codes, true)) {
                continue;
            }
            $update = array_intersect_key($row, array_fill_keys($allowedKeys, true));
            if ($update !== []) {
                DB::table('skus')->where('sku_code', $skuCode)->update($update);
            }
        }

        // Harvest (CBL-RED-3C-2M): G6 requires blocked fields to be empty. Clear them so gate passes.
        if (in_array('CBL-RED-3C-2M', $codes, true)) {
            DB::table('skus')->where('sku_code', 'CBL-RED-3C-2M')->update([
                'ai_answer_block' => null,
                'best_for' => null,
                'not_for' => null,
                'long_description' => null,
                'expert_authority' => null,
            ]);
        }
    }

    /** Golden content (008) – ai_answer_block, best_for, not_for, etc. */
    private function goldenContentUpdates(): array
    {
        return [
            'CBL-BLK-3C-1M' => [
                'meta_title' => 'Black Braided Pendant Cable Set 3-Core 1m with E27 Holder for Ceiling Light Installation',
                'short_description' => '3-core braided pendant cable set with E27 holder. Rated to 60W. Compatible with LED and CFL. Ideal for standard 2.4m ceilings. BS 7671 compliant. Free UK delivery.',
                'ai_answer_block' => 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.',
                'best_for' => '["Standard ceiling pendant installations", "Kitchen island lighting", "Bedroom pendant upgrades", "Replacing old flex cable"]',
                'not_for' => '["Bathroom installations (not IP-rated)", "Outdoor use", "Heavy industrial fixtures over 5kg"]',
                'long_description' => 'A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely and stylishly. The black braided fabric sleeve covers the inner conductors while providing a decorative finish suited to modern and industrial interiors. Rated to 60W, this cable is compatible with LED and CFL bulbs. Choose the 1m length for standard 2.4m ceiling rooms or opt for the 1.5m variant if you have period property ceilings. BS 7671 compliant for UK domestic installations. The set includes an E27 lamp holder, ceiling rose plate, and all required fixings.',
                'expert_authority' => 'Wiring compliant with BS 7671 (IET Wiring Regulations, 18th Edition). Cable rated to 3A/60W. Suitable for DIY installation with existing ceiling rose.',
            ],
            'CBL-GLD-3C-1M' => [
                'meta_title' => 'Gold Braided Pendant Cable Set 3-Core 1m E27 for Period and Art Deco Interiors',
                'short_description' => 'Gold braided pendant cable set with E27 holder. Perfect for period properties and art deco schemes. 3-core, BS 7671 compliant. Pairs with brass ceiling roses.',
                'ai_answer_block' => 'A gold braided pendant cable set adds inspiration and a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use, with 1m length for standard ceiling heights.',
                'best_for' => '["Period property pendant installations", "Art deco interior schemes", "Statement lighting accents", "Brass fixture pairings"]',
                'not_for' => '["Modern minimalist spaces", "Outdoor use", "Bathrooms"]',
                'long_description' => 'A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style filament bulbs. The braided gold fabric sleeve is durable and flame-retardant. Rated to 60W for LED use. The complete kit includes an E27 lamp holder, ceiling rose plate with matching gold finish, and all required fixings.',
                'expert_authority' => 'BS 7671 compliant. 3-core earthed cable rated to 3A/60W. Gold braided finish is non-conductive outer sheath over PVC insulation.',
            ],
            'CBL-WHT-2C-3M' => [
                'meta_title' => 'White 2-Core Round Flex Cable 3m for Table Lamp and Floor Lamp Rewiring',
                'short_description' => 'White 2-core round flex cable, 3m length. Ideal for rewiring table lamps and floor lamps. CE marked. Bare ends for custom wiring.',
                'ai_answer_block' => 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use. See specification for full details.',
                'best_for' => '["Table lamp rewiring", "Floor lamp cable extension"]',
                'not_for' => '["Ceiling pendant installations (needs 3-core)", "Outdoor use"]',
                'long_description' => 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use. The white round profile blends discreetly against skirting boards. Suitable for lamps rated up to 60W with LED or CFL bulbs. Not intended for ceiling pendant installations or outdoor use.',
                'expert_authority' => 'CE marked. Suitable for Class II (double insulated) luminaires only. Not for earthed fittings.',
            ],
            'SHD-TPE-DRM-35' => [
                'meta_title' => 'Taupe Fabric Drum Lampshade 35cm E27 B22 for Warm Glare-Free Living Room Lighting',
                'short_description' => 'Fabric drum shade in taupe, 35cm diameter. Creates warm, glare-free light for living rooms and bedrooms. Fits E27 and B22 pendants. Fire-retardant.',
                'ai_answer_block' => 'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Ideal solution for rooms where softened ambient lighting matters most.',
                'best_for' => '["Living rooms needing warm ambient light", "Bedrooms with low ceilings", "Replacing dated coolie or pleated shades", "Pairing with dimmer switches"]',
                'not_for' => '["Task lighting (too diffused)", "Kitchens needing directional light", "Outdoor use", "High-humidity bathrooms"]',
                'long_description' => 'A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Fire-retardant fabric meets BS EN 60598-1. The shade produces a soft, glare-free ambience ideal for relaxation spaces. Pair with an LED or CFL bulb rated up to 60W for energy-efficient warm white lighting.',
                'expert_authority' => 'Fire-retardant fabric meets BS EN 60598-1 for luminaire safety. Tested to 60W incandescent / no limit for LED. Ring fitting compatible with both E27 and B22 lamp holder standards.',
            ],
            'SHD-GLS-CNE-20' => [
                'meta_title' => 'Opal Glass Cone Lampshade 20cm E27 for Kitchen Pendant and Modern Minimalist Interiors',
                'short_description' => 'Opal glass cone shade, 20cm. Bright, focused-yet-diffused light for kitchens and modern spaces. E27 ring fitting. BS EN 60598-1 compliant.',
                'ai_answer_block' => 'An opal glass cone shade delivers brighter, more focused light than fabric alternatives, making it ideal for kitchen pendants and reading nooks. The 20cm diameter suits compact pendants. Opal finish softens harshness.',
                'best_for' => '["Kitchen pendant lighting", "Bathroom vanity (check IP rating of fixture)", "Reading nooks", "Modern minimalist interiors"]',
                'not_for' => '["Children\'s rooms (fragile)", "Outdoor use", "Low-ceiling rooms (directional, not diffused)"]',
                'long_description' => 'An opal glass cone shade delivers brighter, more focused light than fabric alternatives. The compact 20cm diameter makes it ideal for smaller pendant installations. The opal finish provides a smooth, even diffusion of light. Compatible with standard E27 pendant holders. BS EN 60598-1 compliant for domestic use.',
                'expert_authority' => 'Borosilicate opal glass meets BS EN 60598-1. Heat resistant to 200C. E27 ring fitting compatible with standard UK pendant holders.',
            ],
            'BLB-LED-E27-4W' => [
                'meta_title' => 'LED Filament Bulb E27 4W 2700K Warm White 470 Lumens Dimmable Squirrel Cage',
                'short_description' => 'E27 LED filament bulb, 4W warm white 2700K. 470 lumens. Dimmable. Squirrel cage style. Fits pendant cable sets and table lamps.',
                'ai_answer_block' => 'A 4W LED filament bulb with E27 screw cap produces 470 lumens of warm white light at 2700K, equivalent to a 40W incandescent. Fits standard E27 pendants, table lamps, and floor lamps. Dimmable with compatible trailing-edge dimmer switches.',
                'best_for' => '["E27 pendant cable sets", "Table lamp bulb replacement", "Vintage-style visible bulb displays"]',
                'not_for' => '["B22 bayonet fittings", "Outdoor unenclosed fixtures", "High-lumen task lighting needs"]',
                'long_description' => 'A 4W LED filament bulb with E27 cap produces warm white light at 2700K, delivering 470 lumens equivalent to a traditional 40W incandescent bulb. Fully dimmable with trailing-edge dimmer switches. Compatible with standard E27 screw fittings found in ceiling pendants, table lamps, and floor lamps across UK homes.',
                'expert_authority' => 'CE and RoHS compliant. Energy rating A+. 25,000 hour rated lifespan. Compatible with trailing-edge dimmers.',
            ],
            'BLB-LED-B22-8W' => [
                'meta_title' => 'LED GLS Bulb B22 8W Cool White 4000K 806 Lumens for Kitchen Ceiling Fittings',
                'short_description' => 'B22 bayonet LED bulb, 8W cool white 4000K. 806 lumens, equivalent to 60W. Ideal for kitchens and workspaces. CE and RoHS compliant.',
                'ai_answer_block' => 'An 8W LED GLS bulb with B22 bayonet cap produces 806 lumens of cool white light at 4000K, equivalent to a traditional 60W bulb. Specification: designed for kitchen ceiling fittings and workspaces where bright, clear illumination is needed. Non-dimmable.',
                'best_for' => '["B22 ceiling fittings", "Kitchen and workspace lighting", "High-brightness task areas"]',
                'not_for' => '[]',
                'long_description' => 'An 8W LED bulb with B22 bayonet cap produces cool white light at 4000K, delivering 806 lumens equivalent to a traditional 60W incandescent bulb. It fits all standard B22 bayonet cap fittings commonly found in UK ceiling lights. Non-dimmable. Ideal for kitchens and workspaces.',
                'expert_authority' => 'CE and RoHS compliant. Energy rating A+. 15,000 hour rated lifespan. Non-dimmable.',
            ],
            'PND-SET-BRS-3L' => [
                'meta_title' => 'Antique Brass 3-Light Pendant Cluster Set E27 for Kitchen Island and Dining Table Lighting',
                'short_description' => 'Brass 3-light pendant cluster set with E27 holders. Statement lighting for kitchen islands and dining tables. BS 7671 compliant. Adjustable drop length.',
                'ai_answer_block' => 'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. The solution suits both period and contemporary interiors with antique brass finish.',
                'best_for' => '["Kitchen island statement lighting", "Dining table centrepiece", "Open-plan living areas", "Period property renovations"]',
                'not_for' => '["Low ceilings under 2.4m", "Bathrooms (not IP-rated)", "Single bulb requirements"]',
                'long_description' => 'A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors. BS 7671 compliant. Full installation hardware and instructions are included.',
                'expert_authority' => 'BS 7671 and BS EN 60598-1 compliant. Max load 60W per holder (180W total). Ceiling plate supports up to 5kg. Requires 3-core supply. Professional installation recommended for new wiring.',
            ],
            'CBL-RED-3C-2M' => [
                'meta_title' => 'Red Twisted Pendant Cable 3-Core 2m E27 Spec Grade',
                'short_description' => 'A red twisted 3-core pendant cable at 2m length with E27 holder. Specification grade. For stylish ceiling pendant installations in creative interiors.',
            ],
        ];
    }
}
