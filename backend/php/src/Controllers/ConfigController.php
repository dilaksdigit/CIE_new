<?php

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * CIE config API — GET/PUT system configuration (gate thresholds, tier weights, etc.).
 * Stored in storage/app/cie_config.json. Admin-only for PUT.
 */
class ConfigController
{
    private function configPath(): string
    {
        return storage_path('app/cie_config.json');
    }

    private function defaultConfig(): array
    {
        return [
            'gate_thresholds' => [
                'answer_block_min' => 250,
                'answer_block_max' => 300,
                'title_max_length' => 250,
                'vector_threshold' => 0.72,
                'title_intent_min' => 20,
            ],
            'tier_score_weights' => [
                'margin_weight' => 0.30,
                'velocity_weight' => 0.30,
                'return_rate_weight' => 0.20,
                'margin_rank_weight' => 0.20,
                'hero_threshold' => 75,
            ],
            'channel_thresholds' => [
                'hero_compete_min' => 85,
                'support_compete_min' => 70,
                'harvest' => 'Excluded',
                'kill' => 'Excluded',
                'feed_regen_time' => '02:00',
            ],
            'audit_settings' => [
                'audit_day' => 'Monday',
                'audit_time' => '06:00',
                'questions_per_category' => 20,
                'engines' => 4,
                'decay_trigger' => 'Week 3',
            ],
        ];
    }

    /**
     * GET /api/config — vector_threshold and tier weights from BusinessRules when table exists.
     */
    public function index()
    {
        $merged = $this->defaultConfig();
        if (Schema::hasTable('business_rules')) {
            try {
                $merged['gate_thresholds']['vector_threshold'] = (float) BusinessRules::get('gates.vector_similarity_min', 0.72);
            } catch (\Throwable $e) {
                // keep default
            }
        }
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
            return ResponseFormatter::error('Invalid config payload', 400);
        }
        $defaults = $this->defaultConfig();
        $merged = array_replace_recursive($defaults, $payload);
        $path = $this->configPath();
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return ResponseFormatter::format($merged);
    }
}
