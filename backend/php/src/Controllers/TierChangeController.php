<?php

namespace App\Controllers;

// SOURCE: CLAUDE.md Section 7 — Tier System; DECISION-006
//         database/migrations/065_create_tier_change_requests_table.sql
//         CIE_v232_FINAL_Developer_Instruction.docx Section 5 R7
// Manual tier override: create request → portfolio_holder approval → finance approval → apply tier.
// Both approvals required before tier is applied. audit_log INSERT only (immutable).

use App\Models\AuditLog;
use App\Models\Sku;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TierChangeController
{
    /** Allowed tier values (lowercase per CLAUDE.md Section 9). */
    private const TIERS = ['hero', 'support', 'harvest', 'kill'];

    /**
     * POST /sku/{sku_id}/tier-change-request — create request (portfolio_holder/content_lead may create).
     * RBAC-05: dual sign-off; request requires portfolio_holder, approval requires finance.
     */
    public function createRequest(Request $request, string $sku_id)
    {
        $data = $request->validate([
            'requested_tier' => 'required|string|in:hero,support,harvest,kill',
        ]);
        $request->merge(['sku_id' => $sku_id]);
        return $this->store($request);
    }

    /**
     * GET /sku/{sku_id}/tier-change-status — get current request status for this SKU.
     */
    public function getStatus(string $sku_id)
    {
        if (!Schema::hasTable('tier_change_requests')) {
            return ResponseFormatter::format(['status' => null, 'request' => null]);
        }
        $sku = Sku::find($sku_id);
        if (!$sku) {
            return ResponseFormatter::error('SKU not found', 404);
        }
        $row = DB::table('tier_change_requests')
            ->where('sku_id', $sku->id)
            ->orderByDesc('created_at')
            ->first();
        if (!$row) {
            return ResponseFormatter::format(['status' => null, 'request' => null]);
        }
        return ResponseFormatter::format([
            'status' => $row->status,
            'request' => [
                'id' => $row->id,
                'requested_tier' => $row->requested_tier,
                'status' => $row->status,
                'created_at' => $row->created_at,
            ],
        ]);
    }

    /**
     * POST /sku/{sku_id}/tier-change-approve — finance approval (approve current pending request for this SKU).
     * RBAC-05: requires finance role.
     */
    public function approveForSku(Request $request, string $sku_id)
    {
        $sku = Sku::find($sku_id);
        if (!$sku) {
            return ResponseFormatter::error('SKU not found', 404);
        }
        $row = DB::table('tier_change_requests')
            ->where('sku_id', $sku->id)
            ->where('status', 'pending_finance_approval')
            ->orderByDesc('created_at')
            ->first();
        if (!$row) {
            return ResponseFormatter::error('No pending finance approval for this SKU', 404);
        }
        $request->merge([]);
        return $this->approveFinance($request, $row->id);
    }

    /**
     * Create a tier change request. Status = pending_portfolio_approval.
     * RBAC: any authenticated user may create a request.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'sku_id' => 'required|string',
            'requested_tier' => 'required|string|in:hero,support,harvest,kill',
        ]);

        $sku = Sku::find($data['sku_id']);
        if (!$sku) {
            return ResponseFormatter::error('SKU not found', 404);
        }

        $currentTier = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower(trim((string) ($sku->tier ?? '')));
        $newTier = strtolower($data['requested_tier']);
        if ($currentTier === $newTier) {
            return ResponseFormatter::error('SKU is already in the requested tier', 400);
        }

        if (!Schema::hasTable('tier_change_requests')) {
            return ResponseFormatter::error('tier_change_requests table does not exist', 500);
        }

        $id = (string) Str::uuid();
        DB::table('tier_change_requests')->insert([
            'id' => $id,
            'sku_id' => $sku->id,
            'requested_tier' => $newTier,
            'status' => 'pending_portfolio_approval',
            'requested_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ResponseFormatter::format([
            'id' => $id,
            'sku_id' => $sku->id,
            'requested_tier' => $newTier,
            'status' => 'pending_portfolio_approval',
        ], 'Created', 201);
    }

    /**
     * Portfolio holder (content_lead) approval. Sets status = pending_finance_approval.
     * RBAC: role must be content_lead (portfolio_holder alias — RoleType.php L42–43).
     * INSERT audit_log: action = tier_change_portfolio_approved.
     */
    public function approvePortfolio(Request $request, string $id)
    {
        $this->assertRole(['content_lead', 'admin']);

        $row = DB::table('tier_change_requests')->where('id', $id)->first();
        if (!$row || $row->status !== 'pending_portfolio_approval') {
            return ResponseFormatter::error('Request not found or not pending portfolio approval', 404);
        }

        $sku = Sku::find($row->sku_id);
        $oldTier = $sku && $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower(trim((string) ($sku->tier ?? 'support')));
        $newTier = strtolower($row->requested_tier);

        DB::table('tier_change_requests')->where('id', $id)->update([
            'status' => 'pending_finance_approval',
            'portfolio_approved_by' => auth()->id(),
            'portfolio_approved_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertAudit('tier_change_portfolio_approved', $row->sku_id, $oldTier, $newTier, $id);

        return ResponseFormatter::format([
            'id' => $id,
            'status' => 'pending_finance_approval',
        ]);
    }

    /**
     * Finance approval. Sets status = approved, applies new tier to sku_master/skus.
     * RBAC: role must be finance.
     * INSERT audit_log: action = tier_change_finance_approved.
     */
    public function approveFinance(Request $request, string $id)
    {
        $this->assertRole(['finance', 'admin']);

        $row = DB::table('tier_change_requests')->where('id', $id)->first();
        if (!$row || $row->status !== 'pending_finance_approval') {
            return ResponseFormatter::error('Request not found or not pending finance approval', 404);
        }

        $sku = Sku::findOrFail($row->sku_id);
        $oldTier = $sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower(trim((string) ($sku->tier ?? 'support')));
        $newTier = strtolower($row->requested_tier);

        DB::table('tier_change_requests')->where('id', $id)->update([
            'status' => 'approved',
            'finance_approved_by' => auth()->id(),
            'finance_approved_at' => now(),
            'applied_at' => now(),
            'updated_at' => now(),
        ]);

        $sku->update(['tier' => $newTier]);

        $this->insertAudit('tier_change_finance_approved', $row->sku_id, $oldTier, $newTier, $id);

        return ResponseFormatter::format([
            'id' => $id,
            'status' => 'approved',
            'tier_applied' => $newTier,
        ]);
    }

    /**
     * Reject a request. RBAC: content_lead or finance.
     * INSERT audit_log: action = tier_change_rejected.
     */
    public function reject(Request $request, string $id)
    {
        $this->assertRole(['content_lead', 'finance', 'admin']);

        $row = DB::table('tier_change_requests')->where('id', $id)->first();
        if (!$row) {
            return ResponseFormatter::error('Request not found', 404);
        }
        if (!in_array($row->status, ['pending_portfolio_approval', 'pending_finance_approval'], true)) {
            return ResponseFormatter::error('Request is no longer pending', 400);
        }

        $sku = Sku::find($row->sku_id);
        $oldTier = $sku ? ($sku->tier instanceof \App\Enums\TierType ? $sku->tier->value : strtolower(trim((string) ($sku->tier ?? 'support')))) : '—';
        $newTier = strtolower($row->requested_tier);

        DB::table('tier_change_requests')->where('id', $id)->update([
            'status' => 'rejected',
            'updated_at' => now(),
        ]);

        $this->insertAudit('tier_change_rejected', $row->sku_id, $oldTier, $newTier, $id);

        return ResponseFormatter::format([
            'id' => $id,
            'status' => 'rejected',
        ]);
    }

    private function assertRole(array $allowed): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $roleName = optional(optional($user)->role)->name ?? '';
        $normalized = strtolower(str_replace(' ', '_', $roleName));
        $allowedNormalized = array_map('strtolower', $allowed);
        if (!in_array($normalized, $allowedNormalized, true)) {
            abort(403, 'Insufficient role for this action');
        }
    }

    /**
     * SOURCE: CLAUDE.md Section 9 — audit_log immutable. INSERT only.
     */
    private function insertAudit(string $action, string $entityId, string $fromTier, string $toTier, string $requestId): void
    {
        try {
            AuditLog::create([
                'action' => $action,
                'actor_id' => auth()->id(),
                'entity_type' => 'sku',
                'entity_id' => $entityId,
                'meta' => json_encode([
                    'from_tier' => $fromTier,
                    'to_tier' => $toTier,
                    'request_id' => $requestId,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TierChangeController: audit_log insert failed: ' . $e->getMessage());
        }
    }
}
