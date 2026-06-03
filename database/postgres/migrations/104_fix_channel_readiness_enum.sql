-- SOURCE: Master Spec §6.5 channel superset; CLAUDE.md channel priority still enforced in app logic.

ALTER TABLE channel_readiness
  MODIFY COLUMN channel TEXT
  NOT NULL;
