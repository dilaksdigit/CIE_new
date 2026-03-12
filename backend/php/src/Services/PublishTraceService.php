<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 11
// SOURCE: CLAUDE.md Section 9 — audit_log is IMMUTABLE. INSERT only, no UPDATE/DELETE.

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Logs auto-publish events to audit_log for traceability.
 * Section 11 Step 7: logAutoPublish with channel results after deploy.
 */
class PublishTraceService
{
    /**
     * INSERT into audit_log: entity_type = sku_publish, entity_id = $skuId.
     * new_value = JSON: { sku_id, channels: [{ channel, status, deployed_at }], triggered_by, timestamp }.
     * No UPDATE. No DELETE. INSERT only.
     *
     * @param int|string $skuId SKU id (supports UUID string).
     * @param array<int, array{channel: string, status: string, deployed_at?: string|null, shopify_product_id?: string|null}> $channelResults
     * @param int|string|null $userId
     */
    public function logAutoPublish($skuId, array $channelResults, $userId): void
    {
        $skuIdStr = (string) $skuId;
        $payload = [
            'sku_id'       => $skuIdStr,
            'channels'     => array_values($channelResults),
            'triggered_by' => $userId !== null ? (string) $userId : null,
            'timestamp'    => now()->toIso8601String(),
        ];

        try {
            AuditLog::create([
                'entity_type' => 'sku_publish',
                'entity_id'   => $skuIdStr,
                'action'      => 'auto_publish',
                'field_name'  => null,
                'old_value'   => null,
                'new_value'   => json_encode($payload),
                'actor_id'    => $userId !== null ? (string) $userId : 'SYSTEM',
                'actor_role'  => 'system',
                'timestamp'   => now(),
                // user_id is NOT NULL in DB; use 'SYSTEM' when no authenticated user
                'user_id'     => $userId !== null ? (string) $userId : 'SYSTEM',
                'ip_address'  => request() ? request()->ip() : null,
                'user_agent'  => request() ? request()->userAgent() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('PublishTraceService::logAutoPublish audit_log insert failed: ' . $e->getMessage(), [
                'sku_id' => $skuIdStr,
            ]);
        }
    }
}
