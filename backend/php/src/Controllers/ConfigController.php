<?php

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    private const GROUP_TO_RULE_MAP = [
        'gate_thresholds' => [
            'answer_block_min' => 'gates.answer_block_min_chars',
            'answer_block_max' => 'gates.answer_block_max_chars',
            'title_max_length' => 'gates.meta_title_max_chars',
            'vector_threshold' => 'gates.vector_similarity_min',
            'title_intent_min' => 'gates.description_min_chars',
        ],
        'tier_score_weights' => [
            'margin_weight' => 'tier.margin_weight',
            'velocity_weight' => 'tier.velocity_weight',
            'return_rate_weight' => 'tier.returns_weight',
            'margin_rank_weight' => 'tier.cppc_weight',
            'hero_threshold' => 'tier.hero_percentile_threshold',
        ],
        'channel_thresholds' => [
            'hero_compete_min' => 'readiness.hero_primary_channel_min',
            'support_compete_min' => 'readiness.support_primary_channel_min',
            // harvest/kill/feed_regen_time remain file-based overrides
        ],
        'audit_settings' => [
            'questions_per_category' => 'decay.audit_question_count',
            'engines' => 'decay.quorum_minimum',
            // audit_day/audit_time/decay_trigger remain file-based overrides
        ],
    ];

    /**
     * Threshold-like keys must come from business_rules only.
     */
    private const FILE_BLOCKED_TOP_LEVEL_KEYS = [
        'gate_thresholds',
        'tier_score_weights',
        'channel_thresholds',
        'readiness',
        'gates',
        'tier',
        'scoring',
        'kpi',
        'decay',
        'chs',
    ];

    private function configPath(): string
    {
        return storage_path('app/cie_config.json');
    }

    private function stripThresholdOverrides(array $config): array
    {
        foreach (self::FILE_BLOCKED_TOP_LEVEL_KEYS as $key) {
            unset($config[$key]);
        }
        return $config;
    }

    private function groupedConfigFromBusinessRules(): array
    {
        $out = [
            'gate_thresholds' => [],
            'tier_score_weights' => [],
            'channel_thresholds' => [],
            'audit_settings' => [],
        ];

        if (!Schema::hasTable('business_rules')) {
            return $out;
        }

        foreach (self::GROUP_TO_RULE_MAP as $group => $pairs) {
            foreach ($pairs as $uiKey => $ruleKey) {
                $value = BusinessRules::get($ruleKey);
                if ($value !== null) {
                    $out[$group][$uiKey] = $value;
                }
            }
        }

        return $out;
    }

    private function fileConfig(): array
    {
        $path = $this->configPath();
        if (!File::exists($path)) {
            return [];
        }
        return json_decode(File::get($path), true) ?: [];
    }

    private function mergeFileOverrides(array $groupedConfig): array
    {
        $file = $this->fileConfig();
        foreach (['gate_thresholds', 'tier_score_weights', 'channel_thresholds', 'audit_settings'] as $group) {
            if (!isset($groupedConfig[$group])) {
                $groupedConfig[$group] = [];
            }
            if (isset($file[$group]) && is_array($file[$group])) {
                $groupedConfig[$group] = array_merge($groupedConfig[$group], $file[$group]);
            }
        }
        return $groupedConfig;
    }

    private function persistGroupedRules(array $input): void
    {
        if (!Schema::hasTable('business_rules')) {
            return;
        }

        foreach (self::GROUP_TO_RULE_MAP as $group => $pairs) {
            if (!isset($input[$group]) || !is_array($input[$group])) {
                continue;
            }
            foreach ($pairs as $uiKey => $ruleKey) {
                if (!array_key_exists($uiKey, $input[$group])) {
                    continue;
                }
                $value = $input[$group][$uiKey];
                DB::table('business_rules')
                    ->where('rule_key', $ruleKey)
                    ->update([
                        'value' => is_scalar($value) ? (string) $value : json_encode($value),
                        'updated_at' => now(),
                    ]);
            }
        }
        BusinessRules::invalidateCache();
    }

    public function index()
    {
        $config = $this->groupedConfigFromBusinessRules();
        $config = $this->mergeFileOverrides($config);
        return ResponseFormatter::format($config);
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $this->persistGroupedRules($input);

        // Keep file-based overrides for any keys not directly mapped to business_rules.
        $existing = $this->fileConfig();
        $merged = array_merge($existing, $this->stripThresholdOverrides($input));
        File::put($this->configPath(), json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Return canonical shape expected by frontend after persistence.
        $fresh = $this->mergeFileOverrides($this->groupedConfigFromBusinessRules());
        return ResponseFormatter::format($fresh);
    }
}
