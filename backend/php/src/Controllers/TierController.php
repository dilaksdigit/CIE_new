<?php
namespace App\Controllers;

use Illuminate\Http\Request;
use App\Utils\ResponseFormatter;
use App\Models\Sku;
use App\Models\TierHistory;
use App\Models\AuditLog;
use App\Enums\TierType;
use App\Services\ValidationService;
use App\Services\ChannelGovernorService;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TierController {
    /**
     * POST /api/v1/erp/sync — receive ERP data push; recompute tiers. Unified API 7.1.
     * Re-validates SKUs after tier change and logs tier_change to audit_log for traceability.
     *
     * Tier formula (spec-aligned): commercial_score per SKU from payload:
     *   score = (contribution_margin_pct/100)*0.40 + (1/max(cppc,0.01))*0.25
     *         + (velocity_90d/cohort_max_velocity)*0.20 + (1 - return_rate_pct/100)*0.15
     * Cohort percentiles: p80, p30, p10 from sorted scores. Thresholds:
     *   score >= p80 → HERO; >= p30 → SUPPORT; >= p10 → HARVEST; < p10 → KILL
     * Override: contribution_margin_pct < 0 → KILL (e.g. FLR-ARC-BLK-175 -4.2% → KILL).
     */
    public function erpSync(Request $request) {
        $payload = $request->validate([
            'sync_date' => 'required|date',
            'skus' => 'required|array',
            'skus.*.sku_id' => 'required|string',
            'skus.*.contribution_margin_pct' => 'nullable|numeric',
            'skus.*.cppc' => 'nullable|numeric',
            'skus.*.velocity_90d' => 'nullable|integer',
            'skus.*.return_rate_pct' => 'nullable|numeric',
        ]);

        $syncDate = $payload['sync_date'];
        $items = $payload['skus'] ?? [];
        $count = count($items);

        $errors = [];
        $autoPromotions = 0;
        $tierChanges = 0;

        // Compute cohort max velocity from payload (normalisation term).
        $maxVelocity = 0;
        foreach ($items as $row) {
            $v = (int) ($row['velocity_90d'] ?? 0);
            if ($v > $maxVelocity) { $maxVelocity = $v; }
        }
        if ($maxVelocity <= 0) { $maxVelocity = 1; }

        // Preload all SKU rows we can match.
        $skuIds = array_values(array_unique(array_map(fn($r) => (string) ($r['sku_id'] ?? ''), $items)));
        $skuMap = Sku::whereIn('sku_code', $skuIds)->get()->keyBy('sku_code');

        // Compute scores for all matched SKUs (based on payload values).
        $scoresBySkuCode = [];
        foreach ($items as $row) {
            $skuCode = (string) ($row['sku_id'] ?? '');
            if ($skuCode === '') { continue; }
            if (!isset($skuMap[$skuCode])) {
                $errors[] = ['sku_id' => $skuCode, 'error' => 'SKU not found'];
                continue;
            }

            $marginPct = (float) ($row['contribution_margin_pct'] ?? 0);
            $cppc = (float) ($row['cppc'] ?? 0);
            $velocity = (float) ($row['velocity_90d'] ?? 0);
            $returnPct = (float) ($row['return_rate_pct'] ?? 0);

            $wMargin = self::tierWeight('tier.margin_weight', 0.40);
            $wCppc = self::tierWeight('tier.cppc_weight', 0.25);
            $wVelocity = self::tierWeight('tier.velocity_weight', 0.20);
            $wReturns = self::tierWeight('tier.returns_weight', 0.15);

            $score =
                (($marginPct / 100.0) * $wMargin) +
                ((1.0 / max($cppc, 0.01)) * $wCppc) +
                (($velocity / $maxVelocity) * $wVelocity) +
                ((1.0 - ($returnPct / 100.0)) * $wReturns);

            $scoresBySkuCode[$skuCode] = round((float) $score, 6);
        }

        if (empty($scoresBySkuCode)) {
            return ResponseFormatter::format([
                'sync_date' => $syncDate,
                'skus_processed' => $count,
                'tier_changes' => 0,
                'auto_promotions' => 0,
                'errors' => $errors,
            ], 'ERP sync received (no matched SKUs)', 200);
        }

        // Percentile thresholds derived from cohort scores.
        $sortedScores = collect($scoresBySkuCode)->values()->sort()->values();
        $n = $sortedScores->count();
        $p80 = $sortedScores[(int) floor($n * 0.8)] ?? $sortedScores->last();
        $p30 = $sortedScores[(int) floor($n * 0.3)] ?? $sortedScores->first();
        $p10 = $sortedScores[(int) floor($n * 0.1)] ?? $sortedScores->first();

        DB::beginTransaction();
        try {
            foreach ($items as $row) {
                $skuCode = (string) ($row['sku_id'] ?? '');
                if ($skuCode === '' || !isset($skuMap[$skuCode])) { continue; }

                /** @var Sku $sku */
                $sku = $skuMap[$skuCode];

                $oldTierRaw = $sku->tier;
                $oldTier = TierType::tryFrom((string) $oldTierRaw) ?? TierType::SUPPORT;

                $marginPct = (float) ($row['contribution_margin_pct'] ?? 0);
                $cppc = (float) ($row['cppc'] ?? 0);
                $velocity = (int) ($row['velocity_90d'] ?? 0);
                $returnPct = (float) ($row['return_rate_pct'] ?? 0);

                $previousVelocity = (int) ($sku->erp_velocity_90d ?? 0);

                // Update ERP fields + keep previous velocity for QoQ comparison.
                $sku->update([
                    'margin_percent' => $marginPct,
                    'erp_cppc' => $cppc,
                    'erp_return_rate_pct' => $returnPct,
                    'previous_velocity_90d' => $previousVelocity > 0 ? $previousVelocity : ($sku->previous_velocity_90d ?? null),
                    'erp_velocity_90d' => $velocity,
                    // Preserve legacy field usage elsewhere by also setting annual_volume to the latest velocity.
                    'annual_volume' => $velocity,
                    'commercial_score' => $scoresBySkuCode[$skuCode] ?? 0,
                ]);

                $score = (float) ($scoresBySkuCode[$skuCode] ?? 0);

                // Base tier from percentile bands; override: negative margin → KILL
                $newTier = TierType::KILL;
                if ($marginPct < 0) {
                    $newTier = TierType::KILL;
                } elseif ($score >= $p80) {
                    $newTier = TierType::HERO;
                } elseif ($score >= $p30) {
                    $newTier = TierType::SUPPORT;
                } elseif ($score >= $p10) {
                    $newTier = TierType::HARVEST;
                }

                $reason = sprintf(
                    'ERP sync %s; score=%.6f; p80=%.6f p30=%.6f p10=%.6f; margin=%.2f%% cppc=%.4f velocity_90d=%d return_rate=%.2f%%',
                    $syncDate,
                    $score,
                    (float) $p80,
                    (float) $p30,
                    (float) $p10,
                    $marginPct,
                    $cppc,
                    $velocity,
                    $returnPct
                );

                // Auto-promotion: Harvest -> Support if velocity increases >30% vs previous quarter.
                $prevForGrowth = (int) ($sku->previous_velocity_90d ?? 0);
                if ($newTier === TierType::HARVEST && $prevForGrowth > 0 && $velocity > (int) floor($prevForGrowth * 1.30)) {
                    $newTier = TierType::SUPPORT;
                    $autoPromotions++;
                    $reason .= sprintf('; auto_promotion=harvest_to_support (prev=%d curr=%d)', $prevForGrowth, $velocity);
                }

                if ($oldTier !== $newTier) {
                    // SOURCE: CIE_Master_Developer_Build_Spec.docx §8 (tier.manual_override_expiry_days)
                    // SOURCE: CIE_v231_Developer_Build_Pack.pdf (sku_tier_history dual sign-off requirement)
                    // Override only valid when approved_by AND second_approver are both non-null.
                    // Override = NONE for gate G6.1 per CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 —
                    // this means no UNILATERAL override. Dual-approved admin overrides are permitted.
                    $expiryDays = (int) BusinessRules::get('tier.manual_override_expiry_days', 90);
                    if ($expiryDays > 0) {
                        $lastOverride = TierHistory::where('sku_id', $sku->id)
                            ->where(function ($q) {
                                $q->where('reason', 'like', '%manual_override%')
                                    ->orWhere('reason', 'manual_override');
                            })
                            ->orderByDesc('changed_at')
                            ->first();

                        if ($lastOverride && $lastOverride->changed_at && $lastOverride->changed_at->gte(now()->subDays($expiryDays))) {
                            $hasDualApproval = !empty($lastOverride->approved_by) && !empty($lastOverride->second_approver);

                            if ($hasDualApproval) {
                                try {
                                    AuditLog::create([
                                        'entity_type' => 'sku',
                                        'entity_id'   => $sku->id,
                                        'action'      => 'erp_sync_skipped_manual_override',
                                        'field_name'  => 'tier',
                                        'old_value'   => $oldTier->value ?? (string) $oldTier,
                                        'new_value'   => $newTier->value ?? (string) $newTier,
                                        'actor_id'    => 'SYSTEM',
                                        'actor_role'  => 'system',
                                        'timestamp'   => now(),
                                        'created_at'  => now(),
                                    ]);
                                } catch (\Throwable $auditErr) {
                                    // Fail-soft: do not break ERP sync if audit_log write fails
                                }
                                continue;
                            }

                            try {
                                AuditLog::create([
                                    'entity_type' => 'sku',
                                    'entity_id'   => $sku->id,
                                    'action'      => 'erp_sync_override_rejected_missing_approver',
                                    'field_name'  => 'tier',
                                    'old_value'   => $lastOverride->approved_by,
                                    'new_value'   => $lastOverride->second_approver,
                                    'actor_id'    => 'SYSTEM',
                                    'actor_role'  => 'system',
                                    'timestamp'   => now(),
                                    'created_at'  => now(),
                                ]);
                            } catch (\Throwable $auditErr) {
                                // Fail-soft: do not break ERP sync if audit_log write fails
                            }
                        }
                    }
                    $tierChanges++;
                    $sku->update([
                        'tier' => $newTier,
                        'tier_rationale' => $reason,
                    ]);

                    app(ChannelGovernorService::class)->recalculateAndPersist($sku);

                    TierHistory::create([
                        'sku_id' => $sku->id,
                        'old_tier' => $oldTier,
                        'new_tier' => $newTier,
                        'reason' => $reason,
                        'margin_percent' => $marginPct,
                        'annual_volume' => $velocity,
                        'changed_by' => auth()->id(),
                    ]);

                    // §9 Audit: log tier change
                    AuditLog::create([
                        'entity_type' => 'sku',
                        'entity_id' => $sku->id,
                        'action' => 'tier_change',
                        'field_name' => 'tier',
                        'old_value' => $oldTier->value ?? (string) $oldTier,
                        'new_value' => $newTier->value ?? (string) $newTier,
                        'actor_id' => (string) (auth()->id() ?? 'SYSTEM'),
                        'actor_role' => optional(optional(auth()->user())->role)->name ?? 'system',
                        'timestamp' => now(),
                        'user_id' => auth()->id(),
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => now(),
                    ]);

                    // Re-validate after tier change (new tier may have new gate requirements)
                    try {
                        $validationService = app(ValidationService::class);
                        $validationResult = $validationService->validate($sku->fresh(), false);
                        if (!$validationResult['valid']) {
                            Log::info("SKU {$sku->sku_code} tier changed to " . ($newTier->value ?? $newTier) . "; validation now failing", [
                                'results' => $validationResult['results'] ?? [],
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Post-tier-change validation failed for SKU {$sku->id}: " . $e->getMessage());
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseFormatter::format([
                'sync_date' => $syncDate,
                'skus_processed' => $count,
                'tier_changes' => 0,
                'auto_promotions' => 0,
                'errors' => array_merge($errors, [['error' => 'ERP sync failed', 'detail' => $e->getMessage()]]),
            ], 'ERP sync failed', 500);
        }

        return ResponseFormatter::format([
            'sync_date' => $syncDate,
            'skus_processed' => $count,
            'tier_changes' => $tierChanges,
            'auto_promotions' => $autoPromotions,
            'errors' => $errors,
            'percentiles' => [
                'p80' => (float) $p80,
                'p30' => (float) $p30,
                'p10' => (float) $p10,
                'max_velocity' => (int) $maxVelocity,
            ],
        ], 'ERP sync processed', 200);
    }

    /**
     * POST /api/v1/tiers/recalculate — trigger tier recalculation using stored ERP data.
     */
    public function recalculate(Request $request)
    {
        $skus = Sku::whereNotNull('commercial_score')->get();
        if ($skus->isEmpty()) {
            return ResponseFormatter::format([
                'skus_processed' => 0,
                'tier_changes' => 0,
                'message' => 'No SKUs with commercial scores found. Run ERP sync first.',
            ]);
        }

        $scores = $skus->pluck('commercial_score')->sort()->values();
        $n = $scores->count();
        $p80 = $scores[(int) floor($n * 0.8)] ?? $scores->last();
        $p30 = $scores[(int) floor($n * 0.3)] ?? $scores->first();
        $p10 = $scores[(int) floor($n * 0.1)] ?? $scores->first();

        $tierChanges = 0;

        DB::beginTransaction();
        try {
            foreach ($skus as $sku) {
                $score = (float) $sku->commercial_score;
                $marginPct = (float) ($sku->margin_percent ?? 0);
                $oldTier = TierType::tryFrom((string) $sku->tier) ?? TierType::SUPPORT;

                $newTier = TierType::KILL;
                if ($marginPct < 0) {
                    $newTier = TierType::KILL;
                } elseif ($score >= $p80) {
                    $newTier = TierType::HERO;
                } elseif ($score >= $p30) {
                    $newTier = TierType::SUPPORT;
                } elseif ($score >= $p10) {
                    $newTier = TierType::HARVEST;
                }

                if ($oldTier !== $newTier) {
                    $tierChanges++;
                    $reason = sprintf('Manual recalculation; score=%.6f; p80=%.6f p30=%.6f p10=%.6f', $score, (float) $p80, (float) $p30, (float) $p10);
                    $sku->update(['tier' => $newTier, 'tier_rationale' => $reason]);

                    app(ChannelGovernorService::class)->recalculateAndPersist($sku);

                    TierHistory::create([
                        'sku_id' => $sku->id,
                        'old_tier' => $oldTier,
                        'new_tier' => $newTier,
                        'reason' => $reason,
                        'margin_percent' => $marginPct,
                        'annual_volume' => $sku->erp_velocity_90d ?? 0,
                        'changed_by' => auth()->id(),
                    ]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseFormatter::format(['error' => $e->getMessage()], 'Recalculation failed', 500);
        }

        return ResponseFormatter::format([
            'skus_processed' => $skus->count(),
            'tier_changes' => $tierChanges,
        ]);
    }

    private static function tierWeight(string $key, float $default): float
    {
        try {
            return (float) BusinessRules::get($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
