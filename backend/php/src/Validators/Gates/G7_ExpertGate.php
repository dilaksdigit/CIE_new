<?php
namespace App\Validators\Gates;
use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
class G7_ExpertGate implements GateInterface
{
 public function validate(Sku $sku): GateResult
 {
 $isBlocking = in_array($sku->tier, [TierType::HERO, TierType::SUPPORT]);
 // Canonical spec: expert_authority (single statement referencing standard/cert) is sufficient for Hero/Support
 $hasAuthority = !empty(trim((string) ($sku->expert_authority ?? $sku->expert_authority_name ?? '')));
 $hasLegacyFields = !empty(trim((string) ($sku->expert_author ?? ''))) && !empty(trim((string) ($sku->expert_credentials ?? '')));

 if ($hasAuthority || $hasLegacyFields) {
     // expert_authority alone is sufficient; legacy fields require a recent review date
     $reviewOk = false;
     if (!empty($sku->review_date)) {
         $reviewOk = strtotime($sku->review_date) >= strtotime('-1 year');
     }
     if ($hasAuthority || ($hasLegacyFields && $reviewOk)) {
         return new GateResult(
             gate: GateType::G7_EXPERT,
             passed: true,
             reason: $hasAuthority
                 ? 'Expert authority statement (standard/cert) present.'
                 : sprintf('Expert authority confirmed (author: %s, reviewed: %s)', $sku->expert_author, $sku->review_date),
             blocking: false
         );
     }
 }

 $missing = [];
 if (!$hasAuthority && !$sku->expert_author) $missing[] = 'Expert author or expert_authority';
 if (!$hasAuthority && !$sku->expert_credentials) $missing[] = 'Expert credentials';
 if (!$sku->review_date || strtotime($sku->review_date) < strtotime('-1 year')) {
     if (!$hasAuthority) $missing[] = 'Recent review (within 1 year)';
 }

 if (count($missing) > 0) {
 return new GateResult(
 gate: GateType::G7_EXPERT,
 passed: false,
 reason: 'Missing expert authority fields: ' . implode(', ', $missing) .
 ($isBlocking ? ' (REQUIRED for ' . $sku->tier->value . ' tier)' : ' (warning only)'),
 blocking: $isBlocking
 );
 }

 return new GateResult(
 gate: GateType::G7_EXPERT,
 passed: true,
 reason: 'Expert authority confirmed.',
 blocking: false
 );
 }
}
