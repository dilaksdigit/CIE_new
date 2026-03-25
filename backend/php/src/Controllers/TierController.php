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
        // SOURCE: openapi.yaml ErpSyncPayload — keys required
        // SOURCE: CIE_Integration_Specification.pdf §1.2 — null fallbacks
        $payload = $request->validate([
            'sync_date' => 'required|date',
            'skus' => 'required|array|min:1',
            'skus.*.sku_id' => 'required|string',
            'skus.*.contribution_margin_pct' => 'present|nullable|numeric',
            'skus.*.cppc' => 'present|nullable|numeric',
            'skus.*.velocity_90d' => 'present|nullable|integer',
            'skus.*.return_rate_pct' => 'present|nullable|numeric',
        ]);

        $syncDate = $payload['sync_date'];
        $items = $payload['skus'] ?? [];
        $count = count($items);

        $errors = [];
        $autoPromotions = 0;
        $tierChanges = 0;
        $orphanSkuCodes = [];
        $marginSkipTierBySkuId = [];

        // Preload all SKU rows we can match from payload (sku_id = sku_code on wire).
        $skuIds = array_values(array_unique(array_map(fn ($r) => (string) ($r['sku_id'] ?? ''), $items)));
        $skuMap = Sku::whereIn('sku_code', $skuIds)->get()->keyBy('sku_code');

        foreach ($items as $row) {
            $skuCode = (string) ($row['sku_id'] ?? '');
            if ($skuCode === '') {
                continue;
            }
            if (!isset($skuMap[$skuCode])) {
                // SOURCE: CIE_Integration_Specification.pdf §1.2 — orphan handling (no errors[] entry)
                $orphanSkuCodes[] = $skuCode;
            }
        }

        DB::beginTransaction();
        try {
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §9.2 — step 5: apply payload ERP rows to matched SKUs
            // SOURCE: CIE_Integration_Specification.pdf §1.2 — ranges, defaults, flags; CIE_v232_Cloud_Briefing §11 — null handling
            foreach ($items as $row) {
                $skuCode = (string) ($row['sku_id'] ?? '');
                if ($skuCode === '' || !isset($skuMap[$skuCode])) {
                    continue;
                }

                /** @var Sku $sku */
                $sku = $skuMap[$skuCode];
                $previousVelocity = (int) ($sku->erp_velocity_90d ?? 0);
                $erpIncomplete = false;
                $rowHasFieldError = false;

                $updateData = [
                    'previous_velocity_90d' => $previousVelocity > 0 ? $previousVelocity : ($sku->previous_velocity_90d ?? null),
                ];
                if (Schema::hasColumn('skus', 'erp_sync_date')) {
                    $updateData['erp_sync_date'] = $syncDate;
                }

                $marginRaw = $row['contribution_margin_pct'] ?? null;
                if ($marginRaw === null || $marginRaw === '') {
                    // SOURCE: CIE_Integration_Specification.pdf §1.2 — missing margin: flag + alert (null fallback rules apply)
                    // SOURCE: CIE_v232_Cloud_Briefing.md §11 — contribution_margin_pct null: flag; keep existing DB value
                    $erpIncomplete = true;
                    try {
                        AuditLog::create([
                            'entity_type' => 'sku',
                            'entity_id'   => $sku->id,
                            'action'      => 'erp_margin_missing_alert',
                            'field_name'  => 'contribution_margin_pct',
                            'old_value'   => null,
                            'new_value'   => json_encode(['reason' => 'Missing contribution_margin_pct from ERP'], JSON_UNESCAPED_SLASHES),
                            'actor_id'    => (string) (auth()->id() ?? 'SYSTEM'),
                            'actor_role'  => optional(optional(auth()->user())->role)->name ?? 'system',
                            'timestamp'   => now(),
                            'created_at'  => now(),
                        ]);
                    } catch (\Throwable $auditErr) {
                        Log::warning('erpSync: erp_margin_missing_alert audit failed: '.$auditErr->getMessage());
                    }
                } else {
                    $marginPct = (float) $marginRaw;
                    if ($marginPct < -100.0 || $marginPct > 100.0) {
                        Log::alert('ERP contribution_margin_pct out of range', ['sku_id' => $skuCode, 'value' => $marginPct]);
                        $patchInvalid = ['tier' => null];
                        if (Schema::hasColumn('skus', 'erp_data_incomplete')) {
                            $patchInvalid['erp_data_incomplete'] = true;
                        }
                        try {
                            $sku->update($patchInvalid);
                        } catch (\Throwable $patchErr) {
                            // SOURCE: CIE_Integration_Specification.pdf §1.2 — e.g. Kill-tier DB trigger may block UPDATE; flag only in log
                            Log::warning('erpSync: could not apply invalid-margin patch', ['sku' => $skuCode, 'error' => $patchErr->getMessage()]);
                        }
                        $marginSkipTierBySkuId[(string) $sku->id] = true;
                        $skuMap[$skuCode] = $sku->fresh();
                        continue;
                    }
                    $updateData['margin_percent'] = $marginPct;
                    if (Schema::hasColumn('skus', 'erp_margin_pct')) {
                        // SOURCE: CIE_v231_Developer_Build_Pack.pdf Section 1.2
                        // erp_margin_pct written alongside other ERP fields for
                        // TierCalculationService::recalculateAllTiers() whereNotNull filter
                        $updateData['erp_margin_pct'] = $marginPct;
                    }
                }

                $cppcRaw = $row['cppc'] ?? null;
                // SOURCE: CIE_Integration_Specification.pdf §1.2 — ERP Data Contract
                if ($cppcRaw === null || $cppcRaw === '') {
                    $erpIncomplete = true;
                    $updateData['erp_cppc'] = 1.00;
                    $errors[] = "SKU {$skuCode}: cppc missing - defaulted to 1.00 (neutral)";
                } else {
                    $cppc = (float) $cppcRaw;
                    if ($cppc < 0.01 || $cppc > 100.00) {
                        $erpIncomplete = true;
                        $rowHasFieldError = true;
                        $errors[] = "SKU {$skuCode}: cppc out of range (value: {$cppcRaw}, expected: 0.01-100.00)";
                    } else {
                        $updateData['erp_cppc'] = $cppc;
                    }
                }

                $velRaw = $row['velocity_90d'] ?? null;
                // SOURCE: CIE_Integration_Specification.pdf §1.2 — ERP Data Contract
                if ($velRaw === null || $velRaw === '') {
                    $erpIncomplete = true;
                    $updateData['erp_velocity_90d'] = 0;
                    $updateData['annual_volume'] = 0;
                    $errors[] = "SKU {$skuCode}: velocity_90d missing - defaulted to 0 (no sales data)";
                } else {
                    $velocity = (int) $velRaw;
                    if ($velocity < 0 || $velocity > 999999) {
                        $erpIncomplete = true;
                        $rowHasFieldError = true;
                        $errors[] = "SKU {$skuCode}: velocity_90d out of range (value: {$velRaw}, expected: 0-999999)";
                    } else {
                        $updateData['erp_velocity_90d'] = $velocity;
                        $updateData['annual_volume'] = $velocity;
                    }
                }

                $retRaw = $row['return_rate_pct'] ?? null;
                // SOURCE: CIE_Integration_Specification.pdf §1.2 — ERP Data Contract
                if ($retRaw === null || $retRaw === '') {
                    $erpIncomplete = true;
                    $updateData['erp_return_rate_pct'] = 5.0;
                    $errors[] = "SKU {$skuCode}: return_rate_pct missing - defaulted to 5.0 (industry avg)";
                } else {
                    $returnPct = (float) $retRaw;
                    if ($returnPct < 0.0 || $returnPct > 100.0) {
                        $erpIncomplete = true;
                        $rowHasFieldError = true;
                        $errors[] = "SKU {$skuCode}: return_rate_pct out of range (value: {$retRaw}, expected: 0.00-100.00)";
                    } else {
                        $updateData['erp_return_rate_pct'] = $returnPct;
                    }
                }

                // SOURCE: CIE_Integration_Specification.pdf §1.4 — skip entire row when any field is invalid
                if ($rowHasFieldError) {
                    $marginSkipTierBySkuId[(string) $sku->id] = true;
                    $skuMap[$skuCode] = $sku->fresh();
                    continue;
                }

                if (Schema::hasColumn('skus', 'erp_data_incomplete')) {
                    $updateData['erp_data_incomplete'] = $erpIncomplete;
                }

                $sku->update($updateData);
                $skuMap[$skuCode] = $sku->fresh();
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
                $this->auditErpOrphansAndCompletion($orphanSkuCodes, $syncDate, $count, 0, $errors);
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

                // SOURCE: CIE_Integration_Specification.pdf §1.2 — invalid margin rows: tier cleared; skip percentile reassignment
                if (!empty($marginSkipTierBySkuId[(string) $sku->id])) {
                    continue;
                }

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

        // SOURCE: CIE_v231_Developer_Build_Pack.pdf §7.1 — ERP sync must always create audit_log; CIE_Integration_Specification.pdf §1.2 — orphan audit
        $this->auditErpOrphansAndCompletion($orphanSkuCodes, $syncDate, $count, $tierChanges, $errors);

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
     * SOURCE: CIE_v231_Developer_Build_Pack.pdf §7.1 — immutable audit_log on every ERP sync completion
     * SOURCE: CIE_Integration_Specification.pdf §1.2 — erp_orphan_skipped rows (not in errors[])
     */
    private function auditErpOrphansAndCompletion(array $orphanSkuCodes, string $syncDate, int $skusProcessed, int $tierChanges, array $errors): void
    {
        foreach ($orphanSkuCodes as $code) {
            try {
                AuditLog::create([
                    'entity_type' => 'erp',
                    'entity_id'   => (string) $code,
                    'action'      => 'erp_orphan_skipped',
                    'field_name'  => 'sku_id',
                    'old_value'   => null,
                    'new_value'   => (string) $code,
                    'actor_id'    => (string) (auth()->id() ?? 'SYSTEM'),
                    'actor_role'  => optional(optional(auth()->user())->role)->name ?? 'system',
                    'timestamp'   => now(),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('auditErpOrphans: orphan log failed: '.$e->getMessage());
            }
        }

        try {
            AuditLog::create([
                'entity_type' => 'erp',
                'entity_id'   => 'sync',
                'action'      => 'erp_sync_completed',
                'field_name'  => null,
                'old_value'   => null,
                'new_value'   => json_encode([
                    'sync_date'      => $syncDate,
                    'skus_processed' => $skusProcessed,
                    'tier_changes'   => $tierChanges,
                    'errors_count'   => count($errors),
                ], JSON_UNESCAPED_SLASHES),
                'actor_id'    => (string) (auth()->id() ?? 'SYSTEM'),
                'actor_role'  => optional(optional(auth()->user())->role)->name ?? 'system',
                'timestamp'   => now(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('auditErpOrphans: erp_sync_completed log failed: '.$e->getMessage());
        }
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
