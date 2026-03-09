<?php
// SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 7 (error codes); CIE_v231_Developer_Build_Pack.pdf (sku_gate_status schema)
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Models\AuditLog;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;

class G4_VectorGate implements GateInterface
{
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
 
        $minLen = (int) BusinessRules::get('content.description_vector_min_length', 100);
        if (!$sku->long_description || strlen(trim($sku->long_description)) < $minLen) {
             return new GateResult(
             gate: GateType::G4_VECTOR,
             passed: false,
             reason: "Long description missing or too short (minimum {$minLen} characters required for vector validation).",
             blocking: true
             );
        }
 
        try {
            $threshold = (float) BusinessRules::get('gates.vector_similarity_min', 0.72);
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

             return new GateResult(
                 gate: GateType::G4_VECTOR,
                 passed: false,
                 reason: 'Your content may not align with the intent. Consider revising.',
                 blocking: false,
                 metadata: [
                     'error_code' => 'CIE_VEC_SIMILARITY_LOW',
                     'user_message' => 'Your content may not align with the intent. Consider revising.',
                     'can_save' => true,
                     'can_publish' => false,
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
