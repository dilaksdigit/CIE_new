<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

class AuditLogController
{
    /**
     * GET /api/audit-logs
     * Returns paginated audit log entries with optional filters.
     *
     * Query params:
     *   sku    — filter by entity_id (SKU code or ID)
     *   user   — filter by actor_id
     *   action — filter by action type
     *   per_page — number of results per page (default 50)
     */
    public function index(Request $request)
    {
        $q = AuditLog::query()->orderBy('timestamp', 'desc');

        if ($request->filled('sku')) {
            $q->where('entity_id', $request->input('sku'));
        }
        if ($request->filled('user')) {
            $q->where('actor_id', $request->input('user'));
        }
        if ($request->filled('action')) {
            $q->where('action', $request->input('action'));
        }

        $perPage = max(1, min(200, $request->integer('per_page', 50)));
        $logs = $q->paginate($perPage);

        return ResponseFormatter::format($logs);
    }
}
