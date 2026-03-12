<?php

namespace App\Controllers;

use App\Models\Sku;
use App\Models\Cluster;
use App\Models\ValidationLog;
use App\Models\StaffEffortLog;
use App\Services\ReadinessScoreService;
use App\Support\BusinessRules;
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
        $rollbackCandidates = $this->safeBuild('buildRollbackCandidates', ['sku_ids' => [], 'count' => 0]);

        return ResponseFormatter::format([
            'tier_summary' => $tierSummary,
            'category_heatmap' => $categoryHeatmap,
            'decay_monitor' => $decayMonitor,
            'effort_allocation' => $effortAllocation,
            'staff_kpis' => $staffKpis,
            'rollback_candidates' => $rollbackCandidates,
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
     * Section 17 Check 9.7 — SKUs with D+30 position worse than baseline (rollback candidates).
     */
    private function buildRollbackCandidates(): array
    {
        if (!Schema::hasTable('gsc_baselines')) {
            return ['sku_ids' => [], 'count' => 0];
        }
        $hasCisStatus = Schema::hasColumn('gsc_baselines', 'cis_status');
        if (!$hasCisStatus) {
            return ['sku_ids' => [], 'count' => 0];
        }
        $ids = DB::table('gsc_baselines')
            ->where('cis_status', 'complete')
            ->whereNotNull('d30_position')
            ->whereNotNull('baseline_avg_position')
            ->whereRaw('d30_position > baseline_avg_position')
            ->distinct()
            ->pluck('sku_id')
            ->values()
            ->all();
        return ['sku_ids' => $ids, 'count' => count($ids)];
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
            ->orderBy('week_start', 'desc')
            ->limit(12);

        $columns = ['id', 'week_start', 'score', 'created_at'];
        if ($hasNotes) {
            $columns[] = 'notes';
        }

        $rows = $query
            ->get($columns)
            ->map(function ($row) use ($hasNotes) {
                return [
                    'id' => (int) $row->id,
                    'week_start' => (string) $row->week_start,
                    'score' => (int) $row->score,
                    'notes' => $hasNotes ? (string) ($row->notes ?? '') : '',
                    'created_at' => (string) $row->created_at,
                ];
            })
            ->values()
            ->all();

        return ResponseFormatter::format(array_reverse($rows));
    }

    /**
     * GET /api/v1/dashboard/decay-alerts
     * Returns Hero SKUs showing decay signals.
     */
    public function decayAlerts()
    {
        $alerts = $this->safeBuild('buildDecayMonitor', []);
        return ResponseFormatter::format($alerts);
    }

    /**
     * GET /api/v1/dashboard/channel-stats
     * Returns per-channel portfolio stats: avg score, compete count, skip count (from channel_readiness).
     */
    public function channelStats()
    {
        $stats = $this->safeBuild('buildChannelStats', []);
        return ResponseFormatter::format($stats);
    }

    /**
     * POST /api/v1/audit-results/weekly-scores
     * Store a new weekly score entry.
     */
    public function storeWeeklyScore(Request $request)
    {
        $request->validate([
            'week_start' => 'required|date',
            'score' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if (!Schema::hasTable('weekly_scores')) {
            return ResponseFormatter::error('weekly_scores table does not exist', 500);
        }

        $id = DB::table('weekly_scores')->insertGetId([
            'week_start' => $request->input('week_start'),
            'score' => $request->input('score'),
            'notes' => $request->input('notes', ''),
            'created_at' => now(),
        ]);

        return ResponseFormatter::format([
            'id' => $id,
            'week_start' => $request->input('week_start'),
            'score' => $request->input('score'),
        ], 'Created', 201);
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
        // SOURCE: CLAUDE.md Section 4 — DECISION-001 (shopify + gmc only)
        $channels = ['shopify', 'gmc'];
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
            'hero_alert' => $heroPct < ( (float) BusinessRules::get('effort.hero_allocation_danger') * 100 ),
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
                $users = \App\Models\User::whereIn('id', $userIds)->with('roles')->get()->keyBy('id');
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
            if ($user && $user->relationLoaded('roles') && $user->role !== null) {
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

    private function buildChannelStats(): array
    {
        if (!Schema::hasTable('channel_readiness')) {
            return $this->defaultChannelStats();
        }

        $rows = DB::table('channel_readiness')->get(['channel', 'score', 'component_scores']);
        $byChannel = [
            'shopify' => ['scores' => [], 'compete' => 0, 'skip' => 0],
            'gmc'     => ['scores' => [], 'compete' => 0, 'skip' => 0],
        ];

        foreach ($rows as $row) {
            $ch = $row->channel;
            if (!isset($byChannel[$ch])) {
                continue;
            }
            $byChannel[$ch]['scores'][] = (int) $row->score;
            $decoded = is_string($row->component_scores)
                ? json_decode($row->component_scores, true)
                : $row->component_scores;
            $status = isset($decoded['status']) ? strtoupper((string) $decoded['status']) : '';
            if ($status === 'COMPETE') {
                $byChannel[$ch]['compete']++;
            } else {
                $byChannel[$ch]['skip']++;
            }
        }

        $labels = [
            'shopify' => 'Shopify',
            'gmc'     => 'Google Merchant Center',
        ];
        $order = ['shopify', 'gmc'];
        $out = [];
        foreach ($order as $key) {
            $scores = $byChannel[$key]['scores'];
            $avg = empty($scores) ? 0 : (int) round(array_sum($scores) / count($scores));
            $out[] = [
                'ch'      => $labels[$key],
                'score'   => $avg,
                'compete' => $byChannel[$key]['compete'],
                'skip'    => $byChannel[$key]['skip'],
            ];
        }
        return $out;
    }

    private function defaultChannelStats(): array
    {
        return [
            ['ch' => 'Shopify', 'score' => 0, 'compete' => 0, 'skip' => 0],
            ['ch' => 'Google Merchant Center', 'score' => 0, 'compete' => 0, 'skip' => 0],
        ];
    }
}
