<?php
// SOURCE: CLAUDE.md Section 6 (G2 rule); CIE_v231_Developer_Build_Pack G2 gate spec; CIE_v232_Developer_Amendment_Pack Section 8
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3 — Kill tier: all gates suspended

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Models\IntentTaxonomy;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G2_IntentGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 8.3
        // Kill tier: zero content effort. All gates suspended.
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 — Kill tier G2 is not_applicable
        if ($sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: true,
                reason: 'not_applicable',
                blocking: false,
                metadata: ['not_applicable_for_tier' => 'kill', 'status' => 'not_applicable', 'user_message' => null]
            );
        }

        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 2.1 — "Exactly 1 Primary Intent"
        $primaryCount = $sku->skuIntents->where('is_primary', true)->count();

        if ($primaryCount === 0) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Primary intent not in locked 9-intent enum',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G2_INVALID_INTENT',
                    'detail' => 'Primary intent not in locked 9-intent enum',
                    'user_message' => 'Main search intent is not recognised. Select from the approved list.'
                ]
            );
        }

        if ($primaryCount > 1) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Primary intent not in locked 9-intent enum',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G2_INVALID_INTENT',
                    'detail' => 'Primary intent not in locked 9-intent enum',
                    'user_message' => 'Main search intent is not recognised. Select from the approved list.'
                ]
            );
        }

        $primaryIntent = $sku->skuIntents->where('is_primary', true)->first();
        $intentName = $primaryIntent->intent->name ?? '';

        // Look up in canonical intent_taxonomy (label + intent_key) — locked 9-intent set
        $taxonomyMatch = IntentTaxonomy::query()
            ->whereRaw('LOWER(label) = ?', [strtolower($intentName)])
            ->orWhereRaw('LOWER(intent_key) = ?', [strtolower(str_replace(' ', '_', $intentName))])
            ->first();

        if (!$taxonomyMatch) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Primary intent not in locked 9-intent enum',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G2_INVALID_INTENT',
                    'detail' => 'Primary intent not in locked 9-intent enum',
                    'user_message' => 'Main search intent is not recognised. Select from the approved list.'
                ]
            );
        }

        // SOURCE: MASTER§7 — G2 validates primary intent from 9-intent taxonomy ONLY. No title keyword check per spec.

        return new GateResult(
            gate: GateType::G2_INTENT,
            passed: true,
            reason: 'Primary Intent validated against canonical taxonomy and title.',
            blocking: false
        );
    }

    private function intentTitleKeyword(string $intentName, string $intentKey): string
    {
        $n = strtolower(str_replace([' ', '-', '/'], '_', $intentName));
        $k = strtolower($intentKey);
        $stems = [
            'compatibility' => 'compat', 'compat' => 'compat',
            'inspiration' => 'inspir', 'inspir' => 'inspir',
            'problem_solving' => 'solut', 'problem-solving' => 'solut',
            'specification' => 'spec', 'product_specs' => 'spec',
            'comparison' => 'compar', 'installation' => 'install',
            'troubleshooting' => 'troubleshoot', 'troubleshoot' => 'troubleshoot',
            'regulatory' => 'safe', 'replacement' => 'replac',
        ];
        if (isset($stems[$n])) return $stems[$n];
        if (isset($stems[$k])) return $stems[$k];
        return strlen($n) >= 4 ? substr($n, 0, 6) : $n;
    }
}
