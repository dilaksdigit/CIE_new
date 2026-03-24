<?php
// SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec §2.1 — G7 Expert Authority
// SOURCE: ENF§Page18 — error code CIE_G7_AUTHORITY_MISSING

namespace App\Validators\Gates;

use App\Models\Sku;
use App\Enums\GateType;
use App\Enums\TierType;
use App\Validators\GateResult;
use App\Validators\GateInterface;

class G7_ExpertAuthorityGate implements GateInterface
{
    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §7 G7 — same rule set as backend/python/api/ai_agent_service.check_specificity (CLAUDE.md R1: no new Python-only HTTP route).
     */
    private function expertAuthorityMatchesPythonSpecificity(string $text): bool
    {
        $pattern = '/(?:\bBS\s*\d+|\bISO\s*\d+|\bEN\s*\d+|\bIEC\s*\d+|\bIEEE\s*\d+|\bANSI\b|\bUL\s*\d+|\bNEC\b|\bATEX\b|\bRoHS\b|\bREACH\b|\bUKCA\b|\bCE\b|\bIP\d{2}\b|\bClass\s+[I12]\b|\bRated\s+to\b|\b\d+\s*[AW]\s*\/\s*\d+\s*[AW]\b)/i';

        return (bool) preg_match($pattern, $text);
    }

    // SOURCE: MASTER§7, ENF§2.1 — G7 = non-empty expert_authority for Hero/Support
    // SOURCE: ENF§Page18 — error code CIE_G7_AUTHORITY_MISSING
    public function validate(Sku $sku): GateResult
    {
        // SOURCE: ENF§2.2 — G7 SUSPENDED for Harvest, N/A for Kill → not_applicable
        if ($sku->tier === TierType::HARVEST || $sku->tier === TierType::KILL) {
            return new GateResult(
                gate: GateType::G7_EXPERT,
                passed: true,
                reason: 'not_applicable',
                blocking: false,
                metadata: ['status' => 'not_applicable', 'user_message' => null]
            );
        }

        // Hero and Support: expert_authority must be non-empty
        $expertAuthority = trim($sku->expert_authority ?? '');

        if (empty($expertAuthority)) {
            return new GateResult(
                gate: GateType::G7_EXPERT,
                passed: false,
                reason: 'Expert authority block is empty',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G7_AUTHORITY_MISSING',
                    'user_message' => 'Add an Expert Authority statement referencing a specific standard, certification, or technical specification.',
                    'detail' => 'expert_authority field is empty for ' . $sku->tier->value . '-tier SKU.'
                ]
            );
        }

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — G7 server-side specificity guard; full AI Agent check is supplementary
        $genericPhrases = ['high quality', 'premium quality', 'best in class', 'top quality', 'industry leading'];
        $lowerAuth = strtolower($expertAuthority);
        foreach ($genericPhrases as $phrase) {
            if (str_contains($lowerAuth, $phrase) && !preg_match('/\b(BS|EN|ISO|IEC|CE|UKCA|UL|CSA)\s*\d/i', $expertAuthority)) {
                return new GateResult(
                    gate: GateType::G7_EXPERT,
                    passed: false,
                    reason: 'Expert authority lacks specific standard or certification reference',
                    blocking: true,
                    metadata: [
                        'error_code' => 'CIE_G7_AUTHORITY_MISSING',
                        'user_message' => 'Your Expert Authority statement must reference a specific standard, certification, or rated specification.',
                        'detail' => 'Generic marketing phrasing without standard/certification reference.',
                    ]
                );
            }
        }

        if (! $this->expertAuthorityMatchesPythonSpecificity($expertAuthority)) {
            return new GateResult(
                gate: GateType::G7_EXPERT,
                passed: false,
                reason: 'Expert authority lacks specific standard or certification reference',
                blocking: true,
                metadata: [
                    'error_code' => 'CIE_G7_AUTHORITY_MISSING',
                    'user_message' => 'Your Expert Authority statement must reference a specific standard, certification, or rated specification.',
                    'detail' => 'expert_authority does not reference a specific standard, certification, or rated specification.',
                ]
            );
        }

        // Non-empty and passed Python-aligned specificity guard — pass
        return new GateResult(
            gate: GateType::G7_EXPERT,
            passed: true,
            reason: 'Expert authority present',
            blocking: false,
            metadata: ['user_message' => null, 'detail' => 'Non-empty for ' . $sku->tier->value . ' tier']
        );
    }
}
