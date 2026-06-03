-- SOURCE: CIE Validation Report HR-01 | CLAUDE.md Section 4 (DECISION-001) — Amazon deferred. Channels: shopify, gmc only.

-- Add new enum values, then migrate data, then drop old values
ALTER TABLE channel_readiness
  MODIFY COLUMN channel TEXT NOT NULL;

UPDATE channel_readiness SET channel = 'gmc' WHERE channel IN ('amazon', 'google_sge', 'ai_assistants');
UPDATE channel_readiness SET channel = 'shopify' WHERE channel = 'own_website';

ALTER TABLE channel_readiness
  MODIFY COLUMN channel TEXT NOT NULL;
