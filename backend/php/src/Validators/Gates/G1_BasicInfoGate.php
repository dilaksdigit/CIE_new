<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class G1_BasicInfoGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        $missing = [];
        
        if (!$sku->sku_code || strlen(trim($sku->sku_code)) === 0) {
            $missing[] = 'SKU code';
        }

        if (!$sku->primary_cluster_id) {
            $missing[] = 'Cluster_ID (Gate G1 requirement)';
        } else {
            $clusterId = $sku->primary_cluster_id;
            if (Schema::hasTable('cluster_master')) {
                $exists = DB::table('cluster_master')
                    ->where('id', $clusterId)
                    ->orWhere('cluster_id', $clusterId)
                    ->exists();
                if (!$exists) {
                    $missing[] = 'Cluster_ID must match an existing record in cluster_master';
                }
            } else {
                if (!$sku->primaryCluster) {
                    $missing[] = 'Cluster_ID must match an existing cluster (exact match in cluster list)';
                }
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
                metadata: ['error_code' => 'CIE_G1_CLUSTER_REQUIRED', 'user_message' => 'Complete required basic info and choose a valid cluster.']
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
