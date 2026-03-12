<?php
// SOURCE: CLAUDE.md Section 6 (G6.1); CLAUDE.md Section 7 (Kill = total lockout); Hardening_Addendum Patch 6; CIE_v231_Developer_Build_Pack G6.1 spec

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Models\IntentTaxonomy;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G6_CommercialPolicyGate implements GateInterface
{
    /** Kill tier block — all fields read-only (CLAUDE.md Section 7). */
    private const KILL_MESSAGE = 'This product is in Kill tier. All content fields are read-only. No editing is permitted. If you believe this classification is wrong, contact your Portfolio Holder to request a tier review.';

    /** Harvest suspended field (Hardening_Addendum Patch 6). */
    private const HARVEST_FIELD_MESSAGE = 'This field is not available for Harvest tier products. Focus on Specification data only.';

    public function validate(Sku $sku): GateResult
    {
        $tierRaw = $sku->tier;
        if ($tierRaw === null || (is_string($tierRaw) && trim($tierRaw) === '')) {
            return new GateResult(
                gate: GateType::G6_COMMERCIAL_POLICY,
                passed: false,
                reason: 'SKU has no tier assigned.',
                blocking: true,
                metadata: ['user_message' => 'This SKU has no tier assigned. Contact your administrator.']
            );
        }

        if ($sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G6_COMMERCIAL_POLICY,
                passed: false,
                reason: self::KILL_MESSAGE,
                blocking: true,
                metadata: ['user_message' => self::KILL_MESSAGE]
            );
        }

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
                        reason: self::HARVEST_FIELD_MESSAGE,
                        blocking: true,
                        metadata: ['user_message' => self::HARVEST_FIELD_MESSAGE]
                    );
                }
            }

            $secondaryIntents = $sku->skuIntents->where('is_primary', false);
            if ($secondaryIntents->count() > 1) {
                return new GateResult(
                    gate: GateType::G6_COMMERCIAL_POLICY,
                    passed: false,
                    reason: self::HARVEST_FIELD_MESSAGE,
                    blocking: true,
                    metadata: ['user_message' => self::HARVEST_FIELD_MESSAGE]
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
                        reason: self::HARVEST_FIELD_MESSAGE,
                        blocking: true,
                        metadata: ['user_message' => self::HARVEST_FIELD_MESSAGE]
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
                        reason: "The selected intent is not permitted for this product's tier.",
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
