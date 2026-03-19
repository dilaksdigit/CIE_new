<?php
namespace App\Controllers;

use App\Services\ValidationService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        return $this->validate($id);
    }

    public function validate($id) {
        try {
            $result = $this->service->validateSku($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'SKU not found', 'sku_id' => $id], 404);
        }

        // Log validation action
        AuditLog::create([
            'entity_type' => 'sku',
            'entity_id'   => $id,
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

        $httpStatus = $result['http_status'] ?? 200;
        unset($result['http_status']);
        return ResponseFormatter::format($result, $result['valid'] ? 'Success' : 'Validation failed', $httpStatus);
    }
}
