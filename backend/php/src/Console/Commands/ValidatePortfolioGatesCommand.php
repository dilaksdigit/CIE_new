<?php

namespace App\Console\Commands;

use App\Controllers\SkuController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Validates that the SKU list (portfolio) returns canonical gate statuses
 * matching expected golden results. Run after cie:refresh-gate-status.
 */
class ValidatePortfolioGatesCommand extends Command
{
    protected $signature = 'cie:validate-portfolio-gates';
    protected $description = 'Validate portfolio list returns expected gate results for golden SKUs';

    /** Expected: G4 fail (answer block 242 chars). */
    private const SHD_GLS_CNE_20 = 'SHD-GLS-CNE-20';
    /** Expected: G5 fail (not_for empty). */
    private const BLB_LED_B22_8W = 'BLB-LED-B22-8W';
    /** Expected: all gates pass (7/7). */
    private const CBL_BLK_3C_1M = 'CBL-BLK-3C-1M';
    /** Expected: Kill, no gates (_kill_locked). */
    private const FLR_ARC_BLK_175 = 'FLR-ARC-BLK-175';

    public function handle(): int
    {
        $controller = app(SkuController::class);
        $request = Request::create('/api/v1/sku', 'GET');
        $request->setUserResolver(fn () => null);
        $response = $controller->index($request);
        $data = $response->getData(true);
        $skus = $data['items'] ?? $data['data'] ?? $data ?? [];
        if (!is_array($skus)) {
            $this->error('List response is not an array.');
            return 1;
        }

        $byCode = [];
        foreach ($skus as $sku) {
            $code = $sku['sku_code'] ?? $sku['sku_id'] ?? null;
            if ($code) {
                $byCode[$code] = $sku;
            }
        }

        $failed = 0;

        // SHD-GLS-CNE-20: G4 must fail
        $s1 = $byCode[self::SHD_GLS_CNE_20] ?? null;
        if (!$s1) {
            $this->warn(self::SHD_GLS_CNE_20 . ' not in list.');
        } elseif (!isset($s1['gates']['G4'])) {
            $this->error(self::SHD_GLS_CNE_20 . ': gates.G4 missing.');
            $failed++;
        } elseif ($s1['gates']['G4']['passed'] !== false) {
            $this->error(self::SHD_GLS_CNE_20 . ': expected G4 FAIL, got pass.');
            $failed++;
        } else {
            $this->info(self::SHD_GLS_CNE_20 . ': G4 FAIL (expected)');
        }

        // BLB-LED-B22-8W: G5 must fail
        $s2 = $byCode[self::BLB_LED_B22_8W] ?? null;
        if (!$s2) {
            $this->warn(self::BLB_LED_B22_8W . ' not in list.');
        } elseif (!isset($s2['gates']['G5'])) {
            $this->error(self::BLB_LED_B22_8W . ': gates.G5 missing.');
            $failed++;
        } elseif ($s2['gates']['G5']['passed'] !== false) {
            $this->error(self::BLB_LED_B22_8W . ': expected G5 FAIL, got pass.');
            $failed++;
        } else {
            $this->info(self::BLB_LED_B22_8W . ': G5 FAIL (expected)');
        }

        // CBL-BLK-3C-1M: when seed 008 is applied, G1–G7 + tier_fields should pass (canonical used)
        $s3 = $byCode[self::CBL_BLK_3C_1M] ?? null;
        if (!$s3) {
            $this->warn(self::CBL_BLK_3C_1M . ' not in list.');
        } else {
            $gates = $s3['gates'] ?? [];
            if (isset($gates['_kill_locked'])) {
                $this->error(self::CBL_BLK_3C_1M . ': unexpected _kill_locked (should be Hero).');
                $failed++;
            } else {
                $required = ['G1', 'G2', 'G3', 'G4', 'G5', 'G6', 'tier_fields', 'G7'];
                $failing = [];
                foreach ($required as $k) {
                    if (empty($gates[$k]['passed'])) {
                        $failing[] = $k;
                    }
                }
                if (count($failing) > 0) {
                    $this->warn(self::CBL_BLK_3C_1M . ': gates failing: ' . implode(', ', $failing) . ' (ensure seed 008 is applied for 7/7)');
                } else {
                    $this->info(self::CBL_BLK_3C_1M . ': G1–G7 + tier_fields PASS (expected)');
                }
            }
        }

        // FLR-ARC-BLK-175: Kill = _kill_locked, no gate chips
        $s4 = $byCode[self::FLR_ARC_BLK_175] ?? null;
        if (!$s4) {
            $this->warn(self::FLR_ARC_BLK_175 . ' not in list.');
        } elseif (!isset($s4['gates']['_kill_locked']) || $s4['gates']['_kill_locked'] !== true) {
            $this->error(self::FLR_ARC_BLK_175 . ': expected _kill_locked true.');
            $failed++;
        } else {
            $this->info(self::FLR_ARC_BLK_175 . ': Kill locked (expected)');
        }

        $this->newLine();
        if ($failed > 0) {
            $this->error("Validation failed: {$failed} assertion(s) failed.");
            return 1;
        }
        $this->info('Portfolio gate validation passed.');
        return 0;
    }
}
