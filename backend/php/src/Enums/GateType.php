<?php
namespace App\Enums;
enum GateType: string
{
 case G1_BASIC_INFO = 'G1_BASIC_INFO';
 case G2_IMAGES = 'G2_IMAGES';
 case G2_INTENT = 'G2_INTENT';
 case G3_SEO = 'G3_SEO';
 case G3_SECONDARY_INTENT = 'G3_SECONDARY_INTENT';
 case G4_ANSWER_BLOCK = 'G4_ANSWER_BLOCK';
 case G4_VECTOR = 'G4_VECTOR';   // Vector gate (distinct from G5_VECTOR; see CIE_v2.3.1_Enforcement_Dev_Spec.pdf §7)
 case G5_VECTOR = 'G5_VECTOR';   // Best-For/Not-For gate (distinct from G4_VECTOR)
 case G5_BEST_NOT_FOR = 'G5_BEST_NOT_FOR';
 case G5_TECHNICAL = 'G5_TECHNICAL';
 case G6_COMMERCIAL = 'G6_COMMERCIAL';
 case G6_COMMERCIAL_POLICY = 'G6_COMMERCIAL_POLICY';
 case G6_DESCRIPTION_QUALITY = 'G6_DESCRIPTION_QUALITY';
 case G7_EXPERT = 'G7_EXPERT';
 
 public function displayName(): string
 {
 return match($this) {
 self::G1_BASIC_INFO => 'Basic Information',
 self::G2_IMAGES => 'Images',
 self::G2_INTENT => 'Primary Intent',
 self::G3_SEO => 'SEO Metadata',
 self::G3_SECONDARY_INTENT => 'Secondary Intent',
 self::G4_ANSWER_BLOCK => 'Answer Block',
 self::G4_VECTOR => 'Semantic Validation',
 self::G5_VECTOR => 'Semantic (Vector) Validation',
 self::G5_BEST_NOT_FOR => 'Best-For / Not-For',
 self::G5_TECHNICAL => 'Technical Specifications',
 self::G6_COMMERCIAL => 'Commercial Data',
 self::G6_COMMERCIAL_POLICY => 'Commercial Policy',
 self::G6_DESCRIPTION_QUALITY => 'Description Quality',
 self::G7_EXPERT => 'Channel Readiness',
 };
 }
 
 public function isBlockingForTier(TierType $tier): bool
 {
 // G1-G6, G6.1 always blocking
 if ($this !== self::G7_EXPERT) {
 return true;
 }
 
 // G7 only blocking for Hero and Support
 return in_array($tier, [TierType::HERO, TierType::SUPPORT]);
 }
}
