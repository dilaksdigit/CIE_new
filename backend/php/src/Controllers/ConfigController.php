<?php

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * CIE config API — GET/PUT system configuration (gate thresholds, tier weights, etc.).
 * GET: Returns BusinessRules from DB grouped by module (readiness, scoring, content, gates, etc.)
 *      so frontend has zero hard-coded thresholds. Merges with storage/app/cie_config.json if present.
 * PUT: Admin-only; writes to cie_config.json (non-threshold overrides).
 */
class ConfigController
{
    private function configPath(): string
    {
        return storage_path('app/cie_config.json');
    }

    /**
     * Build nested config from business_rules: { readiness: { hero_primary_channel_min: 85 }, scoring: { ... }, ... }
     * Uses only the 52 spec rules; adds alias/default keys so frontend receives expected shape (scoring, staff, decay.hero_citation_red_threshold, dashboard).
     */
    private function thresholdsFromBusinessRules(): array
    {
        if (!Schema::hasTable('business_rules')) {
            return [];
        }
        $all = BusinessRules::all();
        $out = [];
        foreach ($all as $ruleKey => $value) {
            $parts = explode('.', $ruleKey, 2);
            $module = $parts[0];
            $subKey = $parts[1] ?? $ruleKey;
            if (!isset($out[$module])) {
                $out[$module] = [];
            }
            $out[$module][$subKey] = $value;
        }
        // Alias spec keys for frontend (no extra rules in DB)
        if (isset($out['readiness']['gold_threshold'])) {
            $out['scoring'] = $out['scoring'] ?? [];
            $out['scoring']['chs_gold_threshold'] = $out['readiness']['gold_threshold'];
            $out['scoring']['chs_silver_threshold'] = $out['readiness']['silver_threshold'] ?? 65;
        }
        if (isset($out['decay']['hero_citation_danger'])) {
            $out['decay']['hero_citation_red_threshold'] = (int) round((float) $out['decay']['hero_citation_danger'] * 100);
        }
        if (isset($out['effort']['hero_allocation_danger'])) {
            $out['dashboard'] = $out['dashboard'] ?? [];
            $out['dashboard']['hero_effort_alert_pct'] = (int) round((float) $out['effort']['hero_allocation_danger'] * 100);
        }
        // Staff KPI thresholds (not in spec; hard-coded defaults)
        $out['staff'] = array_merge($out['staff'] ?? [], [
            'gate_pass_rate_green' => 80,
            'gate_pass_rate_amber' => 60,
            'weekly_score_green' => 8,
            'weekly_score_amber' => 6,
        ]);
        return $out;
    }

    public function index()
    {
        $config = $this->thresholdsFromBusinessRules();

        $path = $this->configPath();
        if (File::exists($path)) {
            $fileConfig = json_decode(File::get($path), true) ?: [];
            $config = array_merge($config, $fileConfig);
        }

        return ResponseFormatter::format($config);
    }

    public function update(Request $request)
    {
        $path = $this->configPath();
        $existing = [];
        if (File::exists($path)) {
            $existing = json_decode(File::get($path), true) ?: [];
        }

        $merged = array_merge($existing, $request->all());
        File::put($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ResponseFormatter::format($merged);
    }
}
