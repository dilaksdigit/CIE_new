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

        // SOURCE: MASTER§5 — thresholds from BusinessRules only, never hard-coded
        $minChars = BusinessRules::get('gates.answer_block_min_chars');
        $maxChars = BusinessRules::get('gates.answer_block_max_chars');
        if ($minChars === null || $maxChars === null) {
            \Illuminate\Support\Facades\Log::error('G4: BusinessRules missing gates.answer_block_min_chars or gates.answer_block_max_chars');
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: 'Gate configuration missing — contact administrator',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G4_CHAR_LIMIT',
                    'user_message' => 'System configuration error. Please contact your administrator.',
                    'detail' => 'BusinessRules gates.answer_block_min_chars or max_chars not configured'
                ]
            );
        }
        $minLen = (int) $minChars;
        $maxLen = (int) $maxChars;

        // SOURCE: ENF§2.2 — G4 SUSPENDED for Harvest/Kill → not_applicable
        if ($sku->tier === TierType::HARVEST || $sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: true,
                reason: 'not_applicable',
                blocking: false,
                metadata: ['status' => 'not_applicable', 'user_message' => null]
            );
        }

        if ($len < $minLen) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: "Your answer block is too short ({$len} characters, minimum {$minLen}).",
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G4_CHAR_LIMIT',
                    'detail' => "Answer block too short ({$len} chars, min {$minLen})",
                    'user_message' => "Your answer block is too short. Use between {$minLen} and {$maxLen} characters."
                ]
            );
        }

        if ($len > $maxLen) {
            return new GateResult(
                gate: GateType::G4_ANSWER_BLOCK,
                passed: false,
                reason: "Your answer block is too long ({$len} characters, maximum {$maxLen}).",
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G4_CHAR_LIMIT',
                    'detail' => "Answer block too long ({$len} chars, max {$maxLen})",
                    'user_message' => "Your answer block is too long. Use between {$minLen} and {$maxLen} characters."
                ]
            );
        }

        // SOURCE: ENF§Page18 — G4 only has CIE_G4_CHAR_LIMIT and CIE_G4_KEYWORD_MISSING. Brand/marketing checks NOT part of G4 publish gate. GAP_LOG: Architect to decide — move to AI Agent advisory or remove.
        // SOURCE: openapi.yaml SkuValidateRequest, CIE_Master_Developer_Build_Spec.docx §7 G4 — primary intent keyword(s) in answer block
        // Keyword check (stemmed)
        $primaryIntentNode = $sku->skuIntents->where('is_primary', true)->first();
        if ($primaryIntentNode) {
            $raw = strtolower(str_replace([' ', '-', '/'], '_', $primaryIntentNode->intent->name ?? ''));
            $intent = preg_replace('/_+/', '_', trim($raw, '_'));
            $answerLower = strtolower($answer);
            if (! $this->answerContainsIntentKeyword($intent, $answerLower)) {
                return new GateResult(
                    gate: GateType::G4_ANSWER_BLOCK,
                    passed: false,
                    reason: 'Your answer block must include the primary intent keyword.',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G4_KEYWORD_MISSING',
                        'detail' => 'Primary intent keyword missing from answer block',
                        'user_message' => 'Your answer block must include the primary intent keyword.'
                    ]
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

    /**
     * SOURCE: openapi.yaml SkuValidateRequest primary_intent enum — all nine intents must map to a keyword check (mirrors backend/python/api/gates_validate.py INTENT_KEYWORDS).
     */
    private function answerContainsIntentKeyword(string $intentNorm, string $answerLower): bool
    {
        $kw = $this->getStemmedKeywordOrList($intentNorm);
        if ($kw === [] || $kw === '') {
            return true;
        }
        if (is_string($kw)) {
            return str_contains($answerLower, $kw);
        }
        foreach ($kw as $fragment) {
            if ($fragment !== '' && str_contains($answerLower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /** @return string|list<string> */
    private function getStemmedKeywordOrList(string $intent): array|string
    {
        switch ($intent) {
            case 'compatibility': return 'compat';
            case 'inspiration': return 'inspir';
            case 'inspiration_style': return 'inspir';
            case 'problem_solving': return 'solut';
            case 'specification': return 'spec';
            case 'comparison': return 'compar';
            case 'installation':
            case 'installation_how_to': return 'install';
            // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — troubleshooting keyword stems (aligned with Python INTENT_KEYWORDS)
            case 'troubleshooting': return ['troubleshoot', 'trouble', 'fix', 'issue', 'problem', 'repair', 'diagnose'];
            // SOURCE: CIE_v2_3_1_Enforcement_Dev_Spec §8.3 — regulatory keyword stems
            case 'regulatory':
            case 'regulatory_safety':
            case 'safety_compliance':
            case 'safety/compliance': return ['regulation', 'safety', 'compliance', 'standard', 'certified', 'bs 7671', 'ce', 'ip rating'];
            case 'replacement':
            case 'replacement_refill': return 'replac';
            case 'bulk_trade':
            case 'bulk/trade': return ['bulk', 'trade', 'wholesale', 'quantity', 'pack'];
            default: return '';
        }
    }
}
