-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.1 — AI audit run Monday 09:00 UTC
SET NAMES utf8mb4;

UPDATE business_rules
SET value = '0 9 * * 1', updated_at = CURRENT_TIMESTAMP
WHERE rule_key = 'sync.ai_audit_cron_schedule';
