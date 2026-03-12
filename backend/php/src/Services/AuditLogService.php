<?php
// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1 (Fail-Soft Vector Validation); CLAUDE.md Section 18 DECISION-005

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    /**
     * Log vector similarity below threshold (fail-soft). Score is stored for governance only — never returned to writer.
     * SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1
     */
    public function logVectorWarn(int $skuId, float $score, ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'entity_type' => 'vector_warn',
                'entity_id'   => (string) $skuId,
                'action'      => 'vector_warn',
                'old_value'   => null,
                'new_value'   => json_encode([
                    'sku_id'     => $skuId,
                    'score'      => $score,
                    'user_id'    => $userId,
                    'timestamp'  => now()->toIso8601String(),
                ]),
                'user_id'     => $userId,
                'timestamp'   => now(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail-soft: do not break validation if audit_log write fails
        }
    }
}
