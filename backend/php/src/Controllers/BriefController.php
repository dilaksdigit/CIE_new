<?php
namespace App\Controllers;

use App\Models\ContentBrief;
use App\Models\Sku;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

class BriefController {

    /**
     * GET /api/briefs
     * Returns paginated list of content briefs.
     */
    public function index() {
        $briefs = ContentBrief::with('sku')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return ResponseFormatter::format($briefs);
    }

    /**
     * POST /api/briefs
     * Creates a new content brief.
     */
    public function store(Request $request) {
        $data = $request->validate([
            'sku_id'                => 'required|string|exists:skus,id',
            'title'                 => 'required|string|max:255',
            'brief_type'            => 'nullable|in:DECAY_REFRESH,NEW_PRODUCT,MANUAL,SEASONAL',
            'priority'              => 'nullable|in:LOW,MEDIUM,HIGH,URGENT',
            'description'           => 'nullable|string',
            'current_content'       => 'nullable|string',
            'suggested_actions'     => 'nullable|array',
            'assigned_to'           => 'nullable|string|exists:users,id',
            'deadline'              => 'nullable|date',
            'effort_estimate_hours' => 'nullable|numeric|min:0',
        ]);

        $brief = ContentBrief::create($data);
        $brief->load('sku');
        return ResponseFormatter::format($brief, 'Created', 201);
    }

    /**
     * GET /api/briefs/{id}
     * Returns a single content brief with its SKU.
     */
    public function show($id) {
        $brief = ContentBrief::with('sku')->findOrFail($id);
        return ResponseFormatter::format($brief);
    }

    /**
     * PUT /api/briefs/{id}
     * Updates a content brief (status, assignment, etc.).
     */
    public function update($id, Request $request) {
        $brief = ContentBrief::findOrFail($id);
        $data = $request->validate([
            'title'                 => 'nullable|string|max:255',
            'brief_type'            => 'nullable|in:DECAY_REFRESH,NEW_PRODUCT,MANUAL,SEASONAL',
            'priority'              => 'nullable|in:LOW,MEDIUM,HIGH,URGENT',
            'description'           => 'nullable|string',
            'current_content'       => 'nullable|string',
            'suggested_actions'     => 'nullable|array',
            'status'                => 'nullable|in:OPEN,IN_PROGRESS,COMPLETED,CANCELLED',
            'assigned_to'           => 'nullable|string|exists:users,id',
            'deadline'              => 'nullable|date',
            'effort_estimate_hours' => 'nullable|numeric|min:0',
            'actual_hours'          => 'nullable|numeric|min:0',
        ]);

        if (isset($data['status']) && $data['status'] === 'COMPLETED' && !$brief->completed_at) {
            $data['completed_at'] = now();
        }

        $brief->update($data);
        $brief->load('sku');
        return ResponseFormatter::format($brief);
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

        $brief = ContentBrief::create([
            'sku_id'            => $sku->id,
            'brief_type'        => 'DECAY_REFRESH',
            'priority'          => 'HIGH',
            'title'             => $title,
            'suggested_actions' => $request->input('failing_questions'),
            'status'            => 'OPEN',
            'deadline'          => now()->addDays(14)->toDateString(),
        ]);

        $brief->load('sku');
        return ResponseFormatter::format($brief, 'Created', 201);
    }
}
