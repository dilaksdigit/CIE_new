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
    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §8.3 — intent_key is snake_case;
     * labels contain hyphens/slashes that must all normalize to underscores
     */
    private static function normalizeIntentKey(string $name): string
    {
        $raw = strtolower($name);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $raw), '_');
    }

    public function validate(Sku $sku): GateResult
    {
        $tier = $sku->tier instanceof TierType ? $sku->tier->value : strtolower((string) $sku->tier);

        // SOURCE: ENF§2.2 — Kill: G3 = N/A. Do not validate secondaries for Kill.
        if ($tier === TierType::KILL->value) {
            return GateResult::notApplicable(
                GateType::G3_SECONDARY_INTENT,
                'G3 is not applicable for Kill-tier SKUs.'
            );
        }

        // SOURCE: ENF§2.2, ENF§8.3 — Harvest G3 optional; if secondaries present, max 1 from [problem_solving, compatibility, specification]
        if ($tier === TierType::HARVEST->value) {
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §8.3 — intent_key is snake_case; labels use hyphens/slashes (display-only)
            $secondaries = $sku->skuIntents->where('is_primary', false)->map(
                fn ($si) => self::normalizeIntentKey((string) ($si->intent->name ?? ''))
            )->values()->all();
            if (empty($secondaries)) {
                // SOURCE: CIE_Doc4b_Golden_Test_Data_Pack §3.1 — Harvest G3 = N/A when 0 secondaries
                // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §2.2 — G3 is OPTIONAL (max 1) for Harvest
                return GateResult::notApplicable(
                    GateType::G3_SECONDARY_INTENT,
                    'Secondary intents are optional for Harvest-tier SKUs.'
                );
            }
            // SOURCE: CIE_Master_Developer_Build_Spec.docx §5.2 — must throw on missing key.
            $maxHarvest = (int) BusinessRules::get('gates.harvest_max_secondary');
            if (count($secondaries) > $maxHarvest) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Too many secondary intents for Harvest',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_COUNT',
                        'user_message' => "Harvest products allow a maximum of {$maxHarvest} supporting intent.",
                        'detail' => "Harvest tier allows max {$maxHarvest} secondary intent"
                    ]
                );
            }
            $allowedKeys = ['problem_solving', 'compatibility', 'specification'];
            if (!in_array($secondaries[0], $allowedKeys, true)) {
                // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Page 18, ENF§2.1 G6.1 — tier-locked intent is G6.1, not G3 count
                return new GateResult(
                    gate: GateType::G6_1_TIER_LOCK,
                    passed: false,
                    reason: 'Secondary not in Harvest allowed set',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G6_1_TIER_INTENT_BLOCKED',
                        'user_message' => 'Harvest products only allow Problem-Solving, Compatibility, or Specification as a supporting intent.',
                        'detail' => 'Intent field not permitted for this SKU\'s tier.'
                    ]
                );
            }
            $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
            $primaryIntentName = $primaryIntentNode ? ($primaryIntentNode->intent->name ?? '') : '';
            $primaryKey = self::normalizeIntentKey($primaryIntentName);
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
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G3 — compare normalized keys, not raw labels
        $primaryNorm = self::normalizeIntentKey($primaryIntentName);
        foreach ($secondaryIntents as $si) {
            if (self::normalizeIntentKey($si->intent->name ?? '') === $primaryNorm) {
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

        // SOURCE: CIE_v2.3_Enforcement_Edition.pdf §1.1 — G3 requires uniqueness across the full secondary intent set.
        $secondaryNormalized = $secondaryIntents
            ->map(fn ($si) => self::normalizeIntentKey((string) ($si->intent->name ?? '')))
            ->filter(fn ($k) => $k !== '')
            ->values();
        if ($secondaryNormalized->count() !== $secondaryNormalized->unique()->count()) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: 'Duplicate secondary intents detected',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G3_SECONDARY_DUPLICATE',
                    'detail' => 'Duplicate secondary intents detected',
                    'user_message' => 'Each secondary intent must be unique. Remove duplicate selections.'
                ]
            );
        }

        // Min 1 for Hero/Support (CLAUDE.md Section 6 G3)
        if ($tier === TierType::HERO->value || $tier === TierType::SUPPORT->value) {
            if ($count < 1) {
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
        }

        // SOURCE: CIE_Master_Developer_Build_Spec §5 — max secondary counts from business_rules (gates.{tier}_max_secondary)
        $maxSecondary = (int) BusinessRules::get('gates.' . $tier . '_max_secondary');
        if (($tier === TierType::HERO->value || $tier === TierType::SUPPORT->value) && $count > $maxSecondary) {
            $msg = "You can select a maximum of {$maxSecondary} secondary intents for this tier. Remove the extras and keep the most relevant ones.";
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
            $key = self::normalizeIntentKey($name);
            $found = $taxonomyRows->contains(function ($r) use ($key) {
                $taxonomyKey = self::normalizeIntentKey((string) ($r->intent_key ?? ''));
                $taxonomyLabel = self::normalizeIntentKey((string) ($r->label ?? ''));
                return $taxonomyKey === $key || $taxonomyLabel === $key;
            });
            if ($name !== '' && !$found) {
                // GAP_LOG: No spec code for invalid secondary enum value. SOURCE: ENF Page 18 — reuse CIE_G3_SECONDARY_COUNT until architect extends table
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: 'Secondary intent not in approved list',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G3_SECONDARY_COUNT',
                        'detail' => "Secondary intent '" . $name . "' is not in the locked 9-intent taxonomy.",
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
