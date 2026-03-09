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
 
    private const SANITISED_REASON = 'Your content may not align with the intent. Consider revising.';

    public function toArray(): array
    {
        $userMessage = $this->metadata['user_message'] ?? $this->reason;

        $metadata = $this->metadata;

        // Strip internal-only keys from the writer-facing metadata.
        unset($metadata['error_code'], $metadata['user_message']);

        $gateKey = strtolower($this->gate->value);
        $isVectorGate = in_array($gateKey, ['g4_vector', 'g5_vector', 'vector_similarity'], true);

        if ($isVectorGate) {
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
            foreach ($metadata as $key => $value) {
                if (strtolower((string) $key) === 'threshold') {
                    unset($metadata[$key]);
                }
            }
        }

        if (is_string($userMessage) && preg_match('/\d+\.\d+/', $userMessage)) {
            $userMessage = self::SANITISED_REASON;
        }

        return [
            'gate_name'    => $this->gate->displayName(),
            'passed'       => $this->passed,
            'blocking'     => $this->blocking,
            'user_message' => $this->passed ? null : $userMessage,
            'metadata'     => $metadata,
        ];
    }
}
