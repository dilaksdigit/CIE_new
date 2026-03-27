<?php
namespace App\Controllers;

use App\Models\AuditResult;
use App\Models\Sku;
use App\Services\PythonWorkerClient;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Support\BusinessRules;
use Illuminate\Support\Str;

class AuditController {
    private $pythonClient;

    public function __construct(PythonWorkerClient $pythonClient) {
        $this->pythonClient = $pythonClient;
    }

    /**
     * POST /api/v1/audit/run — trigger AI citation audit for a category (20 questions). Unified API 7.1.
     * SOURCE: openapi.yaml /audit/run (async receipt); CIE_v232_Hardening_Addendum.pdf Patch 2 §2.1
     */
    public function runByCategory(Request $request) {
        // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15 — consistent validation error envelope
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:cables,lampshades,bulbs,pendants,floor_lamps',
        ]);
        if ($validator->fails()) {
            return ResponseFormatter::standardError(
                400,
                'VALIDATION_FAILED',
                (string) $validator->errors()->first()
            );
        }
        $category = $request->input('category');
        // SOURCE: openapi.yaml AuditRunResponse — run_id format uuid; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1
        $runId = (string) Str::uuid();
        AuditLog::create([
            'entity_type' => 'audit',
            'entity_id'   => $runId,
            'action'      => 'audit_run',
            'field_name'  => null,
            'old_value'   => null,
            'new_value'   => $category,
            'actor_id'    => auth()->id() ?? 'SYSTEM',
            'actor_role'  => auth()->user()->role->name ?? 'system',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'timestamp'   => now(),
        ]);
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.1 — PHP dispatches to Python weekly_service (actual engines + 0–3 scores)
        $dispatch = $this->pythonClient->auditRunForCategory($runId, (string) $category);
        if (empty($dispatch['ok'])) {
            Log::warning('Audit run Python dispatch did not accept job', [
                'category' => $category,
                'run_id' => $runId,
                'dispatch' => $dispatch,
            ]);
        }
        // SOURCE: openapi.yaml AuditRunResponse; CIE_v232_Hardening_Addendum.pdf Patch 2 §2.1
        // Async dispatch: quorum/run_status are initial values. Final engine-derived values are written
        // by the Python service to ai_audit_runs and read via GET /audit/results/{category}.
        return response()->json([
            'run_id'                     => $runId,
            'status'                     => 'running',
            'estimated_duration_minutes' => 15,
            'quorum'                     => 0,
            'run_status'                 => 'running',
        ], 202);
    }

    /**
     * GET /api/v1/audit/results/{category} — latest audit scores + decay status. Unified API 7.1.
     * Computes aggregate citation rate from audit_results joined to skus by category.
     */
    public function resultsByCategory($category) {
        if (!in_array($category, ['cables', 'lampshades', 'bulbs', 'pendants', 'floor_lamps'], true)) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(400, 'INVALID_CATEGORY', 'Invalid category');
        }

        $citationRate = null;
        $results = [];
        $runDate = null;
        $latestRunId = null;
        $quorum = null;
        $runStatus = null;

        try {
            // SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 2 §2.1 — canonical row in ai_audit_runs (Python weekly_service)
            if (Schema::hasTable('ai_audit_runs')) {
                $metaRun = DB::table('ai_audit_runs')
                    ->where('category', $category)
                    ->where('status', 'completed')
                    ->orderByDesc('run_date')
                    ->orderByDesc('created_at')
                    ->first();

                if ($metaRun) {
                    $latestRunId = (string) ($metaRun->run_id ?? '');
                    $runDate = $metaRun->run_date
                        ? \Carbon\Carbon::parse($metaRun->run_date)->toDateString()
                        : now()->toDateString();
                    if ($metaRun->aggregate_citation_rate !== null) {
                        $citationRate = round((float) $metaRun->aggregate_citation_rate, 4);
                    }
                    $quorum = isset($metaRun->engines_available) ? (int) $metaRun->engines_available : null;
                    if (Schema::hasColumn('ai_audit_runs', 'run_status')) {
                        $runStatus = $metaRun->run_status !== null && $metaRun->run_status !== ''
                            ? (string) $metaRun->run_status
                            : null;
                    }
                }
            }

            if ($latestRunId !== null && $latestRunId !== '' && Schema::hasTable('ai_audit_results') && Schema::hasTable('ai_golden_queries')) {
                $rows = DB::table('ai_audit_results as air')
                    ->leftJoin('ai_golden_queries as gq', 'gq.question_id', '=', 'air.question_id')
                    ->leftJoin('skus', 'skus.id', '=', 'air.cited_sku_id')
                    ->where('air.run_id', $latestRunId)
                    ->orderBy('air.question_id')
                    ->orderBy('air.engine')
                    ->get(['air.question_id', 'gq.question_text', 'air.engine', 'air.score', 'skus.sku_code as cited_sku']);

                $byQ = [];
                foreach ($rows as $r) {
                    $qid = (string) ($r->question_id ?? '');
                    if ($qid === '') {
                        continue;
                    }
                    if (!isset($byQ[$qid])) {
                        $byQ[$qid] = [
                            'question_id' => $qid,
                            'question_text' => (string) ($r->question_text ?? $qid),
                            'scores' => [],
                            'cited_sku' => $r->cited_sku ?? null,
                        ];
                    }
                    $eng = (string) ($r->engine ?? '');
                    if ($eng !== '') {
                        $byQ[$qid]['scores'][$eng] = $r->score !== null ? (int) $r->score : 0;
                    }
                    if (!empty($r->cited_sku)) {
                        $byQ[$qid]['cited_sku'] = $r->cited_sku;
                    }
                }
                $results = array_values($byQ);
            } elseif (Schema::hasTable('audit_results') && Schema::hasTable('skus')) {
                $latestRun = DB::table('audit_results as ar')
                    ->join('skus', 'ar.sku_id', '=', 'skus.id')
                    ->where('skus.category', $category)
                    ->where('ar.status', 'SUCCESS')
                    ->orderByDesc('ar.queried_at')
                    ->value('ar.queried_at');

                if ($latestRun) {
                    $runDate = \Carbon\Carbon::parse($latestRun)->toDateString();

                    $row = DB::table('audit_results as ar')
                        ->join('skus', 'ar.sku_id', '=', 'skus.id')
                        ->where('skus.category', $category)
                        ->where('ar.status', 'SUCCESS')
                        ->whereDate('ar.queried_at', $runDate)
                        ->selectRaw('COUNT(*) as total, AVG(ar.score) as avg_score')
                        ->first();

                    if ($row && $row->total > 0 && $row->avg_score !== null) {
                        $citationRate = round((float) $row->avg_score / 100, 4);
                    }

                    $results = DB::table('audit_results as ar')
                        ->join('skus', 'ar.sku_id', '=', 'skus.id')
                        ->where('skus.category', $category)
                        ->where('ar.status', 'SUCCESS')
                        ->whereDate('ar.queried_at', $runDate)
                        ->orderByDesc('ar.score')
                        ->limit(50)
                        ->get(['skus.sku_code', 'ar.engine_type', 'ar.score', 'ar.queried_at'])
                        ->toArray();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("AuditController::resultsByCategory failed for {$category}: " . $e->getMessage());
        }

        $passFail = $citationRate !== null
            ? ($citationRate >= (float) BusinessRules::get('decay.hero_citation_target') ? 'pass' : 'fail')
            : null;

        $decayAlerts = $this->getDecayAlertsForCategory($category);

        // SOURCE: openapi.yaml AuditResults schema; decay_alerts from sku decay fields (CIE_Master_Developer_Build_Spec.docx §6.1)
        return response()->json([
            'data' => [
                'run_id'                  => $latestRunId,
                'category'                => $category,
                'run_date'                => $runDate ?? now()->toDateString(),
                'aggregate_citation_rate' => $citationRate,
                'pass_fail'               => $passFail,
                'results'                 => $results,
                'decay_alerts'            => $decayAlerts,
                'quorum'                  => $quorum,
                'run_status'              => $runStatus,
            ],
        ]);
    }

    /**
     * SOURCE: openapi.yaml AuditResults.decay_alerts; CIE_Master_Developer_Build_Spec.docx §6.1
     */
    private function getDecayAlertsForCategory(string $category): array
    {
        if (!Schema::hasTable('skus')) {
            return [];
        }
        try {
            $q = Sku::query()->where('category', $category);
            if (Schema::hasColumn('skus', 'decay_consecutive_zeros')) {
                $q->where('decay_consecutive_zeros', '>', 0);
            } else {
                return [];
            }
            return $q->get(['id', 'decay_status', 'decay_consecutive_zeros'])->map(function ($sku) {
                $status = strtolower((string) ($sku->decay_status ?? 'none'));
                if ($status === 'none' || $status === '') {
                    return null;
                }
                if (!in_array($status, ['yellow_flag', 'alert', 'auto_brief', 'escalated'], true)) {
                    return null;
                }
                return [
                    'sku_id' => (string) $sku->id,
                    'decay_status' => $status,
                    'consecutive_zero_weeks' => (int) ($sku->decay_consecutive_zeros ?? 0),
                ];
            })->filter()->values()->all();
        } catch (\Throwable $e) {
            Log::warning('getDecayAlertsForCategory: '.$e->getMessage());
            return [];
        }
    }
}
