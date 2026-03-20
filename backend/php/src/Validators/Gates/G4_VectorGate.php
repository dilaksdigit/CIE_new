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
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class G4_VectorGate implements GateInterface
{
    /** Writer-facing message when vector is below threshold — no score numbers (CLAUDE.md R4). */
    private const VECTOR_WARN_MESSAGE = 'Your description may not fully match the expected topic for this product. Consider expanding your content to better cover the primary intent keywords.';

    // SOURCE: openapi.yaml — Python exposes POST /api/v1/sku/similarity (returns status + message). CLAUDE.md R1 — no new endpoints.
    private const PYTHON_ENDPOINT = 'http://python-worker:5000/api/v1/sku/similarity';

    // SOURCE: MASTER§5 — load from BusinessRules, not hard-coded
    private static function minDescriptionWords(): int
    {
        return (int) (BusinessRules::get('gates.description_word_count_min', 50) ?? 50);
    }

    private static function minDescriptionChars(): int
    {
        return (int) (BusinessRules::get('gates.description_min_chars', 100) ?? 100);
    }

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
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: false,
                reason: 'No cluster assigned. SKU must belong to at least one cluster.',
                blocking: true
            );
        }

        $failures = [];

        // Description word-count pre-check (Hero/Support only; Harvest suspended)
        $minDescriptionWords = self::minDescriptionWords();
        if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT], true)) {
            $actualWords = str_word_count($sku->long_description ?? '');
            if ($actualWords < $minDescriptionWords) {
                $needed = $minDescriptionWords - $actualWords;
                $failures[] = new GateResult(
                    gate: GateType::G4_VECTOR,
                    passed: false,
                    reason: "Description has {$actualWords} words. Minimum is {$minDescriptionWords}.",
                    blocking: true,
                    metadata: [
                        // SOURCE: ENF§Page18 — CIE_VEC_SIMILARITY_LOW is the only spec-defined vector error code
                        'error_code'   => 'CIE_VEC_SIMILARITY_LOW',
                        'user_message' => "Your description is {$actualWords} words. Add at least {$needed} more words. "
                            . "Write to solve the problem this product addresses, not to list physical attributes.",
                    ]
                );
            }
        }

        $minLen = self::minDescriptionChars();
        if (!$sku->long_description || strlen(trim($sku->long_description)) < $minLen) {
            $failures[] = new GateResult(
                gate: GateType::G4_VECTOR,
                passed: false,
                reason: "Long description missing or too short (minimum {$minLen} characters required for vector validation).",
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_VEC_SIMILARITY_LOW',
                    'user_message' => "Your description is too short for semantic validation. Add at least {$minLen} characters.",
                ]
            );
            return $failures;
        }

        if (!empty($failures)) {
            return $failures;
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

            if ($status === 'pending') {
                return new GateResult(
                    gate: GateType::G4_VECTOR,
                    passed: true,
                    reason: 'pending',
                    blocking: false,
                    metadata: [
                        'user_message' => $message ?? 'Description validation temporarily unavailable. Your changes are saved but publishing is paused.',
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

            // SOURCE: Hardening Addendum §1.1 — fail-soft: pending, not block. Save allowed, publish blocked.
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'pending',
                blocking: false,
                metadata: [
                    'user_message' => 'Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes (typically within 30 minutes).',
                    'status'       => 'pending',
                    'degraded'     => true,
                ]
            );
        }
    }

    // SOURCE: openapi.yaml POST /api/v1/sku/similarity — request: description, cluster_id; response: status, message
    private function callPythonSimilarity(string $description, string $clusterId): array
    {
        $client = new \GuzzleHttp\Client(['timeout' => 3.0]);
        $response = $client->post(self::PYTHON_ENDPOINT, [
            'json' => [
                'description' => $description,
                'cluster_id'  => $clusterId,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true) ?? ['status' => 'fail', 'message' => 'Invalid response'];
    }
}
