<?php
// SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §2.1 — G6 SKU Tier Tag
// SOURCE: ENF§7.2 — Response includes both G6_tier_tag and G6_1_tier_lock keys

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Models\IntentTaxonomy;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G6_TierTagGate implements GateInterface
{
    private function tierKey(mixed $tierRaw): string
    {
        if ($tierRaw instanceof TierType) {
            return strtolower($tierRaw->value);
        }
        if (is_string($tierRaw)) {
            return strtolower(trim($tierRaw));
        }

        return '';
    }

    // SOURCE: ENF§2.1 — G6 = tier enum only; G6.1 = tier-locked intents / Kill block. Returns array of GateResult.
    public function validate(Sku $sku): array
    {
        $results = [];

        // G6: Tier tag validation
        $tierRaw = $sku->tier;
        $tierKey = $this->tierKey($tierRaw);
        $allowedTiers = ['hero', 'support', 'harvest', 'kill'];
        if ($tierKey === '' || !in_array($tierKey, $allowedTiers, true)) {
            $results[] = new GateResult(
                gate: GateType::G6_TIER_TAG,
                passed: false,
                reason: 'SKU has no valid tier assignment',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G6_MISSING_TIER',
                    'user_message' => 'This product has no tier assignment. Contact your administrator.',
                    'detail' => $tierKey === ''
                        ? 'SKU has no tier assignment'
                        : "SKU tier '{$tierKey}' is invalid. Allowed: hero, support, harvest, kill"
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
        // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §2.1 G6.1 — Kill: "Any edit = violation"
        // SOURCE: CLAUDE.md DECISION-006 — Kill SKU = Total Lockout
        if ($tierKey === 'kill') {
            $results[] = new GateResult(
                gate: GateType::G6_1_TIER_LOCK,
                passed: false,
                reason: 'Kill-tier SKU: all edits blocked.',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G6_1_KILL_EDIT_BLOCKED',
                    'detail' => 'Kill-tier SKU: all edits blocked.',
                    'user_message' => 'This product is marked as Kill tier. No editing is permitted.',
                ]
            );

            return $results;
        }

        // SOURCE: ENF§2.2 — Harvest: G2 Primary Intent REQUIRED (Spec only). ENF§2.1 G6.1 — Harvest: only Specification + 1 other intent.
        if ($tierKey === 'harvest') {
            $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §8.3 — same underscore-key normalization as Harvest secondaries (§2.2)
            $primaryNorm = $primaryIntentNode && $primaryIntentNode->intent
                ? trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) ($primaryIntentNode->intent->name ?? ''))), '_')
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
                // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §8.3 — intent_key is snake_case;
                // labels contain hyphens/slashes that must all normalize to underscores
                $intentName = strtolower($si->intent->name ?? '');
                $intentKey = trim(preg_replace('/[^a-z0-9]+/', '_', $intentName), '_');
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

        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 G6.1 — HERO: all 9 intents available; none disabled.
        // FIX: G6.1-01 — Hero bypasses intent_taxonomy.tier_access (DB row must not block Hero intents).
        if ($tierKey === 'hero') {
            $results[] = new GateResult(
                gate: GateType::G6_1_TIER_LOCK,
                passed: true,
                reason: 'All intents permitted for hero tier',
                blocking: false,
                metadata: []
            );

            return $results;
        }

        // Support: tier-locked intents via canonical tier_access
        $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
        if ($primaryIntentNode && $primaryIntentNode->intent) {
            $intentName = $primaryIntentNode->intent->name;
            $taxonomy = IntentTaxonomy::query()
                ->whereRaw('LOWER(label) = ?', [strtolower($intentName)])
                ->orWhereRaw('LOWER(intent_key) = ?', [strtolower(str_replace(' ', '_', $intentName))])
                ->first();

            if ($taxonomy) {
                $tierAccess = collect(json_decode($taxonomy->tier_access, true) ?? []);
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
