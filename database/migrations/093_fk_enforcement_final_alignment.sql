-- SOURCE: CLAUDE.md §9 — Foreign keys enforced on all relationships
-- SOURCE: CIE_Master_Developer_Build_Spec §6 — FK constraints at DB level
-- PURPOSE: Final deterministic FK alignment after legacy/patch drift.
-- ADDITIVE/SAFE: idempotent checks before each DDL operation.

SET NAMES utf8mb4;

-- -------------------------------------------------------------------
-- 1) Canonicalize retry-queue FKs (replace legacy validation_retry_queue path)
-- -------------------------------------------------------------------
SET @tbl_vrq := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vector_retry_queue'
);

SET @fk_rq_sku := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vector_retry_queue'
      AND CONSTRAINT_NAME = 'fk_retry_queue_sku'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_rq_sku := IF(@tbl_vrq = 0,
    'SELECT ''skip fk_retry_queue_sku (vector_retry_queue missing)'' AS msg',
    IF(@fk_rq_sku = 0,
        'ALTER TABLE vector_retry_queue ADD CONSTRAINT fk_retry_queue_sku FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE',
        'SELECT ''fk_retry_queue_sku already present'' AS msg'
    )
);
PREPARE stmt_rq_sku FROM @sql_rq_sku;
EXECUTE stmt_rq_sku;
DEALLOCATE PREPARE stmt_rq_sku;

SET @fk_rq_cl := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vector_retry_queue'
      AND CONSTRAINT_NAME = 'fk_retry_queue_cluster'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_rq_cl := IF(@tbl_vrq = 0,
    'SELECT ''skip fk_retry_queue_cluster (vector_retry_queue missing)'' AS msg',
    IF(@fk_rq_cl = 0,
        'ALTER TABLE vector_retry_queue ADD CONSTRAINT fk_retry_queue_cluster FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE CASCADE',
        'SELECT ''fk_retry_queue_cluster already present'' AS msg'
    )
);
PREPARE stmt_rq_cl FROM @sql_rq_cl;
EXECUTE stmt_rq_cl;
DEALLOCATE PREPARE stmt_rq_cl;

-- -------------------------------------------------------------------
-- 2) Ensure sku_gate_status FK exists (was dropped in 079, restored in 091)
-- -------------------------------------------------------------------
SET @tbl_sgs := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sku_gate_status'
);
SET @fk_sgs := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sku_gate_status'
      AND CONSTRAINT_NAME = 'fk_gate_status_sku_master'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_sgs := IF(@tbl_sgs = 0,
    'SELECT ''skip fk_gate_status_sku_master (sku_gate_status missing)'' AS msg',
    IF(@fk_sgs = 0,
        'ALTER TABLE sku_gate_status ADD CONSTRAINT fk_gate_status_sku_master FOREIGN KEY (sku_id) REFERENCES sku_master(sku_id) ON DELETE CASCADE',
        'SELECT ''fk_gate_status_sku_master already present'' AS msg'
    )
);
PREPARE stmt_sgs FROM @sql_sgs;
EXECUTE stmt_sgs;
DEALLOCATE PREPARE stmt_sgs;

-- -------------------------------------------------------------------
-- 3) Ensure faq_templates.cluster_id type + FK are canonical
-- -------------------------------------------------------------------
SET @ft_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'faq_templates'
      AND COLUMN_NAME = 'cluster_id'
);
SET @sql_ft_mod := IF(@ft_col > 0,
    'ALTER TABLE faq_templates MODIFY COLUMN cluster_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
    'SELECT ''skip faq_templates.cluster_id canonicalize (column missing)'' AS msg'
);
PREPARE stmt_ft_mod FROM @sql_ft_mod;
EXECUTE stmt_ft_mod;
DEALLOCATE PREPARE stmt_ft_mod;

SET @fk_ft := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'faq_templates'
      AND CONSTRAINT_NAME = 'fk_faq_templates_cluster'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_ft_fk := IF(@ft_col = 0,
    'SELECT ''skip fk_faq_templates_cluster (cluster_id missing)'' AS msg',
    IF(@fk_ft = 0,
        'ALTER TABLE faq_templates ADD CONSTRAINT fk_faq_templates_cluster FOREIGN KEY (cluster_id) REFERENCES cluster_master(cluster_id) ON DELETE SET NULL',
        'SELECT ''fk_faq_templates_cluster already present'' AS msg'
    )
);
PREPARE stmt_ft_fk FROM @sql_ft_fk;
EXECUTE stmt_ft_fk;
DEALLOCATE PREPARE stmt_ft_fk;

-- -------------------------------------------------------------------
-- 4) Resolve blocked relationship: staff_effort_logs.category_id
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_effort_categories (
    id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    code        VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
    label       VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @tbl_sel := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_effort_logs'
);
SET @col_sel := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff_effort_logs'
      AND COLUMN_NAME = 'category_id'
);

-- Null out orphan category_ids before adding FK
SET @sql_sel_clean := IF(@tbl_sel = 0 OR @col_sel = 0,
    'SELECT ''skip staff_effort_logs.category_id cleanup (table/column missing)'' AS msg',
    'UPDATE staff_effort_logs SET category_id = NULL WHERE category_id IS NOT NULL AND category_id NOT IN (SELECT id FROM staff_effort_categories)'
);
PREPARE stmt_sel_clean FROM @sql_sel_clean;
EXECUTE stmt_sel_clean;
DEALLOCATE PREPARE stmt_sel_clean;

SET @fk_sel := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff_effort_logs'
      AND CONSTRAINT_NAME = 'fk_staff_effort_logs_category'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_sel_fk := IF(@tbl_sel = 0 OR @col_sel = 0,
    'SELECT ''skip fk_staff_effort_logs_category (table/column missing)'' AS msg',
    IF(@fk_sel = 0,
        'ALTER TABLE staff_effort_logs ADD CONSTRAINT fk_staff_effort_logs_category FOREIGN KEY (category_id) REFERENCES staff_effort_categories(id) ON DELETE SET NULL',
        'SELECT ''fk_staff_effort_logs_category already present'' AS msg'
    )
);
PREPARE stmt_sel_fk FROM @sql_sel_fk;
EXECUTE stmt_sel_fk;
DEALLOCATE PREPARE stmt_sel_fk;
