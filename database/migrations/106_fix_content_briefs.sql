-- SOURCE: Master Spec §6.5 content_briefs parity.

SET NAMES utf8mb4;

ALTER TABLE content_briefs
  ADD COLUMN IF NOT EXISTS failing_questions JSON NULL,
  ADD COLUMN IF NOT EXISTS current_answer_block TEXT NULL,
  ADD COLUMN IF NOT EXISTS competitor_answers JSON NULL,
  ADD COLUMN IF NOT EXISTS ai_suggested_revision TEXT NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('open','in_progress','completed','overdue') DEFAULT 'open',
  ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL;
