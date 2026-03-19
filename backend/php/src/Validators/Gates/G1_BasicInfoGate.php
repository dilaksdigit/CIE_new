<?php
// SOURCE: CLAUDE.md Rule R3 + CIE_v231_Developer_Build_Pack G1 gate spec; CIE_v232_Developer_Amendment_Pack Section 8 (gate code rule)
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3 — Kill tier: all gates suspended

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;

class G1_BasicInfoGate implements GateInterface
{
    /** Writer-facing message when cluster is missing or invalid — no gate codes (CLAUDE.md R3). */
    private const CLUSTER_MESSAGE = "Your product must be assigned to an approved topic cluster before it can be saved.\nSelect a valid cluster from the list, or contact your SEO Governor if the correct cluster is missing.";

    public function validate(Sku $sku): GateResult
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3
        // Kill tier: zero content effort. All gates suspended.
        if ($sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G1_BASIC_INFO,
                passed: true,
                reason: 'suspended',
                blocking: false,
                metadata: ['suspended_for_tier' => 'kill']
            );
        }

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

        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1, Section 7 (G1)
        // GAP_LOG: skus.primary_cluster_id is UUID FK to clusters.id; clusters.name stores the
        // business string matching cluster_master.cluster_id. Spec Section 6.1 defines
        // sku_master.cluster_id as VARCHAR FK directly to cluster_master.cluster_id, but the
        // v1 skus table uses UUID indirection via clusters.id.
        $clusterRecord = $sku->primaryCluster;
        $clusterBusinessId = $clusterRecord ? $clusterRecord->name : null;
        $cluster = DB::table('cluster_master')
            ->where('cluster_id', $clusterBusinessId)
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
