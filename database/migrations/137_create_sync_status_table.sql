SET NAMES utf8mb4;

-- OPERATIONAL (not part of canonical spec §6 schema list): optional health rows for
-- dashboard / ops. Python weekly_ga4_sync tolerates absence of this table (debug-only).
-- SOURCE: operational alignment with CIE_Master_Developer_Build_Spec.docx §9.5 behaviour notes.

CREATE TABLE IF NOT EXISTS sync_status (
  id                INT NOT NULL AUTO_INCREMENT,
  service           VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  status            VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  last_success_at   TIMESTAMP NULL,
  last_error        VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  last_error_at     TIMESTAMP NULL,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sync_status_service (service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sync_status (service, status)
VALUES ('ga4', 'ok')
ON DUPLICATE KEY UPDATE service = VALUES(service);
