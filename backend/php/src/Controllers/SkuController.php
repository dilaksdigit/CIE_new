<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 11
namespace App\Controllers;

use App\Models\Sku;
use App\Models\AiAgentLog;
use App\Models\IntentTaxonomy;
use App\Models\SkuIntent;
use App\Models\ContentBrief;
use App\Models\Intent;
use App\Models\AuditLog;
use App\Models\ValidationLog;
use App\Services\ValidationService;
use App\Services\ReadinessScoreService;
use App\Services\FaqSuggestionService;
use App\Services\PermissionService;
use App\Services\BaselineService;
use App\Services\ChannelDeployService;
use App\Services\PublishTraceService;
use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use App\Enums\ValidationStatus;
use App\Enums\TierType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SkuController {
    protected $validationService;
    protected $readinessScoreService;
    protected $faqSuggestionService;
    protected $permissionService;
    protected $baselineService;
    protected $channelDeployService;
    protected $publishTraceService;

    public function __construct(ValidationService $validationService, ReadinessScoreService $readinessScoreService, FaqSuggestionService $faqSuggestionService, PermissionService $permissionService, BaselineService $baselineService, ChannelDeployService $channelDeployService, PublishTraceService $publishTraceService) {
        $this->validationService = $validationService;
        $this->readinessScoreService = $readinessScoreService;
        $this->faqSuggestionService = $faqSuggestionService;
        $this->permissionService = $permissionService;
        $this->baselineService = $baselineService;
        $this->channelDeployService = $channelDeployService;
        $this->publishTraceService = $publishTraceService;
    }

    public function index(Request $request) {
        $query = Sku::with(['primaryCluster', 'skuIntents']);
        
        if ($request->has('tier')) {
            $query->where('tier', $request->query('tier'));
        }

        if ($request->has('cluster_id')) {
            $query->where('primary_cluster_id', $request->query('cluster_id'));
        }

        if ($request->has('category')) {
             $category = $request->query('category');
             if ($category !== 'All Categories') {
                 $query->whereHas('primaryCluster', function($q) use ($category) {
                     $q->where('category', $category);
                 });
             }
        }

        if ($request->has('validation_status')) {
            $query->where('validation_status', $request->query('validation_status'));
        }
        
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('sku_code', 'like', "%$search%")
                  ->orWhere('title', 'like', "%$search%");
            });
        }

        $skuCollection = $query->get();
        $skuCodes = $skuCollection->pluck('sku_code')->filter()->values()->toArray();
        $allGateStatuses = $this->batchLoadGateStatuses($skuCodes);

        // GET /v1/sku — full SKU rows for dashboard, SKU list, bulk ops (envelope { items }).
        $items = $skuCollection->map(function ($sku) use ($allGateStatuses) {
            $arr = $sku->toArray();
            $arr['gates'] = $this->buildGateStatuses($sku, $allGateStatuses[$sku->sku_code] ?? null);
            $arr['vector_gate_status'] = $this->deriveVectorGateStatus($sku);
            $arr['ai_citation_rate'] = $sku->score_citation ?? 0;
            return $this->addCamelCaseAliases($arr);
        })->values()->all();

        return response()->json(['items' => $items], 200);
    }

    public function show($id) {
        $sku = Sku::with(['primaryCluster', 'skuIntents.intent'])->findOrFail($id);

        $validation = $sku->validation_status;
        $tierString = $sku->tier instanceof \App\Enums\TierType ? strtoupper($sku->tier->value) : strtoupper(trim((string) ($sku->tier ?? '')));
        $isValid = $validation instanceof ValidationStatus
            ? $validation === ValidationStatus::VALID
            : strtoupper((string) ($validation ?? '')) === 'VALID';

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §15 — full SKU aggregate (content + commercial + readiness + audit)
        // FIX: API-06 — extend GET /sku/{sku_id} beyond SkuValidateRequest-only fields
        $meta = [
            'tier_lock_reason' => $isValid ? "Validated {$tierString} products have core fields locked for governance." : null,
            'cms_banner' => $this->getTierBanner($tierString ?: 'SUPPORT'),
            'field_tooltips' => [
                'best_for' => "Min 2 required for Hero/Support (v2.3.2)",
                'not_for' => "Min 1 required for all validated SKUs (v2.3.2)"
            ]
        ];

        // SOURCE: openapi.yaml /sku/{sku_id} — SkuValidateRequest-compatible fields at root (backward compatible).
        $primaryIntent = null;
        $secondary = [];
        foreach ($sku->skuIntents as $si) {
            $name = strtolower(str_replace([' ', '-', '/'], '_', (string) ($si->intent->name ?? '')));
            $name = preg_replace('/_+/', '_', trim($name, '_'));
            if ($si->is_primary) {
                $primaryIntent = $name;
            } else {
                $secondary[] = $name;
            }
        }

        $readiness = $this->readinessScoreService->computeReadiness($sku);
        $commercial = $this->buildCommercialSnapshot($sku);
        $gateStatuses = $this->loadGateStatusRowsForSku($sku);
        $auditStatus = $this->buildAuditStatusSnapshot($sku);

        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.2 — writer suggestion cards use latest import_batch
        $semrushImports = $this->loadSemrushImportsForSku($sku);

        return response()->json([
            'sku_id' => (string) $sku->id,
            'sku_code' => (string) ($sku->sku_code ?? ''),
            'product_name' => (string) ($sku->title ?? ''),
            'cluster_id' => $sku->primary_cluster_id,
            'tier' => strtolower((string) ($sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : $sku->tier)),
            'primary_intent' => $primaryIntent,
            'secondary_intents' => array_values($secondary),
            'title' => (string) ($sku->title ?? ''),
            'description' => (string) ($sku->long_description ?? ''),
            'answer_block' => (string) ($sku->ai_answer_block ?? ''),
            'best_for' => self::parseListAttribute($sku->best_for),
            'not_for' => self::parseListAttribute($sku->not_for),
            'expert_authority' => (string) ($sku->expert_authority ?? ''),
            'decay_status' => (string) ($sku->decay_status ?? 'none'),
            'meta' => $meta,
            'commercial' => $commercial,
            'readiness' => $readiness,
            'audit_status' => $auditStatus,
            'gates' => $gateStatuses,
            'semrush_imports' => $semrushImports,
        ], 200);
    }

    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §15 — ERP snapshot fields on SKU
     */
    private function buildCommercialSnapshot(Sku $sku): array
    {
        return [
            'margin_percent' => $sku->margin_percent !== null ? (float) $sku->margin_percent : null,
            'erp_cppc' => $sku->erp_cppc !== null ? (float) $sku->erp_cppc : null,
            'erp_velocity_90d' => $sku->erp_velocity_90d !== null ? (int) $sku->erp_velocity_90d : null,
            'erp_return_rate_pct' => $sku->erp_return_rate_pct !== null ? (float) $sku->erp_return_rate_pct : null,
            'commercial_score' => $sku->commercial_score !== null ? (float) $sku->commercial_score : null,
            'annual_volume' => $sku->annual_volume !== null ? (int) $sku->annual_volume : null,
        ];
    }

    /**
     * SOURCE: GateValidator + sku_gate_status — latest gate audit snapshot
     */
    private function loadGateStatusRowsForSku(Sku $sku): array
    {
        if (!Schema::hasTable('sku_gate_status')) {
            return [];
        }
        $key = (string) ($sku->sku_code ?? '');
        if ($key === '') {
            return [];
        }
        try {
            $rows = DB::table('sku_gate_status')->where('sku_id', $key)->get();
            $out = [];
            foreach ($rows as $row) {
                $out[$row->gate_code] = [
                    'status' => $row->status,
                    'error_code' => $row->error_code ?? null,
                    'checked_at' => $row->checked_at ?? null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('loadGateStatusRowsForSku: ' . $e->getMessage(), ['sku_id' => $sku->id]);
            return [];
        }
    }

    /**
     * SOURCE: CLAUDE.md §10 — audit trail; validation_logs for last run
     */
    private function buildAuditStatusSnapshot(Sku $sku): array
    {
        $validationStatus = $sku->validation_status instanceof ValidationStatus
            ? $sku->validation_status->value
            : (string) ($sku->validation_status ?? '');

        $last = null;
        if (Schema::hasTable('validation_logs')) {
            try {
                $last = ValidationLog::where('sku_id', $sku->id)->orderByDesc('id')->first();
            } catch (\Throwable $e) {
                $last = null;
            }
        }

        return [
            'validation_status' => $validationStatus,
            'last_validation_passed' => $last ? (bool) $last->passed : null,
            'last_validation_at' => $last && isset($last->created_at) && $last->created_at
                ? $last->created_at->toIso8601String()
                : null,
        ];
    }

    /**
     * Load semrush_imports rows for this SKU (by sku_code) for Writer Edit keyword/competitor cards.
     * Returns [] if table or sku_code column missing (e.g. pre-migration 064).
     *
     * SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.2 — latest import_batch only (FIX SEM-07)
     */
    private function loadSemrushImportsForSku(Sku $sku): array
    {
        if (!Schema::hasTable('semrush_imports')) {
            return [];
        }
        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.2 — filter latest batch; optional sku_code column for row-level SKU linkage
        $skuCode = (string) ($sku->sku_code ?? '');
        try {
            $latestBatch = DB::table('semrush_imports')->max('import_batch');
            if ($latestBatch === null) {
                return [];
            }
            $q = DB::table('semrush_imports')->where('import_batch', $latestBatch);
            if (Schema::hasColumn('semrush_imports', 'sku_code') && $skuCode !== '') {
                $q->where('sku_code', $skuCode);
            }
            $rows = $q->orderByDesc('search_volume')->limit(100)->get();
            return $rows->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            Log::warning('loadSemrushImportsForSku failed: ' . $e->getMessage(), ['sku_id' => $sku->id]);
            return [];
        }
    }

    /** v2.3.2 Patch 6: Exact tier banner copy per CIE Hardening Addendum §6.1. */
    private function getTierBanner($tier) {
        $t = is_string($tier) ? strtoupper(trim($tier)) : '';
        switch ($t) {
            case 'HERO':
                return 'HERO SKU — Full CIE Coverage. This product is a top-revenue performer. All 9 intent types, full Answer Block, FAQ, JSON-LD, and channel feeds are enabled. Target: ≥85 readiness on all active channels within 30 days.';
            case 'SUPPORT':
                return 'SUPPORT SKU — Focused Coverage. This product supports revenue but does not lead. Primary intent + max 2 secondary intents enabled. Answer Block and Best-For/Not-For required. Max 2 hours per quarter.';
            case 'HARVEST':
                return 'HARVEST SKU — Maintenance Mode. This product has low margin and limited growth potential. Only Specification + 1 optional intent are available. Answer Block, Best-For/Not-For, and Expert Authority are suspended. Max 30 minutes per quarter. Focus your time on Hero SKUs instead.';
            case 'KILL':
                return 'KILL SKU — Editing Disabled. This product has negative margin or is flagged for delisting. All content fields are read-only. No time investment permitted. If you believe this classification is wrong, contact your Portfolio Holder to request a tier review (requires Finance co-approval).';
            default:
                return 'Standard technical maintenance mode active.';
        }
    }

    public function update(Request $request, $id) {
        try {
            // Kill-tier check first: load and reject before any other logic (no bypass)
            $sku = Sku::lockForUpdate()->findOrFail($id);
            if ($sku->tier === \App\Enums\TierType::KILL) {
                // SOURCE: openapi.yaml ValidationResponse; CLAUDE.md R3 — plain English user_message; no gate codes for writer UI
                return response()->json([
                    'status' => 'fail',
                    'gates' => [
                        'tier_content_lock' => [
                            'status' => 'fail',
                            'error_code' => 'CIE_TIER_LOCKED',
                            'detail' => 'Kill-tier SKU — all content fields are locked.',
                            'user_message' => 'This product is scheduled for removal. No content changes are allowed.',
                        ],
                    ],
                    'vector_check' => [
                        'status' => 'pass',
                        'user_message' => null,
                    ],
                    'degraded_mode' => false,
                    'save_allowed' => false,
                    'publish_allowed' => false,
                ], 400);
            }

            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 Gate G6.1
            // SOURCE: CIE_Master_Developer_Build_Spec.docx Gate G6.1 — Server-side enforcement
            if ($sku->tier === \App\Enums\TierType::HARVEST) {
                $harvestBlocked = [
                    'answer_block', 'best_for', 'not_for',
                    'expert_authority', 'title', 'long_description', 'wikidata_uri',
                ];

                foreach ($harvestBlocked as $field) {
                    if ($request->has($field)) {
                        return response()->json([
                            'status'       => 'fail',
                            'error_code'   => 'HARVEST_TIER_FIELD_BLOCKED',
                            'detail'       => "Harvest-tier SKU. Field '{$field}' is not permitted.",
                            'user_message' => 'This SKU is Harvest tier. Only Specification and 1 '
                                            . 'optional intent are available. Answer Block, '
                                            . 'Best-For/Not-For, and Expert Authority are suspended.',
                        ], 422);
                    }
                }

                if ($request->has('secondary_intents')) {
                    // Harvest allows one optional secondary from the tier-locked set.
                    $allowed  = ['problem_solving', 'compatibility', 'specification'];
                    $provided = (array) $request->input('secondary_intents');

                    if (count($provided) > 1) {
                        return response()->json([
                            'status'     => 'fail',
                            'error_code' => 'HARVEST_SECONDARY_INTENT_LIMIT',
                            'detail'     => 'Harvest-tier SKU allows max 1 secondary intent.',
                        ], 422);
                    }

                    foreach ($provided as $intent) {
                        if (!in_array($intent, $allowed, true)) {
                            return response()->json([
                                'status'     => 'fail',
                                'error_code' => 'HARVEST_SECONDARY_INTENT_INVALID',
                                'detail'     => "Harvest-tier SKU secondary intent must be one of 'problem_solving', 'compatibility', or 'specification'. Got: '{$intent}'.",
                            ], 422);
                        }
                    }
                }
            }

            // Version conflict detection
            $clientVersion = $request->input('lock_version');
            if ($clientVersion !== null && $clientVersion != $sku->lock_version) {
                return response()->json([
                    'error' => 'VERSION_CONFLICT',
                    'message' => "This SKU was modified by another user. Please merge or discard your changes (v{$clientVersion} != server v{$sku->lock_version}).",
                ], 409);
            }

            // 3.2 Permission matrix: only allow fields this role may edit
            $allowedFields = $this->permissionService->allowedSkuUpdateFields(auth()->user());
            $updateData = [];
            foreach ($allowedFields as $field) {
                if ($field === 'lock_version') continue;
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }
            $updateData['lock_version'] = ($sku->lock_version ?? 1) + 1;

            // SOURCE: openapi.yaml /sku/{sku_id}/validate — validate incoming payload before persist.
            $draftValidation = $this->validationService->validateSku((string) $id, $request->all());
            $draftHttp = (int) ($draftValidation['http_status'] ?? 200);
            $draftBody = $draftValidation['openapi_validation_body'] ?? null;
            if ($draftHttp >= 400 && is_array($draftBody)) {
                return response()->json($draftBody, 400);
            }

            // Gate enforcement: do not allow setting validation_status to VALID/PENDING without passing gates
            $requestedStatus = $updateData['validation_status'] ?? null;
            if ($requestedStatus !== null && in_array($requestedStatus, ['VALID', 'PENDING'], true)) {
                $validationResult = $this->validationService->validate($sku->fresh(), true);
                $results = $validationResult['results'] ?? [];
                $blockingFailures = array_values(collect($results)->where('blocking', true)->where('passed', false)->all());
                $valid = $validationResult['valid'] ?? false;
                if (!$valid || !empty($blockingFailures)) {
                    return response()->json([
                        'error' => 'BLOCKING_GATE_FAILURE',
                        'message' => 'Cannot publish: one or more blocking gates failed.',
                    ], 403);
                }
            }

            // Field-level audit: capture old values before update (exclude lock_version)
            $auditFields = array_diff_key($updateData, ['lock_version' => true]);
            $oldValues = [];
            foreach (array_keys($auditFields) as $field) {
                $oldValues[$field] = $sku->getAttribute($field);
            }

            $sku->update($updateData);

            // Log each changed field to audit_log (old/new diff)
            // SOURCE: CLAUDE.md §10 — every save attempt traceable
            // FIX: API-18 — log content_save_no_change when no field values changed
            $userId = auth()->id();
            $fieldAuditCount = 0;
            foreach ($auditFields as $field => $newVal) {
                $oldVal = $oldValues[$field] ?? null;
                if ($oldVal === $newVal) continue;
                AuditLog::create([
                    'entity_type' => 'sku',
                    'entity_id'  => (string) $sku->id,
                    'action'     => 'update',
                    'field_name' => $field,
                    'old_value'  => is_scalar($oldVal) ? (string) $oldVal : json_encode($oldVal),
                    'new_value'  => is_scalar($newVal) ? (string) $newVal : json_encode($newVal),
                    'actor_id'   => (string) ($userId ?? 'SYSTEM'),
                    'actor_role' => optional(optional(auth()->user())->role)->name ?? 'system',
                    'timestamp'  => now(),
                    'user_id'    => $userId,
                ]);
                $fieldAuditCount++;
            }
            if ($fieldAuditCount === 0) {
                AuditLog::create([
                    'entity_type' => 'sku',
                    'entity_id'   => (string) $sku->id,
                    'action'      => 'content_save_no_change',
                    'field_name'  => null,
                    'old_value'   => null,
                    'new_value'   => null,
                    'actor_id'    => (string) ($userId ?? 'SYSTEM'),
                    'actor_role'  => optional(optional(auth()->user())->role)->name ?? 'system',
                    'timestamp'   => now(),
                    'user_id'     => $userId,
                ]);
            }

            // Run validation after update
            $manualStatusUpdate = isset($updateData['validation_status']);
            $validationResult = $this->validationService->validate($sku->fresh(), $manualStatusUpdate);

            return response()->json([
                'status' => 'updated',
                'validation' => $validationResult['openapi_validation_body'] ?? null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(404, 'SKU_NOT_FOUND', 'SKU not found');
        } catch (\Exception $e) {
            Log::error('SKU update failed: ' . $e->getMessage());
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(500, 'UPDATE_FAILED', 'Update failed');
        }
    }

    public function store(Request $request) {
        $data = $request->all();
        $data['lock_version'] = 1;

        // Only pass columns that exist on skus (expert_authority, ai_answer_block may be missing pre-migration)
        $table = (new Sku)->getTable();
        $columns = Schema::getColumnListing($table);
        $data = array_intersect_key($data, array_fill_keys($columns, true));

        $sku = Sku::create($data);

        AuditLog::create([
            'entity_type' => 'sku',
            'entity_id'   => (string) $sku->id,
            'action'      => 'create',
            'new_value'   => json_encode($data),
            'actor_id'    => (string) (auth()->id() ?? 'SYSTEM'),
            'actor_role'  => auth()->user()->role->name ?? 'system',
            'timestamp'   => now(),
            'user_id'     => auth()->id(),
        ]);

        // Run validation after creation
        $validationResult = $this->validationService->validate($sku->fresh());

        return response()->json([
            'sku' => $sku->fresh(['primaryCluster', 'skuIntents.intent']),
            'validation' => $validationResult
        ], 201);
    }

    /**
     * PUT /api/v1/sku/{sku_id}/content — delegate to update logic (gates + audit log).
     */
    public function updateContent(Request $request, string $sku_id)
    {
        return $this->update($request, $sku_id);
    }

    /**
     * POST /api/v1/sku/{sku_id}/publish — publish SKU to active channels.
     * SOURCE: CIE_Master_Developer_Build_Spec.docx Section 11 (7 steps). Amendment Pack Section 5: no human approval.
     * Flow: 1) Re-validate gates 2) GSC+GA4 baseline (fail-soft — never blocks publish) 3) Deploy Shopify then GMC 4) Recompute readiness 5) D+15/D+30 via baseline row 6) logAutoPublish.
     */
    public function publish(Request $request, string $sku_id)
    {
        $sku = Sku::findOrFail($sku_id);
        $user = auth()->user();

        // Step 1: Re-validate all gates (defence in depth).
        // SOURCE: openapi.yaml ValidationResponse — publish 400 must return full gate contract
        // FIX: API-08 — use openapi_validation_body from ValidationService
        $validation = $this->validationService->validate($sku);
        $canPublish = $validation['can_publish'] ?? false;
        if (!$canPublish) {
            $http = (int) ($validation['http_status'] ?? 400);
            $body = $validation['openapi_validation_body'] ?? [
                'status' => ($validation['ai_validation_pending'] ?? false) ? 'pending' : 'fail',
                'gates' => [],
                'vector_check' => ['status' => 'pass', 'user_message' => null],
                'degraded_mode' => (bool) ($validation['ai_validation_pending'] ?? false),
                'save_allowed' => true,
                'publish_allowed' => false,
            ];
            $statusCode = ($http >= 400 && $http < 600) ? $http : 400;
            return response()->json($body, $statusCode);
        }

        // RBAC: role must be permitted to publish.
        if (!$user || !$user->can('publish_sku')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §9.5, §2.7 — baseline capture failure never blocks publish
        $baselineId = $this->baselineService->captureGsc($sku);
        if ($baselineId === null) {
            try {
                AuditLog::create([
                    'entity_type' => 'sku_publish',
                    'entity_id'   => (string) $sku_id,
                    'action'      => 'baseline_capture_failed',
                    'field_name'  => null,
                    'old_value'   => null,
                    'new_value'   => json_encode([
                        'gsc_captured' => false,
                        'ga4_captured' => false,
                        'note' => 'Baseline not captured — CIS unavailable for this change',
                    ], JSON_UNESCAPED_SLASHES),
                    'actor_id'    => (string) ($user->id ?? 'SYSTEM'),
                    'actor_role'  => optional($user->role)->name ?? 'system',
                    'timestamp'   => now(),
                    'user_id'     => $user->id ?? null,
                    'ip_address'  => $request->ip(),
                    'user_agent'  => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('publish: audit_log baseline_capture_failed failed: ' . $e->getMessage());
            }
        } else {
            // Step 3: GA4 baseline into same row.
            $this->baselineService->captureGa4($sku, $baselineId);
            $this->baselineService->updateBaselineContentSnapshot($baselineId, $sku);
        }

        // Step 4: Deploy to Shopify then GMC (N8N webhooks).
        $shopifyResult = $this->channelDeployService->deployToShopify($sku_id);
        $gmcResult = $this->channelDeployService->deployToGMC($sku_id);
        $channelResults = [$shopifyResult, $gmcResult];

        // Step 5: Recompute per-channel readiness scores.
        $readinessScores = $this->readinessScoreService->computeReadiness($sku->fresh());

        // Step 6: D+15 and D+30 measurement jobs — baseline row exists; Python cron (cis_d15_job, cis_d30_job) picks it up. No new endpoint.

        // Step 7: Audit log for auto-publish (INSERT only).
        $this->publishTraceService->logAutoPublish($sku_id, $channelResults, $user->id ?? null);

        // CLAUDE.md §9: semrush_content_snapshots row auto-created when content is published.
        try {
            $now = now();
            DB::table('semrush_content_snapshots')->insert([
                'sku_id'         => $sku_id,
                'import_batch_id' => null,
                'snapshot_date'  => $now,
                'concluded_at'   => null,
                'created_at'     => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('publish: semrush_content_snapshots insert failed: ' . $e->getMessage());
        }

        // SOURCE: openapi.yaml /sku/{sku_id}/publish — 200 response; CLAUDE.md §4 DECISION-001
        return response()->json([
            'status'            => 'published',
            'channels_updated'  => ['shopify', 'gmc'],
            'channels'          => $channelResults,
            'baseline_id'       => $baselineId,
            'readiness_scores'  => $readinessScores,
        ], 200);
    }

    /**
     * SOURCE: Phase 7 fix request — channel deploy callback for readiness refresh.
     * FIX: P7-SKU-01
     */
    public function channelDeployed(string $skuCode, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'channel' => 'required|string',
            'status' => 'required|string',
            'timestamp' => 'required|string',
        ]);

        $sku = Sku::where('sku_code', $skuCode)->first();
        if (!$sku) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(404, 'SKU_NOT_FOUND', 'SKU not found');
        }

        $readiness = $this->readinessScoreService->computeReadiness($sku->fresh());

        AuditLog::create([
            'entity_type' => 'channel_deploy',
            'entity_id' => (string) $sku->id,
            'action' => 'channel_deployed',
            'field_name' => (string) $payload['channel'],
            'old_value' => null,
            'new_value' => json_encode($payload),
            'actor_id' => 'SYSTEM',
            'actor_role' => 'system',
            'timestamp' => now(),
            'user_id' => 'SYSTEM',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'recorded',
            'readiness' => $readiness,
        ], 200);
    }

    /**
     * SOURCE: Phase 7 fix request — channel deploy failure callback audit.
     * FIX: P7-SKU-02
     */
    public function channelFailed(string $skuCode, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'channel' => 'required|string',
            'error' => 'required|string',
            'retry_scheduled' => 'required|boolean',
        ]);

        $sku = Sku::where('sku_code', $skuCode)->first();
        if (!$sku) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(404, 'SKU_NOT_FOUND', 'SKU not found');
        }

        AuditLog::create([
            'entity_type' => 'channel_deploy',
            'entity_id' => (string) $sku->id,
            'action' => 'channel_failed',
            'field_name' => (string) $payload['channel'],
            'old_value' => null,
            'new_value' => json_encode($payload),
            'actor_id' => 'SYSTEM',
            'actor_role' => 'system',
            'timestamp' => now(),
            'user_id' => 'SYSTEM',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        Log::warning('Channel deployment failure recorded', [
            'sku_code' => $skuCode,
            'channel' => $payload['channel'],
            'error' => $payload['error'],
            'retry_scheduled' => (bool) $payload['retry_scheduled'],
        ]);

        return response()->json(['status' => 'failure_recorded'], 200);
    }

    /**
     * GET /api/v1/sku/{id}/readiness — per-channel readiness scores (0-100). Unified API 7.1 / 11.3.
     */
    public function readiness($id) {
        $sku = Sku::findOrFail($id);
        $result = $this->readinessScoreService->computeReadiness($sku);
        return response()->json($result, 200);
    }

    public function stats() {
        $total = Sku::count();
        $byTier = Sku::selectRaw("UPPER(COALESCE(tier, 'SUPPORT')) as tier, COUNT(*) as count")
            ->groupBy(DB::raw("UPPER(COALESCE(tier, 'SUPPORT'))"))
            ->pluck('count', 'tier');

        $validated = Sku::where('validation_status', 'VALID')->count();

        return response()->json([
            'total' => $total,
            'by_tier' => $byTier,
            'validated' => $validated,
        ], 200);
    }

    public function faqSuggestions($id) {
        $sku = Sku::findOrFail($id);
        $suggestions = $this->faqSuggestionService->getSuggestions($sku);
        return response()->json($suggestions, 200);
    }

    public function auditResults($id) {
        $sku = Sku::findOrFail($id);
        $results = [];

        if (Schema::hasTable('audit_results')) {
            $results = DB::table('audit_results')
                ->where('sku_id', $sku->id)
                ->orderByDesc('queried_at')
                ->limit(50)
                ->get()
                ->toArray();
        }

        return response()->json($results, 200);
    }

    /**
     * GET /api/v1/sku/{sku_id}/rollback-content — original content snapshot for rollback (Section 17 Check 9.7).
     * Returns latest baseline_content_snapshot from gsc_baselines for this SKU, or 404 if none.
     */
    public function rollbackContent($sku_id) {
        $sku = Sku::find($sku_id);
        if (!$sku) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(404, 'SKU_NOT_FOUND', 'SKU not found');
        }
        if (!Schema::hasTable('gsc_baselines')) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(404, 'NO_BASELINE_DATA', 'No baseline data');
        }
        $row = DB::table('gsc_baselines')
            ->where('sku_id', $sku_id)
            ->whereNotNull('baseline_content_snapshot')
            ->orderByDesc('id')
            ->first();
        if (!$row || empty($row->baseline_content_snapshot)) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::standardError(404, 'NO_ROLLBACK_CONTENT', 'No rollback content available');
        }
        $snapshot = is_string($row->baseline_content_snapshot)
            ? json_decode($row->baseline_content_snapshot, true)
            : $row->baseline_content_snapshot;
        return response()->json(['content' => $snapshot ?? []], 200);
    }

    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §4.4 / §15
     * FIX: AI-08 — Content pre-fill via Python AI Agent (POST /api/v1/sku/{sku_id}/suggest)
     */
    public function suggest(string $skuId): JsonResponse
    {
        $sku = Sku::with(['primaryCluster', 'skuIntents.intent'])->findOrFail($skuId);

        if ($sku->tier === TierType::KILL) {
            return response()->json([
                'error' => 'AI suggestions unavailable — enter manually.',
                'fields_editable' => false,
            ], 200);
        }

        $primaryIntent = null;
        foreach ($sku->skuIntents as $si) {
            if ($si->is_primary && $si->intent) {
                $primaryIntent = (string) ($si->intent->name ?? '');
                break;
            }
        }

        $cluster = $sku->primaryCluster;
        $skuData = [
            'sku_id' => (string) $sku->id,
            'sku_code' => (string) ($sku->sku_code ?? ''),
            'product_name' => (string) ($sku->title ?? ''),
            'category' => $cluster ? (string) ($cluster->category ?? '') : '',
            'cluster_id' => $sku->primary_cluster_id,
            'cluster_intent' => $cluster ? (string) ($cluster->intent_statement ?? '') : '',
            'primary_intent' => $primaryIntent,
            'tier' => strtolower((string) ($sku->tier instanceof TierType ? $sku->tier->value : $sku->tier)),
            'certifications' => [],
        ];

        $baseUrl = rtrim((string) config('services.python_worker.url', ''), '/');
        if ($baseUrl === '') {
            $baseUrl = 'http://localhost:8000';
        }
        $url = $baseUrl . '/api/v1/sku/suggest';

        try {
            $response = Http::timeout(120)->acceptJson()->post($url, $skuData);

            if ($response->successful()) {
                $suggestion = $response->json();
                if (!is_array($suggestion)) {
                    $suggestion = [];
                }

                // Fail-soft envelope from Python (200 + error field)
                if (isset($suggestion['error'])) {
                    if (Schema::hasTable('ai_agent_logs')) {
                        try {
                            AiAgentLog::create([
                                'sku_id' => (string) $sku->id,
                                'function_called' => 'content_suggest',
                                'prompt_hash' => hash('sha256', (string) json_encode($skuData)),
                                'response_received' => false,
                                'confidence_score' => null,
                                'status' => 'pending',
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('AiAgentLog create failed: ' . $e->getMessage(), ['sku_id' => $sku->id]);
                        }
                    }

                    return response()->json($suggestion, 200);
                }

                if (Schema::hasTable('ai_agent_logs')) {
                    try {
                        AiAgentLog::create([
                            'sku_id' => (string) $sku->id,
                            'function_called' => 'content_suggest',
                            'prompt_hash' => hash('sha256', (string) json_encode($skuData)),
                            'response_received' => true,
                            'confidence_score' => isset($suggestion['confidence_score'])
                                ? (float) $suggestion['confidence_score']
                                : null,
                            'status' => 'pending',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('AiAgentLog create failed: ' . $e->getMessage(), ['sku_id' => $sku->id]);
                    }
                }

                return response()->json($suggestion, 200);
            }

            if (Schema::hasTable('ai_agent_logs')) {
                try {
                    AiAgentLog::create([
                        'sku_id' => (string) $sku->id,
                        'function_called' => 'content_suggest',
                        'prompt_hash' => hash('sha256', (string) json_encode($skuData)),
                        'response_received' => false,
                        'confidence_score' => null,
                        'status' => 'pending',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('AiAgentLog create failed: ' . $e->getMessage(), ['sku_id' => $sku->id]);
                }
            }

            return response()->json([
                'error' => 'AI suggestions unavailable — enter manually.',
                'fields_editable' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::warning('suggest Python call failed: ' . $e->getMessage(), ['sku_id' => $sku->id]);

            return response()->json([
                'error' => 'AI suggestions unavailable — enter manually.',
                'fields_editable' => true,
            ], 200);
        }
    }

    /**
     * SOURCE: openapi.yaml /sku/{sku_id}/suggestions/{suggestion_id}/status
     * FIX: AI-14 — Log accepted/edited/rejected; proxy to Python engine when configured
     */
    public function suggestionStatus(Request $request, string $sku_id, string $suggestion_id): JsonResponse
    {
        $status = (string) $request->input('status', 'seen');
        if (Schema::hasTable('ai_agent_logs')) {
            try {
                $mapped = in_array((string) $status, ['accepted', 'edited'], true)
                    ? (string) $status
                    : 'rejected';
                AiAgentLog::where('sku_id', $sku_id)
                    ->where('function_called', 'content_suggest')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['status' => $mapped]);
            } catch (\Throwable $e) {
                Log::warning('suggestionStatus ai_agent_logs: ' . $e->getMessage());
            }
        }

        $engineBase = rtrim((string) env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $url = $engineBase . '/sku/' . urlencode($sku_id) . '/suggestions/' . urlencode($suggestion_id) . '/status';
        $client = Http::acceptJson();
        $token = env('CIE_ENGINE_TOKEN');
        if (!empty($token)) {
            $client = $client->withToken($token);
        }
        $response = $client->post($url, $request->all());

        return response()->json($response->json(), $response->status());
    }

    /**
     * GET /api/v1/queue/today
     * Returns writer queue rows for My Queue page.
     * SOURCE: CIE_Master_Developer_Build_Spec.docx Section 14.1; openapi.yaml /queue/today (Kill locked)
     * FIX: API-05/11 — include all tiers; Hero/Support sorted by weighted score first, then Harvest, then Kill
     * Thresholds via BusinessRules::get() — no hard-coded values
     */
    public function queueToday(Request $request) {
        $tierFilter = $request->query('tier');
        $q = Sku::query()->select([
            'id', 'sku_code', 'title', 'tier', 'validation_status', 'updated_at',
            'short_description', 'long_description', 'best_for', 'not_for', 'margin_percent',
            'decay_status', 'content_score', 'readiness_score', 'ai_answer_block', 'expert_authority',
        ]);
        if (Schema::hasColumn('skus', 'is_active')) {
            $q->where('is_active', true);
        }
        // SOURCE: openapi.yaml /queue/today; CIE_v232_UI_Restructure_Instructions.docx Step 3
        // Default includes Hero/Support/Harvest/Kill so Kill rows can be returned as locked=true.
        if ($tierFilter !== null && $tierFilter !== '') {
            $q->where('tier', strtolower((string) $tierFilter));
        } else {
            $q->whereIn('tier', ['hero', 'support', 'harvest', 'kill']);
        }
        $candidates = $q->get();

        $amberThreshold = (int) BusinessRules::get('chs.amber_threshold');
        $heroReadinessMin = (int) BusinessRules::get('readiness.hero_primary_channel_min');

        foreach ($candidates as $sku) {
            $score = 0;
            $decayStatus = $sku->decay_status ?? 'none';

            $tierLower = $sku->tier instanceof TierType ? $sku->tier->value : strtolower((string) ($sku->tier ?? ''));

            // SOURCE: CIE_Master_Developer_Build_Spec.docx §14.1 — scoring factors are for Hero/Support candidates only.
            if (in_array($tierLower, ['hero', 'support'], true)) {
                if (in_array($decayStatus, ['auto_brief', 'escalated'], true)) {
                    $score += (int) BusinessRules::get('queue.decay_critical_bonus', 100);
                }
                if ($decayStatus === 'alert') {
                    $score += (int) BusinessRules::get('queue.decay_alert_bonus', 60);
                }
                $chs = (int) ($sku->content_score ?? 0);
                if ($chs < $amberThreshold) {
                    $score += (int) BusinessRules::get('queue.low_chs_bonus', 40);
                }
                if ($tierLower === 'hero') {
                    $readiness = (int) ($sku->readiness_score ?? 0);
                    if ($readiness < $heroReadinessMin) {
                        $score += (int) BusinessRules::get('queue.hero_readiness_gap_bonus', 35);
                    }
                    // SOURCE: CIE_Master_Developer_Build_Spec.docx §14.1
                    // FIX: AI-11 — Hero + no answer_block only (skus.ai_answer_block)
                    $answerBlockEmpty = trim((string) ($sku->ai_answer_block ?? '')) === '';
                    if ($answerBlockEmpty) {
                        $score += (int) BusinessRules::get('queue.hero_missing_answer_bonus', 30);
                    }
                }
                if ($this->hasOpenBrief((string) $sku->id)) {
                    $score += (int) BusinessRules::get('queue.open_brief_bonus', 25);
                }
            } else {
                $score = 0;
            }

            $sku->priority_score = $score;
        }

        $tierGroup = static function (Sku $sku): int {
            $t = $sku->tier instanceof TierType ? $sku->tier->value : strtolower((string) ($sku->tier ?? ''));
            if (in_array($t, ['hero', 'support'], true)) {
                return 0;
            }
            if ($t === 'harvest') {
                return 1;
            }
            if ($t === 'kill') {
                return 2;
            }
            return 3;
        };

        $sorted = $candidates->sort(function (Sku $a, Sku $b) use ($tierGroup) {
            $ga = $tierGroup($a);
            $gb = $tierGroup($b);
            if ($ga !== $gb) {
                return $ga <=> $gb;
            }
            return ($b->priority_score ?? 0) <=> ($a->priority_score ?? 0);
        })->values();
        $top10 = $sorted->take(10);

        $items = $top10->map(function ($sku) {
            $rawStatus = $sku->validation_status;
            if ($rawStatus instanceof ValidationStatus) {
                $status = strtoupper($rawStatus->value);
                $isValid = $rawStatus === ValidationStatus::VALID;
            } else {
                $status = strtoupper((string) ($rawStatus ?? ''));
                $isValid = $status === 'VALID';
            }

            $tier = $sku->tier instanceof \App\Enums\TierType ? strtoupper($sku->tier->value) : strtoupper((string) ($sku->tier ?? ''));
            [$fieldsDone, $fieldsTotal] = $this->computeFieldProgress($sku, $tier);

            return [
                'sku_id' => (string) $sku->id,
                'sku_code' => (string) ($sku->sku_code ?? ''),
                'product_name' => (string) ($sku->title ?? 'Untitled'),
                'tier' => strtolower($tier),
                'validation_status' => strtolower($status),
                'done' => $isValid,
                'priority_score' => (int) ($sku->priority_score ?? 0),
                'missing_fields_count' => max(0, $fieldsTotal - $fieldsDone),
                'ai_suggestion_count' => 0,
                'locked' => strtolower($tier) === 'kill',
                'decay_status' => (string) ($sku->decay_status ?? 'none'),
            ];
        });

        return response()->json(['items' => $items->values()->all()], 200);
    }

    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx Section 14.1 — open content refresh brief
     */
    private function hasOpenBrief(string $skuId): bool {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5
        // FIX: DEC-03 — content_briefs status is lowercase; keep legacy uppercase compatibility.
        return ContentBrief::where('sku_id', $skuId)->whereIn('status', ['open', 'OPEN'])->exists();
    }

    private function addCamelCaseAliases(array $arr): array
    {
        if (array_key_exists('primary_cluster', $arr)) {
            $arr['primaryCluster'] = $arr['primary_cluster'];
        }
        if (array_key_exists('sku_intents', $arr)) {
            $arr['skuIntents'] = $arr['sku_intents'];
        }
        return $arr;
    }

    /**
     * Batch-load canonical gate statuses from sku_gate_status (populated by GateValidator).
     * Returns array keyed by sku_code, each value an array of row objects.
     */
    private function batchLoadGateStatuses(array $skuCodes): array
    {
        if (empty($skuCodes) || !Schema::hasTable('sku_gate_status')) {
            return [];
        }
        try {
            $rows = DB::table('sku_gate_status')
                ->whereIn('sku_id', $skuCodes)
                ->get();
            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row->sku_id][] = $row;
            }
            return $grouped;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Build gate pass/fail map for frontend chips.
     * Uses canonical sku_gate_status when available, otherwise corrected inline fallback.
     */
    private function buildGateStatuses(Sku $sku, ?array $canonicalStatuses = null): array
    {
        $tier = $sku->tier instanceof \App\Enums\TierType
            ? strtoupper($sku->tier->value)
            : strtoupper((string) ($sku->tier ?? ''));

        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3
        // Kill tier: no gates active, submit hidden.
        if (strtolower($tier) === 'kill') {
            return ['_kill_locked' => true];
        }

        // Use canonical only if we have a complete set of full gate codes (G1_BASIC_INFO, etc.).
        // Otherwise (old short codes G1/G2, or partial writes) use fallback so portfolio shows correct chips.
        $requiredFullCodes = [
            'G1_BASIC_INFO', 'G2_INTENT', 'G3_SECONDARY_INTENT', 'G4_ANSWER_BLOCK',
            'G5_BEST_NOT_FOR', 'G5_TECHNICAL', 'G6_COMMERCIAL_POLICY', 'G7_EXPERT', 'G4_VECTOR',
        ];
        if (!empty($canonicalStatuses) && $this->canonicalStatusesHaveRequiredFullCodes($canonicalStatuses, $requiredFullCodes)) {
            return $this->buildGateStatusesFromCanonical($canonicalStatuses, $sku, $tier);
        }
        return $this->buildGateStatusesFallback($sku, $tier);
    }

    /**
     * True only if canonical has (at least) the required full gate codes for a complete display.
     */
    private function canonicalStatusesHaveRequiredFullCodes(array $rows, array $requiredFullCodes): bool
    {
        $codes = [];
        foreach ($rows as $row) {
            $code = is_object($row) ? $row->gate_code : ($row['gate_code'] ?? '');
            if (is_string($code)) {
                $codes[$code] = true;
            }
        }
        $have = 0;
        foreach ($requiredFullCodes as $req) {
            if (!empty($codes[$req])) {
                $have++;
            }
        }
        return $have >= 7; // require at least 7 of 9 for canonical to be used
    }

    /**
     * Map sku_gate_status rows (gate_code → status) to frontend gate keys.
     */
    private function buildGateStatusesFromCanonical(array $rows, Sku $sku, string $tier): array
    {
        $statusMap = [];
        foreach ($rows as $row) {
            $code = is_object($row) ? $row->gate_code : ($row['gate_code'] ?? '');
            $status = is_object($row) ? $row->status : ($row['status'] ?? 'fail');
            $statusMap[$code] = in_array($status, ['pass', 'warn'], true);
        }

        // G5: On success G5_TechnicalGate writes only G5_TECHNICAL=pass (never G5_BEST_NOT_FOR).
        // Treat G5 as passed when either Best-For/Not-For or Technical is pass so UI matches golden.
        $g5Passed = ($statusMap['G5_BEST_NOT_FOR'] ?? false) || ($statusMap['G5_TECHNICAL'] ?? false);
        // G6 = commercial policy (golden); tier_fields same source for Harvest display.
        $g6Passed = $statusMap['G6_COMMERCIAL_POLICY'] ?? $this->tierFieldsComplete($sku, $tier);

        return [
            'G1'          => ['passed' => $statusMap['G1_BASIC_INFO'] ?? false],
            'G2'          => ['passed' => $statusMap['G2_INTENT'] ?? false],
            'G3'          => ['passed' => $statusMap['G3_SECONDARY_INTENT'] ?? false],
            'G4'          => ['passed' => $statusMap['G4_ANSWER_BLOCK'] ?? false],
            'G5'          => ['passed' => $g5Passed],
            'G6'          => ['passed' => $g6Passed],
            'tier_fields' => ['passed' => $g6Passed],
            'G7'          => ['passed' => $statusMap['G7_EXPERT'] ?? false],
            'VEC'         => ['passed' => $statusMap['G4_VECTOR'] ?? false],
        ];
    }

    /**
     * Inline gate checks used when sku_gate_status has no data for this SKU
     * (i.e. before first validation run). Mirrors real gate pipeline logic.
     * SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7
     */
    private function buildGateStatusesFallback(Sku $sku, string $tier): array
    {
        $isSuspended = in_array($tier, ['HARVEST', 'KILL']);

        $hasCluster = !empty($sku->primary_cluster_id);
        if ($sku->relationLoaded('skuIntents')) {
            $primaryIntentCount = $sku->skuIntents->where('is_primary', true)->count();
        } else {
            $primaryIntentCount = DB::table('sku_intents')
                ->where('sku_id', $sku->id)
                ->where('is_primary', true)
                ->count();
        }
        $hasIntents = $isSuspended || $primaryIntentCount > 0;

        $answerBlock = trim((string) ($sku->ai_answer_block ?? ''));
        $answerLen = strlen($answerBlock);
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — no hard-coded fallbacks; seed is single source
        $minChars = (int) BusinessRules::get('gates.answer_block_min_chars');
        $maxChars = (int) BusinessRules::get('gates.answer_block_max_chars');
        $hasAnswerBlock = $isSuspended || ($answerLen >= $minChars && $answerLen <= $maxChars);

        $hasBestNotFor = $isSuspended || $this->checkBestNotForCounts($sku, $tier);

        $hasDescription = $this->hasDescriptionQuality($sku, $tier);

        $hasG7 = $isSuspended || $this->fallbackChannelReadiness($sku, $tier);

        $hasVec = $isSuspended || $hasDescription;

        // G6 = commercial policy (tier fields), aligned with canonical and golden.
        $g6TierPassed = $this->tierFieldsComplete($sku, $tier);

        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 — G2 checks primary intent, not title
        $g2Passed = $tier === 'KILL'
            ? true
            : $this->fallbackG2PrimaryIntentPasses($sku);

        return [
            'G1'          => ['passed' => $hasCluster],
            'G2'          => ['passed' => $g2Passed],
            'G3'          => ['passed' => $hasIntents],
            'G4'          => ['passed' => $hasAnswerBlock],
            'G5'          => ['passed' => $hasBestNotFor],
            'G6'          => ['passed' => $g6TierPassed],
            'tier_fields' => ['passed' => $g6TierPassed],
            'G7'          => ['passed' => $hasG7],
            'VEC'         => ['passed' => $hasVec],
        ];
    }

    private function hasDescriptionQuality(Sku $sku, string $tier): bool
    {
        if ($tier === 'KILL') return true;
        $longDesc = $sku->long_description ?? '';
        if (empty($longDesc)) {
            return false;
        }
        $minWords = (int) BusinessRules::get('gates.description_word_count_min');
        return str_word_count($longDesc) >= $minWords;
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 — G2 = primary intent in locked 9-intent taxonomy
     */
    private function fallbackG2PrimaryIntentPasses(Sku $sku): bool
    {
        $raw = $sku->getAttribute('primary_intent');
        if ($raw !== null && $raw !== '') {
            $norm = strtolower(str_replace([' ', '-', '/'], '_', trim((string) $raw)));
            return $norm !== '' && in_array($norm, IntentTaxonomy::validPrimaryIntents(), true);
        }
        if (!$sku->relationLoaded('skuIntents')) {
            $sku->load(['skuIntents.intent']);
        }
        $primaryNode = $sku->skuIntents->where('is_primary', true)->first();
        if (!$primaryNode || !$primaryNode->intent) {
            return false;
        }
        $intentName = (string) ($primaryNode->intent->name ?? '');
        $norm = strtolower(str_replace([' ', '-', '/'], '_', $intentName));
        if ($norm !== '' && in_array($norm, IntentTaxonomy::validPrimaryIntents(), true)) {
            return true;
        }
        return IntentTaxonomy::query()
            ->whereRaw('LOWER(label) = ?', [strtolower($intentName)])
            ->orWhereRaw('LOWER(intent_key) = ?', [strtolower(str_replace(' ', '_', $intentName))])
            ->exists();
    }

    private function checkBestNotForCounts(Sku $sku, string $tier): bool
    {
        if (!in_array($tier, ['HERO', 'SUPPORT'])) return true;
        $bestFor = self::parseListAttribute($sku->best_for);
        $notFor = self::parseListAttribute($sku->not_for);
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — no hard-coded fallbacks
        $bestForMin = (int) BusinessRules::get('gates.best_for_min_entries');
        $notForMin = (int) BusinessRules::get('gates.not_for_min_entries');
        return count($bestFor) >= $bestForMin && count($notFor) >= $notForMin;
    }

    private static function parseListAttribute($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }
        $raw = $value ?? '';
        if (is_string($raw) && (str_starts_with(trim($raw), '[') || str_starts_with(trim($raw), '{'))) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }
        }
        return array_filter(array_map('trim', explode(',', (string) $raw)));
    }

    private function isValidStatus(Sku $sku): bool
    {
        return ($sku->validation_status instanceof ValidationStatus)
            ? $sku->validation_status === ValidationStatus::VALID
            : strtoupper((string) ($sku->validation_status ?? '')) === 'VALID';
    }

    /**
     * G7 fallback: check channel_readiness scores directly instead of relying
     * on validation_status, so unvalidated SKUs show accurate G7 chips.
     * SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7 — G7 gate logic
     */
    private function fallbackChannelReadiness(Sku $sku, string $tier): bool
    {
        $skuCode = $sku->sku_code ?? '';
        if ($skuCode === '' || !Schema::hasTable('channel_readiness')) {
            return false;
        }

        try {
            $rows = DB::table('channel_readiness')
                ->where('sku_id', $skuCode)
                ->get();
        } catch (\Throwable $e) {
            return false;
        }

        if ($rows->isEmpty()) {
            return false;
        }

        $defaultThreshold = (int) BusinessRules::get('readiness.hero_primary_channel_min');
        if ($tier === 'HERO') {
            return $rows->every(function ($row) use ($defaultThreshold) {
                $channel = strtolower($row->channel ?? 'shopify');
                $threshold = (int) (BusinessRules::get(
                    "channel.{$channel}_readiness_threshold",
                    $defaultThreshold
                ) ?? $defaultThreshold);
                return ($row->score ?? 0) >= $threshold;
            });
        }

        $primary = $rows->first();
        $channel = strtolower($primary->channel ?? 'shopify');
        $threshold = (int) (BusinessRules::get(
            "channel.{$channel}_readiness_threshold",
            $defaultThreshold
        ) ?? $defaultThreshold);
        return ($primary->score ?? 0) >= $threshold;
    }

    private function tierFieldsComplete(Sku $sku, string $tier): bool
    {
        switch ($tier) {
            case 'HERO':
                return !empty($sku->title)
                    && !empty($sku->ai_answer_block ?? $sku->short_description)
                    && !empty($sku->best_for)
                    && !empty($sku->not_for)
                    && !empty($sku->long_description);
            case 'SUPPORT':
                return !empty($sku->title)
                    && !empty($sku->ai_answer_block ?? $sku->short_description)
                    && !empty($sku->best_for)
                    && !empty($sku->not_for);
            case 'HARVEST':
                return !empty($sku->long_description);
            case 'KILL':
                return true;
            default:
                return true;
        }
    }

    private function deriveVectorGateStatus(Sku $sku): ?string
    {
        if ($this->isValidStatus($sku)) return 'pass';

        $isDegraded = ($sku->validation_status instanceof ValidationStatus)
            ? $sku->validation_status === ValidationStatus::DEGRADED
            : strtoupper((string) ($sku->validation_status ?? '')) === 'DEGRADED';

        if ($isDegraded) return 'pending';

        $tier = $sku->tier instanceof \App\Enums\TierType
            ? strtoupper($sku->tier->value)
            : strtoupper((string) ($sku->tier ?? ''));
        if (in_array($tier, ['HARVEST', 'KILL'])) return 'pass';

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5 + §7 (G6) — configurable minimum description words.
        $hasContent = !empty($sku->long_description) && str_word_count($sku->long_description ?? '') >= (int) BusinessRules::get('gates.description_min_words');
        return $hasContent ? null : 'fail';
    }

    private function decodeFaqs(Sku $sku): array
    {
        $raw = $sku->faq_data;
        if (empty($raw)) return [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }

    private function loadHistory(Sku $sku): array
    {
        try {
            if (!Schema::hasTable('audit_log')) return [];

            return DB::table('audit_log')
                ->where('entity_type', 'sku')
                ->where('entity_id', (string) $sku->id)
                ->orderByDesc('timestamp')
                ->limit(50)
                ->get()
                ->map(function ($entry) {
                    $userName = 'System';
                    if (!empty($entry->user_id)) {
                        $user = DB::table('users')->where('id', $entry->user_id)->first();
                        if ($user) {
                            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? 'System');
                        }
                    }
                    return [
                        'user_name'  => $userName,
                        'created_at' => $entry->timestamp ?? $entry->created_at ?? null,
                        'action'     => $entry->action ?? 'update',
                        'field_name' => $entry->field_name ?? null,
                        'old_value'  => $entry->old_value ?? null,
                        'new_value'  => $entry->new_value ?? null,
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function computeFieldProgress(Sku $sku, string $tier): array
    {
        switch ($tier) {
            case 'HERO':
                $required = ['title', 'short_description', 'long_description', 'best_for', 'not_for', 'expert_authority'];
                break;
            case 'SUPPORT':
                $required = ['title', 'short_description', 'long_description', 'best_for', 'not_for'];
                break;
            case 'HARVEST':
                $required = ['title', 'long_description'];
                break;
            default:
                return [0, 0];
        }

        $total = count($required);
        $done = 0;
        foreach ($required as $field) {
            $val = $sku->getAttribute($field);
            if ($field === 'short_description') {
                $val = $val ?? $sku->getAttribute('ai_answer_block');
            }
            if (!empty($val)) $done++;
        }

        return [$done, $total];
    }
}
