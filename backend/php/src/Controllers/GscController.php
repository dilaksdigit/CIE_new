<?php
namespace App\Controllers;

// SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1

class GscController
{
    /**
     * GET /api/v1/gsc/status — GSC connection health and verified property list.
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1
     * Live GSC API call goes here when service account credentials are configured.
     * Phase 2.1 — LLM Check: NO (requires live credentials per spec).
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
