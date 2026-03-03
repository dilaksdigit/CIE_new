<?php

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * CIE config API — GET/PUT system configuration (gate thresholds, tier weights, etc.).
 * Stored in storage/app/cie_config.json. Admin-only for PUT.
 */
class ConfigController
{
    private const HTTP_BAD_REQUEST = 400;
    private const DIR_PERMISSION   = 0755;

    private function configPath(): string
    {
        return storage_path('app/cie_config.json');
    }

    /**
     * Every value from BusinessRules::get(rule_key, fallback). No raw numbers outside get() (Phase 0 Check 0.1).
     */
    private function defaultConfig(): array
    {
        return [
            'gate_thresholds' => [
                'answer_block_min' => (int) BusinessRules::get('g4.answer_block_min', 250),
                'answer_block_max' => (int) BusinessRules::get('g4.answer_block_max', 300),
                'title_max_length' => (int) BusinessRules::get('content.title_max_length', 250),
                'vector_threshold' => (float) BusinessRules::get('gates.vector_similarity_min', 0.72),
                'title_intent_min' => (int) BusinessRules::get('content.title_intent_min', 20),
            ],
            'tier_score_weights' => [
                'margin_weight' => (float) BusinessRules::get('tier.margin_weight', 0.40),
                'velocity_weight' => (float) BusinessRules::get('tier.velocity_weight', 0.20),
                'return_rate_weight' => (float) BusinessRules::get('tier.returns_weight', 0.15),
                'margin_rank_weight' => (float) BusinessRules::get('tier.margin_rank_weight', 0.20),
                'hero_threshold' => (int) BusinessRules::get('readiness.hero_primary_threshold', 85),
            ],
            'channel_thresholds' => [
                'hero_compete_min' => (int) BusinessRules::get('readiness.hero_primary_threshold', 85),
                'support_compete_min' => (int) BusinessRules::get('readiness.hero_all_channels_threshold', 70),
                'harvest' => (string) BusinessRules::get('channel.harvest_label', 'Excluded'),
                'kill' => (string) BusinessRules::get('channel.kill_label', 'Excluded'),
                'feed_regen_time' => (string) BusinessRules::get('audit.feed_regen_time_utc', '02:00'),
            ],
            'audit_settings' => [
                'audit_day' => (string) BusinessRules::get('audit.weekly_day', 'Monday'),
                'audit_time' => (string) BusinessRules::get('audit.weekly_time_utc', '09:00'),
                'questions_per_category' => (int) BusinessRules::get('audit.questions_per_category', 20),
                'engines' => (int) BusinessRules::get('audit.engines_count', 4),
                'decay_trigger' => (string) BusinessRules::get('audit.decay_trigger_label', 'Week 3'),
            ],
        ];
    }

    /**
     * GET /api/config — all values from BusinessRules (no hard-coded config).
     */
    public function index()
    {
        $merged = $this->defaultConfig();
        $path = $this->configPath();
        if (File::exists($path)) {
            $json = File::get($path);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $merged = array_replace_recursive($merged, $data);
            }
        }
        return ResponseFormatter::format($merged);
    }

    /**
     * PUT /api/config — Admin only (enforce via middleware in routes).
     */
    public function update(Request $request)
    {
        $payload = $request->all();
        if (!is_array($payload)) {
            return ResponseFormatter::error('Invalid config payload', self::HTTP_BAD_REQUEST);
        }
        $defaults = $this->defaultConfig();
        $merged = array_replace_recursive($defaults, $payload);
        $path = $this->configPath();
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, self::DIR_PERMISSION, true);
        }
        File::put($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return ResponseFormatter::format($merged);
    }
}
