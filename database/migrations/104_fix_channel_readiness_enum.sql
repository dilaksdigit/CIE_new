-- SOURCE: Master Spec §6.5 channel superset; CLAUDE.md channel priority still enforced in app logic.

SET NAMES utf8mb4;

ALTER TABLE channel_readiness
  MODIFY COLUMN channel ENUM('shopify','gmc','google_sge','amazon','ai_assistants','own_website')
  NOT NULL;
