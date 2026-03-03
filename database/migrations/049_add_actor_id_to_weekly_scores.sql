-- SOURCE: CIE_Master_Developer_Build_Spec.docx — weekly_scores actor_id
-- SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §9

ALTER TABLE weekly_scores
  ADD COLUMN actor_id CHAR(36) NULL AFTER created_at,
  ADD CONSTRAINT fk_weekly_scores_actor
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL;

