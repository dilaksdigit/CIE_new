-- SOURCE: CIE_v232_UI_Restructure_Instructions.docx — weekly_scores table
ALTER TABLE weekly_scores
    CHANGE COLUMN week_start week_start_date DATE NOT NULL,
    ADD COLUMN writer_user_id CHAR(36) NOT NULL AFTER id,
    ADD COLUMN created_by CHAR(36) NOT NULL AFTER notes;
