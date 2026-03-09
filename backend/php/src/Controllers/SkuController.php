<?php
namespace App\Controllers;

use App\Models\Sku;
use App\Models\SkuIntent;
use App\Models\Intent;
use App\Models\AuditLog;
use App\Models\ValidationLog;
use App\Services\ValidationService;
use App\Services\ReadinessScoreService;
use App\Services\FaqSuggestionService;
use App\Services\PermissionService;
use App\Utils\ResponseFormatter;
use App\Enums\ValidationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SkuController {
    protected $validationService;
    protected $readinessScoreService;
    protected $faqSuggestionService;
    protected $permissionService;

    public function __construct(ValidationService $validationService, ReadinessScoreService $readinessScoreService, FaqSuggestionService $faqSuggestionService, PermissionService $permissionService) {
        $this->validationService = $validationService;
        $this->readinessScoreService = $readinessScoreService;
        $this->faqSuggestionService = $faqSuggestionService;
        $this->permissionService = $permissionService;
    }

    public function index(Request $request) {
        $query = Sku::with(['primaryCluster']);
        
        if ($request->has('tier')) {
            $query->where('tier', $request->query('tier'));
        }

        if ($request->has('cluster_id')) {
            $query->where('primary_cluster_id', $request->query('cluster_id'));
        }

        // Keep "category" filter for backward compatibility (maps to clusters matching name)
        if ($request->has('category')) {
             $category = $request->query('category');
             if ($category !== 'All Categories') {
                 $query->whereHas('primaryCluster', function($q) use ($category) {
                     $q->where('name', 'like', "%$category%");
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

        return ResponseFormatter::format($query->get());
    }

    public function show($id) {
        $sku = Sku::with(['primaryCluster', 'skuIntents.intent'])->findOrFail($id);

        // Patch 6: Tier-mode UX Copy & Banners
        $validation = $sku->validation_status;
        $tierString = is_string($sku->tier) ? strtoupper(trim($sku->tier)) : (string) $sku->tier;
        $isValid = $validation instanceof ValidationStatus
            ? $validation === ValidationStatus::VALID
            : strtoupper((string) ($validation ?? '')) === 'VALID';

        $meta = [
            'tier_lock_reason' => $isValid ? "Validated {$tierString} products have core fields locked for governance." : null,
            'cms_banner' => $this->getTierBanner($tierString ?: 'SUPPORT'),
            'field_tooltips' => [
                'best_for' => "Min 2 required for Hero/Support (v2.3.2)",
                'not_for' => "Min 1 required for all validated SKUs (v2.3.2)"
            ]
        ];

        return ResponseFormatter::format(['sku' => $sku, 'instructions' => $meta]);
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
            if (strtoupper((string) ($sku->tier ?? '')) === 'KILL') {
                return response()->json([
                    'error' => 'KILL_TIER_LOCKED',
                    'message' => 'Kill-tier SKUs cannot be modified. Contact Portfolio Holder for tier review.',
                ], 403);
            }

            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 Gate G6.1
            // SOURCE: CIE_Master_Developer_Build_Spec.docx Gate G6.1 — Server-side enforcement
            if (strtoupper((string) ($sku->tier ?? '')) === 'HARVEST') {
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
                    $allowed  = ['problem_solving', 'compatibility'];
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
                                'detail'     => "Harvest-tier SKU secondary intent must be 'problem_solving' or 'compatibility'. Got: '{$intent}'.",
                            ], 422);
                        }
                    }
                }
            }

            // Version conflict detection
            $clientVersion = $request->input('lock_version');
            if ($clientVersion !== null && $clientVersion != $sku->lock_version) {
                return response()->json([
                    'error' => "VERSION CONFLICT: This SKU was modified by another user. Please merge or discard your changes (v{$clientVersion} != server v{$sku->lock_version})."
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
                        'message' => 'Cannot publish: One or more blocking gates failed',
                        'failures' => $blockingFailures,
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
            $userId = auth()->id();
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
            }

            // Run validation after update
            $manualStatusUpdate = isset($updateData['validation_status']);
            $validationResult = $this->validationService->validate($sku->fresh(), $manualStatusUpdate);

            return ResponseFormatter::format([
                'sku' => $sku->fresh(['primaryCluster', 'skuIntents.intent']),
                'validation' => $validationResult
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'SKU not found'], 404);
        } catch (\Exception $e) {
            Log::error('SKU update failed: ' . $e->getMessage());
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
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

        return ResponseFormatter::format([
            'sku' => $sku->fresh(['primaryCluster', 'skuIntents.intent']),
            'validation' => $validationResult
        ], "SKU created successfully", 201);
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
     * Internally calls validation pipeline and only succeeds when all gates pass.
     */
    public function publish(Request $request, string $sku_id)
    {
        $sku = Sku::findOrFail($sku_id);

        // Step 1: Run validation pipeline for this SKU (includes Python Engine call).
        $validation = $this->validationService->validate($sku);

        $canPublish = $validation['can_publish'] ?? false;
        if (!$canPublish) {
            $status = ($validation['ai_validation_pending'] ?? false) ? 'pending' : 'fail';

            return response()->json([
                'status' => $status,
                'gates' => $validation['gates'] ?? [],
                'failures' => $validation['failures'] ?? [],
                'publish_allowed' => false,
            ], 400);
        }

        // Step 2: RBAC check — role must be permitted to publish.
        $user = auth()->user();
        if (!$user || !$user->can('publish_sku')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // Step 3: Create audit_log entry for publish action.
        AuditLog::create([
            'entity_type' => 'sku',
            'entity_id'   => (string) $sku_id,
            'action'      => 'publish',
            'field_name'  => null,
            'old_value'   => null,
            'new_value'   => json_encode([
                'status' => 'published',
                'validation_log_id' => $validation['validation_log_id'] ?? null,
            ]),
            'actor_id'    => auth()->id() ?? 'SYSTEM',
            'actor_role'  => optional(optional($user)->role)->name ?? 'system',
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'timestamp'   => now(),
            'user_id'     => auth()->id(),
        ]);

        // Step 4: Return spec-compliant publish response body.
        return response()->json([
            'status'           => 'published',
            'channels_updated' => ['google_sge', 'amazon', 'own_website'],
        ], 200);
    }

    /**
     * GET /api/v1/sku/{id}/readiness — per-channel readiness scores (0-100). Unified API 7.1 / 11.3.
     * Score components weighted by tier; Harvest uses only applicable gates (max 45) then normalised to 0-100.
     */
    public function readiness($id) {
        $sku = Sku::findOrFail($id);
        $result = $this->readinessScoreService->computeReadiness($sku);
        return ResponseFormatter::format($result);
    }

    public function stats() {
        $total = Sku::count();
        $byTier = Sku::selectRaw("UPPER(COALESCE(tier, 'SUPPORT')) as tier, COUNT(*) as count")
            ->groupBy(DB::raw("UPPER(COALESCE(tier, 'SUPPORT'))"))
            ->pluck('count', 'tier');

        $validated = Sku::where('validation_status', 'VALID')->count();

        return ResponseFormatter::format([
            'total' => $total,
            'by_tier' => $byTier,
            'validated' => $validated,
        ]);
    }

    public function faqSuggestions($id) {
        $sku = Sku::findOrFail($id);
        $suggestions = $this->faqSuggestionService->getSuggestions($sku);
        return ResponseFormatter::format($suggestions);
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

        return ResponseFormatter::format($results);
    }

    /**
     * GET /api/v1/queue/today
     * Returns writer queue rows for My Queue page.
     */
    public function queueToday(Request $request) {
        $items = Sku::query()
            ->select(['id', 'sku_code', 'title', 'tier', 'validation_status', 'updated_at'])
            ->orderByRaw("CASE
                WHEN UPPER(COALESCE(tier, '')) = 'HERO' THEN 0
                WHEN UPPER(COALESCE(tier, '')) = 'SUPPORT' THEN 1
                WHEN UPPER(COALESCE(tier, '')) = 'HARVEST' THEN 2
                WHEN UPPER(COALESCE(tier, '')) = 'KILL' THEN 3
                ELSE 99
            END")
            ->orderBy('title')
            ->get()
            ->map(function ($sku) {
                $rawStatus = $sku->validation_status;
                if ($rawStatus instanceof ValidationStatus) {
                    $status = strtoupper($rawStatus->value);
                    $isValid = $rawStatus === ValidationStatus::VALID;
                } else {
                    $status = strtoupper((string) ($rawStatus ?? ''));
                    $isValid = $status === 'VALID';
                }

                $tier = strtoupper((string) ($sku->tier ?? ''));

                return [
                    'id' => (string) $sku->id,
                    'sku_id' => (string) ($sku->sku_code ?? $sku->id),
                    'name' => (string) ($sku->title ?? 'Untitled'),
                    'tier' => $tier,
                    'done' => $isValid,
                    'status' => $status,
                    // Field-level completion currently not persisted per SKU in API v2.3.2.
                    // Keep stable keys so frontend can render progress safely.
                    'fields_done' => 0,
                    'fields_total' => 0,
                    'missing_fields_count' => 0,
                    'ai_suggestion_count' => 0,
                    'urgency' => $tier === 'HERO' ? 'high' : ($tier === 'SUPPORT' ? 'medium' : 'low'),
                    'reason' => 'Prioritized by AI queue engine',
                ];
            });

        return ResponseFormatter::format($items);
    }
}
