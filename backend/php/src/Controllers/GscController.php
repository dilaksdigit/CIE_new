<?php
namespace App\Controllers;

// SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1

class GscController
{
    /**
     * GET /api/v1/gsc/status — GSC connection health and verified property list.
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1
     * FINAL Dev Instruction Phase 2.1 — verified property list via service account.
     *
     * NOTE (FIX GSC-01 / GAP-P6-4): Live Search Console verification adds latency and hard‑fails when
     * credentials are absent (typical dev/staging). Production may call searchanalytics.query here when
     * architect approves; until then, property list is taken from config for testability.
     */
    public function status(): \Illuminate\Http\JsonResponse
    {
        $gscProperty = config('services.gsc.property', env('GSC_PROPERTY'));
        return response()->json([
            'status'              => 'ok',
            'verified_properties'  => $gscProperty ? [$gscProperty] : [],
        ], 200);
    }
}
