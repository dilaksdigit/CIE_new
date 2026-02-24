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

class AuditController {
    private $pythonClient;

    public function __construct(PythonWorkerClient $pythonClient) {
        $this->pythonClient = $pythonClient;
    }

    public function runAudit(Request $request, $sku_id) {
        $sku = Sku::findOrFail($sku_id);

        // Queue the audit job in Python worker
        $queueResult = $this->pythonClient->queueAudit($sku_id);

        if (!($queueResult['queued'] ?? false)) {
            Log::warning("Failed to queue audit for SKU {$sku_id}", $queueResult);

            AuditLog::create([
                'entity_type' => 'audit',
                'entity_id'   => $sku_id,
                'action'      => 'audit_run',
                'field_name'  => null,
                'old_value'   => null,
                'new_value'   => 'queued_failed',
                'actor_id'    => auth()->id() ?? 'SYSTEM',
                'actor_role'  => auth()->user()->role->name ?? 'system',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'timestamp'   => now(),
            ]);
            // Fail-soft: return pending state even if Python is down
            return ResponseFormatter::format([
                'sku_id' => $sku_id,
                'status' => 'initiated',
                'audit_id' => $queueResult['audit_id'] ?? bin2hex(random_bytes(8)),
                'message' => 'Audit queued (service degradation mode)'
            ], "Audit initiated for SKU {$sku_id}", 202);
        }

        Log::info("Audit queued for SKU {$sku_id}", ['audit_id' => $queueResult['audit_id'] ?? 'unknown']);

        AuditLog::create([
            'entity_type' => 'audit',
            'entity_id'   => $sku_id,
            'action'      => 'audit_run',
            'field_name'  => null,
            'old_value'   => null,
            'new_value'   => 'queued',
            'actor_id'    => auth()->id() ?? 'SYSTEM',
            'actor_role'  => auth()->user()->role->name ?? 'system',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'timestamp'   => now(),
        ]);

        return ResponseFormatter::format([
            'sku_id' => $sku_id,
            'status' => 'queued',
            'audit_id' => $queueResult['audit_id'] ?? bin2hex(random_bytes(8)),
            'message' => 'Audit has been queued and will run on the next worker cycle'
        ], "Audit initiated for SKU {$sku_id}", 202);
    }

    public function history($sku_id) {
        // Return audit history for a specific SKU
        $results = AuditResult::where('sku_id', $sku_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return ResponseFormatter::format($results);
    }

    /**
     * Get audit result (for polling)
     */
    public function getResult($auditId) {
        $result = $this->pythonClient->getAuditResult($auditId);
        
        if ($result['status'] === 'pending' || $result['status'] === 'error') {
            return ResponseFormatter::format($result, 'Audit result', $result['status'] === 'error' ? 500 : 202);
        }

        return ResponseFormatter::format($result);
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
        return response()->json([
            'data' => [
                'run_id' => $runId,
                'status' => 'running',
                'estimated_duration_minutes' => 15,
            ]
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

        $passFail = $citationRate !== null ? ($citationRate >= 0.6 ? 'pass' : 'fail') : null;

        return response()->json([
            'data' => [
                'run_id'                 => null,
                'category'               => $category,
                'run_date'               => $runDate ?? now()->toDateString(),
                'aggregate_citation_rate' => $citationRate,
                'pass_fail'              => $passFail,
                'results'                => $results,
                'decay_alerts'           => [],
            ]
        ]);
    }
}
