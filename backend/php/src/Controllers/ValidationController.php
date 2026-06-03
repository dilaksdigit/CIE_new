<?php
// SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 7.2
namespace App\Controllers;

use App\Services\ValidationService;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ValidationController {
    protected $service;

    public function __construct(ValidationService $service) {
        $this->service = $service;
    }

    /**
     * POST /api/v1/sku/validate — spec path. Request body: { "sku_id": "uuid" } or { "id": "uuid" }.
     */
    public function validateByPayload(Request $request)
    {
        $id = $request->input('sku_id') ?? $request->input('id');
        if (!$id && $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $id = $decoded['sku_id'] ?? $decoded['id'] ?? null;
            }
        }
        if (!$id) {
            return response()->json(['error' => 'sku_id or id required in body'], 400);
        }
        return $this->validate($request, $id);
    }

    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7.2, openapi.yaml SkuValidateRequest — merge JSON body over persisted SKU for live draft validation
    public function validate(Request $request, string $sku_id) {
        $httpTimeout = max(60, (int) config('services.python_worker.validate_timeout_seconds', 120));
        if (function_exists('set_time_limit')) {
            @set_time_limit($httpTimeout + 30);
        }
        try {
            $payload = $request->validate([
                'cluster_id' => 'sometimes|nullable|string',
                'tier' => 'sometimes|nullable|string',
                'primary_intent' => 'sometimes|nullable',
                'secondary_intents' => 'sometimes|array',
                'secondary_intents.*' => 'sometimes|nullable|string',
                'title' => 'sometimes|nullable|string',
                'description' => 'sometimes|nullable|string',
                'answer_block' => 'sometimes|nullable|string',
                'best_for' => 'sometimes|array',
                'best_for.*' => 'sometimes|nullable|string',
                'not_for' => 'sometimes|array',
                'not_for.*' => 'sometimes|nullable|string',
                'expert_authority' => 'sometimes|nullable|string',
                'action' => 'sometimes|nullable|string|in:save,publish',
            ]);
            $result = $this->service->validateSku($sku_id, $payload);
            if (!empty($result['kill_blocked'])) {
                return response()->json([
                    'error' => 'Kill-tier SKU — content editing is blocked.',
                    'code' => 'KILL_TIER_BLOCKED',
                ], 403);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'SKU not found', 'sku_id' => $sku_id], 404);
        }

        // Log validation action (non-blocking — validation result must still return)
        try {
            AuditLog::create([
                'entity_type' => 'sku',
                'entity_id'   => $sku_id,
                'action'      => 'validate',
                'field_name'  => null,
                'old_value'   => null,
                'new_value'   => json_encode([
                    'status'   => $result['status'] ?? null,
                    'valid'    => $result['valid'] ?? null,
                ]),
                'actor_id'    => auth()->id() ?? 'SYSTEM',
                'actor_role'  => auth()->user()->role->name ?? 'system',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'timestamp'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ValidationController audit_log: ' . $e->getMessage());
        }

        $httpStatus = $result['http_status'] ?? 200;
        unset($result['http_status']);
        // SOURCE: openapi.yaml ValidationResponse, ENF§7.2 — validate returns unwrapped body at JSON root (not ResponseFormatter envelope)
        $body = $result['openapi_validation_body'] ?? [];
        unset($result['openapi_validation_body']);

        return response()->json($body, $httpStatus);
    }
}
