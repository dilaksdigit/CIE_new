<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.1
namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Support\BusinessRules;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G4_AnswerBlockGate implements GateInterface
{
    public function validate(Sku $sku): GateResult
    {
        $answer = trim((string) ($sku->ai_answer_block ?? ''));
        $len = strlen($answer);

        $minLen = (int) BusinessRules::get('gates.answer_block_min_chars');
        $maxLen = (int) BusinessRules::get('gates.answer_block_max_chars');
        
        // SOURCE: CIE_Master_Developer_Build_Spec.docx Section 7 — G4 SUSPENDED for Harvest/Kill
        if ($sku->tier === TierType::HARVEST || $sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: true,
                reason: 'Answer block check is not required for this product tier.',
                blocking: false
            );
        }

        if ($len < $minLen) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: 'Your answer block is too short. It must be at least 250 characters.',
                blocking: true
            );
        }

        if ($len > $maxLen) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: 'Your answer block is too long. It must be no more than 300 characters.',
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
                    reason: 'Your answer block cannot start with the brand name.',
                    blocking: true
                );
            }

            // Simple marketing-fluff heuristic: too many brand mentions relative to length (§5.3: not in 52 rules; hard-coded)
            $brandCount = substr_count($normalizedAnswer, $normalizedBrand);
            $brandCountThreshold = 3;
            $lenGuard            = 400;
            if ($brandCount >= $brandCountThreshold && $len < $lenGuard) {
                return new GateResult(
                    gate: GateType::G4_ANSWER_BLOCK,
                    passed: false,
                    reason: 'Your answer block appears to be marketing copy. Reduce brand mentions and focus on the customer question.',
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
                    reason: 'Your answer block must include the primary intent keyword.',
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
