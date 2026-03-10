<?php
namespace App\Services;

// SOURCE: CIE_Integration_Specification.pdf Section 1.2-1.4; CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 9.2
// SOURCE: CIE_Master_Developer_Build_Spec.docx §8.1 / §5.3; CLAUDE.md §7
// SOURCE: CIE_Master_Developer_Build_Spec.docx §5; CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.2; CIE_Integration_Specification.pdf §1.3
// SOURCE: CIE_v231_Developer_Build_Pack.pdf Canonical Schema + CIE_Master_Developer_Build_Spec.docx §8 + CIE_Integration_Specification.pdf §1.3
use App\Models\Sku;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ERPSyncService {
    /**
     * Sync ERP payload and recompute tiers.
     * @param array $payload ERP sync payload (see openapi.yaml)
     * @return array Response object per /erp/sync 200 schema
     */
    public function sync(array $payload): array
    {
        $now = Carbon::now();
        $errors = [];
        $skus_processed = 0;
        $skus = $payload['skus'] ?? [];
        $erp_sku_ids = array_column($skus, 'sku_id');
        $cie_skus = Sku::all()->keyBy('sku_id');
        // 1. Validate and process each ERP row
        foreach ($skus as $row) {
            $sku_id = $row['sku_id'] ?? null;
            $margin = $row['contribution_margin_pct'] ?? null;
            $cppc = $row['cppc'] ?? null;
            $velocity = $row['velocity_90d'] ?? null;
            $returns = $row['return_rate_pct'] ?? null;
            // Validate row
            if (!$sku_id || !is_numeric($margin) || !is_numeric($cppc) || !is_numeric($velocity) || !is_numeric($returns) || $margin > 100 || $margin < -100 || $cppc < 0 || $velocity < 0 || $returns < 0) {
                $errors[] = "Invalid data for SKU $sku_id";
                Log::error("ERP sync: Invalid data for SKU $sku_id", $row);
                $this->alertAdmin("ERP sync: Invalid data for SKU $sku_id", $row);
                continue;
            }
            // Orphan ERP SKU (not in CIE)
            if (!isset($cie_skus[$sku_id])) {
                $errors[] = "Orphan ERP SKU: $sku_id";
                Log::warning("ERP sync: Orphan ERP SKU $sku_id", $row);
                continue;
            }
            // Update SKU commercial data (canonical erp_-prefixed columns)
            $sku = $cie_skus[$sku_id];
            $sku->erp_margin_pct = $margin;
            $sku->erp_cppc = $cppc;
            $sku->erp_velocity_90d = $velocity;
            $sku->erp_return_rate_pct = $returns;
            $sku->save();
            $skus_processed++;
        }
        // 2. Delegate tier recomputation to canonical engine
        $tierResult = (new TierCalculationService())->recalculateAllTiers();
        $tier_changes = count($tierResult);
        // 3. Mark CIE SKUs not in ERP as stale
        foreach ($cie_skus as $sku_id => $sku) {
            if (!in_array($sku_id, $erp_sku_ids)) {
                $sku->commercial_data_stale = true;
                $sku->save();
            }
        }
        // 4. Return response
        return [
            'sync_date' => $now->toIso8601String(),
            'skus_processed' => $skus_processed,
            'tier_changes' => $tier_changes,
            'errors' => $errors,
        ];
    }

    private function alertAdmin($message, $context = [])
    {
        // Placeholder: send notification to admin (implement as needed)
        Log::alert($message, $context);
    }
}
