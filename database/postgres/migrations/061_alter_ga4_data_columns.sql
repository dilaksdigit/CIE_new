-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6
-- Rename sessions to organic_sessions to match spec column name exactly.
ALTER TABLE ga4_data
    CHANGE COLUMN sessions organic_sessions INT NOT NULL DEFAULT 0;
