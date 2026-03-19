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
 public function validate(Sku $sku): GateResult|array
 {
 if ($sku->tier === TierType::KILL) {
     return new GateResult(
         gate: GateType::G5_BEST_NOT_FOR,
         passed: true,
         reason: 'This product tier does not require Best-For/Not-For.',
         blocking: false
     );
 }

 if ($sku->tier === TierType::HARVEST) {
     return new GateResult(
         gate: GateType::G5_BEST_NOT_FOR,
         passed: true,
         reason: 'Best-For/Not-For check is not required for this product tier.',
         blocking: false
     );
 }

 $failures = [];

 // --- Technical-spec block (cluster, required specs, unit format) ---
 $cluster = $sku->primaryCluster;
 $requiredSpecs = [];
 if (!$cluster) {
     $failures[] = new GateResult(
         gate: GateType::G5_TECHNICAL,
         passed: false,
         reason: 'No cluster assigned. Cannot validate technical specs.',
         blocking: true
     );
 } else {
     $requiredSpecs = $cluster->required_specifications ?? [];
     $skuSpecs = $sku->specifications ?? [];

     $missing = [];
     foreach ($requiredSpecs as $specName) {
         if (!isset($skuSpecs[$specName]) || empty($skuSpecs[$specName])) {
             $missing[] = $specName;
         }
     }

     if (count($missing) > 0) {
         $failures[] = new GateResult(
             gate: GateType::G5_TECHNICAL,
             passed: false,
             reason: 'Missing required specifications: ' . implode(', ', $missing),
             blocking: true
         );
     }

     $unitIssues = $this->validateUnits($skuSpecs);
     if (count($unitIssues) > 0) {
         $failures[] = new GateResult(
             gate: GateType::G5_TECHNICAL,
             passed: false,
             reason: 'Unit format issues: ' . implode(', ', $unitIssues),
             blocking: true
         );
     }
 }

 // --- Best-For / Not-For: min 2 best_for + min 1 not_for for Hero/Support (CLAUDE.md Section 6 G5) ---
 if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT], true)) {
     $bestForMin = (int) BusinessRules::get('gates.best_for_min_entries');
     $notForMin = (int) BusinessRules::get('gates.not_for_min_entries');
     $bestFor = self::parseListAttribute($sku->best_for);
     $notFor = self::parseListAttribute($sku->not_for);

     if (count($bestFor) < $bestForMin) {
         $failures[] = new GateResult(
             gate: GateType::G5_BEST_NOT_FOR,
             passed: false,
             reason: 'Add at least 2 best-for use cases. These help customers understand when this product is the right choice.',
             blocking: true,
             metadata: ['user_message' => 'Add at least 2 best-for use cases. These help customers understand when this product is the right choice.']
         );
     }

     if (count($notFor) < $notForMin) {
         $failures[] = new GateResult(
             gate: GateType::G5_BEST_NOT_FOR,
             passed: false,
             reason: 'Add at least 1 not-for exclusion. This tells customers when they should choose a different product.',
             blocking: true,
             metadata: ['user_message' => 'Add at least 1 not-for exclusion. This tells customers when they should choose a different product.']
         );
     }
 }

 if (!empty($failures)) {
     return $failures;
 }

 return new GateResult(
     gate: GateType::G5_TECHNICAL,
     passed: true,
     reason: sprintf('All %d required specifications completed with valid units', count($requiredSpecs)),
     blocking: false
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
