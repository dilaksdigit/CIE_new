<?php
// SOURCE: ENF§2.1 — G6 (tier tag) and G6.1 (tier-locked intents / Kill edit block) are separate gates
// SOURCE: ENF§7.2 — Response includes both G6_tier_tag and G6_1_tier_lock keys

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Models\IntentTaxonomy;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G6_CommercialPolicyGate implements GateInterface
{
    // SOURCE: ENF§2.1 — G6 = tier enum only; G6.1 = tier-locked intents / Kill block. Returns array of GateResult.
    public function validate(Sku $sku): array
    {
        $results = [];

        // G6: Tier tag validation
        $tierRaw = $sku->tier;
        if ($tierRaw === null || (is_string($tierRaw) && trim((string) $tierRaw) === '')) {
            $results[] = new GateResult(
                gate: GateType::G6_TIER_TAG,
                passed: false,
                reason: 'SKU has no tier assignment',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G6_MISSING_TIER',
                    'user_message' => 'This product has no tier assignment. Contact your administrator.',
                    'detail' => 'SKU has no tier assignment'
                ]
            );
            return $results;
        }

        $results[] = new GateResult(
            gate: GateType::G6_TIER_TAG,
            passed: true,
            reason: 'Tier valid',
            blocking: false,
            metadata: []
        );

        // G6.1: Tier-locked intents / Kill block
        if ($sku->tier === TierType::KILL) {
            $results[] = new GateResult(
                gate: GateType::G6_1_TIER_LOCK,
                passed: false,
                reason: 'Kill-tier SKU: all edits blocked',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G6_1_KILL_EDIT_BLOCKED',
                    'user_message' => 'This product is flagged for delisting. No edits are permitted.',
                    'detail' => 'Kill-tier SKU: all edits blocked'
                ]
            );
            return $results;
        }

        // SOURCE: ENF§2.2 — Harvest: G2 Primary Intent REQUIRED (Spec only). ENF§2.1 G6.1 — Harvest: only Specification + 1 other intent.
        if ($sku->tier === TierType::HARVEST) {
            $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
            $primaryNorm = $primaryIntentNode && $primaryIntentNode->intent
                ? strtolower(trim(str_replace([' ', '-', '/'], '_', $primaryIntentNode->intent->name ?? '')))
                : '';
            if ($primaryNorm !== 'specification') {
                $results[] = new GateResult(
                    gate: GateType::G6_1_TIER_LOCK,
                    passed: false,
                    reason: 'Harvest primary intent must be Specification',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED',
                        'detail' => "Harvest tier requires primary intent = specification, got '{$primaryNorm}'",
                        'user_message' => 'Harvest products must have Specification as their main intent.'
                    ]
                );
                return $results;
            }

            $secondaryIntents = $sku->skuIntents->where('is_primary', false);
            if ($secondaryIntents->count() > 1) {
                $results[] = new GateResult(
                    gate: GateType::G6_1_TIER_LOCK,
                    passed: false,
                    reason: 'Harvest allows max 1 secondary intent',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED',
                        'user_message' => 'This SKU\'s tier does not permit the selected intent fields.',
                        'detail' => 'Intent field not permitted for this SKU\'s tier.'
                    ]
                );
                return $results;
            }
            $allowedSecondary = ['problem_solving', 'compatibility', 'specification'];
            foreach ($secondaryIntents as $si) {
                $intentName = strtolower($si->intent->name ?? '');
                $intentKey  = str_replace(' ', '_', $intentName);
                if (!in_array($intentKey, $allowedSecondary, true)) {
                    $results[] = new GateResult(
                        gate: GateType::G6_1_TIER_LOCK,
                        passed: false,
                        reason: 'Harvest secondary not in allowed intents',
                        blocking: true,
                        metadata: [
                            'error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED',
                            'user_message' => 'This SKU\'s tier does not permit the selected intent fields.',
                            'detail' => 'Intent field not permitted for this SKU\'s tier.'
                        ]
                    );
                    return $results;
                }
            }
            $results[] = new GateResult(
                gate: GateType::G6_1_TIER_LOCK,
                passed: true,
                reason: 'Harvest tier-lock valid',
                blocking: false,
                metadata: []
            );
            return $results;
        }

        // Hero/Support: tier-locked intents via canonical tier_access
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
                    $results[] = new GateResult(
                        gate: GateType::G6_1_TIER_LOCK,
                        passed: false,
                        reason: "The selected intent is not permitted for this product's tier.",
                        blocking: true,
                        metadata: [
                            'error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED',
                            'user_message' => 'This SKU\'s tier does not permit the selected intent fields.',
                            'detail' => 'Intent field not permitted for this SKU\'s tier.'
                        ]
                    );
                    return $results;
                }
            }
        }

        $results[] = new GateResult(
            gate: GateType::G6_1_TIER_LOCK,
            passed: true,
            reason: 'Tier-lock valid',
            blocking: false,
            metadata: []
        );
        return $results;
    }
}
