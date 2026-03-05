<?php
namespace App\Enums;
enum ValidationStatus: string
{
    case DRAFT = 'DRAFT';
    // SOURCE: CIE_v232_Hardening_Addendum.pdf §1.1 (Patch 1 — VECTOR_PENDING)
    // SOURCE: openapi.yaml ValidationResponse.status enum — 'pending' = VECTOR_PENDING
    // VALID USE: VECTOR gate ONLY — when OpenAI embedding API is unavailable.
    // NOT a review-queue state. Review Queue permanently eliminated per
    // CIE_v232_Developer_Amendment_Pack_v2.docx §4.2.
    // Behaviour: save allowed, publish BLOCKED, retry queued automatically.
    case PENDING = 'pending';
    case VALID = 'VALID';
    case INVALID = 'INVALID';
    case DEGRADED = 'DEGRADED'; // Fail-soft state
 
 public function canPublish(): bool
 {
 return $this === self::VALID;
 }
 
 public function color(): string
 {
 return match($this) {
 self::DRAFT => '#9CA3AF',
 self::PENDING => '#F59E0B',
 self::VALID => '#10B981',
 self::INVALID => '#EF4444',
 self::DEGRADED => '#F97316',
 };
 }
}
