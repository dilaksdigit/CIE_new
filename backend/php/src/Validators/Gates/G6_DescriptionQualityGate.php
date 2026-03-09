<?php
// SOURCE: CLAUDE.md §6 | CIE_Master_Developer_Build_Spec.docx §7 | CIE_v2.3_Enforcement_Edition.pdf §1.2

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
    public function validate(Sku $sku): GateResult|array
    {
        $tier = strtoupper((string) ($sku->tier->value ?? $sku->tier ?? ''));

        if (in_array($tier, ['HARVEST', 'KILL'], true)) {
            return new GateResult(
                gate: GateType::G6_DESCRIPTION_QUALITY,
                passed: true,
                reason: 'G6 Description Quality suspended for ' . $tier . ' tier.',
                blocking: false
            );
        }

        $failures = [];

        // Check 1 — Word Count (blocking)
        // SOURCE: CLAUDE.md §6 | CIE_Master_Developer_Build_Spec.docx §5.3
        $minWords = (int) BusinessRules::get('gates.description_word_count_min');
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

        // Check 2 — Semantic / Vector Similarity (blocking, fail-soft on service unavailability)
        // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §1.2
        // Threshold enforcement is performed server-side in the Python similarity service
        // via BusinessRules.get('gates.vector_similarity_min') per
        // CIE_Master_Developer_Build_Spec.docx §5 and §7 (VEC row).
        $clusterId = $sku->primary_cluster_id ?? '';

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
                // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §1.2 — use this rejection message exactly
                $failures[] = new GateResult(
                    gate: GateType::G6_DESCRIPTION_QUALITY,
                    passed: false,
                    reason: 'Description semantic similarity is below the required threshold.',
                    blocking: true,
                    metadata: [
                        'error_code'   => 'CIE_G6_SEMANTIC_MISMATCH',
                        'user_message' => "Description does not align with Cluster Intent [{$clusterId}]. "
                            . "Rewrite to address the problem this product solves, not its physical attributes.",
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
