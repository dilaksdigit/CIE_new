<?php
namespace App\Services;

use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Baseline capture for GSC/GA4 (Section 17 Check 9.3, 9.4).
 * Creates gsc_baselines row, fetches GSC then GA4 metrics via Python API, writes to same row.
 * Does not throw. Publish flow must not abort when baseline row creation or API fetch fails (Master Build Spec §9.5 / §2.7).
 */
class BaselineService
{
    public function getLandingUrl(Sku $sku): string
    {
        $base = rtrim(env('CIE_LANDING_BASE_URL', ''), '/');
        $code = $sku->sku_code ?? '';
        return $base === '' ? '' : $base . '/' . ltrim($code, '/');
    }

    /**
     * Create a new gsc_baselines row for this SKU. Returns baseline id or null.
     */
    public function createBaselineRow(string $skuId): ?int
    {
        try {
            $id = DB::table('gsc_baselines')->insertGetId([
                'sku_id' => $skuId,
                'baseline_captured_at' => now(),
                'gsc_status' => 'pending',
                'ga4_status' => 'pending',
            ]);
            return $id;
        } catch (\Throwable $e) {
            Log::warning('Baseline create row failed: ' . $e->getMessage(), ['sku_id' => $skuId]);
            return null;
        }
    }

    /**
     * Fetch GSC metrics from Python API for the given URL. Returns array or null on failure.
     */
    public function fetchGscMetrics(string $url): ?array
    {
        $base = rtrim(env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $endpoint = $base . '/baseline/gsc-metrics';
        try {
            $client = Http::timeout(30)->acceptJson();
            if ($token = env('CIE_ENGINE_TOKEN')) {
                $client = $client->withToken($token);
            }
            $response = $client->post($endpoint, ['url' => $url]);
            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('Baseline GSC fetch failed: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * Fetch GA4 metrics from Python API for the given URL. Returns array or null on failure.
     */
    public function fetchGa4Metrics(string $url): ?array
    {
        $base = rtrim(env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $endpoint = $base . '/baseline/ga4-metrics';
        try {
            $client = Http::timeout(30)->acceptJson();
            if ($token = env('CIE_ENGINE_TOKEN')) {
                $client = $client->withToken($token);
            }
            $response = $client->post($endpoint, ['url' => $url]);
            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('Baseline GA4 fetch failed: ' . $e->getMessage(), ['url' => $url]);
            return null;
        }
    }

    /**
     * Update gsc_baselines row with GSC data. Sets gsc_status to 'captured' or 'unbaselined'.
     */
    public function updateBaselineGsc(int $baselineId, ?array $gsc): void
    {
        if ($gsc === null || ($gsc['impressions'] === null && $gsc['clicks'] === null)) {
            DB::table('gsc_baselines')->where('id', $baselineId)->update([
                'gsc_status' => 'unbaselined',
            ]);
            return;
        }
        DB::table('gsc_baselines')->where('id', $baselineId)->update([
            'baseline_impressions' => $gsc['impressions'] ?? null,
            'baseline_clicks' => $gsc['clicks'] ?? null,
            'baseline_ctr' => $gsc['ctr'] ?? null,
            'baseline_avg_position' => $gsc['avg_position'] ?? null,
            'gsc_status' => 'captured',
        ]);
    }

    /**
     * Update gsc_baselines row with GA4 data. Sets ga4_status to 'captured' or 'unbaselined'.
     */
    public function updateBaselineGa4(int $baselineId, ?array $ga4): void
    {
        if ($ga4 === null || ($ga4['sessions'] === null && $ga4['conversion_rate'] === null)) {
            DB::table('gsc_baselines')->where('id', $baselineId)->update([
                'ga4_status' => 'unbaselined',
            ]);
            return;
        }
        DB::table('gsc_baselines')->where('id', $baselineId)->update([
            'baseline_organic_sessions' => $ga4['sessions'] ?? null,
            'baseline_conversion_rate' => $ga4['conversion_rate'] ?? null,
            'baseline_revenue' => $ga4['revenue'] ?? null,
            'ga4_status' => 'captured',
        ]);
    }

    /**
     * Store content snapshot in baseline row for rollback (Check 9.7).
     */
    public function updateBaselineContentSnapshot(int $baselineId, Sku $sku): void
    {
        $snapshot = [
            'title' => $sku->title,
            'short_description' => $sku->short_description,
            'long_description' => $sku->long_description,
            'ai_answer_block' => $sku->ai_answer_block ?? null,
            'best_for' => $sku->best_for,
            'not_for' => $sku->not_for,
            'expert_authority' => $sku->expert_authority ?? null,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('gsc_baselines', 'baseline_content_snapshot')) {
            DB::table('gsc_baselines')->where('id', $baselineId)->update([
                'baseline_content_snapshot' => json_encode($snapshot),
            ]);
        }
    }

    /**
     * Capture GSC baseline for SKU: create row, fetch GSC, update row. Returns baseline_id or null.
     */
    public function captureGsc(Sku $sku): ?int
    {
        $url = $this->getLandingUrl($sku);
        $baselineId = $this->createBaselineRow($sku->id);
        if ($baselineId === null) {
            return null;
        }
        $gsc = $this->fetchGscMetrics($url);
        $this->updateBaselineGsc($baselineId, $gsc);
        return $baselineId;
    }

    /**
     * Capture GA4 baseline into the same row (by baseline_id or latest for sku_id).
     */
    public function captureGa4(Sku $sku, ?int $baselineId = null): void
    {
        if ($baselineId === null) {
            $row = DB::table('gsc_baselines')
                ->where('sku_id', $sku->id)
                ->orderByDesc('id')
                ->first();
            $baselineId = $row ? $row->id : null;
        }
        if ($baselineId === null) {
            return;
        }
        $url = $this->getLandingUrl($sku);
        $ga4 = $this->fetchGa4Metrics($url);
        $this->updateBaselineGa4($baselineId, $ga4);
    }

    /**
     * Capture both GSC and GA4 baseline for SKU before deploy. Never throws.
     * Returns ['baseline_id' => int|null, 'has_gsc' => bool, 'has_ga4' => bool, 'no_baseline' => bool].
     */
    public function captureBaselineBeforeDeploy(Sku $sku): array
    {
        $baselineId = $this->captureGsc($sku);
        $this->captureGa4($sku, $baselineId);

        $hasGsc = false;
        $hasGa4 = false;
        if ($baselineId !== null) {
            $row = DB::table('gsc_baselines')->where('id', $baselineId)->first();
            if ($row) {
                $hasGsc = ($row->gsc_status ?? '') === 'captured';
                $hasGa4 = ($row->ga4_status ?? '') === 'captured';
                $this->updateBaselineContentSnapshot($baselineId, $sku);
            }
        }

        return [
            'baseline_id' => $baselineId,
            'has_gsc' => $hasGsc,
            'has_ga4' => $hasGa4,
            'no_baseline' => !$hasGsc && !$hasGa4,
        ];
    }

    /**
     * Section 17 Check 9.7 — true when SKU has a baseline with D+30 position worse than baseline (negative delta).
     */
    public function isRollbackCandidate(string $skuId): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('gsc_baselines')) {
            return false;
        }
        $row = DB::table('gsc_baselines')
            ->where('sku_id', $skuId)
            ->where('cis_status', 'complete')
            ->whereNotNull('d30_position')
            ->whereNotNull('baseline_avg_position')
            ->orderByDesc('id')
            ->first();
        if (!$row) {
            return false;
        }
        $d30 = (float) $row->d30_position;
        $base = (float) $row->baseline_avg_position;
        return $d30 > $base;
    }
}
