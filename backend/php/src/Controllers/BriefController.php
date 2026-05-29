<?php
namespace App\Controllers;

use App\Models\ContentBrief;
use App\Models\Sku;
use App\Models\AuditLog;
use App\Services\BriefGenerationService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BriefController {

    /**
     * GET /api/v1/briefs — list content briefs.
     */
    public function index(Request $request) {
        $query = ContentBrief::with('sku')->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        $rows = $query->get();
        $this->auditBriefLifecycle($rows);
        return ResponseFormatter::format($rows);
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
        try {
            AuditLog::create([
                'entity_type' => 'brief',
                'entity_id' => (string) ($brief->id ?? ''),
                'action' => 'brief_created',
                'field_name' => 'status',
                'old_value' => null,
                'new_value' => (string) ($brief->status ?? 'open'),
                'actor_id' => auth()->check() ? (string) auth()->id() : 'SYSTEM',
                'actor_role' => auth()->check() ? (string) (optional(auth()->user()->role)->name ?? 'system') : 'system',
                'timestamp' => now(),
            ]);
        } catch (\Throwable) {
        }
        return ResponseFormatter::format($brief, 'Created', 201);
    }

    private function auditBriefLifecycle($briefs): void
    {
        if (!Schema::hasTable('audit_log')) {
            return;
        }
        foreach ($briefs as $brief) {
            $briefId = (string) ($brief->id ?? '');
            if ($briefId === '') {
                continue;
            }
            $status = strtolower((string) ($brief->status ?? ''));
            $deadline = isset($brief->deadline) ? (string) $brief->deadline : null;
            if ($status === 'completed') {
                $this->writeLifecycleAuditOnce($briefId, 'brief_completed', $status);
            } elseif ($deadline && $status !== 'completed' && $status !== 'cancelled' && $status !== 'closed' && now()->gt(\Carbon\Carbon::parse($deadline))) {
                $this->writeLifecycleAuditOnce($briefId, 'brief_overdue', $status);
            }
        }
    }

    private function writeLifecycleAuditOnce(string $briefId, string $action, string $status): void
    {
        $exists = AuditLog::query()
            ->where('entity_type', 'brief')
            ->where('entity_id', $briefId)
            ->where('action', $action)
            ->exists();
        if ($exists) {
            return;
        }
        try {
            AuditLog::create([
                'entity_type' => 'brief',
                'entity_id' => $briefId,
                'action' => $action,
                'field_name' => 'status',
                'old_value' => null,
                'new_value' => $status,
                'actor_id' => 'SYSTEM',
                'actor_role' => 'system',
                'timestamp' => now(),
            ]);
        } catch (\Throwable) {
        }
    }
}
