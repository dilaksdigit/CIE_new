<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Services\ValidationService;
use Illuminate\Console\Command;

/**
 * Re-runs validation for all golden/dummy SKUs so sku_gate_status is populated
 * with every gate (G1–G7, VEC). Fixes portfolio overview showing only 2 Pass.
 */
class RefreshGoldenGateStatusCommand extends Command
{
    protected $signature = 'cie:refresh-gate-status
                            {--codes= : Comma-separated sku_codes; default: all golden SKUs}';
    protected $description = 'Re-run validation for golden SKUs to refresh sku_gate_status (fix portfolio gate chips)';

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
            try {
                $validationService->validate($sku->fresh(['primaryCluster', 'skuIntents.intent']), false);
                $tierLabel = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : (string) ($sku->tier ?? '');
                $this->line('  <info>OK</info> ' . $sku->sku_code . ' (' . $tierLabel . ')');
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  FAIL ' . $sku->sku_code . ': ' . $e->getMessage());
                $err++;
            }
        }

        $this->newLine();
        $this->info("Done. Passed: {$ok}, Errors: {$err}. Reload the portfolio overview to see updated gate chips.");
        return $err > 0 ? 1 : 0;
    }
}
