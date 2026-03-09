<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Models\IntentTaxonomy;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G6_CommercialPolicyGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        // G6: Kill-tier absolute lock
        if ($sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G6_COMMERCIAL_POLICY,
                passed: false,
                reason: 'Gate G6 Failed: KILL-tier SKUs must have zero effort. Any edit is a policy violation.',
                blocking: true
            );
        }

        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 Gate G6.1
        // Harvest-tier: only specification + 1 optional secondary intent (problem_solving | compatibility)
        if ($sku->tier === TierType::HARVEST) {
            $harvestBlockedFields = [
                'answer_block', 'best_for', 'not_for',
                'expert_authority', 'title', 'long_description', 'wikidata_uri',
            ];

            foreach ($harvestBlockedFields as $field) {
                $value = $sku->getAttribute($field);
                if ($value !== null && $value !== '' && $value !== '[]') {
                    return new GateResult(
                        gate: GateType::G6_COMMERCIAL_POLICY,
                        passed: false,
                        reason: "Gate G6.1 Failed: Harvest tier SKU attempted write to restricted field '{$field}'. Only Specification + 1 optional intent allowed.",
                        blocking: true,
                        metadata: ['error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED']
                    );
                }
            }

            $secondaryIntents = $sku->skuIntents->where('is_primary', false);
            if ($secondaryIntents->count() > 1) {
                return new GateResult(
                    gate: GateType::G6_COMMERCIAL_POLICY,
                    passed: false,
                    reason: 'Gate G6.1 Failed: Harvest tier allows max 1 secondary intent. Found: ' . $secondaryIntents->count() . '.',
                    blocking: true,
                    metadata: ['error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED']
                );
            }

            $allowedSecondary = ['problem_solving', 'compatibility'];
            foreach ($secondaryIntents as $si) {
                $intentName = strtolower($si->intent->name ?? '');
                $intentKey  = str_replace(' ', '_', $intentName);
                if (!in_array($intentKey, $allowedSecondary, true)) {
                    return new GateResult(
                        gate: GateType::G6_COMMERCIAL_POLICY,
                        passed: false,
                        reason: "Gate G6.1 Failed: Harvest tier secondary intent must be 'problem_solving' or 'compatibility'. Got: '{$intentKey}'.",
                        blocking: true,
                        metadata: ['error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED']
                    );
                }
            }
        }

        // G6.1: Tier-locked intents via canonical tier_access
        $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
        if ($primaryIntentNode && $primaryIntentNode->intent) {
            $intentName = $primaryIntentNode->intent->name;

            $taxonomy = IntentTaxonomy::query()
                ->whereRaw('LOWER(label) = ?', [strtolower($intentName)])
                ->orWhereRaw('LOWER(intent_key) = ?', [strtolower(str_replace(' ', '_', $intentName))])
                ->first();

            if ($taxonomy) {
                $tierAccess = collect(json_decode($taxonomy->tier_access, true) ?? []);
                $tierKey = strtolower($sku->tier instanceof TierType ? $sku->tier->value : (string) $sku->tier);

                if (!$tierAccess->contains($tierKey)) {
                    return new GateResult(
                        gate: GateType::G6_COMMERCIAL_POLICY,
                        passed: false,
                        reason: "Gate G6.1 Failed: Intent '{$intentName}' not permitted for tier '{$tierKey}'.",
                        blocking: true
                    );
                }
            }
        }

        return new GateResult(
            gate: GateType::G6_COMMERCIAL_POLICY,
            passed: true,
            reason: 'Commercial tier and effort policy aligned.',
            blocking: false
        );
    }
}
