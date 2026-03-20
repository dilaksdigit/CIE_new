<?php
// SOURCE: CLAUDE.md Section 6 (G3 rule); CIE_v231_Developer_Build_Pack G3 gate spec
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G3_SecondaryIntentGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        // SOURCE: ENF§2.2 — Kill: G3 = N/A. Do not validate secondaries for Kill.
        if ($sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: true,
                reason: 'not_applicable',
                blocking: false,
                metadata: ['status' => 'not_applicable', 'user_message' => null]
            );
        }

        // SOURCE: ENF§2.2, ENF§8.3 — Harvest G3 optional; if secondaries present, max 1 from [problem_solving, compatibility, specification]
        if ($sku->tier === TierType::HARVEST) {
            $secondaries = $sku->skuIntents->where('is_primary', false)->map(fn ($si) => strtolower(str_replace(' ', '_', $si->intent->name ?? '')))->values()->all();
            if (empty($secondaries)) {
                return new GateResult(gate: GateType::G3_SECONDARY_INTENT, passed: true, reason: 'N/A', blocking: false, metadata: ['user_message' => null]);
            }
            if (count($secondaries) > 1) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Too many secondary intents for Harvest',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_COUNT',
                        'user_message' => 'Harvest products allow a maximum of 1 supporting intent.',
                        'detail' => 'Harvest tier allows max 1 secondary intent'
                    ]
                );
            }
            $allowedKeys = ['problem_solving', 'compatibility', 'specification'];
            if (!in_array($secondaries[0], $allowedKeys, true)) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Secondary not in Harvest allowed set',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_COUNT',
                        'user_message' => 'Harvest products only allow Problem-Solving, Compatibility, or Specification as a supporting intent.',
                        'detail' => 'Harvest secondary not in allowed_intents [1,3,4]'
                    ]
                );
            }
            $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
            $primaryIntentName = $primaryIntentNode ? ($primaryIntentNode->intent->name ?? '') : '';
            $primaryKey = strtolower(str_replace(' ', '_', $primaryIntentName));
            if ($secondaries[0] === $primaryKey) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Secondary same as primary',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_DUPLICATE',
                        'user_message' => 'Your supporting intent cannot be the same as the main intent.',
                        'detail' => 'Secondary intent same as primary'
                    ]
                );
            }
            return new GateResult(gate: GateType::G3_SECONDARY_INTENT, passed: true, reason: 'Harvest secondary valid', blocking: false, metadata: ['user_message' => null]);
        }

        $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
        $primaryIntentName = $primaryIntentNode ? ($primaryIntentNode->intent->name ?? '') : '';

        $secondaryIntents = $sku->skuIntents->where('is_primary', false);
        $count = $secondaryIntents->count();

        // Duplicate check: secondary cannot match primary
        foreach ($secondaryIntents as $si) {
            if (($si->intent->name ?? '') === $primaryIntentName) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Secondary intent same as primary',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_DUPLICATE',
                        'detail' => 'Secondary intent same as primary',
                        'user_message' => 'Your supporting intent cannot be the same as the main intent.'
                    ]
                );
            }
        }

        // Min 1 for Hero/Support (CLAUDE.md Section 6 G3)
        if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT]) && $count < 1) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: 'Too few secondary intents',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G3_SECONDARY_COUNT',
                    'detail' => 'At least one secondary intent required',
                    'user_message' => 'You must add at least one secondary intent. Secondary intents help the system understand the full range of what this product can do.'
                ]
            );
        }

        // Max 3 for Hero, max 2 for Support (CIE_v231_Developer_Build_Pack G3)
        $maxSecondary = ($sku->tier === TierType::HERO) ? 3 : 2;
        if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT]) && $count > $maxSecondary) {
            $msg = $maxSecondary === 3
                ? 'You can select a maximum of 3 secondary intents. Remove the extras and keep the most relevant ones.'
                : 'You can select a maximum of 2 secondary intents for this tier. Remove the extras and keep the most relevant one.';
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: 'Too many secondary intents',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G3_SECONDARY_COUNT',
                    'detail' => 'Max secondary intents exceeded',
                    'user_message' => $msg
                ]
            );
        }

        // Invalid value: all secondaries must be in locked 9-intent taxonomy
        $taxonomyRows = \App\Models\IntentTaxonomy::query()->get();
        foreach ($secondaryIntents as $si) {
            $name = $si->intent->name ?? '';
            $key = strtolower(str_replace(' ', '_', $name));
            $label = strtolower($name);
            $found = $taxonomyRows->contains(fn ($r) => strtolower($r->intent_key ?? '') === $key || strtolower($r->label ?? '') === $label);
            if ($name !== '' && !$found) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Secondary intent not in approved list',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_COUNT',
                        'detail' => 'Secondary intent not in locked 9-intent taxonomy',
                        'user_message' => 'One or more of your secondary intents is not in the approved list. Use only the options available in the dropdown.'
                    ]
                );
            }
        }

        return new GateResult(
            gate: GateType::G3_SECONDARY_INTENT,
            passed: true,
            reason: 'Secondary intents validated.',
            blocking: false
        );
    }
}
