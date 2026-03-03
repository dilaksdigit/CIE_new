-- SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx Section 1
-- SOURCE: README_First_CIE_v232_Developer_README.docx Phase 6
-- Creates the weekly_scores table — the ONLY new database table permitted in v2.3.2

CREATE TABLE IF NOT EXISTS weekly_scores (
  id          INT          NOT NULL AUTO_INCREMENT,
  week_start  DATE         NOT NULL,
  score       INT          NOT NULL CHECK (score >= 1 AND score <= 10),
  notes       TEXT         NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

