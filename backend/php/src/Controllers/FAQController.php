<?php
// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 4

namespace App\Controllers;

use App\Services\FAQService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FAQController
{
    public function __construct(
        private FAQService $faqService
    ) {
    }

    /**
     * GET /faq/templates?cluster_id=&intent_key=
     * Returns template questions for the given cluster and intent.
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $clusterId = $request->query('cluster_id');
        $intentKey = $request->query('intent_key');
        if ($clusterId !== null && $clusterId !== '') {
            $clusterId = (int) $clusterId;
        } else {
            $clusterId = null;
        }
        if ($intentKey !== null && $intentKey !== '') {
            $intentKey = (string) $intentKey;
        } else {
            $intentKey = null;
        }
        $templates = $this->faqService->getTemplates($clusterId, $intentKey);
        return response()->json($templates);
    }

    /**
     * POST /sku/{id}/faq
     * Body: { responses: [{ template_id, answer }] }
     */
    public function saveResponses(Request $request, $id): JsonResponse
    {
        $skuId = $id;
        $body = $request->all();
        $responses = $body['responses'] ?? null;
        if (!is_array($responses) || count($responses) === 0) {
            return response()->json(['message' => 'responses array is required and must be non-empty'], 422);
        }
        $this->faqService->saveResponses($skuId, $responses);
        return response()->json(['success' => true], 200);
    }
}
