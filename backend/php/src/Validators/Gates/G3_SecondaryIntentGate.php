<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G3_SecondaryIntentGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        // Harvest tier: G3 is OPTIONAL/SUSPENDED — return N/A immediately.
        if ($sku->tier === 'HARVEST') {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: true,
                reason: 'N/A',
                blocking: false,
                metadata: ['status' => 'N/A']
            );
        }

        $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
        $primaryIntentName = $primaryIntentNode->intent->name ?? '';
        
        $secondaryIntents = $sku->skuIntents->where('is_primary', false);
        $count = $secondaryIntents->count();

        // Uniqueness check (Secondary cannot match Primary)
        foreach ($secondaryIntents as $si) {
            if ($si->intent->name === $primaryIntentName) {
                return new GateResult(
                    gate: GateType::G3_SECONDARY_INTENT,
                    passed: false,
                    reason: "Gate G3 Failed: Secondary intent cannot match Primary ('{$primaryIntentName}').",
                    blocking: true
                );
            }
        }

        // Hero/Support minimum requirement
        if (in_array($sku->tier, ['HERO', 'SUPPORT']) && $count < 1) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: "Gate G3 Failed: Hero/Support SKUs require minimum 1 secondary intent.",
                blocking: true
            );
        }

        // Unified max of 3 for Hero and Support (§2.1 Gate Table — G3)
        if (in_array($sku->tier, ['HERO', 'SUPPORT']) && $count > 3) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: "Maximum 3 secondary intents allowed",
                blocking: true
            );
        }

        if ($sku->tier === 'KILL' && $count > 0) {
            return new GateResult(
                gate: GateType::G3_SECONDARY_INTENT,
                passed: false,
                reason: "Gate G3 Failed: Kill-tier SKUs may not have any secondary intents.",
                blocking: true
            );
        }

        return new GateResult(
            gate: GateType::G3_SECONDARY_INTENT,
            passed: true,
            reason: "{$count} Secondary Intents validated.",
            blocking: false
        );
    }
}
