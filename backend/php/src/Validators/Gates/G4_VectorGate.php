<?php
// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1 (Fail-Soft Vector Validation); CLAUDE.md Section 11, Section 18 DECISION-005

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
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
 
 public function validate(Sku $sku): GateResult
 {
 if (!$sku->primary_cluster_id) {
 return new GateResult(
 gate: GateType::G4_VECTOR,
 passed: false,
 reason: 'No cluster assigned. SKU must belong to at least one cluster.',
 blocking: true
 );
 }
 
        $minLen = 100; // §5.3: content.description_vector_min_length not in 52 rules; hard-coded
        if (!$sku->long_description || strlen(trim($sku->long_description)) < $minLen) {
             return new GateResult(
             gate: GateType::G4_VECTOR,
             passed: false,
             reason: "Long description missing or too short (minimum {$minLen} characters required for vector validation).",
             blocking: true
             );
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
                AuditLog::create([
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
                passed: false,
                reason: 'Description validation temporarily unavailable. Your changes are saved but publishing is paused until validation completes (typically within 30 minutes).',
                blocking: false,
                metadata: ['degraded' => true, 'status' => 'pending']
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
