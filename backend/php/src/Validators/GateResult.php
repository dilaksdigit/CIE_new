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

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §2.2 — tier matrix: gate not_applicable for non-applicable tiers
     */
    public static function notApplicable(GateType $gate, string $detail = ''): self
    {
        $meta = ['status' => 'not_applicable', 'user_message' => null];
        if ($detail !== '') {
            $meta['detail'] = $detail;
        }

        return new self(
            gate: $gate,
            passed: true,
            reason: 'not_applicable',
            blocking: false,
            metadata: $meta
        );
    }
 
    private const SANITISED_REASON = 'Your content may not align with the intent. Consider revising.';

    // SOURCE: openapi.yaml ValidationResponse — gates must include error_code, detail, user_message
    public function toArray(): array
    {
        $userMessage = $this->metadata['user_message'] ?? null;
        if ($userMessage === null) {
            $userMessage = $this->passed ? null : $this->reason;
        }
        $gateKey = strtolower($this->gate->value);
        $isVectorGate = in_array($gateKey, ['g4_vector', 'g5_vector', 'vector_similarity'], true);

        if ($isVectorGate && is_string($userMessage) && preg_match('/\d+\.\d+/', $userMessage)) {
            $userMessage = self::SANITISED_REASON;
        }

        // SOURCE: CLAUDE.md §11 — vector fail-soft: passed=true + warn_only still surfaces user_message
        $surfaceUserMessage = ! $this->passed
            || ! empty($this->metadata['warn_only'])
            || (($this->metadata['status'] ?? '') === 'warn');

        $result = [
            'gate_name'    => $this->gate->displayName(),
            'gate'         => $this->gate->value,
            'status'       => $this->passed ? 'pass' : 'fail',
            'detail'       => $this->metadata['detail'] ?? $this->reason,
            'passed'       => $this->passed,
            'blocking'     => $this->blocking,
            'user_message' => $surfaceUserMessage ? $userMessage : null,
        ];

        if (isset($this->metadata['error_code'])) {
            $result['error_code'] = $this->metadata['error_code'];
        }

        if (isset($this->metadata['user_message']) && $surfaceUserMessage) {
            $result['user_message'] = $this->metadata['user_message'];
        }

        $extra = array_diff_key($this->metadata, array_flip(['error_code', 'user_message', 'detail']));
        if ($isVectorGate) {
            foreach ($extra as $key => $value) {
                $lowerKey = strtolower((string) $key);
                if (in_array($lowerKey, ['similarity', 'score', 'cosine', 'threshold'], true) || is_int($value) || is_float($value)) {
                    unset($extra[$key]);
                }
            }
        } else {
            foreach ($extra as $key => $value) {
                if (strtolower((string) $key) === 'threshold') {
                    unset($extra[$key]);
                }
            }
        }
        if (!empty($extra)) {
            $result['metadata'] = $extra;
        }

        return $result;
    }
}
