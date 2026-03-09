<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;

class G1_BasicInfoGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        $missing = [];
        
        if (!$sku->sku_code || strlen(trim($sku->sku_code)) === 0) {
            $missing[] = 'SKU code';
        }

        if (!$sku->primary_cluster_id) {
            $missing[] = 'Cluster ID';
        } else {
            // SOURCE: CIE_v231_Developer_Build_Pack.pdf §1.2, CIE_v2_3_Enforcement_Edition.pdf §1.1
            // Single authoritative lookup against cluster_master. No fallback permitted.
            $clusterId = $sku->primary_cluster_id;
            $cluster = DB::table('cluster_master')
                ->where('cluster_id', $clusterId)
                ->where('is_active', true)
                ->first();

            if (!$cluster) {
                $missing[] = 'Cluster ID must match an active cluster in the master list';
            }
        }

        if (!$sku->title || strlen(trim($sku->title)) === 0) {
            $missing[] = 'Title';
        }
        
        if (!$sku->short_description || strlen(trim($sku->short_description)) < 50) {
            $missing[] = 'Short description (min 50 characters)';
        }
        
        if (count($missing) > 0) {
            return new GateResult(
                gate: GateType::G1_BASIC_INFO,
                passed: false,
                reason: 'Missing required fields: ' . implode(', ', $missing),
                blocking: true,
                metadata: ['error_code' => 'CIE_G1_INVALID_CLUSTER', 'user_message' => 'Complete required basic info and choose a valid cluster.']
            );
        }
        
        return new GateResult(
            gate: GateType::G1_BASIC_INFO,
            passed: true,
            reason: 'All required basic information fields (incl. Cluster_ID) are present',
            blocking: false
        );
    }
}
