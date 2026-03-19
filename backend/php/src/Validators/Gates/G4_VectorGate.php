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

    private const PYTHON_ENDPOINT = 'http://python-worker:5000/validate-vector';

    private const MIN_DESCRIPTION_WORDS = 50;

    public function validate(Sku $sku): GateResult|array
    {
        if ($sku->tier === TierType::KILL || $sku->tier === TierType::HARVEST) {
            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'N/A',
                blocking: false,
                metadata: ['status' => 'N/A']
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
        if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT], true)) {
            $actualWords = str_word_count($sku->long_description ?? '');
            if ($actualWords < self::MIN_DESCRIPTION_WORDS) {
                $needed = self::MIN_DESCRIPTION_WORDS - $actualWords;
                $failures[] = new GateResult(
                    gate: GateType::G4_VECTOR,
                    passed: false,
                    reason: "Description has {$actualWords} words. Minimum is " . self::MIN_DESCRIPTION_WORDS . ".",
                    blocking: true,
                    metadata: [
                        'error_code'   => 'CIE_VEC_DESCRIPTION_TOO_SHORT',
                        'user_message' => "Your description is {$actualWords} words. Add at least {$needed} more words. "
                            . "Write to solve the problem this product addresses, not to list physical attributes.",
                    ]
                );
            }
        }

        $minLen = 100;
        if (!$sku->long_description || strlen(trim($sku->long_description)) < $minLen) {
            $failures[] = new GateResult(
                gate: GateType::G4_VECTOR,
                passed: false,
                reason: "Long description missing or too short (minimum {$minLen} characters required for vector validation).",
                blocking: true
            );
            return $failures;
        }

        if (!empty($failures)) {
            return $failures;
        }

        try {
            $threshold = (float) BusinessRules::get('gates.vector_similarity_min');
            $response = $this->callPythonValidator($sku->long_description, $sku->primary_cluster_id, $threshold);

            // Persist similarity to canonical sku_content (if present)
            if (isset($response['similarity']) && method_exists($sku, 'content') && $sku->content) {
                $sku->content->update(['vector_similarity' => $response['similarity']]);
            }

            if ($response['valid']) {
                 return new GateResult(
                 gate: GateType::G4_VECTOR,
                 passed: true,
                 reason: 'Semantic match confirmed',
                 blocking: false,
                 metadata: []
                 );
            }

            // SOURCE: CLAUDE.md DECISION-005; Hardening_Addendum Patch 1 — WARNING only, save succeeds. No score in writer-facing output.
            $similarity = isset($response['similarity']) ? (float) $response['similarity'] : 0.0;
            $auditLog = app(AuditLogService::class);
            $auditLog->logVectorWarn((int) $sku->id, $similarity, auth()->id());

            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: self::VECTOR_WARN_MESSAGE,
                blocking: false,
                metadata: [
                    'user_message' => self::VECTOR_WARN_MESSAGE,
                    'gate'         => 'vector_similarity',
                    'warn_only'    => true,
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

            return new GateResult(
                gate: GateType::G4_VECTOR,
                passed: true,
                reason: 'Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes (typically within 30 minutes).',
                blocking: false,
                metadata: ['degraded' => true, 'status' => 'pending', 'warn_only' => true]
            );
        }
 }
 
    private function callPythonValidator(string $description, string $clusterId, float $threshold): array
    {
        $client = new \GuzzleHttp\Client(['timeout' => 3.0]);
        $response = $client->post(self::PYTHON_ENDPOINT, [
            'json' => [
                'description' => $description,
                'cluster_id' => $clusterId,
                'threshold' => $threshold,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }
}
