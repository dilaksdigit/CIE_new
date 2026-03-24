<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 Gate Response Format
namespace App\Services;

use App\Models\Intent;
use App\Models\Sku;
use App\Models\SkuIntent;
use App\Models\ValidationLog;
use App\Enums\ValidationStatus;
use App\Support\BusinessRules;
use App\Validators\GateValidator;
use Illuminate\Support\Facades\Log;

class ValidationService
{
    protected $validator;
    private $pythonClient;
    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §8.3 Intent Taxonomy Lookup Table
    // SOURCE: CIE_v231_Developer_Build_Pack §intent_taxonomy — intent_key → label mapping
    // These are the ONLY valid intent keys. Any other value must fail G2 validation.
    private const INTENT_TAXONOMY_MAP = [
        'problem_solving' => 'Problem-Solving',
        'comparison' => 'Comparison',
        'compatibility' => 'Compatibility',
        'specification' => 'Specification',
        'installation' => 'Installation / How-To',
        'troubleshooting' => 'Troubleshooting',
        'inspiration' => 'Inspiration / Style',
        'regulatory' => 'Regulatory / Safety',
        'replacement' => 'Replacement / Refill',
    ];

    // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §7.2 — gate response key contract
    private const OPENAPI_GATE_KEY_MAP = [
        'G1_BASIC_INFO'        => 'G1_cluster_id',
        'G2_INTENT'            => 'G2_primary_intent',
        'G3_SECONDARY_INTENT'  => 'G3_secondary_intents',
        'G4_ANSWER_BLOCK'      => 'G4_answer_block',
        'G4_VECTOR'            => 'vector_check',
        'G5_BEST_NOT_FOR'      => 'G5_best_not_for',
        'G5_TECHNICAL'         => 'G5_best_not_for',
        'G6_COMMERCIAL_POLICY' => 'G6_tier_tag',
        'G6_TIER_TAG'          => 'G6_tier_tag',
        'G6_1_TIER_LOCK'       => 'G6_1_tier_lock',
        'G7_EXPERT'            => 'G7_expert_authority',
    ];

    public function __construct(GateValidator $validator, PythonWorkerClient $pythonClient)
    {
        $this->validator = $validator;
        $this->pythonClient = $pythonClient;
    }

    /**
     * SOURCE: openapi.yaml ValidationResponse, ENF§7.2 — top-level contract for CMS validate (no envelope).
     * FIX: MF-01 — Response uses `gates` only; no parallel `failures` array in HTTP body.
     */
    protected function buildOpenApiValidationBody(array $validationResults): array
    {
        $gatesKeyed = $validationResults['gates'] ?? [];
        $openapiGates = [];
        foreach ($gatesKeyed as $id => $g) {
            if (!is_array($g)) {
                continue;
            }
            $openapiGates[$id] = [
                'status' => $g['status'] ?? (($g['passed'] ?? false) ? 'pass' : 'fail'),
                'error_code' => $g['error_code'] ?? ($g['metadata']['error_code'] ?? null),
                'detail' => $g['detail'] ?? ($g['metadata']['detail'] ?? $g['reason'] ?? null),
                'user_message' => $g['user_message'] ?? ($g['metadata']['user_message'] ?? null),
            ];
        }

        $hasBlockingFailure = false;
        foreach ($gatesKeyed as $g) {
            if (!is_array($g)) {
                continue;
            }
            if (!($g['passed'] ?? true) && ($g['blocking'] ?? true)) {
                $hasBlockingFailure = true;
                break;
            }
        }

        $degraded = (bool) ($validationResults['degraded_mode'] ?? false);
        $s = strtolower((string) ($validationResults['status'] ?? ''));

        $topStatus = 'fail';
        if ($hasBlockingFailure) {
            $topStatus = 'fail';
        } elseif ($degraded) {
            $topStatus = 'pending';
        } elseif ($s === 'valid') {
            $topStatus = 'pass';
        } elseif ($s === 'invalid') {
            $topStatus = 'fail';
        } elseif ($s === 'degraded') {
            $topStatus = 'pending';
        }

        // SOURCE: openapi.yaml ValidationResponse — gates object at root; no parallel `failures` array (ENF§7.2).
        // FIX: MF-01 — Omit failures from HTTP body; consumers use per-gate status under `gates`.
        return [
            'status' => $topStatus,
            'gates' => $openapiGates,
            'vector_check' => $validationResults['vector_check'] ?? ['status' => 'pass', 'user_message' => null],
            'degraded_mode' => $degraded,
            'save_allowed' => (bool) ($validationResults['save_allowed'] ?? true),
            'publish_allowed' => (bool) ($validationResults['publish_allowed'] ?? false),
        ];
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
            $resultRows = $validationResults['results'] ?? [];
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

            // SOURCE: CIE_Master_Developer_Build_Spec §7.1 — list every failed gate row (supports multiple G5 failures)
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §7.3 — only documented CIE_* gate codes; no synthetic CIE_G5-style codes
            $failures = [];
            foreach ($resultRows as $g) {
                if (! ($g['passed'] ?? true)) {
                    $errorCode = $g['error_code'] ?? ($g['metadata']['error_code'] ?? null);
                    if ($errorCode === null || $errorCode === '') {
                        // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §7.3 — only 12 defined CIE_ codes permitted.
                        // Missing error_code is a gate implementation bug, not a user-facing error.
                        Log::error('Gate failed without error_code — gate implementation bug', [
                            'sku_id' => $sku->id,
                            'gate'   => $g['gate'] ?? $g['gate_name'] ?? 'unknown',
                        ]);
                        $errorCode = null;
                    }
                    $gateEnum = (string) ($g['gate'] ?? '');
                    $failures[] = [
                        'gate'         => self::OPENAPI_GATE_KEY_MAP[$gateEnum] ?? $gateEnum,
                        'error_code'   => $errorCode,
                        'detail'       => $g['detail'] ?? ($g['metadata']['detail'] ?? '') ?: ($g['reason'] ?? $g['user_message'] ?? ''),
                        'user_message' => $g['user_message'] ?? ($g['metadata']['user_message'] ?? '') ?: ($g['reason'] ?? ''),
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
            foreach ($resultRows as $g) {
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
                'openapi_validation_body' => $this->buildOpenApiValidationBody($validationResults),
            ];
        } catch (\Exception $e) {
            Log::error("Validation failed for SKU {$sku->id}: {$e->getMessage()}");
            return [
                'valid' => false,
                'status' => ValidationStatus::INVALID,
                'next_action' => 'Validation service error',
                'error' => $e->getMessage(),
                'failures' => [['gate' => 'VALIDATION', 'error_type' => 'INTERNAL_VALIDATION_ERROR', 'detail' => $e->getMessage(), 'user_message' => 'Validation service error.']],
                'http_status' => 500,
                // SOURCE: openapi.yaml ValidationResponse — status enum pass|fail|pending only (CLAUDE.md §10)
                // FIX: API-08 — exception path uses pending + degraded_mode (no non-spec top-level keys)
                'openapi_validation_body' => [
                    'status' => 'pending',
                    'gates' => [],
                    'vector_check' => [
                        'status' => 'pending',
                        'user_message' => 'Validation service error. Please try again.',
                    ],
                    'degraded_mode' => true,
                    'save_allowed' => true,
                    'publish_allowed' => false,
                ],
            ];
        }
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.2, openapi.yaml SkuValidateRequest — optional draft payload merged in-memory for gate evaluation (not persisted here).
     */
    public function validateSku(string $id, array $draft = [])
    {
        $sku = Sku::with(['skuIntents.intent'])->findOrFail($id);
        if ($draft !== []) {
            $this->applyValidationDraft($sku, $draft);
        }
        app()->instance('cie.validation_draft_keys', array_keys($draft));
        try {
            return $this->validate($sku);
        } finally {
            app()->forgetInstance('cie.validation_draft_keys');
        }
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.2 — map validate request fields onto the in-memory SKU graph (primary_cluster_id, long_description, ai_answer_block, intents).
     */
    protected function applyValidationDraft(Sku $sku, array $draft): void
    {
        if (array_key_exists('cluster_id', $draft)) {
            $sku->primary_cluster_id = $draft['cluster_id'];
        }
        if (array_key_exists('tier', $draft) && $draft['tier'] !== null && trim((string) $draft['tier']) !== '') {
            $sku->tier = strtolower(trim((string) $draft['tier']));
        }
        if (array_key_exists('title', $draft)) {
            $sku->title = $draft['title'];
        }
        if (array_key_exists('description', $draft)) {
            $sku->long_description = $draft['description'];
        }
        if (array_key_exists('answer_block', $draft)) {
            $sku->ai_answer_block = $draft['answer_block'];
        }
        if (array_key_exists('expert_authority', $draft)) {
            $sku->expert_authority = $draft['expert_authority'];
        }
        if (array_key_exists('best_for', $draft) && is_array($draft['best_for'])) {
            $sku->best_for = $draft['best_for'];
        }
        if (array_key_exists('not_for', $draft) && is_array($draft['not_for'])) {
            $sku->not_for = $draft['not_for'];
        }

        if (array_key_exists('primary_intent', $draft) || array_key_exists('secondary_intents', $draft)) {
            $collection = collect();
            if (array_key_exists('primary_intent', $draft)) {
                $p = $draft['primary_intent'];
                if (is_array($p)) {
                    $p = $p[0] ?? null;
                }
                if ($p !== null && trim((string) $p) !== '') {
                    $label = $this->intentDraftToTaxonomyLabel((string) $p);
                    $intentModel = new Intent(['name' => $label]);
                    $si = new SkuIntent(['is_primary' => true, 'sku_id' => $sku->id]);
                    $si->setRelation('intent', $intentModel);
                    $collection->push($si);
                }
            } else {
                foreach ($sku->skuIntents->where('is_primary', true) as $existing) {
                    $collection->push($existing);
                }
            }
            if (array_key_exists('secondary_intents', $draft) && is_array($draft['secondary_intents'])) {
                foreach ($draft['secondary_intents'] as $sec) {
                    if ($sec === null || trim((string) $sec) === '') {
                        continue;
                    }
                    $label = $this->intentDraftToTaxonomyLabel((string) $sec);
                    $intentModel = new Intent(['name' => $label]);
                    $si = new SkuIntent(['is_primary' => false, 'sku_id' => $sku->id]);
                    $si->setRelation('intent', $intentModel);
                    $collection->push($si);
                }
            } else {
                foreach ($sku->skuIntents->where('is_primary', false) as $existing) {
                    $collection->push($existing);
                }
            }
            $sku->setRelation('skuIntents', $collection->values());
        }
    }

    /** SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — intent_taxonomy labels (007_seed_canonical_cie.sql) */
    protected function intentDraftToTaxonomyLabel(string $raw): ?string
    {
        $key = strtolower(str_replace([' ', '-', '/'], '_', trim($raw)));

        return self::INTENT_TAXONOMY_MAP[$key] ?? null;
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
