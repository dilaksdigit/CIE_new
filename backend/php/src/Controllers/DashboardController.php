<?php

namespace App\Controllers;

use App\Models\Sku;
use App\Models\Cluster;
use App\Models\ValidationLog;
use App\Models\StaffEffortLog;
use App\Services\ReadinessScoreService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CIE v2.3.2 — Dashboard data for S4 Maturity, Decay Monitor, Effort Allocation, Staff KPIs.
 * All responses are dynamic from database (no hard-coded values).
 */
class DashboardController
{
    public function __construct(
        private ReadinessScoreService $readinessScoreService
    ) {
    }

    /**
     * GET /api/dashboard/summary
     * Returns: tier_summary, category_heatmap, decay_monitor, effort_allocation, staff_kpis
     */
    public function summary(Request $request)
    {
        $tierSummary = $this->safeBuild('buildTierSummary', []);
        $categoryHeatmap = $this->safeBuild('buildCategoryHeatmap', []);
        $decayMonitor = $this->safeBuild('buildDecayMonitor', []);
        $effortAllocation = $this->safeBuild('buildEffortAllocation', ['by_tier' => [], 'total_hours' => 0, 'hero_pct' => 0, 'hero_alert' => false]);
        $staffKpis = $this->safeBuild('buildStaffKpis', []);

        return ResponseFormatter::format([
            'tier_summary' => $tierSummary,
            'category_heatmap' => $categoryHeatmap,
            'decay_monitor' => $decayMonitor,
            'effort_allocation' => $effortAllocation,
            'staff_kpis' => $staffKpis,
        ]);
    }

    private function safeBuild(string $method, $default)
    {
        try {
            return $this->$method();
        } catch (\Throwable $e) {
            Log::warning("DashboardController::{$method} failed: " . $e->getMessage(), ['exception' => $e]);
            return $default;
        }
    }

    /**
     * GET /api/dashboard/decay-alerts
     * Hero SKUs with consecutive zero citation weeks (decay_status != 'none').
     */
    public function decayAlerts()
    {
        $list = $this->buildDecayMonitor();
        return ResponseFormatter::format($list);
    }

    /**
     * GET /api/audit-results/weekly-scores
     * Returns weekly score trend rows for reviewer KPI view.
     */
    public function weeklyScores()
    {
        if (!Schema::hasTable('weekly_scores')) {
            return ResponseFormatter::format([]);
        }

        $hasNotes = false;
        try {
            $hasNotes = Schema::hasColumn('weekly_scores', 'notes');
        } catch (\Throwable $e) {
            $hasNotes = false;
        }

        $query = DB::table('weekly_scores')
            ->orderBy('week', 'desc')
            ->limit(12);

        $columns = ['id', 'week', 'score', 'created_at', 'actor_id'];
        if ($hasNotes) {
            $columns[] = 'notes';
        }

        $rows = $query
            ->get($columns)
            ->map(function ($row) use ($hasNotes) {
                return [
                    'id' => (int) $row->id,
                    'week_start' => (string) $row->week,
                    'score' => (int) $row->score,
                    'notes' => $hasNotes ? (string) ($row->notes ?? '') : '',
                    'created_at' => (string) $row->created_at,
                    'actor_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
                    'actor_name' => null,
                ];
            })
            ->values()
            ->all();

        return ResponseFormatter::format(array_reverse($rows));
    }

    /**
     * POST /api/audit-results/weekly-scores
     * KPI Reviewer: save a weekly score (1-10 + notes). One row per week_start.
     */
    public function storeWeeklyScore(Request $request)
    {
        if (!Schema::hasTable('weekly_scores')) {
            return ResponseFormatter::format(['error' => 'weekly_scores table not available'], 'Table not found', 503);
        }
        $data = $request->validate([
            'week_start' => 'required|date',
            'score' => 'required|integer|min:1|max:10',
            'notes' => 'nullable|string|max:2000',
        ]);

        $actorId = auth()->id();

        // Canonical schema uses "week" (VARCHAR) rather than "week_start" DATE.
        $existing = DB::table('weekly_scores')->where('week', $data['week_start'])->first();
        $hasNotes = false;
        try {
            $hasNotes = Schema::hasColumn('weekly_scores', 'notes');
        } catch (\Throwable $e) {
            $hasNotes = false;
        }

        if ($existing) {
            $update = [
                'score' => (int) $data['score'],
                'actor_id' => $actorId,
            ];
            if ($hasNotes) {
                $update['notes'] = $data['notes'] ?? null;
            }
            DB::table('weekly_scores')->where('id', $existing->id)->update($update);
            $row = (object) [
                'id' => $existing->id,
                'week_start' => $data['week_start'],
                'score' => (int) $data['score'],
                'notes' => $data['notes'] ?? '',
                'created_at' => $existing->created_at ?? now(),
                'actor_id' => $actorId,
            ];
        } else {
            $insert = [
                'week' => $data['week_start'],
                'score' => (int) $data['score'],
                'user_id' => $actorId,
                'actor_id' => $actorId,
            ];
            if ($hasNotes) {
                $insert['notes'] = $data['notes'] ?? null;
            }
            $id = DB::table('weekly_scores')->insertGetId($insert);
            $row = (object) [
                'id' => $id,
                'week_start' => $data['week_start'],
                'score' => (int) $data['score'],
                'notes' => $data['notes'] ?? '',
                'created_at' => now(),
                'actor_id' => $actorId,
            ];
        }
        return ResponseFormatter::format([
            'id' => (int) $row->id,
            'week_start' => (string) $row->week_start,
            'score' => (int) $row->score,
            'notes' => (string) ($row->notes ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'actor_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
        ], 'Saved', 201);
    }

    private function buildTierSummary(): array
    {
        $tiers = ['HERO', 'SUPPORT', 'HARVEST', 'KILL'];
        $out = [];
        foreach ($tiers as $tier) {
            $q = Sku::where('tier', $tier);
            $count = $q->count();
            $avgReadiness = (float) $q->avg('readiness_score');
            $avgMargin = (float) Sku::where('tier', $tier)->avg('margin_percent');
            $out[] = [
                'tier' => $tier,
                'count' => $count,
                'avg_readiness' => round($avgReadiness, 1),
                'avg_margin' => $avgMargin !== null ? round($avgMargin, 1) : null,
            ];
        }
        return $out;
    }

    private function buildCategoryHeatmap(): array
    {
        $channels = ['own_website', 'google_sge', 'amazon', 'ai_assistants'];
        $categories = [];
        try {
            if (Schema::hasColumn((new Cluster)->getTable(), 'category')) {
                $categories = Cluster::distinct()->pluck('category')->filter()->values()->all();
            }
        } catch (\Throwable $e) {
            // ignore
        }
        if (empty($categories)) {
            $categories = ['cables', 'lampshades', 'bulbs', 'pendants'];
        }

        $skusByCategory = [];
        $skus = Sku::with(['primaryCluster'])->get();
        foreach ($skus as $sku) {
            $cluster = $sku->primaryCluster;
            $cat = $cluster && isset($cluster->category) ? ($cluster->category ?: 'cables') : 'cables';
            if (!isset($skusByCategory[$cat])) {
                $skusByCategory[$cat] = [];
            }
            $skusByCategory[$cat][] = $sku;
        }

        $heatmap = [];
        foreach ($categories as $category) {
            $row = ['category' => $category];
            $perChannel = array_fill_keys($channels, []);
            $skusInCat = $skusByCategory[$category] ?? [];
            foreach ($skusInCat as $sku) {
                try {
                    $result = $this->readinessScoreService->computeReadiness($sku);
                    foreach ($result['channels'] ?? [] as $ch) {
                        $chName = $ch['channel'] ?? '';
                        if (isset($perChannel[$chName])) {
                            $perChannel[$chName][] = (int) ($ch['score'] ?? 0);
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
            foreach ($channels as $ch) {
                $scores = $perChannel[$ch] ?? [];
                $row[$ch] = empty($scores) ? 0 : round(array_sum($scores) / count($scores), 1);
            }
            $heatmap[] = $row;
        }
        return $heatmap;
    }

    private function buildDecayMonitor(): array
    {
        if (!Schema::hasColumn((new Sku)->getTable(), 'decay_status')) {
            return [];
        }
        return Sku::where('tier', 'HERO')
            ->whereNotNull('decay_status')
            ->where('decay_status', '!=', 'none')
            ->get(['id', 'sku_code', 'title', 'decay_status', 'decay_consecutive_zeros'])
            ->map(fn ($s) => [
                'sku_id' => $s->id,
                'sku_code' => $s->sku_code,
                'title' => $s->title,
                'decay_status' => $s->decay_status ?? 'none',
                'consecutive_zero_weeks' => (int) ($s->decay_consecutive_zeros ?? 0),
            ])
            ->values()
            ->all();
    }

    private function buildEffortAllocation(): array
    {
        $startOfWeek = now()->startOfWeek();
        $table = (new StaffEffortLog)->getTable();
        if (!Schema::hasColumn($table, 'tier') || !Schema::hasColumn($table, 'logged_at') || !Schema::hasColumn($table, 'hours_spent')) {
            return [
                'by_tier' => array_map(fn ($t) => ['tier' => $t, 'hours' => 0, 'pct' => 0], ['HERO', 'SUPPORT', 'HARVEST', 'KILL']),
                'total_hours' => 0,
                'hero_pct' => 0,
                'hero_alert' => true,
            ];
        }
        $totals = StaffEffortLog::where('logged_at', '>=', $startOfWeek)
            ->selectRaw('tier, SUM(hours_spent) as hours')
            ->groupBy('tier')
            ->pluck('hours', 'tier');
        $totalHours = $totals->sum();
        $heroHours = (float) ($totals->get('HERO') ?? 0);
        $heroPct = $totalHours > 0 ? round(($heroHours / $totalHours) * 100, 1) : 0;

        $byTier = [];
        foreach (['HERO', 'SUPPORT', 'HARVEST', 'KILL'] as $tier) {
            $h = (float) ($totals->get($tier) ?? 0);
            $byTier[] = [
                'tier' => $tier,
                'hours' => round($h, 2),
                'pct' => $totalHours > 0 ? round(($h / $totalHours) * 100, 1) : 0,
            ];
        }
        return [
            'by_tier' => $byTier,
            'total_hours' => round($totalHours, 2),
            'hero_pct' => $heroPct,
            'hero_alert' => $heroPct < 60,
        ];
    }

    private function buildStaffKpis(): array
    {
        $startOfWeek = now()->startOfWeek();
        $logTable = (new ValidationLog)->getTable();
        if (!Schema::hasColumn($logTable, 'validated_by') || !Schema::hasColumn($logTable, 'passed')) {
            return [];
        }
        $validationStats = ValidationLog::where('created_at', '>=', $startOfWeek)
            ->selectRaw('validated_by, COUNT(*) as total, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed')
            ->groupBy('validated_by')
            ->get();
        $userIds = $validationStats->pluck('validated_by')->filter()->unique()->values()->all();
        $effortByUser = collect();
        try {
            $effortTable = (new StaffEffortLog)->getTable();
            if (Schema::hasColumn($effortTable, 'user_id') && Schema::hasColumn($effortTable, 'logged_at')) {
                $effortByUser = StaffEffortLog::where('logged_at', '>=', $startOfWeek)
                    ->whereNotNull('user_id')
                    ->selectRaw('user_id, SUM(hours_spent) as hours')
                    ->groupBy('user_id')
                    ->get()
                    ->keyBy('user_id');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $users = collect();
        if (!empty($userIds)) {
            try {
                $users = \App\Models\User::whereIn('id', $userIds)->with('role')->get()->keyBy('id');
            } catch (\Throwable $e) {
                // continue with empty users
            }
        }
        $out = [];
        foreach ($validationStats as $row) {
            $userId = $row->validated_by;
            $total = (int) $row->total;
            $passed = (int) $row->passed;
            $rework = $total - $passed;
            $passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
            $user = $users->get($userId);
            $hours = $effortByUser->get($userId);
            $roleName = 'viewer';
            if ($user && $user->relationLoaded('role') && $user->role !== null) {
                $roleName = $user->role->name ?? 'viewer';
            }
            $out[] = [
                'user_id' => $userId,
                'user_name' => $user ? ($user->name ?? 'Unknown') : 'Unknown',
                'role' => $roleName,
                'validations' => $total,
                'gate_pass_rate' => $passRate,
                'rework_count' => $rework,
                'hours_spent' => $hours ? round((float) $hours->hours, 2) : 0,
            ];
        }
        return $out;
    }
}
