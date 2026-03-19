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
        // Harvest tier: G3 suspended per Hardening_Addendum Patch 6
        if ($sku->tier === TierType::HARVEST) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: true,
                reason: 'N/A',
                blocking: false,
                metadata: ['status' => 'N/A']
            );
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
                    reason: 'A secondary intent cannot be the same as your primary intent. Choose a different secondary intent.',
                    blocking: true,
                    metadata: ['user_message' => 'A secondary intent cannot be the same as your primary intent. Choose a different secondary intent.']
                );
            }
        }

        // Min 1 for Hero/Support (CLAUDE.md Section 6 G3)
        if (in_array($sku->tier, [TierType::HERO, TierType::SUPPORT]) && $count < 1) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: 'You must add at least one secondary intent. Secondary intents help the system understand the full range of what this product can do.',
                blocking: true,
                metadata: ['user_message' => 'You must add at least one secondary intent. Secondary intents help the system understand the full range of what this product can do.']
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
                reason: $msg,
                blocking: true,
                metadata: ['user_message' => $msg]
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
                    reason: 'One or more of your secondary intents is not in the approved list. Use only the options available in the dropdown.',
                    blocking: true,
                    metadata: ['user_message' => 'One or more of your secondary intents is not in the approved list. Use only the options available in the dropdown.']
                );
            }
        }

        if ($sku->tier === TierType::KILL && $count > 0) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: 'This product has been marked for removal and cannot have secondary intents.',
                blocking: true,
                metadata: ['user_message' => 'This product has been marked for removal and cannot have secondary intents.']
            );
        }

        return new GateResult(
            gate: GateType::G3_SECONDARY_INTENT,
            passed: true,
            reason: 'Secondary intents validated.',
            blocking: false
        );
    }
}
