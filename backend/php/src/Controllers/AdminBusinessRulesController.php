<?php

namespace App\Controllers;

use App\Models\BusinessRule;
use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CIE v2.3.2 – Admin API for business_rules table (Phase 0 Check 0.1).
 * All endpoints require admin role. No hard-coded values.
 */
class AdminBusinessRulesController
{
    /**
     * GET /api/admin/business-rules — all rows from business_rules, optional filter by module.
     */
    public function index(Request $request)
    {
        $query = DB::table('business_rules');
        $module = $request->query('module');
        if ($module !== null && $module !== '') {
            $query->where('rule_key', 'like', $module . '.%');
        }
        $rows = $query->orderBy('rule_key')->get();
        $data = $rows->map(function ($row) {
            $module = strpos($row->rule_key, '.') !== false ? explode('.', $row->rule_key)[0] : null;
            return [
                'rule_key' => $row->rule_key,
                'label' => $row->description,
                'rule_value' => $row->value,
                'data_type' => $row->value_type,
                'module' => $module,
                'unit' => null,
                'approval_level' => null,
                'last_changed_at' => $row->updated_at,
            ];
        });
        return ResponseFormatter::format($data->values()->all());
    }

    /**
     * PUT /api/admin/business-rules/{key} — update value for rule_key, invalidate cache.
     * Returns 202 for dual-approval rules, 200 otherwise. (Schema has no approval_level; always 200.)
     */
    public function update(Request $request, string $key)
    {
        $key = str_replace(['/', '\\'], '.', $key);
        $rule = BusinessRule::where('rule_key', $key)->first();
        if (!$rule) {
            return ResponseFormatter::error('Rule not found', 404);
        }
        $value = $request->input('value');
        if ($value === null && !$request->has('value')) {
            return ResponseFormatter::error('value is required', 400);
        }
        $rule->value = (string) $value;
        $rule->save();
        BusinessRules::invalidateCache();
        $approvalLevel = null;
        $status = 200;
        if ($approvalLevel === 'dual') {
            $status = 202;
        }
        return ResponseFormatter::format([
            'rule_key' => $rule->rule_key,
            'rule_value' => $rule->value,
            'approval_level' => $approvalLevel,
            'pending_second_approval' => $status === 202,
        ], 'Success', $status);
    }

    /**
     * POST /api/admin/business-rules/{key}/approve — second approver confirms (no-op when no dual-approval).
     */
    public function approve(string $key)
    {
        $key = str_replace(['/', '\\'], '.', $key);
        $rule = BusinessRule::where('rule_key', $key)->first();
        if (!$rule) {
            return ResponseFormatter::error('Rule not found', 404);
        }
        return ResponseFormatter::format(['rule_key' => $rule->rule_key, 'approved' => true]);
    }

    /**
     * GET /api/admin/business-rules/audit — all rows from business_rules_audit.
     */
    public function audit()
    {
        if (!Schema::hasTable('business_rules_audit')) {
            return ResponseFormatter::format([]);
        }
        $rows = DB::table('business_rules_audit')->orderByDesc('changed_at')->get();
        $data = $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'rule_key' => $row->rule_key,
                'old_value' => $row->old_value,
                'new_value' => $row->new_value,
                'changed_at' => $row->changed_at,
                'changed_by' => $row->changed_by,
            ];
        });
        return ResponseFormatter::format($data->values()->all());
    }
}
