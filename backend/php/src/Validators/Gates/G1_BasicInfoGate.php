<?php
// SOURCE: ENF§2.1 — G1 = Cluster ID: valid cluster from approved semantic contract. ENF§Page18 — CIE_G1_INVALID_CLUSTER = Cluster ID not in approved master list.
// SOURCE: CLAUDE.md Rule R3; CIE_Master_Developer_Build_Spec.docx Section 6.1

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;
use Illuminate\Support\Facades\DB;

class G1_BasicInfoGate implements GateInterface
{
    // SOURCE: ENF§2.1, ENF§Page18 — G1 = cluster_id vs cluster_master ONLY
    public function validate(Sku $sku): GateResult
    {
        $clusterId = $sku->primary_cluster_id ?? '';

        if (empty($clusterId)) {
            return new GateResult(
                gate: GateType::G1_BASIC_INFO,
                passed: false,
                reason: 'Cluster ID missing',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G1_INVALID_CLUSTER',
                    'detail' => 'Cluster ID not in approved master list',
                    'user_message' => 'Select a valid product group from the approved list.'
                ]
            );
        }

        // Resolve primary_cluster_id (UUID) → cluster_master via clusters.name = cluster_id
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
                reason: 'Cluster ID not in master list',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G1_INVALID_CLUSTER',
                    'detail' => 'Cluster ID not in approved master list',
                    'user_message' => 'The selected product group is not recognised. Choose from the approved list.'
                ]
            );
        }

        // SOURCE: CIE_v2_3_Enforcement_Edition.pdf — Gate G1: Intent-first title
        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8
        $blockedPrefixes = (string) BusinessRules::get('gates.g1_blocked_title_prefixes', '');
        $title = strtolower(trim((string) ($sku->title ?? '')));
        foreach (explode(',', $blockedPrefixes) as $prefix) {
            $prefix = strtolower(trim($prefix));
            if ($prefix !== '' && str_starts_with($title, $prefix)) {
                return new GateResult(
                    gate: GateType::G1_BASIC_INFO,
                    passed: false,
                    reason: 'Title starts with blocked brand/attribute prefix',
                    blocking: true,
                    metadata: [
                        'error_code' => 'G1_INTENT_TITLE',
                        'field' => 'title',
                        'detail' => 'Title starts with brand or attribute term: ' . $prefix,
                        'user_message' => 'Start your title with what this product solves or is used for, not the brand or product type.',
                    ]
                );
            }
        }

        // GAP_LOG: Title/description presence checks were in G1 but are not part of G1 per ENF§2.1. Architect to decide: move to pre-validation or create separate check.
        return new GateResult(gate: GateType::G1_BASIC_INFO, passed: true, reason: 'Cluster valid', metadata: []);
    }
}
