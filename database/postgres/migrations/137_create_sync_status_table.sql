-- OPERATIONAL (not part of canonical spec §6 schema list): optional health rows for
-- dashboard / ops. Python weekly_ga4_sync tolerates absence of this table (debug-only).
-- SOURCE: operational alignment with CIE_Master_Developer_Build_Spec.docx §9.5 behaviour notes.

CREATE TABLE IF NOT EXISTS sync_status (
  id                INT NOT NULL ,
  service           VARCHAR(30) NOT NULL,
  status            VARCHAR(30) NOT NULL DEFAULT 'ok',
  last_success_at   TIMESTAMP NULL,
  last_error        VARCHAR(1000) NULL,
  last_error_at     TIMESTAMP NULL,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

INSERT INTO sync_status (service, status)
VALUES ('ga4', 'ok')
ON DUPLICATE KEY UPDATE service = VALUES(service);
