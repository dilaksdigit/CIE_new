<?php
namespace App\Validators;

use App\Models\Sku;
use App\Models\SkuGateStatus;
use App\Models\AuditLog;
use App\Enums\ValidationStatus;
use App\Enums\TierType;
use App\Enums\GateType;
use App\Validators\Gates\G1_BasicInfoGate;
use App\Validators\Gates\G2_IntentGate;
use App\Validators\Gates\G3_SecondaryIntentGate;
use App\Validators\Gates\G4_AnswerBlockGate;
use App\Validators\Gates\G4_VectorGate;
use App\Validators\Gates\G5_TechnicalGate;
use App\Validators\Gates\G6_TierTagGate;
use App\Validators\Gates\G7_ExpertAuthorityGate;

class GateValidator
{
    private array $gates = [
        G1_BasicInfoGate::class,
        G2_IntentGate::class,
        G3_SecondaryIntentGate::class,
        G4_AnswerBlockGate::class,
        G4_VectorGate::class,
        G5_TechnicalGate::class,
        G6_TierTagGate::class,
        G7_ExpertAuthorityGate::class,
    ];

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §2.2 — Kill: only G1, G6, G6.1 execute; G2–G5, G7, VEC must not run
     * SOURCE: CIE_Master_Developer_Build_Spec §7 — Kill tier: content gates blocked; cluster + commercial policy only
     * SOURCE: CIE_Doc4b_Golden_Test_Data_Pack §3.1 — Kill SKU gate matrix
     */
    private function recordGateResult(
        Sku $sku,
        GateResult $result,
        array &$results,
        bool &$overallPassed,
        ?GateResult &$blockingFailure,
        bool &$isDegraded,
        bool &$hasVectorWarn
    ): void {
        $results[] = $result;

        \App\Models\ValidationLog::create([
            'sku_id' => $sku->id,
            'gate_type' => $result->gate->value ?? (string) $result->gate,
            'passed' => $result->passed,
            'reason' => $result->reason,
            'is_blocking' => $result->blocking,
            'similarity_score' => $result->metadata['similarity'] ?? null,
            'validated_by' => (function_exists('auth') && app()->bound('auth') && auth()->check()) ? auth()->id() : null,
        ]);

        try {
            $status = 'pass';
            if (! $result->passed) {
                if ($result->metadata['degraded'] ?? false) {
                    $status = 'pending';
                } elseif (($result->metadata['status'] ?? '') === 'warn') {
                    $status = 'warn';
                } else {
                    $status = 'fail';
                }
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
            }
        } catch (\Throwable $e) {
        }

        if (! $result->passed) {
            if ($result->blocking) {
                $overallPassed = false;
                if (! $blockingFailure) {
                    $blockingFailure = $result;
                }
            }
        }
        if ($result->metadata['degraded'] ?? false) {
            $isDegraded = true;
        }
        if (($result->metadata['status'] ?? '') === 'warn' || ($result->metadata['warn_only'] ?? false)) {
            $hasVectorWarn = true;
        }
    }

    public function validateAll(Sku $sku, bool $preserveStatus = false): array
    {
        $results = [];
        $overallPassed = true;
        $isDegraded = false;
        $hasVectorWarn = false;
        $blockingFailure = null;
        $tier = $sku->tier instanceof TierType ? $sku->tier->value : strtolower((string) $sku->tier);

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — Kill tier: G6 required; G6.1 blocks all edits.
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G6.1 — Kill lock after G6 tier tag confirmed.
        // FIX: G6-03 — Record G6 PASS (tier tag present) before G6.1 kill block.
        if ($tier === TierType::KILL->value) {
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — G1 REQUIRED for all tiers.
            // SOURCE: Readme_First_CIE_v232_Developer_Build_Guide.pdf Phase 1 Step 2 — Kill checks include G1 + G6; content gates are suspended.
            $g1Result = (new G1_BasicInfoGate())->validate($sku);
            $this->recordGateResult(
                $sku,
                $g1Result,
                $results,
                $overallPassed,
                $blockingFailure,
                $isDegraded,
                $hasVectorWarn
            );

            $this->recordGateResult(
                $sku,
                new GateResult(
                    gate: GateType::G6_TIER_TAG,
                    passed: true,
                    reason: 'Kill tier tag present',
                    blocking: false,
                    metadata: []
                ),
                $results,
                $overallPassed,
                $blockingFailure,
                $isDegraded,
                $hasVectorWarn
            );
            $killBlock = new GateResult(
                gate: GateType::G6_1_TIER_LOCK,
                passed: false,
                reason: 'Kill-tier SKU: all content editing is disabled.',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G6_1_KILL_EDIT_BLOCKED',
                    'detail' => 'Kill-tier SKU: all content editing is disabled.',
                    'user_message' => 'This product has negative margin and is flagged for delisting. All editing is disabled. Contact your Portfolio Holder if you need a tier review.',
                ]
            );
            $this->recordGateResult(
                $sku,
                $killBlock,
                $results,
                $overallPassed,
                $blockingFailure,
                $isDegraded,
                $hasVectorWarn
            );

            foreach ([GateType::G2_INTENT, GateType::G3_SECONDARY_INTENT, GateType::G4_ANSWER_BLOCK, GateType::G5_BEST_NOT_FOR, GateType::G7_EXPERT] as $naGate) {
                $this->recordGateResult(
                    $sku,
                    GateResult::notApplicable($naGate, 'Kill tier: gate suspended'),
                    $results,
                    $overallPassed,
                    $blockingFailure,
                    $isDegraded,
                    $hasVectorWarn
                );
            }
        } elseif ($tier === TierType::HARVEST->value) {
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §8.3 — Harvest executes only G1, G2, G6.
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G6.1 — HARVEST: Specification + 1 other; G6.1 results MUST be recorded.
            // FIX: G6.1-03 — Do not skip G6_1_TIER_LOCK on Harvest (orchestrator must call recordGateResult for all G6.1 outcomes).
            foreach ([new G1_BasicInfoGate(), new G2_IntentGate(), new G6_TierTagGate()] as $gate) {
                $rawResult = $gate->validate($sku);
                $gateResults = is_array($rawResult) ? $rawResult : [$rawResult];
                foreach ($gateResults as $result) {
                    $this->recordGateResult(
                        $sku,
                        $result,
                        $results,
                        $overallPassed,
                        $blockingFailure,
                        $isDegraded,
                        $hasVectorWarn
                    );
                }
            }
        } else {
            foreach ($this->gates as $gateClass) {
                $gate = new $gateClass();
                $rawResult = $gate->validate($sku);
                $gateResults = is_array($rawResult) ? $rawResult : [$rawResult];

                foreach ($gateResults as $result) {
                    $this->recordGateResult(
                        $sku,
                        $result,
                        $results,
                        $overallPassed,
                        $blockingFailure,
                        $isDegraded,
                        $hasVectorWarn
                    );
                }
            }
        }

        if ($overallPassed && ! $isDegraded && ! $hasVectorWarn) {
            $status = ValidationStatus::VALID;
            $canPublish = true;
            $nextAction = 'SKU is ready for publication';
        } elseif ($isDegraded) {
            $status = ValidationStatus::DEGRADED;
            $canPublish = false;
            $nextAction = 'Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes (typically within 30 minutes).';
        } elseif ($hasVectorWarn && ! $blockingFailure) {
            // SOURCE: CLAUDE.md §11, CIE_v232_Hardening_Addendum.pdf §1.1 — vector warn = content-quality warning; not degraded_mode (service healthy)
            $status = ValidationStatus::VALID;
            $canPublish = false;
            $nextAction = 'Your content may not align with the intent. Consider revising.';
        } else {
            $status = ValidationStatus::INVALID;
            $canPublish = false;
            $nextAction = $blockingFailure ? $blockingFailure->reason : 'Fix validation errors before publication';
        }

        // SOURCE: ENF§2.1 G6.1, BUILD§Step2 — Kill SKUs are never publishable
        if ($tier === TierType::KILL->value) {
            $canPublish = false;
        }

        // SOURCE: CIE_v232_Hardening_Addendum.pdf §1 (Patch 1 — Fail-Soft Vector Validation)
        $updateData = [
            'can_publish' => $canPublish,
            'last_validated_at' => now(),
            'ai_validation_pending' => $isDegraded,
        ];

        if (! $preserveStatus) {
            $updateData['validation_status'] = $status;
        }

        if ($tier !== TierType::KILL->value) {
            $sku->update($updateData);
        }

        $sanitisedReason = 'Your content may not align with the intent. Consider revising.';

        $gatePayload = array_map(fn ($r) => $r->toArray(), $results);
        foreach ($gatePayload as &$gate) {
            $gateKey = strtolower((string) ($gate['gate_name'] ?? ''));
            $isVectorGate = str_contains($gateKey, 'vector') || str_contains($gateKey, 'semantic');

            if ($isVectorGate) {
                if (isset($gate['metadata']) && is_array($gate['metadata'])) {
                    foreach ($gate['metadata'] as $k => $v) {
                        if (is_int($v) || is_float($v)) {
                            unset($gate['metadata'][$k]);
                        }
                    }
                }

                foreach (['user_message', 'reason', 'detail'] as $field) {
                    if (isset($gate[$field]) && is_string($gate[$field]) && preg_match('/\d+\.\d+/', $gate[$field])) {
                        $gate[$field] = $sanitisedReason;
                    }
                }
            }
        }
        unset($gate);

        $saveAllowed = true;

        // SOURCE: openapi.yaml ValidationResponse, ENF§7.2 — gates keyed by gate id
        $gateIdMap = [
            'G1_BASIC_INFO'        => 'G1_cluster_id',
            'G2_INTENT'            => 'G2_primary_intent',
            'G3_SECONDARY_INTENT'  => 'G3_secondary_intents',
            'G4_ANSWER_BLOCK'      => 'G4_answer_block',
            'G4_VECTOR'            => 'vector_check',
            'G5_BEST_NOT_FOR'      => 'G5_best_not_for',
            'G6_COMMERCIAL_POLICY' => 'G6_tier_tag',
            'G6_TIER_TAG'          => 'G6_tier_tag',
            'G6_1_TIER_LOCK'       => 'G6_1_tier_lock',
            'G7_EXPERT'            => 'G7_expert_authority',
        ];
        $gatesKeyed = [];
        $vectorCheck = ['status' => 'pass', 'user_message' => null];
        foreach ($gatePayload as $g) {
            $enumVal = $g['gate'] ?? '';
            $key = $gateIdMap[$enumVal] ?? $enumVal;
            $reason = $g['reason'] ?? '';
            $gateStatus = ($reason === 'not_applicable') ? 'not_applicable' : (($g['passed'] ?? false) ? 'pass' : 'fail');
            if (isset($g['metadata']['degraded']) && $g['metadata']['degraded'] && ! $g['passed']) {
                $gateStatus = 'pending';
            }
            if (($g['passed'] ?? false) && (($g['metadata']['status'] ?? '') === 'warn' || ! empty($g['metadata']['warn_only']))) {
                $gateStatus = 'warn';
            }
            $g['status'] = $gateStatus;
            if ($key === 'vector_check') {
                $vcStatus = 'pass';
                if ($reason === 'not_applicable') {
                    $vcStatus = 'not_applicable';
                } elseif (! ($g['passed'] ?? false)) {
                    $vcStatus = ($g['metadata']['degraded'] ?? false) ? 'pending' : 'fail';
                } elseif (! empty($g['metadata']['warn_only']) || (($g['metadata']['status'] ?? '') === 'warn')) {
                    $vcStatus = 'warn';
                }
                $vectorCheck = ['status' => $vcStatus, 'user_message' => $g['user_message'] ?? null];
            }
            $gatesKeyed[$key] = $g;
        }

        return [
            'sku_id'          => $sku->id,
            'status'          => strtolower($status->value),
            'overall_status'  => $status->value,
            'can_publish'     => $canPublish,
            'degraded_mode'   => $isDegraded,
            'save_allowed'    => $saveAllowed,
            'publish_allowed' => $canPublish,
            'gates'           => $gatesKeyed,
            'vector_check'    => $vectorCheck,
            'results'         => $gatePayload,
            'next_action'     => $nextAction,
        ];
    }
}
