<?php
// SOURCE: CLAUDE.md Section 6 (G6); CIE_v231_Developer_Build_Pack G6 spec; CLAUDE.md Section 7 (tier system)

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class G6_DescriptionQualityGate implements GateInterface
{
    /** System message when tier is not assigned — non-writer-facing, returned as 400 (CLAUDE.md Section 6 G6). */
    private const TIER_NOT_ASSIGNED_MESSAGE = 'SKU tier is not assigned. Tier must be set before content can be validated. Contact Admin to trigger ERP sync.';

    public function validate(Sku $sku): GateResult|array
    {
        // GATE-07: Check tier is assigned before word-count/semantic validation (CLAUDE.md Section 6, 7)
        $tierRaw = $sku->tier;
        $tierStr = $tierRaw instanceof TierType ? strtolower($tierRaw->value ?? '') : strtolower(trim((string) ($tierRaw ?? '')));
        $allowedTiers = ['hero', 'support', 'harvest', 'kill'];
        if ($tierRaw === null || (is_string($tierRaw) && trim($tierRaw) === '') || !in_array($tierStr, $allowedTiers, true)) {
            return new GateResult(
                gate: GateType::G6_DESCRIPTION_QUALITY,
                passed: false,
                reason: self::TIER_NOT_ASSIGNED_MESSAGE,
                blocking: true,
                metadata: [
                    'system_error' => true,
                    'gate' => 'description_quality',
                    'status' => 'error',
                    'message' => self::TIER_NOT_ASSIGNED_MESSAGE,
                ]
            );
        }

        $tier = strtoupper($tierStr);

        if (in_array($tier, ['HARVEST', 'KILL'], true)) {
            return new GateResult(
                gate: GateType::G6_DESCRIPTION_QUALITY,
                passed: true,
                reason: 'Description quality check is not required for this product tier.',
                blocking: false
            );
        }

        $failures = [];

        // Check 1 — Word Count (blocking) (§5.3: gates.description_word_count_min not in 52 rules; hard-coded 50)
        $minWords = 50;
        $actual = str_word_count($sku->description ?? '');

        if ($actual < $minWords) {
            $needed = $minWords - $actual;
            $failures[] = new GateResult(
                gate: GateType::G6_DESCRIPTION_QUALITY,
                passed: false,
                reason: "Description has {$actual} words. Minimum is {$minWords}.",
                blocking: true,
                metadata: [
                    'error_code'   => 'CIE_G6_DESCRIPTION_TOO_SHORT',
                    'user_message' => "Your description is {$actual} words. Add at least {$needed} more words. "
                        . "Write to solve the problem this product addresses, not to list physical attributes.",
                ]
            );
        }

        // Check 2 — Semantic / Vector Similarity (fail-soft: below threshold = WARNING only, save allowed)
        // SOURCE: CLAUDE.md DECISION-005 — fail-soft, warn only, save allowed
        // SOURCE: CLAUDE.md Hard Rule R4 — cosine score must not be shown to writer
        // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §1.2; CIE_Master_Developer_Build_Spec.docx §7 VEC row
        $clusterId = $sku->primary_cluster_id ?? '';
        $vectorWarnMessage = 'Your description may not fully match the expected topic for this product. Consider expanding your content to better cover the primary intent keywords.';

        try {
            $response = Http::timeout(10)->post(
                config('services.python_api.url', 'http://localhost:8000') . '/api/v1/sku/similarity',
                [
                    'description' => $sku->description ?? '',
                    'cluster_id'  => $clusterId,
                ]
            );

            $result = $response->json();
            $status = $result['status'] ?? '';

            if ($status === 'pending') {
                Log::warning('G6 vector similarity unavailable (pending/degraded)', [
                    'sku_id'     => $sku->id,
                    'cluster_id' => $clusterId,
                ]);
                try {
                    \App\Models\AuditLog::create([
                        'entity_type' => 'gate_status',
                        'entity_id'   => $sku->id,
                        'action'      => 'G6_VECTOR_FAIL_SOFT',
                        'field_name'  => GateType::G6_DESCRIPTION_QUALITY->value,
                        'old_value'   => null,
                        'new_value'   => 'pending',
                        'actor_id'    => 'SYSTEM',
                        'actor_role'  => 'system',
                        'timestamp'   => now(),
                        'created_at'  => now(),
                    ]);
                } catch (\Throwable $auditEx) {
                    // Fail-soft: do not break validation if audit_log write fails
                }
            } elseif ($status === 'fail') {
                // SOURCE: CLAUDE.md DECISION-005 — WARNING only, save allowed; no blocking failure. No cosine value in writer output.
                try {
                    \App\Models\AuditLog::create([
                        'entity_type' => 'gate_status',
                        'entity_id'   => $sku->id,
                        'action'      => 'G6_VECTOR_BELOW_THRESHOLD',
                        'field_name'  => GateType::G6_DESCRIPTION_QUALITY->value,
                        'old_value'   => null,
                        'new_value'   => 'warn',
                        'actor_id'    => auth()->id() ?? 'SYSTEM',
                        'actor_role'  => (function_exists('auth') && auth()->check() && auth()->user() && auth()->user()->role) ? auth()->user()->role->name : 'system',
                        'timestamp'   => now(),
                        'created_at'  => now(),
                    ]);
                } catch (\Throwable $auditEx) {
                    // Fail-soft: do not break validation if audit_log write fails
                }
                // Return as warning (passed=true, blocking=false, warn_only) — save allowed, publish blocked by GateValidator
                $failures[] = new GateResult(
                    gate: GateType::G6_DESCRIPTION_QUALITY,
                    passed: true,
                    reason: $vectorWarnMessage,
                    blocking: false,
                    metadata: [
                        'user_message' => $vectorWarnMessage,
                        'warn_only'    => true,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Fail-soft: vector service unavailable — warn but do not block save
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §7 VEC row
            Log::warning('G6 vector similarity service unavailable (fail-soft)', [
                'sku_id'     => $sku->id,
                'cluster_id' => $clusterId,
                'error'      => $e->getMessage(),
            ]);

            try {
                \App\Models\AuditLog::create([
                    'entity_type' => 'gate_status',
                    'entity_id'   => $sku->id,
                    'action'      => 'G6_VECTOR_FAIL_SOFT',
                    'field_name'  => GateType::G6_DESCRIPTION_QUALITY->value,
                    'old_value'   => null,
                    'new_value'   => 'pending',
                    'actor_id'    => 'SYSTEM',
                    'actor_role'  => 'system',
                    'timestamp'   => now(),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $auditEx) {
                // Fail-soft: do not break validation if audit_log write fails
            }
        }

        if (!empty($failures)) {
            return $failures;
        }

        return new GateResult(
            gate: GateType::G6_DESCRIPTION_QUALITY,
            passed: true,
            reason: 'Description meets minimum word count and semantic similarity requirements.',
            blocking: false
        );
    }
}
