<?php
namespace App\Validators;

use App\Models\Sku;
use App\Models\SkuGateStatus;
use App\Models\AuditLog;
use App\Enums\ValidationStatus;
use App\Validators\Gates\G1_BasicInfoGate;
use App\Validators\Gates\G2_IntentGate;
use App\Validators\Gates\G3_SecondaryIntentGate;
use App\Validators\Gates\G4_AnswerBlockGate;
use App\Validators\Gates\G4_VectorGate;
use App\Validators\Gates\G5_TechnicalGate;
use App\Validators\Gates\G6_CommercialPolicyGate;
use App\Validators\Gates\G7_ExpertGate;
class GateValidator
{
    private array $gates = [
        G1_BasicInfoGate::class,
        G2_IntentGate::class,
        G3_SecondaryIntentGate::class,
        G4_AnswerBlockGate::class,
        G4_VectorGate::class,
        G5_TechnicalGate::class,
        G6_CommercialPolicyGate::class,
        G7_ExpertGate::class,
    ];
 
 public function validateAll(Sku $sku, bool $preserveStatus = false): array
 {
 $results = [];
 $overallPassed = true;
 $isDegraded = false;
 $blockingFailure = null;
 
        foreach ($this->gates as $gateClass) {
            $gate = new $gateClass();
            $result = $gate->validate($sku);
            $results[] = $result;

            // Log the gate check (legacy validation_logs)
            \App\Models\ValidationLog::create([
                'sku_id' => $sku->id,
                'gate_type' => $result->gate,
                'passed' => $result->passed,
                'reason' => $result->reason,
                'is_blocking' => $result->blocking,
                'similarity_score' => $result->metadata['similarity'] ?? null,
                'validated_by' => (function_exists('auth') && app()->bound('auth') && auth()->check()) ? auth()->id() : null
            ]);

            // Canonical per-gate status (sku_gate_status table, using business SKU code as sku_id)
            try {
                $status = 'pass';
                if (! $result->passed) {
                    $status = ($result->metadata['degraded'] ?? false) ? 'pending' : 'fail';
                }

                SkuGateStatus::updateOrCreate(
                    [
                        'sku_id'    => $sku->sku_code,
                        'gate_code' => $result->gate->value ?? (string) $result->gate,
                    ],
                    [
                        'status'        => $status,
                        'error_code'    => $result->metadata['error_code'] ?? null,
                        'error_message' => $result->reason,
                        'checked_at'    => now(),
                    ]
                );

                // §9 Audit: log gate check
                try {
                    AuditLog::create([
                        'entity_type' => 'gate_status',
                        'entity_id'   => $sku->id,
                        'action'      => $result->passed ? 'gate_pass' : 'gate_fail',
                        'field_name'  => $result->gate->value ?? (string) $result->gate,
                        'old_value'   => null,
                        'new_value'   => $result->passed ? 'pass' : 'fail',
                        'actor_id'    => (function_exists('auth') && auth()->check()) ? (string) auth()->id() : 'SYSTEM',
                        'actor_role'  => (function_exists('auth') && auth()->check() && auth()->user()->role) ? auth()->user()->role->name : 'system',
                        'timestamp'   => now(),
                        'user_id'     => (function_exists('auth') && auth()->check()) ? auth()->id() : null,
                        'ip_address'  => request() ? request()->ip() : null,
                        'user_agent'  => request() ? request()->userAgent() : null,
                        'created_at'  => now(),
                    ]);
                } catch (\Throwable $auditEx) {
                    // Fail-soft: do not break validation if audit_log missing columns
                }
            } catch (\Throwable $e) {
                // Fail-soft: do not break validation if sku_gate_status table or FK not yet in place
            }
 
 if (!$result->passed) {
 if ($result->blocking) {
 $overallPassed = false;
 if (!$blockingFailure) {
 $blockingFailure = $result;
 }
 }
 if ($result->metadata['degraded'] ?? false) {
 $isDegraded = true;
 }
 }
 }
 
 if ($overallPassed) {
 $status = ValidationStatus::VALID;
 $canPublish = true;
 $nextAction = 'SKU is ready for publication';
 } elseif ($isDegraded) {
 $status = ValidationStatus::DEGRADED;
 $canPublish = false;
 $nextAction = 'Save allowed but publication blocked. Validation will retry automatically.';
 } else {
 $status = ValidationStatus::INVALID;
 $canPublish = false;
 $nextAction = $blockingFailure ? $blockingFailure->reason : 'Fix validation errors before publication';
 }
 
        // Patch: If status is PENDING (submitted for review), do not overwrite it with automated status
        // We still run validation to get the results, but we keep the PENDING state for the queue.
        $currentStatus = $sku->fresh()->validation_status;
        
        $updateData = [
            'can_publish' => $canPublish,
            'last_validated_at' => now(),
            'ai_validation_pending' => $isDegraded
        ];

        if (!$preserveStatus && $currentStatus !== ValidationStatus::PENDING) {
            $updateData['validation_status'] = $status;
        }

        $sku->update($updateData);
 
 return [
 'sku_id' => $sku->id,
 'overall_status' => $status->value,
 'can_publish' => $canPublish,
 'gates' => array_map(fn($r) => $r->toArray(), $results),
 'next_action' => $nextAction
 ];
 }
}
