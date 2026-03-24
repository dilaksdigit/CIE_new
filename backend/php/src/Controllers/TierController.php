<?php
namespace App\Controllers;

use Illuminate\Http\Request;
use App\Models\Sku;
use App\Models\TierHistory;
use App\Models\AuditLog;
use App\Enums\TierType;
use App\Services\ValidationService;
use App\Services\ChannelGovernorService;
use App\Services\TierCalculationService;
use App\Support\BusinessRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TierController {
    /**
     * POST /api/v1/erp/sync — receive ERP data push; recompute tiers. Unified API 7.1.
     * Re-validates SKUs after tier change and logs tier_change to audit_log for traceability.
     *
     * Commercial score: TierCalculationService::commercialPriorityScore (BusinessRules weights + scales).
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2; CIE_Master_Developer_Build_Spec.docx §8.2
     * FIX: TS-02 — Percentiles from ALL active SKUs after payload ERP field updates; scores recomputed for full catalog.
     * Override: contribution_margin_pct < 0 → KILL (e.g. FLR-ARC-BLK-175 -4.2% → KILL).
     */
    public function erpSync(Request $request) {
        $payload = $request->validate([
            'sync_date' => 'required|date',
            'skus' => 'required|array',
            'skus.*.sku_id' => 'required|string',
            'skus.*.contribution_margin_pct' => 'required|numeric',
            'skus.*.cppc' => 'required|numeric',
            'skus.*.velocity_90d' => 'required|integer',
            'skus.*.return_rate_pct' => 'required|numeric',
        ]);

        $syncDate = $payload['sync_date'];
        $items = $payload['skus'] ?? [];
        $count = count($items);

        $errors = [];
        $autoPromotions = 0;
        $tierChanges = 0;

        // Preload all SKU rows we can match from payload (sku_id = sku_code on wire).
        $skuIds = array_values(array_unique(array_map(fn($r) => (string) ($r['sku_id'] ?? ''), $items)));
        $skuMap = Sku::whereIn('sku_code', $skuIds)->get()->keyBy('sku_code');

        foreach ($items as $row) {
            $skuCode = (string) ($row['sku_id'] ?? '');
            if ($skuCode === '') {
                continue;
            }
            if (!isset($skuMap[$skuCode])) {
                $errors[] = ['sku_id' => $skuCode, 'error' => 'SKU not found'];
            }
        }

        DB::beginTransaction();
        try {
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — step 5: apply payload ERP rows to matched SKUs
            foreach ($items as $row) {
                $skuCode = (string) ($row['sku_id'] ?? '');
                if ($skuCode === '' || !isset($skuMap[$skuCode])) {
                    continue;
                }

                /** @var Sku $sku */
                $sku = $skuMap[$skuCode];
                $marginPct = (float) ($row['contribution_margin_pct'] ?? 0);
                $cppc = (float) ($row['cppc'] ?? 0);
                $velocity = (int) ($row['velocity_90d'] ?? 0);
                $returnPct = (float) ($row['return_rate_pct'] ?? 0);
                $previousVelocity = (int) ($sku->erp_velocity_90d ?? 0);

                $updateData = [
                    'margin_percent' => $marginPct,
                    'erp_cppc' => $cppc,
                    'erp_return_rate_pct' => $returnPct,
                    'previous_velocity_90d' => $previousVelocity > 0 ? $previousVelocity : ($sku->previous_velocity_90d ?? null),
                    'erp_velocity_90d' => $velocity,
                    'annual_volume' => $velocity,
                ];
                if (Schema::hasColumn('skus', 'erp_margin_pct')) {
                    $updateData['erp_margin_pct'] = $marginPct;
                }
                if (Schema::hasColumn('skus', 'erp_sync_date')) {
                    $updateData['erp_sync_date'] = $syncDate;
                }
                $sku->update($updateData);
            }

            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — recompute commercial_score for ALL active SKUs
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §8.2 — assign_tier uses full-catalog score distribution
            $allSkusQuery = Sku::query();
            if (Schema::hasColumn('skus', 'is_active')) {
                $allSkusQuery->where('is_active', true);
            }
            $allSkus = $allSkusQuery->get();

            if ($allSkus->isEmpty()) {
                DB::commit();
                return response()->json([
                    'sync_date' => $syncDate,
                    'skus_processed' => $count,
                    'tier_changes' => 0,
                    'auto_promotions' => 0,
                    'errors' => collect($errors)->map(function ($e) {
                        if (is_string($e)) {
                            return $e;
                        }
                        return (string) ($e['error'] ?? $e['detail'] ?? json_encode($e));
                    })->values()->all(),
                ], 200);
            }

            $scoresById = [];
            foreach ($allSkus as $sku) {
                $marginPct = (float) ($sku->margin_percent ?? $sku->erp_margin_pct ?? 0);
                $cppc = (float) ($sku->erp_cppc ?? 0);
                $velocity = (float) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
                $returnPct = (float) ($sku->erp_return_rate_pct ?? 0);
                $raw = TierCalculationService::commercialPriorityScore($marginPct, $cppc, $velocity, $returnPct);
                $rounded = round($raw, 6);
                $scoresById[$sku->id] = $rounded;
                $sku->update(['commercial_score' => $rounded]);
            }

            $sortedScores = collect($scoresById)->values()->sort()->values();
            $n = max(1, $sortedScores->count());
            $heroPct = (float) BusinessRules::get('tier.hero_percentile_threshold');
            $supportPct = (float) BusinessRules::get('tier.support_percentile_threshold');
            $harvestPct = (float) BusinessRules::get('tier.harvest_percentile_threshold');
            $p80 = $sortedScores[(int) floor($n * $heroPct)] ?? $sortedScores->last();
            $p30 = $sortedScores[(int) floor($n * $supportPct)] ?? $sortedScores->first();
            $p10 = $sortedScores[(int) floor($n * $harvestPct)] ?? $sortedScores->first();

            foreach ($allSkus as $sku) {
                $sku->refresh();

                $oldTier = $sku->tier instanceof TierType ? $sku->tier : (TierType::tryFrom(strtolower((string) ($sku->tier ?? ''))) ?? TierType::SUPPORT);

                $marginPct = (float) ($sku->margin_percent ?? $sku->erp_margin_pct ?? 0);
                $cppc = (float) ($sku->erp_cppc ?? 0);
                $velocity = (int) ($sku->erp_velocity_90d ?? $sku->annual_volume ?? 0);
                $returnPct = (float) ($sku->erp_return_rate_pct ?? 0);
                $score = (float) ($scoresById[$sku->id] ?? 0);

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

                // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — configurable velocity threshold.
                $prevForGrowth = (int) ($sku->previous_velocity_90d ?? 0);
                $autoPromoteThreshold = (float) BusinessRules::get('tier.auto_promotion_velocity_threshold');
                if (
                    $newTier === TierType::HARVEST
                    && $prevForGrowth > 0
                    && $velocity > (int) floor($prevForGrowth * (1 + $autoPromoteThreshold))
                ) {
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
                    $expiryDays = (int) BusinessRules::get('tier.manual_override_expiry_days');
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
            return response()->json([
                'sync_date' => $syncDate,
                'skus_processed' => $count,
                'tier_changes' => 0,
                'auto_promotions' => 0,
                'errors' => collect(array_merge($errors, ['ERP sync failed: ' . $e->getMessage()]))
                    ->map(function ($err) {
                        if (is_string($err)) return $err;
                        return (string) ($err['error'] ?? $err['detail'] ?? json_encode($err));
                    })->values()->all(),
            ], 500);
        }

        return response()->json([
            'sync_date' => $syncDate,
            'skus_processed' => $count,
            'tier_changes' => $tierChanges,
            'auto_promotions' => $autoPromotions,
            // SOURCE: openapi.yaml /erp/sync response — errors[] must be strings.
            'errors' => collect($errors)->map(function ($e) {
                if (is_string($e)) return $e;
                return (string) ($e['error'] ?? $e['detail'] ?? json_encode($e));
            })->values()->all(),
        ], 200);
    }

    /**
     * SOURCE: Phase 7 fix request — explicit ERP sync failure audit endpoint.
     * FIX: P7-TIER-01
     */
    public function syncFailed(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'error' => 'required|string',
            'attempts' => 'required|integer',
            'last_attempt_at' => 'nullable|string',
        ]);

        AuditLog::create([
            'entity_type' => 'erp_sync',
            'entity_id' => 'system',
            'action' => 'sync_failed',
            'field_name' => 'sync',
            'old_value' => null,
            'new_value' => json_encode($payload),
            'actor_id' => 'SYSTEM',
            'actor_role' => 'system',
            'timestamp' => now(),
            'user_id' => 'SYSTEM',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        Log::alert('ERP sync failure recorded', $payload);

        return response()->json(['status' => 'failure_logged'], 200);
    }

    /**
     * POST /api/v1/tiers/recalculate — trigger tier recalculation using stored ERP data.
     */
    public function recalculate(Request $request)
    {
        $skus = Sku::whereNotNull('commercial_score')->get();
        if ($skus->isEmpty()) {
            return response()->json([
                'skus_processed' => 0,
                'tier_changes' => 0,
                'message' => 'No SKUs with commercial scores found. Run ERP sync first.',
            ], 200);
        }

        $scores = $skus->pluck('commercial_score')->sort()->values();
        $n = max(1, $scores->count());
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.3 — percentile indices from BusinessRules (aligned with ERP sync)
        $heroPct = (float) BusinessRules::get('tier.hero_percentile_threshold');
        $supportPct = (float) BusinessRules::get('tier.support_percentile_threshold');
        $harvestPct = (float) BusinessRules::get('tier.harvest_percentile_threshold');
        $p80 = $scores[(int) floor($n * $heroPct)] ?? $scores->last();
        $p30 = $scores[(int) floor($n * $supportPct)] ?? $scores->first();
        $p10 = $scores[(int) floor($n * $harvestPct)] ?? $scores->first();

        $tierChanges = 0;

        DB::beginTransaction();
        try {
            foreach ($skus as $sku) {
                $score = (float) $sku->commercial_score;
                $marginPct = (float) ($sku->margin_percent ?? 0);
                $oldTier = $sku->tier instanceof TierType ? $sku->tier : (TierType::tryFrom(strtolower((string) ($sku->tier ?? ''))) ?? TierType::SUPPORT);

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

                    // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — audit_log on tier change
                    // FIX: TS-04 — recalculate path previously omitted AuditLog
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
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'RECALCULATION_FAILED', 'message' => 'Recalculation failed'], 500);
        }

        return response()->json([
            'skus_processed' => $skus->count(),
            'tier_changes' => $tierChanges,
        ], 200);
    }
}
