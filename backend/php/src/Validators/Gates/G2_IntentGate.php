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
        $primaryIntent = $sku->skuIntents->where('is_primary', true)->first();

        if (!$primaryIntent) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: 'Gate G2 Failed: Primary Intent required.',
                blocking: true
            );
        }

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
                reason: "Gate G2 Failed: Intent '{$intentName}' not in locked 9-intent taxonomy.",
                blocking: true,
                metadata: ['error_code' => 'CIE_G2_INTENT_TAXONOMY', 'user_message' => 'Primary intent must be one of the 9 allowed intents.']
            );
        }

        // G2: Primary intent must appear in the title (stemmed/keyword match).
        $title = strtolower(trim((string) ($sku->title ?? '')));
        $keyword = $this->intentTitleKeyword($intentName, $taxonomyMatch->intent_key ?? '');
        if ($keyword !== '' && $title !== '' && strpos($title, $keyword) === false) {
            return new GateResult(
                gate: GateType::G2_INTENT,
                passed: false,
                reason: "Gate G2 Failed: Primary intent '{$intentName}' must appear in the title (e.g. keyword: {$keyword}).",
                blocking: true,
                metadata: ['error_code' => 'CIE_G2_INTENT_IN_TITLE', 'user_message' => 'Add the main search intent into the product title.', 'terms' => $keyword]
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
