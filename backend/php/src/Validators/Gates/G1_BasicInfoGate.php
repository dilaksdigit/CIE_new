<?php
// SOURCE: CLAUDE.md Rule R3 + CIE_v231_Developer_Build_Pack G1 gate spec; CIE_v232_Developer_Amendment_Pack Section 8 (gate code rule)

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;

class G1_BasicInfoGate implements GateInterface
{
    /** Writer-facing message when cluster is missing or invalid — no gate codes (CLAUDE.md R3). */
    private const CLUSTER_MESSAGE = "Your product must be assigned to an approved topic cluster before it can be saved.\nSelect a valid cluster from the list, or contact your SEO Governor if the correct cluster is missing.";

    public function validate(Sku $sku): GateResult
    {
        $missing = [];

        if (!$sku->sku_code || strlen(trim($sku->sku_code)) === 0) {
            $missing[] = 'SKU code';
        }

        if (!$sku->primary_cluster_id) {
            return new GateResult(
                gate: GateType::G1_BASIC_INFO,
                passed: false,
                reason: self::CLUSTER_MESSAGE,
                blocking: true,
                metadata: ['user_message' => self::CLUSTER_MESSAGE]
            );
        }

        $clusterId = $sku->primary_cluster_id;
        $cluster = DB::table('cluster_master')
            ->where('cluster_id', $clusterId)
            ->where('is_active', true)
            ->first();

        if (!$cluster) {
            return new GateResult(
                gate: GateType::G1_BASIC_INFO,
                passed: false,
                reason: self::CLUSTER_MESSAGE,
                blocking: true,
                metadata: ['user_message' => self::CLUSTER_MESSAGE]
            );
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
                metadata: ['user_message' => 'Complete required basic info: ' . implode(', ', $missing) . '.']
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
