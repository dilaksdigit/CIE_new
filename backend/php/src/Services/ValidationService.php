<?php
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
                $gateKey = $g['gate'] ?? '';
                if ($gateKey === 'G5_VECTOR' || $gateKey === 'G4_VECTOR') {
                    $vectorValidation = [
                        'gate' => $gateKey,
                        'valid' => $g['passed'] ?? false,
                        'blocking' => $g['blocking'] ?? true,
                        'reason' => $g['reason'] ?? '',
                        'similarity' => $g['metadata']['similarity'] ?? 0,
                    ];
                    break;
                }
            }

            $status = ValidationStatus::tryFrom(strtoupper((string) $overallStatus)) ?? ValidationStatus::INVALID;
            $nextAction = $validationResults['next_action'] ?? 'Fix validation errors before publication';
            $isDegraded = !empty(array_filter($gates, fn($g) => ($g['metadata']['degraded'] ?? false)));

            // Build failures list: ALL failed gates with error_code, detail, user_message (for 400 response)
            $failures = [];
            foreach ($gates as $g) {
                if (!($g['passed'] ?? true)) {
                    $failures[] = [
                        'error_code' => $g['error_code'] ?? ('CIE_' . ($g['gate'] ?? 'UNKNOWN')),
                        'detail' => $g['detail'] ?? $g['reason'] ?? '',
                        'user_message' => $g['user_message'] ?? $g['reason'] ?? '',
                    ];
                }
            }

            $validationLog = ValidationLog::create([
                'sku_id' => $sku->id,
                'user_id' => auth()->id() ?? null,
                'validation_status' => $status,
                'results_json' => json_encode(array_merge($validationResults, ['vector' => $vectorValidation])),
                'passed' => $status === ValidationStatus::VALID,
            ]);

            Log::info("Validation complete for SKU {$sku->id}", ['status' => $status, 'validation_log_id' => $validationLog->id]);

            $httpFailStatus = 400;
            try {
                $httpFailStatus = (int) BusinessRules::get('validation.http_fail_status', 400);
            } catch (\Throwable $e) {
                // ignore
            }

            return [
                'valid' => $status === ValidationStatus::VALID,
                'status' => $status,
                'validation_log_id' => $validationLog->id,
                'results' => $gates,
                'gates' => $gates,
                'failures' => $failures,
                'next_action' => $nextAction,
                'can_publish' => $canPublish,
                'ai_validation_pending' => $isDegraded,
                'vector_validation' => $vectorValidation,
                'http_status' => ($status === ValidationStatus::VALID || ($status === ValidationStatus::DEGRADED && empty($failures))) ? 200 : $httpFailStatus,
            ];
        } catch (\Exception $e) {
            Log::error("Validation failed for SKU {$sku->id}: {$e->getMessage()}");
            return [
                'valid' => false,
                'status' => ValidationStatus::INVALID,
                'next_action' => 'Validation service error',
                'error' => $e->getMessage(),
                'failures' => [['error_code' => 'CIE_VALIDATION_ERROR', 'detail' => $e->getMessage(), 'user_message' => 'Validation service error.']],
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

        if ($successCount >= 3) {
            return 'ADVANCE'; // 3/4 engines = Advance decay timer
        } elseif ($successCount == 2) {
            return 'PAUSE';   // 2/4 engines = Scores recorded, decay PAUSED
        } else {
            return 'FREEZE';  // <=1 engine = Run FAILED, decay FROZEN
        }
    }
}
