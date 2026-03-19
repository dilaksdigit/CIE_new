-- Expand validation_logs.gate_type enum to support new gates
-- Aligns with App\Enums\GateType values to prevent ENUM truncation warnings
-- Includes G5_BEST_NOT_FOR and G6_DESCRIPTION_QUALITY (used by G5_TechnicalGate, G6_DescriptionQualityGate)

ALTER TABLE validation_logs
MODIFY COLUMN gate_type ENUM(
    'G1_BASIC_INFO',
    'G2_IMAGES',
    'G2_INTENT',
    'G3_SEO',
    'G3_SECONDARY_INTENT',
    'G4_ANSWER_BLOCK',
    'G4_VECTOR',
    'G5_VECTOR',
    'G5_BEST_NOT_FOR',
    'G5_TECHNICAL',
    'G6_COMMERCIAL',
    'G6_COMMERCIAL_POLICY',
    'G6_DESCRIPTION_QUALITY',
    'G7_EXPERT'
) NOT NULL;

