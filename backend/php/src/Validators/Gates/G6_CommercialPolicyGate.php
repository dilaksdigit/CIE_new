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
