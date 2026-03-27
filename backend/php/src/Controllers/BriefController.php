<?php
namespace App\Controllers;

use App\Models\ContentBrief;
use App\Models\Sku;
use App\Services\BriefGenerationService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

class BriefController {

    /**
     * GET /api/v1/briefs — list content briefs.
     */
    public function index(Request $request) {
        $query = ContentBrief::with('sku')->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        return ResponseFormatter::format($query->get());
    }

    /**
     * POST /api/v1/brief/generate — auto-generate content brief (Week 3 decay). Unified API 7.1.
     */
    public function generate(Request $request) {
        // SOURCE: openapi.yaml POST /brief/generate; CIE_Master_Developer_Build_Spec.docx §6.5 — shared with DecayService via BriefGenerationService
        $request->validate([
            'sku_id' => 'required|string|exists:skus,id',
            'failing_questions' => 'required|array',
            'failing_questions.*' => 'string',
            'competitor_answers' => 'nullable|array',
            'competitor_answers.*' => 'nullable|string|max:2000',
            'ai_suggested_revision' => 'nullable|string|max:10000',
            'current_answer_block' => 'nullable|string|max:65535',
        ]);

        $brief = app(BriefGenerationService::class)->generateDecayRefreshBrief(
            (string) $request->input('sku_id'),
            (array) $request->input('failing_questions', []),
            $request->only(['competitor_answers', 'ai_suggested_revision', 'current_answer_block'])
        );

        $brief->load('sku');
        return ResponseFormatter::format($brief, 'Created', 201);
    }
}
