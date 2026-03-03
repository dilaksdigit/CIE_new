<?php
namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BaselineController
{
    /**
     * POST /api/gsc/baseline/{sku_id}
     *
     * Captures a 14-day GSC baseline for the given SKU.
     * Fail-soft: never blocks publish; tags rows as 'unbaselined' on failure.
     */
    public function captureGscBaseline(string $skuId)
    {
        /** @var Sku|null $sku */
        $sku = Sku::find($skuId);
        if (!$sku) {
            return response()->json(['error' => 'SKU not found'], 404);
        }

        $now = now();

        try {
            // Spec: 14-day window = last 2 rows from url_performance view for this SKU.
            $rows = DB::table('url_performance')
                ->where('sku_id', $skuId)
                ->orderByDesc('captured_at')
                ->limit(2)
                ->get();
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $gscStatus = 'captured';
        $baselineImpressions = null;
        $baselineClicks = null;
        $baselineCtr = null;
        $baselineAvgPosition = null;

        if ($rows->isEmpty()) {
            $gscStatus = 'unbaselined';
        } else {
            $baselineImpressions = (float) $rows->avg('impressions');
            $baselineClicks = (float) $rows->avg('clicks');
            $baselineCtr = (float) $rows->avg('ctr');
            $baselineAvgPosition = (float) $rows->avg('avg_position');
        }

        $baselineId = DB::table('gsc_baselines')->insertGetId([
            'sku_id'                 => $skuId,
            'baseline_captured_at'   => $now,
            'baseline_impressions'   => $baselineImpressions,
            'baseline_clicks'        => $baselineClicks,
            'baseline_ctr'           => $baselineCtr,
            'baseline_avg_position'  => $baselineAvgPosition,
            'baseline_organic_sessions' => null,
            'baseline_conversion_rate'  => null,
            'baseline_revenue'          => null,
            'gsc_status'             => $gscStatus,
            'ga4_status'             => 'pending',
            'change_id'              => null,
        ]);

        if ($gscStatus === 'unbaselined') {
            AuditLog::create([
                'entity_type' => 'sku',
                'entity_id'   => $skuId,
                'action'      => 'gsc_baseline',
                'field_name'  => 'gsc_status',
                'old_value'   => null,
                'new_value'   => 'unbaselined',
                'actor_id'    => auth()->id() ?? 'SYSTEM',
                'actor_role'  => optional(optional(auth()->user())->role)->name ?? 'system',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'timestamp'   => now(),
            ]);
        }

        return response()->json([
            'baseline_id'          => $baselineId,
            'sku_id'               => $skuId,
            'baseline_captured_at' => $now,
            'gsc_status'           => $gscStatus,
        ], 200);
    }

    /**
     * POST /api/ga4/baseline/{sku_id}
     *
     * Writes GA4 metrics into the most recent gsc_baselines row for this SKU.
     * Fail-soft: on error, tags ga4_status as 'unbaselined' and does not block.
     *
     * Expected JSON body:
     * {
     *   "baseline_organic_sessions": 123,
     *   "baseline_conversion_rate": 0.0345,
     *   "baseline_revenue": 1234.56
     * }
     */
    public function captureGa4Baseline(Request $request, string $skuId)
    {
        /** @var Sku|null $sku */
        $sku = Sku::find($skuId);
        if (!$sku) {
            return response()->json(['error' => 'SKU not found'], 404);
        }

        $baseline = DB::table('gsc_baselines')
            ->where('sku_id', $skuId)
            ->orderByDesc('baseline_captured_at')
            ->first();

        if (!$baseline) {
            // No prior GSC baseline; record as unbaselined GA4 and proceed.
            $id = DB::table('gsc_baselines')->insertGetId([
                'sku_id'                    => $skuId,
                'baseline_captured_at'      => now(),
                'baseline_impressions'      => null,
                'baseline_clicks'           => null,
                'baseline_ctr'              => null,
                'baseline_avg_position'     => null,
                'baseline_organic_sessions' => null,
                'baseline_conversion_rate'  => null,
                'baseline_revenue'          => null,
                'gsc_status'                => 'unbaselined',
                'ga4_status'                => 'unbaselined',
                'change_id'                 => null,
            ]);

            return response()->json([
                'baseline_id' => $id,
                'sku_id'      => $skuId,
                'ga4_status'  => 'unbaselined',
            ], 200);
        }

        $sessions = $request->input('baseline_organic_sessions');
        $convRate = $request->input('baseline_conversion_rate');
        $revenue  = $request->input('baseline_revenue');

        $ga4Status = 'captured';

        if ($sessions === null || $convRate === null || $revenue === null) {
            $ga4Status = 'unbaselined';
        }

        DB::table('gsc_baselines')
            ->where('id', $baseline->id)
            ->update([
                'baseline_organic_sessions' => $sessions !== null ? (int) $sessions : null,
                'baseline_conversion_rate'  => $convRate !== null ? (float) $convRate : null,
                'baseline_revenue'          => $revenue !== null ? (float) $revenue : null,
                'ga4_status'                => $ga4Status,
            ]);

        if ($ga4Status === 'unbaselined') {
            AuditLog::create([
                'entity_type' => 'sku',
                'entity_id'   => $skuId,
                'action'      => 'ga4_baseline',
                'field_name'  => 'ga4_status',
                'old_value'   => $baseline->ga4_status ?? null,
                'new_value'   => 'unbaselined',
                'actor_id'    => auth()->id() ?? 'SYSTEM',
                'actor_role'  => optional(optional(auth()->user())->role)->name ?? 'system',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'timestamp'   => now(),
            ]);
        }

        return response()->json([
            'baseline_id' => $baseline->id,
            'sku_id'      => $skuId,
            'ga4_status'  => $ga4Status,
        ], 200);
    }
}

