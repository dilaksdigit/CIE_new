<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G5_TechnicalGate implements GateInterface
{
 public function validate(Sku $sku): GateResult
 {
 $cluster = $sku->primaryCluster;
 if (!$cluster) {
 return new GateResult(
 gate: GateType::G5_TECHNICAL,
 passed: false,
 reason: 'No cluster assigned. Cannot validate technical specs.',
 blocking: true
 );
 }
 
 $requiredSpecs = $cluster->required_specifications ?? [];
 $skuSpecs = $sku->specifications ?? [];
 
 $missing = [];
 foreach ($requiredSpecs as $specName) {
 if (!isset($skuSpecs[$specName]) || empty($skuSpecs[$specName])) {
 $missing[] = $specName;
 }
 }
 
 if (count($missing) > 0) {
 return new GateResult(
 gate: GateType::G5_TECHNICAL,
 passed: false,
 reason: 'Missing required specifications: ' . implode(', ', $missing),
 blocking: true
 );
 }

 // Patch 5: Best-For/Not-For from BusinessRules (g5.best_for_min, g5.not_for_min) for Hero/Support only
 $tier = strtoupper((string) ($sku->tier->value ?? $sku->tier ?? ''));
 if (in_array($tier, ['HERO', 'SUPPORT'], true)) {
     $bestForMin = (int) BusinessRules::get('g5.best_for_min', 2);
     $notForMin = (int) BusinessRules::get('g5.not_for_min', 1);
     $bestFor = self::parseListAttribute($sku->best_for);
     $notFor = self::parseListAttribute($sku->not_for);
     if (count($bestFor) < $bestForMin || count($notFor) < $notForMin) {
         return new GateResult(
             gate: GateType::G5_TECHNICAL,
             passed: false,
             reason: sprintf('Min %d Best-For (found %d) and %d Not-For (found %d) required.', $bestForMin, count($bestFor), $notForMin, count($notFor)),
             blocking: true
         );
     }
 }

 // Patch 4 §4.3: FAQ completeness is a readiness component only in v2.3.2, not a publish gate until v2.4.

 $unitIssues = $this->validateUnits($skuSpecs);
 if (count($unitIssues) > 0) {
 return new GateResult(
 gate: GateType::G5_TECHNICAL,
 passed: false,
 reason: 'Unit format issues: ' . implode(', ', $unitIssues),
 blocking: true
 );
 }
 
 return new GateResult(
 gate: GateType::G5_TECHNICAL,
 passed: true,
 reason: sprintf('All %d required specifications completed with valid units',
 count($requiredSpecs)),
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
