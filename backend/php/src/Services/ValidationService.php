<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §7.1 Gate Response Format
namespace App\Services;

use App\Models\Cluster;
use App\Models\Intent;
use App\Models\Sku;
use App\Models\SkuGateStatus;
use App\Models\SkuIntent;
use App\Models\ValidationLog;
use App\Enums\ValidationStatus;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\Log;

class ValidationService
{
    private PythonWorkerClient $pythonClient;
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

    // SOURCE: DECISION-011 — persist sku_gate_status using legacy gate_code values for portfolio UI.
    private const OPENAPI_TO_GATE_CODE = [
        'G1_cluster_id' => 'G1_BASIC_INFO',
        'G2_primary_intent' => 'G2_INTENT',
        'G3_secondary_intents' => 'G3_SECONDARY_INTENT',
        'G4_answer_block' => 'G4_ANSWER_BLOCK',
        'G5_best_not_for' => 'G5_BEST_NOT_FOR',
        'G6_tier_tag' => 'G6_TIER_TAG',
        'G6_1_tier_lock' => 'G6_1_TIER_LOCK',
        'G7_expert_authority' => 'G7_EXPERT',
    ];

    private const LEGACY_INTENT_KEY_ALIASES = [
        'safety_compliance' => 'regulatory',
        'regulatory_safety' => 'regulatory',
        'inspiration_style' => 'inspiration',
        'installation_how_to' => 'installation',
        'replacement_refill' => 'replacement',
    ];

    public function __construct(PythonWorkerClient $pythonClient)
    {
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

        // SOURCE: openapi.yaml ValidationResponse — gate object should always surface the full gate contract for consumers.
        $requiredGateKeys = [
            'G1_cluster_id',
            'G2_primary_intent',
            'G3_secondary_intents',
            'G4_answer_block',
            'G5_best_not_for',
            'G6_tier_tag',
            'G6_1_tier_lock',
            'G7_expert_authority',
        ];
        foreach ($requiredGateKeys as $requiredGateKey) {
            if (!array_key_exists($requiredGateKey, $openapiGates)) {
                $openapiGates[$requiredGateKey] = [
                    'status' => 'not_applicable',
                    'error_code' => null,
                    'detail' => 'Gate not applicable for this tier',
                    'user_message' => null,
                ];
            }
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
     * Full validation pipeline for a SKU — delegates to Python POST /api/v1/sku/validate (DECISION-011).
     */
    public function validate(Sku $sku, bool $preserveStatus = false, string $action = 'save'): array
    {
        Log::info("Starting validation for SKU {$sku->id}", ['sku_code' => $sku->sku_code]);

        try {
            $tier = $sku->tier instanceof \App\Enums\TierType
                ? $sku->tier->value
                : strtolower((string) ($sku->tier ?? ''));
            if ($tier === 'kill') {
                return [
                    'valid' => false,
                    'status' => ValidationStatus::INVALID,
                    'kill_blocked' => true,
                    'http_status' => 403,
                    'openapi_validation_body' => [
                        'status' => 'fail',
                        'gates' => [],
                        'vector_check' => ['status' => 'not_applicable', 'user_message' => null],
                        'degraded_mode' => false,
                        'save_allowed' => false,
                        'publish_allowed' => false,
                    ],
                ];
            }

            $payload = $this->buildSkuValidatePayload($sku, $action);
            $proxy = $this->pythonClient->validateSkuGates($payload);
            $httpStatus = (int) ($proxy['http_status'] ?? 500);
            $body = is_array($proxy['body'] ?? null) ? $proxy['body'] : [];

            if (!$preserveStatus) {
                $this->persistSkuGateStatusFromOpenApiBody($sku, $body);
            }

            $topStatus = strtolower((string) ($body['status'] ?? 'fail'));
            $status = match ($topStatus) {
                'pass' => ValidationStatus::VALID,
                'pending' => ValidationStatus::DEGRADED,
                default => ValidationStatus::INVALID,
            };

            $failures = $this->extractFailuresFromOpenApiBody($body);
            $vectorCheck = is_array($body['vector_check'] ?? null) ? $body['vector_check'] : [];
            $vectorStatus = strtolower((string) ($vectorCheck['status'] ?? 'pass'));
            $vectorValidation = [
                'gate' => 'G4_VECTOR',
                'valid' => in_array($vectorStatus, ['pass', 'warn'], true),
                'blocking' => $vectorStatus === 'fail',
                'reason' => (string) ($vectorCheck['user_message'] ?? ''),
            ];

            $canPublish = (bool) ($body['publish_allowed'] ?? false);
            $isDegraded = (bool) ($body['degraded_mode'] ?? false) || $topStatus === 'pending';
            $nextAction = match ($topStatus) {
                'pass' => $vectorStatus === 'warn'
                    ? 'Gates passed; resolve vector similarity warning before publishing.'
                    : 'All gates passed.',
                'pending' => 'Validation pending — publishing paused until the service completes.',
                default => 'Fix validation errors before publication',
            };

            $validationLogId = null;
            try {
                $validationLog = ValidationLog::create([
                    'sku_id' => $sku->id,
                    'user_id' => auth()->id() ?? null,
                    'validation_status' => $status->value,
                    'results_json' => json_encode($body),
                    'passed' => $status === ValidationStatus::VALID,
                ]);
                $validationLogId = $validationLog->id;
            } catch (\Throwable $logEx) {
                Log::warning("Validation log write failed for SKU {$sku->id}: {$logEx->getMessage()}");
            }

            Log::info("Validation complete for SKU {$sku->id}", ['status' => $status, 'validation_log_id' => $validationLogId]);

            $warnings = [];
            if ($vectorStatus === 'warn') {
                $warnings[] = [
                    'field' => 'description',
                    'message' => (string) ($vectorCheck['user_message'] ?? 'Your content may not align with the intent. Consider revising.'),
                ];
            }

            $responseHttp = $httpStatus >= 500 ? 500 : ($topStatus === 'fail' ? 400 : 200);

            return [
                'valid' => $status === ValidationStatus::VALID,
                'status' => $status,
                'validation_log_id' => $validationLogId,
                'results' => $body['gates'] ?? [],
                'gates' => $body['gates'] ?? [],
                'failures' => $failures,
                'warnings' => $warnings,
                'next_action' => $nextAction,
                'can_publish' => $canPublish,
                'ai_validation_pending' => $isDegraded,
                'vector_validation' => $vectorValidation,
                'action' => $action,
                'http_status' => $responseHttp,
                'openapi_validation_body' => $this->normalizeOpenApiValidationBody($body),
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
     * SOURCE: openapi.yaml SkuValidateRequest — map persisted/in-memory SKU to Python validate payload.
     */
    protected function buildSkuValidatePayload(Sku $sku, string $action): array
    {
        $clusterId = null;
        if ($sku->relationLoaded('primaryCluster') && $sku->primaryCluster) {
            $clusterId = $sku->primaryCluster->name;
        } elseif (!empty($sku->primary_cluster_id)) {
            $rawCluster = (string) $sku->primary_cluster_id;
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $rawCluster)) {
                $cluster = $sku->relationLoaded('primaryCluster') ? $sku->primaryCluster : Cluster::find($rawCluster);
                $clusterId = $cluster?->name;
                if ($clusterId === null || $clusterId === '') {
                    $clusterId = \Illuminate\Support\Facades\DB::table('clusters')
                        ->where('id', $rawCluster)
                        ->value('name');
                }
            } else {
                // Draft validate may set business cluster_id directly on the in-memory SKU.
                $clusterId = $rawCluster;
            }
        }

        $primaryIntent = null;
        $secondaryIntents = [];
        if ($sku->relationLoaded('skuIntents')) {
            foreach ($sku->skuIntents as $si) {
                $name = (string) ($si->intent->name ?? '');
                $key = $this->intentNameToApiKey($name);
                if ($key === null || $key === '') {
                    continue;
                }
                if ($si->is_primary) {
                    $primaryIntent = $key;
                } else {
                    $secondaryIntents[] = $key;
                }
            }
        }

        $tier = $sku->tier instanceof \App\Enums\TierType
            ? $sku->tier->value
            : strtolower((string) ($sku->tier ?? ''));

        return [
            'sku_id' => (string) ($sku->sku_code ?? $sku->id),
            'cluster_id' => $clusterId,
            'tier' => $tier !== '' ? $tier : null,
            'primary_intent' => $primaryIntent,
            'secondary_intents' => $secondaryIntents,
            'title' => $sku->title,
            'description' => $sku->long_description,
            'answer_block' => $sku->ai_answer_block,
            'best_for' => $this->normalizeStringList($sku->best_for ?? []),
            'not_for' => $this->normalizeStringList($sku->not_for ?? []),
            'expert_authority' => $sku->expert_authority ?? null,
            'action' => in_array($action, ['save', 'publish'], true) ? $action : 'save',
        ];
    }

    protected function persistSkuGateStatusFromOpenApiBody(Sku $sku, array $body): void
    {
        $gates = is_array($body['gates'] ?? null) ? $body['gates'] : [];
        $skuKey = (string) ($sku->sku_code ?? $sku->id);

        foreach ($gates as $openapiKey => $gate) {
            if (!is_array($gate)) {
                continue;
            }
            $gateCode = self::OPENAPI_TO_GATE_CODE[$openapiKey] ?? null;
            if ($gateCode === null) {
                continue;
            }
            try {
                SkuGateStatus::updateOrCreate(
                    ['sku_id' => $skuKey, 'gate_code' => $gateCode],
                    [
                        'status' => strtolower((string) ($gate['status'] ?? 'fail')),
                        'error_code' => $gate['error_code'] ?? null,
                        'error_message' => $gate['detail'] ?? $gate['user_message'] ?? null,
                        'checked_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('sku_gate_status persist failed', [
                    'sku_id' => $skuKey,
                    'gate' => $gateCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $vector = is_array($body['vector_check'] ?? null) ? $body['vector_check'] : [];
        if ($vector !== []) {
            try {
                SkuGateStatus::updateOrCreate(
                    ['sku_id' => $skuKey, 'gate_code' => 'G4_VECTOR'],
                    [
                        'status' => strtolower((string) ($vector['status'] ?? 'pending')),
                        'error_code' => null,
                        'error_message' => $vector['user_message'] ?? null,
                        'checked_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('sku_gate_status vector persist failed', ['sku_id' => $skuKey, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @return list<array{gate: string, error_code: ?string, detail: string, user_message: string}>
     */
    protected function extractFailuresFromOpenApiBody(array $body): array
    {
        $failures = [];
        foreach ((array) ($body['gates'] ?? []) as $gateKey => $gate) {
            if (!is_array($gate) || strtolower((string) ($gate['status'] ?? '')) !== 'fail') {
                continue;
            }
            $failures[] = [
                'gate' => (string) $gateKey,
                'error_code' => $gate['error_code'] ?? null,
                'detail' => (string) ($gate['detail'] ?? ''),
                'user_message' => (string) ($gate['user_message'] ?? $gate['detail'] ?? ''),
            ];
        }

        $vector = is_array($body['vector_check'] ?? null) ? $body['vector_check'] : [];
        if (strtolower((string) ($vector['status'] ?? '')) === 'fail') {
            $failures[] = [
                'gate' => 'vector_check',
                'error_code' => null,
                'detail' => (string) ($vector['user_message'] ?? 'Vector validation failed'),
                'user_message' => (string) ($vector['user_message'] ?? ''),
            ];
        }

        return $failures;
    }

    protected function normalizeOpenApiValidationBody(array $body): array
    {
        return [
            'status' => $body['status'] ?? 'fail',
            'gates' => is_array($body['gates'] ?? null) ? $body['gates'] : [],
            'vector_check' => is_array($body['vector_check'] ?? null)
                ? $body['vector_check']
                : ['status' => 'pass', 'user_message' => null],
            'degraded_mode' => (bool) ($body['degraded_mode'] ?? false),
            'save_allowed' => (bool) ($body['save_allowed'] ?? true),
            'publish_allowed' => (bool) ($body['publish_allowed'] ?? false),
        ];
    }

    protected function intentNameToApiKey(string $name): ?string
    {
        $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '_', $name), '_'));
        if ($key === '') {
            return null;
        }
        $key = self::LEGACY_INTENT_KEY_ALIASES[$key] ?? $key;

        if (array_key_exists($key, self::INTENT_TAXONOMY_MAP)) {
            return $key;
        }

        foreach (self::INTENT_TAXONOMY_MAP as $canonical => $label) {
            $labelKey = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '_', $label), '_'));
            if ($labelKey === $key) {
                return $canonical;
            }
        }

        return $key;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    protected function normalizeStringList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $value), static fn ($v) => $v !== ''));
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->normalizeStringList($decoded);
            }
        }

        return [];
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.2, openapi.yaml SkuValidateRequest — optional draft payload merged in-memory for gate evaluation (not persisted here).
     */
    public function validateSku(string $id, array $draft = [])
    {
        $sku = Sku::with(['skuIntents.intent', 'primaryCluster'])->findOrFail($id);
        $action = strtolower((string) ($draft['action'] ?? 'save'));
        if (!in_array($action, ['save', 'publish'], true)) {
            $action = 'save';
        }
        if ($draft !== []) {
            $this->applyValidationDraft($sku, $draft);
        }
        app()->instance('cie.validation_draft_keys', array_keys($draft));
        try {
            return $this->validate($sku, false, $action);
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
        // SOURCE: CLAUDE.md R3 — no hard-coded thresholds
        $quorumPause   = (int) BusinessRules::get('decay.quorum_pause_minimum', 2);
        if ($successCount >= $quorumAdvance) {
            return 'ADVANCE';
        } elseif ($successCount == $quorumPause) {
            return 'PAUSE';
        } else {
            return 'FREEZE';  // <=1 engine = Run FAILED, decay FROZEN
        }
    }
}
