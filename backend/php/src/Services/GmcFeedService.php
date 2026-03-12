<?php
namespace App\Services;

use App\Models\Sku;
use App\Models\AuditLog;
use Illuminate\Support\Collection;

/**
 * SOURCE: CLAUDE.md Section 4 & 7 | CHAN-02 — GMC feed inclusion rules.
 * Kill/Harvest excluded; Hero ≥85 readiness, Support ≥70.
 * Use this when building the GMC supplemental feed so only eligible SKUs are included.
 */
class GmcFeedService
{
    public function getEligibleSkuIdsForFeed(): Collection
    {
        $skus = Sku::whereIn('tier', ['hero', 'support'])->get();
        $eligible = collect();
        $excluded = [];

        foreach ($skus as $sku) {
            if (ChannelGovernorService::isEligibleForGMC($sku)) {
                $eligible->push($sku->id);
            } else {
                $excluded[] = ['sku_id' => $sku->sku_code ?? $sku->id, 'tier' => $sku->tier, 'readiness_score' => $sku->readiness_score ?? 0];
            }
        }

        if (!empty($excluded)) {
            try {
                AuditLog::create([
                    'entity_type' => 'gmc_feed',
                    'entity_id'   => 'feed_run',
                    'action'      => 'gmc_feed_excluded_skus',
                    'field_name'  => null,
                    'old_value'   => null,
                    'new_value'   => json_encode(['excluded' => $excluded, 'count' => count($excluded)]),
                    'actor_id'    => 'SYSTEM',
                    'actor_role'  => 'system',
                    'timestamp'   => now(),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable $e) {
                // Fail-soft: do not break feed generation
            }
        }

        return $eligible;
    }
}
