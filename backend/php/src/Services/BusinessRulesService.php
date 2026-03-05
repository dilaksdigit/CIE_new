<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CIE v2.3.2 – Business rules from DB. No hard-coded thresholds in engines.
 * get($key) throws when key is missing and no default given. Cache invalidated on every rule update.
 */
class BusinessRulesService
{
    private const CACHE_KEY = 'cie_business_rules';
    private const CACHE_TTL = 300;

    /**
     * Get a rule value by key. Returns typed value (int, float, bool, string, array).
     * @param string $key e.g. 'gates.vector_similarity_min'
     * @param mixed $default If provided, return this when key is missing (no error).
     * @return mixed
     * @throws \RuntimeException when key is missing and no default provided
     */
    public function get(string $key, $default = null): mixed
    {
        $all = $this->all();
        if (!array_key_exists($key, $all)) {
            if (func_num_args() >= 2) {
                return $default;
            }
            throw new \RuntimeException("Business rule key not found: {$key}");
        }
        return $all[$key];
    }

    /**
     * Get all rules as key => typed value. Uses cache; invalidate after any update.
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $ttl = (int) $this->getRaw('business_rules.cache_ttl_seconds', 300);
        return Cache::remember(self::CACHE_KEY, $ttl, function () {
            return $this->loadFromDb();
        });
    }

    /**
     * Load rules from DB and type-cast. Bypasses cache.
     * @return array<string, mixed>
     */
    private function loadFromDb(): array
    {
        if (!Schema::hasTable('business_rules')) {
            return $this->getDefaults();
        }
        $rows = DB::table('business_rules')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->rule_key] = $this->cast($row->value, $row->value_type ?? 'string');
        }
        return $out;
    }

    private function getRaw(string $key, $default = null): mixed
    {
        if (!Schema::hasTable('business_rules')) {
            return $default;
        }
        $row = DB::table('business_rules')->where('rule_key', $key)->first();
        return $row ? $this->cast($row->value, $row->value_type ?? 'string') : $default;
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Invalidate cache (call after any business_rules row update).
     */
    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Defaults when table does not exist (fallback so app can boot).
     * @return array<string, mixed>
     */
    // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 5.3 (all 52 rules)
    private function getDefaults(): array
    {
        return [
            // Tier weights
            'tier.margin_weight' => 0.40,
            'tier.cppc_weight' => 0.25,
            'tier.velocity_weight' => 0.20,
            'tier.returns_weight' => 0.15,
            'tier.manual_override_expiry_days' => 90,
            'tier.hero_percentile' => 80,
            'tier.support_percentile' => 30,
            'tier.harvest_percentile' => 10,

            // Gates
            'gates.answer_block_min_chars' => 120,
            'gates.answer_block_max_chars' => 2000,
            'gates.vector_similarity_min' => 0.72,
            'gates.best_for_min_entries' => 2,
            'gates.not_for_min_entries' => 1,
            'gates.secondary_intent_max' => 3,
            'gates.meta_title_max_chars' => 70,
            'gates.meta_description_max_chars' => 160,
            'gates.meta_description_min_chars' => 80,

            // Sync schedules
            'sync.gsc_cron_schedule' => '0 2 * * 1',
            'sync.ga4_cron_schedule' => '0 3 * * 1',
            'sync.erp_cron_schedule' => '0 4 * * 1',
            'sync.ai_audit_cron_schedule' => '0 5 * * 1',
            'sync.baseline_lookback_weeks' => 8,

            // Decay
            'decay.yellow_flag_weeks' => 1,
            'decay.alert_weeks' => 2,
            'decay.auto_brief_weeks' => 3,
            'decay.escalate_weeks' => 4,
            'decay.auto_brief_deadline_days' => 7,
            'decay.hero_citation_target' => 0.85,
            'decay.hero_citation_danger' => 0.60,
            'decay.audit_question_count' => 5,
            'decay.quorum_minimum' => 3,

            // CIS
            'cis.measurement_window_d15' => 15,
            'cis.measurement_window_d30' => 30,

            // CHS weights
            'chs.intent_alignment_weight' => 0.5,
            'chs.green_threshold' => 0.8,
            'chs.amber_threshold' => 0.6,

            // Effort
            'effort.hero_allocation_target' => 0.25,
            'effort.hero_allocation_danger' => 0.10,

            // Readiness
            'readiness.deadline_days_after_completion' => 14,
            'readiness.valid_cluster_id' => 10,
            'readiness.has_primary_intent' => 10,
            'readiness.has_secondary_intents' => 10,
            'readiness.answer_block_passes_g4' => 15,
            'readiness.best_for_not_for_populated' => 10,
            'readiness.expert_authority_present' => 10,
            'readiness.json_ld_renders_valid' => 10,
            'readiness.images_meet_channel' => 10,
            'readiness.pricing_present' => 10,
            'readiness.category_specific_complete' => 5,

            // Channel
            'channel.shopify_rate_limit' => 2.0,
            'channel.gmc_rate_limit' => 1.0,

            // Validation
            'validation.http_fail_status' => 400,
        ];
    }
}
