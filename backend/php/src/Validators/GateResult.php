<?php
namespace App\Validators;
use App\Enums\GateType;
class GateResult
{
 public function __construct(
 public GateType $gate,
 public bool $passed,
 public string $reason,
 public bool $blocking = true,
 public array $metadata = []
 ) {}
 
 public function toArray(): array
 {
        $errorCode = $this->metadata['error_code'] ?? ('CIE_' . strtoupper(str_replace(['.', ' ', '-'], '_', $this->gate->value)));
        $userMessage = $this->metadata['user_message'] ?? $this->reason;

        $metadata = $this->metadata;

        // Defence-in-depth: never expose raw similarity or threshold values on the vector gate.
        // We strip numeric fields and well-known keys before serialising the response.
        $gateKey = $this->gate->value;
        if (strtolower($gateKey) === 'g5_vector' || strtolower($gateKey) === 'vector_similarity') {
            foreach ($metadata as $key => $value) {
                $lowerKey = strtolower((string) $key);
                if (in_array($lowerKey, ['similarity', 'score', 'cosine', 'threshold'], true)) {
                    unset($metadata[$key]);
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    unset($metadata[$key]);
                }
            }
        } else {
            // Threshold values are always internal-only; strip them for all gates.
            foreach ($metadata as $key => $value) {
                if (strtolower((string) $key) === 'threshold') {
                    unset($metadata[$key]);
                }
            }
        }

        return [
            'gate'        => $gateKey,
            'gate_name'   => $this->gate->displayName(),
            'passed'      => $this->passed,
            'reason'      => $this->reason,
            'blocking'    => $this->blocking,
            'error_code'  => $this->passed ? null : $errorCode,
            'detail'      => $this->reason,
            'user_message'=> $this->passed ? null : $userMessage,
            'metadata'    => $metadata,
        ];
 }
}
