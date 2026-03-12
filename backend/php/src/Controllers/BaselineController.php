<?php
namespace App\Controllers;

use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BaselineService;

/**
 * Section 17 Check 9.3 — POST /api/v1/gsc/baseline/{sku_id} and POST /api/v1/ga4/baseline/{sku_id}.
 * GSC creates/updates gsc_baselines row; GA4 writes into the same row.
 */
class BaselineController
{
    protected BaselineService $baselineService;

    public function __construct(BaselineService $baselineService)
    {
        $this->baselineService = $baselineService;
    }

    /**
     * POST /api/v1/gsc/baseline/{sku_id} — capture GSC baseline BEFORE deploy.
     * Creates gsc_baselines row and fills GSC columns. Returns baseline_id for GA4 call.
     */
    public function captureGsc(string $sku_id)
    {
        $sku = Sku::find($sku_id);
        if (!$sku) {
            return response()->json(['error' => 'SKU not found'], 404);
        }
        $baselineId = $this->baselineService->captureGsc($sku);
        return response()->json([
            'baseline_id' => $baselineId,
            'gsc_status' => $baselineId ? (DB::table('gsc_baselines')->where('id', $baselineId)->value('gsc_status')) : null,
        ]);
    }

    /**
     * POST /api/v1/ga4/baseline/{sku_id} — write GA4 into the SAME gsc_baselines row.
     * Accepts optional baseline_id in body; otherwise uses latest baseline for sku_id.
     */
    public function captureGa4(Request $request, string $sku_id)
    {
        $sku = Sku::find($sku_id);
        if (!$sku) {
            return response()->json(['error' => 'SKU not found'], 404);
        }
        $baselineId = $request->input('baseline_id');
        $this->baselineService->captureGa4($sku, $baselineId ? (int) $baselineId : null);
        $row = DB::table('gsc_baselines')->where('sku_id', $sku_id)->orderByDesc('id')->first();
        return response()->json([
            'baseline_id' => $row ? $row->id : null,
            'ga4_status' => $row ? $row->ga4_status : null,
        ]);
    }
}
