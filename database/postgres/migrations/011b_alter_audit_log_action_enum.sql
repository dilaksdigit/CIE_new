-- SOURCE: CIE_v231_Developer_Build_Pack.pdf audit_log schema
ALTER TABLE audit_log
    MODIFY COLUMN action TEXT NOT NULL;
