<?php
// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1 (Fail-Soft Vector Validation); CLAUDE.md Section 11, Section 18 DECISION-005
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7 — VEC gate + description word-count pre-check (merged from former G6_DescriptionQualityGate)

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class G4_VectorGate implements GateInterface
{
    /** Writer-facing message when vector is below threshold — no score numbers (CLAUDE.md R4). */
    private const VECTOR_WARN_MESSAGE = 'Your description may not fully match the expected topic for this product. Consider expanding your content to better cover the primary intent keywords.';

    // SOURCE: BUILD§Step2 — PHP→Python service calls; base URL from env (default port 8000 for uvicorn)
    private function pythonSimilarityEndpoint(): string
    {
        // SOURCE: BUILD§Step2 — PHP→Python worker URL from config then env (default port 8000 / uvicorn)
        $base = rtrim((string) config('services.python_worker.url', env('PYTHON_WORKER_URL', 'http://python-worker:8000')), '/');

        return $base . '/api/v1/sku/similarity';
    }

    // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — BusinessRules only, no numeric fallbacks
    private static function minDescriptionWords(): int
    {
        return (int) BusinessRules::get('gates.description_word_count_min');
    }

    private static function minDescriptionChars(): int
    {
        return (int) BusinessRules::get('gates.description_min_chars');
    }

    // SOURCE: openapi.yaml SimilarityResponse; ENF§2.2 — vector gate + pre-checks
    public function validate(Sku $sku): GateResult|array
    {
        // SOURCE: ENF§2.2 — G4 SUSPENDED for Harvest/Kill → not_applicable
        if ($sku->tier === TierType::KILL || $sku->tier === TierType::HARVEST) {
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'not_applicable',
                blocking: false,
                metadata: ['status' => 'not_applicable', 'user_message' => null]
            );
        }

        if (!$sku->primary_cluster_id) {
            // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §7.2 — 4-field failure object
            // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §7.3 — CIE_G1_INVALID_CLUSTER (cluster not in approved master list / missing assignment)
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: false,
                reason: 'No cluster_id assigned to SKU.',
                blocking: true,
                metadata: [
                    'status'       => 'fail',
                    'error_code'   => 'CIE_G1_INVALID_CLUSTER',
                    'detail'       => 'No cluster_id assigned to SKU.',
                    'user_message' => 'This product needs a cluster assignment before validation can run.',
                ]
            );
        }

        // SOURCE: CLAUDE.md §11 + Decision-005 — Vector validation is FAIL-SOFT; pre-checks warn but do not block save
        // SOURCE: CIE_v232_Hardening_Addendum Patch 1 — VECTOR_PENDING / degraded-save alignment
        // SOURCE: CIE_Master_Developer_Build_Spec §7 — VEC fail-soft when checks are advisory
        $minDescriptionWords = self::minDescriptionWords();
        if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT], true)) {
            $actualWords = str_word_count($sku->long_description ?? '');
            if ($actualWords < $minDescriptionWords) {
                $detail = "Description may be too short for reliable vector analysis ({$actualWords} words, recommended minimum {$minDescriptionWords}).";
                try {
                    AuditLog::create([
                        'entity_type' => 'sku',
                        'entity_id'   => $sku->id,
                        'action'      => 'VECTOR_WARN',
                        'field_name'  => 'G4_VECTOR',
                        'old_value'   => null,
                        'new_value'   => 'pre_check_word_count',
                        'actor_id'    => 'SYSTEM',
                        'actor_role'  => 'system',
                        'timestamp'   => now(),
                        'created_at'  => now(),
                    ]);
                } catch (\Throwable $e) {
                }

                return new GateResult(
                    gate: GateType::G4_VECTOR,
                    passed: true,
                    reason: 'warn_only',
                    blocking: false,
                    metadata: [
                        'status'       => 'warn',
                        'warn_only'    => true,
                        'vector_status'=> 'degraded',
                        'error_code'   => null,
                        'detail'       => $detail,
                        'user_message' => 'Your content may not align with the intent. Consider revising.',
                    ]
                );
            }
        }

        $minLen = self::minDescriptionChars();
        $trimmed = trim((string) ($sku->long_description ?? ''));
        if ($trimmed === '' || strlen($trimmed) < $minLen) {
            $charCount = $trimmed === '' ? 0 : strlen($trimmed);
            $detail = $trimmed === ''
                ? 'Description is empty; vector analysis may be unreliable until content is added.'
                : "Description may be too short for reliable vector analysis ({$charCount} characters, recommended minimum {$minLen}).";
            try {
                AuditLog::create([
                    'entity_type' => 'sku',
                    'entity_id'   => $sku->id,
                    'action'      => 'VECTOR_WARN',
                    'field_name'  => 'G4_VECTOR',
                    'old_value'   => null,
                    'new_value'   => 'pre_check_char_count',
                    'actor_id'    => 'SYSTEM',
                    'actor_role'  => 'system',
                    'timestamp'   => now(),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $e) {
            }

            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'warn_only',
                blocking: false,
                metadata: [
                    'status'        => 'warn',
                    'warn_only'     => true,
                    'vector_status' => 'degraded',
                    'error_code'    => null,
                    'detail'        => $detail,
                    'user_message'  => 'Your content may not align with the intent. Consider revising.',
                ]
            );
        }

        try {
            // SOURCE: openapi.yaml SimilarityResponse — POST body: description, cluster_id; response: { status, message }
            $response = $this->callPythonSimilarity($sku->long_description, $sku->primary_cluster_id);

            $status = $response['status'] ?? 'fail';
            $message = $response['message'] ?? null;

            if ($status === 'pass') {
                return new GateResult(
                    gate: GateType::G4_VECTOR,
                    passed: true,
                    reason: 'Vector similarity pass',
                    blocking: false,
                    metadata: ['user_message' => null]
                );
            }

            // SOURCE: openapi.yaml ValidationResponse.vector_check.status — wire value is `pending` (not VECTOR_PENDING). FIX: VEC-05
            if ($status === 'pending') {
                return new GateResult(
                    gate: GateType::G4_VECTOR,
                    passed: true,
                    reason: 'pending',
                    blocking: false,
                    metadata: [
                        'user_message' => $message ?? 'Content quality check temporarily unavailable. Your save will proceed.',
                        'status'       => 'pending',
                        'degraded'     => true,
                    ]
                );
            }

            // SOURCE: CLAUDE.md §11, DECISION-005 — below threshold = WARNING, not block. Fail-Soft Vector Validation.
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'warn_only',
                blocking: false,
                metadata: [
                    'error_code' => 'CIE_VEC_SIMILARITY_LOW',
                    'user_message' => $message ?? self::VECTOR_WARN_MESSAGE,
                    'detail' => 'Description semantic similarity below threshold',
                    'warn_only' => true,
                ]
            );
        } catch (\Exception $e) {
            try {
                DB::table('vector_retry_queue')->insert([
                    'sku_id'        => $sku->sku_code ?? (string) $sku->id,
                    'description'   => $sku->long_description ?? '',
                    'cluster_id'    => $sku->primary_cluster_id ?? '',
                    'retry_count'   => 0,
                    'max_retries'   => 5,
                    'next_retry_at' => now()->addMinutes(5),
                    'status'        => 'queued',
                    'created_at'    => now(),
                ]);
            } catch (\Throwable $queueError) {
                \Illuminate\Support\Facades\Log::warning('vector_retry_queue insert failed', [
                    'error' => $queueError->getMessage(),
                ]);
            }

            try {
                \App\Models\AuditLog::create([
                    'entity_type' => 'sku',
                    'entity_id'   => $sku->id,
                    'action'      => 'embedding_api_error',
                    'field_name'  => 'G4_VECTOR',
                    'old_value'   => null,
                    'new_value'   => 'pending',
                    'actor_id'    => 'SYSTEM',
                    'actor_role'  => 'system',
                    'timestamp'   => now(),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $auditErr) {
                // Fail-soft: do not break validation if audit_log write fails
            }

            // SOURCE: openapi.yaml vector_check.status enum `pending`; Hardening Addendum §1.1 — fail-soft, save allowed.
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'pending',
                blocking: false,
                metadata: [
                    'user_message' => 'Content quality check temporarily unavailable. Your save will proceed.',
                    'status'       => 'pending',
                    'degraded'     => true,
                ]
            );
        }
    }

    // SOURCE: openapi.yaml POST /api/v1/sku/similarity — request: description, cluster_id; response: status, message
    private function callPythonSimilarity(string $description, string $clusterId): array
    {
        // Allow moderate upstream latency to reduce false degraded/pending states.
        $client = new \GuzzleHttp\Client(['timeout' => 12.0]);
        $response = $client->post($this->pythonSimilarityEndpoint(), [
            'json' => [
                'description' => $description,
                'cluster_id'  => $clusterId,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? ['status' => 'fail', 'message' => 'Invalid response'];
    }
}
