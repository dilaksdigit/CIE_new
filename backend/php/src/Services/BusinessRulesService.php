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
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5
        $ttl = (int) config('cie.business_rules_cache_ttl', 300);
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
            throw new \RuntimeException(
                'business_rules table does not exist. Run migrations before using BusinessRules.'
            );
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
            throw new \RuntimeException(
                'business_rules table does not exist. Run migrations before using BusinessRules.'
            );
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

}
