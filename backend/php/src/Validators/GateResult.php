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
 return [
 'gate' => $this->gate->value,
 'gate_name' => $this->gate->displayName(),
 'passed' => $this->passed,
 'reason' => $this->reason,
 'blocking' => $this->blocking,
 'error_code' => $this->passed ? null : $errorCode,
 'detail' => $this->reason,
 'user_message' => $this->passed ? null : $userMessage,
 'metadata' => $this->metadata
 ];
 }
}
