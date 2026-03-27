SET NAMES utf8mb4;

-- SOURCE: CIE_Master_Developer_Build_Spec.docx §9.3
-- Track source system for unmatched URLs (gsc vs ga4).

ALTER TABLE gsc_unmatched_urls
  ADD COLUMN IF NOT EXISTS source VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT 'gsc' AFTER url;
