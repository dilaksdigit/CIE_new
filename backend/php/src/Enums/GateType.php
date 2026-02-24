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
 case G4_VECTOR = 'G4_VECTOR';   // Legacy alias; use G5_VECTOR for semantic/vector gate
 case G5_VECTOR = 'G5_VECTOR';   // Semantic validation (cluster match)
 case G5_TECHNICAL = 'G5_TECHNICAL';
 case G6_COMMERCIAL = 'G6_COMMERCIAL';
 case G6_COMMERCIAL_POLICY = 'G6_COMMERCIAL_POLICY';
 case G7_EXPERT = 'G7_EXPERT';
 
 public function displayName(): string
 {
 return match($this) {
 self::G1_BASIC_INFO => 'G1 - Basic Information',
 self::G2_IMAGES => 'G2 - Images',
 self::G2_INTENT => 'G2 - Primary Intent',
 self::G3_SEO => 'G3 - SEO Metadata',
 self::G3_SECONDARY_INTENT => 'G3 - Secondary Intent',
 self::G4_ANSWER_BLOCK => 'G4 - Answer Block',
 self::G4_VECTOR => 'G4 - Semantic Validation (legacy)',
 self::G5_VECTOR => 'G5 - Semantic (Vector) Validation',
 self::G5_TECHNICAL => 'G5 - Technical Specifications',
 self::G6_COMMERCIAL => 'G6 - Commercial Data',
 self::G6_COMMERCIAL_POLICY => 'G6.1 - Commercial Policy',
 self::G7_EXPERT => 'G7 - Expert Authority',
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
