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
use Illuminate\Support\Facades\Validator;

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
        $openapiSummary = $this->safeBuild('buildOpenApiDashboardSummary', []);

        return ResponseFormatter::format(array_merge([
            'tier_summary' => $tierSummary,
            'category_heatmap' => $categoryHeatmap,
            'decay_monitor' => $decayMonitor,
            'effort_allocation' => $effortAllocation,
            'staff_kpis' => $staffKpis,
            'rollback_candidates' => $rollbackCandidates,
        ], $openapiSummary));
    }

    /**
     * SOURCE: openapi.yaml DashboardSummaryResponse schema
     */
    private function buildOpenApiDashboardSummary(): array
    {
        $heroMin = (int) BusinessRules::get('readiness.hero_primary_channel_min');
        $heroQuery = Sku::query()->where('tier', 'hero');
        if (Schema::hasColumn('skus', 'is_active')) {
            $heroQuery->where('is_active', true);
        }
        $heroSkusAtRisk = (clone $heroQuery)->where(function ($q) use ($heroMin) {
            $q->whereNull('readiness_score')->orWhere('readiness_score', '<', $heroMin);
        })->count();

        $avgReadinessHero = round((float) ((clone $heroQuery)->avg('readiness_score') ?? 0), 1);

        $avgCitation = 0.0;
        if (Schema::hasColumn('skus', 'score_citation')) {
            try {
                $avgCitation = round((float) ((clone $heroQuery)->avg('score_citation') ?? 0), 2);
            } catch (\Throwable $e) {
                $avgCitation = 0.0;
            }
        }

        $openBriefs = 0;
        if (Schema::hasTable('content_briefs')) {
            try {
                $openBriefs = (int) DB::table('content_briefs')->whereIn('status', ['open', 'OPEN'])->count();
            } catch (\Throwable $e) {
                $openBriefs = 0;
            }
        }

        $publishedThisWeek = 0;
        $weekAgo = now()->subDays(7);
        if (Schema::hasColumn('skus', 'last_published_at')) {
            try {
                $publishedThisWeek = (int) Sku::query()
                    ->whereNotNull('last_published_at')
                    ->where('last_published_at', '>=', $weekAgo)
                    ->count();
            } catch (\Throwable $e) {
                $publishedThisWeek = 0;
            }
        }

        $effort = $this->safeBuild('buildEffortAllocation', ['hero_pct' => 0]);
        $heroTimePct = isset($effort['hero_pct']) ? round((float) $effort['hero_pct'], 1) : 0.0;

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §9.5
        $ga4Delayed = false;
        $ga4DelayedMessage = null;
        $ga4Badge = 'ok';
        if (Schema::hasTable('sync_status')) {
            try {
                $ga4 = DB::table('sync_status')->where('service', 'ga4')->first();
                if ($ga4) {
                    $status = (string) ($ga4->status ?? 'ok');
                    $ga4Badge = $status;
                    $lastSuccess = $ga4->last_success_at ? (string) $ga4->last_success_at : null;
                    $lastErrorAt = $ga4->last_error_at ? (string) $ga4->last_error_at : null;
                    if ($status === 'delayed' || ($lastErrorAt && (!$lastSuccess || $lastErrorAt > $lastSuccess))) {
                        $ga4Delayed = true;
                        $ga4DelayedMessage = 'GA4 data delayed — last sync: ' . ($lastSuccess ?? 'never');
                    }
                }
            } catch (\Throwable $e) {
                // fail-soft for dashboard
            }
        }

        return [
            'hero_skus_at_risk' => $heroSkusAtRisk,
            'avg_readiness_hero' => $avgReadinessHero,
            'avg_citation_rate_pct' => $avgCitation,
            'hero_time_pct' => $heroTimePct,
            'open_briefs_count' => $openBriefs,
            'skus_published_this_week' => $publishedThisWeek,
            'ga4_delayed' => $ga4Delayed,
            'ga4_delayed_message' => $ga4DelayedMessage,
            'ga4_badge' => $ga4Badge,
            'products_completed_this_week' => $this->computeProductsCompletedThisWeek(),
            'first_submit_pass_rate' => $this->computeFirstSubmitPassRateThisWeek(),
            'avg_time_per_sku' => $this->computeAvgTimePerSkuMinutesThisWeek(),
            'hero_skus_pct' => $this->computeHeroSkusPortfolioPct(),
        ];
    }

    /**
     * SOURCE: CIE_v232_UI_Restructure_Instructions.docx §5 — share of SKUs in Hero tier (portfolio “Hero %”).
     */
    private function computeHeroSkusPortfolioPct(): ?float
    {
        $q = Sku::query();
        if (Schema::hasColumn((new Sku)->getTable(), 'is_active')) {
            $q->where('is_active', true);
        }
        $total = (clone $q)->count();
        if ($total === 0) {
            return null;
        }
        $hero = (clone $q)->where('tier', 'hero')->count();

        return round(100.0 * $hero / $total, 1);
    }

    /**
     * SOURCE: CIE_v232_UI_Restructure_Instructions.docx §5 — SKUs published since Monday 00:00 app timezone (aligned with staff KPI week).
     */
    private function computeProductsCompletedThisWeek(): ?int
    {
        if (!Schema::hasColumn((new Sku)->getTable(), 'last_published_at')) {
            return null;
        }
        $start = now()->startOfWeek();

        return (int) Sku::query()
            ->whereNotNull('last_published_at')
            ->where('last_published_at', '>=', $start)
            ->count();
    }

    /**
     * Approximation: share of SKUs whose first validation_logs row this week has passed=true.
     */
    private function computeFirstSubmitPassRateThisWeek(): ?float
    {
        if (!Schema::hasTable('validation_logs') || !Schema::hasColumn('validation_logs', 'passed')) {
            return null;
        }
        $hasCreated = Schema::hasColumn('validation_logs', 'created_at');
        $hasValidated = Schema::hasColumn('validation_logs', 'validated_at');
        if (!$hasCreated && !$hasValidated) {
            return null;
        }
        $start = now()->startOfWeek();
        $skuIds = DB::table('validation_logs')
            ->select('sku_id')
            ->where(function ($q) use ($hasCreated, $hasValidated, $start) {
                if ($hasCreated && $hasValidated) {
                    $q->whereRaw('COALESCE(validation_logs.created_at, validation_logs.validated_at) >= ?', [$start]);
                } elseif ($hasCreated) {
                    $q->where('created_at', '>=', $start);
                } else {
                    $q->where('validated_at', '>=', $start);
                }
            })
            ->distinct()
            ->pluck('sku_id');
        if ($skuIds->isEmpty()) {
            return null;
        }
        $firstPassed = 0;
        $total = 0;
        foreach ($skuIds as $sid) {
            $q = DB::table('validation_logs')->where('sku_id', $sid);
            if ($hasCreated && $hasValidated) {
                $q->whereRaw('COALESCE(validation_logs.created_at, validation_logs.validated_at) >= ?', [$start]);
                $q->orderByRaw('COALESCE(validation_logs.created_at, validation_logs.validated_at) ASC');
            } elseif ($hasCreated) {
                $q->where('created_at', '>=', $start)->orderBy('created_at');
            } else {
                $q->where('validated_at', '>=', $start)->orderBy('validated_at');
            }
            $row = $q->orderBy('id')->first(['passed']);
            if ($row) {
                $total++;
                if ((int) ($row->passed ?? 0) === 1) {
                    $firstPassed++;
                }
            }
        }

        return $total > 0 ? round(100.0 * $firstPassed / $total, 1) : null;
    }

    /**
     * Average minutes from first sku audit_log (create/update) to last_published_at for SKUs published this week.
     */
    private function computeAvgTimePerSkuMinutesThisWeek(): ?float
    {
        if (!Schema::hasTable('audit_log') || !Schema::hasColumn((new Sku)->getTable(), 'last_published_at')) {
            return null;
        }
        $start = now()->startOfWeek();
        $skus = Sku::query()
            ->whereNotNull('last_published_at')
            ->where('last_published_at', '>=', $start)
            ->get(['id', 'last_published_at']);
        if ($skus->isEmpty()) {
            return null;
        }
        $useTs = Schema::hasColumn('audit_log', 'timestamp');
        $diffs = [];
        foreach ($skus as $sku) {
            $pub = $sku->last_published_at;
            if ($pub === null) {
                continue;
            }
            $pubCarbon = \Carbon\Carbon::parse((string) $pub);
            $q = DB::table('audit_log')
                ->where('entity_type', 'sku')
                ->where('entity_id', (string) $sku->id)
                ->whereIn('action', ['create', 'update']);
            if ($useTs) {
                $q->whereRaw('COALESCE(`timestamp`, created_at) <= ?', [$pubCarbon]);
                $q->orderByRaw('COALESCE(`timestamp`, created_at) ASC');
            } else {
                $q->where('created_at', '<=', $pubCarbon)->orderBy('created_at');
            }
            $first = $q->first(['created_at', 'timestamp']);
            if (!$first) {
                continue;
            }
            $t0 = $useTs && $first->timestamp
                ? \Carbon\Carbon::parse((string) $first->timestamp)
                : \Carbon\Carbon::parse((string) $first->created_at);
            $diffs[] = max(0, $t0->diffInMinutes($pubCarbon));
        }

        return $diffs !== [] ? round(array_sum($diffs) / count($diffs), 1) : null;
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
        $hasMeasurementStatus = Schema::hasColumn('gsc_baselines', 'measurement_status');
        if (!$hasMeasurementStatus) {
            return ['sku_ids' => [], 'count' => 0];
        }
        $ids = DB::table('gsc_baselines')
            ->where('measurement_status', 'complete')
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
     * GET /api/v1/audit-results/weekly-scores
     * SOURCE: openapi.yaml WeeklyScoresResponse; CIE_Master_Developer_Build_Spec.docx §12 — AI audit aggregates (Concept B).
     */
    public function getAuditWeeklyScores(Request $request)
    {
        if (!Schema::hasTable('ai_audit_results') || !Schema::hasTable('ai_audit_runs')) {
            return response()->json(['scores' => []], 200);
        }

        $weeks = (int) $request->query('weeks', 12);
        $weeks = max(1, min(52, $weeks));
        $categoryFilter = $request->query('category');
        $allowedCategories = ['cables', 'lampshades', 'bulbs', 'pendants'];
        if ($categoryFilter !== null && $categoryFilter !== '' && !in_array($categoryFilter, $allowedCategories, true)) {
            return ResponseFormatter::error('Invalid category', 400);
        }

        $bindings = [];
        $catSql = '';
        if ($categoryFilter !== null && $categoryFilter !== '') {
            $catSql = ' AND r.category = ? ';
            $bindings[] = $categoryFilter;
        }

        $weekBuckets = DB::select(
            'SELECT week_start_date FROM (
                SELECT DISTINCT DATE(COALESCE(air.week_ending, r.run_date)) AS week_start_date
                FROM ai_audit_results AS air
                INNER JOIN ai_audit_runs AS r ON r.run_id = air.run_id
                WHERE r.status = \'completed\' ' . $catSql . '
            ) AS w
            ORDER BY week_start_date DESC
            LIMIT ' . (int) $weeks,
            $bindings
        );

        if (empty($weekBuckets)) {
            return response()->json(['scores' => []], 200);
        }

        $weekDates = array_map(fn ($w) => (string) $w->week_start_date, $weekBuckets);
        $scoresOut = [];

        foreach ($weekDates as $weekStartDate) {
            $cats = $categoryFilter ? [$categoryFilter] : $allowedCategories;
            foreach ($cats as $cat) {
                $row = $this->buildWeeklyScoreEntryForWeekCategory($weekStartDate, $cat);
                if ($row !== null) {
                    $scoresOut[] = $row;
                }
            }
        }

        usort($scoresOut, function ($a, $b) {
            return strcmp((string) $b['week_start_date'], (string) $a['week_start_date']);
        });

        // SOURCE: openapi.yaml WeeklyScoresResponse — JSON root { scores: [...] } (no ResponseFormatter envelope).
        return response()->json(['scores' => $scoresOut], 200);
    }

    /**
     * Aggregate ai_audit_results for one calendar week bucket and category.
     *
     * @return array<string, mixed>|null
     */
    private function buildWeeklyScoreEntryForWeekCategory(string $weekStartDate, string $category): ?array
    {
        $engines = ['chatgpt', 'gemini', 'perplexity', 'google_sge'];

        $base = DB::table('ai_audit_results as air')
            ->join('ai_audit_runs as r', 'r.run_id', '=', 'air.run_id')
            ->where('r.status', 'completed')
            ->where('r.category', $category)
            ->whereRaw('DATE(COALESCE(air.week_ending, r.run_date)) = ?', [$weekStartDate]);

        $count = (clone $base)->count();
        if ($count === 0) {
            return null;
        }

        $avgRow = (clone $base)
            ->where(function ($q) {
                $q->whereNull('air.is_available')->orWhere('air.is_available', '=', 1)->orWhere('air.is_available', '=', true);
            })
            ->whereNotNull('air.score')
            ->selectRaw('AVG(air.score) as v')
            ->first();
        $avgScore = $avgRow && $avgRow->v !== null ? round((float) $avgRow->v, 4) : 0.0;

        $engineScores = [];
        foreach ($engines as $eng) {
            $er = (clone $base)
                ->where('air.engine', $eng)
                ->where(function ($q) {
                    $q->whereNull('air.is_available')->orWhere('air.is_available', '=', 1)->orWhere('air.is_available', '=', true);
                })
                ->whereNotNull('air.score')
                ->selectRaw('AVG(air.score) as v')
                ->first();
            $engineScores[$eng] = $er && $er->v !== null ? round((float) $er->v, 4) : 0.0;
        }

        $qZero = (clone $base)
            ->where('air.score', 0)
            ->where(function ($q) {
                $q->whereNull('air.is_available')->orWhere('air.is_available', '=', 1)->orWhere('air.is_available', '=', true);
            })
            ->selectRaw('COUNT(DISTINCT air.question_id) AS qz')
            ->first();
        $questionsAtZero = (int) ($qZero->qz ?? 0);

        return [
            'week_start_date' => $weekStartDate,
            'category' => $category,
            'avg_score' => $avgScore,
            'engine_scores' => $engineScores,
            'questions_at_zero' => $questionsAtZero,
        ];
    }

    /**
     * GET /api/v1/review/weekly-scores
     * SOURCE: CIE_v232_UI_Restructure_Instructions.docx §5 Step 5 — manual KPI weekly scores (Concept A).
     */
    public function weeklyKpiScores()
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
        // SOURCE: Weekly score validation audit — HTTP 400 on invalid body (not Laravel default 422).
        $validator = Validator::make($request->all(), [
            'week_start' => 'required|date',
            'score' => 'required|integer|min:1|max:10',
            'notes' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        if (!Schema::hasTable('weekly_scores')) {
            return ResponseFormatter::error('weekly_scores table does not exist', 500);
        }

        $hasNotes = false;
        try {
            $hasNotes = Schema::hasColumn('weekly_scores', 'notes');
        } catch (\Throwable $e) {
            $hasNotes = false;
        }

        $hasUserId = false;
        try {
            $hasUserId = Schema::hasColumn('weekly_scores', 'user_id');
        } catch (\Throwable $e) {
            $hasUserId = false;
        }

        $row = [
            'week_start' => $request->input('week_start'),
            'score' => $request->input('score'),
            'created_at' => now(),
        ];
        if ($hasNotes) {
            $row['notes'] = $request->input('notes', '');
        }
        if ($hasUserId) {
            $uid = auth()->id();
            if ($uid === null) {
                return ResponseFormatter::error('Authentication required', 401);
            }
            $row['user_id'] = $uid;
        }

        $id = DB::table('weekly_scores')->insertGetId($row);

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
        $hasBriefs = Schema::hasTable('content_briefs');

        return Sku::where('tier', 'HERO')
            ->whereNotNull('decay_status')
            ->where('decay_status', '!=', 'none')
            ->get(['id', 'sku_code', 'title', 'decay_status', 'decay_consecutive_zeros'])
            ->map(function ($s) use ($hasBriefs) {
                $row = [
                    'sku_id' => $s->id,
                    'sku_code' => $s->sku_code,
                    'title' => $s->title,
                    'decay_status' => $s->decay_status ?? 'none',
                    'consecutive_zero_weeks' => (int) ($s->decay_consecutive_zeros ?? 0),
                    'brief_status' => null,
                    'brief_deadline' => null,
                    'brief_completed_at' => null,
                ];
                if ($hasBriefs && in_array((string) ($s->decay_status ?? ''), ['auto_brief', 'escalated'], true)) {
                    $brief = DB::table('content_briefs')
                        ->where('sku_id', $s->id)
                        ->where('brief_type', 'DECAY_REFRESH')
                        ->orderByDesc('created_at')
                        ->first(['status', 'deadline', 'completed_at']);
                    if ($brief) {
                        $row['brief_status'] = $brief->status ?? null;
                        $row['brief_deadline'] = $brief->deadline ? (string) $brief->deadline : null;
                        $row['brief_completed_at'] = isset($brief->completed_at) && $brief->completed_at
                            ? (string) $brief->completed_at
                            : null;
                    }
                }

                return $row;
            })
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
