<?php
// SOURCE: CLAUDE.md Section 6 (G2 rule); CIE_v231_Developer_Build_Pack G2 gate spec; CIE_v232_Developer_Amendment_Pack Section 8

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
                reason: 'You must select exactly one primary intent for this product. Choose the intent that best describes what a customer is trying to accomplish when they find this product.',
                blocking: true,
                metadata: ['user_message' => 'You must select exactly one primary intent for this product. Choose the intent that best describes what a customer is trying to accomplish when they find this product.']
            );
        }

        if ($primaryCount > 1) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Only one primary intent is allowed. Remove the extra selection and keep the one that best fits this product.',
                blocking: true,
                metadata: ['user_message' => 'Only one primary intent is allowed. Remove the extra selection and keep the one that best fits this product.']
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
                reason: 'The intent you selected is not in the approved list. Choose from the available options in the dropdown.',
                blocking: true,
                metadata: ['user_message' => 'The intent you selected is not in the approved list. Choose from the available options in the dropdown.']
            );
        }

        // G2: Primary intent must appear in the title (stemmed/keyword match).
        $title = strtolower(trim((string) ($sku->title ?? '')));
        $keyword = $this->intentTitleKeyword($intentName, $taxonomyMatch->intent_key ?? '');
        if ($keyword !== '' && $title !== '' && strpos($title, $keyword) === false) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Main search intent must appear in the title. Add the intent keyword to match what customers search for.',
                blocking: true,
                metadata: ['user_message' => 'Main search intent must appear in the title. Add the intent keyword to match what customers search for.', 'terms' => $keyword]
            );
        }

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
