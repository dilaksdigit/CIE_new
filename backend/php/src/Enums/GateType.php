<?php
namespace App\Enums;

enum GateType: string
{
    case G1_BASIC_INFO = 'G1_BASIC_INFO';
    case G2_INTENT = 'G2_INTENT';
    case G3_SECONDARY_INTENT = 'G3_SECONDARY_INTENT';
    case G4_ANSWER_BLOCK = 'G4_ANSWER_BLOCK';
    case G4_VECTOR = 'G4_VECTOR';
    case G5_BEST_NOT_FOR = 'G5_BEST_NOT_FOR';
    case G5_TECHNICAL = 'G5_TECHNICAL';
    case G6_COMMERCIAL_POLICY = 'G6_COMMERCIAL_POLICY';
    case G7_EXPERT = 'G7_EXPERT';

    public function displayName(): string
    {
        return match($this) {
            self::G1_BASIC_INFO => 'Basic Information',
            self::G2_INTENT => 'Primary Intent',
            self::G3_SECONDARY_INTENT => 'Secondary Intent',
            self::G4_ANSWER_BLOCK => 'Answer Block',
            self::G4_VECTOR => 'Semantic Validation',
            self::G5_BEST_NOT_FOR => 'Best-For / Not-For',
            self::G5_TECHNICAL => 'Technical Specifications',
            self::G6_COMMERCIAL_POLICY => 'Tier Tag / Commercial Policy',
            self::G7_EXPERT => 'Expert Authority',
        };
    }

    public function isBlockingForTier(TierType $tier): bool
    {
        if ($this !== self::G7_EXPERT) {
            return true;
        }

        return in_array($tier, [TierType::HERO, TierType::SUPPORT]);
    }
}
