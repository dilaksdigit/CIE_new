<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G5_TechnicalGate implements GateInterface
{
 public function validate(Sku $sku): GateResult|array
 {
 $tier = strtoupper((string) ($sku->tier->value ?? $sku->tier ?? ''));

 if ($tier === 'KILL') {
     return new GateResult(
         gate: GateType::G5_BEST_NOT_FOR,
         passed: true,
         reason: 'G5 N/A for Kill tier.',
         blocking: false
     );
 }

 if ($tier === 'HARVEST') {
     return new GateResult(
         gate: GateType::G5_BEST_NOT_FOR,
         passed: true,
         reason: 'G5 Suspended for Harvest tier.',
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

     // Patch 4 §4.3: FAQ completeness is a readiness component only in v2.3.2, not a publish gate until v2.4.

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

 // --- Best-For / Not-For block (independent, always evaluated for Hero/Support) ---
 if (in_array($tier, ['HERO', 'SUPPORT'], true)) {
     $bestForMin = (int) BusinessRules::get('g5.best_for_min', 2);
     $notForMin = (int) BusinessRules::get('g5.not_for_min', 1);
     $bestFor = self::parseListAttribute($sku->best_for);
     $notFor = self::parseListAttribute($sku->not_for);

     if (count($bestFor) < $bestForMin) {
         $failures[] = new GateResult(
             gate: GateType::G5_BEST_NOT_FOR,
             passed: false,
             reason: sprintf('best_for has %d entries; minimum is %d.', count($bestFor), $bestForMin),
             blocking: true,
             metadata: [
                 'error_code' => 'CIE_G5_BESTFOR_COUNT',
                 'user_message' => sprintf('At least %d Best-For applications are required.', $bestForMin),
             ]
         );
     }

     if (count($notFor) < $notForMin) {
         $failures[] = new GateResult(
             gate: GateType::G5_BEST_NOT_FOR,
             passed: false,
             reason: sprintf('not_for has %d entries; minimum is %d.', count($notFor), $notForMin),
             blocking: true,
             metadata: [
                 'error_code' => 'CIE_G5_BESTFOR_COUNT',
                 'user_message' => sprintf('At least %d Not-For application(s) are required.', $notForMin),
             ]
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
