<?php
namespace App\Controllers;

use App\Models\ContentBrief;
use App\Models\Sku;
use App\Support\BusinessRules;
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
        $request->validate([
            'sku_id'              => 'required|string|exists:skus,id',
            'failing_questions'   => 'required|array',
            'failing_questions.*' => 'string',
        ]);

        $sku = Sku::findOrFail($request->input('sku_id'));
        $title = 'Decay Refresh: ' . ($sku->title ?: $sku->sku_code ?: $sku->id);

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5
        // FIX: DEC-03 — status enum is lowercase.
        $brief = ContentBrief::create([
            'sku_id'            => $sku->id,
            'brief_type'        => 'DECAY_REFRESH',
            'priority'          => 'HIGH',
            'title'             => $title,
            'suggested_actions' => $request->input('failing_questions'),
            'status'            => 'open',
            'deadline'          => now()->addDays((int) BusinessRules::get('decay.auto_brief_deadline_days'))->toDateString(),
        ]);

        $brief->load('sku');
        return ResponseFormatter::format($brief, 'Created', 201);
    }
}
