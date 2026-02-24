<?php
namespace App\Controllers;

use App\Services\ValidationService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use App\Models\AuditLog;

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
        if (!$id) {
            return response()->json(['error' => 'sku_id or id required in body'], 400);
        }
        return $this->validate($id);
    }

    public function validate($id) {
        $result = $this->service->validateSku($id);

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
