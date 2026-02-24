<?php
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G4_AnswerBlockGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        $answer = trim((string) ($sku->ai_answer_block ?? ''));
        $len = strlen($answer);

        $minLen = (int) BusinessRules::get('g4.answer_block_min', 250);
        $maxLen = (int) BusinessRules::get('g4.answer_block_max', 300);
        
        // Harvest SKUs have G4 suspended (spec: Harvest maintenance mode)
        if ($sku->tier === 'HARVEST') {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: true,
                reason: 'G4 Suspended for Harvest tier.',
                blocking: false
            );
        }

        if ($len < $minLen) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: "Gate G4 Failed: Answer Block too short ({$len}/{$minLen} min).",
                blocking: true
            );
        }

        if ($len > $maxLen) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: "Gate G4 Failed: Answer Block too long ({$len}/{$maxLen} max).",
                blocking: true
            );
        }

        // Brand name / marketing guardrails
        $brand = env('CIE_BRAND_NAME');
        if ($brand) {
            $normalizedAnswer = strtolower($answer);
            $normalizedBrand  = strtolower($brand);

            // Must NOT start with brand name
            if (str_starts_with($normalizedAnswer, $normalizedBrand)) {
                return new GateResult(
                    gate: GateType::G4_ANSWER_BLOCK,
                    passed: false,
                    reason: "Gate G4 Failed: Answer Block must not start with the brand name ('{$brand}').",
                    blocking: true
                );
            }

            // Simple marketing-fluff heuristic: too many brand mentions relative to length
            $brandCount = substr_count($normalizedAnswer, $normalizedBrand);
            if ($brandCount >= 3 && $len < 400) {
                return new GateResult(
                    gate: GateType::G4_ANSWER_BLOCK,
                    passed: false,
                    reason: "Gate G4 Failed: Answer Block appears to be marketing copy (brand mentioned {$brandCount} times).",
                    blocking: true
                );
            }
        }

        // Keyword check (stemmed)
        $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
        if ($primaryIntentNode) {
            $intent = strtolower($primaryIntentNode->intent->name ?? '');
            $keyword = $this->getStemmedKeyword($intent);
            
            if ($keyword && strpos(strtolower($answer), $keyword) === false) {
                return new GateResult(
                    gate: GateType::G4_ANSWER_BLOCK,
                    passed: false,
                    reason: "Gate G4 Failed: Answer Block must contain the Primary Intent keyword ('{$keyword}').",
                    blocking: true
                );
            }
        }

        return new GateResult(
            gate: GateType::G4_ANSWER_BLOCK,
            passed: true,
            reason: 'AI Answer Block meets length and keyword requirements.',
            blocking: false
        );
    }

    private function getStemmedKeyword(string $intent): string
    {
        switch ($intent) {
            case 'compatibility': return 'compat';
            case 'inspiration': return 'inspir';
            case 'problem-solving': return 'solut';
            case 'specification': return 'spec';
            case 'comparison': return 'compar';
            case 'installation': return 'install'; // Added
            case 'troubleshooting': return 'shoot'; // Added (troubleshoot/shooting)
            case 'regulatory': return 'safe'; // Added (regulatory/safety) - 'safe' is common root
            case 'replacement': return 'replac'; // Added (replace/replacement)
            default: return '';
        }
    }
}
