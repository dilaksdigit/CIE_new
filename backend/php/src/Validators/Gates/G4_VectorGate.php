<?php
namespace App\Validators\Gates;

use App\Models\Sku;
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
 gate: GateType::G5_VECTOR,
 passed: false,
 reason: 'No cluster assigned. SKU must belong to at least one cluster.',
 blocking: true
 );
 }
 
        $minLen = (int) BusinessRules::get('content.description_vector_min_length', 100);
        if (!$sku->long_description || strlen(trim($sku->long_description)) < $minLen) {
            return new GateResult(
                gate: GateType::G5_VECTOR,
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
                    gate: GateType::G5_VECTOR,
                    passed: true,
                    reason: sprintf('Semantic match confirmed (similarity: %.2f)', $response['similarity']),
                    blocking: false,
                    metadata: ['similarity' => $response['similarity']]
                );
            }

            return new GateResult(
                gate: GateType::G5_VECTOR,
                passed: false,
                reason: $response['reason'],
                blocking: true,
                metadata: ['similarity' => $response['similarity']]
            );
        } catch (\Exception $e) {
            // Fail-soft: queue for retry and mark as degraded
            try {
                DB::table('validation_retry_queue')->insert([
                    'sku_id'        => $sku->sku_code ?? (string) $sku->id,
                    'gate_code'     => 'VECTOR',
                    'retry_count'   => 0,
                    'next_retry_at' => now()->addMinutes(5),
                    'created_at'    => now(),
                ]);
            } catch (\Throwable $queueError) {
                // Swallow queue errors to avoid blocking saves
            }

            return new GateResult(
                gate: GateType::G5_VECTOR,
                passed: false,
                reason: 'Vector validation temporarily unavailable. Save allowed with DEGRADED status.',
                blocking: false,
                metadata: ['degraded' => true, 'error' => $e->getMessage()]
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
