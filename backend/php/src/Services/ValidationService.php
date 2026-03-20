<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 Gate Response Format
namespace App\Services;

use App\Models\Sku;
use App\Models\ValidationLog;
use App\Enums\ValidationStatus;
use App\Support\BusinessRules;
use App\Validators\GateValidator;
use Illuminate\Support\Facades\Log;

class ValidationService
{
    protected $validator;
    private $pythonClient;

    public function __construct(GateValidator $validator, PythonWorkerClient $pythonClient)
    {
        $this->validator = $validator;
        $this->pythonClient = $pythonClient;
    }

    /**
     * Full validation pipeline for a SKU.
     * Uses GateValidator keys: overall_status, can_publish, gates. Returns all failures with error_code, detail, user_message.
     */
    public function validate(Sku $sku, bool $preserveStatus = false): array
    {
        Log::info("Starting validation for SKU {$sku->id}", ['sku_code' => $sku->sku_code]);

        try {
            $validationResults = $this->validator->validateAll($sku, $preserveStatus);
            $gates = $validationResults['gates'] ?? [];
            $overallStatus = $validationResults['overall_status'] ?? 'invalid';
            $canPublish = $validationResults['can_publish'] ?? false;

            $vectorValidation = null;
            foreach ($gates as $g) {
                $gateKey = $g['gate'] ?? $g['gate_name'] ?? '';
                $name = $g['gate_name'] ?? $g['gate'] ?? '';
                if (stripos((string) $gateKey, 'VECTOR') !== false || stripos((string) $name, 'vector') !== false) {
                    $vectorValidation = [
                        'gate' => $gateKey,
                        'valid' => $g['passed'] ?? false,
                        'blocking' => $g['blocking'] ?? true,
                        'reason' => $g['reason'] ?? '',
                    ];
                    break;
                }
            }

            $status = ValidationStatus::tryFrom(strtoupper((string) $overallStatus)) ?? ValidationStatus::INVALID;
            $nextAction = $validationResults['next_action'] ?? 'Fix validation errors before publication';
            $isDegraded = !empty(array_filter($gates, fn($g) => ($g['metadata']['degraded'] ?? false)));

            // Build failures list: ALL failed gates with gate, error_code, detail, user_message (for 400 response) — §7.1
            $failures = [];
            foreach ($gates as $g) {
                if (!($g['passed'] ?? true)) {
                    $failures[] = [
                        'gate'         => $g['gate_name'] ?? $g['gate'] ?? 'UNKNOWN',
                        'error_code'   => $g['error_code'] ?? ('CIE_' . ($g['gate'] ?? 'UNKNOWN')),
                        'detail'       => $g['detail'] ?? $g['reason'] ?? $g['user_message'] ?? '',
                        'user_message' => $g['user_message'] ?? $g['reason'] ?? '',
                    ];
                }
            }

            $validationLog = ValidationLog::create([
                'sku_id' => $sku->id,
                'user_id' => auth()->id() ?? null,
                'validation_status' => $status->value,
                'results_json' => json_encode(array_merge($validationResults, ['vector' => $vectorValidation])),
                'passed' => $status === ValidationStatus::VALID,
            ]);

            Log::info("Validation complete for SKU {$sku->id}", ['status' => $status, 'validation_log_id' => $validationLog->id]);

            // SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1 — include warnings when gate passed but warn_only (e.g. vector below threshold)
            $warnings = [];
            foreach ($gates as $g) {
                if (($g['passed'] ?? false) && !empty($g['metadata']['warn_only'])) {
                    $msg = $g['metadata']['user_message'] ?? $g['reason'] ?? 'Content may not fully match expected topic.';
                    $warnings[] = ['field' => 'description', 'message' => $msg];
                }
            }

            return [
                'valid' => $status === ValidationStatus::VALID,
                'status' => $status,
                'validation_log_id' => $validationLog->id,
                'results' => $gates,
                'gates' => $gates,
                'failures' => $failures,
                'warnings' => $warnings,
                'next_action' => $nextAction,
                'can_publish' => $canPublish,
                'ai_validation_pending' => $isDegraded,
                'vector_validation' => $vectorValidation,
                'http_status' => ($status === ValidationStatus::VALID || ($status === ValidationStatus::DEGRADED && empty($failures))) ? 200 : 400,
            ];
        } catch (\Exception $e) {
            Log::error("Validation failed for SKU {$sku->id}: {$e->getMessage()}");
            return [
                'valid' => false,
                'status' => ValidationStatus::INVALID,
                'next_action' => 'Validation service error',
                'error' => $e->getMessage(),
                'failures' => [['gate' => 'VALIDATION', 'error_code' => 'CIE_VALIDATION_ERROR', 'detail' => $e->getMessage(), 'user_message' => 'Validation service error.']],
                'http_status' => 500,
            ];
        }
    }

    public function validateSku($id)
    {
        $sku = Sku::findOrFail($id);
        return $this->validate($sku);
    }

    /**
     * Patch 2: AI Audit Quorum Rules
     * Advances or pauses decay based on engine availability.
     */
    public function evaluateAuditQuorum(Sku $sku, array $engineResults): string
    {
        $successCount = collect($engineResults)->where('status', 'SUCCESS')->count();
        $sku->update(['last_audit_quorum' => $successCount]);

        $quorumAdvance = (int) BusinessRules::get('decay.quorum_minimum');
        $quorumPause   = 2; // §5.3: not in 52 rules; hard-coded
        if ($successCount >= $quorumAdvance) {
            return 'ADVANCE';
        } elseif ($successCount == $quorumPause) {
            return 'PAUSE';
        } else {
            return 'FREEZE';  // <=1 engine = Run FAILED, decay FROZEN
        }
    }
}
