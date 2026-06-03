-- SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 1 (Integration Checklist — vector_retry_queue)
-- Adds resolved flag and attempted_at for fail-soft vector validation tracking.

SET NAMES utf8mb4;

ALTER TABLE vector_retry_queue
  ADD COLUMN attempted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER next_retry_at;

ALTER TABLE vector_retry_queue
  ADD COLUMN resolved TINYINT(1) NOT NULL DEFAULT 0 AFTER attempted_at;
