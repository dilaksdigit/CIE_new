<?php

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Models\IntentTaxonomy;
use App\Enums\GateType;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G2_IntentGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf Section 2.1 — "Exactly 1 Primary Intent"
        $primaryCount = $sku->skuIntents->where('is_primary', true)->count();

        if ($primaryCount === 0) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Primary Intent required.',
                blocking: true,
                metadata: ['error_code' => 'G2_PRIMARY_INTENT_REQUIRED', 'user_message' => 'Main search intent missing. Add the primary intent to match what customers search for.']
            );
        }

        if ($primaryCount > 1) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: "Exactly 1 Primary Intent required. Found {$primaryCount}.",
                blocking: true,
                metadata: ['error_code' => 'G2_DUPLICATE_PRIMARY_INTENT', 'user_message' => 'Only 1 main search intent is allowed. Remove the extra intent.']
            );
        }

        $primaryIntent = $sku->skuIntents->where('is_primary', true)->first();

        $intentName = $primaryIntent->intent->name ?? '';

        // Look up in canonical intent_taxonomy (label + intent_key)
        $taxonomyMatch = IntentTaxonomy::query()
            ->whereRaw('LOWER(label) = ?', [strtolower($intentName)])
            ->orWhereRaw('LOWER(intent_key) = ?', [strtolower(str_replace(' ', '_', $intentName))])
            ->first();

        if (!$taxonomyMatch) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: "Intent '{$intentName}' is not a valid Primary Intent.",
                blocking: true,
                metadata: ['error_code' => 'CIE_G2_INTENT_TAXONOMY', 'user_message' => 'Main search intent missing. Add a valid primary intent to match what customers search for.']
            );
        }

        // G2: Primary intent must appear in the title (stemmed/keyword match).
        $title = strtolower(trim((string) ($sku->title ?? '')));
        $keyword = $this->intentTitleKeyword($intentName, $taxonomyMatch->intent_key ?? '');
        if ($keyword !== '' && $title !== '' && strpos($title, $keyword) === false) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: "Primary intent '{$intentName}' must appear in the title (e.g. keyword: {$keyword}).",
                blocking: true,
                metadata: ['error_code' => 'CIE_G2_INTENT_IN_TITLE', 'user_message' => "Main search intent missing. Add \"{$keyword}\" to match what customers search for.", 'terms' => $keyword]
            );
        }

        return new GateResult(
            gate: GateType::G2_INTENT,
            passed: true,
            reason: "Primary Intent '{$intentName}' validated against canonical taxonomy and title.",
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
