-- SOURCE: CIE_Master_Developer_Build_Spec.docx §9.3
-- Track source system for unmatched URLs (gsc vs ga4).

ALTER TABLE gsc_unmatched_urls
  ADD COLUMN IF NOT EXISTS source VARCHAR(10)
    NOT NULL DEFAULT 'gsc' AFTER url;
