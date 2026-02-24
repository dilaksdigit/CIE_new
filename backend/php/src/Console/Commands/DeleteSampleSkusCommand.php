<?php

namespace App\Console\Commands;

use App\Models\Sku;
use Illuminate\Console\Command;

/**
 * Delete all sample SKUs (from sample JSON + workflow test runs).
 */
class DeleteSampleSkusCommand extends Command
{
    protected $signature = 'cie:delete-sample-skus';
    protected $description = 'Delete all sample SKU data from the database';

    private const SAMPLE_CODES = [
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

    public function handle(): int
    {
        $exact = Sku::whereIn('sku_code', self::SAMPLE_CODES)->get();
        $pattern = Sku::where('sku_code', 'like', 'CBL-BLK-3C-1M-%')->get();
        $all = $exact->merge($pattern)->unique('id');

        if ($all->isEmpty()) {
            $this->info('No sample SKUs found.');
            return 0;
        }

        $codes = $all->pluck('sku_code')->toArray();
        $this->line('Deleting ' . count($codes) . ' SKU(s): ' . implode(', ', $codes));

        foreach ($all as $sku) {
            $sku->delete();
        }

        $this->info('Sample SKUs deleted. Related rows (intents, validation_logs, etc.) removed by CASCADE.');
        return 0;
    }
}
