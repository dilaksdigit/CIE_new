-- Expand validation_logs.gate_type enum to support new gates
-- Aligns with App\Enums\GateType values to prevent ENUM truncation warnings
-- Includes G5_BEST_NOT_FOR and G6_DESCRIPTION_QUALITY (used by G5_TechnicalGate, G6_DescriptionQualityGate)

ALTER TABLE validation_logs
MODIFY COLUMN gate_type TEXT NOT NULL;
