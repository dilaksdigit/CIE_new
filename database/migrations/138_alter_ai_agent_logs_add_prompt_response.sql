-- Audit ref: A9.4 — Phase 4 pattern engine: optional full prompt + response text
SET NAMES utf8mb4;

ALTER TABLE ai_agent_logs
  ADD COLUMN IF NOT EXISTS prompt_text TEXT NULL AFTER prompt_hash,
  ADD COLUMN IF NOT EXISTS response_text TEXT NULL AFTER prompt_text;
