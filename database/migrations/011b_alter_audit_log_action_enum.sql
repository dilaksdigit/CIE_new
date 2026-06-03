-- SOURCE: CIE_v231_Developer_Build_Pack.pdf audit_log schema
ALTER TABLE audit_log
    MODIFY COLUMN action ENUM(
        'create','update','delete','publish','validate',
        'tier_change','gate_pass','gate_fail','audit_run',
        'brief_generated','escalation','login','permission_change'
    ) NOT NULL;
