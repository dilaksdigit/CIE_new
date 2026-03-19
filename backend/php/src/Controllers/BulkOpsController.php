<?php

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin bulk operations: summary (counts, operation list, max SKU limit) and execution endpoints.
 * Zero hardcoded counts or labels in frontend — all from this API.
 */
class BulkOpsController
{
    /** Operation definitions: id, label, description, icon, count_key (null = no count badge). */
    private const OPERATIONS = [
        [
            'id' => 'tier_reassignment',
            'label' => 'Bulk Tier Reassignment',
            'description' => 'Apply ERP sync tier changes to multiple SKUs',
            'icon' => '▦',
            'count_key' => 'tier_reassignment_pending',
        ],
        [
            'id' => 'cluster_assignment',
            'label' => 'Bulk Cluster Assignment',
            'description' => 'Move SKUs between semantic clusters',
            'icon' => '⬡',
            'count_key' => null,
        ],
        [
            'id' => 'status_change',
            'label' => 'Bulk Status Change',
            'description' => 'Draft → Active, Active → Archived',
            'icon' => '⊞',
            'count_key' => null,
        ],
        [
            'id' => 'faq_template',
            'label' => 'FAQ Template Application',
            'description' => 'Apply FAQ templates to category SKUs',
            'icon' => '📋',
            'count_key' => 'faq_template_pending',
        ],
        [
            'id' => 'csv_import',
            'label' => 'CSV Import',
            'description' => 'Import SKU data from spreadsheet',
            'icon' => '↓',
            'count_key' => null,
        ],
        [
            'id' => 'csv_export',
            'label' => 'CSV Export',
            'description' => 'Export current SKU data for analysis',
            'icon' => '↑',
            'count_key' => null,
        ],
    ];

    /**
     * GET /api/v1/admin/bulk-ops/summary
     * Returns operations list, live counts, and max_skus_per_operation from config.
     */
    public function summary()
    {
        $counts = $this->computeCounts();
        try {
            $maxSkus = (int) BusinessRules::get('bulk_ops.max_skus_per_operation', 500);
        } catch (\Throwable $e) {
            $maxSkus = 500;
        }
        $operations = array_map(function ($op) use ($counts) {
            $countKey = $op['count_key'] ?? null;
            $count = $countKey !== null ? ($counts[$countKey] ?? 0) : null;
            return [
                'id' => $op['id'],
                'label' => $op['label'],
                'description' => $op['description'],
                'icon' => $op['icon'],
                'count' => $count,
            ];
        }, self::OPERATIONS);

        return ResponseFormatter::format([
            'operations' => $operations,
            'counts' => $counts,
            'max_skus_per_operation' => $maxSkus,
        ]);
    }

    /** Live counts for tier reassignment pending and FAQ template pending. */
    private function computeCounts(): array
    {
        $tierReassignmentPending = 0;
        $faqTemplatePending = 0;

        if (Schema::hasTable('tier_change_requests')) {
            $tierReassignmentPending = DB::table('tier_change_requests')
                ->whereIn('status', ['pending_portfolio_approval', 'pending_finance_approval'])
                ->count();
        }

        if (Schema::hasTable('skus') && Schema::hasTable('faq_templates') && Schema::hasTable('sku_faq_responses')) {
            $faqTemplatePending = DB::table('skus')
                ->where('skus.tier', 'hero')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('faq_templates as ft')
                        ->where('ft.is_required', true)
                        ->whereNotExists(function ($q2) {
                            $q2->select(DB::raw(1))
                                ->from('sku_faq_responses as r')
                                ->whereColumn('r.sku_id', 'skus.id')
                                ->whereColumn('r.template_id', 'ft.id')
                                ->whereNotNull('r.answer')
                                ->where('r.answer', '!=', '');
                        });
                })
                ->count();
        }

        return [
            'tier_reassignment_pending' => $tierReassignmentPending,
            'faq_template_pending' => $faqTemplatePending,
        ];
    }

    /**
     * GET /api/v1/admin/bulk-ops/tier-change-requests
     * List pending tier change requests for bulk approval flow.
     */
    public function listTierChangeRequests(Request $request)
    {
        if (!Schema::hasTable('tier_change_requests')) {
            return ResponseFormatter::format(['requests' => [], 'total' => 0]);
        }
        $status = $request->query('status');
        $query = DB::table('tier_change_requests as tcr')
            ->join('skus as s', 's.id', '=', 'tcr.sku_id')
            ->select('tcr.id', 'tcr.sku_id', 's.sku_code', 'tcr.requested_tier', 'tcr.status', 'tcr.created_at')
            ->whereIn('tcr.status', ['pending_portfolio_approval', 'pending_finance_approval']);
        if ($status) {
            $query->where('tcr.status', $status);
        }
        $requests = $query->orderBy('tcr.created_at')->get();
        return ResponseFormatter::format([
            'requests' => $requests,
            'total' => $requests->count(),
        ]);
    }

    /**
     * POST /api/v1/admin/bulk-ops/cluster-assignment
     * Body: { sku_ids: string[], cluster_id: string }
     */
    public function clusterAssignment(Request $request)
    {
        $data = $request->validate([
            'sku_ids' => 'required|array',
            'sku_ids.*' => 'required|string',
            'cluster_id' => 'required|string',
        ]);
        try {
            $maxSkus = (int) BusinessRules::get('bulk_ops.max_skus_per_operation', 500);
        } catch (\Throwable $e) {
            $maxSkus = 500;
        }
        $skuIds = array_slice(array_unique($data['sku_ids']), 0, $maxSkus);
        $updated = DB::table('skus')->whereIn('id', $skuIds)->update(['primary_cluster_id' => $data['cluster_id']]);
        return ResponseFormatter::format(['updated' => $updated, 'sku_ids' => $skuIds]);
    }

    /**
     * POST /api/v1/admin/bulk-ops/status-change
     * Body: { sku_ids: string[], validation_status: 'DRAFT'|'PENDING'|'VALID'|'INVALID'|'DEGRADED' }
     */
    public function statusChange(Request $request)
    {
        $data = $request->validate([
            'sku_ids' => 'required|array',
            'sku_ids.*' => 'required|string',
            'validation_status' => 'required|string|in:DRAFT,PENDING,VALID,INVALID,DEGRADED',
        ]);
        try {
            $maxSkus = (int) BusinessRules::get('bulk_ops.max_skus_per_operation', 500);
        } catch (\Throwable $e) {
            $maxSkus = 500;
        }
        $skuIds = array_slice(array_unique($data['sku_ids']), 0, $maxSkus);
        $updated = DB::table('skus')->whereIn('id', $skuIds)->update(['validation_status' => $data['validation_status']]);
        return ResponseFormatter::format(['updated' => $updated, 'sku_ids' => $skuIds]);
    }

    /**
     * POST /api/v1/admin/bulk-ops/faq-apply
     * Body: { template_id: string, sku_ids: string[] } — ensure response rows exist for template on each SKU.
     */
    public function faqApply(Request $request)
    {
        $data = $request->validate([
            'template_id' => 'required|string',
            'sku_ids' => 'required|array',
            'sku_ids.*' => 'required|string',
        ]);
        try {
            $maxSkus = (int) BusinessRules::get('bulk_ops.max_skus_per_operation', 500);
        } catch (\Throwable $e) {
            $maxSkus = 500;
        }
        $skuIds = array_slice(array_unique($data['sku_ids']), 0, $maxSkus);
        $templateId = $data['template_id'];
        $inserted = 0;
        $now = now()->format('Y-m-d H:i:s');
        foreach ($skuIds as $skuId) {
            $exists = DB::table('sku_faq_responses')->where('sku_id', $skuId)->where('template_id', $templateId)->exists();
            if (!$exists) {
                DB::table('sku_faq_responses')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'sku_id' => $skuId,
                    'template_id' => $templateId,
                    'answer' => '',
                    'approved' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }
        return ResponseFormatter::format(['applied' => $inserted, 'sku_ids' => $skuIds]);
    }

    /**
     * GET /api/v1/admin/bulk-ops/export?format=csv
     * Export SKU list as CSV (id, sku_code, tier, validation_status, primary_cluster_id).
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $rows = DB::table('skus')->select('id', 'sku_code', 'tier', 'validation_status', 'primary_cluster_id')->get();
        if ($format === 'csv') {
            $csv = "id,sku_code,tier,validation_status,primary_cluster_id\n";
            foreach ($rows as $r) {
                $csv .= implode(',', [
                    '"' . str_replace('"', '""', $r->id ?? '') . '"',
                    '"' . str_replace('"', '""', $r->sku_code ?? '') . '"',
                    '"' . str_replace('"', '""', $r->tier ?? '') . '"',
                    '"' . str_replace('"', '""', $r->validation_status ?? '') . '"',
                    '"' . str_replace('"', '""', $r->primary_cluster_id ?? '') . '"',
                ]) . "\n";
            }
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="sku-export-' . date('Y-m-d-His') . '.csv"',
            ]);
        }
        return ResponseFormatter::format($rows->toArray());
    }
}
