<?php
namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Sku;
use App\Enums\TierType;
use App\Models\User;

class ApprovalService
{
    /**
     * Patch 7: Tier Override Workflow
     * Prevents single-user override of core tiers.
     */
    public function createTierOverrideRequest(Sku $sku, string $newTier, string $reason): ApprovalRequest
    {
        return ApprovalRequest::create([
            'requester_id' => auth()->id(),
            'entity_type' => 'SKU_TIER',
            'entity_id' => $sku->id,
            'requested_change' => json_encode([
                'old_tier' => $sku->tier,
                'new_tier' => $newTier,
                'reason' => $reason
            ]),
            'status' => 'PENDING'
        ]);
    }

    public function approve(string $requestId): bool
    {
        $request = ApprovalRequest::findOrFail($requestId);
        $user = auth()->user();

        // Dual sign-off logic (Q7)
        if ($user->hasRole('FINANCE_DIRECTOR')) {
            $request->update([
                'finance_approver_id' => $user->id,
                'finance_approved_at' => now()
            ]);
        } elseif ($user->hasRole('COMMERCIAL_DIRECTOR')) {
            $request->update([
                'commercial_approver_id' => $user->id,
                'commercial_approved_at' => now()
            ]);
        }

        // Check if both signed off
        if ($request->finance_approved_at && $request->commercial_approved_at) {
            $request->update(['status' => 'APPROVED']);
            $this->applyChange($request);
            return true;
        }

        return false;
    }

    private function applyChange(ApprovalRequest $request): void
    {
        $change = json_decode($request->requested_change, true);
        if ($request->entity_type === 'SKU_TIER') {
            $sku = Sku::findOrFail($request->entity_id);
            $sku->update(['tier' => $change['new_tier']]);
            
            \App\Models\TierHistory::create([
                'sku_id' => $sku->id,
                'old_tier' => $change['old_tier'],
                'new_tier' => $change['new_tier'],
                'reason' => "Manual override approved by Finance & Commercial: " . ($change['reason'] ?? ''),
                'changed_by' => $request->commercial_approver_id // Log the final approver
            ]);
        }
    }
}
