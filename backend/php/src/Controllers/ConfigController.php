<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.1 — business_rules schema

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CIE config API — GET/PUT system configuration (gate thresholds, tier weights, etc.).
 * GET: Returns BusinessRules from DB grouped by module (readiness, scoring, content, gates, etc.)
 *      so frontend has zero hard-coded thresholds.
 * PUT: Admin-only; writes directly to business_rules.
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
                        'rule_value' => is_scalar($value) ? (string) $value : json_encode($value),
                        'last_changed_at' => now(),
                    ]);
            }
        }
        BusinessRules::invalidateCache();
    }

    public function index()
    {
        return ResponseFormatter::format($this->groupedConfigFromBusinessRules());
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $this->persistGroupedRules($input);
        return ResponseFormatter::format($this->groupedConfigFromBusinessRules());
    }
}
