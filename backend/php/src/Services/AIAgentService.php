<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — AI Agent specificity check

namespace App\Services;

/**
 * Deterministic specificity check for expert authority statements.
 *
 * Verifies that a given text references a recognised standard, certification,
 * or technical specification (e.g. BS 7671, CE, UKCA, IP rating).
 * Rejects generic marketing phrases that carry no verifiable authority.
 */
class AIAgentService
{
    private const STANDARD_PATTERN = '/(?:'
        . '\bBS\s*\d+'
        . '|\bISO\s*\d+'
        . '|\bEN\s*\d+'
        . '|\bIEC\s*\d+'
        . '|\bIEEE\s*\d+'
        . '|\bANSI\b'
        . '|\bUL\s*\d+'
        . '|\bNEC\b'
        . '|\bATEX\b'
        . '|\bRoHS\b'
        . '|\bREACH\b'
        . '|\bUKCA\b'
        . '|\bCE\b'
        . '|\bIP\d{2}\b'
        . '|\bClass\s+[I12]\b'
        . '|\bRated\s+to\b'
        . '|\b\d+\s*[AW]\s*\/\s*\d+\s*[AW]\b'
        . ')/i';

    /**
     * Return true when $text references a recognised standard, certification,
     * or rated specification. Return false for empty or generic content.
     */
    public static function checkSpecificity(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match(self::STANDARD_PATTERN, $trimmed);
    }
}
