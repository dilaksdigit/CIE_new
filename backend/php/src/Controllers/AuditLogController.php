<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuditLogController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('audit_log')) {
            return ResponseFormatter::format([]);
        }

        $query = AuditLog::orderByDesc('timestamp');

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        $limit = min((int) $request->query('limit', 100), 500);
        $logs = $query->limit($limit)->get();

        return ResponseFormatter::format($logs);
    }
}
