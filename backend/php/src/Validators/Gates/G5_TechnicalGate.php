<?php
// SOURCE: CLAUDE.md Section 6 (G5 rule — Hero/Support only); CIE_v231_Developer_Build_Pack G5 spec; Hardening_Addendum Patch 6
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1
// GAP_LOG: $sku->specifications is referenced at line ~50 but no specifications column exists in
// the spec schema (sku_master, sku_content, or skus in Section 6.1). The technical spec validation
// block will see an empty array and may produce false positives/negatives depending on whether the
// cluster has required_specifications. Awaiting architect decision on structured specs storage.

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G5_TechnicalGate implements GateInterface
{
    // SOURCE: ENF§2.1 — G5 = min 2 best_for + min 1 not_for ONLY. ENF§Page18 — only CIE_G5_BESTFOR_COUNT for G5.
    public function validate(Sku $sku): GateResult|array
    {
        // SOURCE: ENF§2.2 — G5 SUSPENDED for Harvest/Kill → not_applicable
        if (in_array($sku->tier, [TierType::HARVEST, TierType::KILL], true)) {
            return new GateResult(
                gate: GateType::G5_BEST_NOT_FOR,
                passed: true,
                reason: 'not_applicable',
                blocking: false,
                metadata: ['status' => 'not_applicable', 'user_message' => null]
            );
        }

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — thresholds from BusinessRules only (no numeric fallbacks)
        $minBestFor = (int) BusinessRules::get('gates.best_for_min_entries');
        $minNotFor = (int) BusinessRules::get('gates.not_for_min_entries');
        $bestFor = self::parseListAttribute($sku->best_for);
        $notFor = self::parseListAttribute($sku->not_for);

        // SOURCE: CIE_Master_Developer_Build_Spec §7.1 — all distinct violations returned simultaneously
        $results = [];
        $bestForCount = count($bestFor);
        $notForCount = count($notFor);
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.3 — CIE_G5_BESTFOR_COUNT; detail must distinguish best_for vs not_for.
        // FIX: G5-02 — Distinct user-facing copy per field (same error_code per spec table).
        if ($bestForCount < $minBestFor) {
            $results[] = new GateResult(
                gate: GateType::G5_BEST_NOT_FOR,
                passed: false,
                reason: 'Insufficient best_for entries',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G5_BESTFOR_COUNT',
                    'detail' => "Only {$bestForCount} best_for entries. Minimum is {$minBestFor}.",
                    'user_message' => "Add at least {$minBestFor} 'Best For' use cases for this product.",
                ]
            );
        }
        if ($notForCount < $minNotFor) {
            $results[] = new GateResult(
                gate: GateType::G5_BEST_NOT_FOR,
                passed: false,
                reason: 'Insufficient not_for entries',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G5_BESTFOR_COUNT',
                    'detail' => "{$notForCount} not_for entries. Minimum is {$minNotFor}.",
                    'user_message' => "Add at least {$minNotFor} 'Not Suitable For' entry for this product.",
                ]
            );
        }

        if ($results !== []) {
            return $results;
        }

        // GAP_LOG: required_specifications and validateUnits() removed from G5 publish gate per ENF§2.1.
        return new GateResult(
            gate: GateType::G5_BEST_NOT_FOR,
            passed: true,
            reason: "{$bestForCount} best-for, {$notForCount} not-for",
            metadata: []
        );
    }
 
 private function validateUnits(array $specs): array
 {
 $issues = [];
 $standardUnits = ['lbs', 'kg', 'oz', 'g', 'in', 'cm', 'ft', 'm', 'mm'];
 foreach ($specs as $name => $value) {
 if (preg_match('/\d+\s*([a-zA-Z]+)/', (string)$value, $matches)) {
 $unit = strtolower($matches[1]);
 if (!in_array($unit, $standardUnits)) {
 $issues[] = sprintf('%s: use standard units (found "%s")', $name, $unit);
 }
 }
 }
 return $issues;
 }

 /** Parse best_for/not_for whether stored as JSON array or comma-separated string. */
 private static function parseListAttribute($value): array
 {
     if (is_array($value)) {
         return array_values(array_filter(array_map('trim', $value)));
     }
     $raw = $value ?? '';
     if (is_string($raw) && (str_starts_with(trim($raw), '[') || str_starts_with(trim($raw), '{'))) {
         $decoded = json_decode($raw, true);
         if (is_array($decoded)) {
             return array_values(array_filter(array_map('trim', $decoded)));
         }
     }
     return array_filter(array_map('trim', explode(',', (string) $raw)));
 }
}
