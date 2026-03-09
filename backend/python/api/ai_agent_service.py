# SOURCE: CIE_Master_Developer_Build_Spec.docx §7 — AI Agent specificity check
"""
Deterministic specificity check for expert authority statements.

Verifies that expert_authority references a recognised standard,
certification, or technical specification (e.g. BS 7671, CE, UKCA, IP rating).
Rejects generic marketing phrases that carry no verifiable authority.
"""
from __future__ import annotations

import re

_STANDARD_PATTERN = re.compile(
    r'(?:'
    r'\bBS\s*\d+'
    r'|\bISO\s*\d+'
    r'|\bEN\s*\d+'
    r'|\bIEC\s*\d+'
    r'|\bIEEE\s*\d+'
    r'|\bANSI\b'
    r'|\bUL\s*\d+'
    r'|\bNEC\b'
    r'|\bATEX\b'
    r'|\bRoHS\b'
    r'|\bREACH\b'
    r'|\bUKCA\b'
    r'|\bCE\b'
    r'|\bIP\d{2}\b'
    r'|\bClass\s+[I12]\b'
    r'|\bRated\s+to\b'
    r'|\b\d+\s*[AW]\s*/\s*\d+\s*[AW]\b'
    r')',
    re.IGNORECASE,
)


def check_specificity(text: str) -> bool:
    """Return True when *text* references a recognised standard, certification,
    or rated specification. Return False for empty or generic content."""
    if not text or not text.strip():
        return False
    return bool(_STANDARD_PATTERN.search(text))
