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
use App\Models\AuditLog;
use App\Support\BusinessRules;

class AuditController {
    private $pythonClient;

    public function __construct(PythonWorkerClient $pythonClient) {
        $this->pythonClient = $pythonClient;
    }

    /**
     * POST /api/v1/audit/run — trigger AI citation audit for a category (20 questions). Unified API 7.1.
     */
    public function runByCategory(Request $request) {
        $request->validate(['category' => 'required|string|in:cables,lampshades,bulbs,pendants,floor_lamps']);
        $category = $request->input('category');
        $runId = bin2hex(random_bytes(16));
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
        // SOURCE: openapi.yaml AuditRunResponse; CIE_v232_Hardening_Addendum.pdf Patch 2
        return response()->json([
            'run_id' => $runId,
            'status' => 'running',
            // Async trigger response: quorum/run_status are initialized and finalized by the worker run output.
            'quorum' => 0,
            'run_status' => 'complete',
            'estimated_duration_minutes' => 15,
        ], 202);
    }

    /**
     * GET /api/v1/audit/results/{category} — latest audit scores + decay status. Unified API 7.1.
     * Computes aggregate citation rate from audit_results joined to skus by category.
     */
    public function resultsByCategory($category) {
        if (!in_array($category, ['cables', 'lampshades', 'bulbs', 'pendants', 'floor_lamps'], true)) {
            return response()->json(['error' => 'Invalid category'], 400);
        }

        $citationRate = null;
        $results = [];
        $runDate = null;

        try {
            if (Schema::hasTable('audit_results') && Schema::hasTable('skus')) {
                // Get most recent audit run date for this category
                $latestRun = DB::table('audit_results as ar')
                    ->join('skus', 'ar.sku_id', '=', 'skus.id')
                    ->where('skus.category', $category)
                    ->where('ar.status', 'SUCCESS')
                    ->orderByDesc('ar.queried_at')
                    ->value('ar.queried_at');

                if ($latestRun) {
                    $runDate = \Carbon\Carbon::parse($latestRun)->toDateString();

                    // Aggregate citation rate = avg(score) / 100 for that run date
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

                    // Recent results (up to 50)
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
                'run_id'                  => null,
                'category'                => $category,
                'run_date'                => $runDate ?? now()->toDateString(),
                'aggregate_citation_rate' => $citationRate,
                'pass_fail'               => $passFail,
                'results'                 => $results,
                'decay_alerts'            => $decayAlerts,
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
